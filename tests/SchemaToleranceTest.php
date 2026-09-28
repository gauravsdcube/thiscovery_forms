<?php
/**
 * NEW-13. Queries must not mention deleted_at when that column is not
 * there yet, and the foreign-key guard must see the existing RESTRICT rule.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;

$failures = [];
ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV R13 schema tolerance', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);

if (!FormField::supportsSoftDelete()) {
    $failures[] = 'deleted_at should be present on this database';
}

FormField::$softDeleteOverride = false;
$liveSql = $form->getFields()->createCommand()->rawSql;
$allSql = $form->getAllFields()->createCommand()->rawSql;
FormField::$softDeleteOverride = null;
if (stripos($liveSql, 'deleted_at') !== false) {
    $failures[] = 'live field query still uses deleted_at';
}
if (stripos($allSql, 'deleted_at') !== false) {
    $failures[] = 'all-field query still uses deleted_at';
}

require_once dirname(__DIR__) . '/migrations/m260928_200000_answer_field_fk_guard.php';
$migration = new m260928_200000_answer_field_fk_guard();
if ($migration->deleteRule() !== 'RESTRICT') {
    $failures[] = 'delete rule is ' . $migration->deleteRule();
}
if ($migration->safeUp() !== true) {
    $failures[] = 'guard migration did not finish';
}
if ($migration->deleteRule() !== 'RESTRICT') {
    $failures[] = 'delete rule changed';
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
