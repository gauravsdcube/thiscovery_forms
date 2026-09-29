<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\RichHtml;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\services\TranslationService;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var $contentContainer */
/** @var \humhub\modules\thiscoveryForms\models\FormAnswer|null $answer */

ThiscoveryFormsAsset::register($this);

$css = $formModel->getSafeCustomCss();
$answer = $answer ?? null;
$isPreview = !empty($isPreview);
$fillLang = (new TranslationService())->resolve($formModel);
(new TranslationService())->overlay($formModel, $fillLang);
$fillRtl = TranslationService::isRtl($fillLang);
$showButton = $formModel->showsCompletionButton();
$externalBtn = $showButton && !str_starts_with($formModel->getCompletionButtonUrl(), '/')
    && preg_match('#^https?://#i', $formModel->getCompletionButtonUrl());
?>

<div class="cf-fill-page cf-thankyou" id="cf-fill"
     dir="<?= $fillRtl ? 'rtl' : 'ltr' ?>"
     lang="<?= Html::encode($fillLang) ?>">
    <?php if ($css !== ''): ?>
        <style type="text/css"><?= $css ?></style>
    <?php endif; ?>

    <?php if ($formModel->canManage()): ?>
        <div class="cf-fill-toolbar">
            <div class="cf-fill-toolbar__actions">
                <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Edit'))
                    ->link(Url::toEdit($formModel))->pjax(!$formModel->hidesHumhubHeader())->sm()->icon('pencil') ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="cf-fill-body">
        <?php if ($isPreview): ?>
            <div class="alert alert-warning">
                <?= Yii::t('ThiscoveryFormsModule.base', 'This was a test submission. It is not counted in participant results.') ?>
            </div>
            <?php if ($formModel->usesCompletionRedirect()): ?>
                <div class="alert alert-info">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Live submissions redirect to {url}. Preview stays on this page.', [
                        'url' => Html::encode($formModel->getCompletionRedirectUrl()),
                    ]) ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <div class="cf-thankyou__body">
            <?php $notConsented = Yii::$app->session->getFlash('cf-not-consented'); ?>
            <?php $withdrawToken = (string)Yii::$app->session->get('cf-consent-withdraw', ''); ?>
            <?php if ($withdrawToken !== ''): ?>
                <?php Yii::$app->session->remove('cf-consent-withdraw'); ?>
            <?php endif; ?>
            <?php $overQuota = Yii::$app->session->getFlash('cf-over-quota'); ?>
            <?php $quotaRedirect = (string)Yii::$app->session->get('cf-quota-redirect', ''); ?>
            <?php if ($quotaRedirect !== ''): ?>
                <?php Yii::$app->session->remove('cf-quota-redirect'); ?>
            <?php endif; ?>
            <?php if (is_string($overQuota) && $overQuota !== ''): ?>
                <div class="cf-thankyou__default" role="status">
                    <h2><?= Yii::t('ThiscoveryFormsModule.base', 'This group is full') ?></h2>
                    <p><?= nl2br(Html::encode($overQuota)) ?></p>
                    <?php if ($quotaRedirect !== ''): ?>
                        <p><a href="<?= Html::encode($quotaRedirect) ?>"><?= Yii::t('ThiscoveryFormsModule.base', 'Continue') ?></a></p>
                        <meta http-equiv="refresh" content="5;url=<?= Html::encode($quotaRedirect) ?>">
                    <?php endif; ?>
                </div>
            <?php elseif (is_string($notConsented) && $notConsented !== ''): ?>
                <div class="cf-thankyou__default"><p><?= nl2br(Html::encode($notConsented)) ?></p></div>
            <?php elseif ($withdrawToken !== ''): ?>
                <p><?= Yii::t('ThiscoveryFormsModule.base', 'Keep this withdrawal code. It is shown once: {code}', ['code' => $withdrawToken]) ?></p>
            <?php endif; ?>
            <?php
            $screenMessage = '';
            if ($answer && (string)$answer->outcome === \humhub\modules\thiscoveryForms\models\FormAnswer::OUTCOME_SCREENED_OUT) {
                $screenMessage = trim((string)(new \humhub\modules\thiscoveryForms\services\RandomisationService())->config($formModel)['screen_out_message']);
            }
            ?>
            <?php if ((is_string($overQuota) && $overQuota !== '') || (is_string($notConsented) && $notConsented !== '')): ?>
            <?php elseif ($screenMessage !== ''): ?>
                <div class="cf-thankyou__default">
                    <p><?= nl2br(Html::encode($screenMessage)) ?></p>
                </div>
            <?php elseif ($formModel->hasThankYouContent()): ?>
                <div class="cf-thankyou__content richtext-output">
                    <?= RichHtml::toHtml($formModel->thank_you_content) ?>
                </div>
            <?php else: ?>
                <div class="cf-thankyou__default">
                    <i class="fa fa-check-circle" aria-hidden="true"></i>
                    <h2><?= Yii::t('ThiscoveryFormsModule.base', 'Thank you!') ?></h2>
                    <p><?= $formModel->isProject()
                        ? Yii::t('ThiscoveryFormsModule.base', 'Your project has been submitted for review.')
                        : Yii::t('ThiscoveryFormsModule.base', 'Your submission has been saved.') ?></p>
                </div>
            <?php endif; ?>

            <?php if ($formModel->isProject() && $answer): ?>
                <div class="cf-thankyou__actions">
                    <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'View your project'))
                        ->link(Url::toProject($formModel, $answer)) ?>
                    <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Catalogue'))
                        ->link(Url::toCatalogue($formModel)) ?>
                </div>
            <?php elseif ($showButton): ?>
                <div class="cf-thankyou__actions">
                    <?php
                    $btn = Button::primary($formModel->getCompletionButtonLabel())
                        ->link($formModel->getCompletionButtonUrl());
                    if (!$externalBtn) {
                        $btn->pjax(!$formModel->hidesHumhubHeader());
                    } else {
                        $btn->options(['target' => '_blank', 'rel' => 'noopener']);
                    }
                    echo $btn;
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
