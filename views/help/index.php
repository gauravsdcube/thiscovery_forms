<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var $contentContainer */
/** @var array $sections */
/** @var array $pages */

ThiscoveryFormsAsset::register($this);
?>

<div class="cf-help-page">
    <div class="cf-list-header">
        <div>
            <div class="cf-dash-kicker"><?= Yii::t('ThiscoveryFormsModule.base', 'Thiscovery Forms') ?></div>
            <h1 class="cf-list-title"><?= Yii::t('ThiscoveryFormsModule.base', 'Help') ?></h1>
            <p class="cf-list-sub">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Guides for administrators and form creators. People filling a form do not see these pages.') ?>
            </p>
        </div>
        <div class="cf-list-header__actions">
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Back to forms'))
                ->link(Url::toManageIndex($contentContainer))
                ->icon('arrow-left')
                ->loader(false) ?>
        </div>
    </div>

    <?php foreach ($sections as $section): ?>
        <section class="cf-help-section">
            <h2 class="cf-help-section__title"><?= Html::encode($section['title']) ?></h2>
            <p class="cf-help-section__intro"><?= Html::encode($section['intro']) ?></p>
            <div class="cf-help-cards">
                <?php foreach ($section['pages'] as $slug): ?>
                    <?php $meta = $pages[$slug] ?? null; ?>
                    <?php if (!$meta) { continue; } ?>
                    <a class="cf-help-card" href="<?= Html::encode(Url::toHelp($contentContainer, $slug)) ?>">
                        <span class="cf-help-card__icon"><i class="fa fa-<?= Html::encode($meta['icon']) ?>" aria-hidden="true"></i></span>
                        <span class="cf-help-card__body">
                            <span class="cf-help-card__title"><?= Html::encode($meta['title']) ?></span>
                            <span class="cf-help-card__summary"><?= Html::encode($meta['summary']) ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
