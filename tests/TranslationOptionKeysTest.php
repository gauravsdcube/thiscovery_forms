<?php
/**
 * NEW-6. Choice translations are keyed by code, including codes that look like positions.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\TranslationImportExportService;
use humhub\modules\thiscoveryForms\services\TranslationService;

$failures = [];
ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();
$form = ReviewLib::form($space, 'EV R6 option codes', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
ReviewLib::clearFields($form);
$scale = ReviewLib::field($form, FormField::TYPE_RADIO, 'Scale', [
    'variable' => 'r6_scale',
    'required' => 1,
    'sort_order' => 1,
    'options' => ['1 | One', '2 | Two', '3 | Three', '4 | Four', '5 | Five'],
]);
$sparse = ReviewLib::field($form, FormField::TYPE_RADIO, 'Sparse', [
    'variable' => 'r6_sparse',
    'required' => 1,
    'sort_order' => 2,
    'options' => ['1 | Yes', '2 | No', '9 | Unknown'],
]);
$zero = ReviewLib::field($form, FormField::TYPE_RADIO, 'Zero', [
    'variable' => 'r6_zero',
    'required' => 1,
    'sort_order' => 3,
    'options' => ['0 | None', '1 | Low', '2 | Mid', '3 | High', '4 | Top'],
]);
$named = ReviewLib::field($form, FormField::TYPE_RADIO, 'Named', [
    'variable' => 'r6_named',
    'required' => 1,
    'sort_order' => 4,
    'options' => ['yes | Yes', 'no | No'],
]);
$form = ReviewLib::publishOpen($form);

$svc = new TranslationImportExportService();
$export = $svc->exportJson($form);
$keys = array_column($export['strings'], 'key');
foreach ([
    'field.' . $scale->id . '.option.c:1',
    'field.' . $scale->id . '.option.c:5',
    'field.' . $sparse->id . '.option.c:9',
    'field.' . $zero->id . '.option.c:0',
    'field.' . $named->id . '.option.c:yes',
] as $expected) {
    if (!in_array($expected, $keys, true)) {
        $failures[] = 'export missing ' . $expected;
    }
}

$fr = static function (int $id, string $code, string $label): array {
    return ['key' => 'field.' . $id . '.option.c:' . $code, 'translations' => ['fr' => $label]];
};
$error = $svc->importRows($form, [
    $fr((int)$scale->id, '1', 'Un'),
    $fr((int)$scale->id, '2', 'Deux'),
    $fr((int)$scale->id, '3', 'Trois'),
    $fr((int)$scale->id, '4', 'Quatre'),
    $fr((int)$scale->id, '5', 'Cinq'),
    $fr((int)$sparse->id, '1', 'Oui'),
    $fr((int)$sparse->id, '2', 'Non'),
    $fr((int)$sparse->id, '9', 'Inconnu'),
    $fr((int)$zero->id, '0', 'Aucun'),
    $fr((int)$zero->id, '1', 'Bas'),
    $fr((int)$zero->id, '2', 'Milieu'),
    $fr((int)$zero->id, '3', 'Haut'),
    $fr((int)$zero->id, '4', 'Sommet'),
]);
if ($error) {
    $failures[] = 'import: ' . $error;
}

$labels = static function ($form, int $fieldId): array {
    $form = ReviewLib::reload($form);
    (new TranslationService())->overlay($form, 'fr');
    foreach ($form->fields as $field) {
        if ((int)$field->id === $fieldId) {
            $out = [];
            foreach ($field->getChoicePairs() as $pair) {
                $out[(string)$pair['code']] = (string)$pair['label'];
            }
            return $out;
        }
    }
    return [];
};

$scaleLabels = $labels($form, (int)$scale->id);
if (($scaleLabels['1'] ?? '') !== 'Un' || ($scaleLabels['5'] ?? '') !== 'Cinq') {
    $failures[] = 'scale labels ' . json_encode($scaleLabels);
}
$sparseLabels = $labels($form, (int)$sparse->id);
if (($sparseLabels['1'] ?? '') !== 'Oui' || ($sparseLabels['9'] ?? '') !== 'Inconnu') {
    $failures[] = 'sparse labels ' . json_encode($sparseLabels);
}
$zeroLabels = $labels($form, (int)$zero->id);
if (($zeroLabels['0'] ?? '') !== 'Aucun' || ($zeroLabels['4'] ?? '') !== 'Sommet') {
    $failures[] = 'zero-based labels ' . json_encode($zeroLabels);
}

$again = $svc->exportJson(ReviewLib::reload($form));
$byKey = [];
foreach ($again['strings'] as $row) {
    $byKey[$row['key']] = $row['translations']['fr'] ?? '';
}
if (($byKey['field.' . $scale->id . '.option.c:1'] ?? '') !== 'Un') {
    $failures[] = 'scale round trip lost Un';
}
if (($byKey['field.' . $sparse->id . '.option.c:9'] ?? '') !== 'Inconnu') {
    $failures[] = 'sparse round trip lost Inconnu';
}
if (($byKey['field.' . $zero->id . '.option.c:0'] ?? '') !== 'Aucun') {
    $failures[] = 'zero-based round trip lost Aucun';
}

$old = $svc->importRows(ReviewLib::reload($form), [
    ['key' => 'field.' . $scale->id . '.option.1', 'translations' => ['cy' => 'Un']],
    ['key' => 'field.' . $scale->id . '.option.5', 'translations' => ['cy' => 'Pump']],
    ['key' => 'field.' . $named->id . '.option.0', 'translations' => ['cy' => 'Ie']],
    ['key' => 'field.' . $named->id . '.option.1', 'translations' => ['cy' => 'Na']],
]);
if ($old) {
    $failures[] = 'old import: ' . $old;
}
$cy = static function ($form, int $fieldId): array {
    $form = ReviewLib::reload($form);
    (new TranslationService())->overlay($form, 'cy');
    foreach ($form->fields as $field) {
        if ((int)$field->id === $fieldId) {
            $out = [];
            foreach ($field->getChoicePairs() as $pair) {
                $out[(string)$pair['code']] = (string)$pair['label'];
            }
            return $out;
        }
    }
    return [];
};
$oldScale = $cy($form, (int)$scale->id);
if (($oldScale['1'] ?? '') !== 'Un' || ($oldScale['5'] ?? '') !== 'Pump') {
    $failures[] = 'old numeric key was not matched to the code ' . json_encode($oldScale);
}
$oldNamed = $cy($form, (int)$named->id);
if (($oldNamed['yes'] ?? '') !== 'Ie' || ($oldNamed['no'] ?? '') !== 'Na') {
    $failures[] = 'old positional key was not applied ' . json_encode($oldNamed);
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
