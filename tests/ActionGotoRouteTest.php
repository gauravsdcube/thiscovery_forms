<?php
/**
 * HF-6. A go-to action is part of the stored route.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\thiscoveryForms\services\FormActionService;
use yii\db\Query;

$failures = [];
$admin = review_user('review_netadmin');
ReviewLib::asUser($admin);
$form = ReviewLib::form(review_space(), 'EV HF6 action goto', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
(new Query())->createCommand()->delete('custom_form_answer_field', ['answer_id' => (new Query())->select('id')->from('custom_form_answer')->where(['form_id' => (int)$form->id])])->execute();
(new Query())->createCommand()->delete('custom_form_answer', ['form_id' => (int)$form->id])->execute();
ReviewLib::clearFields($form);

$gate = ReviewLib::field($form, FormField::TYPE_RADIO, 'Gate', [
    'variable' => 'gate',
    'required' => 1,
    'sort_order' => 1,
    'options' => ['yes | Yes', 'no | No'],
]);
$gate->setActions([['fn' => FormActionService::FN_GOTO_PAGE, 'page_key' => 'target', 'template_id' => 0, 'name' => '', 'value' => '']]);
$gate->save(false);
$midBreak = ReviewLib::field($form, FormField::TYPE_PAGE_BREAK, 'To mid', ['sort_order' => 2]);
$midBreak->setPageBreakConfig(['pageKey' => 'mid', 'title' => 'Middle']);
$midBreak->save(false);
$mid = ReviewLib::field($form, FormField::TYPE_TEXT, 'Skipped', ['variable' => 'mid', 'required' => 1, 'sort_order' => 3]);
$targetBreak = ReviewLib::field($form, FormField::TYPE_PAGE_BREAK, 'To target', ['sort_order' => 4]);
$targetBreak->setPageBreakConfig(['pageKey' => 'target', 'title' => 'Target']);
$targetBreak->save(false);
$jumped = ReviewLib::field($form, FormField::TYPE_TEXT, 'Jumped', ['variable' => 'jumped', 'required' => 1, 'sort_order' => 5]);
$form = ReviewLib::publishOpen($form);

ReviewLib::asUser(null);
$submit = new SubmitForm();
$submit->form = ReviewLib::reload($form);
$submit->values = [(int)$gate->id => 'yes', (int)$jumped->id => 'kept-on-target'];
$answer = $submit->save(null, true, false, false);
if (!$answer) {
    $failures[] = 'submit failed ' . json_encode($submit->getErrorSummary(true));
} else {
    $rows = (new Query())->from('custom_form_answer_field')->where(['answer_id' => (int)$answer->id])->all();
    $byField = [];
    foreach ($rows as $row) {
        $byField[(int)$row['field_id']] = (string)$row['value'];
    }
    if (($byField[(int)$jumped->id] ?? '') !== 'kept-on-target') {
        $failures[] = 'jumped answer was not stored';
    }
    if (isset($byField[(int)$mid->id])) {
        $failures[] = 'skipped page was stored';
    }
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
