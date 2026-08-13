<?php

use humhub\modules\content\widgets\richtext\RichText;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\HtmlSanitizer;
use humhub\modules\thiscoveryForms\services\VariableSubstitutor;
use humhub\modules\file\widgets\FilePreview;
use humhub\modules\file\widgets\UploadButton;
use humhub\modules\file\widgets\UploadProgress;
use yii\helpers\Html;

/** @var FormField $field */
/** @var mixed $value */
/** @var int $displayIndex */
/** @var CustomForm $formModel */
/** @var array $allValues */
/** @var FormField[] $allFields */

$userId = (int)(Yii::$app->user->id ?? 0);
$inputName = 'SubmitForm[values][' . $field->id . ']';
$inputId = 'cf-input-' . $field->id;
$choiceOptions = FormField::isChoiceType($field->type) ? $field->getShuffledOptions($userId) : [];

if ($field->type === FormField::TYPE_RICH_TEXT): ?>
    <div class="cf-rich-block richtext-output">
        <?= RichText::convert($field->getRichTextContent(), RichText::FORMAT_HTML) ?>
    </div>
<?php elseif ($field->type === FormField::TYPE_HTML):
    $htmlCfg = $field->getHtmlConfig();
    $html = (new VariableSubstitutor())->substitute(
        $htmlCfg['html'],
        Yii::$app->user->identity,
        $formModel,
        $allValues,
        $allFields
    );
    $html = (new HtmlSanitizer())->sanitize($html);
    ?>
    <?php if ($htmlCfg['collect']): ?>
        <div class="cf-question__meta">
            <span class="cf-question__num"><?= (int)$displayIndex ?></span>
            <?php if ($htmlCfg['required']): ?>
                <span class="cf-question__required"><?= Yii::t('ThiscoveryFormsModule.base', 'Required') ?></span>
            <?php endif; ?>
        </div>
        <div class="cf-question__label">
            <?= Html::encode($field->label) ?>
            <?php if ($htmlCfg['required']): ?><span class="text-danger">*</span><?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="cf-html-block" data-cf-html-block="<?= (int)$field->id ?>" data-cf-html-var="<?= Html::encode($htmlCfg['variable']) ?>">
        <?= $html ?>
        <?php if ($htmlCfg['collect']): ?>
            <?php
            $stored = is_array($value) ? implode(', ', $value) : (string)$value;
            ?>
            <?= Html::hiddenInput($inputName, $stored, [
                'data-cf-html-value' => true,
                'data-cf-html-required' => $htmlCfg['required'] ? '1' : '0',
            ]) ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="cf-question__meta">
        <span class="cf-question__num"><?= (int)$displayIndex ?></span>
        <?php if ($field->required || ($field->type === FormField::TYPE_HTML && $field->getHtmlConfig()['required'])): ?>
            <span class="cf-question__required"><?= Yii::t('ThiscoveryFormsModule.base', 'Required') ?></span>
        <?php endif; ?>
    </div>

    <label class="cf-question__label" for="<?= Html::encode($inputId) ?>">
        <?= Html::encode($field->label) ?>
        <?php if ($field->required): ?><span class="text-danger">*</span><?php endif; ?>
    </label>

    <?php if ($field->help_text): ?>
        <p class="cf-question__help"><?= Html::encode($field->help_text) ?></p>
    <?php endif; ?>

    <div class="cf-question__control">
        <?php if ($field->type === FormField::TYPE_TEXTAREA): ?>
            <?= Html::textarea($inputName, is_array($value) ? '' : $value, [
                'class' => 'form-control cf-input',
                'rows' => 4,
                'id' => $inputId,
                'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Your answer'),
            ]) ?>
        <?php elseif ($field->type === FormField::TYPE_NUMBER): ?>
            <?= Html::input('number', $inputName, is_array($value) ? '' : $value, [
                'class' => 'form-control cf-input',
                'id' => $inputId,
            ]) ?>
        <?php elseif ($field->type === FormField::TYPE_EMAIL): ?>
            <?= Html::input('email', $inputName, is_array($value) ? '' : $value, [
                'class' => 'form-control cf-input',
                'id' => $inputId,
                'placeholder' => 'name@example.com',
            ]) ?>
        <?php elseif ($field->type === FormField::TYPE_DATE): ?>
            <?= Html::input('date', $inputName, is_array($value) ? '' : $value, [
                'class' => 'form-control cf-input',
                'id' => $inputId,
            ]) ?>
        <?php elseif ($field->type === FormField::TYPE_DROPDOWN): ?>
            <?= Html::dropDownList($inputName, is_array($value) ? null : $value, array_combine($choiceOptions, $choiceOptions) ?: [], [
                'class' => 'form-control cf-input',
                'id' => $inputId,
                'prompt' => Yii::t('ThiscoveryFormsModule.base', 'Please select'),
            ]) ?>
        <?php elseif ($field->type === FormField::TYPE_RADIO): ?>
            <div class="cf-choice-list">
                <?php foreach ($choiceOptions as $opt): ?>
                    <label class="cf-choice">
                        <?= Html::radio($inputName, (string)$value === (string)$opt, ['value' => $opt]) ?>
                        <span><?= Html::encode($opt) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php elseif ($field->type === FormField::TYPE_CHECKBOX): ?>
            <?php
            $selected = is_array($value) ? $value : [];
            $maxSelect = $field->getMaxSelect();
            $exclusiveOptions = $field->getExclusiveOptions();
            $listAttrs = ['class' => 'cf-choice-list'];
            if ($maxSelect) {
                $listAttrs['data-cf-max-select'] = (int)$maxSelect;
            }
            if ($exclusiveOptions) {
                $listAttrs['data-cf-exclusive'] = implode('|', $exclusiveOptions);
            }
            ?>
            <div <?= \yii\helpers\Html::renderTagAttributes($listAttrs) ?>>
                <?php foreach ($choiceOptions as $opt): ?>
                    <label class="cf-choice">
                        <?= Html::checkbox($inputName . '[]', in_array($opt, $selected, true), ['value' => $opt]) ?>
                        <span><?= Html::encode($opt) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php elseif ($field->type === FormField::TYPE_RATING): ?>
            <?php
            $scale = $field->getRatingScale();
            $min = (int)$scale['min'];
            $max = (int)$scale['max'];
            $step = max(1, (int)$scale['step']);
            $lowLabel = $scale['lowLabel'] ?? '';
            $highLabel = $scale['highLabel'] ?? '';
            $selected = is_array($value) ? null : $value;
            $ratingCount = 0;
            for ($ratingValue = $min; $ratingValue <= $max; $ratingValue += $step) {
                $ratingCount++;
            }
            $ratingCount = max(1, $ratingCount);
            ?>
            <div class="cf-rating-scale" data-cf-rating style="--cf-rating-count: <?= (int)$ratingCount ?>">
                <div class="cf-rating-options" role="radiogroup" aria-label="<?= Html::encode($field->label) ?>">
                    <?php for ($ratingValue = $min; $ratingValue <= $max; $ratingValue += $step): ?>
                        <label class="cf-rating-option<?= (string)$selected === (string)$ratingValue ? ' is-selected' : '' ?>">
                            <?= Html::radio($inputName, (string)$selected === (string)$ratingValue, [
                                'value' => $ratingValue,
                                'required' => (bool)$field->required,
                            ]) ?>
                            <span class="cf-rating-pill"><?= (int)$ratingValue ?></span>
                        </label>
                    <?php endfor; ?>
                </div>
                <?php if ($lowLabel !== '' || $highLabel !== ''): ?>
                    <div class="cf-rating-labels">
                        <span class="cf-rating-label cf-rating-label-low"><?= Html::encode($lowLabel) ?></span>
                        <span class="cf-rating-label cf-rating-label-high"><?= Html::encode($highLabel) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($field->type === FormField::TYPE_RANKING): ?>
            <?php
            $options = $choiceOptions;
            $ranked = [];
            if (is_array($value)) {
                foreach ($value as $option) {
                    if (in_array((string)$option, $options, true)) {
                        $ranked[] = (string)$option;
                    }
                }
            } elseif ($value !== null && $value !== '') {
                $decoded = json_decode((string)$value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    foreach ($decoded as $option) {
                        if (in_array((string)$option, $options, true)) {
                            $ranked[] = (string)$option;
                        }
                    }
                }
            }
            foreach ($options as $option) {
                if (!in_array($option, $ranked, true)) {
                    $ranked[] = $option;
                }
            }
            ?>
            <div class="cf-ranking" data-cf-ranking>
                <p class="cf-ranking-hint">
                    <i class="fa fa-arrows-v" aria-hidden="true"></i>
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Drag the handle or use the arrows to rank (1 = highest preference).') ?>
                </p>
                <ol class="cf-ranking-list" data-cf-ranking-list>
                    <?php foreach ($ranked as $rIndex => $option): ?>
                        <li class="cf-ranking-item" data-value="<?= Html::encode($option) ?>">
                            <span class="cf-ranking-handle" data-cf-rank-handle role="button" tabindex="0" title="<?= Yii::t('ThiscoveryFormsModule.base', 'Drag to reorder') ?>" aria-label="<?= Yii::t('ThiscoveryFormsModule.base', 'Drag to reorder') ?>">
                                <i class="fa fa-bars" aria-hidden="true"></i>
                            </span>
                            <span class="cf-ranking-order" aria-hidden="true"><?= (int)$rIndex + 1 ?></span>
                            <span class="cf-ranking-label"><?= Html::encode($option) ?></span>
                            <span class="cf-ranking-controls">
                                <button type="button" class="cf-ranking-btn" data-cf-rank-up title="<?= Yii::t('ThiscoveryFormsModule.base', 'Move up') ?>" aria-label="<?= Yii::t('ThiscoveryFormsModule.base', 'Move up') ?>">
                                    <i class="fa fa-chevron-up"></i>
                                </button>
                                <button type="button" class="cf-ranking-btn" data-cf-rank-down title="<?= Yii::t('ThiscoveryFormsModule.base', 'Move down') ?>" aria-label="<?= Yii::t('ThiscoveryFormsModule.base', 'Move down') ?>">
                                    <i class="fa fa-chevron-down"></i>
                                </button>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?= Html::hiddenInput($inputName, json_encode($ranked), [
                    'data-cf-ranking-value' => true,
                ]) ?>
            </div>
        <?php elseif ($field->type === FormField::TYPE_FILE): ?>
            <?php
            $guid = is_array($value) ? '' : (string)$value;
            $uploadId = 'cfFile' . $field->id;
            ?>
            <div class="cf-file-box">
                <?= Html::hiddenInput($inputName, $guid, [
                    'id' => $uploadId . '_guid',
                    'data-cf-file-guid' => true,
                ]) ?>
                <?= UploadButton::widget([
                    'id' => $uploadId,
                    'label' => Yii::t('ThiscoveryFormsModule.base', 'Upload file'),
                    'tooltip' => false,
                    'cssButtonClass' => 'btn-primary btn-sm',
                    'single' => true,
                    'progress' => '#' . $uploadId . '_progress',
                    'preview' => '#' . $uploadId . '_preview',
                ]) ?>
                <?= UploadProgress::widget(['id' => $uploadId . '_progress']) ?>
                <?= FilePreview::widget([
                    'id' => $uploadId . '_preview',
                    'edit' => true,
                    'options' => ['style' => 'margin-top:8px'],
                ]) ?>
            </div>
        <?php else: ?>
            <?php
            $textValue = is_array($value) ? '' : (string)$value;
            if ($textValue === '') {
                $prefill = $field->getPrefillValue();
                if ($prefill !== null) {
                    $textValue = $prefill;
                }
            }
            ?>
            <?= Html::textInput($inputName, $textValue, [
                'class' => 'form-control cf-input',
                'id' => $inputId,
                'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Your answer'),
            ]) ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
