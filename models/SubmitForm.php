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

    /** @var bool A roster row was added or removed during this save. */
    public $rosterChanged = false;

    /** @var string Instance key to reopen after a roster change. */
    public $rosterKey = '';

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
            // An empty page still has to enforce consent and other rules (skipOnEmpty would skip them).
            [['values'], 'validateFields', 'skipOnEmpty' => false],
        ];
    }

    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_DRAFT] = ['values'];
        return $scenarios;
    }

    /** Why a completed response is being changed; read from the posted change_reason when null. */
    public ?string $changeReason = null;

    /** Changes to a completed response are audited (V3-44); filling in a draft is not. */
    private bool $auditing = false;

    /** A manager changing someone else's completed response: attributed, and needs a reason. */
    private bool $managerEdit = false;

    /**
     * A manager (not the respondent) is changing a completed response (V3-44).
     */
    public static function isManagerEdit(?CustomForm $form, ?FormAnswer $answer): bool
    {
        if (!$form || !$answer || $answer->isNewRecord || !$answer->isComplete() || Yii::$app->user->isGuest) {
            return false;
        }
        $userId = (int)Yii::$app->user->id;
        return $form->canManage() && (int)$answer->created_by !== $userId;
    }

    private function changeReason(): string
    {
        $reason = $this->changeReason ?? (string)Yii::$app->request->post('change_reason', '');
        return mb_substr(trim($reason), 0, 255);
    }

    /** @var array<int,true>|null loop member ids, memoised for this submit */
    private ?array $loopIds = null;

    /**
     * A loop member's answer is always keyed by instance path. Key shape cannot decide
     * this: repeat codes 0..n post as a PHP list (V3-15).
     */
    private function isLoopMember(FormField $field): bool
    {
        if (!$this->form) {
            return false;
        }
        if ($this->loopIds === null) {
            $this->loopIds = (new \humhub\modules\thiscoveryForms\services\LoopService())->loopFieldIds($this->form);
        }
        return isset($this->loopIds[(int)$field->id]);
    }

    /**
     * One posted answer (or one loop repeat of it), cleaned for its type.
     *
     * @param mixed $raw
     * @return mixed
     */
    private function parsePosted(FormField $field, $raw, string $instance)
    {
        switch ($field->type) {
            case FormField::TYPE_FILE:
                return $this->acceptFileGuid($field, is_string($raw) ? trim($raw) : '', $instance);
            case FormField::TYPE_CHECKBOX:
                return $this->sanitizeChoiceValue($field, is_array($raw) ? array_values($raw) : []);
            case FormField::TYPE_RANKING:
                if (is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    $raw = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
                }
                return is_array($raw) ? array_values(array_map('strval', array_filter($raw, 'is_scalar'))) : [];
            case FormField::TYPE_MAXDIFF:
                $raw = $raw ?? [];
                if (is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    $raw = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $raw;
                }
                return $this->normaliseMaxDiff($field, $raw);
            case FormField::TYPE_GRID_SINGLE:
            case FormField::TYPE_GRID_MULTI:
            case FormField::TYPE_BEST_WORST:
            case FormField::TYPE_DRILLDOWN:
            case FormField::TYPE_IMAGE_AREA:
                $raw = $raw ?? [];
                if (is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    $raw = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $raw;
                }
                return $raw;
            case FormField::TYPE_MAP:
                $raw = $raw ?? [];
                if (is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    $raw = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $raw;
                }
                $clean = $field->sanitizeMapAnswer($raw);
                return !empty($clean['features']) ? $clean : [];
            case FormField::TYPE_HTML:
                if (is_array($raw)) {
                    return array_values(array_map('strval', array_filter($raw, 'is_scalar')));
                }
                return is_string($raw) ? trim($raw) : (string)$raw;
            case FormField::TYPE_RADIO:
            case FormField::TYPE_DROPDOWN:
                return $this->sanitizeChoiceValue($field, $raw ?? '');
            case FormField::TYPE_RATING:
                return ($raw === '' || $raw === null || $raw === []) ? '' : $raw;
        }
        return $raw ?? '';
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
            $raw = $fieldPost[$key] ?? null;
            if ($this->isLoopMember($field)) {
                // Every type is parsed per repeat, including files and HTML (V3-17).
                $clean = [];
                if (is_array($raw)) {
                    foreach ($raw as $instance => $cell) {
                        $clean[(string)$instance] = $this->parsePosted($field, $cell, (string)$instance);
                    }
                }
                $this->values[$field->id] = $clean;
                continue;
            }
            $this->values[$field->id] = $this->parsePosted($field, $raw, '');
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

    private function acceptFileGuid(FormField $field, string $guid, string $instance = ''): string
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
            $storedQuery = FormAnswerField::find()
                ->select('value')
                ->where(['answer_id' => (int)$answer->id, 'field_id' => (int)$field->id]);
            if (\humhub\modules\thiscoveryForms\services\LoopService::columnReady()) {
                // A loop repeat's file is matched against that repeat's stored cell (V3-17).
                $storedQuery->andWhere(['instance_key' => $instance]);
            }
            $stored = $storedQuery->scalar();
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
                $this->values[$id] = $meta->valueFor($field->getRespondentMetaKey(), $this->values[$id] ?? null, null, $this->form);
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
     * When "Other" is selected, store "Other: typed text" as the answer value. In a loop,
     * each repeat has its own text, posted as other_text[field][repeat] (V3-45).
     */
    protected function applyOtherSpecify($post): void
    {
        $otherPost = $post['SubmitForm']['other_text'] ?? ($post['other_text'] ?? []);
        if (!is_array($otherPost)) {
            return;
        }

        $frozen = array_map('intval', $this->frozenFieldIds);
        foreach ($this->form->fields as $field) {
            if (!in_array($field->type, [FormField::TYPE_DROPDOWN, FormField::TYPE_RADIO, FormField::TYPE_CHECKBOX], true)) {
                continue;
            }
            if (in_array((int)$field->id, $frozen, true)) {
                // A frozen answer (Delphi consensus) is not changed by posted "specify" text (V3-50).
                continue;
            }
            $current = $this->values[$field->id] ?? null;
            $loop = $this->isLoopMember($field) && is_array($current);
            $otherLabel = $field->findOtherOption();
            if ($otherLabel === null) {
                $cells = $loop ? array_values($current) : [$current];
                foreach ($cells as $cell) {
                    foreach (is_array($cell) ? $cell : [$cell] as $item) {
                        if (is_scalar($item) && $item !== '' && FormField::isOtherOption((string)$item)) {
                            $otherLabel = (string)$item;
                            break 2;
                        }
                    }
                }
            }
            if ($otherLabel === null) {
                continue;
            }
            $posted = $otherPost[(string)$field->id] ?? '';
            if ($loop) {
                $texts = is_array($posted) ? $posted : [];
                foreach ($current as $instance => $cell) {
                    $text = trim((string)(is_scalar($texts[(string)$instance] ?? null) ? $texts[(string)$instance] : ''));
                    if ($text !== '') {
                        $this->values[$field->id][(string)$instance] = $this->withOtherText($field, $otherLabel, $cell, $text);
                    }
                }
                continue;
            }
            $text = trim((string)(is_scalar($posted) ? $posted : ''));
            if ($text !== '') {
                $this->values[$field->id] = $this->withOtherText($field, $otherLabel, $current, $text);
            }
        }
    }

    /**
     * @param mixed $value one answer (a list for a checkbox)
     * @return mixed
     */
    private function withOtherText(FormField $field, string $otherLabel, $value, string $text)
    {
        $stored = FormField::otherSpecifyPrefix($otherLabel) . $text;
        $isOther = static fn($item): bool => is_scalar($item)
            && ((string)$item === $otherLabel || str_starts_with((string)$item, FormField::otherSpecifyPrefix($otherLabel)));
        if ($field->type === FormField::TYPE_CHECKBOX) {
            if (!is_array($value)) {
                return $value;
            }
            return array_map(static fn($item) => $isOther($item) ? $stored : $item, array_values($value));
        }
        if (is_array($value)) {
            return $value;
        }
        return $isOther($value) ? $stored : $value;
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

    /**
     * Load everything a formula may read, once, from the response rather than the current
     * request (V3-9, V3-13): the frozen date, named formulas, stored action variables, and the
     * declared URL parameters captured when the response started.
     * Returns URL values that were read from this request and are not stored yet.
     *
     * @return array<string,string>
     */
    public function prepareFormulaValues(?FormAnswer $answer): array
    {
        $this->values['__today'] = \humhub\modules\thiscoveryForms\services\formula\FormulaRuntime::frozenToday($this->form, $answer);
        $this->values['__named'] = \humhub\modules\thiscoveryForms\services\formula\FormulaRuntime::named($this->form);
        $this->values['__loops'] = array_keys((new \humhub\modules\thiscoveryForms\services\LoopService())->loopFieldIds($this->form));
        $stored = [];
        if ($answer && !$answer->isNewRecord && $answer->hasAttribute('variables_json')) {
            $decoded = json_decode((string)$answer->variables_json, true);
            $stored = is_array($decoded) ? $decoded : [];
        }
        foreach ($stored as $name => $leaf) {
            $value = is_array($leaf) ? (string)($leaf['v'] ?? '') : (string)$leaf;
            if (str_starts_with((string)$name, 'url:')) {
                $this->values[(string)$name] = $value;
            } else {
                $this->values['var:' . $name] = $value;
            }
        }
        // Panel attributes for [panel:key], never on a fully anonymous form (ADR-004).
        $anonymous = $this->form->hidesIdentityFromManagers() && \humhub\modules\thiscoveryForms\Module::identityEnforced();
        $memberId = (int)($this->panelMemberId ?: ($answer->panel_member_id ?? 0));
        if (!$anonymous && $memberId > 0) {
            $member = FormPanelMember::findOne($memberId);
            foreach ($member ? $member->getDemographics() : [] as $key => $value) {
                if (is_scalar($value)) {
                    $this->values['panel.' . $key] = (string)$value;
                }
            }
        }
        $fresh = [];
        $probe = [];
        $this->form->applyDeclaredUrlParams($probe, Yii::$app->request->get());
        foreach ($probe as $key => $value) {
            if (array_key_exists($key, $stored)) {
                continue;
            }
            $this->values[$key] = $value;
            if ($value !== '') {
                $fresh[$key] = (string)$value;
            }
        }
        return $fresh;
    }

    /** Store URL values captured on this request so resume and edits keep them (V3-13). */
    private function persistUrlValues(FormAnswer $answer, array $fresh): void
    {
        if ($fresh === [] || $answer->isNewRecord || !$answer->hasAttribute('variables_json')) {
            return;
        }
        $decoded = json_decode((string)$answer->variables_json, true);
        $stored = is_array($decoded) ? $decoded : [];
        foreach ($fresh as $key => $value) {
            $stored[$key] = ['t' => 'text', 'v' => $value];
        }
        $answer->variables_json = json_encode($stored, JSON_UNESCAPED_UNICODE);
        $answer->updateAttributes(['variables_json' => $answer->variables_json]);
    }

    public function validateFields(): void
    {
        $this->prepareFormulaValues($this->editingAnswer instanceof FormAnswer ? $this->editingAnswer : null);
        \humhub\modules\thiscoveryForms\services\formula\FormulaRuntime::fill(
            $this->values,
            array_values($this->form->fields),
            (string)$this->values['__today']
        );
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
            $value = $this->values[$field->id] ?? null;
            if ($this->isLoopMember($field)) {
                $this->validateLoopField($field, $value);
                continue;
            }
            if (!$field->isVisible($this->values, $this->form->fields)) {
                continue;
            }
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

            $this->validateFieldValue($field, $value);
            $this->validateAnswerCheck($field);

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
        if ($this->scenario !== self::SCENARIO_DRAFT && $this->form) {
            $loops = new \humhub\modules\thiscoveryForms\services\LoopService();
            if ($loops->rosterBelowMinimum($this->form, $this->editingAnswer, array_values($this->form->fields))) {
                $this->addError('values', Yii::t('ThiscoveryFormsModule.base', 'Add the required rows before submitting.'));
            }
        }
    }

    /**
     * DAT-11: on a single-response form, a second completed response from the same person (in
     * another tab, or a double submit) is refused. The form row is locked first, so two
     * submits cannot both see "no response yet".
     */
    private function duplicateSingleResponse(FormAnswer $answer, bool $asDraft, bool $isTest, bool $anonymous): bool
    {
        if ($asDraft || $isTest || !$this->form || (int)$this->form->allow_multiple === 1) {
            return false;
        }
        if (!$answer->isNewRecord && !$answer->isInProgress()) {
            return false;
        }
        $who = [];
        if ($answer->panel_member_id) {
            $who['panel_member_id'] = (int)$answer->panel_member_id;
        } elseif (!$anonymous && !Yii::$app->user->isGuest) {
            $who['created_by'] = (int)Yii::$app->user->id;
        } else {
            return false;
        }
        Yii::$app->db->createCommand('SELECT id FROM ' . CustomForm::tableName() . ' WHERE id = :id FOR UPDATE', [
            ':id' => (int)$this->form->id,
        ])->queryScalar();
        $query = FormAnswer::find()->where($who + [
            'form_id' => (int)$this->form->id,
            'status' => FormAnswer::STATUS_COMPLETE,
            'is_test' => 0,
            'wave_id' => $answer->wave_id ?: null,
            'round_id' => $answer->round_id ?: null,
        ]);
        if (!$answer->isNewRecord) {
            $query->andWhere(['<>', 'id', (int)$answer->id]);
        }
        return $query->exists();
    }

    private function isRequiredToRespondent(FormField $field): bool
    {
        if ($field->isHiddenFromRespondent() || $field->type === FormField::TYPE_RESPONDENT_META) {
            return false;
        }
        if ($field->type === FormField::TYPE_HTML) {
            return (bool)$field->getHtmlConfig()['required'];
        }
        return (bool)$field->required;
    }

    /** @var array<int, list<int>>|null */
    private ?array $loopScopes = null;

    /**
     * The answers as one loop repeat sees them.
     *
     * @return array<int|string,mixed>
     */
    private function scopedValues(FormField $field, string $path): array
    {
        $loops = new \humhub\modules\thiscoveryForms\services\LoopService();
        if ($this->loopScopes === null) {
            $this->loopScopes = $loops->loopScopes(array_values($this->form->fields));
        }
        return $loops->scopedValues($this->values, $this->loopScopes, (int)$field->id, $path);
    }

    /**
     * Visibility and required are judged per repeat, with the other answers in the
     * loop read from the same repeat (V3-18).
     *
     * @param mixed $value
     */
    private function validateLoopField(FormField $field, $value): void
    {
        $loops = new \humhub\modules\thiscoveryForms\services\LoopService();
        $required = $this->isRequiredToRespondent($field) && $this->scenario !== self::SCENARIO_DRAFT;
        foreach ($loops->shownPaths(array_values($this->form->fields), $field, $this->values) as $instance) {
            $code = (string)$instance['code'];
            if (!$field->isVisible($this->scopedValues($field, $code), $this->form->fields)) {
                continue;
            }
            $cell = is_array($value) ? ($value[$code] ?? null) : null;
            if ($this->isEmptyValue($cell)) {
                if ($required) {
                    $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" is required for {repeat}.', [
                        'label' => $field->label,
                        'repeat' => (string)($instance['label'] ?? $code),
                    ]));
                }
                continue;
            }
            $this->validateFieldValue($field, $cell);
        }
    }

    /**
     * Type rules for one stored answer, or one loop repeat.
     *
     * @param mixed $value
     */
    private function validateFieldValue(FormField $field, $value): void
    {
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
                    // Plain decimals only: 1e5 passed is_numeric but was empty in formulas (V3-54).
                    if (!is_numeric($value) || \humhub\modules\thiscoveryForms\services\formula\Decimal::canonical(trim((string)$value)) === null) {
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
                case FormField::TYPE_TEXT:
                case FormField::TYPE_TEXTAREA:
                    $this->validateTextRules($field, $value);
                    break;
                case FormField::TYPE_DATE:
                    $this->validateDateRules($field, $value);
                    break;
            }
    }

    /** Length and pattern (LOG-12). The hard cap applies even with no limit set. */
    private function validateTextRules(FormField $field, $value): void
    {
        $text = is_array($value) ? '' : trim((string)$value);
        $rules = $field->getValidation();
        $length = mb_strlen($text);
        $max = $field->maxTextLength();
        if ($length > $max) {
            $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" can be at most {n} characters.', [
                'label' => $field->label,
                'n' => $max,
            ]));
            return;
        }
        if ($rules['min_length'] !== '' && $length < (int)$rules['min_length']) {
            $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" must be at least {n} characters.', [
                'label' => $field->label,
                'n' => (int)$rules['min_length'],
            ]));
            return;
        }
        if ($rules['pattern'] !== '' && !FormField::patternMatches($rules['pattern'], $text)) {
            $this->addError('values', $rules['pattern_message'] !== ''
                ? '"' . $field->label . '": ' . $rules['pattern_message']
                : Yii::t('ThiscoveryFormsModule.base', '"{label}" is not in the expected format.', ['label' => $field->label]));
        }
    }

    /** A real date, inside the question's earliest and latest dates (LOG-12). */
    private function validateDateRules(FormField $field, $value): void
    {
        $text = is_array($value) ? '' : trim((string)$value);
        if (!FormField::isRealDate($text)) {
            $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" must be a date.', ['label' => $field->label]));
            return;
        }
        $min = $field->dateBound('date_min');
        $max = $field->dateBound('date_max');
        if ($min !== '' && $text < $min) {
            $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" must be on or after {date}.', ['label' => $field->label, 'date' => $min]));
        } elseif ($max !== '' && $text > $max) {
            $this->addError('values', Yii::t('ThiscoveryFormsModule.base', '"{label}" must be on or before {date}.', ['label' => $field->label, 'date' => $max]));
        }
    }

    /**
     * The question's answer check: a formula over this and other answers that must be true,
     * for example [end] >= [start] or sum([a],[b],[c]) = 100 (LOG-12).
     */
    private function validateAnswerCheck(FormField $field): void
    {
        $tree = $field->getValidationCheck();
        if ($tree === null || $this->scenario === self::SCENARIO_DRAFT) {
            return;
        }
        if ((new \humhub\modules\thiscoveryForms\services\LogicEngine())->rulesMet(['when' => $tree], $this->values, $this->form->fields)) {
            return;
        }
        $message = $field->getValidation()['check_message'];
        $this->addError('values', $message !== ''
            ? '"' . $field->label . '": ' . $message
            : Yii::t('ThiscoveryFormsModule.base', '"{label}" does not fit with your other answers.', ['label' => $field->label]));
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

        if ($isTest) {
            // A test or preview submission never advances live rotation counters (V3-48).
            \humhub\modules\thiscoveryForms\services\RandomisationService::$preview = true;
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

        $this->auditing = $existing && !$existing->isNewRecord && $existing->isComplete();
        $this->managerEdit = !$isTest && self::isManagerEdit($this->form, $existing);
        if ($this->managerEdit && $this->changeReason() === '') {
            $this->addError('values', Yii::t('ThiscoveryFormsModule.base', 'Give a reason for changing this response.'));
            return null;
        }

        $pinnedEdition = $this->resolvePinnedEdition($isTest);
        if ($pinnedEdition === false) {
            $this->addError('values', Yii::t('ThiscoveryFormsModule.base', 'This form edition could not be confirmed. Please reload the page and try again.'));
            return null;
        }
        // A new response is checked against the current edition. If the page was opened on an
        // earlier one, it would be validated against one and stamped with the other: ask the
        // respondent to check the updated form instead (V3-52). Drafts keep their own edition.
        $isNewResponse = !$existing || $existing->isNewRecord || !(int)$existing->edition_id;
        if (!$asDraft && $isNewResponse && is_int($pinnedEdition) && $pinnedEdition > 0
            && $this->form && (int)$this->form->current_edition_id > 0 && $pinnedEdition !== (int)$this->form->current_edition_id) {
            $this->addError('values', Yii::t('ThiscoveryFormsModule.base', 'This form was updated while you were filling it in. Your answers are kept: please check them and submit again.'));
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
                // Only the keyed hash is stored; the plain code stays in this session (DAT-14).
                $plainCode = (new \humhub\modules\thiscoveryForms\services\ResumeService())->generateCode();
                $answer->resume_code = \humhub\modules\thiscoveryForms\services\ResumeService::hash($plainCode);
                \humhub\modules\thiscoveryForms\services\ResumeService::rememberPlain((int)$this->form->id, $plainCode);
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

        $answerSchema = $answer::getTableSchema();
        if ($answerSchema && isset($answerSchema->columns['formula_today']) && !$answer->formula_today) {
            $answer->formula_today = \humhub\modules\thiscoveryForms\services\formula\FormulaRuntime::today($this->form);
        }

        if (!$isTest && !$answer->edition_id) {
            if (is_int($pinnedEdition) && $pinnedEdition > 0) {
                $answer->edition_id = $pinnedEdition;
            } elseif ($this->form && $this->form->current_edition_id) {
                $answer->edition_id = (int)$this->form->current_edition_id;
            }
        }

        // DAT-11: a double click or a resubmitted page posts the same token; the response it
        // already created is returned instead of a second one.
        $submitToken = '';
        try {
            $submitToken = (string)Yii::$app->request->post('submit_token', '');
        } catch (\Throwable $e) {
            $submitToken = '';
        }
        $tokenKey = preg_match('/^[a-f0-9]{32}$/', $submitToken) ? 'cf-submit-' . (int)$this->form->id . '-' . $submitToken : null;
        if ($tokenKey && !$asDraft && !$isTest) {
            $previousId = (int)Yii::$app->cache->get($tokenKey);
            $previous = $previousId > 0 ? FormAnswer::findOne(['id' => $previousId, 'form_id' => (int)$this->form->id]) : null;
            if ($previous && $previous->isComplete()) {
                return $previous;
            }
        }

        $fileGuids = [];
        $db = Yii::$app->db;
        FormAnswerField::$deferFileDeletes = true;
        $transaction = $db->beginTransaction();
        try {
            if ($this->duplicateSingleResponse($answer, $asDraft, $isTest, (bool)$anonymous)) {
                $transaction->rollBack();
                FormAnswerField::discardDeferredFiles();
                $this->addError('values', Yii::t('ThiscoveryFormsModule.base', 'You have already submitted this form.'));
                return null;
            }
            if (!$answer->save()) {
                $transaction->rollBack();
                FormAnswerField::discardDeferredFiles();
                $this->addError('values', Yii::t('ThiscoveryFormsModule.base', 'Could not save your submission. Please try again.'));
                return null;
            }
            $answer->populateRelation('form', $this->form);
            // Formula inputs and calculated values first, so stratification, consent and quota
            // cells can read calculated questions, variables and URL values (V3-21).
            $freshUrl = $this->prepareFormulaValues($answer);
            $this->persistUrlValues($answer, $freshUrl);
            \humhub\modules\thiscoveryForms\services\formula\FormulaRuntime::fill(
                $this->values,
                array_values($this->form->fields),
                (string)$this->values['__today']
            );
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
                            $this->auditCell($answer, $existingCell, null);
                            $existingCell->delete();
                        }
                    }
                    continue;
                }
                $loops = new \humhub\modules\thiscoveryForms\services\LoopService();
                if ($instanceColumn && $this->isLoopMember($field)) {
                    $shown = $loops->shownPaths(array_values($this->form->fields), $field, $this->values);
                    $posted = $this->values[$fieldId] ?? [];
                    if (!is_array($posted)) {
                        $posted = [];
                    }
                    $onPath = $this->isOnAnswerPath($field);
                    foreach ($shown as $instance) {
                        $code = (string)$instance['code'];
                        $slot = $fieldId . ':' . $code;
                        if (!array_key_exists($code, $posted)) {
                            continue;
                        }
                        $cell = $posted[$code];
                        // A question hidden in this repeat stores nothing, as outside a loop (V3-18);
                        // a draft keeps it until the final submit (DAT-12).
                        if (!$onPath || !$field->isVisible($this->scopedValues($field, $code), $this->form->fields)) {
                            if ($asDraft) {
                                continue;
                            }
                            $cell = null;
                        }
                        if ($this->isEmptyValue($cell)) {
                            if (isset($existingFields[$slot])) {
                                $this->auditCell($answer, $existingFields[$slot], null);
                                $existingFields[$slot]->delete();
                            }
                            continue;
                        }
                        $af = $existingFields[$slot] ?? new FormAnswerField();
                        // A blank filled in on a completed response is a change too (V3-44).
                        $previous = $af->isNewRecord ? ($this->auditing ? '' : null) : (string)$af->value;
                        $af->answer_id = $answer->id;
                        $af->field_id = $fieldId;
                        $af->instance_key = $code;
                        $af->value = $this->encodeValue($cell);
                        if ($previous !== null) {
                            $this->auditCell($answer, $af, (string)$af->value, $previous);
                        }
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
                if (!$visible && $asDraft) {
                    // A draft keeps an answer that logic now hides: changing an earlier answer
                    // back shows it again with the answer intact. Only the final submit drops it (DAT-12).
                    continue;
                }
                $value = $visible ? ($this->values[$fieldId] ?? null) : null;

                if (!$visible || $this->isEmptyValue($value)) {
                    if (isset($existingFields[$fieldId . ':'])) {
                        $this->auditCell($answer, $existingFields[$fieldId . ':'], null);
                        $existingFields[$fieldId . ':']->delete();
                    }
                    continue;
                }

                if ($field->type === FormField::TYPE_IMAGE_AREA) {
                    $value = $this->scoreImageArea($field, is_array($value) ? $value : []);
                }

                $af = $existingFields[$fieldId . ':'] ?? new FormAnswerField();
                // A blank filled in on a completed response is a change too (V3-44).
                $previous = $af->isNewRecord ? ($this->auditing ? '' : null) : (string)$af->value;
                $af->answer_id = $answer->id;
                $af->field_id = $fieldId;
                if ($instanceColumn) {
                    $af->instance_key = '';
                }
                $af->value = $this->encodeValue($value);
                if ($previous !== null) {
                    $this->auditCell($answer, $af, (string)$af->value, $previous);
                }
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
                $pageCols = ['status', 'outcome', 'current_page', 'updated_at'];
                if ($answer->hasAttribute('current_page_key')) {
                    // The quota sets the page by position; an older saved key must not override it (DAT-16).
                    $answer->current_page_key = null;
                    $pageCols[] = 'current_page_key';
                }
                $answer->save(false, $pageCols);
            } elseif (in_array($this->quotaHalt, ['end', 'redirect'], true)) {
                $answer->status = FormAnswer::STATUS_COMPLETE;
                $answer->resume_code = null;
                $answer->current_page = null;
                if ($anonymous) {
                    $answer->resume_email = null;
                }
                $answer->outcome = FormAnswer::OUTCOME_OVER_QUOTA;
                if ($answer->hasAttribute('current_page_key')) {
                    $answer->current_page_key = null;
                }
                $answer->save(false, array_merge(['status', 'outcome', 'resume_code', 'resume_email', 'current_page', 'updated_at'], $answer->hasAttribute('current_page_key') ? ['current_page_key'] : []));
            } elseif (!$asDraft) {
                $answer->status = FormAnswer::STATUS_COMPLETE;
                $answer->resume_code = null;
                $answer->current_page = null;
                if ($anonymous) {
                    $answer->resume_email = null;
                }
                $answer->outcome = $this->terminalOutcome($answer);
                if ($answer->hasAttribute('current_page_key')) {
                    $answer->current_page_key = null;
                }
                $answer->save(false, array_merge(['status', 'outcome', 'resume_code', 'resume_email', 'current_page', 'updated_at'], $answer->hasAttribute('current_page_key') ? ['current_page_key'] : []));
            }

            \humhub\modules\thiscoveryForms\services\RandomisationService::$current = $answer;
            $opened = (new \humhub\modules\thiscoveryForms\services\LoopService())->applyRosterCommands($answer, $this->form);
            if ($opened !== null) {
                $this->rosterChanged = true;
                $this->rosterKey = $opened;
                $answer->current_instance_key = substr($opened, 0, 191);
                $answer->save(false, ['current_instance_key', 'updated_at']);
            }

            $transaction->commit();
            if ($tokenKey && !$asDraft && !$isTest && $answer->isComplete()) {
                Yii::$app->cache->set($tokenKey, (int)$answer->id, 86400);
            }
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
            // A value that is not one of this question's options is not stored, even if it
            // looks like "Other" (SCO-15).
            return false;
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

    /**
     * A MaxDiff answer is stored with the version shown and, per set, the items that set held,
     * taken from the server's design, so scoring uses exactly what this person saw (SCO-1).
     *
     * @param mixed $raw
     * @return mixed
     */
    private function normaliseMaxDiff(FormField $field, $raw)
    {
        if (!is_array($raw)) {
            return $raw;
        }
        $version = $field->maxDiffVersionFor($raw);
        $answers = $raw['sets'] ?? $raw;
        if (!is_array($answers)) {
            return $raw;
        }
        $out = [];
        foreach ($field->maxDiffSets($version) as $i => $set) {
            $pair = is_array($answers[$i] ?? null) ? $answers[$i] : [];
            $out[$i] = [
                'best' => (string)($pair['best'] ?? ''),
                'worst' => (string)($pair['worst'] ?? ''),
                'items' => array_values(array_map('strval', (array)$set)),
            ];
        }
        $filled = array_filter($out, static fn ($p) => $p['best'] !== '' || $p['worst'] !== '');
        return $filled ? ['version' => $version, 'sets' => $out] : [];
    }

    private function validateMaxDiff(FormField $field, $value): void
    {
        if (!is_array($value)) {
            $this->invalid($field);
            return;
        }
        $sets = $field->maxDiffSets($field->maxDiffVersionFor($value));
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
        $engine = new \humhub\modules\thiscoveryForms\services\LogicEngine();
        foreach ($this->form->fields as $field) {
            $logic = $field->getLogic();
            if (($logic['action'] ?? '') !== \humhub\modules\thiscoveryForms\services\LogicEngine::ACTION_SCREEN_OUT) {
                continue;
            }
            if (!$this->isOnAnswerPath($field)) {
                continue;
            }
            if (!empty($logic['when']) && $engine->rulesMet($logic, $this->values, $this->form->fields)) {
                return FormAnswer::OUTCOME_SCREENED_OUT;
            }
        }
        // Always record a completed questionnaire explicitly, whether or not randomisation
        // is on, so later saves can tell it was already counted (V3-7).
        return (string)$answer->outcome !== '' ? (string)$answer->outcome : FormAnswer::OUTCOME_COMPLETE;
    }

    private function auditCell(FormAnswer $answer, FormAnswerField $cell, ?string $newValue, ?string $oldValue = null): void
    {
        if (!$this->auditing) {
            return;
        }
        $reason = $this->changeReason();
        \humhub\modules\thiscoveryForms\services\AnswerAudit::record(
            $answer,
            (int)$cell->field_id,
            (string)($cell->instance_key ?? ''),
            $oldValue ?? (string)$cell->value,
            $newValue,
            $reason !== '' ? $reason : 'edit',
            // The manager is named even on anonymous forms; that identifies the editor, not the respondent.
            $this->managerEdit ? (int)Yii::$app->user->id : null
        );
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
