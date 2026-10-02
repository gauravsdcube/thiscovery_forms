<?php

use yii\helpers\Html;

/** @var string $token */
/** @var string $masked */

$this->title = Yii::t('ThiscoveryFormsModule.base', 'Download');
?>
<div class="container cf-secure-public">
    <div class="panel panel-default">
        <div class="panel-body">
            <h1><?= Yii::t('ThiscoveryFormsModule.base', 'Download a file') ?></h1>
            <p>
                <?= Yii::t(
                    'ThiscoveryFormsModule.base',
                    'A code can be sent to {email}. The code works once and then expires. Enter it here to download the file.',
                    ['email' => Html::encode($masked)]
                ) ?>
            </p>

            <?= Html::beginForm(['/thiscovery-forms/secure/request-code'], 'post') ?>
                <?= Html::hiddenInput('t', $token) ?>
                <?= Html::submitButton(Yii::t('ThiscoveryFormsModule.base', 'Email me a code'), ['class' => 'btn btn-default']) ?>
            <?= Html::endForm() ?>

            <?= Html::beginForm(['/thiscovery-forms/secure/redeem'], 'post', ['class' => 'cf-secure-public__code']) ?>
                <?= Html::hiddenInput('t', $token) ?>
                <label>
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Code') ?>
                    <?= Html::textInput('code', '', [
                        'class' => 'form-control',
                        'autocomplete' => 'one-time-code',
                        'inputmode' => 'text',
                        'autocapitalize' => 'characters',
                        'spellcheck' => 'false',
                        'required' => true,
                    ]) ?>
                </label>
                <?= Html::submitButton(Yii::t('ThiscoveryFormsModule.base', 'Download'), ['class' => 'btn btn-primary']) ?>
            <?= Html::endForm() ?>
        </div>
    </div>
</div>
