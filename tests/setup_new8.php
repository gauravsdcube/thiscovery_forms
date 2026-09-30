<?php
/**
 * Forms and the routing flag for the NEW-8 browser check.
 * Usage: setup_new8.php create|on|off
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\services\FormActionService;

$mode = $argv[1] ?? 'create';
$module = Yii::$app->getModule('thiscovery-forms');
if ($mode === 'on' || $mode === 'off') {
    $module->settings->set(Module::SETTING_ROUTING_ALIGNMENT, $mode === 'on' ? '1' : '0');
    fwrite(STDOUT, Module::routingAligned() ? "flag-on\n" : "flag-off\n");
    exit(0);
}

ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();

$action = ReviewLib::form($space, 'EV R8 action goto', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
ReviewLib::clearFields($action);
$gate = ReviewLib::field($action, FormField::TYPE_RADIO, 'Gate', [
    'variable' => 'r8_gate',
    'required' => 1,
    'sort_order' => 1,
    'options' => ['yes | Yes', 'no | No'],
]);
$gate->setActions([['fn' => FormActionService::FN_GOTO_PAGE, 'page_key' => 'target', 'template_id' => 0, 'name' => '', 'value' => '']]);
$gate->save(false);
$midBreak = ReviewLib::field($action, FormField::TYPE_PAGE_BREAK, 'To mid', ['sort_order' => 2]);
$midBreak->setPageBreakConfig(['pageKey' => 'mid', 'title' => 'Middle']);
$midBreak->save(false);
ReviewLib::field($action, FormField::TYPE_TEXT, 'Middle question', ['variable' => 'r8_mid', 'required' => 1, 'sort_order' => 3]);
$targetBreak = ReviewLib::field($action, FormField::TYPE_PAGE_BREAK, 'To target', ['sort_order' => 4]);
$targetBreak->setPageBreakConfig(['pageKey' => 'target', 'title' => 'Target']);
$targetBreak->save(false);
ReviewLib::field($action, FormField::TYPE_TEXT, 'Target question', ['variable' => 'r8_target', 'required' => 1, 'sort_order' => 5]);
$action = ReviewLib::publishOpen($action);

$skip = ReviewLib::form($space, 'EV R8 skipped goto', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
ReviewLib::clearFields($skip);
$q = ReviewLib::field($skip, FormField::TYPE_RADIO, 'Gate', [
    'variable' => 'r8s_gate',
    'required' => 1,
    'sort_order' => 1,
    'options' => ['yes | Yes', 'no | No'],
]);
$mb = ReviewLib::field($skip, FormField::TYPE_PAGE_BREAK, 'Mid', ['sort_order' => 2]);
$mb->setPageBreakConfig(['pageKey' => 'mid', 'title' => 'Mid']);
$mb->save(false);
ReviewLib::field($skip, FormField::TYPE_TEXT, 'Mid question', [
    'variable' => 'r8s_mid',
    'required' => 1,
    'sort_order' => 3,
    'logic' => \humhub\modules\thiscoveryForms\services\LogicEngine::fromFormula('[r8s_gate] = "no"'),
]);
$ab = ReviewLib::field($skip, FormField::TYPE_PAGE_BREAK, 'After', ['sort_order' => 4]);
$ab->setPageBreakConfig(['pageKey' => 'after', 'title' => 'After']);
$ab->setLogic(\humhub\modules\thiscoveryForms\services\LogicEngine::fromFormula('[r8s_gate] = "yes"', 'goto_page', 'land'));
$ab->save(false);
ReviewLib::field($skip, FormField::TYPE_TEXT, 'After question', ['variable' => 'r8s_after', 'required' => 1, 'sort_order' => 5]);
$lb = ReviewLib::field($skip, FormField::TYPE_PAGE_BREAK, 'Land', ['sort_order' => 6]);
$lb->setPageBreakConfig(['pageKey' => 'land', 'title' => 'Land']);
$lb->save(false);
ReviewLib::field($skip, FormField::TYPE_TEXT, 'Land question', ['variable' => 'r8s_land', 'required' => 1, 'sort_order' => 7]);
$skip = ReviewLib::publishOpen($skip);

fwrite(STDOUT, 'action=' . (int)$action->id . ' skip=' . (int)$skip->id . "\n");
