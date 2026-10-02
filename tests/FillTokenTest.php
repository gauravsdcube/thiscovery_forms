<?php
/**
 * The public fill link uses a random token. Issuing a new token retires the previous link.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;

$failures = [];
ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV fill token', ['allow_anonymous' => 1]);
$form = ReviewLib::reload($form);
$token = $form->getFillToken();
if ($token === '' || !preg_match('/^[A-Za-z0-9_-]{20,64}$/', $token)) {
    $failures[] = 'fill token was not created';
}
if ((string)$token === (string)$form->id) {
    $failures[] = 'fill token is the form id';
}
$url = Url::toView($form, true);
if (!str_contains($url, 't=' . rawurlencode($token)) && !str_contains($url, 't=' . $token)) {
    $failures[] = 'share url has no fill token: ' . $url;
}
if (preg_match('/(?:\\?|&)id=' . (int)$form->id . '(?:&|$)/', $url)) {
    $failures[] = 'share url still contains the form id: ' . $url;
}
$old = $token;
$next = $form->rotateFillToken();
if ($next === $old || CustomForm::findByFillToken($old) !== null) {
    $failures[] = 'the previous fill token still opens the form';
}
$found = CustomForm::findByFillToken($next);
if (!$found || (int)$found->id !== (int)$form->id) {
    $failures[] = 'the new fill token does not find the form';
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
echo $url . "\n";
