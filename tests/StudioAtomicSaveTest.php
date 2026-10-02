<?php
/**
 * V3-37. A studio save that fails a design check writes nothing: no renamed, added or
 * deleted question survives, and the reasons are reported.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\FormSnapshotService;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV V3-37 atomic save', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
ReviewLib::clearFields($form);
$saved = $form->saveFieldsFromPost([
    'a' => ['type' => FormField::TYPE_TEXT, 'label' => 'First', 'variable' => 'v337_a', 'sort_order' => 1],
    'b' => ['type' => FormField::TYPE_TEXT, 'label' => 'Second', 'variable' => 'v337_b', 'sort_order' => 2],
]);
$check($saved === true, 'the valid design did not save');
$before = FormField::find()->where(['form_id' => (int)$form->id])->orderBy(['sort_order' => SORT_ASC])->all();
$snapshot = array_map(static fn(FormField $f) => [(int)$f->id, (string)$f->label, (string)$f->variable], $before);
$firstId = (int)$before[0]->id;

// Rename "First", drop "Second", add a page and a backward go-to: refused as a whole.
$rows = [
    (string)$firstId => ['id' => $firstId, 'type' => FormField::TYPE_TEXT, 'label' => 'Renamed', 'variable' => 'v337_a', 'sort_order' => 1],
    'br' => ['type' => FormField::TYPE_PAGE_BREAK, 'label' => 'Later', 'sort_order' => 2, 'page_key' => 'later'],
    'c' => [
        'type' => FormField::TYPE_RADIO, 'label' => 'Gate', 'variable' => 'v337_gate', 'sort_order' => 3,
        'options' => ['yes', 'no'], 'logic_action' => 'goto_page', 'logic_goto' => 'start',
        'logic_formula' => '[v337_a] = "yes"',
    ],
];
$form = ReviewLib::reload($form);
$refused = $form->saveFieldsFromPost($rows);
$check($refused === false, 'an invalid design was accepted');
$message = $form->designRefusalMessage();
$check(str_contains($message, 'not saved') && str_contains($message, 'earlier page'), 'the refusal did not give the reason: ' . $message);
$after = FormField::find()->where(['form_id' => (int)$form->id])->orderBy(['sort_order' => SORT_ASC])->all();
$check(array_map(static fn(FormField $f) => [(int)$f->id, (string)$f->label, (string)$f->variable], $after) === $snapshot,
    'a refused save still changed the stored questions');

// The studio can re-render the author's unsaved design.
$unsaved = (new FormSnapshotService())->fieldsFromRows($form, $rows);
$check(count($unsaved) === 3 && (string)$unsaved[0]->label === 'Renamed' && (int)$unsaved[0]->id === $firstId, 'the unsaved design could not be rebuilt for the studio');

// Inside an outer transaction the refusal is a savepoint, and the outer work survives.
$tx = Yii::$app->db->beginTransaction();
$form = ReviewLib::reload($form);
$form->saveFieldsFromPost($rows);
$check(Yii::$app->db->getTransaction() !== null, 'a nested refusal ended the outer transaction');
$tx->rollBack();

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
