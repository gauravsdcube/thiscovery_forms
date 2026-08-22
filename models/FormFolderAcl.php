<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\Group;
use humhub\modules\user\models\User;
use Yii;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $folder_id
 * @property int|null $group_id
 * @property int|null $user_id
 * @property int $can_view
 * @property int $can_create
 * @property int $can_manage
 *
 * @property-read FormFolder $folder
 * @property-read Group|null $group
 * @property-read User|null $user
 */
class FormFolderAcl extends ActiveRecord
{
    public static function tableName()
    {
        return 'custom_form_folder_acl';
    }

    public function rules()
    {
        return [
            [['folder_id'], 'required'],
            [['folder_id', 'group_id', 'user_id'], 'integer'],
            [['can_view', 'can_create', 'can_manage'], 'boolean'],
            [['can_view'], 'default', 'value' => 1],
            [['can_create', 'can_manage'], 'default', 'value' => 0],
        ];
    }

    public function attributeLabels()
    {
        return [
            'group_id' => Yii::t('ThiscoveryFormsModule.base', 'Group'),
            'user_id' => Yii::t('ThiscoveryFormsModule.base', 'User'),
            'can_view' => Yii::t('ThiscoveryFormsModule.base', 'View'),
            'can_create' => Yii::t('ThiscoveryFormsModule.base', 'Create forms'),
            'can_manage' => Yii::t('ThiscoveryFormsModule.base', 'Manage folder'),
        ];
    }

    public function getFolder(): ActiveQuery
    {
        return $this->hasOne(FormFolder::class, ['id' => 'folder_id']);
    }

    public function getGroup(): ActiveQuery
    {
        return $this->hasOne(Group::class, ['id' => 'group_id']);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getSubjectLabel(): string
    {
        if ($this->group) {
            return $this->group->name;
        }
        if ($this->user) {
            return $this->user->displayName;
        }
        return Yii::t('ThiscoveryFormsModule.base', 'Unknown');
    }
}
