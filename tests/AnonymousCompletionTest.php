<?php
/**
 * NEW-5. A token-link guest on a fully anonymous wave gets a completion row
 * and a completion email, without the answer storing the member. A second
 * submission for that wave is blocked. Test answers are skipped.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormEmailTemplate;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormPanelActivity;
use humhub\modules\thiscoveryForms\models\FormWave;
use humhub\modules\thiscoveryForms\services\FillContext;
use humhub\modules\thiscoveryForms\services\FillContextService;
use humhub\modules\thiscoveryForms\services\PanelService;

$failures = [];
ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();
$form = ReviewLib::form($space, 'EV R5 token completion', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
    'identity_mode' => CustomForm::IDENTITY_FULLY_ANONYMOUS,
]);
ReviewLib::clearFields($form);
ReviewLib::field($form, FormField::TYPE_TEXT, 'Note', ['variable' => 'r5_note', 'required' => 0]);
$form = ReviewLib::publishOpen($form);
$form->use_waves = 1;
$tpl = new FormEmailTemplate();
$tpl->contentcontainer_id = (int)$space->contentcontainer_id;
$tpl->title = 'EV R5 completion';
$tpl->subject = 'EV R5 completion';
$tpl->body_html = '<p>Thank you</p>';
$tpl->save(false);
$form->completion_email_template_id = (int)$tpl->id;
$form->log_panel_activity = 1;
$form->save(false);
$panel = (new PanelService())->ensurePanel($form);
$form->setSetting('panel_id', (int)$panel->id);
$form->save(false);
$member = (new PanelService())->upsertMember($panel, [
    'email' => 'r5-guest@example.test',
    'first' => 'Token',
    'last' => 'Guest',
]);
$wave = new FormWave();
$wave->form_id = (int)$form->id;
$wave->wave_number = (int)FormWave::find()->where(['form_id' => (int)$form->id])->max('wave_number') + 1;
$wave->title = 'Wave';
$wave->status = FormWave::STATUS_OPEN;
$wave->opens_at = date('Y-m-d H:i:s', time() - 86400);
$wave->save(false);

$answer = new FormAnswer();
$answer->form_id = (int)$form->id;
$answer->status = FormAnswer::STATUS_COMPLETE;
$answer->wave_id = (int)$wave->id;
$answer->is_test = 0;
$answer->submitted_at = date('Y-m-d H:i:s');
$answer->save(false);
$answer->updateAttributes(['created_by' => null, 'panel_member_id' => null]);

FormPanelActivity::deleteAll(['form_id' => (int)$form->id, 'answer_id' => null]);
$before = (int)(json_decode((string)@file_get_contents('http://127.0.0.1:8025/api/v1/search?query=' . rawurlencode('r5-guest@example.test')), true)['messages_count'] ?? 0);
ReviewLib::asUser(null);
(new PanelService())->handleCompletion(ReviewLib::reload($form), $answer, $member);
$answer->refresh();
$row = FormPanelActivity::find()->where([
    'form_id' => (int)$form->id,
    'member_id' => (int)$member->id,
    'answer_id' => null,
    'wave_id' => (int)$wave->id,
])->one();
if (!$row) {
    $failures[] = 'no anonymous completion row for the token guest';
} elseif ((string)$row->created_at !== date('Y-m-d') . ' 00:00:00') {
    $failures[] = 'anonymous completion time was ' . $row->created_at;
}
if ($answer->panel_member_id !== null) {
    $failures[] = 'the answer stored the member';
}
$after = (int)(json_decode((string)@file_get_contents('http://127.0.0.1:8025/api/v1/search?query=' . rawurlencode('r5-guest@example.test')), true)['messages_count'] ?? 0);
if ($after <= $before) {
    $failures[] = 'completion email was not sent';
}

$ctx = new FillContext(ReviewLib::reload($form));
$ctx->member = $member;
$ctx->wave = $wave;
$again = (new FillContextService())->findScopedAnswer($ctx->form, $ctx, (int)$wave->id, 'wave_id');
if (!$again || !$again->isComplete()) {
    $failures[] = 'a second fill of the same wave was not blocked';
}

$preview = new FormAnswer();
$preview->form_id = (int)$form->id;
$preview->status = FormAnswer::STATUS_COMPLETE;
$preview->wave_id = (int)$wave->id;
$preview->is_test = 1;
$preview->save(false);
$beforeRows = (int)FormPanelActivity::find()->where(['form_id' => (int)$form->id, 'member_id' => (int)$member->id])->count();
(new PanelService())->handleCompletion(ReviewLib::reload($form), $preview, $member);
$afterRows = (int)FormPanelActivity::find()->where(['form_id' => (int)$form->id, 'member_id' => (int)$member->id])->count();
if ($afterRows !== $beforeRows) {
    $failures[] = 'a test answer wrote a completion row';
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
