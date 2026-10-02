<?php
/**
 * High findings still open after 1.28.7: snapshot binding, import variables,
 * piping ids, answer audit, user erasure, similarity, best-worst, and grids.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\dashboard\MatrixQuestionType;
use humhub\modules\thiscoveryForms\services\dashboard\RankedQuestionType;
use humhub\modules\thiscoveryForms\services\ErasureService;
use humhub\modules\thiscoveryForms\services\FormCloneService;
use humhub\modules\thiscoveryForms\services\FormSnapshotService;
use humhub\modules\thiscoveryForms\services\integrity\IntegrityService;
use humhub\modules\thiscoveryForms\services\QuestionImportExportService;
use yii\db\Query;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

$admin = review_user('review_netadmin');
ReviewLib::asUser($admin);
$space = review_space();

$form = ReviewLib::form($space, 'EV high DAT-4', ['allow_anonymous' => 0, 'allow_multiple' => 1]);
ReviewLib::clearFields($form);
$live = ReviewLib::field($form, FormField::TYPE_TEXT, 'Age', ['variable' => 'age_live']);
$ghosts = [];
foreach ([900001, 900002] as $id) {
    $ghost = new FormField();
    $ghost->id = $id;
    $ghost->form_id = (int)$form->id;
    $ghost->type = FormField::TYPE_TEXT;
    $ghost->label = 'Age';
    $ghost->variable = '';
    $ghosts[] = $ghost;
}
$method = new ReflectionMethod(FormSnapshotService::class, 'bindHydratedFieldsToLiveRows');
$method->setAccessible(true);
$method->invoke(new FormSnapshotService(), $form, $ghosts);
$bound = [(int)$ghosts[0]->id, (int)$ghosts[1]->id];
$check(count(array_unique($bound)) === 2, 'two snapshot questions bound to ' . implode(',', $bound));
$check(in_array((int)$live->id, $bound, true), 'neither snapshot question bound to the live field');
$check(count(array_filter($bound, static fn(int $id): bool => $id === (int)$live->id)) === 1, 'the live field was used twice');

$importForm = ReviewLib::form($space, 'EV high DAT-5', ['allow_anonymous' => 0, 'allow_multiple' => 1]);
ReviewLib::clearFields($importForm);
$existingAge = ReviewLib::field($importForm, FormField::TYPE_TEXT, 'Age', ['variable' => 'age']);
$importError = (new QuestionImportExportService())->appendFieldPayloads($importForm, [[
    'type' => 'text',
    'label' => 'Imported age',
    'variable' => 'age',
    'logic_formula' => '[age] = "yes"',
    'logic_action' => 'show',
]]);
// The colliding name is renamed, so [age] would follow it and the question would be
// shown by its own answer. LOG-9 refuses that save and keeps the existing question.
$check(is_string($importError) && str_contains($importError, 'own answer'), 'a self-showing import was accepted: ' . (string)$importError);
$imported = FormField::find()->where(['form_id' => (int)$importForm->id, 'label' => 'Imported age'])->one();
$existingAge->refresh();
$check($imported === null, 'the refused import was stored');
$check($existingAge->variable === 'age', 'the existing age variable was renamed');

$source = ReviewLib::form($space, 'EV high DAT-6', ['allow_anonymous' => 0, 'allow_multiple' => 1]);
ReviewLib::clearFields($source);
$piped = ReviewLib::field($source, FormField::TYPE_RICH_TEXT, 'Note', ['variable' => 'note']);
$piped->setRichTextContent('See {{answer:' . (int)$piped->id . '}}');
$piped->save(false);
$source->thank_you_content = 'Thanks {{answer:' . (int)$piped->id . '}}';
$source->answers_visibility = $source->answers_visibility ?: CustomForm::ANSWERS_PERMISSION;
$source->save(false, ['thank_you_content', 'answers_visibility']);
$target = new CustomForm($space);
$target->answers_visibility = CustomForm::ANSWERS_PERMISSION;
$copied = (new FormCloneService())->copyInto($source, $target, ['title' => 'EV high DAT-6 clone', 'silent' => true]);
$check($copied, 'clone failed');
if ($copied) {
    $cloneField = FormField::find()->where(['form_id' => (int)$target->id, 'type' => FormField::TYPE_RICH_TEXT])->one();
    $check($cloneField instanceof FormField, 'cloned rich text is missing');
    if ($cloneField instanceof FormField) {
        $html = $cloneField->getRichTextContent();
        $check(str_contains($html, '{{answer:' . (int)$cloneField->id . '}}'), 'cloned piping still uses the source question id');
        $check(!str_contains($html, '{{answer:' . (int)$piped->id . '}}') || (int)$cloneField->id === (int)$piped->id, 'cloned piping kept the old id');
    }
    $target->refresh();
    $check(str_contains((string)$target->thank_you_content, '{{answer:'), 'thank-you piping was dropped');
}

$auditForm = ReviewLib::form($space, 'EV high GOV-3', ['allow_anonymous' => 0, 'allow_multiple' => 1]);
ReviewLib::clearFields($auditForm);
$note = ReviewLib::field($auditForm, FormField::TYPE_TEXT, 'Note', ['variable' => 'note', 'required' => 0]);
$auditForm = ReviewLib::publishOpen($auditForm);
$first = ReviewLib::submit($auditForm, [(int)$note->id => 'one']);
$check($first !== null, 'first answer was not saved');
if ($first) {
    $auditCount = (new Query())->from('{{%custom_form_answer_audit}}')->where(['answer_id' => (int)$first->id])->count();
    $check((int)$auditCount === 0, 'the first save wrote an audit row');
    $second = ReviewLib::submit($auditForm, [(int)$note->id => 'two'], false, $first);
    $check($second !== null, 'edited answer was not saved');
    $row = (new Query())->from('{{%custom_form_answer_audit}}')->where(['answer_id' => (int)$first->id])->one();
    $check(is_array($row), 'editing an answer wrote no audit row');
    if (is_array($row)) {
        $check((string)$row['old_value'] === 'one' || str_contains((string)$row['old_value'], 'one'), 'audit old value was ' . (string)$row['old_value']);
        $check((string)$row['new_value'] === 'two' || str_contains((string)$row['new_value'], 'two'), 'audit new value was ' . (string)$row['new_value']);
        $check((int)$row['actor_id'] === (int)$admin->id, 'audit actor was ' . (string)$row['actor_id']);
        $check((string)$row['reason'] !== '', 'audit reason was empty');
    }
}

$eraseForm = ReviewLib::form($space, 'EV high GOV-4', ['allow_anonymous' => 0, 'allow_multiple' => 1]);
ReviewLib::clearFields($eraseForm);
$kept = ReviewLib::field($eraseForm, FormField::TYPE_TEXT, 'Kept', ['variable' => 'kept', 'required' => 0]);
$eraseForm = ReviewLib::publishOpen($eraseForm);
$eraseAnswer = ReviewLib::submit($eraseForm, [(int)$kept->id => 'research']);
$check($eraseAnswer !== null && $eraseAnswer->created_by !== null, 'erasure fixture has no identity');
if ($eraseAnswer) {
    (new ErasureService())->pseudonymiseAnswer($eraseAnswer);
    $eraseAnswer->refresh();
    $check($eraseAnswer->created_by === null, 'created_by remained after erasure');
    $check($eraseAnswer->resume_email === null || $eraseAnswer->resume_email === '', 'resume email remained');
    $still = (new Query())->from('custom_form_answer_field')->where(['answer_id' => (int)$eraseAnswer->id, 'field_id' => (int)$kept->id])->one();
    $check(is_array($still) && str_contains((string)$still['value'], 'research'), 'research answer was deleted');
    $logged = (new Query())->from('{{%custom_form_erasure}}')->where(['answer_id' => (int)$eraseAnswer->id])->exists();
    $check($logged, 'erasure was not recorded');
}

$simForm = ReviewLib::form($space, 'EV high INT-3', ['allow_anonymous' => 0, 'allow_multiple' => 1]);
ReviewLib::clearFields($simForm);
$left = [];
$right = [];
for ($i = 1; $i <= 8; $i++) {
    $q = ReviewLib::field($simForm, FormField::TYPE_RADIO, 'Q' . $i, ['variable' => 'q' . $i, 'sort_order' => $i]);
    $left[(int)$q->id] = '3';
    $right[(int)$q->id] = $i === 1 ? '1' : '3';
}
$simForm = ReviewLib::reload($simForm);
$percent = (new IntegrityService())->choiceSimilarity($simForm, $left, $right);
$check($percent < 90, 'one different answer of eight scored ' . $percent);

$ranked = (new RankedQuestionType('best_worst', 'Best-worst'))->extractCells(['best' => 'A', 'worst' => 'B'], []);
$byCode = [];
foreach ($ranked as $cell) {
    $byCode[$cell['bucket_key']] = $cell['value'];
}
$check(($byCode['A'] ?? 0) > 0, 'best did not score positive');
$check(($byCode['B'] ?? 0) < 0, 'worst scored ' . (string)($byCode['B'] ?? 'missing'));

$matrix = (new MatrixQuestionType('grid_multi', 'Grid'))->extractCells(['mood' => ['happy', 'sad']], []);
$keys = array_map(static fn(array $cell): string => (string)$cell['bucket_key'], $matrix);
$check(in_array('mood|happy', $keys, true), 'grid key missing mood|happy: ' . implode(',', $keys));
$check(!in_array('mood|0', $keys, true), 'grid key used the list index');
$zero = (new MatrixQuestionType('grid_multi', 'Grid'))->extractCells(['pain' => ['0', '2']], []);
$zeroKeys = array_map(static fn(array $cell): string => (string)$cell['bucket_key'], $zero);
$check(in_array('pain|0', $zeroKeys, true) && in_array('pain|2', $zeroKeys, true), 'a grid column coded 0 was dropped: ' . implode(',', $zeroKeys));

$gridForm = ReviewLib::form($space, 'EV high A11Y', ['allow_anonymous' => 0, 'allow_multiple' => 1]);
ReviewLib::clearFields($gridForm);
$grid = ReviewLib::field($gridForm, FormField::TYPE_GRID_SINGLE, 'Mood grid', ['variable' => 'mood_grid', 'required' => 1]);
$grid->setGridConfig([
    'rows' => ['Mood'],
    'columns' => ['Happy', 'Sad'],
]);
$grid->save(false);
$radio = ReviewLib::field($gridForm, FormField::TYPE_RADIO, 'Agreement', [
    'variable' => 'agreement',
    'required' => 1,
    'options' => ['yes' => 'Yes', 'no' => 'No'],
]);
$renderFill = static function (FormField $field) use ($gridForm, $grid, $radio): string {
    $params = [
        'field' => $field,
        'value' => '',
        'displayIndex' => 1,
        'formModel' => $gridForm,
        'allValues' => [],
        'allFields' => [$grid, $radio],
        'panelMember' => null,
        'rewriteFileUrls' => static fn(string $html): string => $html,
    ];
    extract($params, EXTR_SKIP);
    ob_start();
    include dirname(__DIR__) . '/views/form/_field_fill.php';
    return (string)ob_get_clean();
};
$html = $renderFill($grid);
$radioHtml = $renderFill($radio);
$check(str_contains($html, 'aria-label='), 'grid control has no accessible name');
$check(str_contains($html, 'scope="row"') && str_contains($html, 'scope="col"'), 'grid headers have no scope');
$check(str_contains($radioHtml, 'role="radiogroup"'), 'radio question is not a group');
$check(str_contains($radioHtml, 'aria-labelledby='), 'radio group is not labelled by the question');
$check(str_contains($radioHtml, 'aria-required'), 'required is not exposed on the radio group');

if ($failures) {
    echo count($failures) . " failed\n";
    exit(1);
}
echo "OK\n";
