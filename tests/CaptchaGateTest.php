<?php
/**
 * NEW-15 / SEC-7. A failed verification is not left in the session, and a
 * poll submit does not record that failure against the answer.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\FillContext;
use humhub\modules\thiscoveryForms\services\integrity\IntegrityService;
use humhub\modules\thiscoveryForms\services\integrity\IntegritySettings;

$failures = [];
Yii::$app->set('captcha', new class extends yii\base\Component {
    public function getValidatorClass(): string
    {
        return yii\validators\RequiredValidator::class;
    }
});
ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV R15 captcha gate', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
ReviewLib::clearFields($form);
ReviewLib::field($form, FormField::TYPE_TEXT, 'Note', ['variable' => 'r15_note', 'required' => 0]);
$form = ReviewLib::publishOpen($form);
IntegritySettings::saveForm($form, [
    'enabled' => 1,
    'captcha' => 1,
    'captcha_mode' => IntegritySettings::CAPTCHA_ALWAYS,
    'captcha_provider' => IntegritySettings::CAPTCHA_PROVIDER_ALTCHA,
    'bot_protection' => 1,
]);
$form = ReviewLib::reload($form);
$svc = new class extends IntegrityService {
    public function verifyCaptcha(array $cfg, array $post): bool
    {
        return ($post['captcha'] ?? '') === 'solved';
    }
};
$ctx = new FillContext($form);
$svc->onFillOpen($form);

$rejected = $svc->gateSubmit($form, ['captcha' => 'nope'], $ctx);
if ($rejected === null) {
    $failures[] = 'invalid token accepted';
}
if (Yii::$app->session->has($svc->captchaResultKey($form))) {
    $failures[] = 'a failed check was left in the session';
}

$accepted = $svc->gateSubmit($form, ['captcha' => 'solved'], $ctx);
if ($accepted !== null) {
    $failures[] = 'solved captcha rejected';
}
$stored = Yii::$app->session->get($svc->captchaResultKey($form));
if (!is_array($stored) || empty($stored['shown']) || empty($stored['passed'])) {
    $failures[] = 'gate did not store shown and passed';
}

$answer = new FormAnswer();
$answer->form_id = (int)$form->id;
$answer->status = FormAnswer::STATUS_COMPLETE;
$answer->submitted_at = date('Y-m-d H:i:s');
$answer->save(false);
$meta = $svc->onComplete($form, $answer, [], $ctx);
if (!$meta || (int)$meta->captcha_shown !== 1 || (int)$meta->captcha_passed !== 1) {
    $failures[] = 'completion did not use the stored result';
}

Yii::$app->session->set($svc->captchaResultKey($form), ['shown' => 1, 'passed' => 0]);
$svc->discardCaptchaResult($form);
$poll = new FormAnswer();
$poll->form_id = (int)$form->id;
$poll->status = FormAnswer::STATUS_COMPLETE;
$poll->submitted_at = date('Y-m-d H:i:s');
$poll->save(false);
$pollMeta = $svc->onComplete($form, $poll, [], $ctx);
if ($pollMeta && $pollMeta->captcha_passed !== null) {
    $failures[] = 'poll submit recorded a captcha result ' . var_export($pollMeta->captcha_passed, true);
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
