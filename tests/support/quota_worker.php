<?php

require __DIR__ . '/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;

$form = CustomForm::findOne((int)($argv[1] ?? 0));
$fieldId = (int)($argv[2] ?? 0);
$count = max(1, (int)($argv[3] ?? 1));
$value = (string)($argv[4] ?? '25');
if (!$form || $fieldId < 1) {
    fwrite(STDERR, "worker needs a form and a field\n");
    exit(1);
}
for ($i = 0; $i < $count; $i++) {
    ReviewLib::submit($form, [$fieldId => $value]);
}
echo "ok\n";
