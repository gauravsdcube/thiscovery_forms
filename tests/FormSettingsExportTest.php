<?php
/**
 * JSON question export carries form settings, except secure send and site-local secrets.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\QuestionImportExportService;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$source = ReviewLib::form(review_space(), 'EV settings export source', [
    'allow_anonymous' => 1,
    'allow_resume' => 0,
]);
ReviewLib::clearFields($source);
$source->description = 'Protocol copy';
$source->thank_you_content = 'Thanks for taking part';
$source->setSetting('secure_send_minutes', 15);
$source->setSetting('test_token', 'secret-token');
$source->keep_partials = 0;
$source->setSetting('keep_partials', false);
$source->save(false);

$age = ReviewLib::field($source, FormField::TYPE_TEXT, 'Age', ['variable' => 'age_export']);
$town = ReviewLib::field($source, FormField::TYPE_TEXT, 'Town', ['variable' => 'town_export']);
$source = ReviewLib::reload($source);
$source->setSetting('integrity', [
    'captcha' => 1,
    'open_captcha' => 1,
    'consistency_rules' => [[
        'id' => 'r1',
        'label' => 'Age and town',
        'conditions' => [
            ['field_id' => (int)$age->id, 'operator' => 'equals', 'value' => '1'],
            ['field_id' => (int)$town->id, 'operator' => 'equals', 'value' => '2'],
        ],
    ]],
]);
$source->setSetting('export', [
    'exclude_columns' => ['field.' . (int)$age->id, 'meta.user'],
    'pii_scrub' => true,
]);
$source->save(false);

$service = new QuestionImportExportService();
$exported = $service->exportJson(ReviewLib::reload($source));
$values = $exported['settings']['values'] ?? [];
$check(!array_key_exists('secure_send_minutes', $values), 'secure send minutes were exported');
$check(!array_key_exists('test_token', $values), 'the preview token was exported');
$check(($exported['settings']['description'] ?? '') === 'Protocol copy', 'description was not exported');
$conditions = $values['integrity']['consistency_rules'][0]['conditions'] ?? [];
$check(($conditions[0]['field_id'] ?? '') === 'age_export', 'consistency rule still used a question id');
$check(($values['export']['exclude_columns'][0] ?? '') === 'field.age_export', 'export column still used a question id');

$target = ReviewLib::form(review_space(), 'EV settings export target', ['allow_anonymous' => 0]);
ReviewLib::clearFields($target);
$target->description = 'Original description';
$target->setSetting('secure_send_minutes', 45);
$target->save(false);
$target = ReviewLib::reload($target);

$error = $service->importJson($target, json_encode($exported), true);
$check($error === null, 'import failed: ' . (string)$error);
$target = ReviewLib::reload($target);
$check($service->settingsApplied, 'settings were not applied on replace');
$check((int)$target->getSetting('secure_send_minutes') === 45, 'target secure send minutes were replaced');
$check((string)$target->description === 'Protocol copy', 'description was not copied');
$check((string)$target->thank_you_content === 'Thanks for taking part', 'thank-you text was not copied');
$check(empty($target->getSetting('keep_partials')), 'keep partials was not copied');
$check((int)$target->allow_anonymous === 1, 'anonymous setting was not copied');
$check(!array_key_exists('test_token', $target->getSettings()) || $target->getSetting('test_token') !== 'secret-token', 'preview token was copied');

$byVariable = [];
foreach ($target->fields as $field) {
    $byVariable[(string)$field->variable] = (int)$field->id;
}
$copiedRules = $target->getSetting('integrity', [])['consistency_rules'][0]['conditions'] ?? [];
$check((int)($copiedRules[0]['field_id'] ?? 0) === ($byVariable['age_export'] ?? 0), 'consistency rule was not pointed at the copied question');
$check(in_array('field.' . ($byVariable['age_export'] ?? 0), $target->getSetting('export', [])['exclude_columns'] ?? [], true), 'export column was not pointed at the copied question');
$check(!empty($target->getSetting('export', [])['pii_scrub']), 'PII scrubbing was not copied');

$busy = ReviewLib::form(review_space(), 'EV settings export busy', []);
ReviewLib::clearFields($busy);
ReviewLib::field($busy, FormField::TYPE_TEXT, 'Kept', ['variable' => 'kept_export']);
$busy->description = 'Leave this';
$busy->setSetting('keep_partials', true);
$busy->save(false);
$busy = ReviewLib::reload($busy);
$append = $service->importJson($busy, json_encode($exported), false);
$check($append === null, 'append import failed: ' . (string)$append);
$busy = ReviewLib::reload($busy);
$check(!$service->settingsApplied, 'append onto a form that already has questions copied settings');
$check((string)$busy->description === 'Leave this', 'append changed the form description');

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
