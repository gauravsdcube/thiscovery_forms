<?php
/**
 * V3-7. Editing a completed response on a quota form (without randomisation) never
 * changes the counter and never turns a counted complete into over-quota.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\services\QuotaService;
use yii\db\Query;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

$svc = new QuotaService();
$module = Yii::$app->getModule('thiscovery-forms');
$module->settings->set(Module::SETTING_RANDOMISATION, '0');
$module->settings->set(Module::SETTING_QUOTAS, '1');
ReviewLib::asUser(review_user('review_netadmin'));

$form = ReviewLib::form(review_space(), 'EV V3-7 quota edit', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
    'identity_mode' => CustomForm::IDENTITY_IDENTIFIED,
]);
$ids = (new Query())->select('id')->from('custom_form_quota')->where(['form_id' => (int)$form->id])->column();
if ($ids) {
    foreach (['custom_form_quota_i18n', 'custom_form_quota_accept', 'custom_form_quota_audit', 'custom_form_quota_reservation', 'custom_form_quota_counter'] as $table) {
        Yii::$app->db->createCommand()->delete($table, ['quota_id' => $ids])->execute();
    }
    Yii::$app->db->createCommand()->delete('custom_form_quota', ['id' => $ids])->execute();
}
$answerIds = FormAnswer::find()->select('id')->where(['form_id' => (int)$form->id])->column();
if ($answerIds) {
    Yii::$app->db->createCommand()->delete('custom_form_answer_field', ['answer_id' => $answerIds])->execute();
    FormAnswer::deleteAll(['id' => $answerIds]);
}
$form->setSetting('quotas_enabled', '1');
$form->save(false);
ReviewLib::clearFields($form);
$age = ReviewLib::field($form, FormField::TYPE_NUMBER, 'Age', ['variable' => 'age', 'required' => 1]);
$note = ReviewLib::field($form, FormField::TYPE_TEXT, 'Note', ['variable' => 'note']);
$form = ReviewLib::publishOpen($form);
$quota = $svc->saveQuota($form, null, ['name' => 'Adults', 'target' => 2, 'rules' => '[age] >= 18', 'action' => 'end', 'action_message' => 'Full.']);
$accepted = static fn(): int => (int)(new Query())->select('accepted')->from('custom_form_quota_counter')->where(['quota_id' => (int)$quota['id']])->scalar();

$first = ReviewLib::submit($form, [(int)$age->id => '30']);
$check($first && $first->countsAsComplete(), 'the first response was not accepted');
$check((string)$first->outcome === FormAnswer::OUTCOME_COMPLETE, 'a completed response did not record outcome=complete');
$check($accepted() === 1, 'counter after one complete is ' . $accepted());

// Edit the completed response three times.
for ($i = 0; $i < 3; $i++) {
    $first = FormAnswer::findOne((int)$first->id);
    $edited = ReviewLib::submit($form, [(int)$age->id => '31', (int)$note->id => 'edit ' . $i], false, $first);
    $check($edited !== null, 'the edit failed');
}
$check($accepted() === 1, 'editing a complete changed the counter to ' . $accepted());

// Fill the quota, then edit the first response again: it must stay complete.
$second = ReviewLib::submit($form, [(int)$age->id => '40']);
$check($accepted() === 2, 'counter after two completes is ' . $accepted());
$first = FormAnswer::findOne((int)$first->id);
ReviewLib::submit($form, [(int)$age->id => '32'], false, $first);
$first = FormAnswer::findOne((int)$first->id);
$check((string)$first->outcome !== FormAnswer::OUTCOME_OVER_QUOTA && $first->countsAsComplete(), 'editing a counted complete turned it into over-quota');
$check($accepted() === 2, 'counter after editing at target is ' . $accepted());

// A third new response is over quota.
$third = ReviewLib::submit($form, [(int)$age->id => '50']);
$check($third && (string)$third->outcome === FormAnswer::OUTCOME_OVER_QUOTA, 'a response past the target was not over quota');

$module->settings->set(Module::SETTING_QUOTAS, '0');
if ($failures) {
    exit(1);
}
echo "PASS\n";
