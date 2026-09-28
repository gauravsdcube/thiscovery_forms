<?php
/**
 * NEW-4. Repair only complete answers on forms whose decoded identity mode is
 * fully anonymous, and only from the date the mode was set. A settings string
 * that merely contains the words is not enough. Reversal restores the values.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormIntegrityMeta;
use humhub\modules\thiscoveryForms\models\FormPanelActivity;
use humhub\modules\thiscoveryForms\models\FormWave;
use humhub\modules\thiscoveryForms\services\IdentityRepair;
use humhub\modules\thiscoveryForms\services\PanelService;
use yii\db\Query;

$failures = [];
ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();
$userId = (int)review_user('review_respondent')->id;

$formA = ReviewLib::form($space, 'EV R4 identified text', [
    'allow_anonymous' => 0,
    'allow_multiple' => 1,
    'identity_mode' => CustomForm::IDENTITY_IDENTIFIED,
]);
$formA->setSetting('researcher_note', 'protocol is not fully_anonymous');
$formA->identity_mode = CustomForm::IDENTITY_IDENTIFIED;
$formA->save(false);

$formB = ReviewLib::form($space, 'EV R4 switched later', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
    'identity_mode' => CustomForm::IDENTITY_FULLY_ANONYMOUS,
]);
$formB->use_waves = 1;
$formB->save(false);
$panel = (new PanelService())->ensurePanel($formB);
$formB->setSetting('panel_id', (int)$panel->id);
$formB->save(false);
$member = (new PanelService())->upsertMember($panel, ['email' => 'r4-done@example.test', 'first' => 'Done', 'last' => 'Member']);
$wave = new FormWave();
$wave->form_id = (int)$formB->id;
$wave->wave_number = (int)FormWave::find()->where(['form_id' => (int)$formB->id])->max('wave_number') + 1;
$wave->title = 'Wave';
$wave->status = FormWave::STATUS_OPEN;
$wave->opens_at = date('Y-m-d H:i:s', time() - 86400);
$wave->save(false);

$old = new FormAnswer();
$old->form_id = (int)$formB->id;
$old->status = FormAnswer::STATUS_COMPLETE;
$old->wave_id = (int)$wave->id;
$old->round_id = 4;
$old->save(false);
$old->updateAttributes([
    'created_by' => $userId,
    'panel_member_id' => (int)$member->id,
    'resume_email' => 'r4-old@example.test',
    'submitted_at' => '2020-01-01 00:00:00',
]);
$recent = new FormAnswer();
$recent->form_id = (int)$formB->id;
$recent->status = FormAnswer::STATUS_COMPLETE;
$recent->wave_id = (int)$wave->id;
$recent->round_id = 4;
$recent->save(false);
$recent->updateAttributes([
    'created_by' => $userId,
    'panel_member_id' => (int)$member->id,
    'resume_email' => 'r4-new@example.test',
    'submitted_at' => date('Y-m-d H:i:s'),
]);
$meta = new FormIntegrityMeta();
$meta->answer_id = (int)$recent->id;
$meta->form_id = (int)$formB->id;
$meta->access_token_hash = 'tok-r4';
$meta->save(false);
$activity = new FormPanelActivity();
$activity->panel_id = (int)$panel->id;
$activity->member_id = (int)$member->id;
$activity->form_id = (int)$formB->id;
$activity->answer_id = (int)$recent->id;
$activity->wave_id = null;
$activity->save(false);

$repair = new IdentityRepair();
$candidates = array_map(static fn(CustomForm $form): int => (int)$form->id, $repair->candidateForms());
if (in_array((int)$formA->id, $candidates, true)) {
    $failures[] = 'an identified form was selected because its settings text matched';
}
if (!in_array((int)$formB->id, $candidates, true)) {
    $failures[] = 'a fully anonymous form was not selected';
}
if ($repair->sinceFor(ReviewLib::reload($formB)) !== null) {
    $failures[] = 'a form with no mode-change history reported a since date';
}
$withoutSince = $repair->selectAnswers(ReviewLib::reload($formB), null);
if ($withoutSince !== []) {
    $failures[] = 'answers were selected when the since date is unknown';
}
$selected = $repair->selectAnswers(ReviewLib::reload($formB), '2026-01-01 00:00:00');
$selectedIds = array_map(static fn(FormAnswer $answer): int => (int)$answer->id, $selected);
if (in_array((int)$old->id, $selectedIds, true)) {
    $failures[] = 'an answer from before the cutoff was selected';
}
if (!in_array((int)$recent->id, $selectedIds, true)) {
    $failures[] = 'an answer from after the cutoff was not selected';
}

if ($selected !== []) {
    $runId = $repair->apply($selected, $userId);
    $recent->refresh();
    $old->refresh();
    $activity->refresh();
    $meta->refresh();
    if ($recent->created_by !== null || $recent->panel_member_id !== null || $recent->resume_email) {
        $failures[] = 'the recent answer kept its identity';
    }
    if ((int)$old->created_by !== $userId) {
        $failures[] = 'the earlier answer was repaired';
    }
    if ((int)$activity->wave_id !== (int)$wave->id || $activity->answer_id !== null) {
        $failures[] = 'activity wave_id=' . var_export($activity->wave_id, true) . ' answer_id=' . var_export($activity->answer_id, true);
    }
    if ((int)$activity->round_id !== 4) {
        $failures[] = 'activity round was not copied';
    }
    if ($meta->access_token_hash !== null) {
        $failures[] = 'access token hash was kept';
    }
    $logged = (int)(new Query())->from('custom_form_identity_repair_log')->where(['run_id' => $runId])->count();
    if ($logged < 1) {
        $failures[] = 'audit log is empty';
    }
    $repair->reverse($runId);
    $recent->refresh();
    $activity->refresh();
    $meta->refresh();
    if ((int)$recent->created_by !== $userId || (int)$recent->panel_member_id !== (int)$member->id) {
        $failures[] = 'reversal did not restore the answer';
    }
    if ((string)$recent->resume_email !== 'r4-new@example.test') {
        $failures[] = 'reversal did not restore the resume email';
    }
    if ((int)$activity->answer_id !== (int)$recent->id || $activity->wave_id !== null) {
        $failures[] = 'reversal did not restore the activity row';
    }
    if ((string)$meta->access_token_hash !== 'tok-r4') {
        $failures[] = 'reversal did not restore the token hash';
    }
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
