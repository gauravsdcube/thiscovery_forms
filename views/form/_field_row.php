<?php

use humhub\modules\content\widgets\richtext\RichTextField;
use humhub\modules\thiscoveryForms\models\FormField;
use yii\helpers\Html;

/** @var string|int $key */
/** @var FormField $field */
/** @var FormField[] $allFields */
/** @var bool $collapsed */

$allFields = $allFields ?? [];
$collapsed = $collapsed ?? true;
$namePrefix = 'fields[' . $key . ']';
$type = $field->type ?: FormField::TYPE_TEXT;
$needsOptions = FormField::isChoiceType($type);
$isRating = ($type === FormField::TYPE_RATING);
$isPageBreak = ($type === FormField::TYPE_PAGE_BREAK);
$isRichText = ($type === FormField::TYPE_RICH_TEXT);
$isHtml = ($type === FormField::TYPE_HTML);
$isAnswerable = !in_array($type, [FormField::TYPE_PAGE_BREAK, FormField::TYPE_RICH_TEXT], true)
    && !($type === FormField::TYPE_HTML && !$field->getHtmlConfig()['collect']);
$rating = $field->getRatingScale();
$pageBreak = $field->getPageBreakConfig();
$htmlCfg = $field->getHtmlConfig();
$hasCondition = $field->hasCondition();
$randomize = $field->isRandomizeOptions();

$conditionOptions = ['' => Yii::t('ThiscoveryFormsModule.base', 'None')];
$pageKeyOptions = ['' => Yii::t('ThiscoveryFormsModule.base', 'Select page…')];
foreach ($allFields as $other) {
    if ((string)$other->id === (string)$key || (string)($other->id ?: '') === '') {
        // still list by key for unsaved
    }
    $otherKey = $other->id ?: null;
    if ($otherKey && (string)$otherKey === (string)$key) {
        continue;
    }
    if ($other->id && (string)$other->id !== (string)$key) {
        if ($other->collectsAnswer() || FormField::isChoiceType($other->type) || !FormField::isStructuralType($other->type)) {
            if ($other->type !== FormField::TYPE_PAGE_BREAK && $other->type !== FormField::TYPE_RICH_TEXT) {
                $conditionOptions[$other->id] = $other->label ?: ('#' . $other->id);
            }
        }
        if ($other->type === FormField::TYPE_PAGE_BREAK) {
            $pk = $other->getPageBreakConfig()['pageKey'];
            $title = $other->getPageBreakConfig()['title'] ?: $pk;
            $pageKeyOptions[$pk] = $title . ' (' . $pk . ')';
        }
    }
}
// Always include own page key option when editing page break targets from other breaks
if ($isPageBreak && !empty($pageBreak['pageKey'])) {
    // no-op for self
}

$typeLabels = FormField::getTypeLabels();
$operatorLabels = FormField::getOperatorLabels();
?>
<div class="cf-field-card thiscovery-forms-field-row<?= $collapsed ? ' is-collapsed' : ' is-expanded' ?>" data-cf-key="<?= Html::encode($key) ?>" data-cf-type="<?= Html::encode($type) ?>">
    <?= Html::hiddenInput($namePrefix . '[id]', $field->id ?: '') ?>

    <div class="cf-field-card__header" data-cf-toggle-card>
        <div class="cf-field-card__handle" data-cf-drag-handle title="<?= Yii::t('ThiscoveryFormsModule.base', 'Drag to reorder') ?>">
            <i class="fa fa-bars"></i>
        </div>
        <div class="cf-field-card__title">
            <span class="cf-field-card__index" data-cf-index></span>
            <span class="cf-field-card__name" data-cf-title>
                <?= Html::encode($field->label ?: Yii::t('ThiscoveryFormsModule.base', 'Untitled field')) ?>
            </span>
            <span class="cf-field-card__type-badge" data-cf-type-label>
                <?= Html::encode($typeLabels[$type] ?? $type) ?>
            </span>
            <?php if ($field->required): ?>
                <span class="cf-field-card__req"><?= Yii::t('ThiscoveryFormsModule.base', 'Required') ?></span>
            <?php endif; ?>
        </div>
        <div class="cf-field-card__actions">
            <button type="button" class="btn btn-sm btn-light" data-cf-toggle-card-btn title="<?= Yii::t('ThiscoveryFormsModule.base', 'Edit field') ?>">
                <i class="fa fa-pencil"></i>
            </button>
            <button type="button" class="btn btn-sm btn-light" data-cf-move-up title="<?= Yii::t('ThiscoveryFormsModule.base', 'Move up') ?>">
                <i class="fa fa-arrow-up"></i>
            </button>
            <button type="button" class="btn btn-sm btn-light" data-cf-move-down title="<?= Yii::t('ThiscoveryFormsModule.base', 'Move down') ?>">
                <i class="fa fa-arrow-down"></i>
            </button>
            <button type="button" class="btn btn-sm btn-light" data-cf-toggle-advanced title="<?= Yii::t('ThiscoveryFormsModule.base', 'Logic') ?>">
                <i class="fa fa-code-fork"></i>
            </button>
            <button type="button" class="btn btn-sm btn-danger" data-cf-remove-field title="<?= Yii::t('ThiscoveryFormsModule.base', 'Remove') ?>">
                <i class="fa fa-trash"></i>
            </button>
        </div>
    </div>

    <div class="cf-field-card__body" data-cf-card-body>
        <div class="row g-3">
            <div class="col-md-6" data-cf-label-wrap>
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Label') ?></label>
                <?= Html::textInput($namePrefix . '[label]', $field->label, [
                    'class' => 'form-control',
                    'data-cf-field-label' => true,
                    'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Field label'),
                ]) ?>
            </div>
            <div class="col-md-3">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Type') ?></label>
                <?= Html::dropDownList($namePrefix . '[type]', $type, $typeLabels, [
                    'class' => 'form-control',
                    'data-cf-field-type' => true,
                ]) ?>
            </div>
            <div class="col-md-3" data-cf-required-wrap>
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Settings') ?></label>
                <div class="cf-switch">
                    <label>
                        <?= Html::checkbox($namePrefix . '[required]', (bool)$field->required, [
                            'value' => '1',
                            'uncheck' => null,
                            'data-cf-required' => true,
                        ]) ?>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Required') ?>
                    </label>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1" data-cf-help-wrap>
            <div class="col-md-12">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Help text') ?>
                    <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
                </label>
                <?= Html::textInput($namePrefix . '[help_text]', $field->help_text, [
                    'class' => 'form-control',
                    'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Shown under the field'),
                ]) ?>
            </div>
        </div>

        <div class="cf-options-panel<?= $needsOptions ? '' : ' d-none' ?>" data-cf-options-panel>
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Choices') ?>
                <span class="cf-hint<?= $type === FormField::TYPE_RANKING ? ' d-none' : '' ?>" data-cf-options-hint-choice><?= Yii::t('ThiscoveryFormsModule.base', 'One option per line') ?></span>
                <span class="cf-hint<?= $type === FormField::TYPE_RANKING ? '' : ' d-none' ?>" data-cf-options-hint-ranking><?= Yii::t('ThiscoveryFormsModule.base', 'Items respondents will rank (one per line)') ?></span>
            </label>
            <?= Html::textarea($namePrefix . '[options]', $field->getOptionsAsText(), [
                'class' => 'form-control',
                'rows' => 4,
                'placeholder' => Yii::t('ThiscoveryFormsModule.base', "Option A\nOption B\nOption C"),
                'data-cf-options' => true,
            ]) ?>
            <div class="cf-switch mt-2">
                <label>
                    <?= Html::checkbox($namePrefix . '[randomize]', $randomize, [
                        'value' => '1',
                        'uncheck' => null,
                        'data-cf-randomize' => true,
                    ]) ?>
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Randomize choice order per respondent') ?>
                </label>
            </div>
            <div class="cf-field-note<?= $type === FormField::TYPE_RANKING ? '' : ' d-none' ?>" data-cf-ranking-note>
                <i class="fa fa-info-circle" aria-hidden="true"></i>
                <div>
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Respondents drag these items into their preferred order. Put the default order here; 1 will mean highest preference when answering.') ?>
                </div>
            </div>
            <div class="cf-field-note<?= in_array($type, [FormField::TYPE_DROPDOWN, FormField::TYPE_RADIO, FormField::TYPE_CHECKBOX], true) ? '' : ' d-none' ?>" data-cf-choice-note>
                <i class="fa fa-info-circle" aria-hidden="true"></i>
                <div>
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Each line becomes one selectable choice. Duplicate or empty lines are ignored.') ?>
                </div>
            </div>
            <div class="cf-field-note" data-cf-randomize-note>
                <i class="fa fa-random" aria-hidden="true"></i>
                <div>
                    <?= Yii::t('ThiscoveryFormsModule.base', 'When enabled, each participant sees choices in a different order. The same person keeps a stable order if they reopen the form.') ?>
                </div>
            </div>
        </div>

        <div class="cf-rating-panel<?= $isRating ? '' : ' d-none' ?>" data-cf-rating-panel>
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Rating scale configuration') ?></label>
            <div class="cf-field-note">
                <i class="fa fa-info-circle" aria-hidden="true"></i>
                <div>
                    <strong><?= Yii::t('ThiscoveryFormsModule.base', 'How the scale is built') ?></strong>
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Values start at Min and increase by Step until Max. Example: Min 1, Max 5, Step 1 → 1, 2, 3, 4, 5. Example: Min 10, Max 100, Step 10 → 10, 20, 30 … 100. With Min 1, Max 100, Step 10 you get 1, 11, 21 … (100 only appears if it lands exactly on that sequence).') ?>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Min') ?></label>
                    <?= Html::input('number', $namePrefix . '[rating_min]', (int)$rating['min'], [
                        'class' => 'form-control',
                        'min' => 0,
                    ]) ?>
                    <div class="cf-field-help"><?= Yii::t('ThiscoveryFormsModule.base', 'First value') ?></div>
                </div>
                <div class="col-md-2">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Max') ?></label>
                    <?= Html::input('number', $namePrefix . '[rating_max]', (int)$rating['max'], [
                        'class' => 'form-control',
                        'min' => 1,
                    ]) ?>
                    <div class="cf-field-help"><?= Yii::t('ThiscoveryFormsModule.base', 'Upper limit') ?></div>
                </div>
                <div class="col-md-2">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Step') ?></label>
                    <?= Html::input('number', $namePrefix . '[rating_step]', (int)$rating['step'], [
                        'class' => 'form-control',
                        'min' => 1,
                    ]) ?>
                    <div class="cf-field-help"><?= Yii::t('ThiscoveryFormsModule.base', 'Increment') ?></div>
                </div>
                <div class="col-md-3">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Low label') ?></label>
                    <?= Html::textInput($namePrefix . '[rating_low_label]', $rating['lowLabel'], [
                        'class' => 'form-control',
                        'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'e.g. Poor'),
                    ]) ?>
                    <div class="cf-field-help"><?= Yii::t('ThiscoveryFormsModule.base', 'Shown under the lowest value') ?></div>
                </div>
                <div class="col-md-3">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'High label') ?></label>
                    <?= Html::textInput($namePrefix . '[rating_high_label]', $rating['highLabel'], [
                        'class' => 'form-control',
                        'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'e.g. Excellent'),
                    ]) ?>
                    <div class="cf-field-help"><?= Yii::t('ThiscoveryFormsModule.base', 'Shown under the highest value') ?></div>
                </div>
            </div>
        </div>

        <div class="cf-page-panel<?= $isPageBreak ? '' : ' d-none' ?>" data-cf-page-panel>
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Page break') ?></label>
            <div class="cf-field-note">
                <i class="fa fa-info-circle" aria-hidden="true"></i>
                <div>
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Inserts a new page after the previous fields. Respondents use Next/Back. Optional branch rules can jump to another page when leaving this page, based on an answer.') ?>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Page key') ?></label>
                    <?= Html::textInput($namePrefix . '[page_key]', $pageBreak['pageKey'], [
                        'class' => 'form-control',
                        'data-cf-page-key' => true,
                        'placeholder' => 'p2',
                    ]) ?>
                    <div class="cf-field-help"><?= Yii::t('ThiscoveryFormsModule.base', 'Stable id used by branch rules') ?></div>
                </div>
                <div class="col-md-8">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Page title') ?>
                        <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
                    </label>
                    <?= Html::textInput($namePrefix . '[page_title]', $pageBreak['title'], [
                        'class' => 'form-control',
                        'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Shown at the top of the next page'),
                    ]) ?>
                </div>
            </div>
            <div class="cf-branches mt-3" data-cf-branches>
                <div class="cf-advanced-title"><?= Yii::t('ThiscoveryFormsModule.base', 'Branch rules (optional)') ?></div>
                <div class="cf-field-help mb-2"><?= Yii::t('ThiscoveryFormsModule.base', 'Evaluated when the respondent clicks Next on the page before this break. First matching rule wins; otherwise the next page is used.') ?></div>
                <div data-cf-branch-list>
                    <?php
                    $branches = $pageBreak['branches'] ?: [['fieldKey' => '', 'operator' => FormField::OP_EQUALS, 'value' => '', 'gotoPageKey' => '']];
                    foreach ($branches as $bi => $branch):
                    ?>
                        <div class="cf-branch-row row g-2 mb-2" data-cf-branch-row>
                            <div class="col-md-3">
                                <?= Html::dropDownList($namePrefix . '[branches][' . $bi . '][fieldKey]', $branch['fieldKey'] ?? '', $conditionOptions, [
                                    'class' => 'form-control',
                                    'data-cf-branch-field' => true,
                                ]) ?>
                            </div>
                            <div class="col-md-2">
                                <?= Html::dropDownList($namePrefix . '[branches][' . $bi . '][operator]', $branch['operator'] ?? FormField::OP_EQUALS, $operatorLabels, [
                                    'class' => 'form-control',
                                ]) ?>
                            </div>
                            <div class="col-md-2">
                                <?= Html::textInput($namePrefix . '[branches][' . $bi . '][value]', $branch['value'] ?? '', [
                                    'class' => 'form-control',
                                    'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Value'),
                                ]) ?>
                            </div>
                            <div class="col-md-3">
                                <?= Html::textInput($namePrefix . '[branches][' . $bi . '][gotoPageKey]', $branch['gotoPageKey'] ?? '', [
                                    'class' => 'form-control',
                                    'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Go to page key'),
                                    'list' => 'cf-page-keys',
                                ]) ?>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-sm btn-light" data-cf-remove-branch title="<?= Yii::t('ThiscoveryFormsModule.base', 'Remove') ?>">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-sm btn-light" data-cf-add-branch>
                    <i class="fa fa-plus"></i> <?= Yii::t('ThiscoveryFormsModule.base', 'Add branch rule') ?>
                </button>
            </div>
        </div>

        <div class="cf-rich-panel<?= $isRichText ? '' : ' d-none' ?>" data-cf-rich-panel>
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Rich text content') ?></label>
            <div class="cf-field-note">
                <i class="fa fa-info-circle" aria-hidden="true"></i>
                <div><?= Yii::t('ThiscoveryFormsModule.base', 'Display-only section shown to respondents. Place at the start or anywhere between questions. Formatting toolbar is available below.') ?></div>
            </div>
            <div class="cf-rich-editor" data-cf-rich-editor>
                <?= RichTextField::widget([
                    'id' => 'cf-rich-field-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', (string)$key),
                    'name' => $namePrefix . '[rich_content]',
                    'value' => $field->getRichTextContent(),
                    'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Write instructions, context, or intro text…'),
                    'backupInterval' => 0,
                    'exclude' => ['oembed', 'mention'],
                ]) ?>
            </div>
        </div>

        <div class="cf-html-panel<?= $isHtml ? '' : ' d-none' ?>" data-cf-html-panel>
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'HTML / custom block') ?></label>
            <div class="cf-field-note">
                <i class="fa fa-info-circle" aria-hidden="true"></i>
                <div>
                    <strong><?= Yii::t('ThiscoveryFormsModule.base', 'Variables') ?></strong>
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Use {{user.displayname}}, {{user.email}}, {{user.guid}}, {{form.title}}, {{answer:FIELD_ID}} or {{answer:Field label}}. Scripts and event handlers are stripped for safety. To collect a value, add an input with data-cf-html-var="yourVariable" (or matching name) and enable Collect below.') ?>
                </div>
            </div>
            <?= Html::textarea($namePrefix . '[html_content]', $htmlCfg['html'], [
                'class' => 'form-control',
                'rows' => 8,
                'placeholder' => '<p>Hello {{user.displayname}}</p>' . "\n" . '<input type="text" data-cf-html-var="score" placeholder="Score">',
            ]) ?>
            <div class="row g-3 mt-1">
                <div class="col-md-4">
                    <div class="cf-switch">
                        <label>
                            <?= Html::checkbox($namePrefix . '[html_collect]', !empty($htmlCfg['collect']), [
                                'value' => '1',
                                'uncheck' => null,
                                'data-cf-html-collect' => true,
                            ]) ?>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Collect a value from this block') ?>
                        </label>
                    </div>
                </div>
                <div class="col-md-4" data-cf-html-var-wrap>
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Variable name') ?></label>
                    <?= Html::textInput($namePrefix . '[html_variable]', $htmlCfg['variable'], [
                        'class' => 'form-control',
                        'placeholder' => 'score',
                    ]) ?>
                </div>
                <div class="col-md-4" data-cf-html-req-wrap>
                    <div class="cf-switch" style="padding-top:1.6rem">
                        <label>
                            <?= Html::checkbox($namePrefix . '[html_required]', !empty($htmlCfg['required']), [
                                'value' => '1',
                                'uncheck' => null,
                            ]) ?>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Required when collecting') ?>
                        </label>
                    </div>
                </div>
            </div>
            <div class="mt-2">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Creator notes') ?>
                    <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
                </label>
                <?= Html::textarea($namePrefix . '[html_instructions]', $htmlCfg['instructions'], [
                    'class' => 'form-control',
                    'rows' => 2,
                    'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Internal notes for other form editors (not shown to respondents)'),
                ]) ?>
            </div>
        </div>

        <div class="cf-advanced-panel<?= $hasCondition ? ' is-open' : '' ?>" data-cf-advanced>
            <div class="cf-advanced-title"><?= Yii::t('ThiscoveryFormsModule.base', 'Conditional visibility') ?></div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Show only if field') ?></label>
                    <?= Html::dropDownList(
                        $namePrefix . '[condition_field]',
                        $field->condition_field_id,
                        $conditionOptions,
                        ['class' => 'form-control', 'data-cf-condition-field' => true]
                    ) ?>
                </div>
                <div class="col-md-4">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Operator') ?></label>
                    <?= Html::dropDownList(
                        $namePrefix . '[condition_operator]',
                        $field->condition_operator ?: FormField::OP_EQUALS,
                        $operatorLabels,
                        ['class' => 'form-control']
                    ) ?>
                </div>
                <div class="col-md-4">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Value') ?></label>
                    <?= Html::textInput($namePrefix . '[condition_value]', $field->condition_value, [
                        'class' => 'form-control',
                        'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Expected value'),
                    ]) ?>
                </div>
            </div>
        </div>
    </div>
</div>
