<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var FormAnswer|null $ownDraft */
/** @var string $panelToken */

$ownDraft = $ownDraft ?? null;
$panelToken = $panelToken ?? '';
?>

<div class="cf-resume-gate">
    <h2 class="cf-resume-gate__title">
        <?= Yii::t('ThiscoveryFormsModule.base', 'How would you like to continue?') ?>
    </h2>
    <p class="cf-resume-gate__lead">
        <?= Yii::t('ThiscoveryFormsModule.base', 'Resume a previously saved response, or start a new one.') ?>
    </p>

    <div class="cf-resume-gate__grid">
        <div class="cf-resume-gate__card">
            <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Resume a saved response') ?></h3>
            <p><?= Yii::t('ThiscoveryFormsModule.base', 'Enter the code you were given when you saved your progress.') ?></p>
            <?php if ($ownDraft): ?>
                <p>
                    <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Continue your saved response'))
                        ->link(Url::toContinueOwn($formModel)) ?>
                </p>
            <?php endif; ?>
            <?= Html::beginForm(Url::toLookupResume($formModel), 'post', ['class' => 'cf-resume-lookup__form']) ?>
                <?php if ($panelToken !== ''): ?>
                    <?= Html::hiddenInput('panel_token', $panelToken) ?>
                <?php endif; ?>
                <label class="cf-label" for="cf-resume-gate-code">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Resume code') ?>
                </label>
                <div class="cf-resume-email-row">
                    <?= Html::textInput('resume_code', '', [
                        'id' => 'cf-resume-gate-code',
                        'class' => 'form-control',
                        'placeholder' => 'ABCD-2345-EFGH',
                        'autocomplete' => 'off',
                        'required' => true,
                    ]) ?>
                    <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Continue'))->submit() ?>
                </div>
            <?= Html::endForm() ?>
        </div>

        <div class="cf-resume-gate__card">
            <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Submit a new response') ?></h3>
            <p><?= Yii::t('ThiscoveryFormsModule.base', 'Start this form from the beginning.') ?></p>
            <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Start a new response'))
                ->link(Url::toStartNew($formModel)) ?>
        </div>
    </div>
</div>
