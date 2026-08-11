<?php

namespace humhub\modules\thiscoveryForms\models;

use Yii;
use yii\base\Model;

/**
 * Validates and collects submitted field values for a CustomForm.
 */
class SubmitForm extends Model
{
    public const SCENARIO_DRAFT = 'draft';

    /** @var CustomForm */
    public $form;

    /** @var array fieldId => value */
    public $values = [];

    public function rules()
    {
        return [
            [['values'], 'safe'],
            [['values'], 'validateFields'],
        ];
    }

    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_DRAFT] = ['values'];
        return $scenarios;
    }

    public function loadValuesFromRequest($post, $files = []): bool
    {
        $this->values = [];
        $fieldPost = $post['SubmitForm']['values'] ?? ($post['values'] ?? []);
        if (!is_array($fieldPost)) {
            $fieldPost = [];
        }

        foreach ($this->form->fields as $field) {
            $key = (string)$field->id;
            if (!$field->collectsAnswer()) {
                continue;
            }
            if ($field->type === FormField::TYPE_FILE) {
                $guid = $fieldPost[$key] ?? '';
                $this->values[$field->id] = is_string($guid) ? trim($guid) : '';
                continue;
            }
            if ($field->type === FormField::TYPE_CHECKBOX) {
                $val = $fieldPost[$key] ?? [];
                $this->values[$field->id] = is_array($val) ? array_values($val) : [];
                continue;
            }
            if ($field->type === FormField::TYPE_RANKING) {
                $val = $fieldPost[$key] ?? [];
                if (is_string($val)) {
                    $decoded = json_decode($val, true);
                    $val = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
                }
                $this->values[$field->id] = is_array($val) ? array_values(array_map('strval', $val)) : [];
                continue;
            }
            if ($field->type === FormField::TYPE_HTML) {
                $val = $fieldPost[$key] ?? '';
                if (is_array($val)) {
                    $this->values[$field->id] = array_values(array_map('strval', $val));
                } else {
                    $this->values[$field->id] = is_string($val) ? trim($val) : (string)$val;
                }
                continue;
            }
            $this->values[$field->id] = isset($fieldPost[$key]) ? $fieldPost[$key] : '';
        }

        return true;
    }

    public function loadFromAnswer(FormAnswer $answer): void
    {
        $this->values = $answer->getValuesMap();
    }

    public function validateFields(): void
    {
        foreach ($this->form->fields as $field) {
            if (!$field->collectsAnswer()) {
                continue;
            }
            if (!$field->isConditionMet($this->values)) {
                continue;
            }

            $value = $this->values[$field->id] ?? null;
            $empty = $value === null || $value === '' || $value === [] ;

            $required = $field->required;
            if ($field->type === FormField::TYPE_HTML) {
                $required = (bool)$field->getHtmlConfig()['required'];
            }

            if ($required && $empty) {
                if ($this->scenario === self::SCENARIO_DRAFT) {
                    continue;
                }
                $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" is required.', [
                    'label' => $field->label,
                ]));
                continue;
            }

            if ($empty) {
                continue;
            }

            switch ($field->type) {
                case FormField::TYPE_EMAIL:
                    if (!filter_var((string)$value, FILTER_VALIDATE_EMAIL)) {
                        $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" must be a valid email.', [
                            'label' => $field->label,
                        ]));
                    }
                    break;
                case FormField::TYPE_NUMBER:
                    if (!is_numeric($value)) {
                        $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" must be a number.', [
                            'label' => $field->label,
                        ]));
                    }
                    break;
                case FormField::TYPE_DROPDOWN:
                case FormField::TYPE_RADIO:
                    if (!in_array((string)$value, $field->getOptions(), true)) {
                        $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" has an invalid option.', [
                            'label' => $field->label,
                        ]));
                    }
                    break;
                case FormField::TYPE_CHECKBOX:
                    if (!is_array($value)) {
                        $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" has an invalid value.', [
                            'label' => $field->label,
                        ]));
                        break;
                    }
                    $allowed = $field->getOptions();
                    foreach ($value as $item) {
                        if (!in_array((string)$item, $allowed, true)) {
                            $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" has an invalid option.', [
                                'label' => $field->label,
                            ]));
                            break;
                        }
                    }
                    break;
                case FormField::TYPE_RANKING:
                    if (!is_array($value)) {
                        $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" has an invalid value.', [
                            'label' => $field->label,
                        ]));
                        break;
                    }
                    $allowed = $field->getOptions();
                    if (count($value) !== count(array_unique($value))) {
                        $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" has duplicate ranked items.', [
                            'label' => $field->label,
                        ]));
                        break;
                    }
                    foreach ($value as $item) {
                        if (!in_array((string)$item, $allowed, true)) {
                            $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" has an invalid option.', [
                                'label' => $field->label,
                            ]));
                            break 2;
                        }
                    }
                    break;
                case FormField::TYPE_RATING:
                    $scale = $field->getRatingScale();
                    if (!is_numeric($value)) {
                        $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" must be a valid rating value.', [
                            'label' => $field->label,
                        ]));
                        break;
                    }
                    $numeric = (int)$value;
                    if ($numeric < (int)$scale['min'] || $numeric > (int)$scale['max']) {
                        $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" must be between {min} and {max}.', [
                            'label' => $field->label,
                            'min' => $scale['min'],
                            'max' => $scale['max'],
                        ]));
                        break;
                    }
                    $step = max(1, (int)$scale['step']);
                    if ((($numeric - (int)$scale['min']) % $step) !== 0) {
                        $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" must follow the configured scale step.', [
                            'label' => $field->label,
                        ]));
                    }
                    break;
            }
        }
    }

    /**
     * Persist submission. Returns FormAnswer or null on failure.
     *
     * @param FormAnswer|null $existing
     * @param bool $anonymous When true, do not record submitter identity.
     * @param bool $asDraft When true, skip required checks and keep in-progress status.
     */
    public function save(?FormAnswer $existing = null, bool $anonymous = false, bool $asDraft = false): ?FormAnswer
    {
        if ($asDraft) {
            $this->scenario = self::SCENARIO_DRAFT;
        }

        if (!$this->validate()) {
            return null;
        }

        $answer = $existing ?: new FormAnswer();
        $answer->form_id = $this->form->id;
        if ($anonymous) {
            $answer->forceAnonymous = true;
            $answer->created_by = null;
            $answer->updated_by = null;
        }

        if ($asDraft) {
            $answer->status = FormAnswer::STATUS_IN_PROGRESS;
            if (!$answer->resume_code) {
                $answer->resume_code = (new \humhub\modules\thiscoveryForms\services\ResumeService())->generateCode();
            }
        } else {
            $answer->status = FormAnswer::STATUS_COMPLETE;
            $answer->resume_code = null;
            $answer->current_page = null;
        }

        if (!$answer->save()) {
            $this->addError('values', Yii::t('ThiscoveryFormsModule.base', 'Could not save your submission. Please try again.'));
            return null;
        }

        $existingFields = [];
        foreach ($answer->answerFields as $af) {
            $existingFields[$af->field_id] = $af;
        }

        foreach ($this->form->fields as $field) {
            if (!$field->collectsAnswer()) {
                continue;
            }
            $visible = $field->isConditionMet($this->values);
            $value = $visible ? ($this->values[$field->id] ?? null) : null;

            if (!$visible || $value === null || $value === '' || $value === []) {
                if (isset($existingFields[$field->id])) {
                    $existingFields[$field->id]->delete();
                }
                continue;
            }

            $af = $existingFields[$field->id] ?? new FormAnswerField();
            $af->answer_id = $answer->id;
            $af->field_id = $field->id;
            $af->value = is_array($value) ? json_encode(array_values($value), JSON_UNESCAPED_UNICODE) : (string)$value;

            if (!$af->save()) {
                $this->addError('values', Yii::t('ThiscoveryFormsModule.base', 'Could not save field "{label}".', [
                    'label' => $field->label,
                ]));
                return null;
            }

            if ($field->type === FormField::TYPE_FILE && $af->value) {
                try {
                    $answer->fileManager->attach($af->value);
                } catch (\Throwable $e) {
                    Yii::warning('Thiscovery Forms file attach failed: ' . $e->getMessage(), 'thiscovery-forms');
                }
            }
        }

        return $answer;
    }
}
