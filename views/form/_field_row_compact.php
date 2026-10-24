<?php

use humhub\modules\thiscoveryForms\models\FormField;
use yii\helpers\Html;

/** @var string|int $key */
/** @var FormField $field */

$namePrefix = 'fields[' . $key . ']';
$type = $field->type ?: FormField::TYPE_TEXT;
$isQuestionGroup = ($type === FormField::TYPE_QUESTION_GROUP);
$isGroupEnd = ($type === FormField::TYPE_GROUP_END);
$typeLabels = FormField::getTypeLabels();
$pageKey = '';
$pageTitle = '';
if ($type === FormField::TYPE_PAGE_BREAK) {
    $pageBreak = $field->getPageBreakConfig();
    $pageKey = (string)($pageBreak['pageKey'] ?? '');
    $pageTitle = (string)($pageBreak['title'] ?? '');
}
$displayTitle = ($field->internal_label ?: $field->plainLabel()) ?: Yii::t('ThiscoveryFormsModule.base', 'Untitled field');
?>
<div class="cf-field-card thiscovery-forms-field-row is-collapsed<?= $isQuestionGroup ? ' is-group' : '' ?><?= $isGroupEnd ? ' is-group-end d-none' : '' ?>" data-cf-key="<?= Html::encode((string)$key) ?>" data-cf-type="<?= Html::encode($type) ?>" data-cf-compact="1">
    <?= Html::hiddenInput($namePrefix . '[id]', (int)$field->id) ?>
    <?= Html::hiddenInput($namePrefix . '[sort_order]', (int)$field->sort_order, ['data-cf-sort-order' => true]) ?>
    <?= Html::hiddenInput($namePrefix . '[type]', $type, ['data-cf-field-type' => true]) ?>
    <?= Html::hiddenInput($namePrefix . '[unchanged]', '1') ?>

    <div class="cf-field-card__header" data-cf-toggle-card>
        <?= Html::hiddenInput('', (string)$field->plainLabel(), ['data-cf-field-label' => true]) ?>
        <?= Html::hiddenInput('', (string)$field->internal_label, ['data-cf-internal-label' => true]) ?>
        <?= Html::hiddenInput('', (string)$field->variable, ['data-cf-variable' => true]) ?>
        <?php if ($type === FormField::TYPE_PAGE_BREAK): ?>
            <?= Html::hiddenInput('', $pageKey, ['data-cf-page-key' => true]) ?>
            <?= Html::hiddenInput('', $pageTitle, ['data-cf-page-title' => true]) ?>
        <?php endif; ?>
        <input type="checkbox" class="d-none" data-cf-required<?= $field->required ? ' checked' : '' ?> tabindex="-1" aria-hidden="true">
        <input type="checkbox" class="d-none" data-cf-hidden-field<?= $field->isHiddenFromRespondent() ? ' checked' : '' ?> tabindex="-1" aria-hidden="true">
        <div class="cf-field-card__handle" data-cf-drag-handle title="<?= Yii::t('ThiscoveryFormsModule.base', 'Drag to reorder') ?>">
            <i class="fa fa-bars"></i>
        </div>
        <div class="cf-field-card__title">
            <span class="cf-field-card__index" data-cf-index></span>
            <span class="cf-field-card__name" data-cf-title><?= Html::encode($displayTitle) ?></span>
            <span class="cf-field-card__type-badge" data-cf-type-label><?= Html::encode($typeLabels[$type] ?? $type) ?></span>
            <?php if ($field->required): ?>
                <span class="cf-field-card__req"><?= Yii::t('ThiscoveryFormsModule.base', 'Required') ?></span>
            <?php endif; ?>
            <?php if ($field->isHiddenFromRespondent()): ?>
                <span class="cf-field-card__req" data-cf-hidden-badge><?= Yii::t('ThiscoveryFormsModule.base', 'Hidden') ?></span>
            <?php endif; ?>
        </div>
        <div class="cf-field-card__actions">
            <button type="button" class="btn btn-sm btn-light" data-cf-toggle-card-btn title="<?= Yii::t('ThiscoveryFormsModule.base', 'Edit field') ?>">
                <i class="fa fa-pencil"></i>
            </button>
            <button type="button" class="btn btn-sm btn-light" data-cf-move-up title="<?= Yii::t('ThiscoveryFormsModule.base', 'Move up') ?>">
                <i class="fa fa-arrow-up"></i>
            </button>
            <button type="button" class="btn btn-sm btn-light" data-cf-move-down title="<?= Yii::t('ThiscoveryFormsModule.base', 'Move down') ?>">
                <i class="fa fa-arrow-down"></i>
            </button>
            <button type="button" class="btn btn-sm btn-light" data-cf-toggle-advanced title="<?= Yii::t('ThiscoveryFormsModule.base', 'Logic') ?>">
                <i class="fa fa-code-fork"></i> <?= Yii::t('ThiscoveryFormsModule.base', 'Logic') ?>
            </button>
            <button type="button" class="btn btn-sm btn-light" data-cf-save-question title="<?= Yii::t('ThiscoveryFormsModule.base', 'Save as question template') ?>">
                <i class="fa fa-bookmark-o"></i>
            </button>
            <button type="button" class="btn btn-sm btn-danger" data-cf-remove-field title="<?= Yii::t('ThiscoveryFormsModule.base', 'Remove') ?>">
                <i class="fa fa-trash"></i>
            </button>
        </div>
    </div>

    <?php if ($isQuestionGroup): ?>
        <div class="cf-group-shell" data-cf-group-shell>
            <div class="cf-group-toolbar">
                <button type="button" class="btn btn-sm btn-light" data-cf-add-in-group>
                    <i class="fa fa-plus"></i> <?= Yii::t('ThiscoveryFormsModule.base', 'Add question to group') ?>
                </button>
                <button type="button" class="btn btn-sm btn-light" data-cf-toggle-advanced>
                    <i class="fa fa-code-fork"></i> <?= Yii::t('ThiscoveryFormsModule.base', 'Logic') ?>
                </button>
                <span class="cf-hint text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'Use Logic on this group to show or hide every question inside it together.') ?></span>
            </div>
            <div class="cf-group-body" data-cf-group-body>
                <div class="cf-group-empty" data-cf-group-empty><?= Yii::t('ThiscoveryFormsModule.base', 'No questions in this group yet.') ?></div>
            </div>
        </div>
    <?php endif; ?>
</div>
