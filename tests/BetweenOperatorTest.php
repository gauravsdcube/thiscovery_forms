<?php
/**
 * The between operator is inclusive and uses a min,max value.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\LogicEngine;

$failures = [];
$engine = new LogicEngine();

$check = static function (string $id, $raw, string $expected, bool $want) use ($engine, &$failures): void {
    $bounds = array_map('trim', explode(',', $expected));
    $got = $engine->evaluateRule(
        LogicEngine::fromFormula('between([q], ' . $bounds[0] . ', ' . $bounds[1] . ')'),
        ['q' => $raw]
    );
    if ($got !== $want) {
        $failures[] = $id . ' got ' . ($got ? 'true' : 'false');
    }
};

$check('inside', '5', '1,10', true);
$check('low edge', '1', '1,10', true);
$check('high edge', '10', '1, 10', true);
$check('below', '0', '1,10', false);
$check('text', '10 years', '1,10', false);
$check('reversed', '5', '10,1', true);
$check('list hit', ['1', '4'], '3,6', true);
$check('list miss', ['1', '2'], '3,6', false);

$number = new FormField();
$number->id = 1;
$number->type = FormField::TYPE_NUMBER;
$number->variable = 'q';
$number->label = 'q';
$detail = new FormField();
$detail->id = 2;
$detail->type = FormField::TYPE_TEXT;
$detail->variable = 'detail';
$detail->label = 'detail';
$detail->setLogic(LogicEngine::fromFormula('between([q], 1, 10)'));
$fields = [$number, $detail];
$visible = [];
foreach ($fields as $field) {
    if ($engine->isFieldVisible($field, $fields, [1 => '5'])) {
        $visible[] = (int)$field->id;
    }
}
if ($visible !== [1, 2]) {
    $failures[] = 'between did not show the next question ' . json_encode($visible);
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
