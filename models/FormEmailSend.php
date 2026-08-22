<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int|null $template_id
 * @property int|null $form_id
 * @property int|null $member_id
 * @property int|null $answer_id
 * @property int|null $wave_id
 * @property int|null $field_id
 * @property string $kind
 * @property string|null $email
 * @property string|null $created_at
 */
class FormEmailSend extends ActiveRecord
{
    public const KIND_INVITE = 'invite';
    public const KIND_WAVE = 'wave';
    public const KIND_REMINDER = 'reminder';
    public const KIND_COMPLETION = 'completion';
    public const KIND_ACTION = 'action';
    public const KIND_BUTTON = 'button';

    public static function tableName()
    {
        return 'form_email_send';
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($insert && !$this->created_at) {
            $this->created_at = date('Y-m-d H:i:s');
        }
        return true;
    }

    public function getTemplate(): ActiveQuery
    {
        return $this->hasOne(FormEmailTemplate::class, ['id' => 'template_id']);
    }
}
