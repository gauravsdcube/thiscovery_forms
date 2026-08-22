<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\FormPanel;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var FormPanel $panel */
/** @var bool $isNew */
/** @var $contentContainer */

ThiscoveryFormsAsset::register($this);
?>

<div class="cf-list-page" id="cf-panel-edit">
    <div class="cf-list-header">
        <div>
            <div class="cf-dash-kicker"><?= Yii::t('ThiscoveryFormsModule.base', 'Panels') ?></div>
            <h1 class="cf-list-title">
                <?= $isNew
                    ? Yii::t('ThiscoveryFormsModule.base', 'Create panel')
                    : Yii::t('ThiscoveryFormsModule.base', 'Edit panel') ?>
            </h1>
            <p class="cf-list-sub">
                <?= $isNew
                    ? Yii::t('ThiscoveryFormsModule.base', 'Give the panel a name. You can add extra member fields after you save, then add people.')
                    : Yii::t('ThiscoveryFormsModule.base', 'Name the panel and add extra fields to store on each member. First name, last name, and email are always there.') ?>
            </p>
        </div>
        <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Back to panels'))
            ->link($isNew ? Url::toPanelIndex($contentContainer) : Url::toPanelView($panel, $contentContainer))
            ->sm()
            ->icon('arrow-left')
            ->loader(false) ?>
    </div>

    <?= Html::beginForm(Url::toPanelEdit($contentContainer, $isNew ? null : $panel->id), 'post', [
        'class' => 'cf-folder-form',
    ]) ?>
        <div class="cf-folder-form__card">
            <div class="form-group">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Panel name') ?></label>
                <input type="text" name="title" class="form-control" required
                       value="<?= Html::encode((string)$panel->title) ?>"
                       placeholder="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'e.g. EQ-5D follow-up')) ?>">
            </div>
            <div class="form-group mb-0">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Description') ?>
                    <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
                </label>
                <textarea name="description" class="form-control" rows="3"><?= Html::encode((string)$panel->description) ?></textarea>
            </div>
        </div>

        <?php if (!$isNew): ?>
            <?php
            $schema = $panel->getMemberFields();
            if (!$schema) {
                $schema = [['key' => '', 'label' => '', 'type' => 'text', 'options' => []]];
            }
            $typeLabels = \humhub\modules\thiscoveryForms\services\PanelFieldService::typeLabels();
            ?>
            <div class="cf-folder-form__card">
                <h2 class="cf-folder-form__h"><?= Yii::t('ThiscoveryFormsModule.base', 'Member fields') ?></h2>
                <p class="cf-list-sub">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Every member already has first name, last name, and email. Add further fields to search on, pipe into a survey as {{member.field_key}}, or drop onto a form from Add fields.') ?>
                </p>
                <div data-cf-panel-field-list>
                    <?php foreach ($schema as $i => $field): ?>
                        <?= $this->render('_field_schema_row', [
                            'index' => $i,
                            'field' => $field,
                            'typeLabels' => $typeLabels,
                        ]) ?>
                    <?php endforeach; ?>
                </div>
                <div class="d-none" data-cf-panel-field-proto>
                    <?= $this->render('_field_schema_row', [
                        'index' => '__INDEX__',
                        'field' => ['key' => '', 'label' => '', 'type' => 'text', 'options' => []],
                        'typeLabels' => $typeLabels,
                    ]) ?>
                </div>
                <button type="button" class="btn btn-sm btn-light mt-2" data-cf-add-panel-field>
                    <i class="fa fa-plus"></i> <?= Yii::t('ThiscoveryFormsModule.base', 'Add field') ?>
                </button>
            </div>
        <?php endif; ?>
        <div class="cf-folder-form__actions">
            <?= Button::save($isNew
                ? Yii::t('ThiscoveryFormsModule.base', 'Create panel')
                : Yii::t('ThiscoveryFormsModule.base', 'Save panel'))->submit() ?>
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Cancel'))
                ->link($isNew ? Url::toPanelIndex($contentContainer) : Url::toPanelView($panel, $contentContainer))
                ->loader(false) ?>
        </div>
    <?= Html::endForm() ?>
</div>
<?php
$this->registerJs('humhub.require("thiscoveryForms").initPanelEdit("#cf-panel-edit");', \yii\web\View::POS_READY);
?>
