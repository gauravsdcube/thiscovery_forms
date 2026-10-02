<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\FormAnswer;
use Yii;

/**
 * An edit stores the previous value, the new value, who changed it, and why.
 */
class AnswerAudit
{
    public static function tableReady(): bool
    {
        return Yii::$app->db->schema->getTableSchema('{{%custom_form_answer_audit}}', true) !== null;
    }

    public static function record(
        FormAnswer $answer,
        int $fieldId,
        string $instanceKey,
        ?string $oldValue,
        ?string $newValue,
        string $reason = 'edit'
    ): void {
        if (!self::tableReady() || !$answer->id || $fieldId < 1) {
            return;
        }
        if ($oldValue === null) {
            return;
        }
        if ((string)$oldValue === (string)$newValue) {
            return;
        }
        $actor = null;
        $userId = (int)Yii::$app->user->id;
        if ($userId > 0 && $answer->created_by !== null) {
            $actor = $userId;
        }
        $reason = trim($reason);
        if ($reason === '') {
            $reason = 'edit';
        }
        Yii::$app->db->createCommand()->insert('{{%custom_form_answer_audit}}', [
            'answer_id' => (int)$answer->id,
            'field_id' => $fieldId,
            'instance_key' => mb_substr($instanceKey, 0, 191),
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'actor_id' => $actor,
            'reason' => mb_substr($reason, 0, 255),
            'created_at' => date('Y-m-d H:i:s'),
        ])->execute();
    }
}
