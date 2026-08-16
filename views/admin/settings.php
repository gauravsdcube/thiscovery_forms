<?php

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\ModuleSettings;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var ModuleSettings $model */

$this->title = Yii::t('ThiscoveryFormsModule.base', 'Thiscovery Forms');
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <?= Yii::t('ThiscoveryFormsModule.base', '<strong>Thiscovery Forms</strong> module configuration') ?>
    </div>
    <div class="panel-body">
        <p class="help-block">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Choose which form types people can create. Existing forms of a disabled type stay available; new ones cannot be created.') ?>
        </p>

        <?php $form = ActiveForm::begin(); ?>
        <?= $form->field($model, 'enabledKinds')->checkboxList(CustomForm::getKindLabels(), [
            'itemOptions' => ['labelOptions' => ['class' => 'checkbox']],
            'separator' => '',
        ]) ?>
        <?= Html::submitButton(Yii::t('ThiscoveryFormsModule.base', 'Save'), ['class' => 'btn btn-primary']) ?>
        <?php ActiveForm::end(); ?>
    </div>
</div>
