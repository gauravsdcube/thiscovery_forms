<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $form_id
 * @property string $language
 * @property string|null $title
 * @property string|null $description
 * @property string|null $thank_you_content
 *
 * @property-read CustomForm $form
 */
class FormI18n extends ActiveRecord
{
    public static function tableName()
    {
        return 'custom_form_i18n';
    }

    public function rules()
    {
        return [
            [['form_id', 'language'], 'required'],
            [['form_id'], 'integer'],
            [['language'], 'string', 'max' => 16],
            [['title'], 'string', 'max' => 255],
            [['description', 'thank_you_content'], 'string'],
        ];
    }

    public function getForm(): ActiveQuery
    {
        return $this->hasOne(CustomForm::class, ['id' => 'form_id']);
    }

    public function isEmpty(): bool
    {
        return trim((string)$this->title) === ''
            && trim((string)$this->description) === ''
            && trim((string)$this->thank_you_content) === '';
    }
}
