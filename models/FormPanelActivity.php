<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use yii\db\ActiveQuery;

/**
 * A completed form recorded against a panel member.
 *
 * @property int $id
 * @property int $panel_id
 * @property int $member_id
 * @property int $form_id
 * @property int|null $answer_id
 * @property string|null $created_at
 *
 * @property-read FormPanel $panel
 * @property-read FormPanelMember $member
 * @property-read CustomForm $form
 * @property-read FormAnswer|null $answer
 */
class FormPanelActivity extends ActiveRecord
{
    public static function tableName()
    {
        return 'form_panel_activity';
    }

    public function rules()
    {
        return [
            [['panel_id', 'member_id', 'form_id'], 'required'],
            [['panel_id', 'member_id', 'form_id', 'answer_id'], 'integer'],
            [['created_at'], 'safe'],
        ];
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

    public function getPanel(): ActiveQuery
    {
        return $this->hasOne(FormPanel::class, ['id' => 'panel_id']);
    }

    public function getMember(): ActiveQuery
    {
        return $this->hasOne(FormPanelMember::class, ['id' => 'member_id']);
    }

    public function getForm(): ActiveQuery
    {
        return $this->hasOne(CustomForm::class, ['id' => 'form_id']);
    }

    public function getAnswer(): ActiveQuery
    {
        return $this->hasOne(FormAnswer::class, ['id' => 'answer_id']);
    }
}
