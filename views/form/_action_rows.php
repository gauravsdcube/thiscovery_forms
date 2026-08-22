<?php

use humhub\modules\thiscoveryForms\services\FormActionService;
use yii\helpers\Html;

/** @var string $namePrefix */
/** @var array $actions */
/** @var array $emailTemplateOptions */
/** @var array $pageKeyOptions */

$actions = $actions ?: [FormActionService::emptyAction()];
$emailTemplateOptions = $emailTemplateOptions ?? [0 => Yii::t('ThiscoveryFormsModule.base', 'Default email text')];
$pageKeyOptions = $pageKeyOptions ?? ['' => Yii::t('ThiscoveryFormsModule.base', 'Select page…')];
$fnLabels = FormActionService::functionLabels();
?>
<div data-cf-action-list>
    <?php foreach ($actions as $ai => $action): ?>
        <?php
        $fn = $action['fn'] ?? FormActionService::FN_NONE;
        $rowName = $namePrefix . '[' . $ai . ']';
        ?>
        <div class="cf-action-row row g-2 mb-2" data-cf-action-row>
            <div class="col-md-3">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Function') ?></label>
                <?= Html::dropDownList($rowName . '[fn]', $fn, $fnLabels, [
                    'class' => 'form-control',
                    'data-cf-action-fn' => true,
                ]) ?>
            </div>
            <div class="col-md-4<?= $fn === FormActionService::FN_SEND_EMAIL ? '' : ' d-none' ?>" data-cf-action-param="send_email">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Email template') ?></label>
                <?= Html::dropDownList($rowName . '[template_id]', $action['template_id'] ?? 0, $emailTemplateOptions, [
                    'class' => 'form-control',
                ]) ?>
            </div>
            <div class="col-md-3<?= $fn === FormActionService::FN_GOTO_PAGE ? '' : ' d-none' ?>" data-cf-action-param="goto_page">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Page') ?></label>
                <?= Html::dropDownList($rowName . '[page_key]', $action['page_key'] ?? '', $pageKeyOptions, [
                    'class' => 'form-control',
                ]) ?>
            </div>
            <div class="col-md-2<?= in_array($fn, [FormActionService::FN_SET_VARIABLE, FormActionService::FN_CUSTOM], true) ? '' : ' d-none' ?>" data-cf-action-param="name">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Name') ?></label>
                <?= Html::textInput($rowName . '[name]', $action['name'] ?? '', [
                    'class' => 'form-control',
                    'placeholder' => 'riskBand',
                ]) ?>
            </div>
            <div class="col-md-3<?= in_array($fn, [FormActionService::FN_SET_VARIABLE, FormActionService::FN_CUSTOM], true) ? '' : ' d-none' ?>" data-cf-action-param="value">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Value') ?></label>
                <?= Html::textInput($rowName . '[value]', $action['value'] ?? '', [
                    'class' => 'form-control',
                    'placeholder' => '{{answer:Question label}}',
                ]) ?>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="button" class="btn btn-sm btn-light" data-cf-remove-action title="<?= Yii::t('ThiscoveryFormsModule.base', 'Remove') ?>">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div class="col-12<?= in_array($fn, [FormActionService::FN_CUSTOM, FormActionService::FN_SET_VARIABLE], true) ? '' : ' d-none' ?>" data-cf-action-hint="custom">
                <p class="cf-hint text-muted mb-0">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Custom function: type the name from Settings → Custom functions. Leave Value empty to use that formula. Set variable: store a one-off name and value for this response. Either way, later text and emails can use {{var:name}}.') ?>
                </p>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<button type="button" class="btn btn-sm btn-light" data-cf-add-action>
    <i class="fa fa-plus"></i> <?= Yii::t('ThiscoveryFormsModule.base', 'Add action') ?>
</button>
