<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\services\RandomisationService;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var FormAnswer $answer */

// Managers can move a response to another arm, with a reason (V3-48).
if (!$formModel->canManage() || !RandomisationService::formEnabled($formModel) || !$answer->id) {
    return;
}
$service = new RandomisationService();
$assigned = $service->assignment($answer);
if (!$assigned) {
    return;
}
$arms = [];
foreach ($service->config($formModel)['arms'] as $arm) {
    if ((string)$arm['code'] !== (string)$assigned['arm_code']) {
        $arms[(string)$arm['code']] = (string)$arm['name'] . ' (' . $arm['code'] . ')';
    }
}
if ($arms === []) {
    return;
}
?>
<details class="cf-arm-override">
    <summary><?= Yii::t('ThiscoveryFormsModule.base', 'Arm: {arm} ({method})', [
        'arm' => Html::encode((string)$assigned['arm_name']),
        'method' => Html::encode((string)$assigned['method']),
    ]) ?></summary>
    <?= Html::beginForm(Url::toArmOverride($formModel, (int)$answer->id), 'post') ?>
        <div class="form-group">
            <label for="cf-arm-override-<?= (int)$answer->id ?>"><?= Yii::t('ThiscoveryFormsModule.base', 'Move to arm') ?></label>
            <?= Html::dropDownList('arm_code', null, $arms, ['class' => 'form-control', 'id' => 'cf-arm-override-' . (int)$answer->id]) ?>
        </div>
        <div class="form-group">
            <label for="cf-arm-reason-<?= (int)$answer->id ?>"><?= Yii::t('ThiscoveryFormsModule.base', 'Reason') ?></label>
            <?= Html::textInput('reason', '', ['class' => 'form-control', 'required' => true, 'maxlength' => 255, 'id' => 'cf-arm-reason-' . (int)$answer->id]) ?>
        </div>
        <?= Html::submitButton(Yii::t('ThiscoveryFormsModule.base', 'Change arm'), ['class' => 'btn btn-default btn-sm']) ?>
    <?= Html::endForm() ?>
</details>
