<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\file\models\File;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormPanelActivity;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use humhub\modules\user\models\User;
use Yii;
use yii\db\Query;

/**
 * Deleting a user keeps the research answers and removes the identity around them.
 *
 * Answers are found by account, by panel membership (token and invite answers) and by
 * resume email. Around them, the consent signatures, withdrawals, email log, client
 * hashes, identity-repair log and audit history are cleared too (V3-30). It all runs
 * in one transaction, so a failure leaves nothing half-erased.
 *
 * A manager can also erase one response, by pseudonymising it (the answers are kept for
 * research, the identity goes) or deleting it outright, and can erase a panel member with
 * or without their answers. Every erasure is logged with its mode, actor and reason (GOV-4).
 */
class ErasureService
{
    public function pseudonymiseUser(User $user): int
    {
        $userId = (int)$user->id;
        if ($userId < 1) {
            return 0;
        }
        $db = Yii::$app->db;
        $tx = $db->getTransaction() ? null : $db->beginTransaction();
        try {
            $memberIds = array_map('intval', FormPanelMember::find()->select('id')->where(['user_id' => $userId])->column());
            $email = trim((string)$user->email);
            $where = ['or', ['created_by' => $userId]];
            if ($memberIds) {
                $where[] = ['panel_member_id' => $memberIds];
            }
            if ($email !== '') {
                $where[] = ['resume_email' => $email];
            }
            $answerIds = [];
            foreach (FormAnswer::find()->where($where)->each(100) as $answer) {
                $answerIds[] = (int)$answer->id;
                $this->pseudonymiseAnswer($answer);
            }
            $this->eraseAround($userId, $memberIds, $answerIds, $email);
            foreach (FormPanelMember::find()->where(['id' => $memberIds])->all() as $member) {
                $member->user_id = null;
                $member->email = null;
                $member->first_name = null;
                $member->last_name = null;
                $member->display_name = null;
                $member->status = FormPanelMember::STATUS_INACTIVE;
                $member->save(false);
            }
            if ($tx) {
                $tx->commit();
            }
            return count($answerIds);
        } catch (\Throwable $e) {
            if ($tx) {
                $tx->rollBack();
            }
            throw $e;
        }
    }

    public const MODE_PSEUDONYMISE = 'pseudonymise';
    public const MODE_DELETE = 'delete';

    /**
     * Delete one response and everything that hangs off it: answers, files, consent and email
     * links, integrity, audit trail, randomisation and its place in any quota (GOV-4).
     */
    public function deleteAnswer(FormAnswer $answer, ?int $actorId = null, string $reason = '', ?int $memberId = null): void
    {
        $answerId = (int)$answer->id;
        if ($answerId < 1) {
            return;
        }
        $db = Yii::$app->db;
        $tx = $db->getTransaction() ? null : $db->beginTransaction();
        try {
            $form = $answer->form;
            if ($form) {
                (new QuotaService())->releaseDeleted($form, $answer);
            }
            FormPanelActivity::updateAll(['answer_id' => null], ['answer_id' => $answerId]);
            foreach ($answer->fileManager->findAll() as $file) {
                $file->delete();
            }
            $this->eraseAround(0, [], [$answerId], '');
            foreach ([
                '{{%custom_form_arm_assignment}}', '{{%custom_form_arm_override}}', '{{%custom_form_presentation}}',
                '{{%custom_form_answer_audit}}', 'custom_form_integrity_meta', 'custom_form_identity_repair_log',
                '{{%custom_form_quota_accept}}', '{{%custom_form_quota_reservation}}',
            ] as $table) {
                $schema = $db->schema->getTableSchema($table, true);
                if ($schema !== null && isset($schema->columns['answer_id'])) {
                    $db->createCommand()->delete($table, ['answer_id' => $answerId])->execute();
                }
            }
            $formId = (int)$answer->form_id;
            $answer->delete();
            $this->log($formId, $answerId, self::MODE_DELETE, $actorId, $reason, $memberId);
            if ($tx) {
                $tx->commit();
            }
        } catch (\Throwable $e) {
            if ($tx) {
                $tx->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Erase a panel member: their contact details go, and their linked responses are either
     * kept without identity or deleted. Returns how many responses were handled (GOV-4).
     */
    public function eraseMember(FormPanelMember $member, bool $keepAnswers, ?int $actorId = null, string $reason = ''): int
    {
        $memberId = (int)$member->id;
        if ($memberId < 1) {
            return 0;
        }
        $db = Yii::$app->db;
        $tx = $db->getTransaction() ? null : $db->beginTransaction();
        try {
            $email = trim((string)$member->email);
            $answers = FormAnswer::find()->where(['panel_member_id' => $memberId])->all();
            $answerIds = [];
            foreach ($answers as $answer) {
                $answerIds[] = (int)$answer->id;
                if ($keepAnswers) {
                    $this->pseudonymiseAnswer($answer, $actorId, $reason, $memberId);
                } else {
                    $this->deleteAnswer($answer, $actorId, $reason, $memberId);
                }
            }
            $this->eraseAround(0, [$memberId], $keepAnswers ? $answerIds : [], $email);
            $member->email = null;
            $member->first_name = null;
            $member->last_name = null;
            $member->display_name = null;
            $member->user_id = null;
            $member->status = FormPanelMember::STATUS_INACTIVE;
            $member->save(false);
            if (!$answers) {
                $this->log(0, null, $keepAnswers ? self::MODE_PSEUDONYMISE : self::MODE_DELETE, $actorId, $reason, $memberId);
            }
            if ($tx) {
                $tx->commit();
            }
            return count($answers);
        } catch (\Throwable $e) {
            if ($tx) {
                $tx->rollBack();
            }
            throw $e;
        }
    }

    private function log(int $formId, ?int $answerId, string $mode, ?int $actorId, string $reason, ?int $memberId): void
    {
        $schema = Yii::$app->db->schema->getTableSchema('{{%custom_form_erasure}}', true);
        if ($schema === null) {
            return;
        }
        $row = [
            'form_id' => $formId,
            'answer_id' => $answerId ?? 0,
            'kept_research' => $mode === self::MODE_PSEUDONYMISE ? 1 : 0,
            'erased_at' => date('Y-m-d H:i:s'),
        ];
        if (isset($schema->columns['mode'])) {
            $row['answer_id'] = $answerId;
            $row['mode'] = $mode;
            $row['member_id'] = $memberId;
            $row['actor_id'] = $actorId;
            $row['reason'] = $reason !== '' ? mb_substr($reason, 0, 255) : null;
        }
        Yii::$app->db->createCommand()->insert('{{%custom_form_erasure}}', $row)->execute();
    }

    public function pseudonymiseAnswer(FormAnswer $answer, ?int $actorId = null, string $reason = '', ?int $memberId = null): void
    {
        $answerId = (int)$answer->id;
        if ($answerId < 1) {
            return;
        }
        FormPanelActivity::updateAll(['answer_id' => null], ['answer_id' => $answerId]);
        foreach ($answer->fileManager->findAll() as $file) {
            $file->delete();
        }
        $fileFieldIds = FormField::find()
            ->select('id')
            ->where(['form_id' => (int)$answer->form_id, 'type' => FormField::TYPE_FILE])
            ->column();
        if ($fileFieldIds) {
            // The file reference itself is personal data, so it is not copied into the audit.
            FormAnswerField::deleteAll(['answer_id' => $answerId, 'field_id' => $fileFieldIds]);
        }
        $answer->created_by = null;
        $answer->updated_by = null;
        $answer->resume_email = null;
        $answer->panel_member_id = null;
        $answer->save(false, ['created_by', 'updated_by', 'resume_email', 'panel_member_id', 'updated_at']);
        $this->eraseAround(0, [], [$answerId], '');
        $this->log((int)$answer->form_id, $answerId, self::MODE_PSEUDONYMISE, $actorId, $reason, $memberId);
    }

    /**
     * @param int[] $memberIds
     * @param int[] $answerIds
     */
    private function eraseAround(int $userId, array $memberIds, array $answerIds, string $email): void
    {
        $db = Yii::$app->db;
        $person = ['or'];
        if ($userId > 0) {
            $person[] = ['user_id' => $userId];
        }
        if ($memberIds) {
            $person[] = ['panel_member_id' => $memberIds];
        }
        $byPerson = count($person) > 1;

        if ($this->has('{{%custom_form_consent_record}}')) {
            $records = ['or'];
            if ($byPerson) {
                $records[] = $person;
            }
            if ($answerIds) {
                $records[] = ['answer_id' => $answerIds];
            }
            if (count($records) > 1) {
                $rows = (new Query())->select(['id', 'signature_file_id'])->from('{{%custom_form_consent_record}}')->where($records)->all();
                foreach ($rows as $row) {
                    if ($row['signature_file_id']) {
                        $file = File::findOne((int)$row['signature_file_id']);
                        if ($file) {
                            $file->delete();
                        }
                    }
                }
                $db->createCommand()->update('{{%custom_form_consent_record}}', [
                    'user_id' => null,
                    'panel_member_id' => null,
                    'signature_name' => null,
                    'signature_file_id' => null,
                    'witness_name' => null,
                    'witness_role' => null,
                    'ip_hash' => null,
                    'ua_hash' => null,
                ], ['id' => array_column($rows, 'id')])->execute();
                if ($this->has('{{%custom_form_consent_withdrawal}}') && $rows) {
                    $db->createCommand()->update('{{%custom_form_consent_withdrawal}}', [
                        'user_id' => null,
                        'panel_member_id' => null,
                        'reason' => null,
                    ], ['or', ['record_id' => array_column($rows, 'id')], $byPerson ? $person : '0=1'])->execute();
                }
            }
            if ($byPerson && $this->has('{{%custom_form_consent_requirement}}')) {
                $db->createCommand()->delete('{{%custom_form_consent_requirement}}', $person)->execute();
            }
            if ($userId > 0 && $this->has('{{%custom_form_consent_audit}}')) {
                $db->createCommand()->update('{{%custom_form_consent_audit}}', ['actor_id' => null], ['actor_id' => $userId])->execute();
            }
        }

        if ($this->has('{{%form_email_send}}')) {
            $sends = ['or'];
            if ($memberIds) {
                $sends[] = ['member_id' => $memberIds];
            }
            if ($answerIds) {
                $sends[] = ['answer_id' => $answerIds];
            }
            if ($email !== '') {
                $sends[] = ['email' => $email];
            }
            if (count($sends) > 1) {
                $db->createCommand()->update('{{%form_email_send}}', ['member_id' => null, 'email' => null], $sends)->execute();
            }
        }

        if ($answerIds && $this->has('custom_form_integrity_meta')) {
            $db->createCommand()->update('custom_form_integrity_meta', [
                'ip_hash' => null,
                'ip_network_hash' => null,
                'session_hash' => null,
                'user_agent_hash' => null,
                'access_token_hash' => null,
            ], ['answer_id' => $answerIds])->execute();
        }

        if ($this->has('custom_form_identity_repair_log')) {
            // The repair log holds the identities it removed; erasure removes them for good.
            $log = ['or'];
            if ($answerIds) {
                $log[] = ['answer_id' => $answerIds];
            }
            if ($userId > 0) {
                $log[] = ['and', ['column_name' => ['created_by', 'updated_by']], ['old_value' => (string)$userId]];
            }
            if ($memberIds) {
                $log[] = ['and', ['column_name' => 'panel_member_id'], ['old_value' => array_map('strval', $memberIds)]];
            }
            if ($email !== '') {
                $log[] = ['and', ['column_name' => 'resume_email'], ['old_value' => $email]];
            }
            if (count($log) > 1) {
                $db->createCommand()->delete('custom_form_identity_repair_log', $log)->execute();
            }
        }

        if (AnswerAudit::tableReady()) {
            if ($answerIds) {
                // Earlier values can hold anything the person typed; the current answer is kept.
                $db->createCommand()->update('{{%custom_form_answer_audit}}', [
                    'old_value' => null,
                    'new_value' => null,
                ], ['answer_id' => $answerIds])->execute();
            }
            if ($userId > 0) {
                $db->createCommand()->update('{{%custom_form_answer_audit}}', ['actor_id' => null], ['actor_id' => $userId])->execute();
            }
        }
    }

    private function has(string $table): bool
    {
        return Yii::$app->db->schema->getTableSchema($table, true) !== null;
    }
}
