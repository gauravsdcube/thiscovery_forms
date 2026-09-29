<?php
/**
 * HB-9. Per-response option order is off unless the flag is on.
 * When it is on, exclusive and Other stay put, and the order shown is stored.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\Module;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

$module = Yii::$app->getModule('thiscovery-forms');
$module->settings->set(Module::SETTING_OPTION_ORDER, '0');
$check(!Module::optionOrderPerResponse(), 'option order flag defaults on');

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV HB9 order', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
ReviewLib::clearFields($form);
$field = ReviewLib::field($form, FormField::TYPE_RADIO, 'Colour', ['variable' => 'colour']);
$field->setOptionsFromText("Red\nBlue\nGreen\nYellow\nNone\nOther", true, null, 'None');
$field->save(false);
$form = ReviewLib::publishOpen($form);
$field = FormField::findOne((int)$field->id);

$answer = new FormAnswer();
$answer->form_id = (int)$form->id;
$answer->status = FormAnswer::STATUS_IN_PROGRESS;
$answer->is_test = 0;
$answer->resume_code = 'hb9resume';
$answer->save(false);

FormField::storeOptionOrder($form, $answer);
$answer->refresh();
$flagOffVars = json_decode((string)$answer->vars_json, true);
$check(!is_array($flagOffVars) || !isset($flagOffVars['option_order']), 'flag-off stored an option order');

$module->settings->set(Module::SETTING_OPTION_ORDER, '1');
$shown = $field->getShuffledOptions(null, $answer);
$pinnedAtEnd = true;
$seenPinned = false;
foreach ($shown as $code) {
    $pinned = $code === 'None' || $code === 'Other';
    if ($pinned) {
        $seenPinned = true;
    } elseif ($seenPinned) {
        $pinnedAtEnd = false;
    }
}
$check($pinnedAtEnd && $seenPinned, 'exclusive and Other were shuffled into the list');

FormField::storeOptionOrder(ReviewLib::reload($form), $answer);
$answer->refresh();
$decodedVars = json_decode((string)$answer->vars_json, true);
$stored = is_array($decodedVars) ? ($decodedVars['option_order'][(string)$field->id] ?? null) : null;
$check($stored === $shown, 'the stored order is not the order shown');
$check($field->getShuffledOptions(null, $answer) === $shown, 'a later view did not reuse the stored order');

$module->settings->set(Module::SETTING_OPTION_ORDER, '0');
$answer->delete();

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
