<?php

use yii\helpers\Html;

/** @var int|string $index */
/** @var array{key:string,label:string,type:string,options:string[]} $field */
/** @var array<string,string> $typeLabels */

$isDropdown = ($field['type'] ?? '') === 'dropdown';
$optionsText = implode("\n", $field['options'] ?? []);
?>
<div class="cf-panel-field-row row g-2 mb-2" data-cf-panel-field-row>
    <div class="col-md-3">
        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Label') ?></label>
        <input type="text" name="fields[<?= Html::encode((string)$index) ?>][label]" class="form-control"
               value="<?= Html::encode((string)($field['label'] ?? '')) ?>"
               data-cf-panel-field-label
               placeholder="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'e.g. Postcode')) ?>">
    </div>
    <div class="col-md-3">
        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Variable key') ?></label>
        <input type="text" name="fields[<?= Html::encode((string)$index) ?>][key]" class="form-control"
               value="<?= Html::encode((string)($field['key'] ?? '')) ?>"
               data-cf-panel-field-key
               placeholder="postcode">
    </div>
    <div class="col-md-3">
        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Type') ?></label>
        <?= Html::dropDownList('fields[' . $index . '][type]', $field['type'] ?? 'text', $typeLabels, [
            'class' => 'form-control',
            'data-cf-panel-field-type' => true,
        ]) ?>
    </div>
    <div class="col-md-3 d-flex align-items-end">
        <button type="button" class="btn btn-sm btn-light" data-cf-remove-panel-field title="<?= Yii::t('ThiscoveryFormsModule.base', 'Remove') ?>">
            <i class="fa fa-trash"></i>
        </button>
    </div>
    <div class="col-md-12<?= $isDropdown ? '' : ' d-none' ?>" data-cf-panel-field-options-wrap>
        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Dropdown options') ?></label>
        <textarea name="fields[<?= Html::encode((string)$index) ?>][options]" class="form-control" rows="2"
                  placeholder="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'One option per line')) ?>"><?= Html::encode($optionsText) ?></textarea>
    </div>
</div>
