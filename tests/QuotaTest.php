<?php
/**
 * F4. A full cell keeps the partial answers and does not exceed the target.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormPanel;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\services\FormCloneService;
use humhub\modules\thiscoveryForms\services\LogicEngine;
use humhub\modules\thiscoveryForms\services\QuotaService;
use humhub\modules\thiscoveryForms\services\TranslationImportExportService;
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
$module->settings->set(Module::SETTING_QUOTAS, '0');
ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();

$form = ReviewLib::form($space, 'EV F4 quotas', [
    'allow_anonymous' => 0,
    'allow_multiple' => 1,
    'allow_resume' => 1,
    'identity_mode' => CustomForm::IDENTITY_IDENTIFIED,
]);
$form->setSetting('quotas_enabled', '1');
$form->save(false);
ReviewLib::clearFields($form);
$age = ReviewLib::field($form, FormField::TYPE_NUMBER, 'Age', ['variable' => 'age', 'required' => 1]);
$sex = ReviewLib::field($form, FormField::TYPE_TEXT, 'Sex', ['variable' => 'sex']);
$form = ReviewLib::publishOpen($form);
$reset = static function () use ($form): void {
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
};

$rules = 'between([age], 18, 34) and [sex] = "female"';
$check($svc->rulesMatch($rules, ['age' => '25', 'sex' => 'female'], $form->fields), 'a matching cell was false');
$check(!$svc->rulesMatch($rules, ['age' => '25', 'sex' => 'male'], $form->fields), 'a cross-classified miss was true');
$check($svc->rulesMatch('[panel:site] = "north"', ['panel.site' => 'north']), 'a panel factor was false');
$check(!$svc->cellKnown(LogicEngine::fromFormula('[arm] = "pictogram"'), []), 'an arm cell was known before assignment');

$module->settings->set(Module::SETTING_QUOTAS, '0');
$off = ReviewLib::submit($form, [(int)$age->id => '25', (int)$sex->id => 'female']);
$check($off && $off->countsAsComplete() && (string)$off->outcome !== FormAnswer::OUTCOME_OVER_QUOTA, 'flag off changed the outcome');
$reset();

$module->settings->set(Module::SETTING_QUOTAS, '1');
$cell = $svc->saveQuota($form, null, [
    'name' => 'Age 18 to 34',
    'target' => 2,
    'rules' => $rules,
    'action' => 'end',
    'action_message' => 'This age group is full.',
]);
$check($cell && (int)$cell['reserve'] === 0 && (int)$cell['reserve_minutes'] === 60, 'reservation did not default off at 60 minutes');
$first = ReviewLib::submit($form, [(int)$age->id => '20', (int)$sex->id => 'female']);
$second = ReviewLib::submit($form, [(int)$age->id => '30', (int)$sex->id => 'female']);
$third = ReviewLib::submit($form, [(int)$age->id => '22', (int)$sex->id => 'female']);
$other = ReviewLib::submit($form, [(int)$age->id => '22', (int)$sex->id => 'male']);
$check($first && $first->countsAsComplete() && $second && $second->countsAsComplete(), 'open cells were not completed');
$check($third && (string)$third->outcome === FormAnswer::OUTCOME_OVER_QUOTA && !$third->countsAsComplete(), 'the extra response was not over quota');
$kept = ReviewLib::answerRows((int)$third->id);
$check(count($kept) >= 1, 'over quota dropped the partial answers');
$check($other && $other->countsAsComplete(), 'a different cell was stopped');
$summary = $svc->summary($form);
$check($summary && (int)$summary[0]['accepted'] === 2, 'the counter did not stop at the target');
$fullAudits = (new Query())->from('custom_form_quota_audit')->where(['quota_id' => (int)$cell['id']])->all();
$fullEvents = 0;
foreach ($fullAudits as $audit) {
    $change = json_decode((string)$audit['change_json'], true);
    if (is_array($change) && ($change['event'] ?? '') === 'quota.full') {
        $fullEvents++;
    }
}
$check($fullEvents === 1, 'quota.full was not recorded once');

$csv = (new \humhub\modules\thiscoveryForms\services\ExportService())->toCsv($form, ['header_mode' => 'variable']);
$check(str_contains($csv, 'quota_ids'), 'export missed quota ids');
$check(str_contains((new \humhub\modules\thiscoveryForms\services\ExportService())->codebookCsv($form), 'quota_' . (int)$cell['id']), 'the codebook missed the quota');
$dash = (new \humhub\modules\thiscoveryForms\services\DashboardService())->getFormDashboard($form);
$check(isset($dash['quotas'][0]['accepted']) && (int)$dash['quotas'][0]['accepted'] === 2, 'the dashboard disagreed with the counter');

Yii::$app->db->createCommand()->update('custom_form_quota_counter', ['accepted' => 3], ['quota_id' => (int)$cell['id']])->execute();
$drift = $svc->reconcile($form, false);
$check($drift && (int)$drift[0]['stored'] === 3 && (int)$drift[0]['recount'] === 2, 'reconcile missed a seeded drift');
$svc->reconcile($form, true);
$check((int)$svc->summary($form)[0]['accepted'] === 2, 'reconcile apply did not repair the counter');

$reset();
$parent = $svc->saveQuota($form, null, [
    'name' => 'Women',
    'target' => 1,
    'rules' => '[sex] = "female"',
    'action' => 'end',
    'action_message' => 'Women is full.',
]);
$child = $svc->saveQuota($form, null, [
    'name' => 'Women 18 to 34',
    'target' => 5,
    'parent_id' => (int)$parent['id'],
    'rules' => $rules,
    'action' => 'end',
    'action_message' => 'The younger group is full.',
]);
ReviewLib::submit($form, [(int)$age->id => '20', (int)$sex->id => 'female']);
$nested = ReviewLib::submit($form, [(int)$age->id => '21', (int)$sex->id => 'female']);
$check($nested && (string)$nested->outcome === FormAnswer::OUTCOME_OVER_QUOTA, 'a full parent accepted another child');
$childAccepted = (int)(new Query())->select('accepted')->from('custom_form_quota_counter')->where(['quota_id' => (int)$child['id']])->scalar();
$check($childAccepted === 1, 'the child counter moved after the parent was full');

$reset();
$svc->addHost($form, '1.1.1.1');
$svc->addHost($form, '10.1.1.1');
$redirect = $svc->saveQuota($form, null, [
    'name' => 'Redirect cell',
    'target' => 1,
    'rules' => '[sex] = "female"',
    'action' => 'redirect',
    'action_url' => 'https://1.1.1.1/go?s={status}&q={quota}&a={answer}',
    'action_message' => 'Please continue with the panel.',
]);
$errors = $svc->authoringErrors($form);
$check(!array_filter($errors, static fn($message) => str_contains($message, 'Redirect cell')), 'an allowlisted public redirect was refused');
$private = $svc->saveQuota($form, null, [
    'name' => 'Private redirect',
    'target' => 1,
    'rules' => '[sex] = "male"',
    'action' => 'redirect',
    'action_url' => 'https://10.1.1.1/go',
    'action_message' => 'No.',
]);
$errors = $svc->authoringErrors($form);
$check((bool)array_filter($errors, static fn($message) => str_contains($message, 'Private redirect')), 'a private redirect host was accepted');
ReviewLib::submit($form, [(int)$age->id => '40', (int)$sex->id => 'female']);
$sent = ReviewLib::submit($form, [(int)$age->id => '41', (int)$sex->id => 'female']);
$check($sent && (string)$sent->outcome === FormAnswer::OUTCOME_OVER_QUOTA, 'redirect did not mark over quota');
$check(str_contains((string)Yii::$app->session->get('cf-quota-redirect', ''), 'https://1.1.1.1/go?s=over_quota'), 'the redirect URL was not built');

$reset();
$svc->saveQuota($form, null, [
    'name' => 'Mark only',
    'target' => 1,
    'rules' => '[sex] = "female"',
    'action' => 'continue',
    'action_message' => 'Marked.',
]);
$marked = ReviewLib::submit($form, [(int)$age->id => '40', (int)$sex->id => 'female']);
$markedAgain = ReviewLib::submit($form, [(int)$age->id => '41', (int)$sex->id => 'female']);
$check($marked && $marked->countsAsComplete() && (int)$marked->quota_marker > 0, 'continue did not mark the response');
$check($markedAgain && $markedAgain->countsAsComplete(), 'continue stopped a later response');
$check((int)$svc->summary($form)[0]['accepted'] === 0, 'continue consumed a place');

$reset();
ReviewLib::field($form, FormField::TYPE_PAGE_BREAK, 'Other', [
    'variable' => 'other_page',
    'options' => json_encode(['__type' => FormField::TYPE_PAGE_BREAK, 'pageKey' => 'other']),
]);
$form = ReviewLib::reload($form);
$svc->saveQuota($form, null, [
    'name' => 'Go elsewhere',
    'target' => 1,
    'rules' => '[sex] = "female"',
    'action' => 'goto',
    'action_page_key' => 'other',
    'action_message' => 'Try the other page.',
]);
ReviewLib::submit($form, [(int)$age->id => '40', (int)$sex->id => 'female']);
$moved = ReviewLib::submit($form, [(int)$age->id => '41', (int)$sex->id => 'female']);
$check($moved && $moved->isInProgress() && (string)$moved->outcome !== FormAnswer::OUTCOME_OVER_QUOTA, 'goto closed the response');

$reset();
$hold = $svc->saveQuota($form, null, [
    'name' => 'Held place',
    'target' => 1,
    'rules' => '[sex] = "female"',
    'reserve' => 1,
    'reserve_minutes' => 60,
    'check_page_key' => 'start',
    'action' => 'end',
    'action_message' => 'The held place expired.',
]);
Yii::$app->request->setBodyParams(['current_page' => 1]);
$draft = ReviewLib::submit($form, [(int)$age->id => '28', (int)$sex->id => 'female'], true);
$reserved = (int)(new Query())->select('reserved')->from('custom_form_quota_counter')->where(['quota_id' => (int)$hold['id']])->scalar();
$check($draft && $draft->isInProgress() && $reserved === 1, 'leaving the check page did not reserve a place');
Yii::$app->db->createCommand()->update('custom_form_quota_reservation', [
    'expires_at' => gmdate('Y-m-d H:i:s', time() - 120),
], ['answer_id' => (int)$draft->id])->execute();
$check($svc->expire() === 1, 'expire did not drop the reservation');
$took = ReviewLib::submit($form, [(int)$age->id => '29', (int)$sex->id => 'female']);
$late = ReviewLib::submit($form, [(int)$age->id => '28', (int)$sex->id => 'female'], false, $draft);
$check($took && $took->countsAsComplete(), 'the next person could not take the expired place');
$check($late && (string)$late->outcome === FormAnswer::OUTCOME_OVER_QUOTA, 'a late return still took the place');
Yii::$app->request->setBodyParams([]);

$reset();
$reset();
$panel = FormPanel::findOne(['title' => 'EV F4 panel']) ?: new FormPanel();
$panel->title = 'EV F4 panel';
$panel->save(false);
$member = FormPanelMember::findOne(['token' => 'f4member']) ?: new FormPanelMember();
$member->panel_id = (int)$panel->id;
$member->token = 'f4member';
$member->email = 'ada-f4@example.test';
$member->status = FormPanelMember::STATUS_ACTIVE;
$member->setDemographics(['site' => 'north']);
$member->save(false);
$panelQuota = $svc->saveQuota($form, null, [
    'name' => 'North',
    'target' => 1,
    'rules' => '[panel:site] = "north"',
    'action' => 'end',
    'action_message' => 'North is full.',
]);
$submit = new \humhub\modules\thiscoveryForms\models\SubmitForm();
$submit->form = $form;
$submit->panelMemberId = (int)$member->id;
$submit->values = [(int)$age->id => '33', (int)$sex->id => 'female'];
$panelAnswer = $submit->save(null, false, false, false);
$check($panelAnswer && $panelAnswer->countsAsComplete(), 'a panel cell was not accepted');
$check($svc->blocksInvite($form, $member, null), 'a full known panel cell still invited');

$anon = ReviewLib::form($space, 'EV F4 anonymous quota', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
    'identity_mode' => CustomForm::IDENTITY_FULLY_ANONYMOUS,
]);
$anon->setSetting('quotas_enabled', '1');
$anon->save(false);
$anonField = ReviewLib::field($anon, FormField::TYPE_TEXT, 'City', ['variable' => 'city']);
$svc->saveQuota($anon, null, [
    'name' => 'Anonymous panel',
    'target' => 1,
    'rules' => '[panel:site] = "north"',
    'action' => 'end',
]);
$anonErrors = $svc->authoringErrors(ReviewLib::reload($anon));
$check((bool)array_filter($anonErrors, static fn($message) => str_contains($message, 'Anonymous panel')), 'a fully anonymous form was allowed a panel quota');

$import = $svc->importPayload($form, [
    ['name' => 'Known age', 'target' => 3, 'rules' => '[age] = 50', 'action' => 'end'],
    ['name' => 'Missing question', 'target' => 3, 'rules' => '[not_a_field] = "x"', 'action' => 'end'],
]);
$check($import['imported'] === 1 && in_array('Missing question', $import['failed'], true), 'a missing question did not fail on its own');

$copy = new CustomForm($space);
$copy->title = 'EV F4 clone ' . gmdate('His');
$copy->content->visibility = \humhub\modules\content\models\Content::VISIBILITY_PUBLIC;
$check((new FormCloneService())->copyInto($form, $copy, ['title' => $copy->title]), 'clone failed');
$check($svc->quotas((int)$copy->id) !== [], 'clone dropped the quotas');

$svc->saveTranslation((int)$panelQuota['id'], 'message', 'cy', 'Mae gogledd yn llawn.');
$exported = (new TranslationImportExportService())->exportJson(ReviewLib::reload($form));
$keys = array_column($exported['strings'], 'key');
$check(in_array('quota.' . (int)$panelQuota['id'] . '.name', $keys, true), 'translation export missed the quota name');

$policy = $svc->saveQuota($form, null, [
    'name' => 'Integrity cell',
    'target' => 5,
    'rules' => '[sex] = "other"',
    'count_policy' => 'complete_excluding_integrity',
    'action' => 'end',
]);
$excluded = ReviewLib::submit($form, [(int)$age->id => '50', (int)$sex->id => 'other']);
$svc->releaseExcluded($form, $excluded);
$released = (int)(new Query())->select('accepted')->from('custom_form_quota_counter')->where(['quota_id' => (int)$policy['id']])->scalar();
$check($excluded && $released === 0, 'an integrity exclusion still consumed a place');

$reset();
$module->settings->set(Module::SETTING_QUOTAS, '1');
$form->setSetting('quotas_enabled', '1');
$form->save(false);
$race = $svc->saveQuota($form, null, [
    'name' => 'Race',
    'target' => 10,
    'rules' => '[age] = 25',
    'action' => 'end',
    'action_message' => 'Full.',
]);
$workers = [];
$worker = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/support/quota_worker.php') . ' ' . (int)$form->id . ' ' . (int)$age->id . ' 5 25';
for ($i = 0; $i < 20; $i++) {
    $pipes = [];
    $workers[] = proc_open($worker, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['THISCOVERY_FORMS_TEST_DB' => '1']);
}
foreach ($workers as $process) {
    if (is_resource($process)) {
        proc_close($process);
    }
}
$complete = (int)FormAnswer::find()->where([
    'form_id' => (int)$form->id,
    'is_test' => 0,
    'status' => FormAnswer::STATUS_COMPLETE,
])->andWhere(['outcome' => ['', FormAnswer::OUTCOME_COMPLETE]])->count();
$over = (int)FormAnswer::find()->where([
    'form_id' => (int)$form->id,
    'outcome' => FormAnswer::OUTCOME_OVER_QUOTA,
])->count();
$accepted = (int)(new Query())->select('accepted')->from('custom_form_quota_counter')->where(['quota_id' => (int)$race['id']])->scalar();
$check($complete === 10 && $over === 90 && $accepted === 10, "parallel submits were {$complete} complete, {$over} over quota, counter {$accepted}");

$module->settings->set(Module::SETTING_QUOTAS, '0');
if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
