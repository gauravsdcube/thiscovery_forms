<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var $contentContainer */
/** @var CustomForm[] $templates */

ThiscoveryFormsAsset::register($this);

$kinds = CustomForm::getKindLabels();
$descriptions = CustomForm::getKindDescriptions();
$icons = [
    CustomForm::KIND_SURVEY => 'fa-wpforms',
    CustomForm::KIND_POLL => 'fa-bar-chart',
    CustomForm::KIND_FEEDBACK => 'fa-commenting-o',
    CustomForm::KIND_LONGITUDINAL => 'fa-line-chart',
    CustomForm::KIND_CONSENSUS => 'fa-balance-scale',
];
?>

<div class="cf-create-wizard">
    <div class="cf-list-header">
        <div>
            <h1 class="cf-list-title"><?= Yii::t('ThiscoveryFormsModule.base', 'Create') ?></h1>
            <p class="cf-list-sub"><?= Yii::t('ThiscoveryFormsModule.base', 'Choose a type, or start from a saved template.') ?></p>
        </div>
        <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Back'))
            ->link(Url::toIndex($contentContainer))
            ->icon('arrow-left')
            ->loader(false) ?>
    </div>

    <div class="cf-kind-grid">
        <?php foreach ($kinds as $kind => $label): ?>
            <a class="cf-kind-card" href="<?= Html::encode(Url::toCreate($contentContainer, ['kind' => $kind])) ?>">
                <span class="cf-kind-card__icon"><i class="fa <?= Html::encode($icons[$kind] ?? 'fa-wpforms') ?>"></i></span>
                <h3 class="cf-kind-card__title"><?= Html::encode($label) ?></h3>
                <p class="cf-kind-card__desc"><?= Html::encode($descriptions[$kind] ?? '') ?></p>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($templates)): ?>
        <h2 class="cf-create-wizard__h"><?= Yii::t('ThiscoveryFormsModule.base', 'From a template') ?></h2>
        <ul class="cf-template-list">
            <?php foreach ($templates as $template): ?>
                <li>
                    <a href="<?= Html::encode(Url::toCreate($contentContainer, ['template' => $template->id])) ?>">
                        <strong><?= Html::encode($template->title) ?></strong>
                        <span class="text-muted">
                            <?= Html::encode(CustomForm::getKindLabels()[$template->kind] ?? $template->kind) ?>
                            · <?= Yii::t('ThiscoveryFormsModule.base', '{n} fields', ['n' => count($template->fields)]) ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
