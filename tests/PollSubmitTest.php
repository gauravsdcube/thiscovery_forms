<?php
/**
 * HB-4. JSON submit is only for polls, and the submit rate-limit key
 * is the network, not the session.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\services\integrity\IntegrityService;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$survey = ReviewLib::form(review_space(), 'EV HB4 survey', [
    'kind' => CustomForm::KIND_SURVEY,
    'allow_multiple' => 1,
]);
$poll = ReviewLib::form(review_space(), 'EV HB4 poll', [
    'kind' => CustomForm::KIND_POLL,
    'allow_multiple' => 1,
]);
$check(!$survey->isPoll(), 'survey was treated as a poll');
$check($poll->isPoll(), 'poll was not a poll');

$same = IntegrityService::rateLimitKey(41, '203.0.113.9');
$neighbour = IntegrityService::rateLimitKey(41, '203.0.113.10');
$other = IntegrityService::rateLimitKey(41, '198.51.100.9');
// Keyed by exact address, not /24, so neighbours behind one network are not pooled (V3-51).
$check($same !== $neighbour, 'two addresses in one /24 shared a rate-limit key');
$check($same === IntegrityService::rateLimitKey(41, '203.0.113.9'), 'one address did not keep its key');
$check($same !== $other, 'a different network shared the key');
$check(strpos($same, '203.0.113') === false, 'rate-limit key contains the raw address');
// IPv6 is pooled by /64, so rotating addresses inside one client's prefix does not reset it.
$check(
    IntegrityService::rateLimitKey(41, '2001:db8:1:2::a') === IntegrityService::rateLimitKey(41, '2001:db8:1:2:ffff::b'),
    'two addresses in one IPv6 /64 had different keys'
);
$check(
    IntegrityService::rateLimitKey(41, '2001:db8:1:2::a') !== IntegrityService::rateLimitKey(41, '2001:db8:1:3::a'),
    'two IPv6 /64 prefixes shared a key'
);

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
