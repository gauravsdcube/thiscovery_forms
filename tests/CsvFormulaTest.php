<?php
/**
 * HB-5. CSV exports neutralise cells that a spreadsheet would treat as a formula.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\helpers\CsvCell;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\ExportService;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

foreach (['=1+1', '+1', '-1', '@cmd', "\tcmd", "\rcmd"] as $raw) {
    $safe = CsvCell::neutralise($raw);
    $check($safe !== $raw && $safe[0] === "'", 'cell was not neutralised: ' . json_encode($raw));
    $check(CsvCell::restore($safe) === $raw, 'cell did not round-trip: ' . json_encode($raw));
}
$check(CsvCell::neutralise('hello') === 'hello', 'a plain cell was changed');

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV HB5 csv', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
ReviewLib::clearFields($form);
$field = ReviewLib::field($form, FormField::TYPE_TEXT, 'Note', ['variable' => 'note']);
$form = ReviewLib::publishOpen($form);
$answer = new FormAnswer();
$answer->form_id = (int)$form->id;
$answer->status = FormAnswer::STATUS_COMPLETE;
$answer->is_test = 0;
$answer->submitted_at = date('Y-m-d H:i:s');
$answer->save(false);
Yii::$app->db->createCommand()->insert('custom_form_answer_field', [
    'answer_id' => (int)$answer->id,
    'field_id' => (int)$field->id,
    'value' => '=1+1',
])->execute();

$csv = (new ExportService())->toCsv(ReviewLib::reload($form));
$rows = array_map('str_getcsv', preg_split('/\r\n|\r|\n/', trim($csv)) ?: []);
$flat = [];
foreach ($rows as $row) {
    foreach ($row as $cell) {
        $flat[] = (string)$cell;
    }
}
$check(in_array("'=1+1", $flat, true), 'exported answer still starts with =');
$check(!in_array('=1+1', $flat, true), 'raw formula was exported');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
