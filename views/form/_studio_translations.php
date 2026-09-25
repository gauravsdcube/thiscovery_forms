<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormFieldI18n;
use humhub\modules\thiscoveryForms\models\FormI18n;
use humhub\modules\thiscoveryForms\services\TranslationService;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var bool $isNew */

$source = $formModel->getSourceLanguage();
$enabled = $formModel->getEnabledLanguages();
$targets = array_values(array_filter($enabled, static fn($l) => $l !== $source));
$lang = (string)Yii::$app->request->get('lang', $targets[0] ?? '');
if ($lang === '' || $lang === $source || !in_array($lang, $enabled, true)) {
    $lang = $targets[0] ?? '';
}
$labels = TranslationService::languageLabels();
$targetDir = TranslationService::isRtl($lang) ? 'rtl' : 'ltr';
$formI18n = ($lang && !$isNew)
    ? (FormI18n::findOne(['form_id' => $formModel->id, 'language' => $lang]) ?: new FormI18n())
    : new FormI18n();
$pct = ($lang && !$isNew) ? (new TranslationService())->completeness($formModel, $lang) : 0;
?>

<div class="cf-studio__settings">
    <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Translations') ?></h5>
    <p class="cf-hint text-muted">
        <?= Yii::t('ThiscoveryFormsModule.base', 'Keep one form and overlay labels in other languages. Enable languages on the Settings tab, or import a translation file.') ?>
    </p>

    <?php if ($isNew): ?>
        <div class="alert alert-info">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Save the form first, then add translations.') ?>
        </div>
    <?php else: ?>
        <h6><?= Yii::t('ThiscoveryFormsModule.base', 'Export and import translations') ?></h6>
        <?php
        $i18nNotice = Yii::$app->session->getFlash('cf_i18n_import_notice');
        if (is_array($i18nNotice) && !empty($i18nNotice['message'])):
            $i18nType = (($i18nNotice['type'] ?? '') === 'success') ? 'success' : 'danger';
        ?>
            <div class="alert alert-<?= Html::encode($i18nType) ?>" role="alert">
                <?= Html::encode((string)$i18nNotice['message']) ?>
            </div>
        <?php endif; ?>
        <p class="cf-hint text-muted">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Export every question in the source language. Translate the extra language columns (you can fill several languages in one file), then import it back. Questions themselves are not replaced — only the overlay for each language is updated.') ?>
        </p>
        <p>
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Export CSV'))
                ->link(Url::toExportTranslations($formModel, 'csv'))
                ->icon('download')
                ->loader(false) ?>
            <?= Button::light(Yii::t('ThiscoveryFormsModule.base', 'Export JSON'))
                ->link(Url::toExportTranslations($formModel, 'json'))
                ->icon('download')
                ->loader(false) ?>
        </p>
        <div class="form-group">
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Translation file') ?></label>
            <input type="file" name="translation_file" class="form-control" form="cf-i18n-import-form" accept=".json,.csv,application/json,text/csv">
        </div>
        <button type="submit" class="btn btn-primary" form="cf-i18n-import-form">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Import translations') ?>
        </button>

        <?php if (!$targets): ?>
            <div class="alert alert-info mt-3">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Add at least one extra language on the Settings tab to edit translations here, or import a file with language columns such as cy, fr or de.') ?>
            </div>
        <?php else: ?>
        <hr>
        <p>
            <?php foreach ($targets as $code): ?>
                <a class="btn btn-sm <?= $code === $lang ? 'btn-primary' : 'btn-light' ?>"
                   href="<?= Html::encode(Url::toEdit($formModel) . '?tab=translations&lang=' . urlencode($code)) ?>">
                    <?= Html::encode($labels[$code] ?? $code) ?>
                </a>
            <?php endforeach; ?>
            <span class="text-muted ms-2"><?= Yii::t('ThiscoveryFormsModule.base', '{pct}% translated', ['pct' => $pct]) ?></span>
        </p>

        <?php if (
            Yii::$app->hasModule('thiscovery-translate')
            && Yii::$app->getModule('thiscovery-translate')->getIsEnabled()
            && class_exists(\humhub\modules\thiscoveryTranslate\models\ModuleSettings::class)
            && \humhub\modules\thiscoveryTranslate\models\ModuleSettings::isFormsTranslateEnabled()
        ): ?>
            <?php
            $ttStatus = [];
            if (class_exists(\humhub\modules\thiscoveryTranslate\services\FormTranslateAdapter::class)) {
                $ttStatus = (new \humhub\modules\thiscoveryTranslate\services\FormTranslateAdapter())->statusForForm($formModel);
            }
            ?>
            <?php if ($ttStatus): ?>
                <div class="alert alert-light border mb-2">
                    <strong><?= Yii::t('ThiscoveryFormsModule.base', 'Machine translation status') ?></strong>
                    <ul class="mb-0 mt-1">
                        <?php foreach ($ttStatus as $code => $meta): ?>
                            <li>
                                <?= Html::encode($labels[$code] ?? $code) ?>:
                                <?= Html::encode($meta['status'] ?? '') ?>
                                <?php if (!empty($meta['fields'])): ?>
                                    (<?= (int)$meta['fields'] ?> <?= Yii::t('ThiscoveryFormsModule.base', 'field overlays') ?>)
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <p>
                <?= Html::beginForm(Url::studioAction($formModel, 'generate-translations'), 'post', ['style' => 'display:inline']) ?>
                    <?= Html::hiddenInput('language', $lang) ?>
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Generate machine translation for {lang}', ['lang' => $labels[$lang] ?? $lang]) ?>
                    </button>
                <?= Html::endForm() ?>
                <?= Html::beginForm(Url::studioAction($formModel, 'generate-translations'), 'post', ['style' => 'display:inline']) ?>
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Generate all languages') ?>
                    </button>
                <?= Html::endForm() ?>
            </p>
            <p class="cf-hint text-muted">
                <?= Yii::t('ThiscoveryFormsModule.base', 'After editing a translated field manually, save to lock it. Generate only updates unlocked machine overlays for changed source text.') ?>
            </p>
        <?php endif; ?>

        <?= Html::beginForm(Url::studioAction($formModel, 'translations-save'), 'post') ?>
            <?= Html::hiddenInput('language', $lang) ?>
            <h6><?= Yii::t('ThiscoveryFormsModule.base', 'Form') ?></h6>
            <div class="cf-i18n-grid">
                <div>
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Source') ?></label>
                    <div class="form-control-plaintext"><?= Html::encode($formModel->title) ?></div>
                </div>
                <div>
                    <label class="cf-label"><?= Html::encode($labels[$lang] ?? $lang) ?></label>
                    <input type="text" name="form_i18n[title]" class="form-control" dir="<?= $targetDir ?>" value="<?= Html::encode((string)$formI18n->title) ?>">
                </div>
                <div>
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Description') ?></label>
                    <div class="form-control-plaintext"><?= nl2br(Html::encode((string)$formModel->description)) ?></div>
                </div>
                <div>
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Description') ?></label>
                    <textarea name="form_i18n[description]" class="form-control" dir="<?= $targetDir ?>" rows="3"><?= Html::encode((string)$formI18n->description) ?></textarea>
                </div>
            </div>

            <h6 class="mt-3"><?= Yii::t('ThiscoveryFormsModule.base', 'Fields') ?></h6>
            <?php foreach ($formModel->fields as $field): ?>
                <?php
                /** @var FormField $field */
                $fi = FormFieldI18n::findOne(['field_id' => $field->id, 'language' => $lang]) ?: new FormFieldI18n();
                $overlay = $fi->getOptionsOverlay();
                $optText = '';
                if (isset($overlay['options']) && is_array($overlay['options'])) {
                    $optText = \humhub\modules\thiscoveryForms\services\ChoiceOptions::toText(
                        \humhub\modules\thiscoveryForms\services\ChoiceOptions::itemsFromDecoded(['options' => $overlay['options']])
                    );
                }
                ?>
                <div class="cf-i18n-field">
                    <div class="cf-i18n-grid">
                        <div>
                            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Label') ?></label>
                            <div class="form-control-plaintext"><?= Html::encode($field->label) ?></div>
                        </div>
                        <div>
                            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Translated label') ?></label>
                            <input type="text" name="field_i18n[<?= (int)$field->id ?>][label]" class="form-control" dir="<?= $targetDir ?>" value="<?= Html::encode((string)$fi->label) ?>">
                        </div>
                        <?php if ($field->help_text): ?>
                            <div>
                                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Help text') ?></label>
                                <div class="form-control-plaintext"><?= Html::encode($field->help_text) ?></div>
                            </div>
                            <div>
                                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Translated help') ?></label>
                                <input type="text" name="field_i18n[<?= (int)$field->id ?>][help_text]" class="form-control" dir="<?= $targetDir ?>" value="<?= Html::encode((string)$fi->help_text) ?>">
                            </div>
                        <?php endif; ?>
                        <?php if (FormField::isChoiceType($field->type) && $field->getOptions()): ?>
                            <div>
                                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Choices') ?></label>
                                <div class="form-control-plaintext"><?= nl2br(Html::encode($field->getOptionsAsText())) ?></div>
                            </div>
                            <div>
                                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Translated choices') ?></label>
                                <textarea name="field_i18n[<?= (int)$field->id ?>][options]" class="form-control" dir="<?= $targetDir ?>" rows="4"><?= Html::encode($optText) ?></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-primary mt-3"><?= Yii::t('ThiscoveryFormsModule.base', 'Save translations') ?></button>
        <?= Html::endForm() ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
