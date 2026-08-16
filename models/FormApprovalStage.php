<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\Group;
use humhub\modules\user\models\User;
use Yii;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $form_id
 * @property string $name
 * @property int $sort_order
 * @property int $require_all
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property-read CustomForm $form
 * @property-read FormApprovalAuthority[] $authorities
 */
class FormApprovalStage extends ActiveRecord
{
    public static function tableName()
    {
        return 'custom_form_approval_stage';
    }

    public function rules()
    {
        return [
            [['form_id', 'name'], 'required'],
            [['form_id', 'sort_order', 'require_all'], 'integer'],
            [['require_all'], 'default', 'value' => 0],
            [['name'], 'string', 'max' => 255],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    public function beforeSave($insert)
    {
        $now = date('Y-m-d H:i:s');
        if ($insert && !$this->created_at) {
            $this->created_at = $now;
        }
        $this->updated_at = $now;
        return parent::beforeSave($insert);
    }

    public function requiresAll(): bool
    {
        return (bool)$this->require_all;
    }

    public function getForm(): ActiveQuery
    {
        return $this->hasOne(CustomForm::class, ['id' => 'form_id']);
    }

    public function getAuthorities(): ActiveQuery
    {
        return $this->hasMany(FormApprovalAuthority::class, ['stage_id' => 'id']);
    }

    /**
     * @return User[]
     */
    public function getAuthorityUsers(): array
    {
        $users = [];
        foreach ($this->authorities as $authority) {
            if ($authority->isUser() && $authority->user) {
                $users[$authority->user->id] = $authority->user;
            }
        }
        return array_values($users);
    }

    /**
     * @return int[]
     */
    public function getAuthorityGroupIds(): array
    {
        $ids = [];
        foreach ($this->authorities as $authority) {
            if ($authority->isGroup() && $authority->group_id) {
                $ids[] = (int)$authority->group_id;
            }
        }
        return array_values(array_unique($ids));
    }

    public function getAuthoritySummary(): string
    {
        $parts = [];
        foreach ($this->getAuthorityUsers() as $user) {
            $parts[] = $user->displayName;
        }
        foreach ($this->authorities as $authority) {
            if ($authority->isGroup() && $authority->group) {
                $parts[] = Yii::t('ThiscoveryFormsModule.base', 'Group: {name}', [
                    'name' => $authority->group->name,
                ]);
            }
        }
        if (!$parts) {
            return Yii::t('ThiscoveryFormsModule.base', 'Form managers');
        }
        return implode(', ', $parts);
    }
}
