<?php
/**
 * V3-52. Renaming a variable rewrites every reference to it; a variable cannot be named
 * like an id reference (id5); clone rewrites piped labels.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\formula\FormulaRefs;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV V3-52 rename', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
ReviewLib::clearFields($form);
$rows = [
    'a' => ['type' => FormField::TYPE_NUMBER, 'label' => 'Age', 'variable' => 'age52', 'sort_order' => 1],
    'b' => ['type' => FormField::TYPE_TEXT, 'label' => 'You are {{answer:age52}}', 'variable' => 'why52', 'sort_order' => 2,
        'logic_formula' => '[age52] > 17', 'logic_action' => 'show'],
    'c' => ['type' => FormField::TYPE_CALCULATED, 'label' => 'Double', 'variable' => 'double52', 'sort_order' => 3,
        'formula' => '[age52] * 2', 'formula_result' => 'number'],
];
$check($form->saveFieldsFromPost($rows) === true, 'the first save failed');
$byVar = static function ($form): array {
    $out = [];
    foreach (FormField::find()->where(['form_id' => (int)$form->id])->all() as $f) {
        $out[(string)$f->variable] = $f;
    }
    return $out;
};
$fields = $byVar($form);
$posted = [];
foreach (['a' => 'age52', 'b' => 'why52', 'c' => 'double52'] as $key => $var) {
    $posted[(string)$fields[$var]->id] = ['id' => (int)$fields[$var]->id] + $rows[$key];
}
$posted[(string)$fields['age52']->id]['variable'] = 'years52';
$form = ReviewLib::reload($form);
$check($form->saveFieldsFromPost($posted) === true, 'the rename save failed: ' . $form->designRefusalMessage());
$fields = $byVar(ReviewLib::reload($form));
$check(isset($fields['years52']), 'the variable was not renamed');
$check(str_contains((string)($fields['why52']->getLogic()['text'] ?? ''), '[years52]'), 'the rule still says [age52]');
$check(str_contains((string)$fields['double52']->getFormulaConfig()['formula'], '[years52]'), 'the calculation still says [age52]');
$check(str_contains((string)$fields['why52']->label, '{{answer:years52}}'), 'the piped label still says age52');

// id5 is how a formula names question 5; a variable may not take that form.
$probe = new FormField();
$probe->label = 'x';
$probe->type = FormField::TYPE_TEXT;
$probe->variable = 'id5';
$check($probe->ensureVariable() !== 'id5', 'a variable named id5 was allowed');

// Row and instance references are renamed too.
$check(FormulaRefs::renameText('[q.r1] + count_answered([q[*]]) + [q["x"]]', ['q' => 'z']) === '[z.r1] + count_answered([z[*]]) + [z["x"]]', 'row/instance references were not renamed');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
