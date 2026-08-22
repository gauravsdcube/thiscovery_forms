<?php

use humhub\modules\thiscoveryEditor\assets\EditorAsset;
use humhub\modules\thiscoveryEditor\widgets\EditorField;
use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\FormEmailTemplate;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var FormEmailTemplate $template */
/** @var bool $isNew */
/** @var $contentContainer */

ThiscoveryFormsAsset::register($this);
EditorAsset::register($this);
$this->registerJs('humhub.require("thiscoveryForms").initEmailTemplate("#cf-email-template-edit");', \yii\web\View::POS_READY);
?>

<div class="cf-list-page" id="cf-email-template-edit">
    <div class="cf-list-header">
        <div>
            <div class="cf-dash-kicker"><?= Yii::t('ThiscoveryFormsModule.base', 'Email templates') ?></div>
            <h1 class="cf-list-title">
                <?= $isNew
                    ? Yii::t('ThiscoveryFormsModule.base', 'Create template')
                    : Yii::t('ThiscoveryFormsModule.base', 'Edit template') ?>
            </h1>
            <p class="cf-list-sub">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Each email has a header, body, and footer, like other Thiscovery emails. Write them in Thiscovery Editor. Placeholders are replaced when the email is sent.') ?>
            </p>
        </div>
        <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Back to templates'))
            ->link(Url::toEmailTemplateIndex($contentContainer))
            ->sm()
            ->icon('arrow-left')
            ->pjax(false)
            ->loader(false) ?>
    </div>

    <?= Html::beginForm(Url::toEmailTemplateEdit($contentContainer, $isNew ? null : $template->id), 'post', [
        'class' => 'cf-folder-form',
        'data-pjax' => '0',
    ]) ?>
        <div class="cf-folder-form__card">
            <div class="form-group">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Template name') ?></label>
                <?= Html::activeTextInput($template, 'title', [
                    'class' => 'form-control',
                    'required' => true,
                    'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'e.g. Wave 2 invite'),
                ]) ?>
            </div>
            <div class="form-group mb-0">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Subject') ?></label>
                <?= Html::activeTextInput($template, 'subject', [
                    'class' => 'form-control',
                    'required' => true,
                    'placeholder' => Yii::t('ThiscoveryFormsModule.base', '{{formTitle}} is open'),
                ]) ?>
            </div>
        </div>

        <div class="cf-folder-form__card">
            <h2 class="cf-folder-form__h"><?= Yii::t('ThiscoveryFormsModule.base', 'Email header') ?></h2>
            <p class="cf-hint text-muted">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Optional banner for a logo, title, or branded heading.') ?>
            </p>
            <div class="row g-3">
                <div class="col-md-7">
                    <div class="cf-rich-editor" data-cf-rich-editor>
                        <?= EditorField::widget([
                            'model' => $template,
                            'attribute' => 'header_html',
                            'placeholder' => Yii::t('ThiscoveryFormsModule.base', '{{formTitle}}'),
                            'height' => 160,
                            'profile' => 'simple',
                        ]) ?>
                    </div>
                </div>
                <div class="col-md-5">
                    <?= $this->render('_email_colors', ['template' => $template, 'prefix' => 'header']) ?>
                </div>
            </div>
        </div>

        <div class="cf-folder-form__card">
            <h2 class="cf-folder-form__h"><?= Yii::t('ThiscoveryFormsModule.base', 'Email body') ?></h2>
            <p class="cf-hint text-muted">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Main message. Use headings, lists, links, and buttons. A button URL can be {{formUrl}}.') ?>
            </p>
            <div class="cf-rich-editor" data-cf-rich-editor>
                <?= EditorField::widget([
                    'model' => $template,
                    'attribute' => 'body_html',
                    'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Hello {{firstName}}…'),
                    'height' => 360,
                    'profile' => 'simple',
                ]) ?>
            </div>
        </div>

        <div class="cf-folder-form__card">
            <h2 class="cf-folder-form__h"><?= Yii::t('ThiscoveryFormsModule.base', 'Email footer') ?></h2>
            <p class="cf-hint text-muted">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Optional footer for contact details, a disclaimer, or who sent the email.') ?>
            </p>
            <div class="row g-3">
                <div class="col-md-7">
                    <div class="cf-rich-editor" data-cf-rich-editor>
                        <?= EditorField::widget([
                            'model' => $template,
                            'attribute' => 'footer_html',
                            'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'You received this because you are taking part in {{formTitle}}.'),
                            'height' => 160,
                            'profile' => 'simple',
                        ]) ?>
                    </div>
                </div>
                <div class="col-md-5">
                    <?= $this->render('_email_colors', ['template' => $template, 'prefix' => 'footer']) ?>
                </div>
            </div>
        </div>

        <div class="cf-folder-form__card">
            <h2 class="cf-folder-form__h"><?= Yii::t('ThiscoveryFormsModule.base', 'Placeholders') ?></h2>
            <p class="cf-hint text-muted">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Click to copy. Paste into the subject, header, body, footer, or a button URL.') ?>
            </p>
            <div class="cf-email-vars">
                <?php foreach (['firstName', 'lastName', 'displayName', 'email', 'formTitle', 'formUrl', 'panelName', 'waveTitle'] as $var): ?>
                    <button type="button" class="btn btn-sm btn-light" data-cf-copy-var="{{<?= Html::encode($var) ?>}}">
                        <code>{{<?= Html::encode($var) ?>}}</code>
                    </button>
                <?php endforeach; ?>
                <button type="button" class="btn btn-sm btn-light" data-cf-copy-var="{{var:name}}">
                    <code>{{var:name}}</code>
                </button>
            </div>
        </div>

        <div class="cf-folder-form__actions">
            <?= Button::save($isNew
                ? Yii::t('ThiscoveryFormsModule.base', 'Create template')
                : Yii::t('ThiscoveryFormsModule.base', 'Save template'))->submit()->loader(false) ?>
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Cancel'))
                ->link(Url::toEmailTemplateIndex($contentContainer))
                ->pjax(false)
                ->loader(false) ?>
        </div>
    <?= Html::endForm() ?>
</div>
