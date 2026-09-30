<?php

namespace humhub\modules\thiscoveryForms\services\formula;

/**
 * Evaluates an ADR-010 tree. It does not read the database.
 */
final class Evaluator
{
    public function __construct(private Context $context)
    {
    }

    /** @param array<string,mixed> $tree */
    public function evaluate(array $tree): Value
    {
        $op = (string)($tree['op'] ?? '');
        $args = is_array($tree['args'] ?? null) ? $tree['args'] : [];
        if ($op === 'lit') {
            return $this->literal($tree);
        }
        if ($op === 'ref') {
            return $this->reference($tree);
        }
        if ($op === 'list') {
            return Value::list(array_map(fn ($arg) => $this->evaluate($arg), $args));
        }
        $values = array_map(fn ($arg) => $this->evaluate($arg), $args);
        return match ($op) {
            'add', 'sub', 'mul', 'div', 'mod', 'pow', 'neg' => $this->arithmetic($op, $values),
            'eq', 'ne', 'gt', 'gte', 'lt', 'lte' => Value::bool($this->compare($op, $values[0] ?? Value::empty(), $values[1] ?? Value::empty())),
            'and' => Value::bool(($values[0] ?? Value::empty())->truth() && ($values[1] ?? Value::empty())->truth()),
            'or' => Value::bool(($values[0] ?? Value::empty())->truth() || ($values[1] ?? Value::empty())->truth()),
            'not' => Value::bool(!($values[0] ?? Value::empty())->truth()),
            'in' => Value::bool($this->inList($values[0] ?? Value::empty(), $values[1] ?? Value::empty(), false)),
            'not_in' => Value::bool($this->inList($values[0] ?? Value::empty(), $values[1] ?? Value::empty(), true)),
            'sum', 'mean', 'min', 'max', 'count', 'count_eq', 'count_answered', 'count_selected' => $this->aggregate($op, $values),
            'round', 'floor', 'ceil', 'abs', 'sqrt' => $this->unaryNumber($op, $values),
            'if' => $this->ifValue($values),
            'coalesce' => $this->coalesce($values),
            'ifempty' => ($values[0] ?? Value::empty())->isEmpty() ? ($values[1] ?? Value::empty()) : $values[0],
            'min_valid' => $this->minValid($values),
            'concat' => $this->concat($values),
            'lower', 'upper', 'length' => $this->textOp($op, $values[0] ?? Value::empty()),
            'contains_text' => Value::bool($this->containsText($values[0] ?? Value::empty(), $values[1] ?? Value::empty())),
            'today' => Value::date($this->context->today),
            'date_diff' => $this->dateDiff($values),
            'add_days', 'add_months' => $this->shiftDate($op, $values),
            'year', 'month' => $this->datePart($op, $values[0] ?? Value::empty()),
            'any_eq', 'all_eq' => Value::bool($this->quantify($op, $values)),
            'between' => Value::bool($this->between($values)),
            'is_empty' => Value::bool(($values[0] ?? Value::empty())->isEmpty()),
            'is_answered' => Value::bool($this->answered($values[0] ?? Value::empty())),
            'selected' => Value::bool($this->selected($values)),
            'code_of' => $this->codeOf($values[0] ?? Value::empty()),
            'score_of' => $this->scoreOf($tree, $values),
            default => Value::empty(),
        };
    }

    public function truth(array $tree): bool
    {
        return $this->evaluate($tree)->truth();
    }

    /** @param array<string,mixed> $tree */
    private function literal(array $tree): Value
    {
        $kind = (string)($tree['lit'] ?? '');
        return match ($kind) {
            'number' => ($canonical = Decimal::canonical((string)($tree['v'] ?? ''))) !== null ? Value::number($canonical) : Value::empty(),
            'text' => Value::text((string)($tree['v'] ?? '')),
            'bool' => Value::bool(!empty($tree['v'])),
            'date' => Value::date((string)($tree['v'] ?? '')),
            'datetime' => Value::text((string)($tree['v'] ?? '')),
            default => Value::empty(),
        };
    }

    /** @param array<string,mixed> $tree */
    private function reference(array $tree): Value
    {
        $kind = (string)($tree['ref'] ?? '');
        $name = (string)($tree['name'] ?? '');
        if ($kind === 'arm') {
            return $this->context->arm;
        }
        if ($kind === 'var') {
            return $this->context->vars[$name] ?? Value::empty();
        }
        if ($kind === 'panel') {
            return $this->context->panel[$name] ?? Value::empty();
        }
        if ($kind === 'meta') {
            return $this->context->meta[$name] ?? Value::empty();
        }
        if ($kind === 'url') {
            return $this->context->url[$name] ?? Value::empty();
        }
        if (!empty($tree['all'])) {
            $items = $this->context->instances[$name] ?? [];
            return Value::list(array_map(static fn ($item) => $item['value'], $items));
        }
        if (isset($tree['instance'])) {
            foreach ($this->context->instances[$name] ?? [] as $item) {
                if ($item['key'] === (string)$tree['instance']) {
                    return $item['value'];
                }
            }
            return Value::empty();
        }
        if (isset($tree['row'])) {
            return $this->context->rows[$name][(string)$tree['row']] ?? Value::empty();
        }
        return $this->context->fields[$name] ?? Value::empty();
    }

    /** @param list<Value> $values */
    private function arithmetic(string $op, array $values): Value
    {
        if ($op === 'neg') {
            $value = $values[0] ?? Value::empty();
            if ($value->type !== 'number') {
                return Value::empty();
            }
            $neg = Decimal::negate((string)$value->data);
            return $neg === null ? Value::empty() : Value::number($neg);
        }
        $left = $values[0] ?? Value::empty();
        $right = $values[1] ?? Value::empty();
        if ($left->isEmpty() || $right->isEmpty() || $left->type !== 'number' || $right->type !== 'number') {
            return Value::empty();
        }
        $result = match ($op) {
            'add' => Decimal::add((string)$left->data, (string)$right->data),
            'sub' => Decimal::sub((string)$left->data, (string)$right->data),
            'mul' => Decimal::mul((string)$left->data, (string)$right->data),
            'div' => Decimal::div((string)$left->data, (string)$right->data),
            'mod' => $this->mod((string)$left->data, (string)$right->data),
            'pow' => $this->pow((string)$left->data, (string)$right->data),
            default => null,
        };
        return $result === null ? Value::empty() : Value::number($result);
    }

    private function mod(string $left, string $right): ?string
    {
        if (Decimal::cmp($right, '0') === 0) {
            return null;
        }
        $quotient = Decimal::div($left, $right);
        if ($quotient === null) {
            return null;
        }
        $whole = Decimal::round($quotient, 0);
        if ($whole === null) {
            return null;
        }
        if (Decimal::cmp($quotient, '0') < 0 && Decimal::cmp($quotient, $whole) !== 0) {
            $whole = Decimal::sub($whole, '1');
        }
        $product = $whole === null ? null : Decimal::mul($whole, $right);
        return $product === null ? null : Decimal::sub($left, $product);
    }

    private function pow(string $left, string $right): ?string
    {
        if (Decimal::canonical($right) === null || str_contains($right, '.')) {
            return null;
        }
        $times = (int)$right;
        if ($times < 0 || $times > 20) {
            return null;
        }
        $result = '1';
        for ($i = 0; $i < $times; $i++) {
            $result = Decimal::mul($result, $left);
            if ($result === null) {
                return null;
            }
        }
        return $result;
    }

    private function compare(string $op, Value $left, Value $right): bool
    {
        if ($left->isEmpty() || $right->isEmpty()) {
            return $op === 'ne';
        }
        if ($left->type === 'list') {
            $any = false;
            foreach ($left->data as $item) {
                if ($item instanceof Value && $this->compare($op === 'ne' ? 'eq' : $op, $item, $right)) {
                    $any = true;
                    break;
                }
            }
            return $op === 'ne' ? !$any : $any;
        }
        if ($left->type === 'number' && $right->type === 'number') {
            $order = Decimal::cmp((string)$left->data, (string)$right->data);
            return match ($op) {
                'eq' => $order === 0,
                'ne' => $order !== 0,
                'gt' => $order > 0,
                'gte' => $order >= 0,
                'lt' => $order < 0,
                'lte' => $order <= 0,
                default => false,
            };
        }
        if ($left->type === 'date' && $right->type === 'date') {
            $order = strcmp((string)$left->data, (string)$right->data);
            return match ($op) {
                'eq' => $order === 0,
                'ne' => $order !== 0,
                'gt' => $order > 0,
                'gte' => $order >= 0,
                'lt' => $order < 0,
                'lte' => $order <= 0,
                default => false,
            };
        }
        if ($op === 'gt' || $op === 'gte' || $op === 'lt' || $op === 'lte') {
            return false;
        }
        $same = $this->sameText($left, $right);
        return $op === 'ne' ? !$same : $same;
    }

    private function sameText(Value $left, Value $right): bool
    {
        $a = $left->type === 'bool' ? ($left->data ? 'true' : 'false') : (string)$left->data;
        $b = $right->type === 'bool' ? ($right->data ? 'true' : 'false') : (string)$right->data;
        return $a === $b;
    }

    private function inList(Value $left, Value $right, bool $negate): bool
    {
        if ($left->isEmpty()) {
            return $negate;
        }
        $items = $right->type === 'list' ? $right->data : [$right];
        $found = false;
        foreach ($items as $item) {
            if ($item instanceof Value && $this->compare('eq', $left, $item)) {
                $found = true;
                break;
            }
        }
        return $negate ? !$found : $found;
    }

    /** @param list<Value> $values */
    private function aggregate(string $op, array $values): Value
    {
        $flat = $this->flatten($values);
        if ($op === 'count_eq') {
            $needle = array_pop($flat);
            if (!$needle instanceof Value || $needle->isEmpty() || $this->allEmpty($flat)) {
                return Value::empty();
            }
            $count = 0;
            foreach ($flat as $item) {
                if (!$item->isEmpty() && $this->compare('eq', $item, $needle)) {
                    $count++;
                }
            }
            return Value::number((string)$count);
        }
        if ($op === 'count_answered' || $op === 'count' || $op === 'count_selected') {
            if ($this->allEmpty($flat)) {
                return Value::empty();
            }
            $count = 0;
            foreach ($flat as $item) {
                if ($this->answered($item)) {
                    $count++;
                }
            }
            return Value::number((string)$count);
        }
        $numbers = [];
        foreach ($flat as $item) {
            if ($item->type === 'number') {
                $numbers[] = (string)$item->data;
            }
        }
        if ($numbers === []) {
            return Value::empty();
        }
        if ($op === 'min' || $op === 'max') {
            $best = $numbers[0];
            foreach ($numbers as $number) {
                $order = Decimal::cmp($number, $best);
                if (($op === 'min' && $order < 0) || ($op === 'max' && $order > 0)) {
                    $best = $number;
                }
            }
            return Value::number($best);
        }
        $total = '0';
        foreach ($numbers as $number) {
            $total = Decimal::add($total, $number) ?? $total;
        }
        if ($op === 'mean') {
            $mean = Decimal::div($total, (string)count($numbers));
            return $mean === null ? Value::empty() : Value::number($mean);
        }
        return Value::number($total);
    }

    /** @param list<Value> $values
     * @return list<Value>
     */
    private function flatten(array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            if ($value->type === 'list') {
                foreach ($value->data as $item) {
                    if ($item instanceof Value) {
                        $out[] = $item;
                    }
                }
                continue;
            }
            $out[] = $value;
        }
        return $out;
    }

    /** @param list<Value> $values */
    private function allEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if (!$value->isEmpty()) {
                return false;
            }
        }
        return true;
    }

    /** @param list<Value> $values */
    private function unaryNumber(string $op, array $values): Value
    {
        $value = $values[0] ?? Value::empty();
        if ($value->type !== 'number') {
            return Value::empty();
        }
        $text = (string)$value->data;
        if ($op === 'abs') {
            $result = str_starts_with($text, '-') ? Decimal::negate($text) : $text;
            return $result === null ? Value::empty() : Value::number($result);
        }
        if ($op === 'round') {
            $places = isset($values[1]) && $values[1]->type === 'number' ? (int)$values[1]->data : 0;
            $result = Decimal::round($text, $places);
            return $result === null ? Value::empty() : Value::number($result);
        }
        if ($op === 'floor' || $op === 'ceil') {
            $whole = Decimal::round($text, 0);
            if ($whole === null) {
                return Value::empty();
            }
            $order = Decimal::cmp($text, $whole);
            if ($op === 'floor' && $order < 0) {
                $whole = Decimal::sub($whole, '1');
            }
            if ($op === 'ceil' && $order > 0) {
                $whole = Decimal::add($whole, '1');
            }
            return $whole === null ? Value::empty() : Value::number($whole);
        }
        if ($op === 'sqrt') {
            if (Decimal::cmp($text, '0') < 0) {
                return Value::empty();
            }
            $guess = '1';
            for ($i = 0; $i < 20; $i++) {
                $div = Decimal::div($text, $guess);
                if ($div === null) {
                    return Value::empty();
                }
                $next = Decimal::div(Decimal::add($guess, $div) ?? $guess, '2');
                if ($next === null || $next === $guess) {
                    break;
                }
                $guess = $next;
            }
            return Value::number($guess);
        }
        return Value::empty();
    }

    /** @param list<Value> $values */
    private function ifValue(array $values): Value
    {
        $test = $values[0] ?? Value::empty();
        return $test->truth() ? ($values[1] ?? Value::empty()) : ($values[2] ?? Value::empty());
    }

    /** @param list<Value> $values */
    private function coalesce(array $values): Value
    {
        foreach ($values as $value) {
            if (!$value->isEmpty()) {
                return $value;
            }
        }
        return Value::empty();
    }

    /** @param list<Value> $values */
    private function minValid(array $values): Value
    {
        $need = $values[0] ?? Value::empty();
        if ($need->type !== 'number') {
            return Value::empty();
        }
        $rest = array_slice($values, 1);
        $flat = $this->flatten($rest);
        $numbers = [];
        foreach ($flat as $item) {
            if ($item->type === 'number') {
                $numbers[] = (string)$item->data;
            }
        }
        if (count($numbers) < (int)$need->data) {
            return Value::empty();
        }
        $total = '0';
        foreach ($numbers as $number) {
            $total = Decimal::add($total, $number) ?? $total;
        }
        return Value::number($total);
    }

    /** @param list<Value> $values */
    private function concat(array $values): Value
    {
        $text = '';
        foreach ($this->flatten($values) as $value) {
            if (!$value->isEmpty()) {
                $text .= (string)$value->data;
            }
        }
        return Value::text(substr($text, 0, 2000));
    }

    private function textOp(string $op, Value $value): Value
    {
        if ($value->isEmpty()) {
            return Value::empty();
        }
        $text = (string)$value->data;
        return match ($op) {
            'lower' => Value::text(mb_strtolower($text)),
            'upper' => Value::text(mb_strtoupper($text)),
            'length' => Value::number((string)mb_strlen($text)),
            default => Value::empty(),
        };
    }

    private function containsText(Value $haystack, Value $needle): bool
    {
        if ($needle->isEmpty() || (string)$needle->data === '' || $haystack->isEmpty()) {
            return false;
        }
        if ($haystack->type === 'list') {
            foreach ($haystack->data as $item) {
                if ($item instanceof Value && !$item->isEmpty() && mb_stripos((string)$item->data, (string)$needle->data) !== false) {
                    return true;
                }
            }
            return false;
        }
        return mb_stripos((string)$haystack->data, (string)$needle->data) !== false;
    }

    /** @param list<Value> $values */
    private function dateDiff(array $values): Value
    {
        $start = $values[0] ?? Value::empty();
        $end = $values[1] ?? Value::empty();
        $unit = strtolower((string)(($values[2] ?? Value::empty())->data ?? ''));
        if ($start->type !== 'date' || $end->type !== 'date') {
            return Value::empty();
        }
        $a = date_create_immutable((string)$start->data);
        $b = date_create_immutable((string)$end->data);
        if (!$a || !$b) {
            return Value::empty();
        }
        if ($unit === 'days') {
            return Value::number((string)$a->diff($b)->days * ($b >= $a ? 1 : -1));
        }
        $years = (int)$b->format('Y') - (int)$a->format('Y');
        $months = (int)$b->format('n') - (int)$a->format('n');
        $days = (int)$b->format('j') - (int)$a->format('j');
        $total = $years * 12 + $months;
        if ($days < 0) {
            $total--;
        }
        if ($unit === 'months') {
            return Value::number((string)$total);
        }
        if ($unit === 'years') {
            $whole = intdiv($total, 12);
            if ($total < 0 && $total % 12 !== 0) {
                $whole--;
            }
            return Value::number((string)$whole);
        }
        return Value::empty();
    }

    /** @param list<Value> $values */
    private function shiftDate(string $op, array $values): Value
    {
        $date = $values[0] ?? Value::empty();
        $amount = $values[1] ?? Value::empty();
        if ($date->type !== 'date' || $amount->type !== 'number') {
            return Value::empty();
        }
        $base = date_create_immutable((string)$date->data);
        if (!$base) {
            return Value::empty();
        }
        $steps = (int)$amount->data;
        if ($op === 'add_days') {
            $next = $base->modify(($steps >= 0 ? '+' : '') . $steps . ' days');
            return $next ? Value::date($next->format('Y-m-d')) : Value::empty();
        }
        $next = $base->modify(($steps >= 0 ? '+' : '') . $steps . ' months');
        if (!$next) {
            return Value::empty();
        }
        if ((int)$next->format('j') !== (int)$base->format('j')) {
            $next = $next->modify('last day of previous month');
        }
        return $next ? Value::date($next->format('Y-m-d')) : Value::empty();
    }

    private function datePart(string $op, Value $value): Value
    {
        if ($value->type !== 'date') {
            return Value::empty();
        }
        $date = date_create_immutable((string)$value->data);
        if (!$date) {
            return Value::empty();
        }
        return Value::number($op === 'year' ? $date->format('Y') : $date->format('n'));
    }

    /** @param list<Value> $values */
    private function quantify(string $op, array $values): bool
    {
        $needle = $values[1] ?? Value::empty();
        $items = $this->flatten([$values[0] ?? Value::empty()]);
        if ($op === 'all_eq' && $items === []) {
            return false;
        }
        $seen = false;
        foreach ($items as $item) {
            if ($item->isEmpty()) {
                continue;
            }
            $seen = true;
            $hit = $this->compare('eq', $item, $needle);
            if ($op === 'any_eq' && $hit) {
                return true;
            }
            if ($op === 'all_eq' && !$hit) {
                return false;
            }
        }
        return $op === 'all_eq' && $seen;
    }

    /** @param list<Value> $values */
    private function between(array $values): bool
    {
        $value = $values[0] ?? Value::empty();
        $low = $values[1] ?? Value::empty();
        $high = $values[2] ?? Value::empty();
        if ($value->isEmpty() || $low->isEmpty() || $high->isEmpty()) {
            return false;
        }
        if ($this->compare('gt', $low, $high)) {
            [$low, $high] = [$high, $low];
        }
        return $this->compare('gte', $value, $low) && $this->compare('lte', $value, $high);
    }

    private function answered(Value $value): bool
    {
        if ($value->isEmpty()) {
            return false;
        }
        if ($value->type === 'text' && $value->data === '0') {
            return false;
        }
        if ($value->type === 'list') {
            return $value->data !== [];
        }
        return true;
    }

    /** @param list<Value> $values */
    private function selected(array $values): bool
    {
        $value = $values[0] ?? Value::empty();
        $code = $values[1] ?? Value::empty();
        if ($code->isEmpty()) {
            return false;
        }
        if ($value->type === 'list') {
            return $this->compare('eq', $value, $code);
        }
        return $this->compare('eq', $value, $code);
    }

    private function codeOf(Value $value): Value
    {
        if ($value->type === 'text' || $value->type === 'number') {
            return $value;
        }
        return Value::empty();
    }

    /** @param array<string,mixed> $tree
     * @param list<Value> $values
     */
    private function scoreOf(array $tree, array $values): Value
    {
        $ref = $tree['args'][0] ?? null;
        $name = is_array($ref) ? (string)($ref['name'] ?? '') : '';
        $value = $values[0] ?? Value::empty();
        if ($value->isEmpty() || $name === '') {
            return Value::empty();
        }
        $code = (string)$value->data;
        if (isset($this->context->scores[$name][$code])) {
            return Value::number($this->context->scores[$name][$code]);
        }
        $number = Decimal::canonical($code);
        return $number === null ? Value::empty() : Value::number($number);
    }
}
