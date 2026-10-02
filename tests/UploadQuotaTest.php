<?php
/**
 * SEC-13. A file question limits how many files and how many bytes
 * one question can take. Guests are also rate limited.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\services\UploadQuota;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

$check(UploadQuota::allows(880013, 1, UploadQuota::MAX_FILE_BYTES + 1, false, '203.0.113.9') !== null, 'an oversized file was accepted');
$check(UploadQuota::allows(880013, 1, 100, false, '203.0.113.9') === null, 'a small file was rejected');

for ($i = 0; $i < UploadQuota::MAX_FILES; $i++) {
    UploadQuota::record(880014, 7, 100);
}
$check(UploadQuota::allows(880014, 7, 100, false, '203.0.113.9') !== null, 'the per-question file count was not enforced');
$check(UploadQuota::allows(880014, 8, 100, false, '203.0.113.9') === null, 'a different question was blocked');

UploadQuota::record(880015, 1, UploadQuota::MAX_QUESTION_BYTES - 100);
$check(UploadQuota::allows(880015, 1, 200, false, '203.0.113.9') !== null, 'the per-question size cap was not enforced');

$ip = '203.0.113.' . random_int(20, 250);
$formId = random_int(800000, 899999);
for ($i = 0; $i < UploadQuota::GUEST_LIMIT; $i++) {
    $allowed = UploadQuota::allows($formId, 1, 100, true, $ip);
    if ($allowed !== null) {
        $check(false, 'guest upload ' . $i . ' was rejected early');
        break;
    }
}
$check(UploadQuota::allows($formId, 1, 100, true, $ip) !== null, 'guest uploads were not rate limited');
$check(UploadQuota::allows($formId, 1, 100, false, $ip) === null, 'a signed-in upload was caught by the guest limit');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
