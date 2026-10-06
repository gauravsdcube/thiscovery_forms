<?php
/**
 * A shorter translation keeps the English button width.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\helpers\ButtonLabel;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        fwrite(STDERR, "FAIL $message\n");
    }
};

$same = ButtonLabel::html('Next', 'Next');
$check($same === 'Next', 'English is left as plain text.');

$short = ButtonLabel::html('Next', 'आगे');
$check(str_contains($short, 'data-cf-btn-label'), 'A shorter label is marked for script updates.');
$check(str_contains($short, '>आगे<'), 'The translation stays visible.');
$check(str_contains($short, 'aria-hidden="true">Next<'), 'The English word sets the minimum width.');

$amp = ButtonLabel::html('Save & continue later', 'बाद में सहेजें');
$check(str_contains($amp, 'Save &amp; continue later'), 'The English copy is encoded.');
$check(!str_contains($amp, 'Save & continue'), 'A raw ampersand is not written into the button.');

if ($failures) {
    fwrite(STDERR, count($failures) . " failed\n");
    exit(1);
}
fwrite(STDOUT, "PASS\n");
