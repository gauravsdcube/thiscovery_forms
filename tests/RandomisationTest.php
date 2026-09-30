<?php
/**
 * F1. Server draws are stable, block and least-filled stay balanced,
 * a stored order is reused, and a screened-out response is not a complete.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\services\FormPager;
use humhub\modules\thiscoveryForms\services\LogicEngine;
use humhub\modules\thiscoveryForms\services\RandomisationEngine;
use humhub\modules\thiscoveryForms\services\RandomisationService;
use yii\db\Query;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

$engine = new RandomisationEngine();
$first = $engine->shuffle(['a', 'b', 'c', 'd', 'e'], 42);
$check($engine->shuffle(['a', 'b', 'c', 'd', 'e'], 42) === $first, 'the same seed did not repeat the shuffle');
$check($engine->rotate(['a', 'b', 'c'], 1) === ['b', 'c', 'a'], 'rotate offset 1 did not move the first item to the end');

$block = $engine->block([
    ['code' => 'usual', 'weight' => 1],
    ['code' => 'new', 'weight' => 1],
], 4, 7);
$check(count($block) === 4, 'a block of 4 was not 4 long');
$check(count(array_keys($block, 'usual', true)) === 2 && count(array_keys($block, 'new', true)) === 2, 'a 1:1 block was not exact');

$counts = [];
for ($i = 0; $i < 20; $i++) {
    $pick = $engine->leastFilled($counts, ['usual', 'new'], 1000 + $i);
    $counts[$pick] = ($counts[$pick] ?? 0) + 1;
}
$check(abs(($counts['usual'] ?? 0) - ($counts['new'] ?? 0)) <= 1, 'least-filled imbalance was greater than 1');

$drawn = ['usual' => 0, 'new' => 0];
for ($i = 0; $i < 10000; $i++) {
    $code = $engine->weightedPick([
        ['code' => 'usual', 'weight' => 1],
        ['code' => 'new', 'weight' => 1],
    ], $i + 1);
    $drawn[$code] = ($drawn[$code] ?? 0) + 1;
}
$check($drawn['usual'] > 4500 && $drawn['usual'] < 5500 && $drawn['new'] > 4500 && $drawn['new'] < 5500, '10,000 equal-weight draws were outside 45–55%');

$module = Yii::$app->getModule('thiscovery-forms');
$module->settings->set(Module::SETTING_RANDOMISATION, '0');
$module->settings->set(Module::SETTING_OPTION_ORDER, '0');

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV F1 randomisation', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
(new Query())->createCommand()->delete('custom_form_arm_assignment', [
    'answer_id' => (new Query())->select('id')->from('custom_form_answer')->where(['form_id' => (int)$form->id]),
])->execute();
(new Query())->createCommand()->delete('custom_form_presentation', [
    'answer_id' => (new Query())->select('id')->from('custom_form_answer')->where(['form_id' => (int)$form->id]),
])->execute();
(new Query())->createCommand()->delete('custom_form_arm_allocation', ['form_id' => (int)$form->id])->execute();
(new Query())->createCommand()->delete('custom_form_answer_field', [
    'answer_id' => (new Query())->select('id')->from('custom_form_answer')->where(['form_id' => (int)$form->id]),
])->execute();
(new Query())->createCommand()->delete('custom_form_answer', ['form_id' => (int)$form->id])->execute();
ReviewLib::clearFields($form);

$colour = ReviewLib::field($form, FormField::TYPE_RADIO, 'Colour', ['variable' => 'f1_colour', 'sort_order' => 1]);
$colour->setOptionsFromText("Red\nBlue\nGreen\nYellow\nPurple\nOrange", true);
$colour->save(false);

$guestA = new FormAnswer();
$guestA->form_id = (int)$form->id;
$guestA->status = FormAnswer::STATUS_IN_PROGRESS;
$guestA->is_test = 0;
$guestA->forceAnonymous = true;
$guestA->save(false);
$guestB = new FormAnswer();
$guestB->form_id = (int)$form->id;
$guestB->status = FormAnswer::STATUS_IN_PROGRESS;
$guestB->is_test = 0;
$guestB->forceAnonymous = true;
$guestB->save(false);
$form = ReviewLib::reload($form);
$colour = FormField::findOne((int)$colour->id);
$offA = $colour->getShuffledOptions(null, $guestA);
$offB = $colour->getShuffledOptions(null, $guestB);
$check($offA === $offB, 'flag-off guests did not share the user seed order');

$svc = new RandomisationService();
$svc->saveConfig($form, [
    'enabled' => '1',
    'method' => 'block',
    'block_size' => 4,
    'arms' => "usual|Usual leaflet|1\nnew|New leaflet|1",
    'assign' => 'start',
]);
$form->save(false);
$module->settings->set(Module::SETTING_RANDOMISATION, '1');
$form = ReviewLib::reload($form);
$colour = FormField::findOne((int)$colour->id);
$guestA->populateRelation('form', $form);
$guestB->populateRelation('form', $form);
$onA = $colour->getShuffledOptions(null, $guestA);
$again = $colour->getShuffledOptions(null, $guestA);
$onB = $colour->getShuffledOptions(null, $guestB);
$check($onA === $again, 'a second view did not reuse the stored option order');
$check($onA !== $onB, 'two responses shared an option order');

$assigned = [];
for ($i = 0; $i < 8; $i++) {
    $answer = new FormAnswer();
    $answer->form_id = (int)$form->id;
    $answer->status = FormAnswer::STATUS_IN_PROGRESS;
    $answer->is_test = 0;
    $answer->forceAnonymous = true;
    $answer->save(false);
    $answer->populateRelation('form', $form);
    $row = $svc->assignIfDue($answer, [], null, false);
    $assigned[] = (string)($row['arm_code'] ?? '');
}
$usual = count(array_keys($assigned, 'usual', true));
$new = count(array_keys($assigned, 'new', true));
$check($usual === 4 && $new === 4, 'eight block assignments were not 4 and 4');

$svc->saveConfig($form, [
    'enabled' => '1',
    'method' => 'least_filled',
    'arms' => "usual|Usual leaflet|1\nnew|New leaflet|1",
    'assign' => 'start',
]);
$form->save(false);
$form = ReviewLib::reload($form);
(new Query())->createCommand()->delete('custom_form_arm_assignment', [
    'answer_id' => (new Query())->select('id')->from('custom_form_answer')->where(['form_id' => (int)$form->id]),
])->execute();
$least = [];
for ($i = 0; $i < 5; $i++) {
    $answer = new FormAnswer();
    $answer->form_id = (int)$form->id;
    $answer->status = FormAnswer::STATUS_IN_PROGRESS;
    $answer->is_test = 0;
    $answer->forceAnonymous = true;
    $answer->save(false);
    $answer->populateRelation('form', $form);
    $row = $svc->assignIfDue($answer, [], null, false);
    $least[] = (string)($row['arm_code'] ?? '');
}
$check(abs(count(array_keys($least, 'usual', true)) - count(array_keys($least, 'new', true))) <= 1, 'five least-filled assignments were unbalanced');

$testAnswer = new FormAnswer();
$testAnswer->form_id = (int)$form->id;
$testAnswer->status = FormAnswer::STATUS_IN_PROGRESS;
$testAnswer->is_test = 1;
$testAnswer->forceAnonymous = true;
$testAnswer->save(false);
$testAnswer->populateRelation('form', $form);
$check($svc->assignIfDue($testAnswer, [], null, true) === null, 'a test response was assigned an arm');

$anon = ReviewLib::form(review_space(), 'EV F1 anonymous stratum', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
    'identity_mode' => CustomForm::IDENTITY_FULLY_ANONYMOUS,
]);
$svc->saveConfig($anon, [
    'enabled' => '1',
    'method' => 'stratified',
    'strata' => 'panel:site',
    'arms' => "usual|Usual|1\nnew|New|1",
]);
$anon->save(false);
$anon = ReviewLib::reload($anon);
$errors = $svc->authoringErrors($anon);
$check(in_array('A fully anonymous form cannot stratify on a panel attribute.', $errors, true), 'anonymous panel stratum was allowed');

ReviewLib::clearFields($form);
$blockStart = ReviewLib::field($form, FormField::TYPE_RAND_BLOCK, 'Block', [
    'variable' => 'f1_block',
    'sort_order' => 1,
    'options' => json_encode(['blockKey' => 'leaflet', 'randomise' => ['enabled' => true, 'method' => 'shuffle']]),
]);
$inside = ReviewLib::field($form, FormField::TYPE_TEXT, 'Inside', ['variable' => 'f1_inside', 'sort_order' => 2]);
$mid = ReviewLib::field($form, FormField::TYPE_PAGE_BREAK, 'Mid', [
    'variable' => 'f1_mid',
    'sort_order' => 3,
    'options' => json_encode(['__type' => FormField::TYPE_PAGE_BREAK, 'pageKey' => 'mid']),
]);
$inside2 = ReviewLib::field($form, FormField::TYPE_TEXT, 'Inside two', ['variable' => 'f1_inside2', 'sort_order' => 4]);
$end = ReviewLib::field($form, FormField::TYPE_RAND_BLOCK_END, 'Block end', ['variable' => 'f1_block_end', 'sort_order' => 5]);
$after = ReviewLib::field($form, FormField::TYPE_PAGE_BREAK, 'After', [
    'variable' => 'f1_after',
    'sort_order' => 6,
    'options' => json_encode(['__type' => FormField::TYPE_PAGE_BREAK, 'pageKey' => 'after']),
]);
$outside = ReviewLib::field($form, FormField::TYPE_TEXT, 'Outside', ['variable' => 'f1_outside', 'sort_order' => 7]);
$inside->setLogic(LogicEngine::fromFormula('[f1_inside] = "leave"', LogicEngine::ACTION_GOTO_PAGE, 'after'));
$inside->save(false);
$form = ReviewLib::reload($form);
$svc->saveConfig($form, [
    'enabled' => '1',
    'method' => 'simple',
    'arms' => "usual|Usual leaflet|1",
    'assign' => 'start',
]);
$form->save(false);
$form = ReviewLib::reload($form);
$blocked = $svc->authoringErrors($form);
$check((bool)array_filter($blocked, static fn($line) => str_contains($line, 'outside block')), 'an outbound go-to was allowed');

$route = new FormAnswer();
$route->form_id = (int)$form->id;
$route->status = FormAnswer::STATUS_IN_PROGRESS;
$route->is_test = 0;
$route->forceAnonymous = true;
$route->save(false);
$route->populateRelation('form', $form);
$svc->materialise($route);
$pages = $svc->orders($route)['pages']['leaflet'] ?? [];
$check($pages === ['start', 'mid'] || $pages === ['mid', 'start'], 'the block did not order its own pages');
$againPages = $svc->orders($route)['pages']['leaflet'] ?? [];
$check($againPages === $pages, 'the page order changed on the second read');

$screen = ReviewLib::field($form, FormField::TYPE_RADIO, 'Continue', ['variable' => 'f1_continue', 'sort_order' => 8]);
$screen->setOptionsFromText("yes\nno");
$screen->setLogic(LogicEngine::fromFormula('[f1_continue] = "no"', LogicEngine::ACTION_SCREEN_OUT));
$screen->save(false);
$form = ReviewLib::publishOpen($form);
$screened = ReviewLib::submit($form, [(int)$screen->id => 'no']);
$check($screened instanceof FormAnswer, 'screened-out submit did not save');
if ($screened) {
    $screened->refresh();
    $check((string)$screened->outcome === FormAnswer::OUTCOME_SCREENED_OUT, 'screened-out outcome was not stored');
    $check(!$screened->countsAsComplete(), 'a screened-out response counted as complete');
}
$kept = ReviewLib::submit($form, [(int)$screen->id => 'yes']);
$check($kept instanceof FormAnswer && $kept->countsAsComplete(), 'a normal complete was not counted');

$module->settings->set(Module::SETTING_RANDOMISATION, '0');
$module->settings->set(Module::SETTING_OPTION_ORDER, '0');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
