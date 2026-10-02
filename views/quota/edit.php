<?php

use yii\helpers\Html;

/** @var \humhub\modules\thiscoveryForms\models\CustomForm $formModel */
/** @var array|null $quota */
/** @var array $quotas */

$quota = $quota ?? [
    'name' => '',
    'target' => 0,
    'wave_id' => '',
    'parent_id' => '',
    'rules_json' => '',
    'count_policy' => 'complete',
    'reserve' => 0,
    'reserve_minutes' => 60,
    'action' => 'end',
    'action_message' => '',
    'action_url' => '',
    'action_page_key' => '',
    'check_page_key' => '',
    'status' => 'open',
    'sort_order' => 0,
];
$parents = ['' => Yii::t('ThiscoveryFormsModule.base', 'None')];
foreach ($quotas as $item) {
    if (!empty($quota['id']) && (int)$item['id'] === (int)$quota['id']) {
        continue;
    }
    $parents[(int)$item['id']] = (string)$item['name'];
}
?>
<div class="panel">
    <div class="panel-heading"><?= Html::encode($quota['name'] !== '' ? $quota['name'] : Yii::t('ThiscoveryFormsModule.base', 'Quota')) ?></div>
    <div class="panel-body">
        <?= Html::beginForm() ?>
        <div class="form-group">
            <label><?= Yii::t('ThiscoveryFormsModule.base', 'Name') ?></label>
            <?= Html::textInput('name', (string)$quota['name'], ['class' => 'form-control', 'required' => true]) ?>
        </div>
        <div class="form-group">
            <label><?= Yii::t('ThiscoveryFormsModule.base', 'Target') ?></label>
            <?= Html::textInput('target', (int)$quota['target'], ['class' => 'form-control', 'type' => 'number', 'min' => 0]) ?>
        </div>
        <?php if (!empty($quota['id'])): ?>
            <div class="form-group">
                <label><?= Yii::t('ThiscoveryFormsModule.base', 'Reason for a target change') ?></label>
                <?= Html::textInput('reason', '', ['class' => 'form-control']) ?>
            </div>
        <?php endif; ?>
        <div class="form-group">
            <label><?= Yii::t('ThiscoveryFormsModule.base', 'Wave id, or empty for the whole form') ?></label>
            <?= Html::textInput('wave_id', (string)($quota['wave_id'] ?? ''), ['class' => 'form-control']) ?>
        </div>
        <div class="form-group">
            <label><?= Yii::t('ThiscoveryFormsModule.base', 'Parent') ?></label>
            <?= Html::dropDownList('parent_id', (string)($quota['parent_id'] ?? ''), $parents, ['class' => 'form-control']) ?>
        </div>
        <div class="form-group">
            <label><?= Yii::t('ThiscoveryFormsModule.base', 'Rules') ?></label>
            <p class="help-block"><?= Yii::t('ThiscoveryFormsModule.base', 'A formula, for example [arm] = "pictogram" or [panel:site] = "north". A fully anonymous form cannot use a panel value.') ?></p>
            <?= Html::textarea('rules_json', (string)($quota['rules_json'] ?? ''), ['class' => 'form-control', 'rows' => 8]) ?>
        </div>
        <div class="form-group">
            <label><?= Yii::t('ThiscoveryFormsModule.base', 'Count') ?></label>
            <?= Html::dropDownList('count_policy', (string)$quota['count_policy'], [
                'complete' => Yii::t('ThiscoveryFormsModule.base', 'Completes'),
                'complete_excluding_integrity' => Yii::t('ThiscoveryFormsModule.base', 'Completes, excluding integrity flags'),
            ], ['class' => 'form-control']) ?>
        </div>
        <div class="form-group">
            <label><?= Yii::t('ThiscoveryFormsModule.base', 'Check on leaving page') ?></label>
            <?= Html::textInput('check_page_key', (string)($quota['check_page_key'] ?? ''), ['class' => 'form-control']) ?>
        </div>
        <div class="form-group">
            <label><?= Yii::t('ThiscoveryFormsModule.base', 'If full') ?></label>
            <?= Html::dropDownList('action', (string)$quota['action'], [
                'end' => Yii::t('ThiscoveryFormsModule.base', 'End, with a message'),
                'redirect' => Yii::t('ThiscoveryFormsModule.base', 'Redirect to an allowlisted URL'),
                'goto' => Yii::t('ThiscoveryFormsModule.base', 'Go to a page'),
                'continue' => Yii::t('ThiscoveryFormsModule.base', 'Mark and continue'),
            ], ['class' => 'form-control']) ?>
        </div>
        <div class="form-group">
            <label><?= Yii::t('ThiscoveryFormsModule.base', 'Message') ?></label>
            <?= Html::textInput('action_message', (string)($quota['action_message'] ?? ''), ['class' => 'form-control']) ?>
        </div>
        <div class="form-group">
            <label><?= Yii::t('ThiscoveryFormsModule.base', 'Redirect URL') ?></label>
            <?= Html::textInput('action_url', (string)($quota['action_url'] ?? ''), ['class' => 'form-control']) ?>
        </div>
        <div class="form-group">
            <label><?= Yii::t('ThiscoveryFormsModule.base', 'Go to page') ?></label>
            <?= Html::textInput('action_page_key', (string)($quota['action_page_key'] ?? ''), ['class' => 'form-control']) ?>
        </div>
        <div class="checkbox">
            <label>
                <?= Html::checkbox('reserve', !empty($quota['reserve']), ['value' => 1, 'uncheck' => 0]) ?>
                <?= Yii::t('ThiscoveryFormsModule.base', 'Reserve a place') ?>
            </label>
        </div>
        <div class="form-group">
            <label><?= Yii::t('ThiscoveryFormsModule.base', 'Minutes') ?></label>
            <?= Html::textInput('reserve_minutes', (int)($quota['reserve_minutes'] ?? 60), ['class' => 'form-control', 'type' => 'number', 'min' => 1]) ?>
        </div>
        <?= Html::hiddenInput('status', (string)($quota['status'] ?? 'open')) ?>
        <?= Html::submitButton(Yii::t('ThiscoveryFormsModule.base', 'Save'), ['class' => 'btn btn-primary']) ?>
        <?= Html::endForm() ?>
        <hr>
        <?= Html::beginForm() ?>
        <div class="form-group">
            <label><?= Yii::t('ThiscoveryFormsModule.base', 'Allowlisted redirect host') ?></label>
            <?= Html::textInput('host', '', ['class' => 'form-control']) ?>
        </div>
        <?= Html::hiddenInput('name', (string)$quota['name']) ?>
        <?= Html::submitButton(Yii::t('ThiscoveryFormsModule.base', 'Add host'), ['class' => 'btn btn-default']) ?>
        <?= Html::endForm() ?>
    </div>
</div>
