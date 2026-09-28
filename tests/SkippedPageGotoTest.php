<?php
/**
 * HF-5. A skipped page's go-to is part of the stored route.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use yii\db\Query;

$failures = [];
$admin = review_user('review_netadmin');
ReviewLib::asUser($admin);
$form = ReviewLib::form(review_space(), 'EV HF5 skipped goto', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
(new Query())->createCommand()->delete('custom_form_answer_field', ['answer_id' => (new Query())->select('id')->from('custom_form_answer')->where(['form_id' => (int)$form->id])])->execute();
(new Query())->createCommand()->delete('custom_form_answer', ['form_id' => (int)$form->id])->execute();
ReviewLib::clearFields($form);

$q = ReviewLib::field($form, FormField::TYPE_RADIO, 'Gate', [
    'variable' => 'gate',
    'required' => 1,
    'sort_order' => 1,
    'options' => ['yes | Yes', 'no | No'],
]);
$midBreak = ReviewLib::field($form, FormField::TYPE_PAGE_BREAK, 'Mid', ['sort_order' => 2]);
$midBreak->setPageBreakConfig(['pageKey' => 'mid', 'title' => 'Mid']);
$midBreak->save(false);
$mid = ReviewLib::field($form, FormField::TYPE_TEXT, 'Mid question', [
    'variable' => 'midq',
    'required' => 1,
    'sort_order' => 3,
    'logic' => ['action' => 'show', 'combinator' => 'and', 'rules' => [['fieldKey' => (string)$q->id, 'operator' => 'equals', 'value' => 'no']]],
]);
$afterBreak = ReviewLib::field($form, FormField::TYPE_PAGE_BREAK, 'After', ['sort_order' => 4]);
$afterBreak->setPageBreakConfig(['pageKey' => 'after', 'title' => 'After']);
$afterBreak->setLogic([
    'action' => 'goto_page',
    'combinator' => 'and',
    'gotoPageKey' => 'land',
    'rules' => [['fieldKey' => (string)$q->id, 'operator' => 'equals', 'value' => 'yes']],
]);
$afterBreak->save(false);
$after = ReviewLib::field($form, FormField::TYPE_TEXT, 'After question', ['variable' => 'afterq', 'required' => 1, 'sort_order' => 5]);
$landBreak = ReviewLib::field($form, FormField::TYPE_PAGE_BREAK, 'Land', ['sort_order' => 6]);
$landBreak->setPageBreakConfig(['pageKey' => 'land', 'title' => 'Land']);
$landBreak->save(false);
$land = ReviewLib::field($form, FormField::TYPE_TEXT, 'Land question', ['variable' => 'landq', 'required' => 1, 'sort_order' => 7]);
$form = ReviewLib::publishOpen($form);

ReviewLib::asUser(null);
$submit = new SubmitForm();
$submit->form = ReviewLib::reload($form);
$submit->values = [(int)$q->id => 'yes', (int)$land->id => 'landed'];
$answer = $submit->save(null, true, false, false);
if (!$answer) {
    $failures[] = 'submit failed ' . json_encode($submit->getErrorSummary(true));
} else {
    $rows = (new Query())->from('custom_form_answer_field')->where(['answer_id' => (int)$answer->id])->all();
    $byField = [];
    foreach ($rows as $row) {
        $byField[(int)$row['field_id']] = (string)$row['value'];
    }
    if (($byField[(int)$land->id] ?? '') !== 'landed') {
        $failures[] = 'land answer missing';
    }
    if (isset($byField[(int)$mid->id]) || isset($byField[(int)$after->id])) {
        $failures[] = 'skipped pages were stored';
    }
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
