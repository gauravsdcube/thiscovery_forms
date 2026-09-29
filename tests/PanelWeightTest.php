<?php
/**
 * HB-8. A panel weight of 0 is kept. Completion and a re-import that
 * does not include a weight do not reset it, including on an anonymous form.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormPanelActivity;
use humhub\modules\thiscoveryForms\services\PanelService;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();
$form = ReviewLib::form($space, 'EV HB8 weight', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
    'identity_mode' => CustomForm::IDENTITY_FULLY_ANONYMOUS,
]);
$form = ReviewLib::publishOpen($form);
$panel = (new PanelService())->ensurePanel($form);
$svc = new PanelService();
$member = $svc->addEmailMember($panel, 'hb8-zero@example.test', 'Zero', 0);
$check((float)$member->weight === 0.0, 'a weight of 0 was stored as something else');

$member->weight = 0;
$member->save(false);
$again = $svc->upsertMember($panel, ['email' => 'hb8-zero@example.test', 'first' => 'Zero']);
$check((float)$again->weight === 0.0, 'completion-style update reset the weight');

$svc->importCsv($panel, "email,first_name,last_name\nhb8-zero@example.test,Zero,Member\n");
$again->refresh();
$check((float)$again->weight === 0.0, 're-import reset the weight');

$answer = new FormAnswer();
$answer->form_id = (int)$form->id;
$answer->status = FormAnswer::STATUS_COMPLETE;
$answer->is_test = 0;
$answer->weight = 1;
$answer->submitted_at = date('Y-m-d H:i:s');
$answer->save(false);
FormPanelActivity::deleteAll(['form_id' => (int)$form->id, 'answer_id' => null]);
$svc->handleCompletion($form, $answer, $again);
$again->refresh();
$answer->refresh();
$check((float)$again->weight === 0.0, 'anonymous completion reset the panel weight');
$check((float)$answer->weight === 0.0, 'anonymous completion did not keep the member weight on the answer');

$answer->delete();

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
