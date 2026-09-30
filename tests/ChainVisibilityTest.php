<?php
/**
 * LOG-4. A hidden question's answer does not satisfy a later show rule.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\LogicEngine;

$failures = [];

$field = static function (int $id, array $logic = []): FormField {
    $f = new FormField();
    $f->id = $id;
    $f->type = FormField::TYPE_RADIO;
    $f->variable = 'v' . $id;
    $f->label = 'v' . $id;
    if ($logic) {
        $f->setLogic($logic);
    }
    return $f;
};

$showIf = static function (int $source, string $value): array {
    return LogicEngine::fromFormula('[v' . $source . '] = "' . $value . '"');
};

$a = $field(1);
$b = $field(2, $showIf(1, 'yes'));
$c = $field(3, $showIf(2, 'yes'));
$fields = [$a, $b, $c];
$engine = new LogicEngine();
$visible = [];
foreach ($fields as $one) {
    if ($engine->isFieldVisible($one, $fields, [1 => 'no', 2 => 'yes', 3 => 'secret'])) {
        $visible[] = (int)$one->id;
    }
}
if ($visible !== [1]) {
    $failures[] = 'hidden answer still opened the next question ' . json_encode($visible);
}

$shown = [];
foreach ($fields as $one) {
    if ($engine->isFieldVisible($one, $fields, [1 => 'yes', 2 => 'yes', 3 => 'kept'])) {
        $shown[] = (int)$one->id;
    }
}
if ($shown !== [1, 2, 3]) {
    $failures[] = 'a shown chain was cleared ' . json_encode($shown);
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
