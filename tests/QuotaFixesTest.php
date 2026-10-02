<?php
/**
 * V3-47. Quota saves keep the status and audit rule changes; the allocation CSV works;
 * a reinstated response takes its quota place back.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormIntegrityMeta;
use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\services\ExportService;
use humhub\modules\thiscoveryForms\services\integrity\IntegrityService;
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
$module->settings->set(Module::SETTING_QUOTAS, '1');
ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV V3-47 quota fixes', [
    'allow_anonymous' => 1, 'allow_multiple' => 1, 'identity_mode' => CustomForm::IDENTITY_IDENTIFIED,
]);
$form->setSetting('quotas_enabled', '1');
$form->save(false);
ReviewLib::clearFields($form);
$age = ReviewLib::field($form, FormField::TYPE_NUMBER, 'Age', ['variable' => 'age47', 'required' => 1]);
$form = ReviewLib::publishOpen($form);
$quota = $svc->saveQuota($form, null, [
    'name' => 'Adults 47', 'target' => 5, 'rules' => '[age47] >= 18', 'action' => 'end',
    'count_policy' => 'complete_excluding_integrity',
]);
$qid = (int)$quota['id'];
$accepted = static fn(): int => (int)(new Query())->select('accepted')->from('custom_form_quota_counter')->where(['quota_id' => $qid])->scalar();
$base = ['name' => 'Adults 47', 'target' => 5, 'rules' => '[age47] >= 18', 'action' => 'end', 'count_policy' => 'complete_excluding_integrity'];

// A save without a status keeps a closed quota closed.
$svc->setStatus($form, $qid, 'closed');
$svc->saveQuota($form, $qid, $base);
$check((string)$svc->quota($qid, (int)$form->id)['status'] === 'closed', 'a save without a status reopened a closed quota');
$svc->setStatus($form, $qid, 'open');

$answer = ReviewLib::submit($form, [(int)$age->id => '30']);
$check($answer && $accepted() === 1, 'the response was not counted');

// Once someone is counted, changing the rules needs a reason and is audited.
$check($svc->saveQuota($form, $qid, ['rules' => '[age47] >= 21'] + $base) === null, 'a mid-fieldwork rule change saved without a reason');
$check($svc->saveQuota($form, $qid, ['rules' => '[age47] >= 21', 'reason' => 'Protocol amendment 2'] + $base) !== null, 'a rule change with a reason was refused');
$audit = (new Query())->from('custom_form_quota_audit')->where(['quota_id' => $qid])->orderBy(['id' => SORT_DESC])->one();
$check($audit && str_contains((string)$audit['change_json'], 'rules_json') && str_contains((string)$audit['change_json'], 'Protocol amendment 2'), 'the rule change was not audited');

// The allocation CSV no longer throws.
try {
    $csv = (new ExportService())->allocationCsv($form);
    $check(str_starts_with($csv, 'answer_id,arm_code'), 'the allocation CSV has no header');
} catch (\Throwable $e) {
    $check(false, 'the allocation CSV threw: ' . $e->getMessage());
}

// Excluded on integrity: released. Reinstated: counted again.
if ($answer) {
    $meta = FormIntegrityMeta::findOne(['answer_id' => (int)$answer->id]);
    if (!$meta) {
        $meta = new FormIntegrityMeta();
        $meta->answer_id = (int)$answer->id;
        $meta->form_id = (int)$form->id;
        $meta->save(false);
    }
    $integrity = new IntegrityService();
    $integrity->overrideStatus($form, $meta, FormIntegrityMeta::STATUS_EXCLUDED, FormIntegrityMeta::ANALYSIS_EXCLUDED, 'duplicate');
    $check($accepted() === 0, 'excluding did not release the place');
    $meta->refresh();
    $integrity->overrideStatus($form, $meta, FormIntegrityMeta::STATUS_TRUSTED, FormIntegrityMeta::ANALYSIS_INCLUDED, 'not a duplicate');
    $check($accepted() === 1, 'reinstating did not take the place back (counter ' . $accepted() . ')');
}

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
