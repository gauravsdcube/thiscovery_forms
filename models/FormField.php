<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use humhub\modules\file\models\File;
use humhub\modules\thiscoveryForms\services\ChoiceOptions;
use humhub\modules\thiscoveryForms\services\LogicEngine;
use humhub\modules\thiscoveryForms\services\MaxDiffDesigner;
use humhub\modules\thiscoveryForms\services\PanelFieldService;
use humhub\modules\thiscoveryForms\services\RespondentMetaService;
use Yii;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $form_id
 * @property string $type
 * @property string $label
 * @property string|null $variable
 * @property string|null $variable_live the variable while live, NULL once removed (unique per form, DAT-10)
 * @property string|null $internal_label
 * @property string|null $help_text
 * @property int $required
 * @property int $sort_order
 * @property string|null $options_json
 * @property string|null $deleted_at
 * @property string|null $logic_json
 * @property string|null $actions_json
 * @property string|null $validation_json
 *
 * @property-read CustomForm $form
 * @property-read FormField|null $conditionField
 */
class FormField extends ActiveRecord
{
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_NUMBER = 'number';
    public const TYPE_EMAIL = 'email';
    public const TYPE_DATE = 'date';
    public const TYPE_DROPDOWN = 'dropdown';
    public const TYPE_RADIO = 'radio';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_RATING = 'rating';
    public const RATING_DISPLAY_PILLS = 'pills';
    public const RATING_DISPLAY_THERMOMETER = 'thermometer';
    public const TYPE_RANKING = 'ranking';
    public const TYPE_FILE = 'file';
    public const TYPE_PAGE_BREAK = 'page_break';
    public const TYPE_QUESTION_GROUP = 'question_group';
    public const TYPE_GROUP_END = 'group_end';
    public const TYPE_RAND_BLOCK = 'rand_block';
    public const TYPE_RAND_BLOCK_END = 'rand_block_end';
    public const TYPE_CONSENT = 'consent';
    public const TYPE_RICH_TEXT = 'rich_text';
    public const TYPE_HTML = 'html';
    public const TYPE_GRID_SINGLE = 'grid_single';
    public const TYPE_GRID_MULTI = 'grid_multi';
    public const TYPE_BEST_WORST = 'best_worst';
    public const TYPE_MAXDIFF = 'maxdiff';
    public const TYPE_DRILLDOWN = 'drilldown';
    public const TYPE_IMAGE_AREA = 'image_area';
    public const TYPE_MAP = 'map';
    public const TYPE_RESPONDENT_META = 'respondent_meta';
    public const TYPE_PANEL_ATTR = 'panel_attr';
    public const TYPE_CALCULATED = 'calculated';

    public const CARRY_SELECTED = 'selected';
    public const CARRY_UNSELECTED = 'unselected';
    public const CARRY_ALL = 'all';

    /** Participant-facing question text. The studio name stays at 255. */
    public const LABEL_MAX = 4000;

    public const JUSTIFY_NONE = '';
    public const JUSTIFY_OPTIONAL = 'optional';
    public const JUSTIFY_REQUIRED = 'required';

    public const OP_EQUALS = 'equals';
    public const OP_NOT_EQUALS = 'not_equals';
    public const OP_CONTAINS = 'contains';
    public const OP_CHECKED = 'checked';
    public const OP_GT = 'gt';
    public const OP_GTE = 'gte';
    public const OP_LT = 'lt';
    public const OP_LTE = 'lte';
    public const OP_BETWEEN = 'between';

    public static function tableName()
    {
        return 'custom_form_field';
    }

    public function rules()
    {
        return [
            [['form_id', 'type', 'label'], 'required'],
            [['form_id', 'sort_order'], 'integer'],
            [['required'], 'boolean'],
            [['label'], 'string', 'max' => self::LABEL_MAX],
            [['internal_label'], 'string', 'max' => 255],
            [['variable'], 'string', 'max' => 120],
            [['variable'], 'match', 'pattern' => '/^[A-Za-z][A-Za-z0-9_]*$/', 'skipOnEmpty' => true,
                'message' => Yii::t('ThiscoveryFormsModule.base', 'Variable must start with a letter and use only letters, numbers, and underscores.')],
            [['help_text'], 'string', 'max' => 500],
            [['options_json', 'logic_json', 'actions_json', 'validation_json'], 'string'],
            [['deleted_at'], 'safe'],
            [['type'], 'in', 'range' => array_keys(self::getTypeLabels())],
        ];
    }

    public function attributeLabels()
    {
        return [
            'type' => Yii::t('ThiscoveryFormsModule.base', 'Type'),
            'label' => Yii::t('ThiscoveryFormsModule.base', 'Label'),
            'variable' => Yii::t('ThiscoveryFormsModule.base', 'Variable name'),
            'internal_label' => Yii::t('ThiscoveryFormsModule.base', 'Internal label'),
            'help_text' => Yii::t('ThiscoveryFormsModule.base', 'Help text'),
            'required' => Yii::t('ThiscoveryFormsModule.base', 'Required'),
            'options_json' => Yii::t('ThiscoveryFormsModule.base', 'Options'),
        ];
    }

    public static function slugVariable(string $label, string $type = 'field'): string
    {
        $s = strtolower(trim($label));
        $s = preg_replace('/[^a-z0-9]+/i', '_', $s) ?: '';
        $s = trim((string)$s, '_');
        if ($s === '') {
            $s = $type !== '' ? preg_replace('/[^a-z0-9]+/i', '_', $type) : 'field';
        }
        if (!preg_match('/^[a-z]/i', $s)) {
            $s = 'f_' . $s;
        }
        if (strlen($s) > 100) {
            $s = rtrim(substr($s, 0, 100), '_');
        }
        return $s;
    }

    public function ensureVariable(?array $used = null): string
    {
        $var = trim((string)$this->variable);
        if ($var === '') {
            $var = self::slugVariable((string)$this->label, (string)$this->type);
        }
        if (preg_match('/^id\d+$/i', $var)) {
            // id5 is how formulas address question 5 by id; a variable of that name collided (V3-52).
            $var = 'q_' . $var;
        }
        if ($used !== null) {
            $base = $var;
            $n = 2;
            while (isset($used[strtolower($var)])) {
                $var = $base . '_' . $n;
                $n++;
            }
        }
        $this->variable = $var;
        if (trim((string)$this->internal_label) === '') {
            $this->internal_label = mb_substr((string)$this->label, 0, 255);
        }
        return $var;
    }

    public static function getTypeLabels(): array
    {
        return [
            self::TYPE_TEXT => Yii::t('ThiscoveryFormsModule.base', 'Text'),
            self::TYPE_TEXTAREA => Yii::t('ThiscoveryFormsModule.base', 'Textarea'),
            self::TYPE_NUMBER => Yii::t('ThiscoveryFormsModule.base', 'Number'),
            self::TYPE_EMAIL => Yii::t('ThiscoveryFormsModule.base', 'Email'),
            self::TYPE_DATE => Yii::t('ThiscoveryFormsModule.base', 'Date'),
            self::TYPE_DROPDOWN => Yii::t('ThiscoveryFormsModule.base', 'Dropdown'),
            self::TYPE_RADIO => Yii::t('ThiscoveryFormsModule.base', 'Radio'),
            self::TYPE_CHECKBOX => Yii::t('ThiscoveryFormsModule.base', 'Checkbox'),
            self::TYPE_RATING => Yii::t('ThiscoveryFormsModule.base', 'Rating scale'),
            self::TYPE_RANKING => Yii::t('ThiscoveryFormsModule.base', 'Ranking (drag & drop)'),
            self::TYPE_FILE => Yii::t('ThiscoveryFormsModule.base', 'File upload'),
            self::TYPE_PAGE_BREAK => Yii::t('ThiscoveryFormsModule.base', 'Page break'),
            self::TYPE_QUESTION_GROUP => Yii::t('ThiscoveryFormsModule.base', 'Question group'),
            self::TYPE_GROUP_END => Yii::t('ThiscoveryFormsModule.base', 'Group end'),
            self::TYPE_RAND_BLOCK => Yii::t('ThiscoveryFormsModule.base', 'Randomisation block'),
            self::TYPE_RAND_BLOCK_END => Yii::t('ThiscoveryFormsModule.base', 'Randomisation block end'),
            self::TYPE_CONSENT => Yii::t('ThiscoveryFormsModule.base', 'Consent'),
            self::TYPE_RICH_TEXT => Yii::t('ThiscoveryFormsModule.base', 'Rich text section'),
            self::TYPE_HTML => Yii::t('ThiscoveryFormsModule.base', 'HTML / custom block'),
            self::TYPE_GRID_SINGLE => Yii::t('ThiscoveryFormsModule.base', 'Grid (single)'),
            self::TYPE_GRID_MULTI => Yii::t('ThiscoveryFormsModule.base', 'Grid (multi)'),
            self::TYPE_BEST_WORST => Yii::t('ThiscoveryFormsModule.base', 'Best–worst'),
            self::TYPE_MAXDIFF => Yii::t('ThiscoveryFormsModule.base', 'MaxDiff'),
            self::TYPE_DRILLDOWN => Yii::t('ThiscoveryFormsModule.base', 'Drill-down'),
            self::TYPE_IMAGE_AREA => Yii::t('ThiscoveryFormsModule.base', 'Image area'),
            self::TYPE_MAP => Yii::t('ThiscoveryFormsModule.base', 'Map'),
            self::TYPE_RESPONDENT_META => Yii::t('ThiscoveryFormsModule.base', 'Respondent metadata'),
            self::TYPE_PANEL_ATTR => Yii::t('ThiscoveryFormsModule.base', 'Panel member field'),
            self::TYPE_CALCULATED => Yii::t('ThiscoveryFormsModule.base', 'Calculated'),
        ];
    }

    public static function getOperatorLabels(): array
    {
        return [
            self::OP_EQUALS => Yii::t('ThiscoveryFormsModule.base', 'Equals'),
            self::OP_NOT_EQUALS => Yii::t('ThiscoveryFormsModule.base', 'Does not equal'),
            self::OP_CONTAINS => Yii::t('ThiscoveryFormsModule.base', 'Contains'),
            self::OP_CHECKED => Yii::t('ThiscoveryFormsModule.base', 'Is checked / selected'),
            self::OP_GT => Yii::t('ThiscoveryFormsModule.base', 'Greater than'),
            self::OP_GTE => Yii::t('ThiscoveryFormsModule.base', 'Greater than or equal'),
            self::OP_LT => Yii::t('ThiscoveryFormsModule.base', 'Less than'),
            self::OP_LTE => Yii::t('ThiscoveryFormsModule.base', 'Less than or equal'),
            self::OP_BETWEEN => Yii::t('ThiscoveryFormsModule.base', 'Between (min,max)'),
        ];
    }

    public static function defaultLabelForType(string $type): string
    {
        $labels = self::getTypeLabels();
        return $labels[$type] ?? $type;
    }

    /**
     * Builder POST key for a saved field. Must match data-cf-key in the studio.
     */
    public static function studioKey(?int $id): string
    {
        return $id ? ('id' . $id) : '';
    }

    /**
     * Map a stored field id or studio key onto the studio dropdown value.
     */
    public static function toStudioKey(string $stored): string
    {
        $stored = trim($stored);
        if ($stored !== '' && ctype_digit($stored)) {
            return 'id' . (int)$stored;
        }
        return $stored;
    }

    public function getForm(): ActiveQuery
    {
        return $this->hasOne(CustomForm::class, ['id' => 'form_id']);
    }

    public function beforeDelete()
    {
        if (!parent::beforeDelete()) {
            return false;
        }
        FormFieldI18n::deleteAll(['field_id' => $this->id]);
        return true;
    }

    /** @var bool|null Test override. Null reads the schema once and caches it. */
    public static ?bool $softDeleteOverride = null;

    private static ?bool $softDeleteCache = null;

    public static function supportsSoftDelete(): bool
    {
        if (self::$softDeleteOverride !== null) {
            return self::$softDeleteOverride;
        }
        if (self::$softDeleteCache === null) {
            $schema = Yii::$app->db->getTableSchema(static::tableName(), true);
            self::$softDeleteCache = $schema !== null && isset($schema->columns['deleted_at']);
        }
        return self::$softDeleteCache;
    }

    public function isRemoved(): bool
    {
        if (!self::supportsSoftDelete()) {
            return false;
        }
        return $this->deleted_at !== null && $this->deleted_at !== '';
    }

    /**
     * Hide the question from new fills. Stored answers keep this row id.
     */
    public function beforeValidate()
    {
        // A group end is structural and hidden. The studio stores the type name when the label is blank.
        if ($this->type === self::TYPE_GROUP_END && trim((string)$this->label) === '') {
            $this->label = self::defaultLabelForType(self::TYPE_GROUP_END);
        }
        return parent::beforeValidate();
    }

    /** Keeps variable_live in step, so the unique index holds one live question per name. */
    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        if ($this->hasAttribute('variable_live')) {
            $variable = trim((string)$this->variable);
            $removed = $this->hasAttribute('deleted_at') && $this->deleted_at !== null && $this->deleted_at !== '';
            $this->variable_live = ($variable === '' || $removed || $this->type === self::TYPE_GROUP_END) ? null : $variable;
        }
        return true;
    }

    public function softDelete(): bool
    {
        if (!self::supportsSoftDelete()) {
            return false;
        }
        if ($this->isRemoved()) {
            return true;
        }
        $this->deleted_at = date('Y-m-d H:i:s');
        return (bool)$this->save(false, $this->hasAttribute('variable_live') ? ['deleted_at', 'variable_live'] : ['deleted_at']);
    }

    public function displayLabel(): string
    {
        $label = trim((string)$this->label);
        if ($label === '') {
            $label = trim((string)$this->variable);
        }
        if ($this->isRemoved()) {
            $label .= ' (' . Yii::t('ThiscoveryFormsModule.base', 'removed') . ')';
        }
        return $label;
    }

    public static function isChoiceType(?string $type): bool
    {
        return in_array($type, [self::TYPE_DROPDOWN, self::TYPE_RADIO, self::TYPE_CHECKBOX, self::TYPE_RANKING], true);
    }

    public static function isCarryForwardType(?string $type): bool
    {
        return in_array($type, [self::TYPE_DROPDOWN, self::TYPE_RADIO, self::TYPE_CHECKBOX], true);
    }

    public static function isStructuredAnswerType(?string $type): bool
    {
        return in_array($type, [
            self::TYPE_GRID_SINGLE,
            self::TYPE_GRID_MULTI,
            self::TYPE_BEST_WORST,
            self::TYPE_MAXDIFF,
            self::TYPE_DRILLDOWN,
            self::TYPE_IMAGE_AREA,
            self::TYPE_MAP,
            self::TYPE_RANKING,
            self::TYPE_CHECKBOX,
        ], true);
    }

    public static function isStructuralType(?string $type): bool
    {
        return in_array($type, [
            self::TYPE_PAGE_BREAK,
            self::TYPE_QUESTION_GROUP,
            self::TYPE_GROUP_END,
            self::TYPE_RAND_BLOCK,
            self::TYPE_RAND_BLOCK_END,
            self::TYPE_RICH_TEXT,
        ], true);
    }

    public static function isDisplayOnlyType(?string $type): bool
    {
        return in_array($type, [self::TYPE_PAGE_BREAK, self::TYPE_QUESTION_GROUP, self::TYPE_GROUP_END, self::TYPE_RAND_BLOCK, self::TYPE_RAND_BLOCK_END, self::TYPE_RICH_TEXT], true);
    }

    public static function isQuestionGroup(?string $type): bool
    {
        return $type === self::TYPE_QUESTION_GROUP;
    }

    public static function isGroupEnd(?string $type): bool
    {
        return $type === self::TYPE_GROUP_END;
    }

    public function isStructural(): bool
    {
        return self::isStructuralType($this->type) || $this->type === self::TYPE_PAGE_BREAK;
    }

    public function isDisplayOnly(): bool
    {
        if ($this->type === self::TYPE_HTML) {
            return !$this->getHtmlConfig()['collect'];
        }
        return self::isDisplayOnlyType($this->type);
    }

    public function collectsAnswer(): bool
    {
        if (self::isStructuralType($this->type) || $this->type === self::TYPE_PAGE_BREAK || $this->type === self::TYPE_GROUP_END) {
            return false;
        }
        if ($this->type === self::TYPE_HTML) {
            return (bool)$this->getHtmlConfig()['collect'];
        }
        if ($this->type === self::TYPE_RICH_TEXT || $this->type === self::TYPE_CONSENT) {
            return false;
        }
        return true;
    }

    public function hasOptions(): bool
    {
        return self::isChoiceType($this->type);
    }

    public function getOptions(): array
    {
        return ChoiceOptions::codes($this->getChoicePairs());
    }

    /**
     * @return array<int, array{code:string,label:string}>
     */
    public function getChoicePairs(): array
    {
        return ChoiceOptions::itemsFromDecoded($this->decodedOptions());
    }

    public function optionLabel(string $code): string
    {
        return ChoiceOptions::labelFor($this->getChoicePairs(), $code);
    }

    public function getInstrumentRole(): string
    {
        $decoded = json_decode((string)$this->options_json, true);
        if (!is_array($decoded) || array_is_list($decoded)) {
            return '';
        }
        return trim((string)($decoded['instrument_role'] ?? ''));
    }

    public function setInstrumentRole(string $role): void
    {
        $role = trim($role);
        $decoded = json_decode((string)$this->options_json, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        if ($decoded && array_is_list($decoded)) {
            $decoded = ['options' => $decoded];
        }
        if ($role === '') {
            unset($decoded['instrument_role']);
        } else {
            $decoded['instrument_role'] = $role;
        }
        $this->options_json = $decoded ? json_encode($decoded, JSON_UNESCAPED_UNICODE) : null;
    }

    public function isHiddenFromRespondent(): bool
    {
        if ($this->type === self::TYPE_RESPONDENT_META) {
            return true;
        }
        $decoded = $this->decodedOptions();
        if ($decoded && array_is_list($decoded)) {
            return false;
        }
        return !empty($decoded['hidden']);
    }

    public function setHiddenFromRespondent(bool $hidden): void
    {
        if ($this->type === self::TYPE_RESPONDENT_META) {
            $hidden = true;
        }
        $decoded = $this->decodedOptions();
        if ($decoded && array_is_list($decoded)) {
            $decoded = ['options' => array_values($decoded)];
        }
        if ($hidden) {
            $decoded['hidden'] = true;
        } else {
            unset($decoded['hidden']);
        }
        $this->writeDecodedOptions($decoded);
    }

    public function isAttentionCheck(): bool
    {
        $decoded = $this->decodedOptions();
        if ($decoded && array_is_list($decoded)) {
            return false;
        }
        return !empty($decoded['attentionCheck']);
    }

    public function getAttentionExpected(): string
    {
        $decoded = $this->decodedOptions();
        if ($decoded && array_is_list($decoded)) {
            return '';
        }
        return trim((string)($decoded['attentionExpected'] ?? ''));
    }

    public function setAttentionCheck(bool $enabled, string $expected = ''): void
    {
        $decoded = $this->decodedOptions();
        if ($decoded && array_is_list($decoded)) {
            $decoded = ['options' => array_values($decoded)];
        }
        if ($enabled) {
            $decoded['attentionCheck'] = true;
            $decoded['attentionExpected'] = trim($expected);
        } else {
            unset($decoded['attentionCheck'], $decoded['attentionExpected']);
        }
        $this->writeDecodedOptions($decoded);
    }

    /** Left out of the straight-lining check (INT-6). */
    public function isStraightlineExempt(): bool
    {
        $decoded = $this->decodedOptions();
        return !($decoded && array_is_list($decoded)) && !empty($decoded['straightlineExempt']);
    }

    /** A reverse-keyed item: the same answer here and on a forward item is inconsistent (INT-6). */
    public function isReverseKeyed(): bool
    {
        $decoded = $this->decodedOptions();
        return !($decoded && array_is_list($decoded)) && !empty($decoded['reverseKeyed']);
    }

    /** @return string[] grid row codes that are reverse-keyed */
    public function getReverseRows(): array
    {
        $decoded = $this->decodedOptions();
        if ($decoded && array_is_list($decoded)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', is_array($decoded['reverseRows'] ?? null) ? $decoded['reverseRows'] : [])));
    }

    /**
     * @param string[]|string $reverseRows
     */
    public function setStraightlineConfig(bool $exempt, bool $reverseKeyed, $reverseRows = []): void
    {
        $decoded = $this->decodedOptions();
        if ($decoded && array_is_list($decoded)) {
            $decoded = ['options' => array_values($decoded)];
        }
        if (is_string($reverseRows)) {
            $reverseRows = array_map('trim', explode(',', $reverseRows));
        }
        $rows = array_values(array_unique(array_filter(array_map('strval', is_array($reverseRows) ? $reverseRows : []), 'strlen')));
        foreach (['straightlineExempt' => $exempt, 'reverseKeyed' => $reverseKeyed] as $key => $on) {
            if ($on) {
                $decoded[$key] = true;
            } else {
                unset($decoded[$key]);
            }
        }
        if ($rows) {
            $decoded['reverseRows'] = $rows;
        } else {
            unset($decoded['reverseRows']);
        }
        $this->writeDecodedOptions($decoded);
    }

    /**
     * Whether this question is treated as personal data for CSV export.
     * Defaults on for email, respondent IP, and panel name/email attributes.
     */
    public function isContainsPii(): bool
    {
        $decoded = $this->decodedOptions();
        if ($decoded && !array_is_list($decoded) && array_key_exists('pii', $decoded)) {
            return !empty($decoded['pii']);
        }
        return $this->defaultContainsPii();
    }

    public function defaultContainsPii(): bool
    {
        if ($this->type === self::TYPE_EMAIL) {
            return true;
        }
        if ($this->type === self::TYPE_RESPONDENT_META) {
            return $this->getRespondentMetaKey() === RespondentMetaService::KEY_IP;
        }
        if ($this->type === self::TYPE_PANEL_ATTR) {
            return in_array($this->getPanelAttrKey(), [
                PanelFieldService::KEY_FIRST,
                PanelFieldService::KEY_LAST,
                PanelFieldService::KEY_EMAIL,
                PanelFieldService::KEY_DISPLAY,
            ], true);
        }
        return false;
    }

    public function setContainsPii(bool $pii): void
    {
        $decoded = $this->decodedOptions();
        if ($decoded && array_is_list($decoded)) {
            $decoded = ['options' => array_values($decoded)];
        }
        $decoded['pii'] = $pii;
        $this->writeDecodedOptions($decoded);
    }

    public function getDefaultValue(): string
    {
        $decoded = $this->decodedOptions();
        if ($decoded && array_is_list($decoded)) {
            return '';
        }
        return trim((string)($decoded['defaultValue'] ?? ''));
    }

    public function setDefaultValue(string $value): void
    {
        $value = trim($value);
        $decoded = $this->decodedOptions();
        if ($decoded && array_is_list($decoded)) {
            $decoded = ['options' => array_values($decoded)];
        }
        if ($value === '') {
            unset($decoded['defaultValue']);
        } else {
            $decoded['defaultValue'] = $value;
        }
        $this->writeDecodedOptions($decoded);
    }

    public function getRespondentMetaKey(): string
    {
        if ($this->type !== self::TYPE_RESPONDENT_META) {
            return '';
        }
        $decoded = $this->decodedOptions();
        if ($decoded && array_is_list($decoded)) {
            return '';
        }
        return RespondentMetaService::normalizeKey((string)($decoded['metaKey'] ?? ''));
    }

    public function setRespondentMetaKey(string $key): void
    {
        $key = RespondentMetaService::normalizeKey($key);
        $decoded = $this->decodedOptions();
        if ($decoded && array_is_list($decoded)) {
            $decoded = ['options' => array_values($decoded)];
        }
        $decoded['__type'] = self::TYPE_RESPONDENT_META;
        $decoded['hidden'] = true;
        if ($key === '') {
            unset($decoded['metaKey']);
        } else {
            $decoded['metaKey'] = $key;
        }
        $this->writeDecodedOptions($decoded);
    }

    public function respondentMetaLabel(): string
    {
        return RespondentMetaService::defaultLabel($this->getRespondentMetaKey());
    }

    public function getPanelAttrKey(): string
    {
        if ($this->type !== self::TYPE_PANEL_ATTR) {
            return '';
        }
        $decoded = $this->decodedOptions();
        if ($decoded && array_is_list($decoded)) {
            return '';
        }
        return PanelFieldService::slugify((string)($decoded['panelKey'] ?? ''));
    }

    public function setPanelAttrKey(string $key): void
    {
        $key = PanelFieldService::slugify($key);
        $decoded = $this->decodedOptions();
        if ($decoded && array_is_list($decoded)) {
            $decoded = ['options' => array_values($decoded)];
        }
        $decoded['__type'] = self::TYPE_PANEL_ATTR;
        if ($key === '') {
            unset($decoded['panelKey']);
        } else {
            $decoded['panelKey'] = $key;
        }
        $this->writeDecodedOptions($decoded);
    }

    /**
     * Label shown in results for a stored choice code (or the code itself).
     */
    public function formatChoiceDisplay($value): string
    {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                $parts[] = $this->formatChoiceDisplay($item);
            }
            return implode(', ', array_filter($parts, static fn($p) => $p !== ''));
        }
        $code = trim((string)$value);
        if ($code === '') {
            return '';
        }
        $label = $this->optionLabel($code);
        if ($label !== $code) {
            return $label . ' (' . $code . ')';
        }
        return $code;
    }

    public static function isOtherOption(string $option): bool
    {
        $option = trim($option);
        if ($option === '') {
            return false;
        }
        if (str_contains($option, ':')) {
            return false;
        }
        // "Other", "Other (please specify)", "Other - please state", "Other, please describe";
        // not an option that merely starts with the word, like "Other people's views" (SCO-15).
        return (bool)preg_match('/^other(?:\s*(?:\(\s*(?:please\s+)?(?:specify|state|describe|say|give details)[^)]*\)|[-–,]\s*(?:please\s+)?(?:specify|state|describe|say|give details)\b.*))?\s*\.?$/iu', $option);
    }

    public static function otherSpecifyPrefix(string $option): string
    {
        return rtrim($option, " \t:") . ': ';
    }

    public static function choiceValueMatchesOption(string $value, string $option): bool
    {
        if ($value === $option) {
            return true;
        }
        if (!self::isOtherOption($option)) {
            return false;
        }

        return str_starts_with($value, self::otherSpecifyPrefix($option));
    }

    public static function selectedIncludesOption(array $selected, string $option): bool
    {
        foreach ($selected as $item) {
            if (self::choiceValueMatchesOption((string)$item, $option)) {
                return true;
            }
        }

        return false;
    }

    public function findOtherOption(?array $options = null): ?string
    {
        if (!$this->allowsOtherSpecify()) {
            return null;
        }
        $pairs = $this->getChoicePairs();
        if ($options !== null) {
            $wanted = [];
            foreach ($options as $opt) {
                if (is_array($opt)) {
                    $wanted[] = (string)($opt['code'] ?? $opt['label'] ?? '');
                } else {
                    $wanted[] = (string)$opt;
                }
            }
            foreach ($pairs as $pair) {
                if (!in_array($pair['code'], $wanted, true) && !in_array($pair['label'], $wanted, true)) {
                    continue;
                }
                if (self::isOtherOption($pair['code']) || self::isOtherOption($pair['label'])) {
                    return $pair['code'];
                }
            }
            foreach ($wanted as $opt) {
                if (self::isOtherOption($opt)) {
                    return $opt;
                }
            }
            return null;
        }
        foreach ($pairs as $pair) {
            if (self::isOtherOption($pair['code']) || self::isOtherOption($pair['label'])) {
                return $pair['code'];
            }
        }
        return null;
    }

    /**
     * @return array{selected: bool, text: string}
     */
    public static function otherSpecifyState(string $otherLabel, $value, ?string $otherDisplay = null): array
    {
        $keys = array_unique(array_filter([$otherLabel, $otherDisplay]));
        $items = is_array($value) ? $value : [$value];
        $selected = false;
        $text = '';
        foreach ($items as $item) {
            $item = (string)$item;
            foreach ($keys as $key) {
                $prefix = self::otherSpecifyPrefix($key);
                if ($item === $key) {
                    $selected = true;
                } elseif (str_starts_with($item, $prefix)) {
                    $selected = true;
                    $text = substr($item, strlen($prefix));
                }
            }
        }
        return ['selected' => $selected, 'text' => $text];
    }

    public function allowsChoiceValue(string $item): bool
    {
        foreach ($this->getChoicePairs() as $pair) {
            if ($item === $pair['code'] || $item === $pair['label']) {
                return true;
            }
            if ($item === $pair['code'] . ' | ' . $pair['label']) {
                return true;
            }
            foreach ([$pair['code'], $pair['label']] as $key) {
                // Only this question's own Other option, and only when it asks for text (SCO-15).
                if (!self::isOtherOption($key) || !$this->allowsOtherSpecify()) {
                    continue;
                }
                $prefix = self::otherSpecifyPrefix($key);
                if (str_starts_with($item, $prefix) && strlen($item) > strlen($prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function choiceMatchesExpected($raw, string $expected): bool
    {
        $expected = trim($expected);
        $values = is_array($raw) ? $this->flattenChoiceList($raw) : [trim((string)$raw)];
        foreach ($values as $value) {
            if ($value === $expected) {
                return true;
            }
            foreach ($this->getChoicePairs() as $pair) {
                $keys = array_unique([$pair['code'], $pair['label'], $pair['code'] . ' | ' . $pair['label']]);
                $valueIsPair = in_array($value, $keys, true);
                $expectedIsPair = in_array($expected, $keys, true);
                if ($valueIsPair && $expectedIsPair) {
                    return true;
                }
                foreach ($keys as $key) {
                    if (self::isOtherOption($key) && str_starts_with($value, self::otherSpecifyPrefix($key))) {
                        if ($expectedIsPair || $expected === $key) {
                            return true;
                        }
                    }
                }
            }
            if (self::choiceValueMatchesOption($value, $expected)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return string[]
     */
    private function flattenChoiceList($raw): array
    {
        $out = [];
        $walk = static function ($node) use (&$out, &$walk) {
            if (is_array($node)) {
                foreach ($node as $v) {
                    $walk($v);
                }
                return;
            }
            $s = trim((string)$node);
            if ($s !== '') {
                $out[] = $s;
            }
        };
        $walk($raw);
        return $out;
    }

    public function otherSpecifyIncomplete($value): bool
    {
        if (!$this->requiresOtherText()) {
            return false;
        }
        $label = $this->findOtherOption();
        $items = is_array($value) ? $value : [$value];
        foreach ($items as $item) {
            $item = (string)$item;
            if ($label !== null && $item === $label) {
                return true;
            }
            if ($label === null && self::isOtherOption($item)) {
                return true;
            }
        }

        return false;
    }

    public function isRandomizeOptions(): bool
    {
        if (!$this->options_json || !self::isChoiceType($this->type)) {
            return false;
        }
        $decoded = json_decode($this->options_json, true);
        if (!is_array($decoded)) {
            return false;
        }
        return !empty($decoded['randomize']);
    }

    /**
     * Presentation settings for a choice list, a question group, or a randomisation block.
     *
     * @return array{enabled:bool,method:string,pinFirst:string[],pinLast:string[],show:?int,blockKey:string}
     */
    public function getRandomiseConfig(): array
    {
        $decoded = json_decode((string)$this->options_json, true);
        $decoded = is_array($decoded) ? $decoded : [];
        $cfg = is_array($decoded['randomise'] ?? null) ? $decoded['randomise'] : [];
        $show = $cfg['show'] ?? null;
        return [
            'enabled' => !empty($cfg['enabled']) || ($this->type === self::TYPE_RAND_BLOCK),
            'method' => (($cfg['method'] ?? '') === 'rotate') ? 'rotate' : 'shuffle',
            'pinFirst' => array_values(array_filter(array_map('strval', is_array($cfg['pinFirst'] ?? null) ? $cfg['pinFirst'] : []))),
            'pinLast' => array_values(array_filter(array_map('strval', is_array($cfg['pinLast'] ?? null) ? $cfg['pinLast'] : []))),
            'show' => ($show === null || $show === '') ? null : max(0, (int)$show),
            'blockKey' => trim((string)($decoded['blockKey'] ?? $cfg['blockKey'] ?? '')),
        ];
    }

    /**
     * @return array{document_id:int,must_read:bool,signature:string,witness:bool}
     */
    public function getConsentConfig(): array
    {
        $decoded = json_decode((string)$this->options_json, true);
        $decoded = is_array($decoded) ? $decoded : [];
        $signature = (string)($decoded['signature'] ?? 'typed');
        if (!in_array($signature, ['typed', 'checkbox', 'drawn'], true)) {
            $signature = 'typed';
        }
        return [
            'document_id' => (int)($decoded['document_id'] ?? 0),
            'must_read' => !empty($decoded['must_read']),
            'signature' => $signature,
            'witness' => !empty($decoded['witness']),
        ];
    }

    /**
     * Maximum number of checkbox selections, or null when unlimited.
     */
    public function getMaxSelect(): ?int
    {
        if ($this->type !== self::TYPE_CHECKBOX || !$this->options_json) {
            return null;
        }
        $decoded = json_decode($this->options_json, true);
        if (!is_array($decoded) || empty($decoded['maxSelect'])) {
            return null;
        }
        $max = (int)$decoded['maxSelect'];
        return $max > 0 ? $max : null;
    }

    /**
     * Minimum number of checkbox selections, or null when there is no floor.
     */
    public function getMinSelect(): ?int
    {
        if ($this->type !== self::TYPE_CHECKBOX || !$this->options_json) {
            return null;
        }
        $decoded = json_decode($this->options_json, true);
        if (!is_array($decoded) || empty($decoded['minSelect'])) {
            return null;
        }
        $min = (int)$decoded['minSelect'];
        return $min > 0 ? $min : null;
    }

    public function isMinSelectAll(): bool
    {
        if ($this->type !== self::TYPE_CHECKBOX || !$this->options_json) {
            return false;
        }
        $decoded = json_decode($this->options_json, true);
        return is_array($decoded) && !empty($decoded['minSelectAll']);
    }

    /**
     * Effective minimum ticks for this checkbox question (null = no minimum).
     * Exclusive options (e.g. “None of these”) are not counted toward “require all”.
     */
    public function resolveMinSelect(): ?int
    {
        if ($this->type !== self::TYPE_CHECKBOX) {
            return null;
        }
        $options = $this->getOptions();
        $exclusive = $this->getExclusiveOptions();
        $pool = 0;
        foreach ($options as $opt) {
            if (!in_array((string)$opt, $exclusive, true)) {
                $pool++;
            }
        }
        if ($this->isMinSelectAll()) {
            $min = $pool;
        } else {
            $min = $this->getMinSelect();
            if ($min === null) {
                return null;
            }
        }
        if ($pool > 0 && $min > $pool) {
            $min = $pool;
        }
        $max = $this->getMaxSelect();
        if ($max !== null && $min > $max) {
            $min = $max;
        }
        return $min > 0 ? $min : null;
    }

    /**
     * Checkbox option that cannot be combined with others (e.g. "None of these").
     * Pipe-separated values in exclusiveOption are treated as multiple exclusive choices.
     */
    public function getExclusiveOption(): ?string
    {
        $list = $this->getExclusiveOptions();
        return $list[0] ?? null;
    }

    /**
     * @return string[]
     */
    public function getExclusiveOptions(): array
    {
        if ($this->type !== self::TYPE_CHECKBOX || !$this->options_json) {
            return [];
        }
        $decoded = json_decode($this->options_json, true);
        if (!is_array($decoded)) {
            return [];
        }
        if (!empty($decoded['exclusiveOptions']) && is_array($decoded['exclusiveOptions'])) {
            return array_values(array_filter(array_map('strval', $decoded['exclusiveOptions']), 'strlen'));
        }
        $exclusive = trim((string)($decoded['exclusiveOption'] ?? ''));
        if ($exclusive === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode('|', $exclusive)), 'strlen'));
    }

    /**
     * Whether an Other choice should show the inline "Please specify" box.
     */
    public function allowsOtherSpecify(): bool
    {
        if (!self::isChoiceType($this->type)) {
            return false;
        }
        $decoded = $this->decodedOptions();
        if ($decoded && !array_is_list($decoded) && array_key_exists('otherSpecify', $decoded)) {
            return !empty($decoded['otherSpecify']);
        }
        return true;
    }

    public function setAllowsOtherSpecify(bool $allow): void
    {
        if (!self::isChoiceType($this->type)) {
            return;
        }
        $decoded = $this->decodedOptions();
        if ($decoded && array_is_list($decoded)) {
            $decoded = ['options' => $decoded];
        }
        if ($allow) {
            unset($decoded['otherSpecify']);
        } else {
            $decoded['otherSpecify'] = false;
        }
        $this->writeDecodedOptions($decoded);
    }

    /**
     * Selecting Other asks for text, and that text must be filled in before Next.
     * Turn this off to keep the box and allow it to stay empty.
     */
    public function requiresOtherText(): bool
    {
        if (!$this->allowsOtherSpecify()) {
            return false;
        }
        $decoded = $this->decodedOptions();
        if ($decoded && !array_is_list($decoded) && array_key_exists('otherSpecifyRequired', $decoded)) {
            return !empty($decoded['otherSpecifyRequired']);
        }
        return true;
    }

    public function setRequiresOtherText(bool $required): void
    {
        if (!self::isChoiceType($this->type)) {
            return;
        }
        $decoded = $this->decodedOptions();
        if ($decoded && array_is_list($decoded)) {
            $decoded = ['options' => $decoded];
        }
        if ($required) {
            unset($decoded['otherSpecifyRequired']);
        } else {
            $decoded['otherSpecifyRequired'] = false;
        }
        $this->writeDecodedOptions($decoded);
    }

    /**
     * Studio option rows with a label but no code get a fixed code when they are created, so
     * the code never follows the label text (DAT-9). A row whose label matches an option that
     * is already stored keeps that option's code; a new one takes the next free number.
     *
     * @param array<int|string,mixed> $rows
     * @return array<int|string,mixed>
     */
    private function assignOptionCodes(array $rows): array
    {
        $blank = static fn ($row) => is_array($row) && array_key_exists('code', $row)
            && trim((string)$row['code']) === '' && trim((string)($row['label'] ?? '')) !== '';
        if (!array_filter($rows, $blank)) {
            return $rows;
        }
        $stored = [];
        // Numbers already used, now or before, are never handed out again, so a removed
        // option's code is not reused for a different answer (codeSeq is the high-water mark).
        $prevDecoded = json_decode((string)$this->options_json, true);
        $next = is_array($prevDecoded) && !array_is_list($prevDecoded) ? (int)($prevDecoded['codeSeq'] ?? 0) + 1 : 1;
        foreach ($this->options_json ? $this->getChoicePairs() : [] as $pair) {
            $stored[mb_strtolower(trim((string)$pair['label']))] = (string)$pair['code'];
            if (ctype_digit((string)$pair['code'])) {
                $next = max($next, (int)$pair['code'] + 1);
            }
        }
        // Codes this save already uses.
        $taken = [];
        foreach ($rows as $row) {
            if (is_array($row) && trim((string)($row['code'] ?? '')) !== '') {
                $code = trim((string)$row['code']);
                $taken[$code] = true;
                if (ctype_digit($code)) {
                    $next = max($next, (int)$code + 1);
                }
            }
        }
        foreach ($rows as $i => $row) {
            if (!$blank($row)) {
                continue;
            }
            $label = mb_strtolower(trim((string)$row['label']));
            $code = $stored[$label] ?? null;
            if ($code === null || isset($taken[$code])) {
                while (isset($taken[(string)$next])) {
                    $next++;
                }
                $code = (string)$next;
            }
            $rows[$i]['code'] = $code;
            $taken[$code] = true;
        }
        return $rows;
    }

    /**
     * A file question's own limits (SEC-13): allowed extensions (a subset of
     * UploadQuota::TYPES; empty means all of them) and a lower size limit in MB.
     *
     * @return array{types: string[], maxMb: ?int}
     */
    public function getFileRules(): array
    {
        $decoded = $this->type === self::TYPE_FILE ? $this->decodedOptions() : [];
        $types = is_array($decoded['fileTypes'] ?? null) ? array_values(array_map('strval', $decoded['fileTypes'])) : [];
        $max = isset($decoded['fileMaxMb']) && (int)$decoded['fileMaxMb'] > 0 ? (int)$decoded['fileMaxMb'] : null;
        return ['types' => $types, 'maxMb' => $max];
    }

    public function setFileRules($types, $maxMb): void
    {
        if ($this->type !== self::TYPE_FILE) {
            return;
        }
        $list = is_array($types) ? $types : preg_split('/[\s,;]+/', (string)$types);
        $known = array_keys(\humhub\modules\thiscoveryForms\services\UploadQuota::TYPES);
        $clean = [];
        foreach ($list ?: [] as $type) {
            $type = strtolower(ltrim(trim((string)$type), '.'));
            if ($type !== '' && in_array($type, $known, true)) {
                $clean[$type] = true;
            }
        }
        $decoded = $this->decodedOptions();
        if ($clean) {
            $decoded['fileTypes'] = array_keys($clean);
        } else {
            unset($decoded['fileTypes']);
        }
        $cap = (int)floor(\humhub\modules\thiscoveryForms\services\UploadQuota::MAX_FILE_BYTES / 1048576);
        $mb = (int)$maxMb;
        if ($mb > 0) {
            $decoded['fileMaxMb'] = min($mb, $cap);
        } else {
            unset($decoded['fileMaxMb']);
        }
        $this->writeDecodedOptions($decoded);
    }

    public function getNumberMin(): ?float
    {
        return $this->numericBound('min');
    }

    public function getNumberMax(): ?float
    {
        return $this->numericBound('max');
    }

    public function setNumberRange($min, $max): void
    {
        if ($this->type !== self::TYPE_NUMBER) {
            return;
        }
        $decoded = $this->decodedOptions();
        if ($decoded && array_is_list($decoded)) {
            $decoded = ['options' => $decoded];
        }
        foreach (['min' => $min, 'max' => $max] as $key => $raw) {
            if ($raw === '' || $raw === null) {
                unset($decoded[$key]);
                continue;
            }
            if (!is_numeric($raw)) {
                continue;
            }
            $decoded[$key] = 0 + $raw;
        }
        $this->writeDecodedOptions($decoded);
    }

    /**
     * @return array{formula:string,result:string,display:string,places:int}
     */
    public function getFormulaConfig(): array
    {
        $decoded = $this->decodedOptions();
        if (!is_array($decoded) || array_is_list($decoded)) {
            $decoded = [];
        }
        $result = (string)($decoded['result'] ?? 'number');
        if (!in_array($result, ['number', 'text', 'boolean', 'date'], true)) {
            $result = 'number';
        }
        $places = (int)($decoded['places'] ?? 6);
        return [
            'formula' => trim((string)($decoded['formula'] ?? '')),
            'result' => $result,
            'display' => (($decoded['display'] ?? '') === 'hidden') ? 'hidden' : 'readonly',
            'places' => max(0, min(6, $places)),
        ];
    }

    public function setFormulaConfig(string $formula, string $result = 'number', string $display = 'readonly', $places = 6): void
    {
        $decoded = $this->decodedOptions();
        if (!is_array($decoded) || array_is_list($decoded)) {
            $decoded = [];
        }
        $decoded['formula'] = mb_substr(trim($formula), 0, 4000);
        $decoded['result'] = in_array($result, ['number', 'text', 'boolean', 'date'], true) ? $result : 'number';
        $decoded['display'] = $display === 'hidden' ? 'hidden' : 'readonly';
        $decoded['places'] = max(0, min(6, (int)$places));
        $this->writeDecodedOptions($decoded);
    }

    private function numericBound(string $key): ?float
    {
        if ($this->type !== self::TYPE_NUMBER) {
            return null;
        }
        $decoded = $this->decodedOptions();
        if (!is_array($decoded) || !array_key_exists($key, $decoded)) {
            return null;
        }
        $raw = $decoded[$key];
        return is_numeric($raw) ? (float)$raw : null;
    }

    public function getPrefillProfileAttribute(): ?string
    {
        if (!$this->options_json) {
            return null;
        }
        $decoded = json_decode($this->options_json, true);
        if (!is_array($decoded)) {
            return null;
        }
        $name = trim((string)($decoded['prefillProfile'] ?? ''));
        return $name !== '' ? $name : null;
    }

    public function getPrefillValue($user = null): ?string
    {
        $attr = $this->getPrefillProfileAttribute();
        if ($attr === null) {
            return null;
        }
        $user = $user ?: Yii::$app->user->identity;
        if (!$user || empty($user->profile)) {
            return null;
        }
        $val = trim((string)($user->profile->{$attr} ?? ''));
        return $val !== '' ? $val : null;
    }

    public function setOptionsFromText($text, bool $randomize = false, ?int $maxSelect = null, ?string $exclusiveOption = null, ?int $minSelect = null, ?bool $minSelectAll = null): void
    {
        if (is_array($text)) {
            $text = $this->assignOptionCodes($text);
            $items = ChoiceOptions::itemsFromDecoded(array_values($text));
            // Also accept already-normalized [{code,label}]
            if (!$items) {
                $items = [];
                foreach ($text as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $code = trim((string)($item['code'] ?? ''));
                    $label = trim((string)($item['label'] ?? ''));
                    if ($label === '' && $code === '') {
                        continue;
                    }
                    if ($label === '') {
                        $label = $code;
                    }
                    $items[] = ['code' => $code, 'label' => $label];
                }
            }
        } else {
            $lines = preg_split('/\r\n|\r|\n/', (string)$text) ?: [];
            $pairs = [];
            foreach ($lines as $line) {
                $line = trim((string)$line);
                if ($line === '') {
                    continue;
                }
                $pairs[] = $line;
            }
            $items = ChoiceOptions::parseText(implode("\n", $pairs));
        }
        ChoiceOptions::assertCodeConsistency($items);
        $options = ChoiceOptions::toStorage($items);

        $prev = json_decode((string)$this->options_json, true);
        if (!is_array($prev)) {
            $prev = [];
        }

        if ($maxSelect === null && array_key_exists('maxSelect', $prev)) {
            $maxSelect = (int)$prev['maxSelect'];
        }
        if ($minSelect === null && array_key_exists('minSelect', $prev)) {
            $minSelect = (int)$prev['minSelect'];
        }
        if ($minSelectAll === null) {
            $minSelectAll = !empty($prev['minSelectAll']);
        }
        if ($exclusiveOption === null && !empty($prev['exclusiveOption'])) {
            $exclusiveOption = (string)$prev['exclusiveOption'];
        }

        $maxSelect = ($maxSelect !== null && $maxSelect > 0) ? $maxSelect : null;
        $minSelect = ($minSelect !== null && $minSelect > 0) ? $minSelect : null;
        $minSelectAll = (bool)$minSelectAll;
        $exclusiveOption = trim((string)$exclusiveOption);
        $exclusiveOption = $exclusiveOption !== '' ? $exclusiveOption : null;
        if ($exclusiveOption !== null) {
            $resolved = [];
            foreach (array_filter(array_map('trim', explode('|', $exclusiveOption)), 'strlen') as $part) {
                $code = $part;
                foreach ($items as $item) {
                    if ($part === $item['code'] || $part === $item['label']) {
                        $code = $item['code'];
                        break;
                    }
                }
                $resolved[] = $code;
            }
            $exclusiveOption = $resolved ? implode('|', array_unique($resolved)) : null;
        }

        $carryFrom = trim((string)($prev['carryFrom'] ?? ''));
        $carryMode = (string)($prev['carryMode'] ?? '');
        if (!in_array($carryMode, [self::CARRY_SELECTED, self::CARRY_UNSELECTED, self::CARRY_ALL], true)) {
            $carryMode = '';
        }
        $justification = (string)($prev['justification'] ?? '');
        if (!in_array($justification, [self::JUSTIFY_OPTIONAL, self::JUSTIFY_REQUIRED], true)) {
            $justification = '';
        }
        $instrumentRole = trim((string)($prev['instrument_role'] ?? ''));
        $hidden = !empty($prev['hidden']);
        $defaultValue = trim((string)($prev['defaultValue'] ?? ''));
        $otherSpecify = array_key_exists('otherSpecify', $prev) ? !empty($prev['otherSpecify']) : true;
        $otherSpecifyRequired = array_key_exists('otherSpecifyRequired', $prev) ? !empty($prev['otherSpecifyRequired']) : true;

        if (!$options && !$randomize && $maxSelect === null && $minSelect === null && !$minSelectAll && $exclusiveOption === null && $carryFrom === '' && $justification === '' && $instrumentRole === '' && !$hidden && $defaultValue === '' && $otherSpecify && $otherSpecifyRequired) {
            $this->options_json = null;
            return;
        }

        if ($randomize || $maxSelect !== null || $minSelect !== null || $minSelectAll || $exclusiveOption !== null || $carryFrom !== '' || $justification !== '' || $instrumentRole !== '' || $hidden || $defaultValue !== '' || !$otherSpecify || !$otherSpecifyRequired || (isset($options[0]) && is_array($options[0]))) {
            $payload = ['options' => $options];
            if ($randomize) {
                $payload['randomize'] = true;
            }
            if ($maxSelect !== null) {
                $payload['maxSelect'] = $maxSelect;
            }
            if ($minSelect !== null) {
                $payload['minSelect'] = $minSelect;
            }
            if ($minSelectAll) {
                $payload['minSelectAll'] = true;
            }
            if ($exclusiveOption !== null) {
                $payload['exclusiveOption'] = $exclusiveOption;
            }
            if ($carryFrom !== '') {
                $payload['carryFrom'] = $carryFrom;
                $payload['carryMode'] = $carryMode !== '' ? $carryMode : self::CARRY_SELECTED;
            }
            if ($justification !== '') {
                $payload['justification'] = $justification;
            }
            if ($instrumentRole !== '') {
                $payload['instrument_role'] = $instrumentRole;
            }
            if ($hidden) {
                $payload['hidden'] = true;
            }
            if ($defaultValue !== '') {
                $payload['defaultValue'] = $defaultValue;
            }
            if (!$otherSpecify) {
                $payload['otherSpecify'] = false;
            }
            if (!$otherSpecifyRequired) {
                $payload['otherSpecifyRequired'] = false;
            }
            // Highest numeric code ever used on this question (DAT-9).
            $seq = (int)($prev['codeSeq'] ?? 0);
            foreach ($items as $item) {
                if (ctype_digit((string)$item['code'])) {
                    $seq = max($seq, (int)$item['code']);
                }
            }
            if ($seq > 0) {
                $payload['codeSeq'] = $seq;
            }
            $this->options_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        } else {
            $this->options_json = json_encode($options, JSON_UNESCAPED_UNICODE);
        }
    }

    public function getOptionsAsText(): string
    {
        return ChoiceOptions::toText($this->getChoicePairs());
    }

    /**
     * @return array<int, array{code:string,label:string}>
     */
    /** @var array<string, array<int, array{code:string,label:string}>> */
    private static array $drawnOptionOrder = [];

    /** @var array<string, int> session+field => answer that was shown that order */
    private static array $sessionOptionClaim = [];

    public function getShuffledChoicePairs(?int $userId = null, ?FormAnswer $answer = null): array
    {
        $pairs = $this->getChoicePairs();
        $rand = \humhub\modules\thiscoveryForms\services\RandomisationService::class;
        // A new respondent's first page uses the pending (session) orders, the same ones the
        // first save will store (V3-22).
        if (!$answer && $rand::$current instanceof FormAnswer && (int)$rand::$current->form_id === (int)$this->form_id) {
            $answer = $rand::$current;
        }
        $pendingOk = $answer && $answer->isNewRecord && $this->form && $rand::active($this->form);
        if ($answer && (!$answer->isNewRecord || $pendingOk) && $this->isRandomizeOptions()) {
            // Use an order already stored for this response. Do not draw a new one here:
            // the first page is shown before the answer exists, from the session seed below,
            // and the first save must store that same order (LOG-6).
            $key = trim((string)$this->variable) ?: ('q' . (int)$this->id);
            $storedOrders = (new \humhub\modules\thiscoveryForms\services\RandomisationService())->orders($answer);
            $existing = $storedOrders['options'][$key] ?? null;
            if (is_array($existing) && $existing) {
                return $this->pairsInOrder($pairs, array_map('strval', $existing));
            }
        }
        // Every respondent gets their own order, never one shared by all guests (LOG-6).
        if ($answer && !$answer->isNewRecord) {
            $decodedVars = json_decode((string)$answer->vars_json, true);
            $stored = is_array($decodedVars) ? ($decodedVars['option_order'][(string)$this->id] ?? null) : null;
            if (is_array($stored) && $stored) {
                return $this->pairsInOrder($pairs, $stored);
            }
        }
        $exclusive = $this->shufflePinnedLabels();
        $pinned = [];
        $rest = [];
        foreach ($pairs as $pair) {
            $isOther = self::isOtherOption($pair['code']) || self::isOtherOption($pair['label']);
            $isExclusive = in_array((string)$pair['code'], $exclusive, true)
                || in_array((string)$pair['label'], $exclusive, true);
            if ($isOther || $isExclusive) {
                $pinned[] = $pair;
            } else {
                $rest[] = $pair;
            }
        }
        if (!$this->isRandomizeOptions() || count($rest) < 2) {
            return $pairs;
        }

        // The first page is shown before an answer exists, from the session. The first response
        // in that session stores that same order. Another response in the session gets its own
        // order, so guests do not share one (LOG-6). A stored order, above, wins after that.
        $build = function (string $seedKey) use ($rest, $pinned): array {
            $engine = new \humhub\modules\thiscoveryForms\services\RandomisationEngine();
            $order = $engine->shuffle(range(0, count($rest) - 1), $engine->seedInt($seedKey, 'options:' . (int)$this->id));
            $shuffled = [];
            foreach ($order as $idx) {
                $shuffled[] = $rest[$idx];
            }
            return array_merge($shuffled, $pinned);
        };
        $sessionId = Yii::$app->has('session') ? (string)Yii::$app->session->id : '';
        $sessionKey = $sessionId !== '' ? 'session:' . $sessionId : 'guest:' . (int)$this->form_id;
        $sessionOrder = $build($sessionKey);
        if (!$answer || $answer->isNewRecord) {
            return $sessionOrder;
        }
        $cacheKey = (int)$answer->id . ':' . (int)$this->id;
        if (isset(self::$drawnOptionOrder[$cacheKey])) {
            return self::$drawnOptionOrder[$cacheKey];
        }
        $claimKey = $sessionKey . ':' . (int)$this->id;
        if (!isset(self::$sessionOptionClaim[$claimKey]) || self::$sessionOptionClaim[$claimKey] === (int)$answer->id) {
            self::$sessionOptionClaim[$claimKey] = (int)$answer->id;
            self::$drawnOptionOrder[$cacheKey] = $sessionOrder;
            return $sessionOrder;
        }
        $own = $build('answer:' . (int)$answer->id);
        self::$drawnOptionOrder[$cacheKey] = $own;
        return $own;
    }

    /**
     * Deterministic shuffle for a given user so reopen/edit keeps the same order.
     */
    public function getShuffledOptions(?int $userId = null, ?FormAnswer $answer = null): array
    {
        return ChoiceOptions::codes($this->getShuffledChoicePairs($userId, $answer));
    }

    /**
     * @param array<int, array{code:string,label:string}> $pairs
     * @param string[] $codes
     * @return array<int, array{code:string,label:string}>
     */
    private function pairsInOrder(array $pairs, array $codes): array
    {
        $byCode = [];
        foreach ($pairs as $pair) {
            $byCode[(string)$pair['code']] = $pair;
        }
        $out = [];
        foreach ($codes as $code) {
            $code = (string)$code;
            if (isset($byCode[$code])) {
                $out[] = $byCode[$code];
                unset($byCode[$code]);
            }
        }
        foreach ($byCode as $pair) {
            $out[] = $pair;
        }
        return $out;
    }

    public static function storeOptionOrder(CustomForm $form, FormAnswer $answer): void
    {
        if ($answer->isNewRecord) {
            return;
        }
        $vars = json_decode((string)$answer->vars_json, true);
        if (!is_array($vars)) {
            $vars = [];
        }
        $orders = is_array($vars['option_order'] ?? null) ? $vars['option_order'] : [];
        $changed = false;
        foreach ($form->fields as $field) {
            if (!$field->isRandomizeOptions() || !FormField::isChoiceType($field->type)) {
                continue;
            }
            $id = (string)$field->id;
            if (!empty($orders[$id]) && is_array($orders[$id])) {
                continue;
            }
            $orders[$id] = $field->getShuffledOptions(null, $answer);
            $changed = true;
        }
        if (!$changed) {
            return;
        }
        $vars['option_order'] = $orders;
        $answer->vars_json = json_encode($vars, JSON_UNESCAPED_UNICODE);
        $answer->save(false, ['vars_json', 'updated_at']);
    }

    /**
     * @return string[]
     */
    public function shufflePinnedLabels(): array
    {
        $labels = $this->getExclusiveOptions();
        $decoded = json_decode((string)$this->options_json, true);
        if (is_array($decoded) && !array_is_list($decoded)) {
            $extra = trim((string)($decoded['exclusiveOption'] ?? ''));
            foreach (array_filter(array_map('trim', explode('|', $extra)), 'strlen') as $part) {
                $labels[] = $part;
            }
        }
        return array_values(array_unique($labels));
    }

    public static function getRatingDisplayLabels(): array
    {
        return [
            self::RATING_DISPLAY_PILLS => Yii::t('ThiscoveryFormsModule.base', 'Horizontal pills'),
            self::RATING_DISPLAY_THERMOMETER => Yii::t('ThiscoveryFormsModule.base', 'Vertical thermometer'),
        ];
    }

    public function setRatingScale(array $config): void
    {
        $min = max(0, (int)($config['min'] ?? 1));
        $max = max($min + 1, (int)($config['max'] ?? 5));
        $step = max(1, (int)($config['step'] ?? 1));
        $lowLabel = trim((string)($config['lowLabel'] ?? ''));
        $highLabel = trim((string)($config['highLabel'] ?? ''));
        $display = (string)($config['display'] ?? self::RATING_DISPLAY_PILLS);
        if ($display !== self::RATING_DISPLAY_THERMOMETER) {
            $display = self::RATING_DISPLAY_PILLS;
        }

        $role = trim((string)($config['instrument_role'] ?? ''));
        if ($role === '') {
            $prev = json_decode((string)$this->options_json, true);
            if (is_array($prev)) {
                $role = trim((string)($prev['instrument_role'] ?? ''));
            }
        }

        $payload = [
            '__type' => self::TYPE_RATING,
            'min' => $min,
            'max' => $max,
            'step' => $step,
            'lowLabel' => $lowLabel,
            'highLabel' => $highLabel,
            'display' => $display,
        ];
        if ($role !== '') {
            $payload['instrument_role'] = $role;
        }
        $this->options_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    public function getRatingScale(): array
    {
        $defaults = [
            'min' => 1,
            'max' => 5,
            'step' => 1,
            'lowLabel' => '',
            'highLabel' => '',
            'display' => self::RATING_DISPLAY_PILLS,
        ];

        if (!$this->options_json) {
            return $defaults;
        }

        $decoded = json_decode($this->options_json, true);
        if (!is_array($decoded) || (($decoded['__type'] ?? null) !== self::TYPE_RATING)) {
            return $defaults;
        }

        $display = (string)($decoded['display'] ?? $defaults['display']);
        if ($display !== self::RATING_DISPLAY_THERMOMETER) {
            $display = self::RATING_DISPLAY_PILLS;
        }

        return [
            'min' => max(0, (int)($decoded['min'] ?? $defaults['min'])),
            'max' => max(1, (int)($decoded['max'] ?? $defaults['max'])),
            'step' => max(1, (int)($decoded['step'] ?? $defaults['step'])),
            'lowLabel' => (string)($decoded['lowLabel'] ?? ''),
            'highLabel' => (string)($decoded['highLabel'] ?? ''),
            'display' => $display,
        ];
    }

    public function isThermometerRating(): bool
    {
        return $this->type === self::TYPE_RATING
            && ($this->getRatingScale()['display'] ?? self::RATING_DISPLAY_PILLS) === self::RATING_DISPLAY_THERMOMETER;
    }

    public function setPageBreakConfig(array $config, bool $keepLegacy = false): void
    {
        $pageKey = trim((string)($config['pageKey'] ?? ''));
        if ($pageKey === '') {
            $pageKey = 'p' . substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
        }
        $branches = [];
        foreach (($config['branches'] ?? []) as $branch) {
            if (!is_array($branch)) {
                continue;
            }
            if (isset($branch['fieldKey']) || isset($branch['operator'])) {
                if ($keepLegacy) {
                    $goto = trim((string)($branch['gotoPageKey'] ?? ''));
                    if ($goto === '') {
                        continue;
                    }
                    $branches[] = [
                        'fieldKey' => (string)($branch['fieldKey'] ?? ''),
                        'operator' => (string)($branch['operator'] ?? 'equals'),
                        'value' => (string)($branch['value'] ?? ''),
                        'gotoPageKey' => $goto,
                    ];
                    continue;
                }
                $text = LogicEngine::formulaTextFromLegacy([
                    'rules' => [[
                        'fieldKey' => (string)($branch['fieldKey'] ?? ''),
                        'operator' => (string)($branch['operator'] ?? 'equals'),
                        'value' => (string)($branch['value'] ?? ''),
                    ]],
                ], $this->logicPeers());
                if ($text === null) {
                    throw new \InvalidArgumentException(LogicEngine::legacyMessage());
                }
                $branch['formula'] = $text;
            }
            $formula = trim((string)($branch['formula'] ?? $branch['text'] ?? ''));
            $goto = trim((string)($branch['gotoPageKey'] ?? ''));
            if ($formula === '' || $goto === '') {
                continue;
            }
            $parsed = LogicEngine::fromFormula($formula);
            $branches[] = [
                'v' => 1,
                'text' => $formula,
                'when' => $parsed['when'],
                'gotoPageKey' => $goto,
            ];
        }

        $this->options_json = json_encode([
            '__type' => self::TYPE_PAGE_BREAK,
            'pageKey' => $pageKey,
            'title' => trim((string)($config['title'] ?? '')),
            'branches' => $branches,
            // Where to go when no branch rule matches; empty means the next page (LOG-10).
            'otherwise' => trim((string)($config['otherwise'] ?? '')),
        ], JSON_UNESCAPED_UNICODE);
    }

    public function getPageBreakConfig(): array
    {
        $defaults = [
            'pageKey' => 'p' . (string)($this->id ?: 'new'),
            'title' => '',
            'branches' => [],
            'otherwise' => '',
        ];
        if (!$this->options_json) {
            return $defaults;
        }
        $decoded = json_decode($this->options_json, true);
        if (!is_array($decoded) || (($decoded['__type'] ?? null) !== self::TYPE_PAGE_BREAK)) {
            return $defaults;
        }
        return [
            'pageKey' => (string)($decoded['pageKey'] ?? $defaults['pageKey']),
            'title' => (string)($decoded['title'] ?? ''),
            'branches' => is_array($decoded['branches'] ?? null) ? $decoded['branches'] : [],
            'otherwise' => (string)($decoded['otherwise'] ?? ''),
        ];
    }

    public function setRichTextContent(string $content): void
    {
        $this->options_json = json_encode([
            '__type' => self::TYPE_RICH_TEXT,
            'content' => $content,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function getRichTextContent(): string
    {
        if (!$this->options_json) {
            return '';
        }
        $decoded = json_decode($this->options_json, true);
        if (!is_array($decoded) || (($decoded['__type'] ?? null) !== self::TYPE_RICH_TEXT)) {
            return '';
        }
        return (string)($decoded['content'] ?? '');
    }

    public function setHtmlConfig(array $config): void
    {
        $this->options_json = json_encode([
            '__type' => self::TYPE_HTML,
            'html' => (string)($config['html'] ?? ''),
            'collect' => !empty($config['collect']),
            'variable' => trim((string)($config['variable'] ?? 'value')),
            'instructions' => (string)($config['instructions'] ?? ''),
            'required' => !empty($config['required']),
        ], JSON_UNESCAPED_UNICODE);
    }

    public function getHtmlConfig(): array
    {
        $defaults = [
            'html' => '',
            'collect' => false,
            'variable' => 'value',
            'instructions' => '',
            'required' => false,
        ];
        if (!$this->options_json) {
            return $defaults;
        }
        $decoded = json_decode($this->options_json, true);
        if (!is_array($decoded) || (($decoded['__type'] ?? null) !== self::TYPE_HTML)) {
            return $defaults;
        }
        return [
            'html' => (string)($decoded['html'] ?? ''),
            'collect' => !empty($decoded['collect']),
            'variable' => trim((string)($decoded['variable'] ?? 'value')) ?: 'value',
            'instructions' => (string)($decoded['instructions'] ?? ''),
            'required' => !empty($decoded['required']),
        ];
    }

    public function getCarryForward(): array
    {
        $decoded = $this->decodedOptions();
        $from = trim((string)($decoded['carryFrom'] ?? ''));
        $mode = (string)($decoded['carryMode'] ?? self::CARRY_SELECTED);
        if (!in_array($mode, [self::CARRY_SELECTED, self::CARRY_UNSELECTED, self::CARRY_ALL], true)) {
            $mode = self::CARRY_SELECTED;
        }
        return ['from' => $from, 'mode' => $mode];
    }

    public function setCarryForward(string $from, string $mode = self::CARRY_SELECTED): void
    {
        $from = trim($from);
        if (!in_array($mode, [self::CARRY_SELECTED, self::CARRY_UNSELECTED, self::CARRY_ALL], true)) {
            $mode = self::CARRY_SELECTED;
        }
        $decoded = $this->decodedOptions();
        if ($from === '') {
            unset($decoded['carryFrom'], $decoded['carryMode']);
        } else {
            $decoded['carryFrom'] = $from;
            $decoded['carryMode'] = $mode;
        }
        $this->writeDecodedOptions($decoded);
    }

    public function supportsJustification(): bool
    {
        return in_array($this->type, [
            self::TYPE_DROPDOWN,
            self::TYPE_RADIO,
            self::TYPE_CHECKBOX,
            self::TYPE_RATING,
            self::TYPE_RANKING,
        ], true);
    }

    public static function getJustificationLabels(): array
    {
        return [
            self::JUSTIFY_NONE => Yii::t('ThiscoveryFormsModule.base', 'No comment'),
            self::JUSTIFY_OPTIONAL => Yii::t('ThiscoveryFormsModule.base', 'Optional comment'),
            self::JUSTIFY_REQUIRED => Yii::t('ThiscoveryFormsModule.base', 'Required comment'),
        ];
    }

    public function getJustification(): string
    {
        $decoded = json_decode((string)$this->options_json, true);
        if (!is_array($decoded)) {
            return self::JUSTIFY_NONE;
        }
        $mode = (string)($decoded['justification'] ?? '');
        return in_array($mode, [self::JUSTIFY_OPTIONAL, self::JUSTIFY_REQUIRED], true) ? $mode : self::JUSTIFY_NONE;
    }

    public function getEffectiveJustification(?CustomForm $form = null): string
    {
        $mode = $this->getJustification();
        if ($mode !== self::JUSTIFY_NONE || !$this->supportsJustification()) {
            return $mode;
        }
        $form = $form ?: $this->form;
        if ($form && $form->requiresJustification()) {
            return self::JUSTIFY_REQUIRED;
        }
        return self::JUSTIFY_NONE;
    }

    public function setJustification(string $mode): void
    {
        if (!in_array($mode, [self::JUSTIFY_OPTIONAL, self::JUSTIFY_REQUIRED], true)) {
            $mode = self::JUSTIFY_NONE;
        }
        $decoded = json_decode((string)$this->options_json, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        if ($decoded && $this->isSequentialArray($decoded)) {
            $decoded = ['options' => array_values($decoded)];
        }
        if ($mode === self::JUSTIFY_NONE) {
            unset($decoded['justification']);
        } else {
            $decoded['justification'] = $mode;
        }
        $this->writeDecodedOptions($decoded);
    }

    /**
     * @param array $answers fieldId => value
     * @param FormField[] $fieldsById
     * @return string[]
     */
    public function getEffectiveOptions(array $answers = [], array $fieldsById = [], ?int $userId = null): array
    {
        $carry = $this->getCarryForward();
        if ($carry['from'] === '' || !self::isCarryForwardType($this->type)) {
            return $this->getShuffledOptions($userId);
        }
        $sourceId = ctype_digit($carry['from']) ? (int)$carry['from'] : 0;
        $source = $fieldsById[$sourceId] ?? ($fieldsById[$carry['from']] ?? null);
        if (!$source instanceof self) {
            return $this->getShuffledOptions($userId);
        }
        $sourceOpts = $source->getOptions();
        $raw = $answers[$sourceId] ?? ($answers[$carry['from']] ?? null);
        $selected = [];
        if (is_array($raw)) {
            $selected = array_map('strval', $raw);
        } elseif ($raw !== null && $raw !== '') {
            $selected = [(string)$raw];
        }
        if ($carry['mode'] === self::CARRY_ALL) {
            $options = $sourceOpts;
        } elseif ($carry['mode'] === self::CARRY_UNSELECTED) {
            $options = array_values(array_filter($sourceOpts, static fn($o) => !$source->choiceMatchesExpected($selected, (string)$o)));
        } else {
            $options = array_values(array_filter($sourceOpts, static fn($o) => $source->choiceMatchesExpected($selected, (string)$o)));
        }
        return $options;
    }

    /**
     * @param array $answers fieldId => value
     * @param FormField[] $fieldsById
     * @return array<int, array{code:string,label:string}>
     */
    public function getEffectiveChoicePairs(array $answers = [], array $fieldsById = [], ?int $userId = null): array
    {
        $codes = $this->getEffectiveOptions($answers, $fieldsById, $userId);
        $byCode = [];
        foreach ($this->getChoicePairs() as $pair) {
            $byCode[$pair['code']] = $pair;
        }
        $carry = $this->getCarryForward();
        $sourceId = ctype_digit($carry['from']) ? (int)$carry['from'] : 0;
        $source = $fieldsById[$sourceId] ?? ($fieldsById[$carry['from']] ?? null);
        if ($source instanceof self) {
            foreach ($source->getChoicePairs() as $pair) {
                $byCode[$pair['code']] = $pair;
            }
        }
        $out = [];
        foreach ($codes as $code) {
            $out[] = $byCode[$code] ?? ['code' => (string)$code, 'label' => $this->optionLabel((string)$code)];
        }
        return $out;
    }

    public function setGridConfig(array $config): void
    {
        $rows = self::gridPairs($config['rows'] ?? []);
        $columns = self::gridPairs($config['columns'] ?? []);
        ChoiceOptions::assertCodeConsistency(array_map(static fn($p) => [
            'code' => (string)($p['code'] ?? ''),
            'label' => (string)($p['label'] ?? ''),
        ], $rows));
        ChoiceOptions::assertCodeConsistency(array_map(static fn($p) => [
            'code' => (string)($p['code'] ?? ''),
            'label' => (string)($p['label'] ?? ''),
        ], $columns));
        $layout = (string)($config['mobile_layout'] ?? $config['mobileLayout'] ?? 'scroll');
        if ($layout !== 'stack') {
            $layout = 'scroll';
        }
        $storeRows = array_map(static function (array $p) {
            if (($p['code'] ?? '') === '') {
                return (string)$p['label'];
            }
            return ['code' => (string)$p['code'], 'label' => (string)$p['label']];
        }, $rows);
        $storeCols = array_map(static function (array $p) {
            if (($p['code'] ?? '') === '') {
                return (string)$p['label'];
            }
            return ['code' => (string)$p['code'], 'label' => (string)$p['label']];
        }, $columns);
        $this->options_json = json_encode([
            '__type' => $this->type === self::TYPE_GRID_MULTI ? self::TYPE_GRID_MULTI : self::TYPE_GRID_SINGLE,
            'rows' => $storeRows,
            'columns' => $storeCols,
            'mobile_layout' => $layout,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function getGridConfig(): array
    {
        $decoded = $this->decodedOptions();
        return [
            'rows' => self::gridPairs($decoded['rows'] ?? []),
            'columns' => self::gridPairs($decoded['columns'] ?? []),
            'mobile_layout' => (($decoded['mobile_layout'] ?? 'scroll') === 'stack') ? 'stack' : 'scroll',
        ];
    }

    /**
     * Stable key for a grid row or column: its code, otherwise the stored value, otherwise the label.
     *
     * @param array{code?:string,label?:string,value?:string} $pair
     */
    public static function gridPairKey(array $pair): string
    {
        $code = trim((string)($pair['code'] ?? ''));
        if ($code !== '') {
            return $code;
        }
        $value = trim((string)($pair['value'] ?? ''));
        if ($value !== '') {
            return $value;
        }
        return trim((string)($pair['label'] ?? ''));
    }

    /**
     * @return array<int, array{code:string,label:string,value:string}>
     */
    public static function gridPairs($source): array
    {
        return (new self())->normalizeGridPairs($source);
    }

    /**
     * @return array<int, array{code:string,label:string,value:string}>
     */
    private function normalizeGridPairs($source): array
    {
        if (is_string($source)) {
            $source = preg_split('/\r\n|\r|\n/', $source) ?: [];
        }
        if (!is_array($source)) {
            return [];
        }
        $out = [];
        $anyCode = false;
        $pairs = [];
        foreach ($source as $item) {
            $explicit = '';
            if (is_array($item)) {
                $code = trim((string)($item['code'] ?? ''));
                $label = trim((string)($item['label'] ?? ''));
                $explicit = trim((string)($item['value'] ?? ''));
            } else {
                $line = trim((string)$item);
                if ($line === '') {
                    continue;
                }
                if (preg_match('/^\[([^\]]*)\]\s*(.+)$/u', $line, $m)) {
                    $code = trim($m[1]);
                    $label = trim($m[2]);
                } elseif (preg_match('/^(.+?)\s+\|\s+(.+)$/u', $line, $m)) {
                    $code = trim($m[1]);
                    $label = trim($m[2]);
                } else {
                    $code = '';
                    $label = $line;
                }
            }
            if ($label === '' && $code === '') {
                continue;
            }
            if ($label === '') {
                $label = $code;
            }
            if ($code !== '') {
                $anyCode = true;
            }
            $pairs[] = ['code' => $code, 'label' => $label, 'explicit' => $explicit];
        }
        foreach ($pairs as $pair) {
            if ($anyCode && $pair['code'] === '') {
                // leave empty for studio validation
            }
            if ($pair['explicit'] !== '') {
                $value = $pair['explicit'];
            } else {
                $value = $pair['code'] !== '' ? $pair['code'] : $pair['label'];
            }
            $out[] = ['code' => $pair['code'], 'label' => $pair['label'], 'value' => $value];
        }
        return $out;
    }

    public function setItemsConfig(array $config): void
    {
        $items = $this->linesToList($config['items'] ?? ($config['options'] ?? []));
        $payload = [
            '__type' => $this->type,
            'items' => $items,
        ];
        if ($this->type === self::TYPE_MAXDIFF) {
            $setSize = max(2, (int)($config['setSize'] ?? 4));
            $setCount = max(1, (int)($config['setCount'] ?? max(1, count($items))));
            $stored = json_decode((string)$this->options_json, true);
            $stored = is_array($stored) ? $stored : [];
            // Several versions of the design, each respondent seeing one, so pairs are not the
            // same for everyone (SCO-1).
            $versions = (int)($config['versions'] ?? $stored['versions'] ?? self::MAXDIFF_VERSIONS);
            $versions = max(1, min(self::MAXDIFF_VERSIONS_MAX, $versions));
            $sets = $config['sets'] ?? [];
            if (is_array($sets) && $sets) {
                $designs = [$sets];
            } elseif (!empty($stored['designs']) && ($stored['items'] ?? null) === $items
                && (int)($stored['setSize'] ?? 0) === $setSize && (int)($stored['setCount'] ?? 0) === $setCount
                && count($stored['designs']) === $versions) {
                // Keep the stored designs while nothing they depend on changed: earlier answers
                // are scored against the sets they saw (V3-42).
                $designs = $stored['designs'];
            } else {
                $designs = self::maxDiffDesigns($items, $setSize, $setCount, $versions);
            }
            $payload['setSize'] = $setSize;
            $payload['setCount'] = $setCount;
            $payload['versions'] = count($designs);
            $payload['designs'] = $designs;
            $payload['sets'] = $designs[0] ?? [];
        }
        $this->options_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Source item => translated label. Not stored on the question.
     *
     * @var array<string, string>
     */
    public array $itemLabelOverlay = [];

    /**
     * Label shown for a Best/Worst or MaxDiff item. The posted value stays the source item.
     */
    public function itemDisplayLabel(string $source): string
    {
        $label = trim((string)($this->itemLabelOverlay[$source] ?? ''));
        if ($label === '') {
            return $source;
        }
        if (preg_match('/^(.+?)\s+\|\s+(.+)$/u', $label, $m) && trim($m[1]) === $source) {
            return trim($m[2]);
        }
        return $label;
    }

    public function getItemsConfig(): array
    {
        $decoded = $this->decodedOptions();
        $items = $this->linesToList($decoded['items'] ?? ($decoded['options'] ?? []));
        $setSize = max(2, (int)($decoded['setSize'] ?? 4));
        $setCount = max(1, (int)($decoded['setCount'] ?? max(1, count($items) ?: 1)));
        $sets = is_array($decoded['sets'] ?? null) ? $decoded['sets'] : [];
        $designs = is_array($decoded['designs'] ?? null) && $decoded['designs'] ? array_values($decoded['designs']) : [$sets];
        return [
            'items' => $items,
            'setSize' => $setSize,
            'setCount' => $setCount,
            'sets' => $sets,
            'designs' => $designs,
            'versions' => count($designs),
        ];
    }

    public const MAXDIFF_VERSIONS = 5;
    public const MAXDIFF_VERSIONS_MAX = 20;

    /**
     * Version 1 is the designer's sets; each later version runs the designer on a reordered
     * item list, so it pairs items differently while keeping each version balanced (SCO-1).
     *
     * @param string[] $items
     * @return string[][][]
     */
    public static function maxDiffDesigns(array $items, int $setSize, int $setCount, int $versions): array
    {
        $designer = new MaxDiffDesigner();
        $engine = new \humhub\modules\thiscoveryForms\services\RandomisationEngine();
        $designs = [];
        $seen = [];
        for ($v = 0; $v < $versions; $v++) {
            $order = $v === 0 ? $items : $engine->shuffle($items, $engine->seedInt('maxdiff-design', implode("\0", $items) . ':' . $v));
            $sets = $designer->generateSets(array_values($order), $setSize, $setCount);
            $key = json_encode($sets);
            if ($v > 0 && isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $designs[] = $sets;
        }
        return $designs ?: [[]];
    }

    /** The sets of one version; an unknown version falls back to the first. */
    public function maxDiffSets(int $version): array
    {
        $designs = $this->getItemsConfig()['designs'];
        return $designs[$version] ?? ($designs[0] ?? []);
    }

    /**
     * Which version a respondent sees: the one stored with their answer, otherwise drawn from
     * the session so the version shown before the first save is the one saved (SCO-1).
     *
     * @param mixed $value
     */
    public function maxDiffVersionFor($value): int
    {
        $count = max(1, $this->getItemsConfig()['versions']);
        if (is_array($value) && isset($value['version']) && is_numeric($value['version'])
            && (int)$value['version'] >= 0 && (int)$value['version'] < $count) {
            return (int)$value['version'];
        }
        if ($count === 1) {
            return 0;
        }
        $session = Yii::$app->has('session') ? (string)Yii::$app->session->id : '';
        $engine = new \humhub\modules\thiscoveryForms\services\RandomisationEngine();
        return $engine->seedInt('maxdiff-version:' . ($session !== '' ? $session : 'none'), 'field:' . (int)$this->id) % $count;
    }

    public function setDrilldownTree($tree): void
    {
        if (is_string($tree)) {
            $tree = $this->parseTreeText($tree);
        }
        if (!is_array($tree)) {
            $tree = [];
        }
        $this->options_json = json_encode([
            '__type' => self::TYPE_DRILLDOWN,
            'tree' => $tree,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function getDrilldownTree(): array
    {
        $decoded = $this->decodedOptions();
        return is_array($decoded['tree'] ?? null) ? $decoded['tree'] : [];
    }

    public function getDrilldownTreeAsText(): string
    {
        return $this->treeToText($this->getDrilldownTree());
    }

    public function setImageAreaConfig(array $config): void
    {
        $regions = [];
        foreach (($config['regions'] ?? []) as $region) {
            if (!is_array($region)) {
                continue;
            }
            $label = trim((string)($region['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $id = trim((string)($region['id'] ?? ''));
            if ($id === '') {
                $id = 'r' . substr(md5($label . mt_rand()), 0, 8);
            }
            $regions[] = [
                'id' => $id,
                'label' => $label,
                'x' => $this->clampPercent($region['x'] ?? 0),
                'y' => $this->clampPercent($region['y'] ?? 0),
                'w' => $this->clampPercent($region['w'] ?? 10, 1),
                'h' => $this->clampPercent($region['h'] ?? 10, 1),
                'correct' => !empty($region['correct']),
                'score' => (int)($region['score'] ?? 0),
            ];
        }
        $mode = (string)($config['mode'] ?? 'select');
        if (!in_array($mode, ['select', 'evaluate'], true)) {
            $mode = 'select';
        }
        $this->options_json = json_encode([
            '__type' => self::TYPE_IMAGE_AREA,
            'mode' => $mode,
            'imageUrl' => trim((string)($config['imageUrl'] ?? '')),
            'imageGuid' => trim((string)($config['imageGuid'] ?? '')),
            'multi' => !empty($config['multi']),
            'regions' => $regions,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function getImageAreaConfig(): array
    {
        $decoded = $this->decodedOptions();
        $guid = trim((string)($decoded['imageGuid'] ?? ''));
        $url = (string)($decoded['imageUrl'] ?? '');
        return [
            'mode' => (($decoded['mode'] ?? 'select') === 'evaluate') ? 'evaluate' : 'select',
            'imageUrl' => $url,
            'imageGuid' => $guid,
            'src' => $this->resolveImageSrc($guid, $url),
            'multi' => !empty($decoded['multi']),
            'regions' => is_array($decoded['regions'] ?? null) ? $decoded['regions'] : [],
        ];
    }

    public function getImageSrc(): string
    {
        return $this->getImageAreaConfig()['src'];
    }

    private function resolveImageSrc(string $guid, string $url): string
    {
        if ($guid !== '') {
            $file = File::findOne(['guid' => $guid]);
            if ($file) {
                return (string)$file->getUrl();
            }
        }
        return $url;
    }

    public function setMapConfig(array $config): void
    {
        $types = [];
        foreach ((array)($config['allowedTypes'] ?? ['Point']) as $type) {
            $type = (string)$type;
            if (in_array($type, ['Point', 'LineString', 'Polygon'], true) && !in_array($type, $types, true)) {
                $types[] = $type;
            }
        }
        $max = (int)($config['maxFeatures'] ?? 1);
        if ($max < 1) {
            $max = 1;
        }
        if ($max > 50) {
            $max = 50;
        }
        $lat = (float)($config['lat'] ?? 52.4862);
        $lng = (float)($config['lng'] ?? -1.8904);
        $zoom = (int)($config['zoom'] ?? 7);
        $this->options_json = json_encode([
            '__type' => self::TYPE_MAP,
            'lat' => max(-90.0, min(90.0, $lat)),
            'lng' => max(-180.0, min(180.0, $lng)),
            'zoom' => max(1, min(20, $zoom)),
            'allowedTypes' => $types ?: ['Point'],
            'maxFeatures' => $max,
            'style' => $this->normalizeMapStyle((string)($config['style'] ?? '')),
        ], JSON_UNESCAPED_UNICODE);
    }

    public function getMapConfig(): array
    {
        $decoded = $this->decodedOptions();
        $module = Yii::$app->getModule('thiscovery-mapping');
        $lat = isset($decoded['lat']) ? (float)$decoded['lat'] : (float)($module && method_exists($module, 'getDefaultCenterLat') ? $module->getDefaultCenterLat() : 52.4862);
        $lng = isset($decoded['lng']) ? (float)$decoded['lng'] : (float)($module && method_exists($module, 'getDefaultCenterLng') ? $module->getDefaultCenterLng() : -1.8904);
        $zoom = isset($decoded['zoom']) ? (int)$decoded['zoom'] : (int)($module && method_exists($module, 'getDefaultZoom') ? $module->getDefaultZoom() : 7);
        $types = [];
        foreach ((array)($decoded['allowedTypes'] ?? ['Point']) as $type) {
            $type = (string)$type;
            if (in_array($type, ['Point', 'LineString', 'Polygon'], true) && !in_array($type, $types, true)) {
                $types[] = $type;
            }
        }
        $max = (int)($decoded['maxFeatures'] ?? 1);
        if ($max < 1) {
            $max = 1;
        }
        if ($max > 50) {
            $max = 50;
        }
        return [
            'lat' => max(-90.0, min(90.0, $lat)),
            'lng' => max(-180.0, min(180.0, $lng)),
            'zoom' => max(1, min(20, $zoom)),
            'allowedTypes' => $types ?: ['Point'],
            'maxFeatures' => $max,
            'style' => $this->normalizeMapStyle((string)($decoded['style'] ?? '')),
        ];
    }

    private function normalizeMapStyle(string $style): string
    {
        $style = trim($style);
        if ($style !== '' && class_exists(\humhub\modules\thiscoveryMapping\models\ModuleSettings::class)
            && isset(\humhub\modules\thiscoveryMapping\models\ModuleSettings::styleLabels()[$style])) {
            return $style;
        }
        $module = Yii::$app->getModule('thiscovery-mapping');
        if ($module && method_exists($module, 'getBasemapStyle')) {
            return (string)$module->getBasemapStyle();
        }
        return 'alidade_smooth';
    }

    public function sanitizeMapAnswer($value): array
    {
        $cfg = $this->getMapConfig();
        $empty = ['type' => 'FeatureCollection', 'features' => []];
        if (class_exists(\humhub\modules\thiscoveryMapping\services\GeoJsonValidator::class)) {
            $clean = (new \humhub\modules\thiscoveryMapping\services\GeoJsonValidator())
                ->sanitizeCollection($value, $cfg['allowedTypes'], $cfg['maxFeatures']);
            return is_array($clean) ? $clean : $empty;
        }
        return $empty;
    }

    /** @var array<int,FormField[]> */
    private static array $logicPeers = [];

    /** @return FormField[] */
    private function logicPeers(): array
    {
        $formId = (int)$this->form_id;
        if ($formId <= 0) {
            return [];
        }
        if (!isset(self::$logicPeers[$formId])) {
            self::$logicPeers[$formId] = self::find()->where(['form_id' => $formId])->all();
        }
        return self::$logicPeers[$formId];
    }

    public function getLogic(): array
    {
        $decoded = json_decode((string)$this->logic_json, true);
        if (!is_array($decoded)) {
            return LogicEngine::defaultLogic();
        }
        $upgraded = LogicEngine::upgrade($decoded, $this->logicPeers());
        if ($upgraded === null) {
            return LogicEngine::defaultLogic();
        }
        return $upgraded;
    }

    public function setLogic(array $logic): void
    {
        if (LogicEngine::containsLegacy($logic)) {
            $upgraded = LogicEngine::upgrade($logic, $this->logicPeers());
            if ($upgraded === null || empty($upgraded['when'])) {
                throw new \InvalidArgumentException(LogicEngine::legacyMessage());
            }
            $logic = $upgraded;
        }
        $text = trim((string)($logic['formula'] ?? $logic['text'] ?? ''));
        if ($text !== '' && empty($logic['when'])) {
            $logic = LogicEngine::fromFormula(
                $text,
                (string)($logic['action'] ?? LogicEngine::ACTION_SHOW),
                (string)($logic['gotoPageKey'] ?? $logic['goto'] ?? '')
            );
        }
        $logic = LogicEngine::normalize($logic);
        if (empty($logic['when'])) {
            $this->logic_json = null;
            return;
        }
        $this->logic_json = json_encode([
            'v' => 1,
            'action' => $logic['action'],
            'when' => $logic['when'],
            'text' => $logic['text'],
            'goto' => $logic['gotoPageKey'],
        ], JSON_UNESCAPED_UNICODE);
    }

    /** Longest stored answer, even with no limit set: keeps answers inside the column (LOG-12). */
    public const TEXT_HARD_MAX = 2000;
    public const TEXTAREA_HARD_MAX = 16000;
    public const PATTERN_MAX = 200;

    /**
     * Answer rules for this question (LOG-12), as the studio posts them:
     * min_length, max_length, pattern, pattern_message (text, long text);
     * date_min, date_max (date: YYYY-MM-DD or "today"); check, check_message (any question:
     * a formula that must be true once the question is answered).
     *
     * @return array{min_length:string,max_length:string,pattern:string,pattern_message:string,date_min:string,date_max:string,check:string,check_message:string}
     */
    public function getValidation(): array
    {
        $out = ['min_length' => '', 'max_length' => '', 'pattern' => '', 'pattern_message' => '',
            'date_min' => '', 'date_max' => '', 'check' => '', 'check_message' => '',
            // Delphi consensus for this question, overriding the form's (SCO-5).
            'consensus_agree_from' => '', 'consensus_agree_to' => '', 'consensus_disagree_from' => '',
            'consensus_disagree_to' => '', 'consensus_exclude' => '', 'consensus_iqr_max' => ''];
        if (!$this->hasAttribute('validation_json')) {
            return $out;
        }
        $decoded = json_decode((string)$this->validation_json, true);
        if (!is_array($decoded)) {
            return $out;
        }
        foreach (array_keys($out) as $key) {
            $out[$key] = trim((string)($decoded[$key] ?? ''));
        }
        return $out;
    }

    /**
     * This question's consensus rule, or null to use the form's (SCO-5).
     *
     * @return array{agree_from:?string,agree_to:?string,disagree_from:?string,disagree_to:?string,exclude:string[],iqr_max:?float}|null
     */
    public function getConsensusOverride(): ?array
    {
        $rules = $this->getValidation();
        $v = static fn (string $key): ?string => $rules[$key] !== '' ? $rules[$key] : null;
        $exclude = array_values(array_filter(array_map('trim', explode(',', $rules['consensus_exclude'])), 'strlen'));
        $iqr = $rules['consensus_iqr_max'] !== '' ? (float)$rules['consensus_iqr_max'] : null;
        $out = [
            'agree_from' => $v('consensus_agree_from'),
            'agree_to' => $v('consensus_agree_to'),
            'disagree_from' => $v('consensus_disagree_from'),
            'disagree_to' => $v('consensus_disagree_to'),
            'exclude' => $exclude,
            'iqr_max' => $iqr,
        ];
        return ($out['agree_from'] === null && $out['disagree_from'] === null && !$exclude && $iqr === null) ? null : $out;
    }

    /** The check formula's parsed tree, or null when there is none (or it no longer parses). */
    public function getValidationCheck(): ?array
    {
        $check = $this->getValidation()['check'];
        if ($check === '') {
            return null;
        }
        try {
            return (new \humhub\modules\thiscoveryForms\services\formula\Parser())->parse($check);
        } catch (\humhub\modules\thiscoveryForms\services\formula\FormulaException $e) {
            return null;
        }
    }

    /** Stores the rules, keeping only valid ones; validationErrors() reports the rest at save. */
    public function setValidation($raw): void
    {
        if (!$this->hasAttribute('validation_json')) {
            return;
        }
        $raw = is_array($raw) ? $raw : (is_string($raw) ? (json_decode($raw, true) ?: []) : []);
        $keep = [];
        $text = in_array($this->type, [self::TYPE_TEXT, self::TYPE_TEXTAREA], true);
        if ($text) {
            foreach (['min_length', 'max_length'] as $key) {
                $value = trim((string)($raw[$key] ?? ''));
                if ($value !== '' && ctype_digit($value)) {
                    $keep[$key] = (string)min((int)$value, $this->textHardMax());
                }
            }
            $pattern = trim((string)($raw['pattern'] ?? ''));
            if ($pattern !== '' && self::patternError($pattern) === null) {
                $keep['pattern'] = $pattern;
                $keep['pattern_message'] = trim((string)($raw['pattern_message'] ?? ''));
            }
        }
        if ($this->type === self::TYPE_DATE) {
            foreach (['date_min', 'date_max'] as $key) {
                $value = trim((string)($raw[$key] ?? ''));
                if ($value !== '' && self::dateBoundValid($value)) {
                    $keep[$key] = $value;
                }
            }
        }
        $check = trim((string)($raw['check'] ?? ''));
        if ($check !== '') {
            $keep['check'] = $check;
            $keep['check_message'] = trim((string)($raw['check_message'] ?? ''));
        }
        if (in_array($this->type, [self::TYPE_RADIO, self::TYPE_DROPDOWN, self::TYPE_RATING], true)) {
            foreach (['consensus_agree_from', 'consensus_agree_to', 'consensus_disagree_from', 'consensus_disagree_to', 'consensus_exclude'] as $key) {
                $keep[$key] = mb_substr(trim((string)($raw[$key] ?? '')), 0, 255);
            }
            $iqr = trim((string)($raw['consensus_iqr_max'] ?? ''));
            if ($iqr !== '' && is_numeric($iqr) && (float)$iqr >= 0) {
                $keep['consensus_iqr_max'] = $iqr;
            }
        }
        $keep = array_filter($keep, static fn ($v) => $v !== '');
        $this->validation_json = $keep ? json_encode($keep, JSON_UNESCAPED_UNICODE) : null;
    }

    /**
     * Why posted rules can't be saved.
     *
     * @return string[]
     */
    public static function validationErrors($raw, string $type, string $label): array
    {
        $raw = is_array($raw) ? $raw : [];
        $errors = [];
        $say = static fn (string $m, array $p = []) => Yii::t('ThiscoveryFormsModule.base', $m, ['label' => $label] + $p);
        if (in_array($type, [self::TYPE_TEXT, self::TYPE_TEXTAREA], true)) {
            $min = trim((string)($raw['min_length'] ?? ''));
            $max = trim((string)($raw['max_length'] ?? ''));
            foreach ([$min, $max] as $value) {
                if ($value !== '' && !ctype_digit($value)) {
                    $errors[] = $say('“{label}”: a length must be a whole number.');
                }
            }
            if ($min !== '' && $max !== '' && ctype_digit($min) && ctype_digit($max) && (int)$min > (int)$max) {
                $errors[] = $say('“{label}”: the shortest length is longer than the longest.');
            }
            $pattern = trim((string)($raw['pattern'] ?? ''));
            if ($pattern !== '' && ($why = self::patternError($pattern)) !== null) {
                $errors[] = $say('“{label}”: the answer pattern is not valid ({why}).', ['why' => $why]);
            }
        }
        if ($type === self::TYPE_DATE) {
            $min = trim((string)($raw['date_min'] ?? ''));
            $max = trim((string)($raw['date_max'] ?? ''));
            foreach ([$min, $max] as $value) {
                if ($value !== '' && !self::dateBoundValid($value)) {
                    $errors[] = $say('“{label}”: a date limit must be a real date written YYYY-MM-DD, or today.');
                }
            }
            if ($min !== '' && $max !== '' && $min !== 'today' && $max !== 'today' && self::dateBoundValid($min) && self::dateBoundValid($max) && $min > $max) {
                $errors[] = $say('“{label}”: the earliest date is after the latest.');
            }
        }
        $check = trim((string)($raw['check'] ?? ''));
        if ($check !== '') {
            try {
                (new \humhub\modules\thiscoveryForms\services\formula\Parser())->parse($check);
            } catch (\humhub\modules\thiscoveryForms\services\formula\FormulaException $e) {
                $errors[] = $say('“{label}”: the answer check is not a valid formula: {why}', ['why' => $e->getMessage()]);
            }
        }
        return $errors;
    }

    public function textHardMax(): int
    {
        return $this->type === self::TYPE_TEXTAREA ? self::TEXTAREA_HARD_MAX : self::TEXT_HARD_MAX;
    }

    /** The longest answer allowed: the question's limit, never above the hard cap. */
    public function maxTextLength(): int
    {
        $max = $this->getValidation()['max_length'];
        return $max !== '' ? min((int)$max, $this->textHardMax()) : $this->textHardMax();
    }

    /**
     * A whole-answer regular expression. The author writes the inside; it is anchored and
     * read as Unicode. Patterns are short and checked for compile errors at save.
     */
    public static function patternRegex(string $pattern): string
    {
        return '/^(?:' . str_replace('/', '\/', $pattern) . ')$/u';
    }

    public static function patternError(string $pattern): ?string
    {
        if (mb_strlen($pattern) > self::PATTERN_MAX) {
            return Yii::t('ThiscoveryFormsModule.base', 'longer than {n} characters', ['n' => self::PATTERN_MAX]);
        }
        set_error_handler(static fn () => true);
        try {
            $ok = preg_match(self::patternRegex($pattern), '');
        } finally {
            restore_error_handler();
        }
        return $ok === false ? (preg_last_error_msg() ?: 'syntax') : null;
    }

    /** Whether an answer matches; a pattern that blows the backtrack limit counts as no match. */
    public static function patternMatches(string $pattern, string $value): bool
    {
        $limit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '100000');
        try {
            return @preg_match(self::patternRegex($pattern), $value) === 1;
        } finally {
            ini_set('pcre.backtrack_limit', (string)$limit);
        }
    }

    public static function dateBoundValid(string $value): bool
    {
        return $value === 'today' || self::isRealDate($value);
    }

    public static function isRealDate(string $value): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return false;
        }
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
    }

    /** A date limit as YYYY-MM-DD, with "today" read in the form's time zone. */
    public function dateBound(string $key): string
    {
        $value = $this->getValidation()[$key] ?? '';
        if ($value !== 'today') {
            return $value;
        }
        $zone = \humhub\modules\thiscoveryForms\services\formula\FormulaRuntime::timeZone($this->form);
        return (new \DateTimeImmutable('now', new \DateTimeZone($zone)))->format('Y-m-d');
    }

    public function getActions(): array
    {
        $decoded = json_decode((string)$this->actions_json, true);
        return \humhub\modules\thiscoveryForms\services\FormActionService::normalizeList($decoded);
    }

    public function setActions($actions): void
    {
        $list = \humhub\modules\thiscoveryForms\services\FormActionService::normalizeList($actions);
        $this->actions_json = $list ? json_encode($list, JSON_UNESCAPED_UNICODE) : null;
    }

    public function hasActions(): bool
    {
        return $this->getActions() !== [];
    }

    public function hasCondition(): bool
    {
        $logic = $this->getLogic();
        return !empty($logic['rules']) || !empty($logic['when']);
    }

    /**
     * Whether this field should be shown and validated given current answers.
     * @param array $values fieldId => value
     */
    public function isConditionMet(array $values): bool
    {
        return $this->isVisible($values);
    }

    public function isVisible(array $values, array $orderedFields = []): bool
    {
        return (new LogicEngine())->isFieldVisible($this, $orderedFields, $values);
    }

    /**
     * Evaluate a branch rule against current answers.
     * @param array $values fieldId => value
     * @param array $keyToId tempKey/id map for fieldKey resolution
     */
    public static function evaluateBranch(array $branch, array $values, array $keyToId = [], array $fields = []): bool
    {
        if (isset($branch['fieldKey']) || isset($branch['operator'])) {
            return false;
        }
        return (new LogicEngine())->evaluateRule($branch, $values, $fields);
    }

    public function toPostRow(): array
    {
        $row = [
            'type' => $this->type,
            'label' => $this->label,
            'variable' => $this->variable,
            'internal_label' => $this->internal_label,
            'help_text' => $this->help_text,
            'required' => $this->required ? '1' : '',
            'options' => $this->getOptionsAsText(),
            'option_items' => self::isChoiceType($this->type) ? $this->getChoicePairs() : [],
            'hidden' => $this->isHiddenFromRespondent() ? '1' : '',
            'pii' => $this->isContainsPii() ? '1' : '',
            'straightline_exempt' => $this->isStraightlineExempt() ? '1' : '',
            'reverse_keyed' => $this->isReverseKeyed() ? '1' : '',
            'reverse_rows' => implode(',', $this->getReverseRows()),
            'default_value' => $this->getDefaultValue(),
            'meta_key' => $this->getRespondentMetaKey(),
            'panel_key' => $this->getPanelAttrKey(),
            'randomize' => $this->isRandomizeOptions() ? '1' : '',
            'block_key' => $this->getRandomiseConfig()['blockKey'],
            'randomise_enabled' => $this->getRandomiseConfig()['enabled'] ? '1' : '',
            'randomise_method' => $this->getRandomiseConfig()['method'],
            'randomise_show' => $this->getRandomiseConfig()['show'] === null ? '' : (string)$this->getRandomiseConfig()['show'],
            'randomise_pin_first' => implode(',', $this->getRandomiseConfig()['pinFirst']),
            'randomise_pin_last' => implode(',', $this->getRandomiseConfig()['pinLast']),
            'consent_document_id' => $this->type === self::TYPE_CONSENT ? $this->getConsentConfig()['document_id'] : '',
            'consent_must_read' => $this->type === self::TYPE_CONSENT && $this->getConsentConfig()['must_read'] ? '1' : '',
            'consent_signature' => $this->type === self::TYPE_CONSENT ? $this->getConsentConfig()['signature'] : '',
            'consent_witness' => $this->type === self::TYPE_CONSENT && $this->getConsentConfig()['witness'] ? '1' : '',
            'loop_enabled' => ($loopCfg = (new \humhub\modules\thiscoveryForms\services\LoopService())->config($this)) ? '1' : '',
            'loop_source' => is_array($loopCfg) ? $loopCfg['source'] : '',
            'loop_field_key' => is_array($loopCfg) ? $loopCfg['field_key'] : '',
            'loop_label_field' => is_array($loopCfg) ? (string)($loopCfg['label_field'] ?? '') : '',
            'loop_max' => is_array($loopCfg) ? (string)$loopCfg['max'] : '',
            'loop_min' => is_array($loopCfg) ? (string)$loopCfg['min'] : '',
            'loop_randomise' => is_array($loopCfg) && !empty($loopCfg['randomise']) ? '1' : '',
            'loop_show' => is_array($loopCfg) && $loopCfg['show'] !== null ? (string)$loopCfg['show'] : '',
            'loop_items' => is_array($loopCfg) ? implode("\n", array_map(static fn($item) => $item['code'] . ' | ' . $item['label'], $loopCfg['items'])) : '',
            'max_select' => $this->getMaxSelect(),
            'min_select' => $this->getMinSelect(),
            'min_select_all' => $this->isMinSelectAll() ? '1' : '',
            'exclusive_option' => implode('|', $this->getExclusiveOptions()),
            'other_specify' => $this->allowsOtherSpecify() ? '1' : '0',
            'other_specify_required' => $this->requiresOtherText() ? '1' : '0',
            'formula' => $this->getFormulaConfig()['formula'],
            'formula_result' => $this->getFormulaConfig()['result'],
            'formula_display' => $this->getFormulaConfig()['display'],
            'formula_places' => $this->getFormulaConfig()['places'],
            'number_min' => $this->getNumberMin(),
            'number_max' => $this->getNumberMax(),
            'file_types' => implode(', ', $this->getFileRules()['types']),
            'file_max_mb' => $this->getFileRules()['maxMb'] ?? '',
            'validation' => array_filter($this->getValidation(), static fn ($v) => $v !== ''),
            'prefill_profile' => $this->getPrefillProfileAttribute() ?: '',
            'logic_formula' => $this->getLogic()['text'],
            'logic_action' => $this->getLogic()['action'],
            'logic_combinator' => $this->getLogic()['combinator'],
            'logic_goto' => $this->getLogic()['gotoPageKey'],
            'logic_rules' => $this->getLogic()['rules'],
            'carry_from' => $this->getCarryForward()['from'],
            'carry_mode' => $this->getCarryForward()['mode'],
            'justification' => $this->getJustification(),
            'actions' => $this->getActions(),
        ];

        if ($this->type === self::TYPE_RATING) {
            $scale = $this->getRatingScale();
            $row['rating_min'] = $scale['min'];
            $row['rating_max'] = $scale['max'];
            $row['rating_step'] = $scale['step'];
            $row['rating_low_label'] = $scale['lowLabel'];
            $row['rating_high_label'] = $scale['highLabel'];
            $row['rating_display'] = $scale['display'];
        } elseif ($this->type === self::TYPE_PAGE_BREAK) {
            $cfg = $this->getPageBreakConfig();
            $row['page_key'] = $cfg['pageKey'];
            $row['page_otherwise'] = $cfg['otherwise'];
            $row['page_title'] = $cfg['title'];
            $row['branches'] = $cfg['branches'];
        } elseif ($this->type === self::TYPE_RICH_TEXT) {
            $row['rich_content'] = $this->getRichTextContent();
        } elseif ($this->type === self::TYPE_HTML) {
            $html = $this->getHtmlConfig();
            $row['html_content'] = $html['html'];
            $row['html_collect'] = !empty($html['collect']) ? '1' : '';
            $row['html_variable'] = $html['variable'];
            $row['html_instructions'] = $html['instructions'];
            $row['html_required'] = !empty($html['required']) ? '1' : '';
        } elseif ($this->type === self::TYPE_GRID_SINGLE || $this->type === self::TYPE_GRID_MULTI) {
            $grid = $this->getGridConfig();
            $row['grid_rows'] = implode("\n", array_map(static function ($p) {
                return $p['code'] !== '' ? ($p['code'] . ' | ' . $p['label']) : $p['label'];
            }, $grid['rows']));
            $row['grid_columns'] = implode("\n", array_map(static function ($p) {
                return $p['code'] !== '' ? ($p['code'] . ' | ' . $p['label']) : $p['label'];
            }, $grid['columns']));
            $row['grid_row_items'] = $grid['rows'];
            $row['grid_column_items'] = $grid['columns'];
            $row['grid_mobile_layout'] = $grid['mobile_layout'];
        } elseif ($this->type === self::TYPE_BEST_WORST || $this->type === self::TYPE_MAXDIFF) {
            $items = $this->getItemsConfig();
            $row['items'] = implode("\n", $items['items']);
            $row['maxdiff_set_size'] = $items['setSize'];
            $row['maxdiff_set_count'] = $items['setCount'];
            $row['maxdiff_versions'] = $items['versions'];
        } elseif ($this->type === self::TYPE_DRILLDOWN) {
            $row['drilldown_tree'] = $this->getDrilldownTreeAsText();
        } elseif ($this->type === self::TYPE_IMAGE_AREA) {
            $img = $this->getImageAreaConfig();
            $row['image_url'] = $img['imageUrl'];
            $row['image_guid'] = $img['imageGuid'];
            $row['image_mode'] = $img['mode'];
            $row['image_multi'] = !empty($img['multi']) ? '1' : '';
            $row['image_regions'] = json_encode($img['regions'], JSON_UNESCAPED_UNICODE);
        } elseif ($this->type === self::TYPE_MAP) {
            $map = $this->getMapConfig();
            $row['map_lat'] = $map['lat'];
            $row['map_lng'] = $map['lng'];
            $row['map_zoom'] = $map['zoom'];
            $row['map_types'] = implode(',', $map['allowedTypes']);
            $row['map_max'] = $map['maxFeatures'];
            $row['map_style'] = $map['style'] ?? '';
        }

        return $row;
    }

    public function toExportArray(): array
    {
        $row = $this->toPostRow();
        unset($row['condition_field'], $row['condition_operator'], $row['condition_value']);
        $row['type'] = $this->type;
        $row['logic'] = $this->getLogic();
        return $row;
    }

    /**
     * Settings that structure a form: loops, group and block randomisation, consent options.
     * Import and library insert carry them through unchanged (V3-46).
     */
    public const STRUCTURE_KEYS = [
        'block_key', 'randomise_enabled', 'randomise_method', 'randomise_show', 'randomise_pin_first', 'randomise_pin_last',
        'loop_enabled', 'loop_source', 'loop_field_key', 'loop_label_field', 'loop_max', 'loop_min',
        'loop_items', 'loop_randomise', 'loop_show',
        'consent_must_read', 'consent_signature', 'consent_witness',
        'straightline_exempt', 'reverse_keyed', 'reverse_rows',
    ];

    /**
     * Normalize an export or post row into saveFieldsFromPost shape.
     */
    public static function exportToPostRow(array $payload): ?array
    {
        $type = (string)($payload['type'] ?? '');
        if ($type === '' || !isset(self::getTypeLabels()[$type])) {
            return null;
        }
        $label = trim((string)($payload['label'] ?? ''));
        if ($label === '') {
            $label = self::defaultLabelForType($type);
        }

        $options = $payload['options'] ?? '';
        if (is_array($options)) {
            $options = implode("\n", $options);
        }

        $row = [
            'type' => $type,
            'label' => $label,
            'variable' => trim((string)($payload['variable'] ?? '')),
            'internal_label' => trim((string)($payload['internal_label'] ?? '')),
            'help_text' => (string)($payload['help_text'] ?? ''),
            'required' => !empty($payload['required']) ? '1' : '',
            'options' => (string)$options,
            'option_items' => is_array($payload['option_items'] ?? null) ? $payload['option_items'] : [],
            'randomize' => !empty($payload['randomize']) ? '1' : '',
            'max_select' => $payload['max_select'] ?? ($payload['maxSelect'] ?? ''),
            'min_select' => $payload['min_select'] ?? ($payload['minSelect'] ?? ''),
            'min_select_all' => !empty($payload['min_select_all']) || !empty($payload['minSelectAll']) ? '1' : '',
            'exclusive_option' => (string)($payload['exclusive_option'] ?? $payload['exclusiveOption'] ?? ''),
            'other_specify' => array_key_exists('other_specify', $payload) || array_key_exists('otherSpecify', $payload)
                ? $payload['other_specify'] ?? $payload['otherSpecify']
                : '1',
            'other_specify_required' => array_key_exists('other_specify_required', $payload) || array_key_exists('otherSpecifyRequired', $payload)
                ? ($payload['other_specify_required'] ?? $payload['otherSpecifyRequired'])
                : '1',
            'formula' => (string)($payload['formula'] ?? ''),
            'formula_result' => (string)($payload['formula_result'] ?? 'number'),
            'formula_display' => (string)($payload['formula_display'] ?? 'readonly'),
            'formula_places' => $payload['formula_places'] ?? 6,
            'number_min' => $payload['number_min'] ?? $payload['min'] ?? '',
            'number_max' => $payload['number_max'] ?? $payload['max'] ?? '',
            'prefill_profile' => (string)($payload['prefill_profile'] ?? $payload['prefillProfile'] ?? ''),
            'hidden' => !empty($payload['hidden']) ? '1' : '',
            'default_value' => (string)($payload['default_value'] ?? $payload['defaultValue'] ?? ''),
            'meta_key' => (string)($payload['meta_key'] ?? $payload['metaKey'] ?? ''),
            'rating_min' => $payload['rating_min'] ?? 1,
            'rating_max' => $payload['rating_max'] ?? 5,
            'rating_step' => $payload['rating_step'] ?? 1,
            'rating_low_label' => $payload['rating_low_label'] ?? '',
            'rating_high_label' => $payload['rating_high_label'] ?? '',
            'rating_display' => $payload['rating_display'] ?? self::RATING_DISPLAY_PILLS,
            'page_key' => $payload['page_key'] ?? '',
            'page_title' => $payload['page_title'] ?? '',
            'branches' => is_array($payload['branches'] ?? null) ? $payload['branches'] : [],
            'page_otherwise' => (string)($payload['page_otherwise'] ?? ''),
            'validation' => is_array($payload['validation'] ?? null) ? $payload['validation'] : [],
            'rich_content' => $payload['rich_content'] ?? '',
            'html_content' => $payload['html_content'] ?? '',
            'html_collect' => !empty($payload['html_collect']) ? '1' : '',
            'html_variable' => $payload['html_variable'] ?? 'value',
            'html_instructions' => $payload['html_instructions'] ?? '',
            'html_required' => !empty($payload['html_required']) ? '1' : '',
            'actions' => is_array($payload['actions'] ?? null) ? $payload['actions'] : [],
            'grid_rows' => is_array($payload['grid_rows'] ?? null) ? implode("\n", $payload['grid_rows']) : (string)($payload['grid_rows'] ?? ''),
            'grid_columns' => is_array($payload['grid_columns'] ?? null) ? implode("\n", $payload['grid_columns']) : (string)($payload['grid_columns'] ?? ''),
            'grid_row_items' => is_array($payload['grid_row_items'] ?? null) ? $payload['grid_row_items'] : [],
            'grid_column_items' => is_array($payload['grid_column_items'] ?? null) ? $payload['grid_column_items'] : [],
            'grid_mobile_layout' => (($payload['grid_mobile_layout'] ?? '') === 'stack') ? 'stack' : 'scroll',
            'items' => is_array($payload['items'] ?? null) ? implode("\n", $payload['items']) : (string)($payload['items'] ?? $options),
            'maxdiff_set_size' => $payload['maxdiff_set_size'] ?? ($payload['setSize'] ?? 4),
            'maxdiff_set_count' => $payload['maxdiff_set_count'] ?? ($payload['setCount'] ?? ''),
            'maxdiff_versions' => $payload['maxdiff_versions'] ?? ($payload['versions'] ?? null),
            'drilldown_tree' => is_array($payload['drilldown_tree'] ?? null)
                ? json_encode($payload['drilldown_tree'])
                : (string)($payload['drilldown_tree'] ?? ''),
            'image_url' => (string)($payload['image_url'] ?? ''),
            'image_guid' => (string)($payload['image_guid'] ?? ''),
            'image_mode' => (string)($payload['image_mode'] ?? 'select'),
            'image_multi' => !empty($payload['image_multi']) ? '1' : '',
            'image_regions' => is_array($payload['image_regions'] ?? null)
                ? json_encode($payload['image_regions'], JSON_UNESCAPED_UNICODE)
                : (string)($payload['image_regions'] ?? ''),
            'map_lat' => $payload['map_lat'] ?? '',
            'map_lng' => $payload['map_lng'] ?? '',
            'map_zoom' => $payload['map_zoom'] ?? '',
            'map_types' => is_array($payload['map_types'] ?? null)
                ? implode(',', $payload['map_types'])
                : (string)($payload['map_types'] ?? ''),
            'map_max' => $payload['map_max'] ?? '',
            'map_style' => $payload['map_style'] ?? '',
            'logic_action' => $payload['logic_action'] ?? ($payload['logic']['action'] ?? 'show'),
            'logic_combinator' => $payload['logic_combinator'] ?? ($payload['logic']['combinator'] ?? 'and'),
            'logic_goto' => $payload['logic_goto'] ?? ($payload['logic']['gotoPageKey'] ?? ''),
            'logic_rules' => is_array($payload['logic_rules'] ?? null)
                ? $payload['logic_rules']
                : (is_array($payload['logic']['rules'] ?? null) ? $payload['logic']['rules'] : []),
            'carry_from' => (string)($payload['carry_from'] ?? ''),
            'carry_mode' => (string)($payload['carry_mode'] ?? self::CARRY_SELECTED),
            'logic_formula' => (string)($payload['logic_formula'] ?? ($payload['logic']['text'] ?? '')),
        ];

        if (array_key_exists('pii', $payload)) {
            $row['pii'] = !empty($payload['pii']) ? '1' : '';
        }
        foreach (self::STRUCTURE_KEYS as $key) {
            if (array_key_exists($key, $payload) && is_scalar($payload[$key])) {
                $row[$key] = (string)$payload[$key];
            }
        }
        // A consent document id belongs to the source form; 0 uses this form's latest published sheet.
        if ($type === self::TYPE_CONSENT) {
            $row['consent_document_id'] = 0;
        }

        return $row;
    }

    public static function fromPostRow(array $row): self
    {
        $field = new self();
        $field->type = (string)($row['type'] ?? self::TYPE_TEXT);
        $field->label = (string)($row['label'] ?? '');
        $field->variable = trim((string)($row['variable'] ?? ''));
        $field->internal_label = trim((string)($row['internal_label'] ?? ''));
        $field->help_text = $row['help_text'] ?? null;
        $field->required = !empty($row['required']);
        if ($field->type === self::TYPE_RATING) {
            $field->setRatingScale([
                'min' => $row['rating_min'] ?? 1,
                'max' => $row['rating_max'] ?? 5,
                'step' => $row['rating_step'] ?? 1,
                'lowLabel' => $row['rating_low_label'] ?? '',
                'highLabel' => $row['rating_high_label'] ?? '',
                'display' => $row['rating_display'] ?? self::RATING_DISPLAY_PILLS,
            ]);
        } elseif ($field->type === self::TYPE_PAGE_BREAK) {
            $field->setPageBreakConfig([
                'pageKey' => $row['page_key'] ?? '',
                'title' => $row['page_title'] ?? '',
                'branches' => is_array($row['branches'] ?? null) ? $row['branches'] : [],
                'otherwise' => (string)($row['page_otherwise'] ?? ''),
            ]);
        } elseif ($field->type === self::TYPE_RICH_TEXT) {
            $field->setRichTextContent((string)($row['rich_content'] ?? ''));
        } elseif ($field->type === self::TYPE_HTML) {
            $field->setHtmlConfig([
                'html' => (string)($row['html_content'] ?? ''),
                'collect' => !empty($row['html_collect']),
                'variable' => (string)($row['html_variable'] ?? 'value'),
                'instructions' => (string)($row['html_instructions'] ?? ''),
                'required' => !empty($row['html_required']),
            ]);
        } elseif ($field->type === self::TYPE_GRID_SINGLE || $field->type === self::TYPE_GRID_MULTI) {
            $field->setGridConfig([
                'rows' => $row['grid_row_items'] ?? ($row['grid_rows'] ?? ''),
                'columns' => $row['grid_column_items'] ?? ($row['grid_columns'] ?? ''),
                'mobile_layout' => $row['grid_mobile_layout'] ?? 'scroll',
            ]);
        } elseif ($field->type === self::TYPE_BEST_WORST || $field->type === self::TYPE_MAXDIFF) {
            $field->setItemsConfig([
                'items' => $row['items'] ?? ($row['options'] ?? ''),
                'setSize' => $row['maxdiff_set_size'] ?? 4,
                'setCount' => $row['maxdiff_set_count'] ?? 0,
                'versions' => $row['maxdiff_versions'] ?? null,
            ]);
        } elseif ($field->type === self::TYPE_DRILLDOWN) {
            $field->setDrilldownTree($row['drilldown_tree'] ?? '');
        } elseif ($field->type === self::TYPE_IMAGE_AREA) {
            $regions = $row['image_regions'] ?? [];
            if (is_string($regions)) {
                $decoded = json_decode($regions, true);
                $regions = is_array($decoded) ? $decoded : [];
            }
            $field->setImageAreaConfig([
                'imageUrl' => $row['image_url'] ?? '',
                'imageGuid' => $row['image_guid'] ?? '',
                'mode' => $row['image_mode'] ?? 'select',
                'multi' => !empty($row['image_multi']),
                'regions' => $regions,
            ]);
        } elseif ($field->type === self::TYPE_MAP) {
            $types = $row['map_types'] ?? ['Point'];
            if (is_string($types)) {
                $types = array_filter(array_map('trim', explode(',', $types)));
            }
            $field->setMapConfig([
                'lat' => $row['map_lat'] ?? 52.4862,
                'lng' => $row['map_lng'] ?? -1.8904,
                'zoom' => $row['map_zoom'] ?? 7,
                'allowedTypes' => $types,
                'maxFeatures' => $row['map_max'] ?? 1,
                'style' => $row['map_style'] ?? '',
            ]);
        } elseif (self::isChoiceType($field->type)) {
            $maxSelect = null;
            if (($row['max_select'] ?? '') !== '' && $row['max_select'] !== null) {
                $maxSelect = (int)$row['max_select'];
            }
            $minSelect = null;
            if (array_key_exists('min_select', $row) || array_key_exists('minSelect', $row)) {
                $rawMin = $row['min_select'] ?? $row['minSelect'] ?? '';
                $minSelect = ($rawMin === '' || $rawMin === null) ? 0 : (int)$rawMin;
            }
            $minSelectAll = null;
            if (array_key_exists('min_select_all', $row) || array_key_exists('minSelectAll', $row)) {
                $minSelectAll = !empty($row['min_select_all']) || !empty($row['minSelectAll']);
            }
            $field->setOptionsFromText(
                !empty($row['option_items']) && is_array($row['option_items'])
                    ? $row['option_items']
                    : ($row['options'] ?? ''),
                !empty($row['randomize']),
                $maxSelect,
                (string)($row['exclusive_option'] ?? ''),
                $minSelect,
                $minSelectAll
            );
            if (array_key_exists('other_specify', $row) || array_key_exists('otherSpecify', $row)) {
                $rawOther = $row['other_specify'] ?? $row['otherSpecify'];
                $field->setAllowsOtherSpecify(!in_array($rawOther, [0, '0', false, 'false', ''], true));
            }
            if (array_key_exists('other_specify_required', $row) || array_key_exists('otherSpecifyRequired', $row)) {
                $rawRequired = $row['other_specify_required'] ?? $row['otherSpecifyRequired'];
                $field->setRequiresOtherText(!in_array($rawRequired, [0, '0', false, 'false', ''], true));
            }
            $field->setCarryForward((string)($row['carry_from'] ?? ''), (string)($row['carry_mode'] ?? self::CARRY_SELECTED));
        } elseif ($field->type === self::TYPE_NUMBER) {
            $field->setNumberRange($row['number_min'] ?? null, $row['number_max'] ?? null);
        } elseif ($field->type === self::TYPE_FILE) {
            $field->setFileRules($row['file_types'] ?? '', $row['file_max_mb'] ?? null);
        } elseif ($field->type === self::TYPE_CALCULATED) {
            $field->setFormulaConfig(
                (string)($row['formula'] ?? ''),
                (string)($row['formula_result'] ?? 'number'),
                (string)($row['formula_display'] ?? 'readonly'),
                $row['formula_places'] ?? 6
            );
        }
        if ($field->type === self::TYPE_QUESTION_GROUP && (!empty($row['randomise_enabled']) || !empty($row['loop_enabled']))) {
            $options = [];
            if (!empty($row['randomise_enabled'])) {
                $show = trim((string)($row['randomise_show'] ?? ''));
                $options['randomise'] = [
                    'enabled' => true,
                    'method' => (($row['randomise_method'] ?? '') === 'rotate') ? 'rotate' : 'shuffle',
                    'show' => $show === '' ? null : (int)$show,
                    'pinFirst' => array_values(array_filter(array_map('trim', explode(',', (string)($row['randomise_pin_first'] ?? ''))))),
                    'pinLast' => array_values(array_filter(array_map('trim', explode(',', (string)($row['randomise_pin_last'] ?? ''))))),
                ];
            }
            if (!empty($row['loop_enabled'])) {
                $items = [];
                $lines = preg_split('/\r\n|\r|\n/', (string)($row['loop_items'] ?? '')) ?: [];
                foreach ($lines as $line) {
                    $line = trim((string)$line);
                    if ($line === '') {
                        continue;
                    }
                    $parts = array_map('trim', explode('|', $line, 2));
                    if ($parts[0] === '') {
                        continue;
                    }
                    $items[] = ['code' => $parts[0], 'label' => $parts[1] ?? $parts[0]];
                }
                $source = (string)($row['loop_source'] ?? 'fixed');
                if (!in_array($source, ['fixed', 'choices', 'number', 'roster'], true)) {
                    $source = 'fixed';
                }
                $options['loop'] = [
                    'source' => $source,
                    'field_key' => trim((string)($row['loop_field_key'] ?? '')),
                    'label_field' => trim((string)($row['loop_label_field'] ?? '')),
                    'max' => max(0, (int)($row['loop_max'] ?? 0)),
                    'min' => max(0, (int)($row['loop_min'] ?? 0)),
                    'items' => $items,
                    'randomise' => !empty($row['loop_randomise']),
                    'show' => trim((string)($row['loop_show'] ?? '')) === '' ? null : max(0, (int)$row['loop_show']),
                ];
            }
            $field->options_json = json_encode($options, JSON_UNESCAPED_UNICODE);
        }
        $field->setActions($row['actions'] ?? []);
        $field->setValidation($row['validation'] ?? []);
        $role = trim((string)($row['instrument_role'] ?? ''));
        if ($role !== '') {
            $field->setInstrumentRole($role);
        }
        $field->setHiddenFromRespondent(!empty($row['hidden']) || $field->type === self::TYPE_RESPONDENT_META);
        $field->setDefaultValue((string)($row['default_value'] ?? ''));
        if ($field->type === self::TYPE_RESPONDENT_META) {
            $field->setRespondentMetaKey((string)($row['meta_key'] ?? ''));
        }
        if ($field->type === self::TYPE_PANEL_ATTR) {
            $field->setPanelAttrKey((string)($row['panel_key'] ?? ''));
        }
        if ($field->collectsAnswer()) {
            $field->setContainsPii(array_key_exists('pii', $row) ? !empty($row['pii']) : $field->defaultContainsPii());
        }
        return $field;
    }

    public function inputName(): string
    {
        return 'field_' . $this->id;
    }

    public static function pathExistsInTree(array $tree, array $path): bool
    {
        $path = array_values(array_map('strval', $path));
        if (!$path) {
            return false;
        }
        $nodes = $tree;
        foreach ($path as $i => $label) {
            $found = null;
            foreach ($nodes as $node) {
                if (!is_array($node)) {
                    continue;
                }
                if ((string)($node['label'] ?? '') === $label) {
                    $found = $node;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
            if ($i === count($path) - 1) {
                return true;
            }
            $nodes = is_array($found['children'] ?? null) ? $found['children'] : [];
        }
        return false;
    }

    private function decodedOptions(): array
    {
        $decoded = json_decode((string)$this->options_json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeDecodedOptions(array $decoded): void
    {
        if (!$decoded) {
            $this->options_json = null;
            return;
        }
        $this->options_json = json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param mixed $source
     * @return string[]
     */
    private function linesToList($source): array
    {
        if (is_array($source)) {
            $lines = $source;
        } else {
            $lines = preg_split('/\r\n|\r|\n/', (string)$source) ?: [];
        }
        $out = [];
        foreach ($lines as $line) {
            if (is_array($line)) {
                $line = (string)($line['label'] ?? reset($line) ?: '');
            }
            $line = trim((string)$line);
            if ($line !== '') {
                $out[] = $line;
            }
        }
        return array_values($out);
    }

    private function parseTreeText(string $text): array
    {
        $items = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }
            preg_match('/^(\s*)(.*)$/', $line, $m);
            $label = trim($m[2] ?? '');
            if ($label === '') {
                continue;
            }
            $items[] = [
                'indent' => strlen(str_replace("\t", '  ', $m[1] ?? '')),
                'label' => $label,
            ];
        }
        $i = 0;
        return $this->buildTreeLevel($items, $i, $items[0]['indent'] ?? 0);
    }

    private function buildTreeLevel(array $items, int &$i, int $minIndent): array
    {
        $nodes = [];
        while ($i < count($items)) {
            $item = $items[$i];
            if ($item['indent'] < $minIndent) {
                break;
            }
            if ($item['indent'] > $minIndent && $nodes) {
                break;
            }
            $i++;
            $children = [];
            if ($i < count($items) && $items[$i]['indent'] > $item['indent']) {
                $children = $this->buildTreeLevel($items, $i, $items[$i]['indent']);
            }
            $nodes[] = ['label' => $item['label'], 'children' => $children];
        }
        return $nodes;
    }

    private function treeToText(array $tree, int $depth = 0): string
    {
        $lines = [];
        foreach ($tree as $node) {
            if (!is_array($node)) {
                continue;
            }
            $label = trim((string)($node['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $lines[] = str_repeat('  ', $depth) . $label;
            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            if ($children) {
                $childText = $this->treeToText($children, $depth + 1);
                if ($childText !== '') {
                    $lines[] = $childText;
                }
            }
        }
        return implode("\n", $lines);
    }

    private function clampPercent($value, int $min = 0): float
    {
        $n = (float)$value;
        if ($n < $min) {
            $n = $min;
        }
        if ($n > 100) {
            $n = 100;
        }
        return round($n, 2);
    }

    private function isSequentialArray(array $arr): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($arr);
        }

        $i = 0;
        foreach ($arr as $k => $_) {
            if ($k !== $i++) {
                return false;
            }
        }

        return true;
    }
}
