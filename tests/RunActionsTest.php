<?php
/**
 * HB-2. Run-actions does not accept a submit trigger. Posted action
 * variables cannot override built-in mail tokens and must be names
 * declared on the form. Email HTML escapes those values. Runs are limited.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
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
$form = ReviewLib::form(review_space(), 'EV HB2 actions', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
ReviewLib::clearFields($form);
ReviewLib::field($form, FormField::TYPE_TEXT, 'Age', ['variable' => 'age']);
$form->custom_functions = [['name' => 'score', 'value' => '1']];
$form->save(false);

$filtered = FormActionService::filterPostedVars($form, ['age' => '40'], [
    'age' => '41',
    'email' => 'attacker@example.test',
    'not_declared' => 'nope',
    'score' => '9',
]);
$check(($filtered['age'] ?? '') === '41', 'declared variable was not applied');
$check(($filtered['score'] ?? '') === '9', 'declared function name was not applied');
$check(!isset($filtered['email']), 'built-in email was posted');
$check(!isset($filtered['not_declared']), 'undeclared name was posted');
$check(!FormActionService::acceptsRunTrigger('submit'), 'submit trigger is accepted');
$check(FormActionService::acceptsRunTrigger('field'), 'field trigger is rejected');

$mail = new EmailTemplateService();
$html = $mail->replaceVars('<p>{age}</p>', ['age' => '<script>'], true);
$check(strpos($html, '<script>') === false, 'email HTML did not escape the value');
$check(strpos($html, '&lt;script&gt;') !== false, 'escaped value missing');

$built = $mail->varsFor($form, null, null, null, ['email' => 'attacker@example.test', 'age' => '41']);
$check(($built['email'] ?? '') === '', 'posted email overrode the built-in');
$check(($built['age'] ?? '') === '41', 'extra declared value was dropped');

$ip = '203.0.113.' . random_int(1, 250);
$limited = false;
for ($i = 0; $i < 4; $i++) {
    $limited = FormActionService::tooManyRuns((int)$form->id, $ip, 3, 60);
}
$check($limited, 'run-actions was not rate limited');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
