<?php
/**
 * NEW-7. Grid rows without codes keep their translation. Best/Worst and MaxDiff
 * show the translation and store the source item.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormFieldI18n;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\thiscoveryForms\services\TranslationImportExportService;
use humhub\modules\thiscoveryForms\services\TranslationService;

$failures = [];
ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();
$form = ReviewLib::form($space, 'EV R7 grid and items', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
ReviewLib::clearFields($form);

$grid = ReviewLib::field($form, FormField::TYPE_GRID_SINGLE, 'Grid', [
    'variable' => 'r7_grid',
    'required' => 1,
    'sort_order' => 1,
]);
$grid->setGridConfig([
    'rows' => ['Apple', 'Pear'],
    'columns' => ['Good', 'Bad'],
]);
$grid->save(false);

$bw = ReviewLib::field($form, FormField::TYPE_BEST_WORST, 'Preference', [
    'variable' => 'r7_bw',
    'required' => 1,
    'sort_order' => 2,
]);
$bw->setItemsConfig(['items' => ['Item', 'Other']]);
$bw->save(false);

$md = ReviewLib::field($form, FormField::TYPE_MAXDIFF, 'Sets', [
    'variable' => 'r7_md',
    'required' => 1,
    'sort_order' => 3,
]);
$md->setItemsConfig([
    'items' => ['Item', 'Third', 'Other', 'Fourth'],
    'setSize' => 2,
    'setCount' => 2,
]);
$md->save(false);
$form = ReviewLib::publishOpen($form);

$svc = new TranslationImportExportService();
$error = $svc->importRows($form, [
    ['key' => 'field.' . $grid->id . '.grid_row.Apple', 'translations' => ['fr' => 'Pomme']],
    ['key' => 'field.' . $grid->id . '.grid_row.Pear', 'translations' => ['fr' => 'Poire']],
    ['key' => 'field.' . $grid->id . '.grid_column.Good', 'translations' => ['fr' => 'Bon']],
    ['key' => 'field.' . $bw->id . '.item.0', 'translations' => ['fr' => 'Élément']],
    ['key' => 'field.' . $bw->id . '.item.1', 'translations' => ['fr' => 'Autre']],
    ['key' => 'field.' . $md->id . '.item.0', 'translations' => ['fr' => 'Élément']],
    ['key' => 'field.' . $md->id . '.item.2', 'translations' => ['fr' => 'Autre']],
]);
if ($error) {
    $failures[] = 'import: ' . $error;
}

$form = ReviewLib::reload($form);
(new TranslationService())->overlay($form, 'fr');
$gridFr = null;
$bwFr = null;
$mdFr = null;
foreach ($form->fields as $field) {
    if ((int)$field->id === (int)$grid->id) {
        $gridFr = $field;
    }
    if ((int)$field->id === (int)$bw->id) {
        $bwFr = $field;
    }
    if ((int)$field->id === (int)$md->id) {
        $mdFr = $field;
    }
}
$rows = $gridFr ? $gridFr->getGridConfig()['rows'] : [];
$apple = $rows[0] ?? [];
if (($apple['label'] ?? '') !== 'Pomme' || ($apple['value'] ?? '') !== 'Apple') {
    $failures[] = 'grid row ' . json_encode($apple);
}
$cols = $gridFr ? $gridFr->getGridConfig()['columns'] : [];
if (($cols[0]['label'] ?? '') !== 'Bon' || ($cols[0]['value'] ?? '') !== 'Good') {
    $failures[] = 'grid column ' . json_encode($cols[0] ?? null);
}

$export = $svc->exportJson(ReviewLib::reload($form));
$byKey = [];
foreach ($export['strings'] as $row) {
    $byKey[$row['key']] = $row['translations']['fr'] ?? '';
}
if (($byKey['field.' . $grid->id . '.grid_row.Apple'] ?? '') !== 'Pomme') {
    $failures[] = 'grid round trip lost Pomme';
}

$items = $bwFr ? $bwFr->getItemsConfig()['items'] : [];
if ($items !== ['Item', 'Other']) {
    $failures[] = 'best/worst items were rewritten ' . json_encode($items);
}
$shown = $bwFr ? $bwFr->itemDisplayLabel('Item') : '';
if ($shown !== 'Élément') {
    $failures[] = 'best/worst display ' . json_encode($shown);
}

$mdItems = $mdFr ? $mdFr->getItemsConfig()['items'] : [];
$mdSets = $mdFr ? $mdFr->getItemsConfig()['sets'] : [];
foreach ($mdItems as $item) {
    if (str_contains((string)$item, ' | ')) {
        $failures[] = 'maxdiff item rewritten ' . $item;
    }
}
foreach ($mdSets as $set) {
    foreach ($set as $item) {
        if (str_contains((string)$item, ' | ')) {
            $failures[] = 'maxdiff set rewritten ' . $item;
        }
    }
}
if ($mdFr && $mdFr->itemDisplayLabel('Item') !== 'Élément') {
    $failures[] = 'maxdiff display ' . $mdFr->itemDisplayLabel('Item');
}
if ($mdFr && $mdFr->itemDisplayLabel('Third') !== 'Third') {
    $failures[] = 'untranslated maxdiff item changed';
}

$pipe = new FormFieldI18n();
$pipe->field_id = (int)$bw->id;
$pipe->language = 'de';
$pipe->label = 'Vorliebe';
$pipe->options_json = json_encode(['items' => ['Item | Element', 'Other | Andere']], JSON_UNESCAPED_UNICODE);
$pipe->save(false);
require_once dirname(__DIR__) . '/migrations/m260928_181000_item_translation_labels.php';
(new m260928_181000_item_translation_labels())->safeUp();
$pipe->refresh();
$repaired = json_decode((string)$pipe->options_json, true);
if (($repaired['items'] ?? null) !== ['Element', 'Andere']) {
    $failures[] = 'pipe overlay was not repaired ' . json_encode($repaired['items'] ?? null);
}

ReviewLib::asUser(null);
$submit = new SubmitForm();
$submit->form = $form;
$bwValue = ['best' => 'Item', 'worst' => 'Other'];
$mdValue = ['sets' => []];
foreach ($mdSets as $set) {
    $set = array_values(array_map('strval', $set));
    $mdValue['sets'][] = ['best' => $set[0], 'worst' => ($set[1] ?? $set[0])];
}
$submit->values = [
    (int)$grid->id => ['Apple' => 'Good', 'Pear' => 'Bad'],
    (int)$bw->id => $bwValue,
    (int)$md->id => $mdValue,
];
$answer = $submit->save(null, true, false, false);
if (!$answer) {
    $failures[] = 'submit ' . implode(' ', $submit->getErrorSummary(true));
} else {
    $stored = (new yii\db\Query())->from('custom_form_answer_field')->where([
        'answer_id' => (int)$answer->id,
        'field_id' => (int)$bw->id,
    ])->one();
    $raw = (string)($stored['value'] ?? '');
    if (!str_contains($raw, 'Item') || str_contains($raw, ' | ')) {
        $failures[] = 'stored best/worst ' . $raw;
    }
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
