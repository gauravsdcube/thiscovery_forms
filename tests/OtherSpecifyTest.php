<?php
/**
 * Other text is required unless the question says otherwise.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        fwrite(STDERR, "FAIL $message\n");
    }
};

$options = json_encode([
    'options' => [
        ['code' => '1', 'label' => 'New'],
        ['code' => 'other', 'label' => 'Other'],
    ],
], JSON_UNESCAPED_UNICODE);

$radio = new FormField();
$radio->type = FormField::TYPE_RADIO;
$radio->options_json = $options;
$check($radio->allowsOtherSpecify(), 'Radio shows the Other box by default.');
$check($radio->requiresOtherText(), 'Extra text is required unless turned off.');
$check($radio->otherSpecifyIncomplete('other'), 'A bare Other answer is incomplete when text is required.');

$radio->setRequiresOtherText(false);
$check(!$radio->requiresOtherText(), 'Extra text can be left optional.');
$check(!$radio->otherSpecifyIncomplete('other'), 'A bare Other answer is complete when text is optional.');
$check($radio->otherSpecifyIncomplete('other: notes') === false, 'Typed Other text stays complete.');

if ($failures) {
    fwrite(STDERR, count($failures) . " failed\n");
    exit(1);
}
fwrite(STDOUT, "PASS\n");
