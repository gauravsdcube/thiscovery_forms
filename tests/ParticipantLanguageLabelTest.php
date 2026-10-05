<?php
/**
 * The participant language switch shows the English name and the name in that language.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\services\TranslationService;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        fwrite(STDERR, "FAIL $message\n");
    }
};

$check(TranslationService::participantLanguageLabel('hi') === 'Hindi/हिन्दी', 'Hindi shows the English name and the Hindi name.');
$check(TranslationService::participantLanguageLabel('cy') === 'Welsh/Cymraeg', 'Welsh shows both names.');
$check(TranslationService::participantLanguageLabel('en-GB') === 'English (UK)', 'English is not repeated.');
$check(TranslationService::participantLanguageLabel('gd') === 'Scottish Gaelic/Gàidhlig', 'Scottish Gaelic shows both names.');

if ($failures) {
    fwrite(STDERR, count($failures) . " failed\n");
    exit(1);
}
echo "PASS\n";
