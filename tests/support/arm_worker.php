<?php
/** Worker for AllocationConcurrencyTest: starts N responses and assigns an arm to each. */
require __DIR__ . '/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\services\RandomisationService;

$form = CustomForm::findOne((int)($argv[1] ?? 0));
$count = max(1, (int)($argv[2] ?? 1));
if (!$form) {
    fwrite(STDERR, "worker needs a form\n");
    exit(1);
}
$svc = new RandomisationService();
for ($i = 0; $i < $count; $i++) {
    $answer = new FormAnswer();
    $answer->form_id = (int)$form->id;
    $answer->status = FormAnswer::STATUS_IN_PROGRESS;
    $answer->is_test = 0;
    $answer->forceAnonymous = true;
    $answer->save(false);
    $answer->populateRelation('form', $form);
    $row = $svc->assignIfDue($answer, [], null, false);
    if (!$row || (string)($row['arm_code'] ?? '') === '') {
        fwrite(STDERR, "no arm assigned\n");
        exit(1);
    }
}
echo "ok\n";
