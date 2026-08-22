<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use humhub\modules\file\models\File;
use yii\db\ActiveQuery;
use yii\helpers\Html;

/**
 * @property int $id
 * @property int $answer_id
 * @property int $field_id
 * @property string|null $value
 * @property string|null $justification
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
            [['value', 'justification'], 'string'],
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

        if ($field && $field->type === FormField::TYPE_RESPONDENT_META) {
            return (new \humhub\modules\thiscoveryForms\services\RespondentMetaService())->formatDisplay($this->value);
        }

        if ($field && $field->type === FormField::TYPE_RANKING) {
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $parts = [];
                foreach ($decoded as $index => $item) {
                    $parts[] = ((int)$index + 1) . '. ' . $field->formatChoiceDisplay((string)$item);
                }
                return implode(', ', $parts);
            }
            return (string)$this->value;
        }

        if ($field && in_array($field->type, [
            FormField::TYPE_GRID_SINGLE,
            FormField::TYPE_GRID_MULTI,
            FormField::TYPE_BEST_WORST,
            FormField::TYPE_MAXDIFF,
            FormField::TYPE_DRILLDOWN,
            FormField::TYPE_IMAGE_AREA,
        ], true)) {
            return (new \humhub\modules\thiscoveryForms\services\VariableSubstitutor())
                ->formatAnswer($decoded !== null && json_last_error() === JSON_ERROR_NONE ? $decoded : $this->value, $field, true);
        }

        if ($field && $field->type === FormField::TYPE_RATING) {
            return (string)$this->value;
        }

        if ($field && $field->type === FormField::TYPE_FILE) {
            $file = $this->getUploadedFile();
            return $file ? $file->file_name : $this->value;
        }

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            if ($field && FormField::isChoiceType($field->type)) {
                return $field->formatChoiceDisplay($decoded);
            }
            return implode(', ', $decoded);
        }

        if ($field && FormField::isChoiceType($field->type)) {
            return $field->formatChoiceDisplay($this->value);
        }

        return (string)$this->value;
    }

    public function getUploadedFile(): ?File
    {
        if (!$this->value) {
            return null;
        }
        if ($this->field && $this->field->type !== FormField::TYPE_FILE) {
            return null;
        }
        return File::findOne(['guid' => $this->value]);
    }

    public function getAnswerHtml(): string
    {
        $file = $this->getUploadedFile();
        if ($file) {
            return Html::a(Html::encode($file->file_name), $file->getUrl(['download' => 1]), [
                'target' => '_blank',
                'rel' => 'noopener noreferrer',
            ]);
        }

        $text = $this->getDisplayValue();
        if ($text === '') {
            return '';
        }

        return nl2br(Html::encode($text));
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
