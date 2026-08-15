<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $field_id
 * @property string $language
 * @property string|null $label
 * @property string|null $help_text
 * @property string|null $options_json
 *
 * @property-read FormField $field
 */
class FormFieldI18n extends ActiveRecord
{
    public static function tableName()
    {
        return 'custom_form_field_i18n';
    }

    public function rules()
    {
        return [
            [['field_id', 'language'], 'required'],
            [['field_id'], 'integer'],
            [['language'], 'string', 'max' => 16],
            [['label'], 'string', 'max' => 255],
            [['help_text'], 'string', 'max' => 500],
            [['options_json'], 'string'],
        ];
    }

    public function getField(): ActiveQuery
    {
        return $this->hasOne(FormField::class, ['id' => 'field_id']);
    }

    public function getOptionsOverlay(): array
    {
        $decoded = json_decode((string)$this->options_json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function isEmpty(): bool
    {
        return trim((string)$this->label) === ''
            && trim((string)$this->help_text) === ''
            && trim((string)$this->options_json) === '';
    }
}
