<?php
/**
 * SCO-12. The answers CSV is ready for analysis: complete responses only by default, a 0/1
 * column per multiple-choice option, and codes for why an answer is empty.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\ExportService;
use humhub\modules\thiscoveryForms\services\ExportSettings;
use humhub\modules\thiscoveryForms\services\LogicEngine;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV SCO12 export', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
ReviewLib::clearFields($form);
$gate = ReviewLib::field($form, FormField::TYPE_RADIO, 'Smoker', ['variable' => 's12_smoker', 'sort_order' => 1, 'options' => ['y | Yes', 'n | No']]);
$how = ReviewLib::field($form, FormField::TYPE_TEXT, 'How many', ['variable' => 's12_how', 'sort_order' => 2, 'logic' => LogicEngine::fromFormula('[s12_smoker] = "y"')]);
$sym = ReviewLib::field($form, FormField::TYPE_CHECKBOX, 'Symptoms', ['variable' => 's12_sym', 'sort_order' => 3, 'options' => [['code' => 'c', 'label' => 'Cough'], ['code' => 'f', 'label' => 'Fever']]]);
$note = ReviewLib::field($form, FormField::TYPE_TEXT, 'Note', ['variable' => 's12_note', 'sort_order' => 4]);
$form = ReviewLib::publishOpen($form);

ReviewLib::submit($form, [(int)$gate->id => 'n', (int)$sym->id => ['c']]);
ReviewLib::submit($form, [(int)$gate->id => 'y', (int)$how->id => 'draft only'], true);

$csv = (new ExportService())->toCsv(ReviewLib::reload($form), ['header_mode' => ExportService::HEADER_VARIABLE]);
$rows = array_map('str_getcsv', preg_split('/\r\n|\r|\n/', trim($csv)) ?: []);
$header = $rows[0] ?? [];
$check(count($rows) === 2, 'the in-progress response was exported by default (' . (count($rows) - 1) . ' rows)');
$data = array_combine($header, array_pad($rows[1] ?? [], count($header), ''));
$check(($data['s12_sym_c'] ?? null) === '1' && ($data['s12_sym_f'] ?? null) === '0', 'option columns are not 1/0: ' . json_encode(array_intersect_key($data, array_flip(['s12_sym_c', 's12_sym_f']))));
$check(($data['s12_how'] ?? null) === ExportSettings::MISSING_HIDDEN, 'a question hidden by logic was not coded -98: ' . ($data['s12_how'] ?? 'missing'));
$check(($data['s12_note'] ?? null) === ExportSettings::MISSING_SKIPPED, 'a shown, unanswered question was not coded -99: ' . ($data['s12_note'] ?? 'missing'));
$check(!str_contains($csv, 'draft only'), 'a draft answer reached the export');

$withDrafts = (new ExportService())->toCsv(ReviewLib::reload($form), ['include_in_progress' => '1']);
$check(str_contains($withDrafts, 'draft only'), 'in-progress responses could not be included');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
