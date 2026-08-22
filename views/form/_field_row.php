<?php

use humhub\modules\thiscoveryEditor\widgets\EditorField;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\LogicEngine;
use humhub\modules\thiscoveryForms\services\RespondentMetaService;
use yii\helpers\Html;

/** @var string|int $key */
/** @var FormField $field */
/** @var FormField[] $allFields */
/** @var bool $collapsed */
/** @var string[]|null $allowedTypes */

$allFields = $allFields ?? [];
$collapsed = $collapsed ?? true;
$allowedTypes = $allowedTypes ?? null;
$namePrefix = 'fields[' . $key . ']';
$type = $field->type ?: FormField::TYPE_TEXT;
$needsOptions = FormField::isChoiceType($type);
$isRating = ($type === FormField::TYPE_RATING);
$isPageBreak = ($type === FormField::TYPE_PAGE_BREAK);
$isQuestionGroup = ($type === FormField::TYPE_QUESTION_GROUP);
$isGroupEnd = ($type === FormField::TYPE_GROUP_END);
$isRichText = ($type === FormField::TYPE_RICH_TEXT);
$isHtml = ($type === FormField::TYPE_HTML);
$isPanelAttr = ($type === FormField::TYPE_PANEL_ATTR);
$isGrid = in_array($type, [FormField::TYPE_GRID_SINGLE, FormField::TYPE_GRID_MULTI], true);
$isItems = in_array($type, [FormField::TYPE_BEST_WORST, FormField::TYPE_MAXDIFF], true);
$isMaxDiff = ($type === FormField::TYPE_MAXDIFF);
$isDrilldown = ($type === FormField::TYPE_DRILLDOWN);
$isImageArea = ($type === FormField::TYPE_IMAGE_AREA);
$isCarry = FormField::isCarryForwardType($type);
$isAnswerable = !in_array($type, [
        FormField::TYPE_PAGE_BREAK,
        FormField::TYPE_QUESTION_GROUP,
        FormField::TYPE_GROUP_END,
        FormField::TYPE_RICH_TEXT,
    ], true)
    && !($type === FormField::TYPE_HTML && !$field->getHtmlConfig()['collect']);
$rating = $field->getRatingScale();
$pageBreak = $field->getPageBreakConfig();
$htmlCfg = $field->getHtmlConfig();
$emailTemplateOptions = $emailTemplateOptions ?? [0 => Yii::t('ThiscoveryFormsModule.base', 'Default email text')];
$gridCfg = $field->getGridConfig();
$itemsCfg = $field->getItemsConfig();
$imageCfg = $field->getImageAreaConfig();
$logic = $field->getLogic();
$carry = $field->getCarryForward();
$hasCondition = $field->hasCondition();
$randomize = $field->isRandomizeOptions();
$panelAttrKeys = $panelAttrKeys ?? \humhub\modules\thiscoveryForms\services\PanelFieldService::surveyFieldLabels(null);

$conditionOptions = ['' => Yii::t('ThiscoveryFormsModule.base', 'None')];
$pageKeyOptions = ['' => Yii::t('ThiscoveryFormsModule.base', 'Select page…')];
foreach ($allFields as $other) {
    $otherStudioKey = $other->id ? FormField::studioKey((int)$other->id) : '';
    if ($otherStudioKey === '' || $otherStudioKey === (string)$key) {
        continue;
    }
    if ($other->collectsAnswer() || FormField::isChoiceType($other->type) || !FormField::isStructuralType($other->type)) {
        if ($other->type !== FormField::TYPE_PAGE_BREAK && $other->type !== FormField::TYPE_RICH_TEXT && $other->type !== FormField::TYPE_QUESTION_GROUP && $other->type !== FormField::TYPE_GROUP_END) {
            $conditionOptions[$otherStudioKey] = $other->label ?: ('#' . $other->id);
        }
    }
    if ($other->type === FormField::TYPE_PAGE_BREAK) {
        $pk = $other->getPageBreakConfig()['pageKey'];
        $title = $other->getPageBreakConfig()['title'] ?: $pk;
        $pageKeyOptions[$pk] = $title . ' (' . $pk . ')';
    }
}
// Always include own page key option when editing page break targets from other breaks
if ($isPageBreak && !empty($pageBreak['pageKey'])) {
    // no-op for self
}

$typeLabels = FormField::getTypeLabels();
unset($typeLabels[FormField::TYPE_GROUP_END]);
if (is_array($allowedTypes) && $allowedTypes) {
    $typeLabels = array_intersect_key($typeLabels, array_flip($allowedTypes));
}
if ($isGroupEnd) {
    $typeLabels = [FormField::TYPE_GROUP_END => FormField::getTypeLabels()[FormField::TYPE_GROUP_END]];
}
$operatorLabels = FormField::getOperatorLabels();
$actionLabels = LogicEngine::actionLabelsForType($type);
if ($isQuestionGroup && !isset($actionLabels[$logic['action']]) && isset(LogicEngine::actionLabels()[$logic['action']])) {
    $actionLabels = [$logic['action'] => LogicEngine::actionLabels()[$logic['action']]] + $actionLabels;
}
$logicRules = $logic['rules'] ?: [['fieldKey' => '', 'operator' => FormField::OP_EQUALS, 'value' => '']];
?>
<div class="cf-field-card thiscovery-forms-field-row<?= $collapsed ? ' is-collapsed' : ' is-expanded' ?><?= $isQuestionGroup ? ' is-group' : '' ?><?= $isGroupEnd ? ' is-group-end d-none' : '' ?>" data-cf-key="<?= Html::encode($key) ?>" data-cf-type="<?= Html::encode($type) ?>">
    <?= Html::hiddenInput($namePrefix . '[id]', $field->id ?: '') ?>
    <?= Html::hiddenInput($namePrefix . '[sort_order]', (int)$field->sort_order, ['data-cf-sort-order' => true]) ?>
    <?= Html::hiddenInput($namePrefix . '[instrument_role]', $field->getInstrumentRole()) ?>
    <?= Html::hiddenInput($namePrefix . '[logic_keep]', json_encode($logic, JSON_UNESCAPED_UNICODE), ['data-cf-logic-keep' => true]) ?>

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
            <?php if ($field->isHiddenFromRespondent()): ?>
                <span class="cf-field-card__req" data-cf-hidden-badge><?= Yii::t('ThiscoveryFormsModule.base', 'Hidden') ?></span>
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
            <button type="button" class="btn btn-sm btn-light" data-cf-save-question title="<?= Yii::t('ThiscoveryFormsModule.base', 'Save as question template') ?>">
                <i class="fa fa-bookmark-o"></i>
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
                <div class="cf-switch mt-2<?= in_array($type, [FormField::TYPE_PAGE_BREAK, FormField::TYPE_QUESTION_GROUP, FormField::TYPE_GROUP_END, FormField::TYPE_RICH_TEXT], true) ? ' d-none' : '' ?>" data-cf-hidden-wrap>
                    <label>
                        <?= Html::checkbox($namePrefix . '[hidden]', $field->isHiddenFromRespondent(), [
                            'value' => '1',
                            'uncheck' => null,
                            'data-cf-hidden-field' => true,
                            'disabled' => $type === FormField::TYPE_RESPONDENT_META,
                        ]) ?>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Hidden from respondents') ?>
                    </label>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1<?= $type === FormField::TYPE_RESPONDENT_META ? '' : ' d-none' ?>" data-cf-meta-key-wrap>
            <div class="col-md-6">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Records') ?></label>
                <?= Html::dropDownList($namePrefix . '[meta_key]', $field->getRespondentMetaKey() ?: RespondentMetaService::KEY_IP, RespondentMetaService::keyLabels(), [
                    'class' => 'form-control',
                    'data-cf-meta-key' => true,
                ]) ?>
            </div>
        </div>

        <div class="row g-3 mt-1<?= $type === FormField::TYPE_PANEL_ATTR ? '' : ' d-none' ?>" data-cf-panel-key-wrap>
            <div class="col-md-6">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Panel field') ?></label>
                <?= Html::dropDownList($namePrefix . '[panel_key]', $field->getPanelAttrKey() ?: 'first_name', $panelAttrKeys, [
                    'class' => 'form-control',
                    'data-cf-panel-key' => true,
                ]) ?>
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

        <div class="cf-field-note<?= $type === FormField::TYPE_RESPONDENT_META ? '' : ' d-none' ?>" data-cf-meta-note>
            <i class="fa fa-info-circle" aria-hidden="true"></i>
            <div>
                <?= Yii::t('ThiscoveryFormsModule.base', 'Hidden from respondents. Stores this one value on the response for analysis.') ?>
            </div>
        </div>

        <div class="cf-field-note<?= $type === FormField::TYPE_EMAIL ? '' : ' d-none' ?>" data-cf-email-note>
            <i class="fa fa-info-circle" aria-hidden="true"></i>
            <div>
                <?= Yii::t('ThiscoveryFormsModule.base', 'Respondents must enter a valid email address. The form checks the format when they continue or submit.') ?>
            </div>
        </div>

        <div class="cf-field-note<?= $type === FormField::TYPE_PANEL_ATTR ? '' : ' d-none' ?>" data-cf-panel-note>
            <i class="fa fa-info-circle" aria-hidden="true"></i>
            <div>
                <?= Yii::t('ThiscoveryFormsModule.base', 'Copies this panel member value onto the response. Hidden by default; untick Hidden from respondents if people should confirm or update it. Use {{member.field_key}} in labels and email.') ?>
            </div>
        </div>

        <div class="row g-3 mt-1<?= ($field->isHiddenFromRespondent() && $type !== FormField::TYPE_RESPONDENT_META) ? '' : ' d-none' ?>" data-cf-default-wrap>
            <div class="col-md-12">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Stored value') ?>
                    <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
                </label>
                <?= Html::textInput($namePrefix . '[default_value]', $field->getDefaultValue(), [
                    'class' => 'form-control',
                    'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Value stored with the response'),
                    'data-cf-default-value' => true,
                ]) ?>
                <p class="cf-hint text-muted mb-0"><?= Yii::t('ThiscoveryFormsModule.base', 'Use this for an internal variable. Respondents do not see or edit it.') ?></p>
            </div>
        </div>

        <div class="cf-options-panel<?= $needsOptions ? '' : ' d-none' ?>" data-cf-options-panel>
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Choices') ?>
                <span class="cf-hint<?= $type === FormField::TYPE_RANKING ? ' d-none' : '' ?>" data-cf-options-hint-choice><?= Yii::t('ThiscoveryFormsModule.base', 'One option per line. Optional internal code before |') ?></span>
                <span class="cf-hint<?= $type === FormField::TYPE_RANKING ? '' : ' d-none' ?>" data-cf-options-hint-ranking><?= Yii::t('ThiscoveryFormsModule.base', 'Items respondents will rank (one per line)') ?></span>
            </label>
            <?= Html::textarea($namePrefix . '[options]', $field->getOptionsAsText(), [
                'class' => 'form-control',
                'rows' => 4,
                'placeholder' => Yii::t('ThiscoveryFormsModule.base', "P1 | Public sector\nP2 | Private sector\nP3 | Third sector"),
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
            <div class="row g-3 mt-1<?= $type === FormField::TYPE_CHECKBOX ? '' : ' d-none' ?>" data-cf-max-select-wrap>
                <div class="col-md-4">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Minimum selections') ?>
                        <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
                    </label>
                    <?= Html::input('number', $namePrefix . '[min_select]', $field->getMinSelect(), [
                        'class' => 'form-control',
                        'min' => 1,
                        'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'No minimum'),
                        'data-cf-min-select' => true,
                    ]) ?>
                </div>
                <div class="col-md-4">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Maximum selections') ?>
                        <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
                    </label>
                    <?= Html::input('number', $namePrefix . '[max_select]', $field->getMaxSelect(), [
                        'class' => 'form-control',
                        'min' => 1,
                        'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'No limit'),
                        'data-cf-max-select' => true,
                    ]) ?>
                </div>
                <div class="col-md-4">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Exclusive option') ?>
                        <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
                    </label>
                    <?= Html::textInput($namePrefix . '[exclusive_option]', $field->getExclusiveOption(), [
                        'class' => 'form-control',
                        'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'e.g. None of these'),
                        'data-cf-exclusive-option' => true,
                    ]) ?>
                </div>
                <div class="col-12">
                    <div class="cf-switch">
                        <label>
                            <?= Html::checkbox($namePrefix . '[min_select_all]', $field->isMinSelectAll(), [
                                'value' => '1',
                                'uncheck' => '0',
                                'data-cf-min-select-all' => true,
                            ]) ?>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Require every option to be selected') ?>
                        </label>
                    </div>
                    <p class="cf-hint text-muted mb-0">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Use a minimum to require some ticks, or require every option. An exclusive choice such as “None of these” still counts as a complete answer on its own.') ?>
                    </p>
                </div>
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
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Each line is one choice. Use code | Label so respondents see the label and answers store the code. Add a line called Other to let people type their own answer.') ?>
                </div>
            </div>
            <div class="cf-field-note" data-cf-randomize-note>
                <i class="fa fa-random" aria-hidden="true"></i>
                <div>
                    <?= Yii::t('ThiscoveryFormsModule.base', 'When enabled, each participant sees choices in a different order. The same person keeps a stable order if they reopen the form.') ?>
                </div>
            </div>
            <div class="row g-3 mt-1<?= $isCarry ? '' : ' d-none' ?>" data-cf-carry-wrap>
                <div class="col-md-6">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Carry forward choices from') ?>
                        <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
                    </label>
                    <?= Html::dropDownList($namePrefix . '[carry_from]', FormField::toStudioKey((string)$carry['from']), $conditionOptions, [
                        'class' => 'form-control',
                        'data-cf-carry-from' => true,
                        'data-cf-selected-key' => FormField::toStudioKey((string)$carry['from']),
                    ]) ?>
                </div>
                <div class="col-md-6">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Which choices') ?></label>
                    <?= Html::dropDownList($namePrefix . '[carry_mode]', $carry['mode'] ?: FormField::CARRY_SELECTED, [
                        FormField::CARRY_SELECTED => Yii::t('ThiscoveryFormsModule.base', 'Selected'),
                        FormField::CARRY_UNSELECTED => Yii::t('ThiscoveryFormsModule.base', 'Not selected'),
                        FormField::CARRY_ALL => Yii::t('ThiscoveryFormsModule.base', 'All'),
                    ], [
                        'class' => 'form-control',
                    ]) ?>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1<?= $field->supportsJustification() ? '' : ' d-none' ?>" data-cf-justify-wrap>
            <div class="col-md-6">
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Comment after answer') ?></label>
                <?= Html::dropDownList($namePrefix . '[justification]', $field->getJustification(), FormField::getJustificationLabels(), [
                    'class' => 'form-control',
                    'data-cf-justification' => true,
                ]) ?>
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
                <div class="col-md-4">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Display') ?></label>
                    <?= Html::dropDownList($namePrefix . '[rating_display]', $rating['display'] ?? FormField::RATING_DISPLAY_PILLS, FormField::getRatingDisplayLabels(), [
                        'class' => 'form-control',
                    ]) ?>
                    <div class="cf-field-help"><?= Yii::t('ThiscoveryFormsModule.base', 'Thermometer is a vertical scale. Use 0–100, step 1 for a health VAS.') ?></div>
                </div>
                <div class="col-md-12">
                    <div class="cf-field-note">
                        <i class="fa fa-info-circle" aria-hidden="true"></i>
                        <div>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Official health-status wording and copyright must come from your own licence (for example EuroQol). This layout is a generic vertical scale.') ?>
                        </div>
                    </div>
                </div>
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
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Inserts a new page after the previous fields. Respondents use Next/Back. Logic or branch rules on this break run when they click Next on the page before it.') ?>
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
                                <?= Html::dropDownList($namePrefix . '[branches][' . $bi . '][fieldKey]', FormField::toStudioKey((string)($branch['fieldKey'] ?? '')), $conditionOptions, [
                                    'class' => 'form-control',
                                    'data-cf-branch-field' => true,
                                    'data-cf-selected-key' => FormField::toStudioKey((string)($branch['fieldKey'] ?? '')),
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
                <?= EditorField::widget([
                    'id' => 'cf-rich-field-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', (string)$key),
                    'name' => $namePrefix . '[rich_content]',
                    'value' => $field->getRichTextContent(),
                    'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Write instructions, context, or intro text…'),
                    'height' => 240,
                    'profile' => 'simple',
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

        <div class="cf-grid-panel<?= $isGrid ? '' : ' d-none' ?>" data-cf-grid-panel>
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Grid (matrix)') ?></label>
            <div class="cf-field-note">
                <i class="fa fa-info-circle" aria-hidden="true"></i>
                <div><?= Yii::t('ThiscoveryFormsModule.base', 'Rows are statements; columns are the scale. Single-select allows one column per row; multi-select allows several.') ?></div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Rows') ?></label>
                    <?= Html::textarea($namePrefix . '[grid_rows]', implode("\n", $gridCfg['rows']), [
                        'class' => 'form-control',
                        'rows' => 4,
                        'placeholder' => Yii::t('ThiscoveryFormsModule.base', "Statement A\nStatement B"),
                    ]) ?>
                </div>
                <div class="col-md-6">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Columns') ?></label>
                    <?= Html::textarea($namePrefix . '[grid_columns]', implode("\n", $gridCfg['columns']), [
                        'class' => 'form-control',
                        'rows' => 4,
                        'placeholder' => Yii::t('ThiscoveryFormsModule.base', "Strongly disagree\nDisagree\nNeutral\nAgree\nStrongly agree"),
                    ]) ?>
                </div>
            </div>
        </div>

        <div class="cf-items-panel<?= $isItems ? '' : ' d-none' ?>" data-cf-items-panel>
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Items') ?></label>
            <?= Html::textarea($namePrefix . '[items]', implode("\n", $itemsCfg['items']), [
                'class' => 'form-control',
                'rows' => 5,
                'placeholder' => Yii::t('ThiscoveryFormsModule.base', "Item A\nItem B\nItem C"),
            ]) ?>
            <div class="row g-3 mt-1<?= $isMaxDiff ? '' : ' d-none' ?>" data-cf-maxdiff-panel>
                <div class="col-md-6">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Items per set') ?></label>
                    <?= Html::input('number', $namePrefix . '[maxdiff_set_size]', (int)$itemsCfg['setSize'], [
                        'class' => 'form-control',
                        'min' => 2,
                    ]) ?>
                </div>
                <div class="col-md-6">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Number of sets') ?></label>
                    <?= Html::input('number', $namePrefix . '[maxdiff_set_count]', (int)$itemsCfg['setCount'], [
                        'class' => 'form-control',
                        'min' => 1,
                    ]) ?>
                </div>
            </div>
            <div class="cf-field-note mt-2<?= $isMaxDiff ? '' : ' d-none' ?>" data-cf-maxdiff-note>
                <i class="fa fa-info-circle" aria-hidden="true"></i>
                <div><?= Yii::t('ThiscoveryFormsModule.base', 'Sets are generated when you save. Respondents pick the best and worst item in each set. Analysis uses count scores (best minus worst).') ?></div>
            </div>
        </div>

        <div class="cf-drilldown-panel<?= $isDrilldown ? '' : ' d-none' ?>" data-cf-drilldown-panel>
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Drill-down tree') ?></label>
            <div class="cf-field-note">
                <i class="fa fa-info-circle" aria-hidden="true"></i>
                <div><?= Yii::t('ThiscoveryFormsModule.base', 'One item per line. Indent with two spaces or a tab for children.') ?></div>
            </div>
            <?= Html::textarea($namePrefix . '[drilldown_tree]', $field->getDrilldownTreeAsText(), [
                'class' => 'form-control',
                'rows' => 8,
                'placeholder' => "England\n  London\n  Manchester\nScotland\n  Edinburgh",
            ]) ?>
        </div>

        <div class="cf-image-panel<?= $isImageArea ? '' : ' d-none' ?>" data-cf-image-panel>
            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Image area') ?></label>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Image') ?></label>
                    <?= Html::hiddenInput($namePrefix . '[image_guid]', $imageCfg['imageGuid'], [
                        'data-cf-image-guid' => true,
                    ]) ?>
                    <?= Html::hiddenInput($namePrefix . '[image_src]', $imageCfg['src'], [
                        'data-cf-image-src' => true,
                    ]) ?>
                    <div class="cf-image-upload">
                        <label class="btn btn-primary btn-sm mb-0">
                            <i class="fa fa-cloud-upload" aria-hidden="true"></i>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Upload image') ?>
                            <input type="file" accept="image/*" class="d-none" data-cf-image-file>
                        </label>
                        <button type="button" class="btn btn-sm btn-light<?= $imageCfg['src'] === '' ? ' d-none' : '' ?>" data-cf-image-clear>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Remove') ?>
                        </button>
                        <span class="cf-image-upload__status" data-cf-image-status></span>
                    </div>
                    <div class="mt-2">
                        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Or image URL') ?>
                            <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
                        </label>
                        <?= Html::textInput($namePrefix . '[image_url]', $imageCfg['imageUrl'], [
                            'class' => 'form-control',
                            'data-cf-image-url' => true,
                            'placeholder' => 'https://…',
                        ]) ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Mode') ?></label>
                    <?= Html::dropDownList($namePrefix . '[image_mode]', $imageCfg['mode'], [
                        'select' => Yii::t('ThiscoveryFormsModule.base', 'Select regions'),
                        'evaluate' => Yii::t('ThiscoveryFormsModule.base', 'Evaluate (score / correct)'),
                    ], ['class' => 'form-control', 'data-cf-image-mode' => true]) ?>
                    <div class="cf-switch mt-2">
                        <label>
                            <?= Html::checkbox($namePrefix . '[image_multi]', !empty($imageCfg['multi']), [
                                'value' => '1',
                                'uncheck' => null,
                            ]) ?>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Allow multiple regions') ?>
                        </label>
                    </div>
                </div>
            </div>
            <?= Html::hiddenInput($namePrefix . '[image_regions]', json_encode($imageCfg['regions'], JSON_UNESCAPED_UNICODE), [
                'data-cf-image-regions' => true,
            ]) ?>
            <div class="cf-hotspot-builder mt-3" data-cf-hotspot-builder>
                <div class="cf-hotspot-stage">
                    <img src="<?= Html::encode($imageCfg['src']) ?>" alt="" data-cf-hotspot-img<?= $imageCfg['src'] === '' ? ' class="d-none"' : '' ?>>
                    <div class="cf-hotspot-overlay" data-cf-hotspot-overlay></div>
                </div>
                <p class="cf-field-help"><?= Yii::t('ThiscoveryFormsModule.base', 'Upload an image, then drag on it to add a rectangle. Edit each region’s label, correct flag, and score below.') ?></p>
                <div data-cf-hotspot-list></div>
            </div>
        </div>

        <div class="cf-advanced-panel<?= $hasCondition ? ' is-open' : '' ?>" data-cf-advanced data-cf-logic-panel>
            <div class="cf-advanced-title"><?= Yii::t('ThiscoveryFormsModule.base', 'Logic') ?></div>
            <div class="cf-field-help mb-2"><?= $isQuestionGroup
                ? Yii::t('ThiscoveryFormsModule.base', 'Show or hide every question in this group from an earlier answer. Type option text exactly as listed, without quotation marks.')
                : Yii::t('ThiscoveryFormsModule.base', 'Simple: show or hide this question. Advanced: skip a page or jump when the rules match. Combine rules with AND or OR. Type option text exactly as listed, without quotation marks.') ?></div>
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Action') ?></label>
                    <?= Html::dropDownList($namePrefix . '[logic_action]', $logic['action'], $actionLabels, [
                        'class' => 'form-control',
                        'data-cf-logic-action' => true,
                    ]) ?>
                </div>
                <div class="col-md-3">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Match') ?></label>
                    <?= Html::dropDownList($namePrefix . '[logic_combinator]', $logic['combinator'], [
                        'and' => Yii::t('ThiscoveryFormsModule.base', 'All rules (AND)'),
                        'or' => Yii::t('ThiscoveryFormsModule.base', 'Any rule (OR)'),
                    ], ['class' => 'form-control', 'data-cf-logic-combinator' => true]) ?>
                </div>
                <div class="col-md-4<?= in_array($logic['action'], [LogicEngine::ACTION_GOTO_PAGE], true) ? '' : ' d-none' ?>" data-cf-logic-goto-wrap>
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Go to page key') ?></label>
                    <?= Html::textInput($namePrefix . '[logic_goto]', $logic['gotoPageKey'], [
                        'class' => 'form-control',
                        'placeholder' => 'p2',
                        'list' => 'cf-page-keys',
                    ]) ?>
                </div>
            </div>
            <div class="mt-3" data-cf-logic-rules>
                <div data-cf-logic-rule-list>
                    <?php foreach ($logicRules as $ri => $rule): ?>
                        <div class="cf-branch-row row g-2 mb-2" data-cf-logic-rule-row>
                            <div class="col-md-4">
                                <?= Html::dropDownList($namePrefix . '[logic_rules][' . $ri . '][fieldKey]', FormField::toStudioKey((string)($rule['fieldKey'] ?? '')), $conditionOptions, [
                                    'class' => 'form-control',
                                    'data-cf-condition-field' => true,
                                    'data-cf-selected-key' => FormField::toStudioKey((string)($rule['fieldKey'] ?? '')),
                                ]) ?>
                            </div>
                            <div class="col-md-3">
                                <?= Html::dropDownList($namePrefix . '[logic_rules][' . $ri . '][operator]', $rule['operator'] ?? FormField::OP_EQUALS, $operatorLabels, [
                                    'class' => 'form-control',
                                    'data-cf-logic-operator' => true,
                                ]) ?>
                            </div>
                            <div class="col-md-3">
                                <?= Html::textInput($namePrefix . '[logic_rules][' . $ri . '][value]', $rule['value'] ?? '', [
                                    'class' => 'form-control',
                                    'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Value'),
                                    'data-cf-logic-value' => true,
                                ]) ?>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-sm btn-light" data-cf-remove-logic-rule title="<?= Yii::t('ThiscoveryFormsModule.base', 'Remove') ?>">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-sm btn-light" data-cf-add-logic-rule>
                    <i class="fa fa-plus"></i> <?= Yii::t('ThiscoveryFormsModule.base', 'Add rule') ?>
                </button>
            </div>
        </div>

        <div class="cf-actions-panel<?= $field->hasActions() ? ' is-open' : '' ?>" data-cf-field-actions>
            <div class="cf-advanced-title"><?= Yii::t('ThiscoveryFormsModule.base', 'Actions') ?></div>
            <div class="cf-field-help mb-2">
                <?= $isPageBreak
                    ? Yii::t('ThiscoveryFormsModule.base', 'These run when the respondent finishes the page before this break (Next), in the order listed. Custom function looks up a named formula from Settings → Custom functions.')
                    : Yii::t('ThiscoveryFormsModule.base', 'These run when this question is answered, in the order listed. Custom function looks up a named formula from Settings → Custom functions.') ?>
            </div>
            <?= $this->render('@thiscovery-forms/views/form/_action_rows', [
                'namePrefix' => $namePrefix . '[actions]',
                'actions' => $field->getActions() ?: [\humhub\modules\thiscoveryForms\services\FormActionService::emptyAction()],
                'emailTemplateOptions' => $emailTemplateOptions,
                'pageKeyOptions' => $pageKeyOptions,
            ]) ?>
        </div>
    </div>

    <div class="cf-group-shell<?= $isQuestionGroup ? '' : ' d-none' ?>" data-cf-group-shell>
        <div class="cf-group-toolbar">
            <button type="button" class="btn btn-sm btn-light" data-cf-add-in-group>
                <i class="fa fa-plus"></i> <?= Yii::t('ThiscoveryFormsModule.base', 'Add question to group') ?>
            </button>
            <button type="button" class="btn btn-sm btn-light" data-cf-toggle-advanced>
                <i class="fa fa-code-fork"></i> <?= Yii::t('ThiscoveryFormsModule.base', 'Logic') ?>
            </button>
            <span class="cf-hint text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'Use Logic on this group to show or hide every question inside it together.') ?></span>
        </div>
        <div class="cf-group-body" data-cf-group-body>
            <div class="cf-group-empty" data-cf-group-empty><?= Yii::t('ThiscoveryFormsModule.base', 'No questions in this group yet.') ?></div>
        </div>
    </div>
</div>
