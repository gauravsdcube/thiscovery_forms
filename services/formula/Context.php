<?php

namespace humhub\modules\thiscoveryForms\services\formula;

use humhub\modules\thiscoveryForms\models\FormField;

/**
 * Answers and variables loaded before evaluation. The evaluator does not touch the database.
 */
final class Context
{
    /** @var array<string,Value> */
    public array $fields = [];
    /** @var array<string,list<array{key:string,value:Value}>> */
    public array $instances = [];
    /** @var array<string,array<string,Value>> */
    public array $rows = [];
    /** @var array<string,Value> */
    public array $vars = [];
    /** @var array<string,Value> */
    public array $panel = [];
    /** @var array<string,Value> */
    public array $meta = [];
    /** @var array<string,Value> */
    public array $url = [];
    public Value $arm;
    public string $today = '1970-01-01';
    /** @var array<string,array<string,string>> */
    public array $scores = [];
    /** @var array<string,array<string,string>> */
    public array $labels = [];
    /** @var array<string,array<string,mixed>> */
    public array $named = [];
    public int $steps = 0;

    public function __construct()
    {
        $this->arm = Value::empty();
    }

    /**
     * Option scores for the browser preview, keyed by variable then code.
     *
     * @param FormField[] $fields
     * @return array<string, array<string, string>>
     */
    public static function scoreMap(array $fields): array
    {
        $map = [];
        foreach ($fields as $field) {
            if (!$field instanceof FormField) {
                continue;
            }
            $name = trim((string)$field->variable);
            if ($name === '') {
                continue;
            }
            foreach ($field->getChoicePairs() as $pair) {
                if (!isset($pair['score']) || $pair['score'] === '') {
                    continue;
                }
                $score = Decimal::canonical((string)$pair['score']);
                if ($score === null) {
                    continue;
                }
                $map[$name][(string)$pair['code']] = $score;
            }
        }
        return $map;
    }

    /**
     * @param array<int|string,mixed> $values
     * @param FormField[] $fields
     */
    public static function fromValues(array $values, array $fields = [], string $today = ''): self
    {
        $context = new self();
        $context->today = $today !== '' ? $today : gmdate('Y-m-d');
        // Loop member ids. Their arrays are always instance maps, whatever the key shape:
        // codes 0..n arrive as a PHP list and 1..n as integer keys (V3-15).
        $loops = [];
        foreach ((is_array($values['__loops'] ?? null) ? $values['__loops'] : []) as $loopId) {
            $loops[(string)$loopId] = true;
        }
        foreach ($fields as $field) {
            if (!$field instanceof FormField) {
                continue;
            }
            $name = trim((string)$field->variable);
            if ($name === '') {
                $name = 'id' . (int)$field->id;
            }
            $raw = $values[$name] ?? $values[(int)$field->id] ?? $values[(string)$field->id] ?? $values['id' . (int)$field->id] ?? null;
            $context->rememberField($name, $field, $raw, isset($loops[(string)(int)$field->id]));
        }
        foreach ($values as $key => $raw) {
            $key = (string)$key;
            if ($key === '__today') {
                // The response's frozen formula date (V3-9).
                if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                    $context->today = $raw;
                }
                continue;
            }
            if ($key === '__named') {
                // Named formulas (fn:name), already parsed into trees (V3-13).
                if (is_array($raw)) {
                    foreach ($raw as $name => $tree) {
                        if (is_array($tree)) {
                            $context->named[(string)$name] = $tree;
                        }
                    }
                }
                continue;
            }
            if (str_starts_with($key, '__')) {
                continue;
            }
            if (str_starts_with($key, 'panel.')) {
                $context->panel[substr($key, 6)] = self::scalar($raw);
            } elseif ($key === 'arm') {
                $context->arm = self::scalar($raw);
            } elseif (str_starts_with($key, 'var:')) {
                $context->vars[substr($key, 4)] = self::scalar($raw);
            } elseif (str_starts_with($key, 'meta:')) {
                $context->meta[substr($key, 5)] = self::scalar($raw);
            } elseif (str_starts_with($key, 'url:')) {
                $context->url[substr($key, 4)] = self::scalar($raw);
            } elseif (!isset($context->fields[$key])) {
                $context->fields[$key] = self::scalar($raw);
            }
        }
        return $context;
    }

    private function rememberField(string $name, FormField $field, mixed $raw, bool $loop = false): void
    {
        foreach ($field->getChoicePairs() as $pair) {
            $code = (string)$pair['code'];
            $this->labels[$name][$code] = (string)$pair['label'];
            if (isset($pair['score']) && $pair['score'] !== '' && Decimal::canonical((string)$pair['score']) !== null) {
                $this->scores[$name][$code] = Decimal::canonical((string)$pair['score']);
            }
        }
        $grid = in_array((string)$field->type, ['grid_single', 'grid_multi'], true);
        if (is_array($raw) && $raw !== [] && ($loop || $grid || self::isInstanceMap($raw))) {
            $items = [];
            foreach ($raw as $key => $cell) {
                $value = $grid ? self::scalar($cell, FormField::TYPE_RADIO) : self::scalar($cell, (string)$field->type);
                $items[] = ['key' => (string)$key, 'value' => $value];
                if ($grid) {
                    // Grid rows are addressable as [question.row] (V3-13).
                    $this->rows[$name][(string)$key] = $value;
                }
            }
            $this->instances[$name] = $items;
            $this->store($name, (int)$field->id, Value::list(array_map(static fn ($item) => $item['value'], $items)));
            return;
        }
        $value = self::scalar($raw, (string)$field->type);
        $this->store($name, (int)$field->id, $value);
    }

    private function store(string $name, int $id, Value $value): void
    {
        if (isset($this->rows[$name])) {
            $this->rows[(string)$id] = $this->rows[$name];
            $this->rows['id' . $id] = $this->rows[$name];
        }
        $this->fields[$name] = $value;
        $this->fields[(string)$id] = $value;
        $this->fields['id' . $id] = $value;
        if (isset($this->instances[$name])) {
            $this->instances[(string)$id] = $this->instances[$name];
            $this->instances['id' . $id] = $this->instances[$name];
        }
    }

    /** @param array<mixed> $raw */
    private static function isInstanceMap(array $raw): bool
    {
        // Without loop or grid metadata, only a keyed map (not a plain list of choices)
        // can be an instance map. Loop members and grids are decided by type (V3-15).
        return $raw !== [] && !array_is_list($raw);
    }

    public static function scalar(mixed $raw, string $type = ''): Value
    {
        if ($raw === null || $raw === '') {
            return Value::empty();
        }
        if (is_bool($raw)) {
            return Value::bool($raw);
        }
        if (is_array($raw)) {
            // "0" means unticked only for a single yes/no checkbox. In a list of ticked
            // options it is the option coded 0, so it is kept (LOG-10).
            $itemType = $type === FormField::TYPE_CHECKBOX ? FormField::TYPE_RADIO : $type;
            $items = [];
            foreach ($raw as $item) {
                if (is_array($item)) {
                    continue;
                }
                $text = trim((string)$item);
                if ($text !== '') {
                    $items[] = self::leaf($text, $itemType);
                }
            }
            return Value::list($items);
        }
        return self::leaf(trim((string)$raw), $type);
    }

    private static function leaf(string $text, string $type): Value
    {
        if ($text === '') {
            return Value::empty();
        }
        if ($type === FormField::TYPE_CHECKBOX && $text === '0') {
            return Value::empty();
        }
        $choice = in_array($type, [
            FormField::TYPE_CHECKBOX,
            FormField::TYPE_RADIO,
            FormField::TYPE_DROPDOWN,
            FormField::TYPE_RANKING,
        ], true);
        if ($choice) {
            return Value::text($text);
        }
        if ($type === FormField::TYPE_NUMBER || $type === FormField::TYPE_CALCULATED || $type === '') {
            $number = Decimal::canonical($text);
            if ($number !== null && ($type !== '' || !preg_match('/[^\d.\-]/', $text))) {
                return Value::number($number);
            }
            if ($type === FormField::TYPE_NUMBER || $type === FormField::TYPE_CALCULATED) {
                return Value::empty();
            }
        }
        if (($type === FormField::TYPE_DATE || $type === '') && preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return Value::date($text);
        }
        return Value::text($text);
    }
}
