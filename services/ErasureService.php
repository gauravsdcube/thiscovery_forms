<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormPanelActivity;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use humhub\modules\user\models\User;
use Yii;

/**
 * Deleting a user keeps the research answers and removes the identity around them.
 */
class ErasureService
{
    public function pseudonymiseUser(User $user): int
    {
        $userId = (int)$user->id;
        if ($userId < 1) {
            return 0;
        }
        $count = 0;
        foreach (FormAnswer::find()->where(['created_by' => $userId])->each(100) as $answer) {
            $this->pseudonymiseAnswer($answer);
            $count++;
        }
        $members = FormPanelMember::find()->where(['user_id' => $userId])->all();
        foreach ($members as $member) {
            $member->user_id = null;
            $member->email = null;
            $member->first_name = null;
            $member->last_name = null;
            $member->display_name = null;
            $member->status = FormPanelMember::STATUS_INACTIVE;
            $member->save(false);
        }
        return $count;
    }

    public function pseudonymiseAnswer(FormAnswer $answer): void
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
            foreach (FormAnswerField::find()->where(['answer_id' => $answerId, 'field_id' => $fileFieldIds])->all() as $cell) {
                AnswerAudit::record($answer, (int)$cell->field_id, (string)$cell->instance_key, (string)$cell->value, null, 'erasure');
                $cell->delete();
            }
        }
        $answer->created_by = null;
        $answer->updated_by = null;
        $answer->resume_email = null;
        $answer->panel_member_id = null;
        $answer->save(false, ['created_by', 'updated_by', 'resume_email', 'panel_member_id', 'updated_at']);
        if (Yii::$app->db->schema->getTableSchema('{{%custom_form_erasure}}', true) !== null) {
            Yii::$app->db->createCommand()->insert('{{%custom_form_erasure}}', [
                'form_id' => (int)$answer->form_id,
                'answer_id' => $answerId,
                'kept_research' => 1,
                'erased_at' => date('Y-m-d H:i:s'),
            ])->execute();
        }
    }
}
