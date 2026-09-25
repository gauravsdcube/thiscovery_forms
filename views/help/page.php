<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var array $article */
/** @var $contentContainer */
/** @var array $sections */
/** @var array $pages */
/** @var array $downloads */

ThiscoveryFormsAsset::register($this);
$current = $article['slug'];
?>

<div class="cf-help-page cf-help-article">
    <div class="cf-list-header">
        <div>
            <div class="cf-dash-kicker"><?= Yii::t('ThiscoveryFormsModule.base', 'Help') ?></div>
            <h1 class="cf-list-title"><?= Html::encode($article['title']) ?></h1>
        </div>
        <div class="cf-list-header__actions">
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'All help'))
                ->link(Url::toHelp($contentContainer))
                ->icon('book')
                ->loader(false) ?>
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Back to forms'))
                ->link(Url::toManageIndex($contentContainer))
                ->icon('arrow-left')
                ->loader(false) ?>
        </div>
    </div>

    <div class="cf-help-layout">
        <nav class="cf-help-nav" aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Help')) ?>">
            <?php foreach ($sections as $section): ?>
                <div class="cf-help-nav__group"><?= Html::encode($section['title']) ?></div>
                <?php foreach ($section['pages'] as $slug): ?>
                    <?php $meta = $pages[$slug] ?? null; ?>
                    <?php if (!$meta) { continue; } ?>
                    <a class="cf-help-nav__link<?= $slug === $current ? ' is-active' : '' ?>"
                       href="<?= Html::encode(Url::toHelp($contentContainer, $slug)) ?>">
                        <?= Html::encode($meta['title']) ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
        <article class="cf-help-body">
            <?= $article['html'] ?>
        </article>
    </div>
</div>
