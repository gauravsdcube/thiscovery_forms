<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;
use Yii;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $answer_id
 * @property int $stage_id
 * @property int $user_id
 * @property string $action
 * @property string|null $comment
 * @property string|null $created_at
 *
 * @property-read FormAnswer $answer
 * @property-read FormApprovalStage $stage
 * @property-read User $user
 */
class FormAnswerApproval extends ActiveRecord
{
    public const ACTION_APPROVED = 'approved';
    public const ACTION_CHANGES = 'changes_requested';
    public const ACTION_REJECTED = 'rejected';

    public static function tableName()
    {
        return 'custom_form_answer_approval';
    }

    public function rules()
    {
        return [
            [['answer_id', 'stage_id', 'user_id', 'action'], 'required'],
            [['answer_id', 'stage_id', 'user_id'], 'integer'],
            [['action'], 'in', 'range' => [self::ACTION_APPROVED, self::ACTION_CHANGES, self::ACTION_REJECTED]],
            [['comment'], 'string'],
            [['created_at'], 'safe'],
        ];
    }

    public function beforeSave($insert)
    {
        if ($insert && !$this->created_at) {
            $this->created_at = date('Y-m-d H:i:s');
        }
        return parent::beforeSave($insert);
    }

    public static function getActionLabels(): array
    {
        return [
            self::ACTION_APPROVED => Yii::t('ThiscoveryFormsModule.base', 'Approved'),
            self::ACTION_CHANGES => Yii::t('ThiscoveryFormsModule.base', 'Changes requested'),
            self::ACTION_REJECTED => Yii::t('ThiscoveryFormsModule.base', 'Rejected'),
        ];
    }

    public function getAnswer(): ActiveQuery
    {
        return $this->hasOne(FormAnswer::class, ['id' => 'answer_id']);
    }

    public function getStage(): ActiveQuery
    {
        return $this->hasOne(FormApprovalStage::class, ['id' => 'stage_id']);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
