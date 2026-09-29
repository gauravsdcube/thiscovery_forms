<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\modules\file\models\File;
use humhub\modules\thiscoveryForms\services\FormPager;
use humhub\modules\thiscoveryForms\services\UploadGrant;
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

    /** @var FormAnswer|null Answer being edited, so an already attached file can be kept. */
    public $editingAnswer;

    /** @var int|null */
    public $waveId;

    /** @var array<int, true>|null */
    private $onPathFieldIds;

    /** @var int|null */
    public $roundId;

    /** @var int|null */
    public $panelMemberId;

    /** @var int|null Sort order of a consent question that was refused. Later questions are not required. */
    public $consentRefusedSort;

    /** @var string end, redirect, or goto when a quota stops or diverts this response */
    public $quotaHalt = '';

    /** @var string */
    public $quotaMessage = '';

    /** @var string */
    public $quotaUrl = '';

    /** @var int|null */
    public $quotaPage = null;

    /** @var float */
    public $weight = 1;

    /** @var int[] */
    public $frozenFieldIds = [];

    /** @var array<int|string, mixed> */
    public $previousValues = [];

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
                $this->values[$field->id] = $this->acceptFileGuid($field, is_string($guid) ? trim($guid) : '');
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

    private function acceptFileGuid(FormField $field, string $guid): string
    {
        if ($guid === '') {
            return '';
        }
        if (!UploadGrant::granted((int)$this->form->id, $guid)) {
            UploadGrant::grantLegacy((int)$this->form->id, $this->editingAnswer instanceof FormAnswer ? $this->editingAnswer : null);
        }
        if (UploadGrant::granted((int)$this->form->id, $guid)) {
            return $guid;
        }
        $answer = $this->editingAnswer;
        if ($answer instanceof FormAnswer) {
            $stored = FormAnswerField::find()
                ->select('value')
                ->where(['answer_id' => (int)$answer->id, 'field_id' => (int)$field->id])
                ->scalar();
            $file = File::findOne(['guid' => $guid]);
            $attached = $file && UploadGrant::attachedTo($file, $answer);
            if ((string)$stored === $guid) {
                return $attached ? $guid : '';
            }
            if ($attached) {
                return $guid;
            }
        }
        return '';
    }

    protected function applyHiddenDefaultsAndMeta(): void
    {
        $this->applyServerOwnedValues();
    }

    /**
     * Frozen, hidden, and panel-attribute answers come from the server.
     * A posted value for those fields is discarded.
     */
    public function applyServerOwnedValues(): void
    {
        $meta = new \humhub\modules\thiscoveryForms\services\RespondentMetaService();
        $member = $this->panelMemberId ? FormPanelMember::findOne((int)$this->panelMemberId) : null;
        $frozen = array_map('intval', $this->frozenFieldIds);
        foreach ($this->form->fields as $field) {
            $id = (int)$field->id;
            if (in_array($id, $frozen, true)) {
                $this->values[$id] = $this->previousValues[$id] ?? $this->previousValues[(string)$id] ?? '';
                continue;
            }
            if ($field->type === FormField::TYPE_RESPONDENT_META) {
                $this->values[$id] = $meta->valueFor($field->getRespondentMetaKey(), $this->values[$id] ?? null);
                continue;
            }
            if ($field->type === FormField::TYPE_PANEL_ATTR) {
                $key = $field->getPanelAttrKey();
                $this->values[$id] = ($member && $key !== '')
                    ? \humhub\modules\thiscoveryForms\services\PanelFieldService::memberValue($member, $key)
                    : '';
                continue;
            }
            if ($field->isHiddenFromRespondent()) {
                $this->values[$id] = $field->getDefaultValue();
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
        $this->editingAnswer = $answer;
        $assigned = (new \humhub\modules\thiscoveryForms\services\RandomisationService())->assignment($answer);
        if ($assigned) {
            $this->values['arm'] = (string)$assigned['arm_code'];
        }
    }

    public function validateFields(): void
    {
        (new \humhub\modules\thiscoveryForms\services\ConsentService())->validateSubmit($this);
        foreach ($this->form->fields as $field) {
            if ($this->consentRefusedSort !== null && (int)$field->sort_order > (int)$this->consentRefusedSort) {
                continue;
            }
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
            $loops = new \humhub\modules\thiscoveryForms\services\LoopService();
            if ($loops->isLoopField($this->form, $field)) {
                $group = $loops->groupForField($this->form->fields, $field);
                $shown = $group ? $loops->instances($group, $this->values, $this->form->fields) : [];
                $empty = false;
                if ($shown === []) {
                    $empty = false;
                } else {
                    foreach ($shown as $instance) {
                        $cell = is_array($value) ? ($value[$instance['code']] ?? null) : null;
                        if ($this->isEmptyValue($cell)) {
                            $empty = true;
                        }
                    }
                }
            } else {
                $empty = $this->isEmptyValue($value);
            }

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
                        break;
                    }
                    $number = (float)$value;
                    $min = $field->getNumberMin();
                    $max = $field->getNumberMax();
                    if ($min !== null && $number < $min) {
                        $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" must be at least {min}.', [
                            'label' => $field->label,
                            'min' => $min,
                        ]));
                    }
                    if ($max !== null && $number > $max) {
                        $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" must be at most {max}.', [
                            'label' => $field->label,
                            'max' => $max,
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
     * The signed edition posted with the fill, or null when the page did not send one.
     * False means the token was present and did not verify, or the edition failed to load.
     *
     * @return int|false|null
     */
    private function resolvePinnedEdition(bool $isTest)
    {
        if ($isTest || !$this->form) {
            return null;
        }
        if (!empty($this->form->editionLoadFailed)) {
            return false;
        }
        $token = '';
        try {
            $token = trim((string)Yii::$app->request->post('edition_token', ''));
        } catch (\Throwable $e) {
            $token = '';
        }
        if ($token === '') {
            return null;
        }
        $editionId = (new \humhub\modules\thiscoveryForms\services\FormVersionService())
            ->readSignedEdition($token, (int)$this->form->id);
        return $editionId ?: false;
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

        if ($existing && !$existing->isNewRecord) {
            $existing->populateRelation('form', $this->form);
            \humhub\modules\thiscoveryForms\services\RandomisationService::$current = $existing;
            (new \humhub\modules\thiscoveryForms\services\RandomisationService())->materialise($existing);
            $this->editingAnswer = $existing;
            $this->onPathFieldIds = null;
        }

        if (!$this->validate()) {
            return null;
        }

        $pinnedEdition = $this->resolvePinnedEdition($isTest);
        if ($pinnedEdition === false) {
            $this->addError('values', Yii::t('ThiscoveryFormsModule.base', 'This form edition could not be confirmed. Please reload the page and try again.'));
            return null;
        }

        $stripIdentity = $this->form
            && $this->form->hidesIdentityFromManagers()
            && \humhub\modules\thiscoveryForms\Module::identityEnforced();
        if ($stripIdentity) {
            $anonymous = true;
            $this->panelMemberId = null;
        }

        $answer = $existing ?: new FormAnswer();
        $answer->form_id = $this->form->id;
        if ($this->waveId) {
            $answer->wave_id = $this->waveId;
        }
        if ($this->roundId) {
            $answer->round_id = $this->roundId;
        }
        if ($stripIdentity) {
            $answer->panel_member_id = null;
        } elseif ($this->panelMemberId) {
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
            if ($answer->isNewRecord || $answer->isInProgress()) {
                $answer->status = FormAnswer::STATUS_IN_PROGRESS;
            }
        }

        if ($isTest || ($existing && $existing->isTest())) {
            $answer->is_test = 1;
            $answer->forceAnonymous = true;
            $answer->created_by = null;
            $answer->updated_by = null;
        }

        if (!$isTest && !$answer->edition_id) {
            if (is_int($pinnedEdition) && $pinnedEdition > 0) {
                $answer->edition_id = $pinnedEdition;
            } elseif ($this->form && $this->form->current_edition_id) {
                $answer->edition_id = (int)$this->form->current_edition_id;
            }
        }

        $fileGuids = [];
        $db = Yii::$app->db;
        FormAnswerField::$deferFileDeletes = true;
        $transaction = $db->beginTransaction();
        try {
            if (!$answer->save()) {
                $transaction->rollBack();
                FormAnswerField::discardDeferredFiles();
                $this->addError('values', Yii::t('ThiscoveryFormsModule.base', 'Could not save your submission. Please try again.'));
                return null;
            }
            $answer->populateRelation('form', $this->form);
            \humhub\modules\thiscoveryForms\services\RandomisationService::$current = $answer;
            $rand = new \humhub\modules\thiscoveryForms\services\RandomisationService();
            $rand->materialise($answer);
            $postedPage = Yii::$app->request->post('current_page');
            $assigned = $rand->assignIfDue(
                $answer,
                $this->values,
                ($postedPage === null || $postedPage === '') ? null : (int)$postedPage,
                !$asDraft
            );
            if ($assigned) {
                $this->values['arm'] = (string)$assigned['arm_code'];
            }
            $this->onPathFieldIds = null;
            (new \humhub\modules\thiscoveryForms\services\ConsentService())->applySubmit($this, $answer, $asDraft, (bool)$stripIdentity);
            $previewOutcome = (string)$answer->outcome === FormAnswer::OUTCOME_NOT_CONSENTED
                ? FormAnswer::OUTCOME_NOT_CONSENTED
                : $this->terminalOutcome($answer);
            if (!in_array($previewOutcome, [FormAnswer::OUTCOME_NOT_CONSENTED, FormAnswer::OUTCOME_SCREENED_OUT], true)) {
                (new \humhub\modules\thiscoveryForms\services\QuotaService())->apply(
                    $this,
                    $answer,
                    $asDraft,
                    ($postedPage === null || $postedPage === '') ? null : (int)$postedPage
                );
            }

            $existingFields = [];
            $instanceColumn = \humhub\modules\thiscoveryForms\services\LoopService::columnReady();
            foreach ($answer->answerFields as $af) {
                $instance = $instanceColumn ? (string)($af->instance_key ?? '') : '';
                $existingFields[(int)$af->field_id . ':' . $instance] = $af;
            }

            $liveQuery = FormField::find()
                ->select('id')
                ->where(['form_id' => (int)$this->form->id]);
            if (FormField::supportsSoftDelete()) {
                $liveQuery->andWhere(['deleted_at' => null]);
            }
            $liveFieldIds = array_flip($liveQuery->column());
            $editionFill = (int)$answer->edition_id > 0;

            foreach ($this->form->fields as $field) {
                if (!$field->collectsAnswer()) {
                    continue;
                }
                $fieldId = (int)$field->id;
                if ($fieldId < 1) {
                    continue;
                }
                if (!isset($liveFieldIds[$fieldId])) {
                    // A published edition still contains questions removed from the
                    // draft. Those ids are on the hydrated definition. A live-draft
                    // fill does not, and a posted id with no row at all is skipped.
                    if (!$editionFill) {
                        if (array_key_exists($fieldId, $this->values)) {
                            Yii::warning(
                                'Thiscovery Forms field #' . $fieldId
                                . ' has no live custom_form_field row; answers for it will be skipped.',
                                'thiscovery-forms'
                            );
                        }
                        continue;
                    }
                }
                if ($this->consentRefusedSort !== null && (int)$field->sort_order > (int)$this->consentRefusedSort) {
                    foreach ($existingFields as $slot => $existingCell) {
                        if (str_starts_with((string)$slot, $fieldId . ':')) {
                            $existingCell->delete();
                        }
                    }
                    continue;
                }
                $loops = new \humhub\modules\thiscoveryForms\services\LoopService();
                if ($instanceColumn && $loops->isLoopField($this->form, $field)) {
                    $group = $loops->groupForField($this->form->fields, $field);
                    $shown = $group ? $loops->instances($group, $this->values, $this->form->fields) : [];
                    $posted = $this->values[$fieldId] ?? [];
                    if (!is_array($posted)) {
                        $posted = [];
                    }
                    foreach ($shown as $instance) {
                        $code = (string)$instance['code'];
                        $slot = $fieldId . ':' . $code;
                        if (!array_key_exists($code, $posted)) {
                            continue;
                        }
                        $cell = $posted[$code];
                        if ($this->isEmptyValue($cell)) {
                            if (isset($existingFields[$slot])) {
                                $existingFields[$slot]->delete();
                            }
                            continue;
                        }
                        $af = $existingFields[$slot] ?? new FormAnswerField();
                        $af->answer_id = $answer->id;
                        $af->field_id = $fieldId;
                        $af->instance_key = $code;
                        $af->value = $this->encodeValue($cell);
                        if (!$af->save()) {
                            $transaction->rollBack();
                            FormAnswerField::discardDeferredFiles();
                            $this->addError('values', Yii::t('ThiscoveryFormsModule.base', 'Could not save field "{label}".', [
                                'label' => $field->label,
                            ]));
                            return null;
                        }
                    }
                    continue;
                }
                $visible = $this->isOnAnswerPath($field) && $field->isVisible($this->values, $this->form->fields);
                $value = $visible ? ($this->values[$fieldId] ?? null) : null;

                if (!$visible || $this->isEmptyValue($value)) {
                    if (isset($existingFields[$fieldId . ':'])) {
                        $existingFields[$fieldId . ':']->delete();
                    }
                    continue;
                }

                if ($field->type === FormField::TYPE_IMAGE_AREA) {
                    $value = $this->scoreImageArea($field, is_array($value) ? $value : []);
                }

                $af = $existingFields[$fieldId . ':'] ?? new FormAnswerField();
                $af->answer_id = $answer->id;
                $af->field_id = $fieldId;
                if ($instanceColumn) {
                    $af->instance_key = '';
                }
                $af->value = $this->encodeValue($value);
                $af->justification = $field->supportsJustification()
                    ? (trim((string)($this->justifications[$field->id] ?? '')) ?: null)
                    : null;

                if (!$af->save()) {
                    $transaction->rollBack();
                    FormAnswerField::discardDeferredFiles();
                    $this->addError('values', Yii::t('ThiscoveryFormsModule.base', 'Could not save field "{label}".', [
                        'label' => $field->label,
                    ]));
                    return null;
                }

                if ($field->type === FormField::TYPE_FILE && $af->value) {
                    $fileGuids[] = (string)$af->value;
                }
            }

            if ($this->quotaHalt === 'goto') {
                $answer->status = FormAnswer::STATUS_IN_PROGRESS;
                $answer->current_page = $this->quotaPage;
                $answer->outcome = '';
                $answer->save(false, ['status', 'outcome', 'current_page', 'updated_at']);
            } elseif (in_array($this->quotaHalt, ['end', 'redirect'], true)) {
                $answer->status = FormAnswer::STATUS_COMPLETE;
                $answer->resume_code = null;
                $answer->current_page = null;
                if ($anonymous) {
                    $answer->resume_email = null;
                }
                $answer->outcome = FormAnswer::OUTCOME_OVER_QUOTA;
                $answer->save(false, ['status', 'outcome', 'resume_code', 'resume_email', 'current_page', 'updated_at']);
            } elseif (!$asDraft) {
                $answer->status = FormAnswer::STATUS_COMPLETE;
                $answer->resume_code = null;
                $answer->current_page = null;
                if ($anonymous) {
                    $answer->resume_email = null;
                }
                $answer->outcome = $this->terminalOutcome($answer);
                $answer->save(false, ['status', 'outcome', 'resume_code', 'resume_email', 'current_page', 'updated_at']);
            }

            $transaction->commit();
            FormField::storeOptionOrder($this->form, $answer);
        } catch (\Throwable $e) {
            $transaction->rollBack();
            FormAnswerField::discardDeferredFiles();
            Yii::error('Thiscovery Forms submit rolled back: ' . $e->getMessage(), 'thiscovery-forms');
            $this->addError('values', Yii::t('ThiscoveryFormsModule.base', 'Could not save your submission. Please try again.'));
            return null;
        }

        FormAnswerField::deleteDeferredFiles();
        unset($answer->answerFields);
        foreach ($fileGuids as $guid) {
            try {
                $answer->fileManager->attach($guid);
                UploadGrant::forget((int)$this->form->id, (string)$guid);
            } catch (\Throwable $e) {
                Yii::warning('Thiscovery Forms file attach failed: ' . $e->getMessage(), 'thiscovery-forms');
            }
        }
        if (!$asDraft) {
            UploadGrant::forgetAll((int)$this->form->id);
        }

        if ($this->quotaMessage !== '' && ($this->quotaHalt === 'end' || $this->quotaHalt === 'redirect')) {
            Yii::$app->session->setFlash('cf-over-quota', $this->quotaMessage);
        }
        if ($this->quotaUrl !== '') {
            Yii::$app->session->set('cf-quota-redirect', $this->quotaUrl);
        }

        if (!$asDraft && !$isTest && $answer->countsAsComplete() && Yii::$app->hasModule('thiscovery-dashboard')) {
            $dash = Yii::$app->getModule('thiscovery-dashboard');
            if ($dash instanceof \humhub\modules\thiscoveryDashboard\Module) {
                $dash->enqueueIncrement('forms', (string)$this->form->id, (int)$answer->id);
            }
        }

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

    private function terminalOutcome(FormAnswer $answer): string
    {
        if ((string)$answer->outcome === FormAnswer::OUTCOME_NOT_CONSENTED) {
            return FormAnswer::OUTCOME_NOT_CONSENTED;
        }
        if ((string)$answer->outcome === FormAnswer::OUTCOME_OVER_QUOTA) {
            return FormAnswer::OUTCOME_OVER_QUOTA;
        }
        if (!\humhub\modules\thiscoveryForms\Module::randomisationEnabled()) {
            return (string)$answer->outcome;
        }
        $engine = new \humhub\modules\thiscoveryForms\services\LogicEngine();
        foreach ($this->form->fields as $field) {
            $logic = $field->getLogic();
            if (($logic['action'] ?? '') !== \humhub\modules\thiscoveryForms\services\LogicEngine::ACTION_SCREEN_OUT) {
                continue;
            }
            if (!$this->isOnAnswerPath($field)) {
                continue;
            }
            if (!empty($logic['rules']) && $engine->rulesMet($logic, $this->values, $this->form->fields)) {
                return FormAnswer::OUTCOME_SCREENED_OUT;
            }
        }
        if (\humhub\modules\thiscoveryForms\services\RandomisationService::active($this->form)) {
            return FormAnswer::OUTCOME_COMPLETE;
        }
        return (string)$answer->outcome;
    }

    private function isOnAnswerPath(FormField $field): bool
    {
        if ($field->isHiddenFromRespondent() || $field->type === FormField::TYPE_RESPONDENT_META) {
            return true;
        }
        if ($this->onPathFieldIds === null) {
            $orders = [];
            if ($this->editingAnswer && \humhub\modules\thiscoveryForms\services\RandomisationService::active($this->form)) {
                $orders = (new \humhub\modules\thiscoveryForms\services\RandomisationService())->orders($this->editingAnswer)['pages'];
            }
            $this->onPathFieldIds = (new FormPager())->visitedFieldIds($this->form->fields, $this->values, $orders);
        }
        return isset($this->onPathFieldIds[(int)$field->id]);
    }
}
