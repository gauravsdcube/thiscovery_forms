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
        foreach ($answers as $answer) {
            if ((int)$answer->status !== FormAnswer::STATUS_COMPLETE) {
                continue;
            }
            $this->unlinkActivity($answer, $runId, $now, $ranBy);
            foreach (['created_by', 'updated_by', 'panel_member_id', 'resume_email'] as $column) {
                $this->clearAnswerColumn($answer, $column, $runId, $now, $ranBy);
            }
            $meta = FormIntegrityMeta::findOne(['answer_id' => (int)$answer->id]);
            if ($meta && $meta->access_token_hash !== null && $meta->access_token_hash !== '') {
                $this->writeLog($runId, $now, $ranBy, (int)$answer->id, FormIntegrityMeta::tableName(), (int)$meta->id, 'access_token_hash', (string)$meta->access_token_hash, false);
                $meta->updateAttributes(['access_token_hash' => null]);
            }
        }
        return $runId;
    }

    public function reverse(string $runId): int
    {
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
            FormIntegrityMeta::tableName() => ['access_token_hash'],
        ];
        return isset($allowed[$table]) && in_array($column, $allowed[$table], true);
    }
}
