<?php
/** SEC-13: uploads are limited to known types, and the content must match the extension. */
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../services/UploadQuota.php';

use humhub\modules\thiscoveryForms\services\UploadQuota;

$failures = [];
$dir = sys_get_temp_dir() . '/cf-upload-' . bin2hex(random_bytes(4));
mkdir($dir);
$png = $dir . '/real.png';
// The smallest valid PNG (1x1).
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$fake = $dir . '/fake.png';
file_put_contents($fake, '<html><script>alert(1)</script></html>');
$text = $dir . '/notes.txt';
file_put_contents($text, "plain notes\n");

standalone_assert(UploadQuota::typeError('scan.png', $png) === null, 'a real PNG was refused', $failures);
standalone_assert(UploadQuota::typeError('scan.png', $fake) !== null, 'HTML named .png was accepted', $failures);
standalone_assert(UploadQuota::typeError('page.html', $text) !== null, 'an .html file was accepted', $failures);
standalone_assert(UploadQuota::typeError('logo.svg', $text) !== null, 'an .svg file was accepted', $failures);
standalone_assert(UploadQuota::typeError('run.exe', $text) !== null, 'an .exe file was accepted', $failures);
standalone_assert(UploadQuota::typeError('noext', $text) !== null, 'a file with no extension was accepted', $failures);
standalone_assert(UploadQuota::typeError('notes.txt', $text, ['pdf']) !== null, 'a type outside the question\'s list was accepted', $failures);
standalone_assert(UploadQuota::typeError('NOTES.TXT', $text, ['txt']) === null, 'an allowed type in capitals was refused', $failures);

array_map('unlink', glob($dir . '/*'));
rmdir($dir);
if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
exit(0);
