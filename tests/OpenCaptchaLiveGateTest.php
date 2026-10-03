<?php
/**
 * A published edition must not hide the survey's own CAPTCHA-before-open setting.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\services\FormSnapshotService;
use humhub\modules\thiscoveryForms\services\integrity\IntegritySettings;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
    }
};

$live = json_encode([
    'source_language' => 'en-GB',
    'integrity' => [
        'open_captcha' => 1,
        'open_rate_count' => 1,
        'captcha' => 1,
    ],
], JSON_UNESCAPED_UNICODE);
$published = json_encode([
    'source_language' => 'en-GB',
    'integrity' => [
        'open_captcha' => 0,
        'open_rate_count' => 30,
        'captcha' => 0,
        'enabled' => 1,
    ],
], JSON_UNESCAPED_UNICODE);

$form = new CustomForm();
$form->settings_json = $live;
(new FormSnapshotService())->hydrateInMemory($form, [
    'meta' => [
        'title' => 'Published edition',
        'settings_json' => $published,
    ],
    'fields' => [],
]);
$overlay = IntegritySettings::overlayForForm($form);
$check(($overlay['open_captcha'] ?? null) === 1, 'survey open captcha was replaced by the published edition');
$check(($overlay['open_rate_count'] ?? null) === 1, 'survey open-rate count was replaced by the published edition');
$check(($overlay['captcha'] ?? null) === 1, 'survey submit captcha was replaced by the published edition');
$check(($overlay['enabled'] ?? null) === 1, 'published integrity scoring was dropped');

$inherit = new CustomForm();
$inherit->settings_json = json_encode(['source_language' => 'en-GB', 'integrity' => []], JSON_UNESCAPED_UNICODE);
(new FormSnapshotService())->hydrateInMemory($inherit, [
    'meta' => [
        'title' => 'Published edition',
        'settings_json' => $published,
    ],
    'fields' => [],
]);
$inherited = IntegritySettings::overlayForForm($inherit);
$check(!array_key_exists('open_captcha', $inherited), 'a survey left on the site default kept the published open captcha value');

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
