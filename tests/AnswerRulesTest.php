<?php
/**
 * LOG-12. Text length and pattern, date format and range, and a cross-answer check are
 * enforced on submit; impossible rules are refused at save; a long answer is capped even
 * with no limit set.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\SubmitForm;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV LOG12 rules', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
ReviewLib::clearFields($form);
$code = ReviewLib::field($form, FormField::TYPE_TEXT, 'Study code', ['variable' => 'code', 'sort_order' => 1]);
$code->setValidation(['min_length' => '6', 'max_length' => '6', 'pattern' => '[A-Z]{2}[0-9]{4}', 'pattern_message' => 'Use two capitals then four digits.']);
$code->save(false);
$start = ReviewLib::field($form, FormField::TYPE_DATE, 'Start', ['variable' => 'start', 'sort_order' => 2]);
$start->setValidation(['date_min' => '2020-01-01', 'date_max' => 'today']);
$start->save(false);
$end = ReviewLib::field($form, FormField::TYPE_DATE, 'End', ['variable' => 'end', 'sort_order' => 3]);
$end->setValidation(['check' => '[end] >= [start]', 'check_message' => 'The end date must be after the start date.']);
$end->save(false);
$note = ReviewLib::field($form, FormField::TYPE_TEXT, 'Note', ['variable' => 'note', 'sort_order' => 4]);
$form = ReviewLib::publishOpen($form);

$errorsFor = static function (array $values) use ($form): array {
    $submit = new SubmitForm();
    $submit->form = ReviewLib::reload($form);
    $submit->values = $values;
    $submit->validateFields();
    return $submit->getErrors('values');
};
$has = static fn(array $errors, string $needle): bool => (bool)array_filter($errors, static fn($e) => str_contains($e, $needle));
$good = [(int)$code->id => 'AB1234', (int)$start->id => '2024-03-01', (int)$end->id => '2024-04-01', (int)$note->id => 'ok'];

$check($errorsFor($good) === [], 'valid answers were refused: ' . json_encode($errorsFor($good)));
$check($has($errorsFor([(int)$code->id => 'ab1234'] + $good), 'two capitals'), 'a pattern mismatch was accepted');
$check($has($errorsFor([(int)$code->id => 'AB123'] + $good), '6'), 'a too-short answer was accepted');
$check($has($errorsFor([(int)$start->id => '2024-02-30'] + $good), 'must be a date'), 'an impossible date was accepted');
$check($has($errorsFor([(int)$start->id => '2019-12-31'] + $good), '2020-01-01'), 'a date before the earliest was accepted');
$check($has($errorsFor([(int)$start->id => '2999-01-01', (int)$end->id => '2999-02-01'] + $good), 'on or before'), 'a date after today was accepted');
$check($has($errorsFor([(int)$end->id => '2024-01-01'] + $good), 'after the start date'), 'a failing answer check was accepted');
$check($has($errorsFor([(int)$note->id => str_repeat('x', FormField::TEXT_HARD_MAX + 1)] + $good), (string)FormField::TEXT_HARD_MAX), 'an over-long answer with no limit set was accepted');

$check(FormField::validationErrors(['pattern' => '([a-z'], FormField::TYPE_TEXT, 'X') !== [], 'a broken pattern was saved');
$check(FormField::validationErrors(['min_length' => '9', 'max_length' => '3'], FormField::TYPE_TEXT, 'X') !== [], 'min above max was saved');
$check(FormField::validationErrors(['date_min' => '2024-13-01'], FormField::TYPE_DATE, 'X') !== [], 'an impossible date limit was saved');
$check(FormField::validationErrors(['check' => '[a] = = 1'], FormField::TYPE_TEXT, 'X') !== [], 'a broken check formula was saved');
$check(FormField::validationErrors(['pattern' => '[0-9]+', 'date_min' => 'today', 'check' => '[a] > 1'], FormField::TYPE_TEXT, 'X') === [], 'valid rules were refused');

$row = FormField::findOne((int)$code->id)->toPostRow();
$check(($row['validation']['pattern'] ?? '') === '[A-Z]{2}[0-9]{4}', 'rules were not exported with the question');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
