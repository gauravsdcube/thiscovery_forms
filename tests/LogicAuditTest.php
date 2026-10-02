<?php
/**
 * LOG-9. Logic design checks: duplicate page keys, missing go-to targets, a question shown
 * by its own answer, and a rule that reads a later page are each refused. A clean design
 * passes.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\LogicAudit;
use humhub\modules\thiscoveryForms\services\LogicEngine;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};
$has = static function (array $errors, string $needle): bool {
    foreach ($errors as $error) {
        if (str_contains($error, $needle)) {
            return true;
        }
    }
    return false;
};
$break = static function ($form, string $key, int $sort): FormField {
    $b = ReviewLib::field($form, FormField::TYPE_PAGE_BREAK, 'Break ' . $key, ['sort_order' => $sort]);
    $b->setPageBreakConfig(['pageKey' => $key, 'title' => $key]);
    $b->save(false);
    return $b;
};

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV LOG9 audit', ['allow_anonymous' => 1]);
ReviewLib::clearFields($form);
ReviewLib::field($form, FormField::TYPE_RADIO, 'First', ['variable' => 'first', 'sort_order' => 1, 'options' => ['a | A', 'b | B']]);
ReviewLib::field($form, FormField::TYPE_TEXT, 'Shown if first is a', ['variable' => 'dep', 'sort_order' => 2, 'logic' => LogicEngine::fromFormula('[first] = "a"')]);
ReviewLib::field($form, FormField::TYPE_RADIO, 'Jump', ['variable' => 'jump', 'sort_order' => 3, 'options' => ['y | Y', 'n | N'],
    'logic' => LogicEngine::fromFormula('[jump] = "y"', 'goto_page', 'two')]);
$break($form, 'two', 4);
ReviewLib::field($form, FormField::TYPE_TEXT, 'Page two', ['variable' => 'later', 'sort_order' => 5]);
$check(LogicAudit::errors($form) === [], 'a clean design was refused: ' . json_encode(LogicAudit::errors($form)));

$break($form, 'two', 6);
$check($has(LogicAudit::errors($form), '“two”'), 'a duplicate page key was not refused');

$form2 = ReviewLib::form(review_space(), 'EV LOG9 audit 2', ['allow_anonymous' => 1]);
ReviewLib::clearFields($form2);
ReviewLib::field($form2, FormField::TYPE_RADIO, 'Goes nowhere', ['variable' => 'nowhere', 'sort_order' => 1, 'options' => ['y | Y'],
    'logic' => LogicEngine::fromFormula('[nowhere] = "y"', 'goto_page', 'missing')]);
ReviewLib::field($form2, FormField::TYPE_TEXT, 'Self', ['variable' => 'self', 'sort_order' => 2, 'logic' => LogicEngine::fromFormula('[self] = "x"')]);
ReviewLib::field($form2, FormField::TYPE_TEXT, 'Reads ahead', ['variable' => 'ahead', 'sort_order' => 3, 'logic' => LogicEngine::fromFormula('[future] = "x"')]);
$break($form2, 'next', 4);
ReviewLib::field($form2, FormField::TYPE_TEXT, 'Future', ['variable' => 'future', 'sort_order' => 5]);
$errors = LogicAudit::errors($form2);
$check($has($errors, '“missing”'), 'a missing go-to target was not refused');
$check($has($errors, '“Self”'), 'a question shown by its own answer was not refused');
$check($has($errors, '[future]'), 'a rule reading a later page was not refused');
$check(!$has($errors, '“Goes nowhere” is shown'), 'a go-to on its own answer was refused');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
