<?php

use yii\helpers\Html;

/** @var \humhub\modules\thiscoveryForms\models\CustomForm $formModel */
/** @var bool $isNew */
/** @var string $activeSection */

$activeSection = $activeSection ?? 'basics';

$navItem = static function (string $section, string $title, string $icon, string $summary) use ($activeSection) {
    $active = $section === $activeSection;
    return Html::beginTag('button', [
        'type' => 'button',
        'class' => 'cf-palette__item cf-studio-rail__item' . ($active ? ' is-active' : ''),
        'data-cf-settings-nav' => $section,
        'role' => 'tab',
        'aria-selected' => $active ? 'true' : 'false',
        'title' => $summary,
    ])
        . '<span class="cf-palette__icon" aria-hidden="true"><i class="fa ' . Html::encode($icon) . '"></i></span>'
        . '<span class="cf-studio-rail__text">'
        . '<span class="cf-palette__label">' . Html::encode($title) . '</span>'
        . '<span class="cf-studio-rail__summary">' . Html::encode($summary) . '</span>'
        . '</span>'
        . Html::endTag('button');
};
?>
<aside class="cf-studio__rail cf-settings-rail" data-cf-settings-rail role="tablist" aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Settings sections')) ?>">
    <div class="cf-palette__scroll">
        <div class="cf-palette__group">
            <div class="cf-palette__group-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Form') ?></div>
            <?= $navItem('basics', Yii::t('ThiscoveryFormsModule.base', 'Basics'), 'fa-file-text-o', Yii::t('ThiscoveryFormsModule.base', 'Title, description, status')) ?>
            <?= $navItem('end', Yii::t('ThiscoveryFormsModule.base', 'End of survey'), 'fa-flag-checkered', Yii::t('ThiscoveryFormsModule.base', 'Thank you, redirect, already submitted')) ?>
            <?= $navItem('access', Yii::t('ThiscoveryFormsModule.base', 'Who can take part'), 'fa-users', Yii::t('ThiscoveryFormsModule.base', 'Anonymous fill, editing, save and resume')) ?>
            <?= $navItem('display', Yii::t('ThiscoveryFormsModule.base', 'Participant display'), 'fa-eye', Yii::t('ThiscoveryFormsModule.base', 'Title, description, progress, pages')) ?>
            <?= $navItem('sharing', Yii::t('ThiscoveryFormsModule.base', 'Sharing and display'), 'fa-share-alt', Yii::t('ThiscoveryFormsModule.base', 'Menu, header, dashboard link')) ?>
            <?= $navItem('enrol', Yii::t('ThiscoveryFormsModule.base', 'Panel enrolment'), 'fa-user-plus', Yii::t('ThiscoveryFormsModule.base', 'Add completers to a panel')) ?>
            <?= $navItem('email', Yii::t('ThiscoveryFormsModule.base', 'Email templates'), 'fa-envelope-o', Yii::t('ThiscoveryFormsModule.base', 'Invite, wave, reminder, completion')) ?>
            <?= $navItem('languages', Yii::t('ThiscoveryFormsModule.base', 'Languages'), 'fa-globe', Yii::t('ThiscoveryFormsModule.base', 'Source language and translations')) ?>
            <?= $navItem('actions', Yii::t('ThiscoveryFormsModule.base', 'Actions and functions'), 'fa-bolt', Yii::t('ThiscoveryFormsModule.base', 'On submit, custom functions')) ?>
            <?php if ($formModel->isConsensus()): ?>
                <?= $navItem('consensus', Yii::t('ThiscoveryFormsModule.base', 'Consensus'), 'fa-balance-scale', Yii::t('ThiscoveryFormsModule.base', 'Identity, threshold, freeze')) ?>
            <?php endif; ?>
        </div>

        <?php if ($formModel->usesWaves() || $formModel->isConsensus() || $formModel->isProject()): ?>
            <div class="cf-palette__group">
                <div class="cf-palette__group-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Programme') ?></div>
                <?php if ($formModel->usesWaves()): ?>
                    <?= $navItem('panel', Yii::t('ThiscoveryFormsModule.base', 'Panel & waves'), 'fa-sitemap', Yii::t('ThiscoveryFormsModule.base', 'Panel members and wave schedule')) ?>
                <?php endif; ?>
                <?php if ($formModel->isConsensus()): ?>
                    <?= $navItem('rounds', Yii::t('ThiscoveryFormsModule.base', 'Rounds'), 'fa-refresh', Yii::t('ThiscoveryFormsModule.base', 'Consensus rounds')) ?>
                <?php endif; ?>
                <?php if ($formModel->isProject()): ?>
                    <?= $navItem('approval', Yii::t('ThiscoveryFormsModule.base', 'Approval'), 'fa-check-circle', Yii::t('ThiscoveryFormsModule.base', 'Review and approval stages')) ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="cf-palette__group">
            <div class="cf-palette__group-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Quality & style') ?></div>
            <?= $navItem('integrity', Yii::t('ThiscoveryFormsModule.base', 'Response integrity'), 'fa-shield', Yii::t('ThiscoveryFormsModule.base', 'Captcha, consistency checks')) ?>
            <?= $navItem('translations', Yii::t('ThiscoveryFormsModule.base', 'Translations'), 'fa-language', Yii::t('ThiscoveryFormsModule.base', 'Translate questions and messages')) ?>
            <?= $navItem('css', Yii::t('ThiscoveryFormsModule.base', 'CSS'), 'fa-paint-brush', Yii::t('ThiscoveryFormsModule.base', 'Theme and custom styles')) ?>
        </div>

        <div class="cf-palette__group">
            <div class="cf-palette__group-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Publish') ?></div>
            <?= $navItem('share', Yii::t('ThiscoveryFormsModule.base', 'Share'), 'fa-link', Yii::t('ThiscoveryFormsModule.base', 'Links, import, templates')) ?>
            <?= $navItem('export', Yii::t('ThiscoveryFormsModule.base', 'Export'), 'fa-download', Yii::t('ThiscoveryFormsModule.base', 'CSV columns and PII scrubbing')) ?>
            <?php if (!$isNew && \humhub\modules\thiscoveryForms\services\FormVersionService::isAvailable()): ?>
                <?= $navItem('versions', Yii::t('ThiscoveryFormsModule.base', 'Versions'), 'fa-history', Yii::t('ThiscoveryFormsModule.base', 'Revisions and published editions')) ?>
            <?php endif; ?>
        </div>
    </div>
</aside>
