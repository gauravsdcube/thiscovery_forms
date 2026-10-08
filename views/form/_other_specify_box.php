<?php

use yii\helpers\Html;

/** @var string $code */
/** @var string $label */
/** @var array{selected: bool, text: string} $state */
/** @var string $inputName */
/** @var string $inputId */
/** @var bool $optional */

$boxId = $inputId . '-other-' . preg_replace('/[^a-z0-9_-]/i', '', $code);
?>
<div class="cf-other-specify<?= !empty($state['selected']) ? '' : ' d-none' ?>"
     data-cf-other-wrap
     data-cf-other-option="<?= Html::encode($code) ?>"<?= $optional ? ' data-cf-other-optional="1"' : '' ?>>
    <label class="cf-other-specify__label" for="<?= Html::encode($boxId) ?>">
        <?= Yii::t('ThiscoveryFormsModule.base', 'Please specify') ?>
    </label>
    <?= Html::textInput($inputName, (string)($state['text'] ?? ''), [
        'id' => $boxId,
        'class' => 'form-control cf-input',
        'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Type your answer'),
        'data-cf-other-text' => true,
        'disabled' => empty($state['selected']),
        'aria-label' => $label !== '' ? $label : Yii::t('ThiscoveryFormsModule.base', 'Please specify'),
    ]) ?>
</div>
