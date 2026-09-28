<?php
/**
 * HF-10. The completion record reuses the captcha result from the submit gate.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\FillContext;
use humhub\modules\thiscoveryForms\services\integrity\IntegrityService;
use humhub\modules\thiscoveryForms\services\integrity\IntegritySettings;
use yii\db\Query;

$failures = [];
Yii::$app->set('captcha', new class extends yii\base\Component {
    public function getValidatorClass(): string
    {
        return yii\validators\RequiredValidator::class;
    }
});
ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV HF10 captcha once', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
(new Query())->createCommand()->delete('custom_form_answer_field', ['answer_id' => (new Query())->select('id')->from('custom_form_answer')->where(['form_id' => (int)$form->id])])->execute();
(new Query())->createCommand()->delete('custom_form_integrity_meta', ['form_id' => (int)$form->id])->execute();
(new Query())->createCommand()->delete('custom_form_answer', ['form_id' => (int)$form->id])->execute();
ReviewLib::clearFields($form);
ReviewLib::field($form, FormField::TYPE_TEXT, 'Note', ['variable' => 'note', 'required' => 0]);
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
    $failures[] = 'an invalid captcha token was accepted';
}
if ((int)Yii::$app->session->get($svc->captchaResultKey($form)) === 1) {
    $failures[] = 'a failed check was recorded as passed';
}

$accepted = $svc->gateSubmit($form, ['captcha' => 'solved'], $ctx);
if ($accepted !== null) {
    $failures[] = 'a solved captcha was rejected: ' . $accepted;
}

$answer = new FormAnswer();
$answer->form_id = (int)$form->id;
$answer->status = FormAnswer::STATUS_COMPLETE;
$answer->submitted_at = date('Y-m-d H:i:s');
$answer->save(false);
$meta = $svc->onComplete($form, $answer, ['captcha' => 'solved'], $ctx);
if (!$meta || (int)$meta->captcha_passed !== 1) {
    $failures[] = 'captcha_passed=' . ($meta ? (string)$meta->captcha_passed : 'none');
}
$flags = (string)($meta->flags_json ?? '');
if (str_contains($flags, 'captcha_fail')) {
    $failures[] = 'a solved captcha was flagged captcha_fail';
}
if (Yii::$app->session->has($svc->captchaResultKey($form))) {
    $failures[] = 'the captcha result was not cleared after completion';
}

$again = new FormAnswer();
$again->form_id = (int)$form->id;
$again->status = FormAnswer::STATUS_COMPLETE;
$again->submitted_at = date('Y-m-d H:i:s');
$again->save(false);
$meta2 = $svc->onComplete($form, $again, ['captcha' => 'solved'], $ctx);
if ($meta2 && (int)$meta2->captcha_passed === 1) {
    $failures[] = 'a second completion reused a cleared captcha result';
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS captcha_passed=1\n";
