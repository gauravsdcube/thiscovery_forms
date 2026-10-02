<?php
/**
 * LOG-10. A page break's "otherwise" target is used when no branch rule matches, a stored
 * "skip" rule is read as "hide", and the otherwise target survives an export/import row.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\FormPager;
use humhub\modules\thiscoveryForms\services\LogicAudit;
use humhub\modules\thiscoveryForms\services\LogicEngine;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV LOG10 otherwise', ['allow_anonymous' => 1]);
ReviewLib::clearFields($form);
$q = ReviewLib::field($form, FormField::TYPE_RADIO, 'Which?', ['variable' => 'which', 'sort_order' => 1, 'options' => ['a | A', 'b | B']]);
$sort = 2;
$break = static function (string $key, array $cfg = []) use ($form, &$sort): FormField {
    $b = ReviewLib::field($form, FormField::TYPE_PAGE_BREAK, 'Break ' . $key, ['sort_order' => $sort++]);
    $b->setPageBreakConfig(['pageKey' => $key, 'title' => $key] + $cfg);
    $b->save(false);
    ReviewLib::field($form, FormField::TYPE_TEXT, 'On ' . $key, ['variable' => 'on_' . $key, 'sort_order' => $sort++]);
    return $b;
};
$first = $break('pa', ['branches' => [['formula' => '[which] = "a"', 'gotoPageKey' => 'pa']], 'otherwise' => 'pc']);
$break('pb');
$break('pc');

$fields = $form->getFields()->all();
$built = (new FormPager())->buildPages($fields);
$pager = new FormPager();
$toA = $pager->resolveNextPage($built['pages'], $built['pageKeyIndex'], 0, [(int)$q->id => 'a'], $fields);
$toC = $pager->resolveNextPage($built['pages'], $built['pageKeyIndex'], 0, [(int)$q->id => 'b'], $fields);
$check($toA === (int)$built['pageKeyIndex']['pa'], 'a matching branch did not win: ' . var_export($toA, true));
$check($toC === (int)$built['pageKeyIndex']['pc'], 'no matching branch did not use otherwise: ' . var_export($toC, true));
$check(LogicAudit::errors($form) === [], 'a valid otherwise target was refused: ' . json_encode(LogicAudit::errors($form)));

$cfg = FormField::findOne((int)$first->id)->getPageBreakConfig();
$check($cfg['otherwise'] === 'pc', 'otherwise was not stored');
$row = FormField::findOne((int)$first->id)->toPostRow();
$check(($row['page_otherwise'] ?? null) === 'pc', 'otherwise was not exported');

$first->setPageBreakConfig(['pageKey' => 'pa', 'title' => 'pa', 'otherwise' => 'nowhere']);
$first->save(false);
$check((bool)array_filter(LogicAudit::errors($form), static fn($e) => str_contains($e, '“nowhere”')), 'a missing otherwise target was not refused');

$skip = LogicEngine::normalize(['action' => 'skip', 'when' => ['op' => 'lit', 'lit' => 'bool', 'v' => true]]);
$check($skip['action'] === LogicEngine::ACTION_HIDE, 'a stored skip rule was not read as hide');
$check(!isset(LogicEngine::actionLabels()[LogicEngine::ACTION_SKIP]), 'skip is still offered next to hide');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
