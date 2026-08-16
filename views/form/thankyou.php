<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\RichHtml;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var $contentContainer */
/** @var \humhub\modules\thiscoveryForms\models\FormAnswer|null $answer */

ThiscoveryFormsAsset::register($this);

$css = $formModel->getSafeCustomCss();
$answer = $answer ?? null;
?>

<div class="cf-fill-page cf-thankyou" id="cf-fill">
    <?php if ($css !== ''): ?>
        <style type="text/css"><?= $css ?></style>
    <?php endif; ?>

    <?php if ($formModel->canManage()): ?>
        <div class="cf-fill-toolbar">
            <div class="cf-fill-toolbar__actions">
                <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Edit'))
                    ->link(Url::toEdit($formModel))->sm()->icon('pencil') ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="cf-fill-body">
        <div class="cf-thankyou__body">
            <?php if ($formModel->hasThankYouContent()): ?>
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
            <?php elseif ($formModel->allow_multiple || $formModel->canManage()): ?>
                <div class="cf-thankyou__actions">
                    <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Back to form'))
                        ->link(Url::toView($formModel)) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
