<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\Group;
use humhub\modules\user\models\User;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $stage_id
 * @property string $type
 * @property int|null $user_id
 * @property int|null $group_id
 *
 * @property-read FormApprovalStage $stage
 * @property-read User|null $user
 * @property-read Group|null $group
 */
class FormApprovalAuthority extends ActiveRecord
{
    public const TYPE_USER = 'user';
    public const TYPE_GROUP = 'group';

    public static function tableName()
    {
        return 'custom_form_approval_authority';
    }

    public function rules()
    {
        return [
            [['stage_id', 'type'], 'required'],
            [['stage_id', 'user_id', 'group_id'], 'integer'],
            [['type'], 'in', 'range' => [self::TYPE_USER, self::TYPE_GROUP]],
        ];
    }

    public function isUser(): bool
    {
        return $this->type === self::TYPE_USER;
    }

    public function isGroup(): bool
    {
        return $this->type === self::TYPE_GROUP;
    }

    public function getStage(): ActiveQuery
    {
        return $this->hasOne(FormApprovalStage::class, ['id' => 'stage_id']);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getGroup(): ActiveQuery
    {
        return $this->hasOne(Group::class, ['id' => 'group_id']);
    }
}
