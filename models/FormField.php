<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
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
            [['options_json'], 'string'],
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

    public function getConditionField(): ActiveQuery
    {
        return $this->hasOne(self::class, ['id' => 'condition_field_id']);
    }

    public static function isChoiceType(?string $type): bool
    {
        return in_array($type, [self::TYPE_DROPDOWN, self::TYPE_RADIO, self::TYPE_CHECKBOX, self::TYPE_RANKING], true);
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

        if (!$options && !$randomize && $maxSelect === null && $exclusiveOption === null) {
            $this->options_json = null;
            return;
        }

        if ($randomize || $maxSelect !== null || $exclusiveOption !== null) {
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
        if (!$this->isRandomizeOptions() || count($options) < 2) {
            return $options;
        }

        $userId = $userId ?? (int)(Yii::$app->user->id ?? 0);
        $seed = crc32($userId . ':' . (int)$this->id . ':' . (int)$this->form_id);
        $order = range(0, count($options) - 1);

        // Mulberry32-ish deterministic shuffle
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
            $shuffled[] = $options[$idx];
        }
        return $shuffled;
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

    public function hasCondition(): bool
    {
        return !empty($this->condition_field_id) && !empty($this->condition_operator);
    }

    /**
     * @param array $values fieldId => value
     */
    public function isConditionMet(array $values): bool
    {
        if (!$this->hasCondition()) {
            return true;
        }

        $raw = $values[$this->condition_field_id] ?? null;
        $op = $this->condition_operator;
        $expected = (string)$this->condition_value;

        if (is_array($raw)) {
            $list = array_map('strval', $raw);
            if ($op === self::OP_CHECKED) {
                return in_array($expected, $list, true) || ($expected === '' && !empty($list));
            }
            if ($op === self::OP_EQUALS) {
                return in_array($expected, $list, true);
            }
            if ($op === self::OP_NOT_EQUALS) {
                return !in_array($expected, $list, true);
            }
            if ($op === self::OP_CONTAINS) {
                foreach ($list as $item) {
                    if ($expected !== '' && mb_stripos($item, $expected) !== false) {
                        return true;
                    }
                }
                return false;
            }
            return false;
        }

        $value = (string)$raw;
        switch ($op) {
            case self::OP_EQUALS:
                return $value === $expected;
            case self::OP_NOT_EQUALS:
                return $value !== $expected;
            case self::OP_CONTAINS:
                return $expected !== '' && mb_stripos($value, $expected) !== false;
            case self::OP_CHECKED:
                return $value !== '' && $value !== '0';
            default:
                return true;
        }
    }

    /**
     * Evaluate a branch rule against current answers.
     * @param array $values fieldId => value
     * @param array $keyToId tempKey/id map for fieldKey resolution
     */
    public static function evaluateBranch(array $branch, array $values, array $keyToId = []): bool
    {
        $fieldKey = (string)($branch['fieldKey'] ?? '');
        $fieldId = $keyToId[$fieldKey] ?? (ctype_digit($fieldKey) ? (int)$fieldKey : null);
        if (!$fieldId) {
            return false;
        }
        $probe = new self();
        $probe->condition_field_id = (int)$fieldId;
        $probe->condition_operator = (string)($branch['operator'] ?? self::OP_EQUALS);
        $probe->condition_value = (string)($branch['value'] ?? '');
        return $probe->isConditionMet($values);
    }

    public function inputName(): string
    {
        return 'field_' . $this->id;
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
