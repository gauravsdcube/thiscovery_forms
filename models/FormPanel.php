<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use humhub\modules\thiscoveryForms\services\PanelFieldService;
use humhub\modules\content\models\ContentContainer;
use humhub\modules\user\models\User;
use Yii;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int|null $contentcontainer_id
 * @property int|null $created_by
 * @property string $title
 * @property string|null $description
 * @property string|null $fields_json
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
            [['description', 'fields_json'], 'string'],
            [['contentcontainer_id', 'created_by'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'title' => Yii::t('ThiscoveryFormsModule.base', 'Panel name'),
            'description' => Yii::t('ThiscoveryFormsModule.base', 'Description'),
            'fields_json' => Yii::t('ThiscoveryFormsModule.base', 'Member fields'),
        ];
    }

    /**
     * Extra attributes collected on members of this panel.
     *
     * @return array<int, array{key:string,label:string,type:string,options:string[]}>
     */
    public function getMemberFields(): array
    {
        $decoded = json_decode((string)$this->fields_json, true);
        if (!is_array($decoded)) {
            return [];
        }
        return PanelFieldService::normalizeSchema($decoded);
    }

    public function setMemberFields(array $rows): void
    {
        $fields = PanelFieldService::normalizeSchema($rows);
        $this->fields_json = $fields ? json_encode($fields, JSON_UNESCAPED_UNICODE) : null;
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

    public function getActivities(): ActiveQuery
    {
        return $this->hasMany(FormPanelActivity::class, ['panel_id' => 'id'])
            ->orderBy(['created_at' => SORT_DESC, 'id' => SORT_DESC]);
    }

    public function getActiveMemberCount(): int
    {
        return (int)$this->getActiveMembers()->count();
    }

    /**
     * Space this panel belongs to, or null for network-level panels.
     */
    public function getContentContainer()
    {
        if (!$this->contentcontainer_id) {
            return null;
        }
        $row = ContentContainer::findOne((int)$this->contentcontainer_id);
        return $row ? $row->getPolymorphicRelation() : null;
    }

    public function beforeDelete()
    {
        if (!parent::beforeDelete()) {
            return false;
        }
        FormPanelActivity::deleteAll(['panel_id' => $this->id]);
        foreach ($this->members as $member) {
            $member->delete();
        }
        return true;
    }
}
