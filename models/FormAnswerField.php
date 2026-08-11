<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use humhub\modules\file\models\File;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $answer_id
 * @property int $field_id
 * @property string|null $value
 *
 * @property-read FormAnswer $answer
 * @property-read FormField $field
 */
class FormAnswerField extends ActiveRecord
{
    public static function tableName()
    {
        return 'custom_form_answer_field';
    }

    public function rules()
    {
        return [
            [['answer_id', 'field_id'], 'required'],
            [['answer_id', 'field_id'], 'integer'],
            [['value'], 'string'],
        ];
    }

    public function getAnswer(): ActiveQuery
    {
        return $this->hasOne(FormAnswer::class, ['id' => 'answer_id']);
    }

    public function getField(): ActiveQuery
    {
        return $this->hasOne(FormField::class, ['id' => 'field_id']);
    }

    public function getDisplayValue(): string
    {
        if ($this->value === null || $this->value === '') {
            return '';
        }

        $field = $this->field;
        $decoded = json_decode($this->value, true);

        if ($field && $field->type === FormField::TYPE_RANKING) {
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $parts = [];
                foreach ($decoded as $index => $item) {
                    $parts[] = ((int)$index + 1) . '. ' . (string)$item;
                }
                return implode(', ', $parts);
            }
            return (string)$this->value;
        }

        if ($field && $field->type === FormField::TYPE_RATING) {
            return (string)$this->value;
        }

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return implode(', ', $decoded);
        }

        if ($field && $field->type === FormField::TYPE_FILE) {
            $file = File::findOne(['guid' => $this->value]);
            return $file ? $file->file_name : $this->value;
        }

        return (string)$this->value;
    }

    public function beforeDelete()
    {
        if ($this->field && $this->field->type === FormField::TYPE_FILE && $this->value) {
            $file = File::findOne(['guid' => $this->value]);
            if ($file) {
                $file->delete();
            }
        }
        return parent::beforeDelete();
    }
}
