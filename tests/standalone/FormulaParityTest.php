<?php
/**
 * V3-10: the browser evaluator gives the same value and type as PHP for every operator and
 * function, on typed questions (choice codes including "0", numbers, dates, grids, loops,
 * variables, URL and panel values). Generates the cases, evaluates them in PHP, then runs
 * them through resources/js/thiscoveryForms.formula.rules.js in Node.
 */
require __DIR__ . '/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\formula\Context;
use humhub\modules\thiscoveryForms\services\formula\Evaluator;
use humhub\modules\thiscoveryForms\services\formula\Value;

$today = '2026-09-30';
$scorePairs = [['code' => '1', 'label' => 'Low', 'score' => '10'], ['code' => '2', 'label' => 'High', 'score' => '20']];
$fields = [
    new FormField(1, 'r', 'radio'), new FormField(2, 'r2', 'radio', '', $scorePairs), new FormField(3, 'r3', 'radio'),
    new FormField(4, 'n', 'number'), new FormField(5, 'neg', 'number'), new FormField(6, 'big', 'number'),
    new FormField(7, 'bad', 'number'), new FormField(8, 't', 'text'), new FormField(9, 't0', 'text'),
    new FormField(10, 'd', 'date'), new FormField(11, 'd2', 'date'), new FormField(12, 'dbad', 'date'),
    new FormField(13, 'cb', 'checkbox'), new FormField(14, 'cb0', 'checkbox'), new FormField(15, 'g', 'grid_single'),
    new FormField(16, 'loopa', 'text'), new FormField(17, 'loopn', 'number'), new FormField(18, 'rating', 'rating'),
    new FormField(19, 'dd', 'dropdown'), new FormField(20, 'loop0', 'radio'), new FormField(21, 'cbz', 'checkbox'),
];
$values = [
    1 => '0', 2 => '2', 3 => '', 4 => '7.5', 5 => '-3', 6 => '12345678901234567890', 7 => 'abc',
    8 => 'Hello World', 9 => '0', 10 => '2024-02-29', 11 => '2023-01-31', 12 => '2024-02-30',
    13 => ['a', 'b'], 14 => '0', 15 => ['r1' => '1', 'r2' => '3'],
    16 => ['asthma' => 'wheeze', 'diabetes' => 'thirst'], 17 => ['2' => '4', '5' => '6'], 18 => '4', 19 => 'yes',
    // Repeat codes 0 and 1: PHP holds this as a list, JSON as an array (V3-15).
    20 => [0 => '1', 1 => '3'], '__loops' => [16, 17, 20],
    // A multiple-choice checkbox with the option coded 0 ticked (LOG-10).
    21 => ['0', 'a'],
    'var:score' => '12', 'url:arm' => 'B', 'panel.site' => 'north', 'arm' => 'A',
];
$named = ['double_n' => ['op' => 'mul', 'args' => [['op' => 'ref', 'ref' => 'field', 'name' => 'n'], ['op' => 'lit', 'lit' => 'number', 'v' => '2']]]];

$ref = static fn(string $name, array $extra = []): array => ['op' => 'ref', 'ref' => 'field', 'name' => $name] + $extra;
$num = static fn(string $v): array => ['op' => 'lit', 'lit' => 'number', 'v' => $v];
$txt = static fn(string $v): array => ['op' => 'lit', 'lit' => 'text', 'v' => $v];
$pool = [
    '[r]' => $ref('r'), '[r2]' => $ref('r2'), '[r3]' => $ref('r3'), '[n]' => $ref('n'), '[neg]' => $ref('neg'),
    '[big]' => $ref('big'), '[bad]' => $ref('bad'), '[t]' => $ref('t'), '[t0]' => $ref('t0'), '[d]' => $ref('d'),
    '[d2]' => $ref('d2'), '[dbad]' => $ref('dbad'), '[cb]' => $ref('cb'), '[cb0]' => $ref('cb0'), '[g]' => $ref('g'),
    '[g.r2]' => $ref('g', ['row' => 'r2']), '[loopa[*]]' => $ref('loopa', ['all' => true]),
    '[loopn[*]]' => $ref('loopn', ['all' => true]), '[loopa["asthma"]]' => $ref('loopa', ['instance' => 'asthma']),
    '[loopn["5"]]' => $ref('loopn', ['instance' => '5']), '[rating]' => $ref('rating'), '[dd]' => $ref('dd'),
    '[loop0[*]]' => $ref('loop0', ['all' => true]), '[loop0["0"]]' => $ref('loop0', ['instance' => '0']),
    '[loop0["1"]]' => $ref('loop0', ['instance' => '1']), '[cbz]' => $ref('cbz'),
    '[var:score]' => ['op' => 'ref', 'ref' => 'var', 'name' => 'score'], '[url:arm]' => ['op' => 'ref', 'ref' => 'url', 'name' => 'arm'],
    '[panel:site]' => ['op' => 'ref', 'ref' => 'panel', 'name' => 'site'], '[arm]' => ['op' => 'ref', 'ref' => 'arm'],
    '[missing]' => $ref('missing'),
    '0' => $num('0'), '2' => $num('2'), '7.5' => $num('7.5'), '-1' => $num('-1'), '3' => $num('3'),
    '"0"' => $txt('0'), '"a"' => $txt('a'), '"hello"' => $txt('hello'), '"2"' => $txt('2'),
    'true' => ['op' => 'lit', 'lit' => 'bool', 'v' => true], 'false' => ['op' => 'lit', 'lit' => 'bool', 'v' => false],
    'empty' => ['op' => 'lit', 'lit' => 'empty'], 'date("2024-01-01")' => ['op' => 'lit', 'lit' => 'date', 'v' => '2024-01-01'],
];
$binary = ['add', 'sub', 'mul', 'div', 'mod', 'pow', 'eq', 'ne', 'gt', 'gte', 'lt', 'lte', 'and', 'or', 'in', 'not_in', 'contains_text', 'selected', 'selected_all', 'selected_only', 'ifempty', 'coalesce', 'any_eq', 'all_eq', 'count_eq'];
$unary = ['neg', 'not', 'sum', 'mean', 'min', 'max', 'count', 'count_answered', 'count_selected', 'abs', 'round', 'floor', 'ceil', 'sqrt', 'lower', 'upper', 'length', 'year', 'month', 'is_empty', 'is_answered', 'code_of', 'concat'];

$cases = [];
foreach ($pool as $label => $tree) {
    $cases[] = ['label' => $label, 'tree' => $tree];
    foreach ($unary as $op) {
        $cases[] = ['label' => "$op($label)", 'tree' => ['op' => $op, 'args' => [$tree]]];
    }
}
foreach ($pool as $la => $a) {
    foreach ($pool as $lb => $b) {
        foreach ($binary as $op) {
            $right = $b;
            if ($op === 'in' || $op === 'not_in') {
                $right = ['op' => 'list', 'args' => [$b, $num('2'), $txt('a')]];
            }
            $cases[] = ['label' => "$op($la, $lb)", 'tree' => ['op' => $op, 'args' => [$a, $right]]];
        }
    }
}
$extra = [
    'and(true,true,false)' => ['op' => 'and', 'args' => [$pool['true'], $pool['true'], $pool['false']]],
    'or(false,false,true)' => ['op' => 'or', 'args' => [$pool['false'], $pool['false'], $pool['true']]],
    'and(true)' => ['op' => 'and', 'args' => [$pool['true']]],
    'selected_all([cb],"a","b")' => ['op' => 'selected_all', 'args' => [$pool['[cb]'], $txt('a'), $txt('b')]],
    'selected_only([cb],"a")' => ['op' => 'selected_only', 'args' => [$pool['[cb]'], $txt('a')]],
    'selected_only([cb],["a","b"])' => ['op' => 'selected_only', 'args' => [$pool['[cb]'], ['op' => 'list', 'args' => [$txt('a'), $txt('b')]]]],
    'between(5,10,1)' => ['op' => 'between', 'args' => [$num('5'), $num('10'), $num('1')]],
    'between([r2],1,3)' => ['op' => 'between', 'args' => [$pool['[r2]'], $num('1'), $num('3')]],
    'if([r],1,2)' => ['op' => 'if', 'args' => [$pool['[r]'], $num('1'), $num('2')]],
    'if([r3],1,2)' => ['op' => 'if', 'args' => [$pool['[r3]'], $num('1'), $num('2')]],
    'min_valid(2,[n],[neg],[bad])' => ['op' => 'min_valid', 'args' => [$num('2'), $pool['[n]'], $pool['[neg]'], $pool['[bad]']]],
    'min_valid(3,[n],[neg],[bad])' => ['op' => 'min_valid', 'args' => [$num('3'), $pool['[n]'], $pool['[neg]'], $pool['[bad]']]],
    'score_of([r2])' => ['op' => 'score_of', 'args' => [$pool['[r2]']]],
    'score_of([r])' => ['op' => 'score_of', 'args' => [$pool['[r]']]],
    'today()' => ['op' => 'today', 'args' => []],
    'fn:double_n' => ['op' => 'fn', 'name' => 'double_n'],
    'fn:unknown' => ['op' => 'fn', 'name' => 'unknown'],
    'round(2.5)' => ['op' => 'round', 'args' => [$num('2.5')]],
    'round(-2.5)' => ['op' => 'round', 'args' => [$num('-2.5')]],
    'round(1.23456, 3)' => ['op' => 'round', 'args' => [$num('1.23456'), $num('3')]],
    'concat([t], " ", [n], true)' => ['op' => 'concat', 'args' => [$pool['[t]'], $txt(' '), $pool['[n]'], $pool['true']]],
];
foreach (['days', 'months', 'years'] as $unit) {
    foreach ([['[d]', '[d2]'], ['[d2]', '[d]'], ['[d]', '[dbad]'], ['date("2024-01-01")', '[d]']] as [$x, $y]) {
        $extra["date_diff($x,$y,$unit)"] = ['op' => 'date_diff', 'args' => [$pool[$x], $pool[$y], $txt($unit)]];
    }
}
foreach (['add_days', 'add_months'] as $op) {
    foreach (['1', '-1', '12', '400', '100000', '1.5'] as $amount) {
        foreach (['[d]', '[d2]', '[dbad]'] as $x) {
            $extra["$op($x,$amount)"] = ['op' => $op, 'args' => [$pool[$x], $num($amount)]];
        }
    }
}
foreach (['2', '0.0004', '1000000000000', '123456789', '10', '0.5'] as $x) {
    $extra["sqrt($x)"] = ['op' => 'sqrt', 'args' => [$num($x)]];
}
foreach ($extra as $label => $tree) {
    $cases[] = ['label' => $label, 'tree' => $tree];
}

$norm = static function (Value $value) use (&$norm): array {
    if ($value->type === 'empty') {
        return ['t' => 'empty', 'v' => null];
    }
    if ($value->type === 'list') {
        return ['t' => 'list', 'v' => array_map($norm, $value->data)];
    }
    return ['t' => $value->type, 'v' => $value->data];
};
$phpValues = $values + ['__named' => $named, '__today' => $today];
foreach ($cases as $i => $case) {
    $context = Context::fromValues($phpValues, $fields, $today);
    $cases[$i]['want'] = $norm((new Evaluator($context))->evaluate($case['tree']));
}

$meta = array_map(static fn(FormField $f) => ['id' => (string)$f->id, 'name' => $f->variable, 'type' => $f->type], $fields);
$payload = [
    'fields' => $meta,
    'values' => array_map(static fn($v) => $v, $values),
    'today' => $today,
    'scores' => Context::scoreMap($fields),
    'named' => $named,
    'cases' => $cases,
];
$file = tempnam(sys_get_temp_dir(), 'cf-parity-');
file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE));
$node = trim((string)shell_exec('command -v node'));
if ($node === '') {
    fwrite(STDERR, "node not found; parity not checked\n");
    exit(1);
}
passthru(escapeshellarg($node) . ' ' . escapeshellarg(__DIR__ . '/formula_parity.js') . ' ' . escapeshellarg($file), $code);
@unlink($file);
exit($code === 0 ? 0 : 1);
