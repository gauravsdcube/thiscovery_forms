<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $form_id
 * @property int $answer_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $from_value
 * @property string|null $to_value
 * @property string|null $reason
 * @property string $created_at
 *
 * @property-read User|null $user
 */
class FormIntegrityAudit extends ActiveRecord
{
    public static function tableName()
    {
        return 'custom_form_integrity_audit';
    }

    public function rules()
    {
        return [
            [['form_id', 'answer_id', 'action'], 'required'],
            [['form_id', 'answer_id', 'user_id'], 'integer'],
            [['action'], 'string', 'max' => 48],
            [['from_value', 'to_value'], 'string', 'max' => 64],
            [['reason', 'created_at'], 'safe'],
        ];
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public static function record(
        int $formId,
        int $answerId,
        string $action,
        ?string $from,
        ?string $to,
        ?string $reason = null
    ): self {
        $row = new self();
        $row->form_id = $formId;
        $row->answer_id = $answerId;
        $row->user_id = \Yii::$app->user->id ?: null;
        $row->action = $action;
        $row->from_value = $from;
        $row->to_value = $to;
        $row->reason = $reason;
        $row->created_at = date('Y-m-d H:i:s');
        $row->save(false);
        return $row;
    }
}
