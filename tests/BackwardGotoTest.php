<?php
/**
 * NEW-17. A go-to that points backward is rejected in the studio and, at
 * runtime, continues forward instead of looping.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\FormPager;

$failures = [];
ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV R17 backward goto', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
ReviewLib::clearFields($form);
$saved = $form->saveFieldsFromPost([
    'a' => [
        'type' => FormField::TYPE_TEXT,
        'label' => 'Start question',
        'variable' => 'r17_a',
        'sort_order' => 1,
    ],
    'br' => [
        'type' => FormField::TYPE_PAGE_BREAK,
        'label' => 'To later',
        'sort_order' => 2,
        'page_key' => 'later',
    ],
    'b' => [
        'type' => FormField::TYPE_RADIO,
        'label' => 'Gate',
        'variable' => 'r17_gate',
        'sort_order' => 3,
        'options' => ['yes', 'no'],
        'logic_action' => 'goto_page',
        'logic_goto' => 'start',
        'logic_rules' => [[
            'fieldKey' => 'a',
            'operator' => 'equals',
            'value' => 'yes',
        ]],
    ],
]);
if ($saved) {
    $failures[] = 'a backward go-to was saved';
}
if (!str_contains(implode(' ', $form->getErrorSummary(true)), 'earlier page')) {
    $failures[] = 'validation did not mention the earlier page';
}

$start = new FormField();
$start->id = 1;
$start->type = FormField::TYPE_TEXT;
$start->label = 'Start';
$break = new FormField();
$break->id = 2;
$break->type = FormField::TYPE_PAGE_BREAK;
$break->setPageBreakConfig(['pageKey' => 'later', 'title' => 'Later']);
$gate = new FormField();
$gate->id = 3;
$gate->type = FormField::TYPE_RADIO;
$gate->label = 'Gate';
$gate->setActions([['fn' => 'goto_page', 'page_key' => 'start']]);
$pager = new FormPager();
$built = $pager->buildPages([$start, $break, $gate]);
$next = $pager->resolveNextPage($built['pages'], $built['pageKeyIndex'], 1, [3 => 'yes'], [$start, $break, $gate]);
if ($next === 0) {
    $failures[] = 'runtime followed the backward go-to';
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
