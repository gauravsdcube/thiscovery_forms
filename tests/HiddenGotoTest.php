<?php
/**
 * LOG-8. A go-to or skip-page rule on a question the respondent can't see (here, inside a
 * hidden group) doesn't route them anywhere.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\LogicEngine;

$failures = [];
ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV LOG8 hidden goto', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
ReviewLib::clearFields($form);

$gate = ReviewLib::field($form, FormField::TYPE_RADIO, 'Show the group?', [
    'variable' => 'showgrp',
    'sort_order' => 1,
    'options' => ['yes | Yes', 'no | No'],
]);
$group = ReviewLib::field($form, FormField::TYPE_QUESTION_GROUP, 'Group', [
    'sort_order' => 2,
    'logic' => LogicEngine::fromFormula('[showgrp] = "yes"'),
]);
$router = ReviewLib::field($form, FormField::TYPE_RADIO, 'Route', [
    'variable' => 'route',
    'sort_order' => 3,
    'options' => ['go | Go', 'stay | Stay'],
    'logic' => LogicEngine::fromFormula('[route] = "go"', 'goto_page', 'land'),
]);
$skipper = ReviewLib::field($form, FormField::TYPE_RADIO, 'Skip', [
    'variable' => 'skipme',
    'sort_order' => 4,
    'options' => ['y | Y', 'n | N'],
    'logic' => LogicEngine::fromFormula('[route] = "go"', 'skip_page'),
]);
$end = ReviewLib::field($form, FormField::TYPE_GROUP_END, '', ['sort_order' => 5]);
$fields = array_map(static fn(FormField $f) => FormField::findOne((int)$f->id), [$gate, $group, $router, $skipper, $end]);

$engine = new LogicEngine();
LogicEngine::resetCaches();
$hidden = [(int)$gate->id => 'no', (int)$router->id => 'go'];
$shown = [(int)$gate->id => 'yes', (int)$router->id => 'go'];

if ($engine->pageNavigation($fields, $hidden, $fields) !== null) {
    $failures[] = 'a go-to inside a hidden group still routed the respondent';
}
$nav = $engine->pageNavigation($fields, $shown, $fields);
if (($nav['gotoPageKey'] ?? '') !== 'land') {
    $failures[] = 'a go-to on a shown question did not route: ' . json_encode($nav);
}
// The gate question is visible on this page, so only the skip rule can skip it.
if ($engine->shouldSkipPage($fields, $hidden, $fields)) {
    $failures[] = 'a skip rule inside a hidden group skipped the page';
}
if (!$engine->shouldSkipPage($fields, $shown, $fields)) {
    $failures[] = 'a skip rule on a shown question did not skip the page';
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
