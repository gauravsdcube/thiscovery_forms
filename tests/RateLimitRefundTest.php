<?php
/**
 * INT-8. A submit that is rejected after the gate (validation errors, failed save) gives
 * back its rate-limit count, so a person who keeps fixing errors is never locked out.
 * Attempts already over the limit are not counted, so a blocked script does not keep
 * extending its own block.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\services\FillContext;
use humhub\modules\thiscoveryForms\services\integrity\IntegrityService;
use humhub\modules\thiscoveryForms\services\integrity\IntegritySettings;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV INT8 rate refund', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
$form = ReviewLib::publishOpen($form);
IntegritySettings::saveForm($form, [
    'enabled' => 1,
    'rate_limiting' => 1,
    'captcha' => 0,
    'rate_limit_count' => 2,
    'rate_limit_window' => 10,
]);
$svc = new IntegrityService();
$svc->onFillOpen($form);

// Five rejected saves in a row: each is refunded, so none of them uses up the allowance.
for ($i = 1; $i <= 5; $i++) {
    $ctx = new FillContext($form);
    $gate = $svc->gateSubmit($form, [], $ctx);
    $check($gate === null, "attempt {$i} after refunded failures was blocked: " . (string)$gate);
    $check($ctx->rateCounted, "attempt {$i} was not counted");
    $svc->refundAccessToken($form, $ctx);
    $check(!$ctx->rateCounted, "attempt {$i} was not refunded");
}

// Two accepted submits use the allowance; the third is refused and is not counted.
foreach ([1, 2] as $i) {
    $ctx = new FillContext($form);
    $check($svc->gateSubmit($form, [], $ctx) === null, "accepted submit {$i} was blocked");
}
$ctx = new FillContext($form);
$check($svc->gateSubmit($form, [], $ctx) !== null, 'third submit within the window was accepted');
$check(!$ctx->rateCounted, 'a refused submit was counted');
$svc->refundAccessToken($form, $ctx);
$ctx = new FillContext($form);
$check($svc->gateSubmit($form, [], $ctx) !== null, 'refunding a refused submit reopened the limit');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
