<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\services\integrity\IntegritySettings;
use humhub\widgets\bootstrap\Button;
use humhub\widgets\form\CaptchaField;
use yii\base\DynamicModel;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var array $integritySettings */
/** @var string|null $error */
/** @var mixed $contentContainer */

ThiscoveryFormsAsset::register($this);

$integritySettings = $integritySettings ?? IntegritySettings::forForm($formModel);
$error = $error ?? null;
$provider = (string)($integritySettings['captcha_provider'] ?? IntegritySettings::CAPTCHA_PROVIDER_ALTCHA);
$accessToken = trim((string)Yii::$app->request->get('access', Yii::$app->request->post('access_token', '')));
$panelToken = trim((string)Yii::$app->request->get('token', Yii::$app->request->post('panel_token', '')));
?>
<div class="cf-fill-page" id="cf-fill-open-captcha">
    <div class="cf-fill-body thiscovery-forms-fill">
        <header class="cf-fill-hero">
            <h1 class="cf-fill-hero__title"><?= Html::encode($formModel->title) ?></h1>
        </header>

        <div class="cf-resume-gate">
            <h2 class="cf-resume-gate__title">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Please verify you are human') ?>
            </h2>
            <p class="cf-resume-gate__lead">
                <?= Yii::t('ThiscoveryFormsModule.base', 'This survey has received many open attempts from your connection. Complete the check below to continue.') ?>
            </p>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= Html::encode($error) ?></div>
            <?php endif; ?>

            <?= Html::beginForm(Url::toView($formModel), 'post', ['class' => 'cf-open-captcha-form']) ?>
                <?= Html::hiddenInput('integrity_open_challenge', '1') ?>
                <?php if ($accessToken !== ''): ?>
                    <?= Html::hiddenInput('access_token', $accessToken) ?>
                <?php endif; ?>
                <?php if ($panelToken !== ''): ?>
                    <?= Html::hiddenInput('panel_token', $panelToken) ?>
                <?php endif; ?>

                <div class="cf-captcha-wrap mb-3">
                    <?php if ($provider === IntegritySettings::CAPTCHA_PROVIDER_TURNSTILE && !empty($integritySettings['turnstile_site_key'])): ?>
                        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                        <div class="cf-turnstile" data-sitekey="<?= Html::encode($integritySettings['turnstile_site_key']) ?>"></div>
                    <?php else: ?>
                        <?php
                        $captchaModel = new DynamicModel(['captcha' => null]);
                        echo CaptchaField::widget(['model' => $captchaModel, 'attribute' => 'captcha']);
                        ?>
                    <?php endif; ?>
                </div>

                <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Continue'))
                    ->submit()
                    ->loader(false) ?>
            <?= Html::endForm() ?>
        </div>
    </div>
</div>
