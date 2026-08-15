<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;
use Yii;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int|null $contentcontainer_id
 * @property int|null $created_by
 * @property string $title
 * @property string|null $description
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property-read FormPanelMember[] $members
 * @property-read User|null $creator
 */
class FormPanel extends ActiveRecord
{
    public static function tableName()
    {
        return 'form_panel';
    }

    public function rules()
    {
        return [
            [['title'], 'required'],
            [['title'], 'string', 'max' => 255],
            [['description'], 'string'],
            [['contentcontainer_id', 'created_by'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'title' => Yii::t('ThiscoveryFormsModule.base', 'Panel name'),
            'description' => Yii::t('ThiscoveryFormsModule.base', 'Description'),
        ];
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        if ($insert) {
            $this->created_at = $this->created_at ?: $now;
            $this->created_by = $this->created_by ?: Yii::$app->user->id;
        }
        $this->updated_at = $now;
        return true;
    }

    public function getMembers(): ActiveQuery
    {
        return $this->hasMany(FormPanelMember::class, ['panel_id' => 'id'])
            ->orderBy(['display_name' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getActiveMembers(): ActiveQuery
    {
        return $this->getMembers()->andWhere(['status' => FormPanelMember::STATUS_ACTIVE]);
    }

    public function getCreator(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }

    public function beforeDelete()
    {
        if (!parent::beforeDelete()) {
            return false;
        }
        foreach ($this->members as $member) {
            $member->delete();
        }
        return true;
    }
}
