<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormIntegrityMeta;
use humhub\modules\thiscoveryForms\models\FormPanelActivity;
use humhub\modules\thiscoveryVersioning\models\VersionRevision;
use Yii;
use yii\db\Query;

/**
 * Strip respondent identity from complete answers on fully anonymous forms.
 *
 * File.created_by is left unchanged. A file can outlive the answer and is
 * owned by HumHub's file table; clearing the uploader there would hide them
 * from the file manager without unlinking the upload. The answer's own
 * created_by is cleared, which is the identity the study tools display.
 */
class IdentityRepair
{
    /**
     * The log keeps the removed identities only so a mistaken run can be reversed. After
     * this many days they are purged, so the log stops being a map back to people (V3-30).
     */
    public const REVERSAL_DAYS = 14;

    /**
     * @return CustomForm[]
     */
    public function candidateForms(): array
    {
        $forms = [];
        foreach (CustomForm::find()->each(50) as $form) {
            if ($form->getIdentityMode() === CustomForm::IDENTITY_FULLY_ANONYMOUS) {
                $forms[] = $form;
            }
        }
        return $forms;
    }

    /**
     * When the published definition first changed to fully anonymous.
     * Null when revision history does not show that change.
     */
    public function sinceFor(CustomForm $form): ?string
    {
        if (!class_exists(VersionRevision::class)) {
            return null;
        }
        $rows = VersionRevision::find()
            ->where(['owner_type' => FormVersionAdapter::OWNER_TYPE, 'owner_id' => (int)$form->id])
            ->orderBy(['revision_number' => SORT_ASC])
            ->all();
        $previous = null;
        foreach ($rows as $revision) {
            $anonymous = $this->snapshotAnonymous((string)$revision->snapshot_json);
            if ($anonymous && $previous === false && $revision->created_at) {
                return (string)$revision->created_at;
            }
            $previous = $anonymous;
        }
        return null;
    }

    /**
     * Complete, non-test answers at or after $since. An unknown cutoff selects nothing.
     *
     * @return FormAnswer[]
     */
    public function selectAnswers(CustomForm $form, ?string $since): array
    {
        $since = trim((string)$since);
        if ($since === '') {
            return [];
        }
        return FormAnswer::find()
            ->where([
                'form_id' => (int)$form->id,
                'status' => FormAnswer::STATUS_COMPLETE,
                'is_test' => 0,
            ])
            ->andWhere(['or',
                ['>=', 'submitted_at', $since],
                ['and', ['submitted_at' => null], ['>=', 'created_at', $since]],
            ])
            ->all();
    }

    /**
     * @param FormAnswer[] $answers
     */
    public function apply(array $answers, ?int $ranBy): string
    {
        $runId = Yii::$app->security->generateRandomString(24);
        $now = date('Y-m-d H:i:s');
        $db = Yii::$app->db;
        $tx = $db->getTransaction() ? null : $db->beginTransaction();
        try {
            foreach ($answers as $answer) {
                if ((int)$answer->status !== FormAnswer::STATUS_COMPLETE) {
                    continue;
                }
                $this->unlinkActivity($answer, $runId, $now, $ranBy);
                foreach (['created_by', 'updated_by', 'panel_member_id', 'resume_email'] as $column) {
                    $this->clearAnswerColumn($answer, $column, $runId, $now, $ranBy);
                }
                $meta = FormIntegrityMeta::findOne(['answer_id' => (int)$answer->id]);
                if ($meta) {
                    // Client hashes join this answer to the same person's other answers.
                    foreach (['access_token_hash', 'ip_hash', 'ip_network_hash', 'session_hash', 'user_agent_hash'] as $column) {
                        $this->clearRowColumn(FormIntegrityMeta::tableName(), (int)$meta->id, $column, $meta->$column, (int)$answer->id, $runId, $now, $ranBy);
                    }
                }
                foreach ($this->linkedRows('{{%form_email_send}}', (int)$answer->id) as $row) {
                    $this->clearRowColumn('{{%form_email_send}}', (int)$row['id'], 'answer_id', $row['answer_id'], (int)$answer->id, $runId, $now, $ranBy);
                    $this->dateOnly('{{%form_email_send}}', $row, 'created_at', (int)$answer->id, $runId, $now, $ranBy);
                }
                foreach ($this->linkedRows('{{%custom_form_consent_record}}', (int)$answer->id) as $row) {
                    $this->clearRowColumn('{{%custom_form_consent_record}}', (int)$row['id'], 'answer_id', $row['answer_id'], (int)$answer->id, $runId, $now, $ranBy);
                    $this->dateOnly('{{%custom_form_consent_record}}', $row, 'signed_at', (int)$answer->id, $runId, $now, $ranBy);
                }
            }
            if ($tx) {
                $tx->commit();
            }
        } catch (\Throwable $e) {
            if ($tx) {
                $tx->rollBack();
            }
            throw $e;
        }
        return $runId;
    }

    /** Drop logged identities older than the reversal window. */
    public function purgeExpired(): int
    {
        if (Yii::$app->db->schema->getTableSchema('custom_form_identity_repair_log', true) === null) {
            return 0;
        }
        return Yii::$app->db->createCommand()->delete('custom_form_identity_repair_log', [
            '<', 'ran_at', date('Y-m-d H:i:s', time() - self::REVERSAL_DAYS * 86400),
        ])->execute();
    }

    /** Confirm a run: its logged identities are deleted and it can no longer be reversed. */
    public function finalise(string $runId): int
    {
        return Yii::$app->db->createCommand()->delete('custom_form_identity_repair_log', ['run_id' => $runId])->execute();
    }

    public function reverse(string $runId): int
    {
        $this->purgeExpired();
        $rows = (new Query())
            ->from('custom_form_identity_repair_log')
            ->where(['run_id' => $runId])
            ->orderBy(['id' => SORT_DESC])
            ->all();
        $restored = 0;
        foreach ($rows as $row) {
            $table = (string)$row['table_name'];
            $column = (string)$row['column_name'];
            if (!$this->allowedChange($table, $column)) {
                continue;
            }
            $value = (int)$row['old_is_null'] === 1 ? null : $row['old_value'];
            Yii::$app->db->createCommand()->update($table, [$column => $value], ['id' => (int)$row['row_id']])->execute();
            $restored++;
        }
        return $restored;
    }

    private function unlinkActivity(FormAnswer $answer, string $runId, string $now, ?int $ranBy): void
    {
        $rows = FormPanelActivity::find()->where(['answer_id' => (int)$answer->id])->all();
        foreach ($rows as $activity) {
            if ($activity->wave_id === null && $answer->wave_id) {
                $this->writeLog($runId, $now, $ranBy, (int)$answer->id, FormPanelActivity::tableName(), (int)$activity->id, 'wave_id', null, true);
                $activity->wave_id = (int)$answer->wave_id;
            }
            if ($activity->round_id === null && $answer->round_id) {
                $this->writeLog($runId, $now, $ranBy, (int)$answer->id, FormPanelActivity::tableName(), (int)$activity->id, 'round_id', null, true);
                $activity->round_id = (int)$answer->round_id;
            }
            $this->writeLog($runId, $now, $ranBy, (int)$answer->id, FormPanelActivity::tableName(), (int)$activity->id, 'answer_id', (string)$activity->answer_id, false);
            $activity->answer_id = null;
            $activity->save(false);
        }
    }

    private function clearAnswerColumn(FormAnswer $answer, string $column, string $runId, string $now, ?int $ranBy): void
    {
        $value = $answer->$column;
        if ($value === null || $value === '') {
            return;
        }
        $this->writeLog($runId, $now, $ranBy, (int)$answer->id, FormAnswer::tableName(), (int)$answer->id, $column, (string)$value, false);
        $answer->updateAttributes([$column => null]);
        $answer->$column = null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function linkedRows(string $table, int $answerId): array
    {
        if (Yii::$app->db->schema->getTableSchema($table, true) === null) {
            return [];
        }
        return (new Query())->from($table)->where(['answer_id' => $answerId])->all();
    }

    private function clearRowColumn(string $table, int $rowId, string $column, $value, int $answerId, string $runId, string $now, ?int $ranBy): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $this->writeLog($runId, $now, $ranBy, $answerId, $table, $rowId, $column, (string)$value, false);
        Yii::$app->db->createCommand()->update($table, [$column => null], ['id' => $rowId])->execute();
    }

    /**
     * @param array<string,mixed> $row
     */
    private function dateOnly(string $table, array $row, string $column, int $answerId, string $runId, string $now, ?int $ranBy): void
    {
        $value = (string)($row[$column] ?? '');
        if (strlen($value) < 19 || str_ends_with($value, '00:00:00')) {
            return;
        }
        $this->writeLog($runId, $now, $ranBy, $answerId, $table, (int)$row['id'], $column, $value, false);
        Yii::$app->db->createCommand()->update($table, [$column => substr($value, 0, 10) . ' 00:00:00'], ['id' => (int)$row['id']])->execute();
    }

    private function writeLog(string $runId, string $now, ?int $ranBy, int $answerId, string $table, int $rowId, string $column, ?string $old, bool $wasNull): void
    {
        Yii::$app->db->createCommand()->insert('custom_form_identity_repair_log', [
            'run_id' => $runId,
            'ran_at' => $now,
            'ran_by' => $ranBy,
            'answer_id' => $answerId,
            'table_name' => $table,
            'row_id' => $rowId,
            'column_name' => $column,
            'old_value' => $wasNull ? null : $old,
            'old_is_null' => $wasNull ? 1 : 0,
        ])->execute();
    }

    private function snapshotAnonymous(string $json): bool
    {
        $snapshot = json_decode($json, true);
        if (!is_array($snapshot)) {
            return false;
        }
        $settings = json_decode((string)($snapshot['meta']['settings_json'] ?? ''), true);
        if (!is_array($settings)) {
            return false;
        }
        return ($settings['identity_mode'] ?? '') === CustomForm::IDENTITY_FULLY_ANONYMOUS;
    }

    private function allowedChange(string $table, string $column): bool
    {
        $allowed = [
            FormAnswer::tableName() => ['created_by', 'updated_by', 'panel_member_id', 'resume_email'],
            FormPanelActivity::tableName() => ['answer_id', 'wave_id', 'round_id'],
            FormIntegrityMeta::tableName() => ['access_token_hash', 'ip_hash', 'ip_network_hash', 'session_hash', 'user_agent_hash'],
            '{{%form_email_send}}' => ['answer_id', 'created_at'],
            '{{%custom_form_consent_record}}' => ['answer_id', 'signed_at'],
        ];
        return isset($allowed[$table]) && in_array($column, $allowed[$table], true);
    }
}
