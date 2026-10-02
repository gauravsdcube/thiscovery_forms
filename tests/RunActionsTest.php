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
// V3-40: a session gets the limit; an address (shared NAT) gets five times it.
$limited = false;
for ($i = 0; $i < 3 * 5 + 1; $i++) {
    $limited = FormActionService::tooManyRuns((int)$form->id, $ip, 3, 60);
}
$check($limited, 'run-actions was not rate limited per address');
$check(FormActionService::networkOf('2001:db8:1:2:aaaa::1') === FormActionService::networkOf('2001:db8:1:2:bbbb::9'), 'rotating IPv6 addresses in one /64 were counted separately');
$check(FormActionService::networkOf('2001:db8:1:2::1') !== FormActionService::networkOf('2001:db8:1:3::1'), 'two /64 networks shared a counter');
$to = 'relay-' . uniqid() . '@example.org';
$sent = 0;
for ($i = 0; $i < 5; $i++) {
    $sent += FormActionService::emailAllowed((int)$form->id, $to, '198.51.100.' . $i) ? 1 : 0;
}
$check($sent === 3, 'one address was sent ' . $sent . ' action emails (want 3 a day)');
$foreign = new \humhub\modules\thiscoveryForms\models\FormEmailTemplate();
$foreign->contentcontainer_id = 987654321;
$check(!FormActionService::templateAllowed(ReviewLib::reload($form), $foreign), 'a template from another space was usable (V3-50)');
$global = new \humhub\modules\thiscoveryForms\models\FormEmailTemplate();
$global->contentcontainer_id = null;
$check(FormActionService::templateAllowed(ReviewLib::reload($form), $global), 'a global template was refused');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
