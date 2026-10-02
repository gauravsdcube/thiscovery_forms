<?php
/**
 * DAT-9. A choice added in the studio with no code gets a fixed numeric code, so fixing a
 * typo in its label later does not change the code that answers and rules use.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV DAT9 codes', ['allow_anonymous' => 1]);
ReviewLib::clearFields($form);
$field = ReviewLib::field($form, FormField::TYPE_RADIO, 'Colour', ['variable' => 'colour']);
$field->setOptionsFromText([['code' => '', 'label' => 'Red'], ['code' => '', 'label' => 'Bleu']]);
$field->save(false);
$pairs = FormField::findOne((int)$field->id)->getChoicePairs();
$check(array_column($pairs, 'code') === ['1', '2'], 'new options did not get fixed numeric codes: ' . json_encode($pairs));

// The studio posts the stored code back with the corrected label.
$field = FormField::findOne((int)$field->id);
$field->setOptionsFromText([['code' => '1', 'label' => 'Red'], ['code' => '2', 'label' => 'Blue'], ['code' => '', 'label' => 'Green']]);
$field->save(false);
$pairs = FormField::findOne((int)$field->id)->getChoicePairs();
$check(array_column($pairs, 'code') === ['1', '2', '3'], 'a relabel changed a code, or a new option reused one: ' . json_encode($pairs));
$check(array_column($pairs, 'label') === ['Red', 'Blue', 'Green'], 'labels were not saved');

// Removing an option and adding another never hands its number to a different answer.
$field = FormField::findOne((int)$field->id);
$field->setOptionsFromText([['code' => '1', 'label' => 'Red'], ['code' => '3', 'label' => 'Green'], ['code' => '', 'label' => 'Purple']]);
$field->save(false);
$codes = array_column(FormField::findOne((int)$field->id)->getChoicePairs(), 'code');
$check(!in_array('2', array_slice($codes, 2), true) && end($codes) === '4', 'a removed option\'s code was reused: ' . json_encode($codes));

// Removing the highest-numbered option does not free its number either.
$field = FormField::findOne((int)$field->id);
$field->setOptionsFromText([['code' => '1', 'label' => 'Red'], ['code' => '3', 'label' => 'Green'], ['code' => '', 'label' => 'Orange']]);
$field->save(false);
$codes = array_column(FormField::findOne((int)$field->id)->getChoicePairs(), 'code');
$check(end($codes) === '5', 'the removed highest code was handed out again: ' . json_encode($codes));

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
