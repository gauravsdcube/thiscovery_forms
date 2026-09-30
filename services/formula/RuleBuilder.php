<?php

namespace humhub\modules\thiscoveryForms\services\formula;

/**
 * Turns a studio row into a syntax tree. Evaluation never uses the old compare functions.
 */
final class RuleBuilder
{
    /** @param array<string,mixed> $rule
     * @return array<string,mixed>|null
     */
    public static function fromSimple(array $rule): ?array
    {
        if (isset($rule['op'])) {
            return $rule;
        }
        if (isset($rule['when']) && is_array($rule['when'])) {
            return $rule['when'];
        }
        if (!empty($rule['all']) && is_array($rule['all'])) {
            $args = [];
            foreach ($rule['all'] as $sub) {
                if (!is_array($sub) || ($tree = self::fromSimple($sub)) === null) {
                    return null;
                }
                $args[] = $tree;
            }
            return $args === [] ? null : ['op' => 'and', 'args' => $args];
        }
        if (!empty($rule['any']) && is_array($rule['any'])) {
            $args = [];
            foreach ($rule['any'] as $sub) {
                if (!is_array($sub) || ($tree = self::fromSimple($sub)) === null) {
                    return null;
                }
                $args[] = $tree;
            }
            return $args === [] ? null : ['op' => 'or', 'args' => $args];
        }
        $key = trim((string)($rule['fieldKey'] ?? ''));
        $source = (string)($rule['source'] ?? '');
        if ($key === '' && $source === '') {
            return null;
        }
        $ref = self::reference($source, $key);
        $aggregate = (string)($rule['aggregate'] ?? '');
        if ($aggregate !== '') {
            $ref['all'] = true;
        }
        $expected = self::literal((string)($rule['value'] ?? ''));
        $op = (string)($rule['operator'] ?? 'equals');
        $compare = self::comparison($op, $ref, $expected);
        return match ($aggregate) {
            'any' => ['op' => 'any_eq', 'args' => [$ref, $expected]],
            'all' => ['op' => 'all_eq', 'args' => [$ref, $expected]],
            'count' => $compare === null ? null : ['op' => $compare['op'], 'args' => [ ['op' => 'count_answered', 'args' => [$ref]], $expected ]],
            'sum' => $compare === null ? null : ['op' => $compare['op'], 'args' => [ ['op' => 'sum', 'args' => [$ref]], $expected ]],
            '' => $op === 'equals' && $aggregate === '' ? ['op' => 'eq', 'args' => [$ref, $expected]] : $compare,
            default => null,
        };
    }

    /** @return array<string,mixed> */
    private static function reference(string $source, string $key): array
    {
        if ($source === 'panel' || str_starts_with($key, 'panel.')) {
            $name = str_starts_with($key, 'panel.') ? substr($key, 6) : $key;
            return ['op' => 'ref', 'ref' => 'panel', 'name' => $name];
        }
        if ($source === 'arm' || $key === 'arm') {
            return ['op' => 'ref', 'ref' => 'arm'];
        }
        return ['op' => 'ref', 'ref' => 'field', 'name' => $key];
    }

    /** @param array<string,mixed> $ref
     * @param array<string,mixed> $expected
     * @return array<string,mixed>|null
     */
    private static function comparison(string $op, array $ref, array $expected): ?array
    {
        $map = [
            'equals' => 'eq',
            'not_equals' => 'ne',
            'gt' => 'gt',
            'gte' => 'gte',
            'lt' => 'lt',
            'lte' => 'lte',
        ];
        if (isset($map[$op])) {
            return ['op' => $map[$op], 'args' => [$ref, $expected]];
        }
        if ($op === 'contains') {
            return ['op' => 'contains_text', 'args' => [$ref, $expected]];
        }
        if ($op === 'checked') {
            $needle = (string)($expected['v'] ?? '');
            return $needle === ''
                ? ['op' => 'is_answered', 'args' => [$ref]]
                : ['op' => 'eq', 'args' => [$ref, $expected]];
        }
        if ($op === 'between') {
            $parts = array_map('trim', explode(',', (string)($expected['v'] ?? ''), 2));
            if (count($parts) !== 2) {
                return null;
            }
            return ['op' => 'between', 'args' => [$ref, self::literal($parts[0]), self::literal($parts[1])]];
        }
        return null;
    }

    /** @return array<string,mixed> */
    private static function literal(string $value): array
    {
        $number = Decimal::canonical($value);
        if ($number !== null) {
            return ['op' => 'lit', 'lit' => 'number', 'v' => $number];
        }
        return ['op' => 'lit', 'lit' => 'text', 'v' => $value];
    }
}
