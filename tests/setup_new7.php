<?php
/**
 * Creates the French Best/Worst and MaxDiff form used by the browser check,
 * or prints the stored answer for that form.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\TranslationImportExportService;

$mode = $argv[1] ?? 'create';
ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();
$form = ReviewLib::form($space, 'EV R7 browser items', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);

if ($mode === 'verify') {
    $answer = FormAnswer::find()
        ->where(['form_id' => (int)$form->id, 'status' => FormAnswer::STATUS_COMPLETE, 'is_test' => 0])
        ->orderBy(['id' => SORT_DESC])
        ->one();
    if (!$answer) {
        fwrite(STDOUT, "NO_ANSWER\n");
        exit(1);
    }
    $rows = (new yii\db\Query())->from('custom_form_answer_field')->where(['answer_id' => (int)$answer->id])->all();
    $bad = false;
    foreach ($rows as $row) {
        $value = (string)$row['value'];
        fwrite(STDOUT, 'field=' . $row['field_id'] . ' value=' . $value . "\n");
        if (str_contains($value, ' | ')) {
            $bad = true;
        }
    }
    if ($bad || !count($rows)) {
        exit(1);
    }
    fwrite(STDOUT, "STORED_SOURCE\n");
    exit(0);
}

ReviewLib::clearFields($form);
$bw = ReviewLib::field($form, FormField::TYPE_BEST_WORST, 'Preference', [
    'variable' => 'r7b_bw',
    'required' => 1,
    'sort_order' => 1,
]);
$bw->setItemsConfig(['items' => ['Item', 'Other']]);
$bw->save(false);
$md = ReviewLib::field($form, FormField::TYPE_MAXDIFF, 'Sets', [
    'variable' => 'r7b_md',
    'required' => 1,
    'sort_order' => 2,
]);
$md->setItemsConfig([
    'items' => ['Item', 'Third', 'Other', 'Fourth'],
    'setSize' => 2,
    'setCount' => 2,
]);
$md->save(false);
$form = ReviewLib::publishOpen($form);
$error = (new TranslationImportExportService())->importRows($form, [
    ['key' => 'field.' . $bw->id . '.item.0', 'translations' => ['fr' => 'Élément']],
    ['key' => 'field.' . $bw->id . '.item.1', 'translations' => ['fr' => 'Autre']],
    ['key' => 'field.' . $md->id . '.item.0', 'translations' => ['fr' => 'Élément']],
    ['key' => 'field.' . $md->id . '.item.1', 'translations' => ['fr' => 'Troisième']],
    ['key' => 'field.' . $md->id . '.item.2', 'translations' => ['fr' => 'Autre']],
    ['key' => 'field.' . $md->id . '.item.3', 'translations' => ['fr' => 'Quatrième']],
]);
if ($error) {
    fwrite(STDERR, $error . "\n");
    exit(1);
}
fwrite(STDOUT, 'form=' . (int)$form->id . "\n");
