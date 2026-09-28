<?php
/**
 * NEW-9. Rolling back the answer-field migration must not make a removed
 * question live again. This database is left migrated up.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;

$failures = [];
ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();
$form = ReviewLib::form($space, 'EV R9 rollback guard', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
ReviewLib::clearFields($form);
$keep = ReviewLib::field($form, FormField::TYPE_TEXT, 'Keep me', [
    'variable' => 'r9_keep',
    'required' => 0,
    'sort_order' => 1,
]);
$gone = ReviewLib::field($form, FormField::TYPE_TEXT, 'Remove me', [
    'variable' => 'r9_gone',
    'required' => 0,
    'sort_order' => 2,
]);
$form = ReviewLib::publishOpen($form);
$answer = new humhub\modules\thiscoveryForms\models\FormAnswer();
$answer->form_id = (int)$form->id;
$answer->status = humhub\modules\thiscoveryForms\models\FormAnswer::STATUS_COMPLETE;
$answer->is_test = 1;
$answer->submitted_at = date('Y-m-d H:i:s');
$answer->save(false);
Yii::$app->db->createCommand()->insert('custom_form_answer_field', [
    'answer_id' => (int)$answer->id,
    'field_id' => (int)$gone->id,
    'value' => 'kept',
])->execute();
$gone->softDelete();

$empty = ReviewLib::field($form, FormField::TYPE_TEXT, 'Empty removed', [
    'variable' => 'r9_empty',
    'required' => 0,
    'sort_order' => 3,
]);
$empty->softDelete();

require_once dirname(__DIR__) . '/migrations/m260926_130000_answer_field_restrict.php';
require_once dirname(__DIR__) . '/migrations/m260928_190000_soft_delete_rollback_guard.php';
$migration = new m260926_130000_answer_field_restrict();
$guard = new m260928_190000_soft_delete_rollback_guard();
if ($migration->safeDown() !== false) {
    $failures[] = 'rollback was allowed while a removed question has answers';
}
if ($guard->safeDown() !== false) {
    $failures[] = 'guard rollback was allowed while a removed question has answers';
}

$schema = Yii::$app->db->getTableSchema('custom_form_field', true);
if ($schema === null || !isset($schema->columns['deleted_at'])) {
    $failures[] = 'deleted_at was dropped';
}
$still = FormField::findOne((int)$gone->id);
if (!$still || $still->deleted_at === null) {
    $failures[] = 'the removed question became live';
}
$emptyRow = FormField::findOne((int)$empty->id);
if (!$emptyRow || $emptyRow->deleted_at === null) {
    $failures[] = 'the answer-less removed question was deleted or revived during the refused rollback';
}
$rule = (new yii\db\Query())
    ->select('DELETE_RULE')
    ->from('information_schema.REFERENTIAL_CONSTRAINTS')
    ->where([
        'CONSTRAINT_SCHEMA' => Yii::$app->db->createCommand('SELECT DATABASE()')->queryScalar(),
        'CONSTRAINT_NAME' => 'fk_cfaf_field',
    ])
    ->scalar();
if ($rule !== 'RESTRICT') {
    $failures[] = 'foreign key is ' . $rule;
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS kept=" . (int)$keep->id . " removed=" . (int)$gone->id . "\n";
