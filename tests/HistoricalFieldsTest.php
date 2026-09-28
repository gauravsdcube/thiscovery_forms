<?php
/**
 * NEW-12 / SCO-18. Scoring and the completion rate count removed questions
 * that still have answers. A fully anonymous form does not show a unique-respondent count.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\DashboardService;
use humhub\modules\thiscoveryForms\services\Eq5dService;

$failures = [];
ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV R12 historical fields', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
    'kind' => CustomForm::KIND_EQ5D,
    'identity_mode' => CustomForm::IDENTITY_IDENTIFIED,
]);
ReviewLib::clearFields($form);
$ids = [];
foreach (Eq5dService::dimensionRoles() as $i => $role) {
    $field = ReviewLib::field($form, FormField::TYPE_RADIO, 'D' . ($i + 1), [
        'variable' => 'r12_d' . ($i + 1),
        'sort_order' => $i,
        'options' => ['Level 1', 'Level 2'],
    ]);
    $field->setInstrumentRole($role);
    $field->save(false);
    $ids[] = (int)$field->id;
}
$live = ReviewLib::field($form, FormField::TYPE_TEXT, 'Note', [
    'variable' => 'r12_note',
    'sort_order' => 9,
]);
$form = ReviewLib::publishOpen($form);
Yii::$app->db->createCommand()->delete('custom_form_answer', ['form_id' => (int)$form->id])->execute();
$removed = FormField::findOne($ids[4]);
$removed->updateAttributes(['deleted_at' => '2020-01-01 00:00:00']);
$form = ReviewLib::reload($form);

$values = [];
foreach ($ids as $id) {
    $values[$id] = 'Level 1';
}
$profile = (new Eq5dService())->score($form, $values)['profile'];
if ($profile !== '11111') {
    $failures[] = 'profile ' . $profile;
}

$answer = new FormAnswer();
$answer->form_id = (int)$form->id;
$answer->status = FormAnswer::STATUS_COMPLETE;
$answer->is_test = 0;
$answer->submitted_at = date('Y-m-d H:i:s');
$answer->save(false);
foreach (array_merge($ids, [(int)$live->id]) as $id) {
    Yii::$app->db->createCommand()->insert('custom_form_answer_field', [
        'answer_id' => (int)$answer->id,
        'field_id' => $id,
        'value' => 'Level 1',
    ])->execute();
}
$stats = (new DashboardService())->getFormDashboard(ReviewLib::reload($form));
if ((int)$stats['completionRate'] !== 100) {
    $failures[] = 'completion ' . $stats['completionRate'];
}
if (empty($stats['showUniqueRespondents'])) {
    $failures[] = 'identified form hid unique respondents';
}

$form->identity_mode = CustomForm::IDENTITY_FULLY_ANONYMOUS;
$form->save(false);
$anon = (new DashboardService())->getFormDashboard(ReviewLib::reload($form));
if (!empty($anon['showUniqueRespondents'])) {
    $failures[] = 'anonymous form still shows unique respondents';
}

if ($failures) {
    fwrite(STDOUT, "FAIL " . count($failures) . "\n" . implode("\n", $failures) . "\n");
    exit(1);
}
echo "PASS\n";
