<?php
/**
 * HF-4. A failed answer-row insert rolls the whole submission back.
 * Run: sudo -u www-data php /home/admin/thiscovery-review/bin/boot.php \
 *   /var/www/humhub/protected/modules/thiscovery-forms/tests/SubmitTransactionTest.php
 */
require '/home/admin/thiscovery-review/fixtures/evidence_support.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use yii\db\Query;

$failures = [];
$admin = review_user('review_netadmin');
ReviewLib::asUser($admin);
$space = review_space();
$form = ReviewLib::form($space, 'EV HF4 transaction', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
(new Query())->createCommand()->delete('custom_form_answer_field', ['answer_id' => (new Query())->select('id')->from('custom_form_answer')->where(['form_id' => (int)$form->id])])->execute();
(new Query())->createCommand()->delete('custom_form_answer', ['form_id' => (int)$form->id])->execute();
ReviewLib::clearFields($form);
$a = ReviewLib::field($form, FormField::TYPE_TEXT, 'First', ['variable' => 'hf4_a', 'required' => 1, 'sort_order' => 1]);
$b = ReviewLib::field($form, FormField::TYPE_TEXT, 'Second', ['variable' => 'hf4_b', 'required' => 1, 'sort_order' => 2]);
$form = ReviewLib::publishOpen($form);

$mailBefore = json_decode((string)@file_get_contents('http://127.0.0.1:8025/api/v1/messages'), true);
$beforeTotal = (int)($mailBefore['total'] ?? -1);

$db = Yii::$app->db;
$db->createCommand('DROP TRIGGER IF EXISTS review_hf4_fail')->execute();
$db->createCommand(
    'CREATE TRIGGER review_hf4_fail BEFORE INSERT ON custom_form_answer_field FOR EACH ROW BEGIN IF NEW.field_id = '
    . (int)$b->id . ' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'review injected failure\'; END IF; END'
)->execute();

ReviewLib::asUser(null);
$submit = new SubmitForm();
$submit->form = $form;
$submit->values = [(int)$a->id => 'kept', (int)$b->id => 'lost'];
$saved = $submit->save(null, true, false, false);
$db->createCommand('DROP TRIGGER IF EXISTS review_hf4_fail')->execute();

if ($saved !== null) {
    $failures[] = 'failed insert still returned an answer';
}
$left = (new Query())->from('custom_form_answer')->where(['form_id' => (int)$form->id])->count();
if ((int)$left !== 0) {
    $failures[] = 'rolled-back answer rows left=' . $left;
}
$mailAfter = json_decode((string)@file_get_contents('http://127.0.0.1:8025/api/v1/messages'), true);
if ($beforeTotal >= 0 && (int)($mailAfter['total'] ?? -1) !== $beforeTotal) {
    $failures[] = 'mail was sent on rollback';
}

$form = ReviewLib::reload($form);
$ok = new SubmitForm();
$ok->form = $form;
$ok->values = [(int)$a->id => 'one', (int)$b->id => 'two'];
$answer = $ok->save(null, true, false, false);
$rows = $answer ? (new Query())->from('custom_form_answer_field')->where(['answer_id' => (int)$answer->id])->count() : 0;
if (!$answer || (int)$answer->status !== \humhub\modules\thiscoveryForms\models\FormAnswer::STATUS_COMPLETE || (int)$rows !== 2) {
    $failures[] = 'normal submit did not store both rows';
}
if ($answer && $answer->resume_code) {
    $failures[] = 'complete answer kept a resume code';
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
