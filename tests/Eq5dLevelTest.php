<?php

/**
 * SCO-11. An EQ-5D level is the option code, not the position in the list.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\Eq5dService;

$failures = [];
$service = new Eq5dService();

function eq5d_field(array $options): FormField
{
    $field = new FormField();
    $field->type = FormField::TYPE_RADIO;
    $field->setOptionsFromText($options);
    return $field;
}

$forward = eq5d_field(['1 | No problems', '2 | Slight', '3 | Moderate', '4 | Severe', '5 | Extreme', '9 | Missing']);
if ($service->dimensionLevel($forward, '5') !== 5) {
    $failures[] = 'code 5';
}
if ($service->dimensionLevel($forward, '9') !== Eq5dService::MISSING_DIMENSION) {
    $failures[] = 'code 9';
}
if ($service->dimensionLevel($forward, '6') !== Eq5dService::MISSING_DIMENSION) {
    $failures[] = 'code 6';
}

$reversed = eq5d_field(['5 | Extreme', '1 | No problems']);
if ($service->dimensionLevel($reversed, '5') !== 5) {
    $failures[] = 'reversed code 5 was ' . $service->dimensionLevel($reversed, '5');
}
if ($service->dimensionLevel($reversed, 'Extreme') !== 5) {
    $failures[] = 'label Extreme';
}

if ($failures) {
    fwrite(STDERR, "FAIL\n" . implode("\n", $failures) . "\n");
    exit(1);
}
fwrite(STDOUT, "PASS\n");
