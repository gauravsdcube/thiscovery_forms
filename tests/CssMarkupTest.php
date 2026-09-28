<?php
/**
 * HF-8. Custom CSS cannot break out of the style element.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;

$failures = [];
$admin = review_user('review_netadmin');
ReviewLib::asUser($admin);
$form = ReviewLib::form(review_space(), 'EV HF8 css', []);
$payload = '</st</styleyle><script>alert(1)</script>';
$form->custom_css = $payload;
if ($form->validate(['custom_css'])) {
    $failures[] = 'save accepted CSS that contains <';
}
$form->clearErrors();
$form->custom_css = $payload;
$safe = $form->getSafeCustomCss();
if (str_contains($safe, '<') || str_contains($safe, '<script') || str_contains($safe, '</style>')) {
    $failures[] = 'stored CSS still renders markup: ' . $safe;
}
$form->custom_css = '.cf-title { color: #123456; }';
if (!$form->validate(['custom_css'])) {
    $failures[] = 'normal CSS was rejected';
}
$plain = $form->getSafeCustomCss();
if (!str_contains($plain, 'color: #123456')) {
    $failures[] = 'normal CSS was stripped';
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
