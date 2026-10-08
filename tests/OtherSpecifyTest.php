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
$check($radio->getOpenEndOptions() === ['other'], 'A choice named Other is open-ended without a new setting.');

$box = new FormField();
$box->type = FormField::TYPE_CHECKBOX;
$box->setOptionsFromText([
    ['code' => '1', 'label' => 'Mortuary', 'open_end' => '0', 'open_end_required' => '0', 'exclusive' => '0'],
    ['code' => '6', 'label' => 'Something else', 'open_end' => '1', 'open_end_required' => '1', 'exclusive' => '0'],
    ['code' => '7', 'label' => 'None of the above', 'open_end' => '0', 'open_end_required' => '0', 'exclusive' => '1'],
], false, null, 'left-over');
$check($box->getOpenEndOptions() === ['6'], 'Open-ended follows the ticked choice, not the word Other.');
$check($box->getExclusiveOptions() === ['7'], 'Exclusive follows the ticked choice.');
$check($box->otherSpecifyIncomplete(['6']), 'A ticked open-ended choice still needs its text.');
$check($box->otherSpecifyIncomplete(['7']) === false, 'An exclusive choice does not ask for extra text.');
$check($box->allowsChoiceValue('6: notes'), 'Typed text on a ticked choice is a valid answer.');

$kept = new FormField();
$kept->type = FormField::TYPE_CHECKBOX;
$kept->options_json = json_encode([
    'options' => [
        ['code' => '1', 'label' => 'Yes'],
        ['code' => '9', 'label' => 'None of these'],
    ],
    'exclusiveOption' => '9',
    'otherSpecify' => false,
], JSON_UNESCAPED_UNICODE);
$check($kept->getExclusiveOptions() === ['9'], 'An existing exclusive choice is still exclusive.');
$check($kept->getOpenEndOptions() === [], 'Turning off extra text stays off when no choice is named Other.');

$comma = new FormField();
$comma->type = FormField::TYPE_CHECKBOX;
$comma->options_json = json_encode([
    'options' => [
        ['code' => '1', 'label' => 'All sections'],
        ['code' => '12', 'label' => 'Unsure'],
        ['code' => '13', 'label' => 'None of the above'],
        ['code' => '14', 'label' => 'Other'],
    ],
    'exclusiveOption' => '1,12,13',
], JSON_UNESCAPED_UNICODE);
$check($comma->getExclusiveOptions() === ['1', '12', '13'], 'A comma-separated exclusive list still matches the choice codes.');

if ($failures) {
    fwrite(STDERR, count($failures) . " failed\n");
    exit(1);
}
fwrite(STDOUT, "PASS\n");
