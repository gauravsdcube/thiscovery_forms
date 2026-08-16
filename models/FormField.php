<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use humhub\modules\file\models\File;
use humhub\modules\thiscoveryForms\services\LogicEngine;
use humhub\modules\thiscoveryForms\services\MaxDiffDesigner;
use Yii;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $form_id
 * @property string $type
 * @property string $label
 * @property string|null $help_text
 * @property int $required
 * @property int $sort_order
 * @property string|null $options_json
 * @property int|null $condition_field_id
 * @property string|null $condition_operator
 * @property string|null $condition_value
 * @property string|null $logic_json
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
    public const TYPE_RANKING = 'ranking';
    public const TYPE_FILE = 'file';
    public const TYPE_PAGE_BREAK = 'page_break';
    public const TYPE_RICH_TEXT = 'rich_text';
    public const TYPE_HTML = 'html';
    public const TYPE_GRID_SINGLE = 'grid_single';
    public const TYPE_GRID_MULTI = 'grid_multi';
    public const TYPE_BEST_WORST = 'best_worst';
    public const TYPE_MAXDIFF = 'maxdiff';
    public const TYPE_DRILLDOWN = 'drilldown';
    public const TYPE_IMAGE_AREA = 'image_area';

    public const CARRY_SELECTED = 'selected';
    public const CARRY_UNSELECTED = 'unselected';
    public const CARRY_ALL = 'all';

    public const JUSTIFY_NONE = '';
    public const JUSTIFY_OPTIONAL = 'optional';
    public const JUSTIFY_REQUIRED = 'required';

    public const OP_EQUALS = 'equals';
    public const OP_NOT_EQUALS = 'not_equals';
    public const OP_CONTAINS = 'contains';
    public const OP_CHECKED = 'checked';

    public static function tableName()
    {
        return 'custom_form_field';
    }

    public function rules()
    {
        return [
            [['form_id', 'type', 'label'], 'required'],
            [['form_id', 'sort_order', 'condition_field_id'], 'integer'],
            [['required'], 'boolean'],
            [['label'], 'string', 'max' => 255],
            [['help_text', 'condition_value'], 'string', 'max' => 500],
            [['options_json', 'logic_json'], 'string'],
            [['type'], 'in', 'range' => array_keys(self::getTypeLabels())],
            [['condition_operator'], 'in', 'range' => array_keys(self::getOperatorLabels()), 'skipOnEmpty' => true],
        ];
    }

    public function attributeLabels()
    {
        return [
            'type' => Yii::t('ThiscoveryFormsModule.base', 'Type'),
            'label' => Yii::t('ThiscoveryFormsModule.base', 'Label'),
            'help_text' => Yii::t('ThiscoveryFormsModule.base', 'Help text'),
            'required' => Yii::t('ThiscoveryFormsModule.base', 'Required'),
            'options_json' => Yii::t('ThiscoveryFormsModule.base', 'Options'),
        ];
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
            self::TYPE_RICH_TEXT => Yii::t('ThiscoveryFormsModule.base', 'Rich text section'),
            self::TYPE_HTML => Yii::t('ThiscoveryFormsModule.base', 'HTML / custom block'),
            self::TYPE_GRID_SINGLE => Yii::t('ThiscoveryFormsModule.base', 'Grid (single)'),
            self::TYPE_GRID_MULTI => Yii::t('ThiscoveryFormsModule.base', 'Grid (multi)'),
            self::TYPE_BEST_WORST => Yii::t('ThiscoveryFormsModule.base', 'Best–worst'),
            self::TYPE_MAXDIFF => Yii::t('ThiscoveryFormsModule.base', 'MaxDiff'),
            self::TYPE_DRILLDOWN => Yii::t('ThiscoveryFormsModule.base', 'Drill-down'),
            self::TYPE_IMAGE_AREA => Yii::t('ThiscoveryFormsModule.base', 'Image area'),
        ];
    }

    public static function getOperatorLabels(): array
    {
        return [
            self::OP_EQUALS => Yii::t('ThiscoveryFormsModule.base', 'Equals'),
            self::OP_NOT_EQUALS => Yii::t('ThiscoveryFormsModule.base', 'Does not equal'),
            self::OP_CONTAINS => Yii::t('ThiscoveryFormsModule.base', 'Contains'),
            self::OP_CHECKED => Yii::t('ThiscoveryFormsModule.base', 'Is checked / selected'),
        ];
    }

    public static function defaultLabelForType(string $type): string
    {
        $labels = self::getTypeLabels();
        return $labels[$type] ?? $type;
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

    public function getConditionField(): ActiveQuery
    {
        return $this->hasOne(self::class, ['id' => 'condition_field_id']);
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
            self::TYPE_RANKING,
            self::TYPE_CHECKBOX,
        ], true);
    }

    public static function isStructuralType(?string $type): bool
    {
        return in_array($type, [self::TYPE_PAGE_BREAK, self::TYPE_RICH_TEXT], true);
    }

    public static function isDisplayOnlyType(?string $type): bool
    {
        return in_array($type, [self::TYPE_PAGE_BREAK, self::TYPE_RICH_TEXT], true);
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
        if (self::isStructuralType($this->type) || $this->type === self::TYPE_PAGE_BREAK) {
            return false;
        }
        if ($this->type === self::TYPE_HTML) {
            return (bool)$this->getHtmlConfig()['collect'];
        }
        if ($this->type === self::TYPE_RICH_TEXT) {
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
        if (!$this->options_json) {
            return [];
        }

        $decoded = json_decode($this->options_json, true);
        if (!is_array($decoded)) {
            return [];
        }

        if (isset($decoded['__type'])) {
            if (isset($decoded['options']) && is_array($decoded['options'])) {
                return array_values(array_filter(array_map('strval', $decoded['options']), 'strlen'));
            }
            if (isset($decoded['items']) && is_array($decoded['items'])) {
                return array_values(array_filter(array_map('strval', $decoded['items']), 'strlen'));
            }
            return [];
        }

        if (isset($decoded['options']) && is_array($decoded['options'])) {
            return array_values(array_filter(array_map('strval', $decoded['options']), 'strlen'));
        }

        if (!$this->isSequentialArray($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $decoded), 'strlen'));
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
        $normalized = strtolower($option);
        if ($normalized === 'other') {
            return true;
        }

        return (bool)preg_match('/^other\b/i', $option);
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
        foreach ($options ?? $this->getOptions() as $opt) {
            if (self::isOtherOption((string)$opt)) {
                return (string)$opt;
            }
        }
        return null;
    }

    /**
     * @return array{selected: bool, text: string}
     */
    public static function otherSpecifyState(string $otherLabel, $value): array
    {
        $prefix = self::otherSpecifyPrefix($otherLabel);
        $items = is_array($value) ? $value : [$value];
        $selected = false;
        $text = '';
        foreach ($items as $item) {
            $item = (string)$item;
            if ($item === $otherLabel) {
                $selected = true;
            } elseif (str_starts_with($item, $prefix)) {
                $selected = true;
                $text = substr($item, strlen($prefix));
            }
        }
        return ['selected' => $selected, 'text' => $text];
    }

    public function allowsChoiceValue(string $item): bool
    {
        foreach ($this->getOptions() as $opt) {
            if ($item === $opt) {
                return true;
            }
            if (self::isOtherOption($opt)) {
                $prefix = self::otherSpecifyPrefix($opt);
                if (str_starts_with($item, $prefix) && strlen($item) > strlen($prefix)) {
                    return true;
                }
            }
        }
        if (self::isOtherOption($item)) {
            return true;
        }
        if (preg_match('/^(other\b[^:]*):\s+\S/i', $item)) {
            return true;
        }

        return false;
    }

    public function otherSpecifyIncomplete($value): bool
    {
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

    public function setOptionsFromText($text, bool $randomize = false, ?int $maxSelect = null, ?string $exclusiveOption = null): void
    {
        $lines = preg_split('/\r\n|\r|\n/', (string)$text) ?: [];
        $options = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $options[] = $line;
            }
        }

        $prev = json_decode((string)$this->options_json, true);
        if (!is_array($prev)) {
            $prev = [];
        }

        if ($maxSelect === null && array_key_exists('maxSelect', $prev)) {
            $maxSelect = (int)$prev['maxSelect'];
        }
        if ($exclusiveOption === null && !empty($prev['exclusiveOption'])) {
            $exclusiveOption = (string)$prev['exclusiveOption'];
        }

        $maxSelect = ($maxSelect !== null && $maxSelect > 0) ? $maxSelect : null;
        $exclusiveOption = trim((string)$exclusiveOption);
        $exclusiveOption = $exclusiveOption !== '' ? $exclusiveOption : null;

        $carryFrom = trim((string)($prev['carryFrom'] ?? ''));
        $carryMode = (string)($prev['carryMode'] ?? '');
        if (!in_array($carryMode, [self::CARRY_SELECTED, self::CARRY_UNSELECTED, self::CARRY_ALL], true)) {
            $carryMode = '';
        }
        $justification = (string)($prev['justification'] ?? '');
        if (!in_array($justification, [self::JUSTIFY_OPTIONAL, self::JUSTIFY_REQUIRED], true)) {
            $justification = '';
        }

        if (!$options && !$randomize && $maxSelect === null && $exclusiveOption === null && $carryFrom === '' && $justification === '') {
            $this->options_json = null;
            return;
        }

        if ($randomize || $maxSelect !== null || $exclusiveOption !== null || $carryFrom !== '' || $justification !== '') {
            $payload = ['options' => $options];
            if ($randomize) {
                $payload['randomize'] = true;
            }
            if ($maxSelect !== null) {
                $payload['maxSelect'] = $maxSelect;
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
            $this->options_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        } else {
            $this->options_json = json_encode($options, JSON_UNESCAPED_UNICODE);
        }
    }

    public function getOptionsAsText(): string
    {
        return implode("\n", $this->getOptions());
    }

    /**
     * Deterministic shuffle for a given user so reopen/edit keeps the same order.
     */
    public function getShuffledOptions(?int $userId = null): array
    {
        $options = $this->getOptions();
        $pinned = [];
        $rest = [];
        foreach ($options as $opt) {
            if (self::isOtherOption((string)$opt)) {
                $pinned[] = $opt;
            } else {
                $rest[] = $opt;
            }
        }
        if (!$this->isRandomizeOptions() || count($rest) < 2) {
            return $options;
        }

        $userId = $userId ?? (int)(Yii::$app->user->id ?? 0);
        $seed = crc32($userId . ':' . (int)$this->id . ':' . (int)$this->form_id);
        $order = range(0, count($rest) - 1);

        $n = count($order);
        for ($i = $n - 1; $i > 0; $i--) {
            $seed = ($seed * 1664525 + 1013904223) & 0x7fffffff;
            $j = $seed % ($i + 1);
            $tmp = $order[$i];
            $order[$i] = $order[$j];
            $order[$j] = $tmp;
        }

        $shuffled = [];
        foreach ($order as $idx) {
            $shuffled[] = $rest[$idx];
        }
        return array_merge($shuffled, $pinned);
    }

    public function setRatingScale(array $config): void
    {
        $min = max(0, (int)($config['min'] ?? 1));
        $max = max($min + 1, (int)($config['max'] ?? 5));
        $step = max(1, (int)($config['step'] ?? 1));
        $lowLabel = trim((string)($config['lowLabel'] ?? ''));
        $highLabel = trim((string)($config['highLabel'] ?? ''));

        $this->options_json = json_encode([
            '__type' => self::TYPE_RATING,
            'min' => $min,
            'max' => $max,
            'step' => $step,
            'lowLabel' => $lowLabel,
            'highLabel' => $highLabel,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function getRatingScale(): array
    {
        $defaults = [
            'min' => 1,
            'max' => 5,
            'step' => 1,
            'lowLabel' => '',
            'highLabel' => '',
        ];

        if (!$this->options_json) {
            return $defaults;
        }

        $decoded = json_decode($this->options_json, true);
        if (!is_array($decoded) || (($decoded['__type'] ?? null) !== self::TYPE_RATING)) {
            return $defaults;
        }

        return [
            'min' => max(0, (int)($decoded['min'] ?? $defaults['min'])),
            'max' => max(1, (int)($decoded['max'] ?? $defaults['max'])),
            'step' => max(1, (int)($decoded['step'] ?? $defaults['step'])),
            'lowLabel' => (string)($decoded['lowLabel'] ?? ''),
            'highLabel' => (string)($decoded['highLabel'] ?? ''),
        ];
    }

    public function setPageBreakConfig(array $config): void
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
            $fieldKey = trim((string)($branch['fieldKey'] ?? ''));
            $goto = trim((string)($branch['gotoPageKey'] ?? ''));
            if ($fieldKey === '' || $goto === '') {
                continue;
            }
            $branches[] = [
                'fieldKey' => $fieldKey,
                'operator' => (string)($branch['operator'] ?? self::OP_EQUALS),
                'value' => (string)($branch['value'] ?? ''),
                'gotoPageKey' => $goto,
            ];
        }

        $this->options_json = json_encode([
            '__type' => self::TYPE_PAGE_BREAK,
            'pageKey' => $pageKey,
            'title' => trim((string)($config['title'] ?? '')),
            'branches' => $branches,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function getPageBreakConfig(): array
    {
        $defaults = [
            'pageKey' => 'p' . (string)($this->id ?: 'new'),
            'title' => '',
            'branches' => [],
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
            $options = array_values(array_filter($sourceOpts, static fn($o) => !self::selectedIncludesOption($selected, (string)$o)));
        } else {
            $options = array_values(array_filter($sourceOpts, static fn($o) => self::selectedIncludesOption($selected, (string)$o)));
        }
        return $options;
    }

    public function setGridConfig(array $config): void
    {
        $rows = $this->linesToList($config['rows'] ?? []);
        $columns = $this->linesToList($config['columns'] ?? []);
        $this->options_json = json_encode([
            '__type' => $this->type === self::TYPE_GRID_MULTI ? self::TYPE_GRID_MULTI : self::TYPE_GRID_SINGLE,
            'rows' => $rows,
            'columns' => $columns,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function getGridConfig(): array
    {
        $decoded = $this->decodedOptions();
        return [
            'rows' => $this->linesToList($decoded['rows'] ?? []),
            'columns' => $this->linesToList($decoded['columns'] ?? []),
        ];
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
            $sets = $config['sets'] ?? [];
            if (!is_array($sets) || !$sets) {
                $sets = (new MaxDiffDesigner())->generateSets($items, $setSize, $setCount);
            }
            $payload['setSize'] = $setSize;
            $payload['setCount'] = $setCount;
            $payload['sets'] = $sets;
        }
        $this->options_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    public function getItemsConfig(): array
    {
        $decoded = $this->decodedOptions();
        $items = $this->linesToList($decoded['items'] ?? ($decoded['options'] ?? []));
        $setSize = max(2, (int)($decoded['setSize'] ?? 4));
        $setCount = max(1, (int)($decoded['setCount'] ?? max(1, count($items) ?: 1)));
        $sets = is_array($decoded['sets'] ?? null) ? $decoded['sets'] : [];
        return [
            'items' => $items,
            'setSize' => $setSize,
            'setCount' => $setCount,
            'sets' => $sets,
        ];
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

    public function getLogic(): array
    {
        $decoded = json_decode((string)$this->logic_json, true);
        if (is_array($decoded) && !empty($decoded['rules'])) {
            return LogicEngine::normalize($decoded);
        }
        if ($this->condition_field_id) {
            return LogicEngine::fromLegacy(
                (int)$this->condition_field_id,
                $this->condition_operator,
                $this->condition_value
            );
        }
        return LogicEngine::defaultLogic();
    }

    public function setLogic(array $logic): void
    {
        $logic = LogicEngine::normalize($logic);
        if (empty($logic['rules'])) {
            $this->logic_json = null;
            $this->condition_field_id = null;
            $this->condition_operator = null;
            $this->condition_value = null;
            return;
        }
        $this->logic_json = json_encode($logic, JSON_UNESCAPED_UNICODE);
        $first = $logic['rules'][0];
        $this->condition_field_id = ctype_digit((string)$first['fieldKey']) ? (int)$first['fieldKey'] : null;
        $this->condition_operator = $first['operator'];
        $this->condition_value = $first['value'];
    }

    public function hasCondition(): bool
    {
        $logic = $this->getLogic();
        return !empty($logic['rules']);
    }

    /**
     * Whether this field should be shown and validated given current answers.
     * @param array $values fieldId => value
     */
    public function isConditionMet(array $values): bool
    {
        return $this->isVisible($values);
    }

    public function isVisible(array $values): bool
    {
        return (new LogicEngine())->isVisible($this, $values);
    }

    /**
     * Evaluate a branch rule against current answers.
     * @param array $values fieldId => value
     * @param array $keyToId tempKey/id map for fieldKey resolution
     */
    public static function evaluateBranch(array $branch, array $values, array $keyToId = []): bool
    {
        $fieldKey = (string)($branch['fieldKey'] ?? '');
        if (isset($keyToId[$fieldKey])) {
            $branch['fieldKey'] = (string)$keyToId[$fieldKey];
        }
        return (new LogicEngine())->evaluateRule($branch, $values);
    }

    public function toPostRow(): array
    {
        $row = [
            'type' => $this->type,
            'label' => $this->label,
            'help_text' => $this->help_text,
            'required' => $this->required ? '1' : '',
            'options' => $this->getOptionsAsText(),
            'randomize' => $this->isRandomizeOptions() ? '1' : '',
            'max_select' => $this->getMaxSelect(),
            'exclusive_option' => implode('|', $this->getExclusiveOptions()),
            'prefill_profile' => $this->getPrefillProfileAttribute() ?: '',
            'condition_field' => $this->condition_field_id,
            'condition_operator' => $this->condition_operator,
            'condition_value' => $this->condition_value,
            'logic_action' => $this->getLogic()['action'],
            'logic_combinator' => $this->getLogic()['combinator'],
            'logic_goto' => $this->getLogic()['gotoPageKey'],
            'logic_rules' => $this->getLogic()['rules'],
            'carry_from' => $this->getCarryForward()['from'],
            'carry_mode' => $this->getCarryForward()['mode'],
            'justification' => $this->getJustification(),
        ];

        if ($this->type === self::TYPE_RATING) {
            $scale = $this->getRatingScale();
            $row['rating_min'] = $scale['min'];
            $row['rating_max'] = $scale['max'];
            $row['rating_step'] = $scale['step'];
            $row['rating_low_label'] = $scale['lowLabel'];
            $row['rating_high_label'] = $scale['highLabel'];
        } elseif ($this->type === self::TYPE_PAGE_BREAK) {
            $cfg = $this->getPageBreakConfig();
            $row['page_key'] = $cfg['pageKey'];
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
            $row['grid_rows'] = implode("\n", $grid['rows']);
            $row['grid_columns'] = implode("\n", $grid['columns']);
        } elseif ($this->type === self::TYPE_BEST_WORST || $this->type === self::TYPE_MAXDIFF) {
            $items = $this->getItemsConfig();
            $row['items'] = implode("\n", $items['items']);
            $row['maxdiff_set_size'] = $items['setSize'];
            $row['maxdiff_set_count'] = $items['setCount'];
        } elseif ($this->type === self::TYPE_DRILLDOWN) {
            $row['drilldown_tree'] = $this->getDrilldownTreeAsText();
        } elseif ($this->type === self::TYPE_IMAGE_AREA) {
            $img = $this->getImageAreaConfig();
            $row['image_url'] = $img['imageUrl'];
            $row['image_guid'] = $img['imageGuid'];
            $row['image_mode'] = $img['mode'];
            $row['image_multi'] = !empty($img['multi']) ? '1' : '';
            $row['image_regions'] = json_encode($img['regions'], JSON_UNESCAPED_UNICODE);
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
            'help_text' => (string)($payload['help_text'] ?? ''),
            'required' => !empty($payload['required']) ? '1' : '',
            'options' => (string)$options,
            'randomize' => !empty($payload['randomize']) ? '1' : '',
            'max_select' => $payload['max_select'] ?? ($payload['maxSelect'] ?? ''),
            'exclusive_option' => (string)($payload['exclusive_option'] ?? $payload['exclusiveOption'] ?? ''),
            'prefill_profile' => (string)($payload['prefill_profile'] ?? $payload['prefillProfile'] ?? ''),
            'rating_min' => $payload['rating_min'] ?? 1,
            'rating_max' => $payload['rating_max'] ?? 5,
            'rating_step' => $payload['rating_step'] ?? 1,
            'rating_low_label' => $payload['rating_low_label'] ?? '',
            'rating_high_label' => $payload['rating_high_label'] ?? '',
            'page_key' => $payload['page_key'] ?? '',
            'page_title' => $payload['page_title'] ?? '',
            'branches' => is_array($payload['branches'] ?? null) ? $payload['branches'] : [],
            'rich_content' => $payload['rich_content'] ?? '',
            'html_content' => $payload['html_content'] ?? '',
            'html_collect' => !empty($payload['html_collect']) ? '1' : '',
            'html_variable' => $payload['html_variable'] ?? 'value',
            'html_instructions' => $payload['html_instructions'] ?? '',
            'html_required' => !empty($payload['html_required']) ? '1' : '',
            'grid_rows' => is_array($payload['grid_rows'] ?? null) ? implode("\n", $payload['grid_rows']) : (string)($payload['grid_rows'] ?? ''),
            'grid_columns' => is_array($payload['grid_columns'] ?? null) ? implode("\n", $payload['grid_columns']) : (string)($payload['grid_columns'] ?? ''),
            'items' => is_array($payload['items'] ?? null) ? implode("\n", $payload['items']) : (string)($payload['items'] ?? $options),
            'maxdiff_set_size' => $payload['maxdiff_set_size'] ?? ($payload['setSize'] ?? 4),
            'maxdiff_set_count' => $payload['maxdiff_set_count'] ?? ($payload['setCount'] ?? ''),
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
            'logic_action' => $payload['logic_action'] ?? ($payload['logic']['action'] ?? 'show'),
            'logic_combinator' => $payload['logic_combinator'] ?? ($payload['logic']['combinator'] ?? 'and'),
            'logic_goto' => $payload['logic_goto'] ?? ($payload['logic']['gotoPageKey'] ?? ''),
            'logic_rules' => is_array($payload['logic_rules'] ?? null)
                ? $payload['logic_rules']
                : (is_array($payload['logic']['rules'] ?? null) ? $payload['logic']['rules'] : []),
            'carry_from' => (string)($payload['carry_from'] ?? ''),
            'carry_mode' => (string)($payload['carry_mode'] ?? self::CARRY_SELECTED),
            'condition_field' => $payload['condition_field'] ?? '',
            'condition_operator' => $payload['condition_operator'] ?? self::OP_EQUALS,
            'condition_value' => $payload['condition_value'] ?? '',
        ];

        return $row;
    }

    public static function fromPostRow(array $row): self
    {
        $field = new self();
        $field->type = (string)($row['type'] ?? self::TYPE_TEXT);
        $field->label = (string)($row['label'] ?? '');
        $field->help_text = $row['help_text'] ?? null;
        $field->required = !empty($row['required']);
        if ($field->type === self::TYPE_RATING) {
            $field->setRatingScale([
                'min' => $row['rating_min'] ?? 1,
                'max' => $row['rating_max'] ?? 5,
                'step' => $row['rating_step'] ?? 1,
                'lowLabel' => $row['rating_low_label'] ?? '',
                'highLabel' => $row['rating_high_label'] ?? '',
            ]);
        } elseif ($field->type === self::TYPE_PAGE_BREAK) {
            $field->setPageBreakConfig([
                'pageKey' => $row['page_key'] ?? '',
                'title' => $row['page_title'] ?? '',
                'branches' => is_array($row['branches'] ?? null) ? $row['branches'] : [],
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
                'rows' => $row['grid_rows'] ?? '',
                'columns' => $row['grid_columns'] ?? '',
            ]);
        } elseif ($field->type === self::TYPE_BEST_WORST || $field->type === self::TYPE_MAXDIFF) {
            $field->setItemsConfig([
                'items' => $row['items'] ?? ($row['options'] ?? ''),
                'setSize' => $row['maxdiff_set_size'] ?? 4,
                'setCount' => $row['maxdiff_set_count'] ?? 0,
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
        } elseif (self::isChoiceType($field->type)) {
            $maxSelect = null;
            if (($row['max_select'] ?? '') !== '' && $row['max_select'] !== null) {
                $maxSelect = (int)$row['max_select'];
            }
            $field->setOptionsFromText(
                $row['options'] ?? '',
                !empty($row['randomize']),
                $maxSelect,
                (string)($row['exclusive_option'] ?? '')
            );
            $field->setCarryForward((string)($row['carry_from'] ?? ''), (string)($row['carry_mode'] ?? self::CARRY_SELECTED));
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
