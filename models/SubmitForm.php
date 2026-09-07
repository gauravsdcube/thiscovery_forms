<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\modules\thiscoveryForms\services\FormPager;
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

    /** @var array fieldId => justification text */
    public $justifications = [];

    /** @var int|null */
    public $waveId;

    /** @var array<int, true>|null */
    private $onPathFieldIds;

    /** @var int|null */
    public $roundId;

    /** @var int|null */
    public $panelMemberId;

    /** @var float */
    public $weight = 1;

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
                $this->values[$field->id] = $this->sanitizeChoiceValue($field, is_array($val) ? array_values($val) : []);
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
            if (in_array($field->type, [
                FormField::TYPE_GRID_SINGLE,
                FormField::TYPE_GRID_MULTI,
                FormField::TYPE_BEST_WORST,
                FormField::TYPE_MAXDIFF,
                FormField::TYPE_DRILLDOWN,
                FormField::TYPE_IMAGE_AREA,
            ], true)) {
                $val = $fieldPost[$key] ?? [];
                if (is_string($val)) {
                    $decoded = json_decode($val, true);
                    $val = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $val;
                }
                $this->values[$field->id] = $val;
                continue;
            }
            if ($field->type === FormField::TYPE_MAP) {
                $val = $fieldPost[$key] ?? [];
                if (is_string($val)) {
                    $decoded = json_decode($val, true);
                    $val = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $val;
                }
                $clean = $field->sanitizeMapAnswer($val);
                $this->values[$field->id] = !empty($clean['features']) ? $clean : [];
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
            $posted = isset($fieldPost[$key]) ? $fieldPost[$key] : '';
            if (in_array($field->type, [FormField::TYPE_RADIO, FormField::TYPE_DROPDOWN], true)) {
                $posted = $this->sanitizeChoiceValue($field, $posted);
            }
            if ($field->type === FormField::TYPE_RATING && ($posted === '' || $posted === null || $posted === [])) {
                $posted = '';
            }
            $this->values[$field->id] = $posted;
        }

        $this->applyHiddenDefaultsAndMeta();

        $this->applyOtherSpecify($post);

        $justPost = $post['SubmitForm']['justifications'] ?? ($post['justifications'] ?? []);
        if (!is_array($justPost)) {
            $justPost = [];
        }
        $this->justifications = [];
        foreach ($this->form->fields as $field) {
            if (!$field->supportsJustification()) {
                continue;
            }
            $this->justifications[$field->id] = trim((string)($justPost[(string)$field->id] ?? ''));
        }

        return true;
    }

    protected function applyHiddenDefaultsAndMeta(): void
    {
        $meta = new \humhub\modules\thiscoveryForms\services\RespondentMetaService();
        $member = $this->panelMemberId ? FormPanelMember::findOne((int)$this->panelMemberId) : null;
        foreach ($this->form->fields as $field) {
            if ($field->type === FormField::TYPE_RESPONDENT_META) {
                $this->values[$field->id] = $meta->valueFor($field->getRespondentMetaKey(), $this->values[$field->id] ?? null);
                continue;
            }
            if ($field->type === FormField::TYPE_PANEL_ATTR) {
                $key = $field->getPanelAttrKey();
                $posted = $this->values[$field->id] ?? null;
                if ($this->isEmptyValue($posted) && $member && $key !== '') {
                    $this->values[$field->id] = \humhub\modules\thiscoveryForms\services\PanelFieldService::memberValue($member, $key);
                }
                continue;
            }
            if (!$field->isHiddenFromRespondent()) {
                continue;
            }
            $value = $this->values[$field->id] ?? null;
            if ($this->isEmptyValue($value) && $field->getDefaultValue() !== '') {
                $this->values[$field->id] = $field->getDefaultValue();
            }
        }
    }

    /**
     * When "Other" is selected, store "Other: typed text" as the answer value.
     */
    protected function applyOtherSpecify($post): void
    {
        $otherPost = $post['SubmitForm']['other_text'] ?? ($post['other_text'] ?? []);
        if (!is_array($otherPost)) {
            return;
        }

        foreach ($this->form->fields as $field) {
            if (!in_array($field->type, [FormField::TYPE_DROPDOWN, FormField::TYPE_RADIO, FormField::TYPE_CHECKBOX], true)) {
                continue;
            }
            $otherLabel = $field->findOtherOption();
            if ($otherLabel === null) {
                $current = $this->values[$field->id] ?? null;
                $probe = is_array($current) ? $current : [$current];
                foreach ($probe as $item) {
                    if ($item !== null && $item !== '' && FormField::isOtherOption((string)$item)) {
                        $otherLabel = (string)$item;
                        break;
                    }
                }
            }
            if ($otherLabel === null) {
                continue;
            }
            $text = trim((string)($otherPost[(string)$field->id] ?? ''));
            if ($text === '') {
                continue;
            }
            $stored = FormField::otherSpecifyPrefix($otherLabel) . $text;
            $current = $this->values[$field->id] ?? null;
            if ($field->type === FormField::TYPE_CHECKBOX) {
                $items = is_array($current) ? $current : [];
                $next = [];
                $replaced = false;
                foreach ($items as $item) {
                    if ((string)$item === $otherLabel || str_starts_with((string)$item, FormField::otherSpecifyPrefix($otherLabel))) {
                        $next[] = $stored;
                        $replaced = true;
                    } else {
                        $next[] = $item;
                    }
                }
                if ($replaced) {
                    $this->values[$field->id] = $next;
                }
                continue;
            }
            if ((string)$current === $otherLabel || str_starts_with((string)$current, FormField::otherSpecifyPrefix($otherLabel))) {
                $this->values[$field->id] = $stored;
            }
        }
    }

    public function loadFromAnswer(FormAnswer $answer): void
    {
        $this->values = $answer->getValuesMap();
        $this->justifications = $answer->getJustificationsMap();
    }

    public function validateFields(): void
    {
        foreach ($this->form->fields as $field) {
            if (!$field->collectsAnswer()) {
                continue;
            }
            if (!$this->isOnAnswerPath($field)) {
                continue;
            }
            if (!$field->isVisible($this->values, $this->form->fields)) {
                continue;
            }

            $value = $this->values[$field->id] ?? null;
            $empty = $this->isEmptyValue($value);

            $required = $field->required;
            if ($field->type === FormField::TYPE_HTML) {
                $required = (bool)$field->getHtmlConfig()['required'];
            }
            if ($field->isHiddenFromRespondent() || $field->type === FormField::TYPE_RESPONDENT_META) {
                $required = false;
            }

            if ($required && $empty) {
                if ($this->scenario === self::SCENARIO_DRAFT) {
                    continue;
                }
                if ($field->type === FormField::TYPE_CHECKBOX && $this->addCheckboxMinError($field)) {
                    continue;
                }
                $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" is required.', [
                    'label' => $field->label,
                ]));
                continue;
            }

            if ($empty) {
                if ($this->scenario !== self::SCENARIO_DRAFT) {
                    $this->addCheckboxMinError($field);
                }
                continue;
            }

            switch ($field->type) {
                case FormField::TYPE_RESPONDENT_META:
                case FormField::TYPE_PANEL_ATTR:
                    if ($field->getPanelAttrKey() === 'email' && !filter_var((string)$value, FILTER_VALIDATE_EMAIL)) {
                        $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" must be a valid email.', [
                            'label' => $field->label,
                        ]));
                    }
                    break;
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
                    if (!$field->allowsChoiceValue((string)$value)) {
                        $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" has an invalid option.', [
                            'label' => $field->label,
                        ]));
                    } elseif ($this->scenario !== self::SCENARIO_DRAFT && $field->otherSpecifyIncomplete($value)) {
                        $this->addError('values', Yii::t('ThiscoveryFormsModule.base', 'Please specify your answer for "{label}".', [
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
                    foreach ($value as $item) {
                        if (!$field->allowsChoiceValue((string)$item)) {
                            $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" has an invalid option.', [
                                'label' => $field->label,
                            ]));
                            break;
                        }
                    }
                    if ($this->scenario !== self::SCENARIO_DRAFT && $field->otherSpecifyIncomplete($value)) {
                        $this->addError('values', Yii::t('ThiscoveryFormsModule.base', 'Please specify your answer for "{label}".', [
                            'label' => $field->label,
                        ]));
                    }
                    $exclusiveList = $field->getExclusiveOptions();
                    $selected = array_map('strval', is_array($value) ? $value : []);
                    $hit = array_values(array_intersect($exclusiveList, $selected));
                    $this->addCheckboxMinError($field, $selected, (bool)$hit);
                    $maxSelect = $field->getMaxSelect();
                    if ($maxSelect !== null && count($selected) > $maxSelect) {
                        $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" allows at most {max} selections.', [
                            'label' => $field->label,
                            'max' => $maxSelect,
                        ]));
                    }
                    if ($hit && count($selected) > 1) {
                        $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" cannot combine "{option}" with other choices.', [
                            'label' => $field->label,
                            'option' => implode(', ', $hit),
                        ]));
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
                case FormField::TYPE_GRID_SINGLE:
                case FormField::TYPE_GRID_MULTI:
                    $this->validateGrid($field, $value);
                    break;
                case FormField::TYPE_BEST_WORST:
                    $this->validateBestWorst($field, $value);
                    break;
                case FormField::TYPE_MAXDIFF:
                    $this->validateMaxDiff($field, $value);
                    break;
                case FormField::TYPE_DRILLDOWN:
                    $this->validateDrilldown($field, $value);
                    break;
                case FormField::TYPE_IMAGE_AREA:
                    $this->validateImageArea($field, $value);
                    break;
                case FormField::TYPE_MAP:
                    $this->validateMap($field, $value);
                    break;
            }

            $justMode = $field->getEffectiveJustification($this->form);
            if ($justMode === FormField::JUSTIFY_REQUIRED && $this->scenario !== self::SCENARIO_DRAFT) {
                $just = trim((string)($this->justifications[$field->id] ?? ''));
                if ($just === '') {
                    $this->addError('values', Yii::t('ThiscoveryFormsModule.base', 'Please add a comment for "{label}".', [
                        'label' => $field->label,
                    ]));
                }
            }
        }
    }

    /**
     * Persist submission. Returns FormAnswer or null on failure.
     *
     * @param FormAnswer|null $existing
     * @param bool $anonymous When true, do not record submitter identity.
     * @param bool $asDraft When true, skip required checks and keep in-progress status.
     * @param bool $isTest When true, store as a preview/test run (excluded from participant stats).
     */
    public function save(?FormAnswer $existing = null, bool $anonymous = false, bool $asDraft = false, bool $isTest = false): ?FormAnswer
    {
        if ($asDraft) {
            $this->scenario = self::SCENARIO_DRAFT;
        }

        if (!$this->validate()) {
            return null;
        }

        $answer = $existing ?: new FormAnswer();
        $answer->form_id = $this->form->id;
        if ($this->waveId) {
            $answer->wave_id = $this->waveId;
        }
        if ($this->roundId) {
            $answer->round_id = $this->roundId;
        }
        if ($this->panelMemberId) {
            $answer->panel_member_id = $this->panelMemberId;
        }
        if ($this->weight !== null) {
            $answer->weight = $this->weight;
        }
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

        if ($isTest || ($existing && $existing->isTest())) {
            $answer->is_test = 1;
            $answer->forceAnonymous = true;
            $answer->created_by = null;
            $answer->updated_by = null;
        }

        if (!$answer->save()) {
            $this->addError('values', Yii::t('ThiscoveryFormsModule.base', 'Could not save your submission. Please try again.'));
            return null;
        }

        if (!$isTest && !$answer->edition_id && $this->form->current_edition_id) {
            $answer->updateAttributes(['edition_id' => (int)$this->form->current_edition_id]);
            $answer->edition_id = (int)$this->form->current_edition_id;
        }

        $existingFields = [];
        foreach ($answer->answerFields as $af) {
            $existingFields[$af->field_id] = $af;
        }

        foreach ($this->form->fields as $field) {
            if (!$field->collectsAnswer()) {
                continue;
            }
            $visible = $this->isOnAnswerPath($field) && $field->isVisible($this->values, $this->form->fields);
            $value = $visible ? ($this->values[$field->id] ?? null) : null;

            if (!$visible || $this->isEmptyValue($value)) {
                if (isset($existingFields[$field->id])) {
                    $existingFields[$field->id]->delete();
                }
                continue;
            }

            if ($field->type === FormField::TYPE_IMAGE_AREA) {
                $value = $this->scoreImageArea($field, is_array($value) ? $value : []);
            }

            $af = $existingFields[$field->id] ?? new FormAnswerField();
            $af->answer_id = $answer->id;
            $af->field_id = $field->id;
            $af->value = $this->encodeValue($value);
            $af->justification = $field->supportsJustification()
                ? (trim((string)($this->justifications[$field->id] ?? '')) ?: null)
                : null;

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

        unset($answer->answerFields);

        return $answer;
    }

    /**
     * Drop posted values that are not real options (hidden uncheck "0", browser prefill).
     */
    private function sanitizeChoiceValue(FormField $field, $value)
    {
        $allowed = $field->getOptions();
        $isAllowed = static function (string $item) use ($allowed): bool {
            if ($item === '') {
                return false;
            }
            foreach ($allowed as $opt) {
                $opt = (string)$opt;
                if ($item === $opt) {
                    return true;
                }
                if (FormField::isOtherOption($opt) && str_starts_with($item, FormField::otherSpecifyPrefix($opt))) {
                    return true;
                }
            }
            return FormField::isOtherOption($item);
        };

        if ($field->type === FormField::TYPE_CHECKBOX) {
            $items = is_array($value) ? $value : [];
            $out = [];
            foreach ($items as $item) {
                $item = is_scalar($item) ? (string)$item : '';
                if ($isAllowed($item)) {
                    $out[] = $item;
                }
            }
            return array_values($out);
        }

        if (is_array($value)) {
            return '';
        }
        $item = is_scalar($value) ? (string)$value : '';
        return $isAllowed($item) ? $item : '';
    }

    private function isEmptyValue($value): bool
    {
        if ($value === null || $value === '' || $value === []) {
            return true;
        }
        if (is_array($value)) {
            $filtered = array_filter($value, function ($v) {
                if ($v === null || $v === '' || $v === []) {
                    return false;
                }
                if (is_array($v)) {
                    return !$this->isEmptyValue($v);
                }
                return true;
            });
            return $filtered === [];
        }
        return false;
    }

    private function encodeValue($value): string
    {
        if (!is_array($value)) {
            return (string)$value;
        }
        if (array_is_list($value)) {
            return json_encode(array_values($value), JSON_UNESCAPED_UNICODE);
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    private function invalid(FormField $field): void
    {
        $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" has an invalid value.', [
            'label' => $field->label,
        ]));
    }

    /**
     * @param string[]|null $selected
     */
    private function addCheckboxMinError(FormField $field, ?array $selected = null, bool $exclusiveHit = false): bool
    {
        if ($field->type !== FormField::TYPE_CHECKBOX || $this->scenario === self::SCENARIO_DRAFT) {
            return false;
        }
        $minSelect = $field->resolveMinSelect();
        if ($minSelect === null || $exclusiveHit) {
            return false;
        }
        $count = $selected === null ? 0 : count($selected);
        if ($count >= $minSelect) {
            return false;
        }
        $this->addError('values', $field->isMinSelectAll()
            ? Yii::t('ThiscoveryFormsModule.base', '"{label}" requires every option to be selected.', [
                'label' => $field->label,
            ])
            : Yii::t('ThiscoveryFormsModule.base', '"{label}" requires at least {min} selections.', [
                'label' => $field->label,
                'min' => $minSelect,
            ]));
        return true;
    }

    private function validateGrid(FormField $field, $value): void
    {
        if (!is_array($value)) {
            $this->invalid($field);
            return;
        }
        $cfg = $field->getGridConfig();
        $rows = $cfg['rows'];
        $cols = $cfg['columns'];
        $colValues = [];
        foreach ($cols as $col) {
            if (is_array($col)) {
                foreach ([(string)($col['value'] ?? ''), (string)($col['code'] ?? ''), (string)($col['label'] ?? '')] as $key) {
                    if ($key !== '') {
                        $colValues[$key] = true;
                    }
                }
            } else {
                $colValues[(string)$col] = true;
            }
        }
        $multi = $field->type === FormField::TYPE_GRID_MULTI;
        foreach ($rows as $row) {
            $rowKeys = [];
            if (is_array($row)) {
                foreach ([(string)($row['value'] ?? ''), (string)($row['code'] ?? ''), (string)($row['label'] ?? '')] as $key) {
                    if ($key !== '') {
                        $rowKeys[] = $key;
                    }
                }
            } else {
                $rowKeys[] = (string)$row;
            }
            $cell = null;
            foreach ($rowKeys as $rowKey) {
                if (array_key_exists($rowKey, $value)) {
                    $cell = $value[$rowKey];
                    break;
                }
            }
            if ($cell === null || $cell === '' || $cell === []) {
                if ($field->required && $this->scenario !== self::SCENARIO_DRAFT) {
                    $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" is required.', [
                        'label' => $field->label,
                    ]));
                    return;
                }
                continue;
            }
            $picked = $multi ? (is_array($cell) ? $cell : [$cell]) : [$cell];
            foreach ($picked as $col) {
                if (!isset($colValues[(string)$col])) {
                    $this->invalid($field);
                    return;
                }
            }
        }
    }

    private function validateBestWorst(FormField $field, $value): void
    {
        if (!is_array($value)) {
            $this->invalid($field);
            return;
        }
        $items = $field->getItemsConfig()['items'];
        $best = (string)($value['best'] ?? '');
        $worst = (string)($value['worst'] ?? '');
        if ($best === '' || $worst === '' || $best === $worst) {
            $this->invalid($field);
            return;
        }
        if (!in_array($best, $items, true) || !in_array($worst, $items, true)) {
            $this->invalid($field);
        }
    }

    private function validateMaxDiff(FormField $field, $value): void
    {
        if (!is_array($value)) {
            $this->invalid($field);
            return;
        }
        $sets = $field->getItemsConfig()['sets'];
        $answers = $value['sets'] ?? $value;
        if (!is_array($answers)) {
            $this->invalid($field);
            return;
        }
        foreach ($sets as $i => $set) {
            $pair = $answers[$i] ?? null;
            if (!is_array($pair)) {
                $this->invalid($field);
                return;
            }
            $best = (string)($pair['best'] ?? '');
            $worst = (string)($pair['worst'] ?? '');
            if ($best === '' || $worst === '' || $best === $worst) {
                $this->invalid($field);
                return;
            }
            $set = array_map('strval', $set);
            if (!in_array($best, $set, true) || !in_array($worst, $set, true)) {
                $this->invalid($field);
                return;
            }
        }
    }

    private function validateDrilldown(FormField $field, $value): void
    {
        $path = is_array($value) ? array_values(array_map('strval', $value)) : [];
        $path = array_values(array_filter($path, 'strlen'));
        if (!$path || !FormField::pathExistsInTree($field->getDrilldownTree(), $path)) {
            $this->invalid($field);
        }
    }

    private function validateImageArea(FormField $field, $value): void
    {
        $cfg = $field->getImageAreaConfig();
        $ids = [];
        foreach ($cfg['regions'] as $region) {
            $ids[] = (string)($region['id'] ?? '');
        }
        $picked = [];
        if (is_array($value)) {
            $picked = $value['regions'] ?? (array_is_list($value) ? $value : []);
        }
        if (!is_array($picked) || !$picked) {
            $this->invalid($field);
            return;
        }
        foreach ($picked as $id) {
            if (is_array($id) || !in_array((string)$id, $ids, true)) {
                $this->invalid($field);
                return;
            }
        }
        if (!$cfg['multi'] && count($picked) > 1) {
            $this->invalid($field);
        }
    }

    private function validateMap(FormField $field, $value): void
    {
        $clean = $field->sanitizeMapAnswer($value);
        if (empty($clean['features'])) {
            $this->invalid($field);
            return;
        }
        $this->values[$field->id] = $clean;
    }

    private function scoreImageArea(FormField $field, array $value): array
    {
        $cfg = $field->getImageAreaConfig();
        $picked = $value['regions'] ?? (array_is_list($value) ? $value : []);
        if (!is_array($picked)) {
            $picked = [];
        }
        $picked = array_values(array_map('strval', $picked));
        $score = 0;
        if ($cfg['mode'] === 'evaluate') {
            foreach ($cfg['regions'] as $region) {
                $id = (string)($region['id'] ?? '');
                if (!in_array($id, $picked, true)) {
                    continue;
                }
                $score += (int)($region['score'] ?? 0);
                if (!empty($region['correct']) && (int)($region['score'] ?? 0) === 0) {
                    $score += 1;
                }
            }
        }
        return [
            'regions' => $picked,
            'score' => $score,
        ];
    }

    private function isOnAnswerPath(FormField $field): bool
    {
        if ($field->isHiddenFromRespondent() || $field->type === FormField::TYPE_RESPONDENT_META) {
            return true;
        }
        if ($this->onPathFieldIds === null) {
            $this->onPathFieldIds = (new FormPager())->visitedFieldIds($this->form->fields, $this->values);
        }
        return isset($this->onPathFieldIds[(int)$field->id]);
    }
}
