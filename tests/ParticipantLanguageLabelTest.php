<?php
/**
 * The participant language switch shows the English name and the name in that language.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
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

$ordered = TranslationService::englishFirstCodes(['ar', 'hi', 'en-US', 'pa', 'en-GB']);
$check($ordered[0] === 'en-US' && $ordered[1] === 'en-GB', 'English is not kept ahead of the other languages.');
$check(array_slice($ordered, 2) === ['ar', 'hi', 'pa'], 'The other languages changed order.');
$labels = TranslationService::selectableLanguageLabels(null);
$first = (string)array_key_first($labels);
$check(TranslationService::isEnglishCode($first), 'The language list does not start with English.');

ReviewLib::asUser(review_user('review_netadmin'));
$langForm = ReviewLib::form(review_space(), 'EV language change', []);
$check($langForm->getLanguageChange() === CustomForm::LANG_CHANGE_KEEP, 'A form does not keep answers on a language change by default.');
$langForm->language_change = CustomForm::LANG_CHANGE_LOCK;
$langForm->save(false);
$langForm = ReviewLib::reload($langForm);
$check($langForm->getLanguageChange() === CustomForm::LANG_CHANGE_LOCK, 'The language-change setting was not saved.');
$shown = $langForm->getEnabledLanguages();
$langForm->enabled_languages = ['ar', 'hi', 'en-GB'];
$langForm->source_language = 'en-GB';
$shown = $langForm->getEnabledLanguages();
$check(($shown[0] ?? '') === 'en-GB', 'The fill language list does not start with English.');

if ($failures) {
    fwrite(STDERR, count($failures) . " failed\n");
    exit(1);
}
echo "PASS\n";
