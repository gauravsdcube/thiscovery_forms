<?php
/**
 * HB-7. An action email for one anonymous respondent does not count as
 * the send for a different respondent.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormEmailSend;
use humhub\modules\thiscoveryForms\services\EmailTemplateService;
use humhub\modules\thiscoveryForms\services\FormActionService;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV HB7 email', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
$form = ReviewLib::publishOpen($form);

$first = new FormAnswer();
$first->form_id = (int)$form->id;
$first->status = FormAnswer::STATUS_IN_PROGRESS;
$first->is_test = 0;
$first->save(false);
$second = new FormAnswer();
$second->form_id = (int)$form->id;
$second->status = FormAnswer::STATUS_IN_PROGRESS;
$second->is_test = 0;
$second->save(false);

$check(FormActionService::actorKey($first, null) !== FormActionService::actorKey($second, null), 'two answers share an actor key');
$check(FormActionService::actorKey(null, null) !== '', 'a session actor key is empty');

$mail = new EmailTemplateService();
$row = new FormEmailSend();
$row->kind = FormEmailSend::KIND_ACTION;
$row->form_id = (int)$form->id;
$row->answer_id = null;
$row->template_id = null;
$row->actor_key = 'session:one';
$row->email = 'one@example.test';
$row->save(false);

$check(!$mail->hasSent(FormEmailSend::KIND_ACTION, [
    'form_id' => (int)$form->id,
    'template_id' => null,
    'actor_key' => 'session:two',
]), 'a different anonymous respondent was treated as already sent');
$check($mail->hasSent(FormEmailSend::KIND_ACTION, [
    'form_id' => (int)$form->id,
    'template_id' => null,
    'actor_key' => 'session:one',
]), 'the same respondent was not recognised');

$row->delete();
$first->delete();
$second->delete();

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
