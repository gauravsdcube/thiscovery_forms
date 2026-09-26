<?php
/**
 * HF-1. Fully anonymous forms must not store or name the respondent.
 * Run: sudo -u www-data php /home/admin/thiscovery-review/bin/boot.php \
 *   /var/www/humhub/protected/modules/thiscovery-forms/tests/IdentityModeTest.php
 */
require '/home/admin/thiscovery-review/fixtures/evidence_support.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormPanelActivity;
use humhub\modules\thiscoveryForms\services\PanelService;

$failures = [];

$admin = review_user('review_netadmin');
ReviewLib::asUser($admin);
$space = review_space();
$form = ReviewLib::form($space, 'EV HF1 fully anonymous', [
    'allow_anonymous' => 0,
    'allow_multiple' => 1,
    'identity_mode' => CustomForm::IDENTITY_FULLY_ANONYMOUS,
]);
ReviewLib::clearFields($form);
$q = ReviewLib::field($form, FormField::TYPE_TEXT, 'Note', ['variable' => 'note', 'required' => 0, 'sort_order' => 1]);
$form = ReviewLib::publishOpen($form);

$panel = (new PanelService())->ensurePanel($form);
$respondent = review_user('review_respondent');
$member = (new PanelService())->upsertMember($panel, [
    'user' => $respondent,
    'email' => (string)$respondent->email,
]);

ReviewLib::asUser($respondent);
$anonymous = method_exists($form, 'submitAsAnonymous')
    ? $form->submitAsAnonymous(false, false)
    : ((bool)$form->allowsAnonymous() || Yii::$app->user->isGuest);
$submit = new humhub\modules\thiscoveryForms\models\SubmitForm();
$submit->form = $form;
$submit->values = [(int)$q->id => 'hello'];
$submit->panelMemberId = (int)$member->id;
$answer = $submit->save(null, $anonymous, false, false);
if (!$answer) {
    $failures[] = 'submit returned null';
} else {
    $answer->refresh();
    if ($answer->created_by !== null) {
        $failures[] = 'created_by=' . $answer->created_by;
    }
    if ($answer->updated_by !== null) {
        $failures[] = 'updated_by=' . $answer->updated_by;
    }
    if ($answer->panel_member_id !== null) {
        $failures[] = 'panel_member_id=' . $answer->panel_member_id;
    }
    if ($answer->resume_email !== null && $answer->resume_email !== '') {
        $failures[] = 'resume_email stored';
    }
    (new PanelService())->handleCompletion($form, $answer);
    $answer->refresh();
    if ($answer->panel_member_id !== null) {
        $failures[] = 'handleCompletion linked panel_member_id=' . $answer->panel_member_id;
    }
    $linked = FormPanelActivity::find()
        ->where(['member_id' => (int)$member->id, 'answer_id' => (int)$answer->id])
        ->exists();
    if ($linked) {
        $failures[] = 'activity row stores answer_id';
    }
    $counter = FormPanelActivity::find()
        ->where(['member_id' => (int)$member->id, 'form_id' => (int)$form->id, 'answer_id' => null])
        ->exists();
    if (!$counter) {
        $failures[] = 'no unlinked completion counter';
    }
}

if (!method_exists($form, 'submitAsAnonymous') || $form->submitAsAnonymous(false, false) !== true) {
    $failures[] = 'signed-in fully anonymous submit is not treated as anonymous';
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS form={$form->id} answer={$answer->id}\n";
