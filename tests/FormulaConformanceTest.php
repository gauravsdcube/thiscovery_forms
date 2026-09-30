<?php

/**
 * Shared formula cases. Expected results come from the operator table, not from the evaluator.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\services\formula\Context;
use humhub\modules\thiscoveryForms\services\formula\Decimal;
use humhub\modules\thiscoveryForms\services\formula\Evaluator;
use humhub\modules\thiscoveryForms\services\formula\Parser;
use humhub\modules\thiscoveryForms\services\formula\Value;

$failures = [];
$parser = new Parser();
$cases = [];

function conform_coerce(string $raw): array
{
    if ($raw === '') {
        return ['t' => 'empty'];
    }
    $number = Decimal::canonical($raw);
    if ($number !== null && !preg_match('/[^\d.\-]/', $raw)) {
        return ['t' => 'number', 'v' => $number];
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return ['t' => 'date', 'v' => $raw];
    }
    return ['t' => 'text', 'v' => $raw];
}

function conform_compare(array $left, string $op, array $right): string
{
    if ($left['t'] === 'empty' || $right['t'] === 'empty') {
        return $op === '!=' ? 'bool:true' : 'bool:false';
    }
    if ($left['t'] === 'number' && $right['t'] === 'number') {
        $order = Decimal::cmp($left['v'], $right['v']);
        $hit = match ($op) {
            '=' => $order === 0,
            '!=' => $order !== 0,
            '>' => $order > 0,
            '>=' => $order >= 0,
            '<' => $order < 0,
            '<=' => $order <= 0,
            default => false,
        };
        return $hit ? 'bool:true' : 'bool:false';
    }
    if (in_array($op, ['>', '>=', '<', '<='], true)) {
        return 'bool:false';
    }
    $same = (string)$left['v'] === (string)$right['v'];
    $hit = $op === '!=' ? !$same : $same;
    return $hit ? 'bool:true' : 'bool:false';
}

function conform_arith(array $left, string $op, array $right): string
{
    if ($left['t'] !== 'number' || $right['t'] !== 'number') {
        return 'empty';
    }
    $value = match ($op) {
        '+' => Decimal::add($left['v'], $right['v']),
        '-' => Decimal::sub($left['v'], $right['v']),
        '*' => Decimal::mul($left['v'], $right['v']),
        '/' => Decimal::div($left['v'], $right['v']),
        default => null,
    };
    return $value === null ? 'empty' : 'number:' . $value;
}

function conform_show(Value $value): string
{
    if ($value->isEmpty()) {
        return 'empty';
    }
    if ($value->type === 'bool') {
        return $value->data ? 'bool:true' : 'bool:false';
    }
    return $value->type . ':' . (string)$value->data;
}

$lefts = ['', '0', '1', '2', '10', '10 years', 'Ab', '3,5'];
$rights = ['', '0', '1', '3', 'a', 'Ab'];
foreach ($lefts as $left) {
    foreach ($rights as $right) {
        foreach (['=', '!=', '>', '>=', '<', '<='] as $op) {
            $cases[] = [
                'expr' => '[a] ' . $op . ' [b]',
                'values' => ['a' => $left, 'b' => $right],
                'expect' => conform_compare(conform_coerce($left), $op, conform_coerce($right)),
            ];
        }
    }
}
$nums = ['0', '1', '2', '0.1', '1.5', '-1.5', '10'];
foreach ($nums as $left) {
    foreach ($nums as $right) {
        foreach (['+', '-', '*', '/'] as $op) {
            $cases[] = [
                'expr' => '[a] ' . $op . ' [b]',
                'values' => ['a' => $left, 'b' => $right],
                'expect' => conform_arith(conform_coerce($left), $op, conform_coerce($right)),
            ];
        }
    }
}
$extra = [
    ['sum([a], [b])', ['a' => '', 'b' => ''], 'empty'],
    ['sum([a], [b])', ['a' => '', 'b' => '2'], 'number:2'],
    ['mean([a], [b])', ['a' => '2', 'b' => '4'], 'number:3'],
    ['min_valid(2, [a], [b])', ['a' => '1', 'b' => ''], 'empty'],
    ['min_valid(1, [a], [b])', ['a' => '1', 'b' => ''], 'number:1'],
    ['if([a] > 1, 2, 3)', ['a' => ''], 'number:3'],
    ['contains_text([a], "a")', ['a' => 'Ab'], 'bool:true'],
    ['round(1.5, 0)', [], 'number:2'],
    ['round(-1.5, 0)', [], 'number:-2'],
    ['round(1.25, 1)', [], 'number:1.3'],
    ['date_diff(date("2020-02-29"), date("2021-02-28"), "years")', [], 'number:0'],
    ['date_diff(date("2020-02-29"), date("2021-03-01"), "years")', [], 'number:1'],
    ['add_months(date("2021-01-31"), 1)', [], 'date:2021-02-28'],
    ['add_days(date("2021-01-31"), 1)', [], 'date:2021-02-01'],
    ['is_empty([a])', ['a' => ''], 'bool:true'],
    ['[a] = ""', ['a' => ''], 'bool:false'],
];
foreach ($extra as [$expr, $values, $expect]) {
    $cases[] = ['expr' => $expr, 'values' => $values, 'expect' => $expect];
}

$shared = [];
foreach ($cases as $i => $case) {
    try {
        $tree = $parser->parse($case['expr']);
    } catch (Throwable $e) {
        $failures[] = 'parse ' . $case['expr'] . ' ' . $e->getMessage();
        continue;
    }
    $context = new Context();
    $context->today = '2026-09-30';
    foreach ($case['values'] as $name => $raw) {
        $context->fields[$name] = Context::scalar($raw);
    }
    $got = conform_show((new Evaluator($context))->evaluate($tree));
    if ($got !== $case['expect']) {
        $failures[] = $case['expr'] . ' ' . json_encode($case['values']) . ' got ' . $got . ' expected ' . $case['expect'];
        if (count($failures) > 12) {
            break;
        }
    }
    $shared[] = [
        'tree' => $tree,
        'values' => $case['values'] + ['__today' => '2026-09-30'],
        'expect' => $case['expect'],
    ];
}

$encoded = json_encode($shared, JSON_UNESCAPED_UNICODE);
$target = __DIR__ . '/test-vectors/formula/expressions.json';
if (@file_put_contents($target, $encoded) === false) {
    file_put_contents('/tmp/formula-expressions.json', $encoded);
}

if ($failures) {
    fwrite(STDERR, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
fwrite(STDOUT, 'PASS ' . count($cases) . "\n");
