<?php

use humhub\modules\thiscoveryForms\helpers\ButtonLabel;
use humhub\modules\thiscoveryForms\helpers\RichHtml;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\HtmlSanitizer;
use humhub\modules\thiscoveryForms\services\VariableSubstitutor;
use humhub\modules\file\models\File;
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

$allFields = $allFields ?? [];
$allValues = $allValues ?? [];
$userId = (int)(Yii::$app->user->id ?? 0);
$instanceKey = $instanceKey ?? '';
$inputName = 'SubmitForm[values][' . $field->id . ']' . ($instanceKey !== '' ? '[' . $instanceKey . ']' : '');
if ($instanceKey !== '' && is_array($value)) {
    $value = $value[$instanceKey] ?? '';
}
// A short hash keeps ids unique when two repeat codes differ only in characters stripped here (V3-55).
$inputId = 'cf-input-' . $field->id . ($instanceKey !== '' ? '-' . preg_replace('/[^a-z0-9_-]/i', '', str_replace('/', '__', $instanceKey)) . '-' . substr(md5($instanceKey), 0, 6) : '');
// Each loop repeat has its own "Please specify" text (V3-45).
$otherName = 'SubmitForm[other_text][' . $field->id . ']' . ($instanceKey !== '' ? '[' . $instanceKey . ']' : '');
$otherOptionalAttr = ($field->allowsOtherSpecify() && !$field->requiresOtherText()) ? ' data-cf-other-optional="1"' : '';
$labelId = 'cf-label-' . $inputId;
$choiceGroup = in_array($field->type, [
    FormField::TYPE_RADIO,
    FormField::TYPE_CHECKBOX,
    FormField::TYPE_RANKING,
    FormField::TYPE_GRID_SINGLE,
    FormField::TYPE_GRID_MULTI,
    FormField::TYPE_BEST_WORST,
    FormField::TYPE_MAXDIFF,
    // No single control to point a <label for> at: named as a group instead (V3-49).
    FormField::TYPE_RATING,
    FormField::TYPE_DRILLDOWN,
    FormField::TYPE_IMAGE_AREA,
    FormField::TYPE_MAP,
    // The upload is a button and a hidden value, nothing a <label for> can point at (A11Y-2).
    FormField::TYPE_FILE,
], true);
if (($value === '' || $value === null || $value === []) && $field->getDefaultValue() !== '') {
    $value = $field->getDefaultValue();
}
$panelMember = $panelMember ?? null;
$rewriteFileUrls = $rewriteFileUrls ?? static function (string $html): string { return $html; };
if (($value === '' || $value === null || $value === []) && $field->type === FormField::TYPE_PANEL_ATTR && $panelMember) {
    $value = \humhub\modules\thiscoveryForms\services\PanelFieldService::memberValue($panelMember, $field->getPanelAttrKey());
}
$fieldsById = [];
foreach ($allFields as $f) {
    $fieldsById[(int)$f->id] = $f;
}
$pipe = new VariableSubstitutor();
$user = Yii::$app->user->identity;
$labelText = $pipe->substitutePlain($field->label, $user, $formModel, $allValues, $allFields, [], $panelMember);
$helpText = $field->help_text
    ? $pipe->substitutePlain((string)$field->help_text, $user, $formModel, $allValues, $allFields, [], $panelMember)
    : '';
$choicePairs = FormField::isCarryForwardType($field->type)
    ? $field->getEffectiveChoicePairs($allValues, $fieldsById, $userId)
    : (FormField::isChoiceType($field->type) ? $field->getShuffledChoicePairs($userId, $existingAnswer ?? null) : []);
$choiceOptions = array_map(static fn($p) => $p['code'], $choicePairs);
$choiceLabelFor = static function (string $code) use ($choicePairs, $field): string {
    foreach ($choicePairs as $pair) {
        if ($pair['code'] === $code) {
            return $pair['label'];
        }
    }
    return $field->optionLabel($code);
};
$fillRtl = !empty($fillRtl);

$choiceIsPicked = static function ($value, string $opt): bool {
    if (is_array($value)) {
        foreach ($value as $item) {
            if ((string)$item === $opt) {
                return true;
            }
        }
        return false;
    }
    if ($value === null || $value === '' || $value === false) {
        return false;
    }
    return (string)$value === $opt;
};
$choiceInputOpts = static function (array $extra = []): array {
    return array_merge([
        'uncheck' => null,
        'autocomplete' => 'off',
    ], $extra);
};

if (!isset($textRuleAttrs)) {
    // Text length and pattern for the browser check (LOG-12); the server enforces the same.
    $textRuleAttrs = static function (FormField $field): array {
        $rules = $field->getValidation();
        $attrs = ['maxlength' => $field->maxTextLength()];
        if ($rules['min_length'] !== '') {
            $attrs['minlength'] = (int)$rules['min_length'];
        }
        if ($rules['pattern'] !== '') {
            $attrs['data-cf-pattern'] = $rules['pattern'];
            $attrs['data-cf-pattern-message'] = $rules['pattern_message'];
        }
        return $attrs;
    };
}

if ($field->type === FormField::TYPE_RICH_TEXT):
    $richHtml = RichHtml::toHtml($field->getRichTextContent());
    $richHtml = $pipe->substitute($richHtml, $user, $formModel, $allValues, $allFields);
    $richHtml = $rewriteFileUrls($richHtml);
    
?>
    <div class="cf-rich-block richtext-output" data-cf-pipe-html="<?= Html::encode($field->getRichTextContent()) ?>">
        <?= $richHtml ?>
    </div>
<?php elseif ($field->type === FormField::TYPE_CONSENT): ?>
    <?php
    $consent = new \humhub\modules\thiscoveryForms\services\ConsentService();
    $consentDoc = $consent->resolveDocument($formModel, $field, $existingAnswer ?? null);
    $consentCfg = $field->getConsentConfig();
    // Render exactly what the record will hash, in the participant's language (V3-27).
    $consentShown = $consentDoc ? $consent->presentation($formModel, $consentDoc) : null;
    $consentItems = $consentShown ? $consentShown['items'] : [];
    $consentBody = $consentShown ? $consentShown['body'] : '';
    $consentCfg['signature'] = $consent->effectiveSignature($formModel, (string)$consentCfg['signature']);
    $consentCfg['witness'] = $consent->effectiveWitness($formModel, (bool)$consentCfg['witness']);
    ?>
    <fieldset class="cf-consent" data-cf-consent="<?= (int)$field->id ?>"<?= $consentCfg['must_read'] ? ' data-cf-consent-must-read="1"' : '' ?>>
        <legend class="cf-question__label"><?= Html::encode($field->label) ?></legend>
        <?php if ($consentDoc): ?>
            <div class="cf-consent__sheet" tabindex="0" role="region" aria-label="<?= Html::encode((string)$consentDoc['title']) ?>">
                <h3><?= Html::encode((string)$consentDoc['title']) ?></h3>
                <div class="cf-consent__body richtext-output"><?= (new HtmlSanitizer())->sanitize($consentBody) ?></div>
                <span tabindex="0" data-cf-consent-end><?= Yii::t('ThiscoveryFormsModule.base', 'End of the information sheet') ?></span>
            </div>
            <?php foreach ($consentItems as $item): ?>
                <?= $this->render('_consent_item', ['item' => $item, 'nameBase' => 'consent[' . (int)$field->id . ']', 'idBase' => 'cf-consent-' . (int)$field->id]) ?>
            <?php endforeach; ?>
            <?php if ($consentCfg['signature'] === 'checkbox'): ?>
                <label><input type="checkbox" name="consent[<?= (int)$field->id ?>][attestation]" value="1"> <?= Yii::t('ThiscoveryFormsModule.base', 'I confirm this is my decision.') ?></label>
                <input type="hidden" name="consent[<?= (int)$field->id ?>][signature_method]" value="checkbox">
            <?php else: ?>
                <label for="cf-consent-name-<?= (int)$field->id ?>"><?= Yii::t('ThiscoveryFormsModule.base', 'Type your name') ?></label>
                <input id="cf-consent-name-<?= (int)$field->id ?>" class="form-control" name="consent[<?= (int)$field->id ?>][signature_name]" autocomplete="name">
                <input type="hidden" name="consent[<?= (int)$field->id ?>][signature_method]" value="<?= $consentCfg['signature'] === 'drawn' ? 'drawn' : 'typed' ?>">
                <?php if ($consentCfg['signature'] === 'drawn'): ?>
                    <div class="cf-consent__draw-wrap" data-cf-consent-draw>
                        <canvas class="cf-consent__draw" width="320" height="120" role="img" aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Draw a signature, or type your name instead')) ?>"></canvas>
                        <button type="button" class="btn btn-default btn-sm" data-cf-consent-clear><?= ButtonLabel::html('Clear signature') ?></button>
                    </div>
                    <input type="hidden" name="consent[<?= (int)$field->id ?>][signature_image]" data-cf-consent-image>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($consentCfg['witness']): ?>
                <label for="cf-consent-witness-<?= (int)$field->id ?>"><?= Yii::t('ThiscoveryFormsModule.base', 'Witness name') ?></label>
                <input id="cf-consent-witness-<?= (int)$field->id ?>" class="form-control" name="consent[<?= (int)$field->id ?>][witness_name]" required>
                <label for="cf-consent-witness-role-<?= (int)$field->id ?>"><?= Yii::t('ThiscoveryFormsModule.base', 'Witness role') ?></label>
                <input id="cf-consent-witness-role-<?= (int)$field->id ?>" class="form-control" name="consent[<?= (int)$field->id ?>][witness_role]" required>
            <?php endif; ?>
            <input type="hidden" name="consent[<?= (int)$field->id ?>][scrolled_to_end]" value="0" data-cf-consent-scrolled>
            <input type="hidden" name="consent[<?= (int)$field->id ?>][shown_hash]" value="<?= Html::encode($consentShown['hash']) ?>">
        <?php endif; ?>
    </fieldset>
<?php elseif ($field->type === FormField::TYPE_HTML):
    $htmlCfg = $field->getHtmlConfig();
    $html = (new VariableSubstitutor())->substitute(
        $htmlCfg['html'],
        Yii::$app->user->identity,
        $formModel,
        $allValues,
        $allFields,
        true,
        [],
        $panelMember
    );
    $html = (new HtmlSanitizer())->sanitize($html);
    $html = $rewriteFileUrls($html);
    ?>
    <?php if ($htmlCfg['collect']): ?>
        <?php if ($htmlCfg['required']): ?>
            <div class="cf-question__meta">
                <span class="cf-question__required"><?= Yii::t('ThiscoveryFormsModule.base', 'Required') ?></span>
            </div>
        <?php endif; ?>
        <div class="cf-question__label" data-cf-pipe="<?= Html::encode($field->label) ?>">
            <?= $labelText ?>
            <?php if ($htmlCfg['required']): ?><span class="text-danger">*</span><?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="cf-html-block" data-cf-html-block="<?= (int)$field->id ?>" data-cf-html-var="<?= Html::encode($htmlCfg['variable']) ?>" data-cf-pipe-html="<?= Html::encode($htmlCfg['html']) ?>">
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
    <?php if ($field->required || ($field->type === FormField::TYPE_HTML && $field->getHtmlConfig()['required'])): ?>
        <div class="cf-question__meta">
            <span class="cf-question__required"><?= Yii::t('ThiscoveryFormsModule.base', 'Required') ?></span>
        </div>
    <?php endif; ?>

    <?php if ($choiceGroup): ?>
    <div class="cf-question__label" id="<?= Html::encode($labelId) ?>" data-cf-pipe="<?= Html::encode($field->label) ?>">
        <?= $labelText ?>
        <?php if ($field->required): ?><span class="text-danger">*</span><?php endif; ?>
    </div>
    <?php else: ?>
    <label class="cf-question__label" for="<?= Html::encode($inputId) ?>" data-cf-pipe="<?= Html::encode($field->label) ?>">
        <?= $labelText ?>
        <?php if ($field->required): ?><span class="text-danger">*</span><?php endif; ?>
    </label>
    <?php endif; ?>

    <?php if ($field->help_text && !$field->isThermometerRating()): ?>
        <p class="cf-question__help" data-cf-pipe="<?= Html::encode($field->help_text) ?>"><?= $helpText ?></p>
    <?php endif; ?>
    <?php if (!empty($frozen)): ?>
        <p class="cf-frozen-note"><?= Yii::t('ThiscoveryFormsModule.base', 'This item reached consensus and cannot be changed.') ?></p>
    <?php endif; ?>

    <div class="cf-question__control<?= !empty($frozen) ? ' is-frozen' : '' ?>">
        <?php if ($field->type === FormField::TYPE_RESPONDENT_META): ?>
            <?= Html::hiddenInput($inputName, is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value, [
                'id' => $inputId,
                'aria-required' => $field->required ? 'true' : null,
                'data-cf-respondent-meta' => '1',
                'data-cf-meta-key' => $field->getRespondentMetaKey(),
            ]) ?>
        <?php elseif ($field->type === FormField::TYPE_PANEL_ATTR): ?>
            <?php
            $panelKey = $field->getPanelAttrKey();
            $panel = $formModel->getAttachedPanel() ?: ($panelMember ? $panelMember->panel : null);
            $panelType = $panel ? \humhub\modules\thiscoveryForms\services\PanelFieldService::fieldType($panel, $panelKey) : 'text';
            $panelOpts = $panel ? \humhub\modules\thiscoveryForms\services\PanelFieldService::fieldOptions($panel, $panelKey) : [];
            $panelVal = is_array($value) ? '' : (string)$value;
            ?>
            <?php if ($field->isHiddenFromRespondent()): ?>
                <?= Html::hiddenInput($inputName, $panelVal, [
                    'id' => $inputId,
                    'aria-required' => $field->required ? 'true' : null,
                    'data-cf-panel-attr' => '1',
                    'data-cf-panel-key' => $panelKey,
                ]) ?>
            <?php elseif ($panelType === 'dropdown' && $panelOpts): ?>
                <?= Html::dropDownList($inputName, $panelVal, array_combine($panelOpts, $panelOpts), [
                    'class' => 'form-control cf-input',
                    'id' => $inputId,
                    'aria-required' => $field->required ? 'true' : null,
                    'prompt' => Yii::t('ThiscoveryFormsModule.base', 'Select…'),
                ]) ?>
            <?php elseif ($panelType === 'textarea'): ?>
                <?= Html::textarea($inputName, $panelVal, ['class' => 'form-control cf-input', 'rows' => 3, 'id' => $inputId, 'aria-required' => $field->required ? 'true' : null]) ?>
            <?php else: ?>
                <?php $asEmail = $panelType === 'email' || $panelKey === 'email'; ?>
                <?= Html::input($asEmail ? 'email' : ($panelType === 'number' || $panelType === 'date' ? $panelType : 'text'), $inputName, $panelVal, [
                    'class' => 'form-control cf-input',
                    'id' => $inputId,
                    'aria-required' => $field->required ? 'true' : null,
                    'autocomplete' => $asEmail ? 'email' : null,
                    'inputmode' => $asEmail ? 'email' : null,
                    'data-cf-email' => $asEmail ? '1' : null,
                    'spellcheck' => $asEmail ? 'false' : null,
                ]) ?>
                <?php if ($asEmail): ?>
                    <p class="cf-question__error d-none" data-cf-email-error><?= Yii::t('ThiscoveryFormsModule.base', 'Enter a valid email address, for example name@example.com.') ?></p>
                <?php endif; ?>
            <?php endif; ?>
        <?php elseif ($field->type === FormField::TYPE_TEXTAREA): ?>
            <?= Html::textarea($inputName, is_array($value) ? '' : $value, [
                'class' => 'form-control cf-input',
                'rows' => 4,
                'id' => $inputId,
                'aria-required' => $field->required ? 'true' : null,
                'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Your answer'),
            ] + $textRuleAttrs($field)) ?>
        <?php elseif ($field->type === FormField::TYPE_NUMBER): ?>
            <?php
            $numberAttrs = [
                'class' => 'form-control cf-input',
                'id' => $inputId,
                'aria-required' => $field->required ? 'true' : null,
                'step' => 'any',
            ];
            $numberMin = $field->getNumberMin();
            $numberMax = $field->getNumberMax();
            if ($numberMin !== null) {
                $numberAttrs['min'] = $numberMin;
            }
            if ($numberMax !== null) {
                $numberAttrs['max'] = $numberMax;
            }
            ?>
            <?= Html::input('number', $inputName, is_array($value) ? '' : $value, $numberAttrs) ?>
        <?php elseif ($field->type === FormField::TYPE_CALCULATED): ?>
            <?php
            $formulaCfg = $field->getFormulaConfig();
            $calcValues = $allValues;
            \humhub\modules\thiscoveryForms\services\formula\FormulaRuntime::fill($calcValues, $allFields);
            $calcShown = $calcValues[(int)$field->id] ?? '';
            $calcTree = '';
            if ($formulaCfg['formula'] !== '') {
                try {
                    $calcTree = json_encode(
                        (new \humhub\modules\thiscoveryForms\services\formula\Parser())->parse($formulaCfg['formula']),
                        JSON_UNESCAPED_UNICODE
                    );
                } catch (\Throwable $e) {
                    $calcTree = '';
                }
            }
            ?>
            <?php // Rendered even when the display is hidden, so the browser can use the value in rules (V3-14). ?>
            <output class="form-control-plaintext" id="<?= Html::encode($inputId) ?>"
                data-cf-calc-tree="<?= Html::encode((string)$calcTree) ?>"
                data-cf-calc-result="<?= Html::encode((string)$formulaCfg['result']) ?>"
                data-cf-calc-places="<?= (int)$formulaCfg['places'] ?>"<?= $formulaCfg['display'] === 'hidden' ? ' hidden' : '' ?>><?= Html::encode((string)$calcShown) ?></output>
        <?php elseif ($field->type === FormField::TYPE_EMAIL): ?>
            <?= Html::input('email', $inputName, is_array($value) ? '' : $value, [
                'class' => 'form-control cf-input',
                'id' => $inputId,
                'aria-required' => $field->required ? 'true' : null,
                'placeholder' => 'name@example.com',
                'autocomplete' => 'email',
                'inputmode' => 'email',
                'data-cf-email' => '1',
                'spellcheck' => 'false',
            ]) ?>
            <p class="cf-question__error d-none" data-cf-email-error><?= Yii::t('ThiscoveryFormsModule.base', 'Enter a valid email address, for example name@example.com.') ?></p>
        <?php elseif ($field->type === FormField::TYPE_DATE): ?>
            <?= Html::input('date', $inputName, is_array($value) ? '' : $value, array_filter([
                'class' => 'form-control cf-input',
                'id' => $inputId,
                'aria-required' => $field->required ? 'true' : null,
                // Same limits the server enforces (LOG-12); "today" is resolved in the form's zone.
                'min' => $field->dateBound('date_min') ?: null,
                'max' => $field->dateBound('date_max') ?: null,
            ], static fn ($v) => $v !== null)) ?>
        <?php elseif ($field->type === FormField::TYPE_DROPDOWN): ?>
            <?php
            $carry = $field->getCarryForward();
            $otherLabel = $field->findOtherOption($choiceOptions);
            $otherState = $otherLabel ? FormField::otherSpecifyState($otherLabel, $value) : ['selected' => false, 'text' => ''];
            $dropValue = $otherState['selected'] ? $otherLabel : (is_array($value) ? null : $value);
            if ($dropValue === '' || $dropValue === false) {
                $dropValue = null;
            }
            $dropAttrs = [
                'class' => 'form-control cf-input',
                'id' => $inputId,
                'aria-required' => $field->required ? 'true' : null,
                'prompt' => Yii::t('ThiscoveryFormsModule.base', 'Please select'),
                'autocomplete' => 'off',
            ];
            if ($otherLabel) {
                $dropAttrs['data-cf-other-select'] = $otherLabel;
            }
            if ($carry['from'] !== '') {
                $src = $fieldsById[(int)$carry['from']] ?? null;
                $dropAttrs['data-cf-carry-from'] = $carry['from'];
                $dropAttrs['data-cf-carry-mode'] = $carry['mode'];
                $dropAttrs['data-cf-carry-options'] = json_encode($src ? $src->getChoicePairs() : [], JSON_UNESCAPED_UNICODE);
            }
            ?>
            <?= Html::dropDownList($inputName, $dropValue, (static function (array $pairs): array {
                $list = [];
                foreach ($pairs as $pair) {
                    $list[$pair['code']] = $pair['label'];
                }
                return $list;
            })($choicePairs), $dropAttrs) ?>
            <?php if ($otherLabel): ?>
                <div class="cf-other-specify<?= $otherState['selected'] ? '' : ' d-none' ?>"
                     data-cf-other-wrap
                     data-cf-other-option="<?= Html::encode($otherLabel) ?>"<?= $otherOptionalAttr ?>>
                    <label class="cf-other-specify__label" for="<?= Html::encode($inputId) ?>-other">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Please specify') ?>
                    </label>
                    <?= Html::textInput($otherName, $otherState['text'], [
                        'id' => $inputId . '-other',
                        'class' => 'form-control cf-input',
                        'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Type your answer'),
                        'data-cf-other-text' => true,
                        'disabled' => !$otherState['selected'],
                    ]) ?>
                </div>
            <?php endif; ?>
        <?php elseif ($field->type === FormField::TYPE_RADIO): ?>
            <?php
            $carry = $field->getCarryForward();
            $otherLabel = $field->findOtherOption($choiceOptions);
            $otherState = $otherLabel ? FormField::otherSpecifyState($otherLabel, $value) : ['selected' => false, 'text' => ''];
            $listAttrs = [
                'class' => 'cf-choice-list',
                'role' => 'radiogroup',
                'aria-labelledby' => $labelId,
            ];
            if ($field->required) {
                $listAttrs['aria-required'] = 'true';
            }
            if ($carry['from'] !== '') {
                $src = $fieldsById[(int)$carry['from']] ?? null;
                $listAttrs['data-cf-carry-from'] = $carry['from'];
                $listAttrs['data-cf-carry-mode'] = $carry['mode'];
                $listAttrs['data-cf-carry-options'] = json_encode($src ? $src->getChoicePairs() : [], JSON_UNESCAPED_UNICODE);
            }
            ?>
            <div <?= \yii\helpers\Html::renderTagAttributes($listAttrs) ?>>
                <?php foreach ($choiceOptions as $opt): ?>
                    <?php $isOther = $otherLabel !== null && (string)$opt === $otherLabel; ?>
                    <label class="cf-choice">
                        <?= Html::radio($inputName, $isOther ? $otherState['selected'] : $choiceIsPicked($value, (string)$opt), $choiceInputOpts([
                            'value' => $opt,
                            'required' => (bool)$field->required,
                        ])) ?>
                        <span><?= Html::encode($choiceLabelFor((string)$opt)) ?></span>
                    </label>
                    <?php if ($isOther): ?>
                        <div class="cf-other-specify<?= $otherState['selected'] ? '' : ' d-none' ?>"
                             data-cf-other-wrap
                             data-cf-other-option="<?= Html::encode($otherLabel) ?>"<?= $otherOptionalAttr ?>>
                            <label class="cf-other-specify__label" for="<?= Html::encode($inputId) ?>-other">
                                <?= Yii::t('ThiscoveryFormsModule.base', 'Please specify') ?>
                            </label>
                            <?= Html::textInput($otherName, $otherState['text'], [
                                'id' => $inputId . '-other',
                                'class' => 'form-control cf-input',
                                'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Type your answer'),
                                'data-cf-other-text' => true,
                                'disabled' => !$otherState['selected'],
                            ]) ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php elseif ($field->type === FormField::TYPE_CHECKBOX): ?>
            <?php
            $selected = is_array($value) ? $value : [];
            $maxSelect = $field->getMaxSelect();
            $minSelect = $field->resolveMinSelect();
            $exclusiveOptions = $field->getExclusiveOptions();
            $otherLabel = $field->findOtherOption($choiceOptions);
            $otherState = $otherLabel ? FormField::otherSpecifyState($otherLabel, $selected) : ['selected' => false, 'text' => ''];
            $listAttrs = [
                'class' => 'cf-choice-list',
                'role' => 'group',
                'aria-labelledby' => $labelId,
            ];
            if ($field->required) {
                $listAttrs['aria-required'] = 'true';
            }
            if ($maxSelect) {
                $listAttrs['data-cf-max-select'] = (int)$maxSelect;
            }
            if ($minSelect) {
                $listAttrs['data-cf-min-select'] = (int)$minSelect;
            }
            if ($field->isMinSelectAll()) {
                $listAttrs['data-cf-min-select-all'] = '1';
            }
            if ($exclusiveOptions) {
                $listAttrs['data-cf-exclusive'] = implode('|', $exclusiveOptions);
            }
            $carry = $field->getCarryForward();
            if ($carry['from'] !== '') {
                $src = $fieldsById[(int)$carry['from']] ?? null;
                $listAttrs['data-cf-carry-from'] = $carry['from'];
                $listAttrs['data-cf-carry-mode'] = $carry['mode'];
                $listAttrs['data-cf-carry-options'] = json_encode($src ? $src->getChoicePairs() : [], JSON_UNESCAPED_UNICODE);
            }
            ?>
            <?php if ($instanceKey !== ''): ?>
                <?php // Posts the repeat even with every box unticked, so unticking clears it (V3-45). ?>
                <?= Html::hiddenInput($inputName . '[]', '') ?>
            <?php endif; ?>
            <div <?= \yii\helpers\Html::renderTagAttributes($listAttrs) ?>>
                <?php foreach ($choiceOptions as $opt): ?>
                    <?php $isOther = $otherLabel !== null && (string)$opt === $otherLabel; ?>
                    <label class="cf-choice">
                        <?= Html::checkbox($inputName . '[]', $isOther ? $otherState['selected'] : $choiceIsPicked($selected, (string)$opt), $choiceInputOpts(['value' => $opt])) ?>
                        <span><?= Html::encode($choiceLabelFor((string)$opt)) ?></span>
                    </label>
                    <?php if ($isOther): ?>
                        <div class="cf-other-specify<?= $otherState['selected'] ? '' : ' d-none' ?>"
                             data-cf-other-wrap
                             data-cf-other-option="<?= Html::encode($otherLabel) ?>"<?= $otherOptionalAttr ?>>
                            <label class="cf-other-specify__label" for="<?= Html::encode($inputId) ?>-other">
                                <?= Yii::t('ThiscoveryFormsModule.base', 'Please specify') ?>
                            </label>
                            <?= Html::textInput($otherName, $otherState['text'], [
                                'id' => $inputId . '-other',
                                'class' => 'form-control cf-input',
                                'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Type your answer'),
                                'data-cf-other-text' => true,
                                'disabled' => !$otherState['selected'],
                            ]) ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php if ($minSelect): ?>
                <p class="cf-question__help">
                    <?= $field->isMinSelectAll()
                        ? Yii::t('ThiscoveryFormsModule.base', 'Select every option.')
                        : Yii::t('ThiscoveryFormsModule.base', 'Select at least {min} options.', ['min' => $minSelect]) ?>
                </p>
            <?php endif; ?>
        <?php elseif ($field->type === FormField::TYPE_RATING): ?>
            <?php
            $scale = $field->getRatingScale();
            $min = (int)$scale['min'];
            $max = (int)$scale['max'];
            $step = max(1, (int)$scale['step']);
            $lowLabel = $scale['lowLabel'] ?? '';
            $highLabel = $scale['highLabel'] ?? '';
            $selected = is_array($value) ? null : $value;
            $hasValue = $selected !== null && $selected !== '';
            $ratingCount = 0;
            for ($ratingValue = $min; $ratingValue <= $max; $ratingValue += $step) {
                $ratingCount++;
            }
            $ratingCount = max(1, $ratingCount);
            ?>
            <?php if (($scale['display'] ?? FormField::RATING_DISPLAY_PILLS) === FormField::RATING_DISPLAY_THERMOMETER): ?>
                <?php
                $range = max(1, $max - $min);
                $tickEvery = $step;
                $pad = 16;
                $scaleH = 1000;
                $vbW = 200;
                $vbH = $scaleH + (2 * $pad);
                $half = ['major' => 64, 'mid' => 40, 'minor' => 18];
                $cx = $fillRtl ? ($vbW - 70) : 70;
                $labelX = $fillRtl
                    ? ($cx - $half['major'] - 12)
                    : ($cx + $half['major'] + 12);
                $tickSvg = [];
                for ($tick = $min; $tick <= $max; $tick += $tickEvery) {
                    $fromMin = $tick - $min;
                    if ($fromMin % 10 === 0) {
                        $kind = 'major';
                    } elseif ($fromMin % 5 === 0) {
                        $kind = 'mid';
                    } else {
                        $kind = 'minor';
                    }
                    $t = ($tick - $min) / $range;
                    $y = $pad + ((1 - $t) * $scaleH);
                    $yAttr = htmlspecialchars((string)round($y, 2), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $hw = $half[$kind];
                    $tickSvg[] = '<line class="cf-vas__tick cf-vas__tick--' . $kind . '" x1="'
                        . ($cx - $hw) . '" y1="' . $yAttr . '" x2="' . ($cx + $hw) . '" y2="' . $yAttr . '" />';
                    if ($kind !== 'minor') {
                        $tickSvg[] = '<text class="cf-vas__n" direction="ltr" text-anchor="'
                            . ($fillRtl ? 'end' : 'start') . '" x="' . $labelX . '" y="' . $yAttr . '">'
                            . (int)$tick . '</text>';
                    }
                }
                $pct = $hasValue
                    ? max(0, min(100, (($max - (int)$selected) / $range) * 100))
                    : null;
                $hitTop = ($pad / $vbH) * 100;
                $hitHeight = ($scaleH / $vbH) * 100;
                $hitLeft = (($cx - $half['major']) / $vbW) * 100;
                $hitWidth = (($half['major'] * 2) / $vbW) * 100;
                ?>
                <div class="cf-vas"
                     data-cf-vas
                     data-min="<?= (int)$min ?>"
                     data-max="<?= (int)$max ?>"
                     data-step="<?= (int)$step ?>">
                    <div class="cf-vas__copy">
                        <?php if ($helpText !== ''): ?>
                            <p class="cf-vas__help" data-cf-pipe="<?= Html::encode($field->help_text) ?>"><?= $helpText ?></p>
                        <?php endif; ?>
                        <div class="cf-vas__score">
                            <label class="cf-vas__score-label" for="<?= Html::encode($inputId) ?>">
                                <?= Yii::t('ThiscoveryFormsModule.base', 'Score') ?>
                            </label>
                            <?= Html::input('number', $inputName, $hasValue ? (int)$selected : '', [
                                'class' => 'form-control cf-vas__input',
                                'id' => $inputId,
                                'aria-required' => $field->required ? 'true' : null,
                                'min' => $min,
                                'max' => $max,
                                'step' => $step,
                                'required' => (bool)$field->required,
                                'inputmode' => 'numeric',
                                'autocomplete' => 'off',
                                'aria-valuemin' => $min,
                                'aria-valuemax' => $max,
                                'readonly' => !empty($frozen),
                                'data-cf-vas-input' => true,
                            ]) ?>
                        </div>
                    </div>
                    <div class="cf-vas__scale">
                        <?php if ($highLabel !== ''): ?>
                            <div class="cf-vas__end cf-vas__end--high"><?= Html::encode($highLabel) ?></div>
                        <?php endif; ?>
                        <div class="cf-vas__ruler">
                            <svg class="cf-vas__svg" viewBox="0 0 <?= (int)$vbW ?> <?= (int)$vbH ?>"
                                 aria-hidden="true" focusable="false">
                                <line class="cf-vas__spine" x1="<?= (int)$cx ?>" y1="<?= (int)$pad ?>"
                                      x2="<?= (int)$cx ?>" y2="<?= (int)($pad + $scaleH) ?>" />
                                <?= implode("\n", $tickSvg) ?>
                            </svg>
                            <div class="cf-vas__hit"
                                 role="slider"
                                 tabindex="0"
                                 aria-labelledby="<?= Html::encode($labelId) ?>"<?= $field->required ? ' aria-required="true"' : '' ?>
                                 aria-orientation="vertical"
                                 aria-valuemin="<?= (int)$min ?>"
                                 aria-valuemax="<?= (int)$max ?>"
                                 <?= $hasValue ? 'aria-valuenow="' . (int)$selected . '"' : '' ?>
                                 data-cf-vas-track
                                 style="top: <?= Html::encode(sprintf('%.3f', $hitTop)) ?>%; height: <?= Html::encode(sprintf('%.3f', $hitHeight)) ?>%; left: <?= Html::encode(sprintf('%.3f', $hitLeft)) ?>%; width: <?= Html::encode(sprintf('%.3f', $hitWidth)) ?>%;">
                                <span class="cf-vas__marker" data-cf-vas-marker<?= $pct === null ? ' hidden' : '' ?>
                                      style="<?= $pct === null ? '' : 'top:' . $pct . '%' ?>"></span>
                            </div>
                        </div>
                        <?php if ($lowLabel !== ''): ?>
                            <div class="cf-vas__end cf-vas__end--low"><?= Html::encode($lowLabel) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
            <div class="cf-rating-scale" data-cf-rating style="--cf-rating-count: <?= (int)$ratingCount ?>">
                <div class="cf-rating-options" role="radiogroup" aria-labelledby="<?= Html::encode($labelId) ?>"<?= $field->required ? ' aria-required="true"' : '' ?>>
                    <?php for ($ratingValue = $min; $ratingValue <= $max; $ratingValue += $step): ?>
                        <?php $ratingPicked = $hasValue && (string)$selected === (string)$ratingValue; ?>
                        <label class="cf-rating-option<?= $ratingPicked ? ' is-selected' : '' ?>">
                            <?= Html::radio($inputName, $ratingPicked, $choiceInputOpts([
                                'value' => $ratingValue,
                                'required' => (bool)$field->required && $ratingValue === $min,
                            ])) ?>
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
            <?php endif; ?>
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
            <div class="cf-ranking" data-cf-ranking role="group" aria-labelledby="<?= Html::encode($labelId) ?>"<?= $field->required ? ' aria-required="true"' : '' ?>>
                <p class="cf-ranking-hint">
                    <i class="fa fa-arrows-v" aria-hidden="true"></i>
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Drag the handle or use the arrows to rank (1 = highest preference).') ?>
                </p>
                <ol class="cf-ranking-list" data-cf-ranking-list>
                    <?php foreach ($ranked as $rIndex => $option): ?>
                        <?php $rankLabel = $choiceLabelFor((string)$option); ?>
                        <li class="cf-ranking-item" data-value="<?= Html::encode($option) ?>" data-cf-rank-label="<?= Html::encode($rankLabel) ?>">
                            <?php // Names the item and its place, so a screen reader knows what moves (A11Y-7). ?>
                            <span class="cf-ranking-handle" data-cf-rank-handle role="button" tabindex="0" title="<?= Yii::t('ThiscoveryFormsModule.base', 'Drag to reorder') ?>" aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', '{item}, position {n} of {count}. Use the up and down arrow keys to move it.', ['item' => $rankLabel, 'n' => $rIndex + 1, 'count' => count($ranked)])) ?>">
                                <i class="fa fa-bars" aria-hidden="true"></i>
                            </span>
                            <span class="cf-ranking-order" aria-hidden="true"><?= (int)$rIndex + 1 ?></span>
                            <span class="cf-ranking-label"><?= Html::encode($choiceLabelFor((string)$option)) ?></span>
                            <span class="cf-ranking-controls">
                                <button type="button" class="cf-ranking-btn" data-cf-rank-up title="<?= Yii::t('ThiscoveryFormsModule.base', 'Move up') ?>" aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Move {item} up', ['item' => $rankLabel])) ?>">
                                    <i class="fa fa-chevron-up" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="cf-ranking-btn" data-cf-rank-down title="<?= Yii::t('ThiscoveryFormsModule.base', 'Move down') ?>" aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Move {item} down', ['item' => $rankLabel])) ?>">
                                    <i class="fa fa-chevron-down" aria-hidden="true"></i>
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
            $existingFile = $guid !== '' ? File::findOne(['guid' => $guid]) : null;
            ?>
            <div class="cf-file-box" data-cf-file-field role="group" aria-labelledby="<?= Html::encode($labelId) ?>"<?= $field->required ? ' aria-required="true"' : '' ?>>
                <?= Html::hiddenInput($inputName, $guid, [
                    'id' => $uploadId . '_guid',
                    'data-cf-file-guid' => true,
                ]) ?>
                <?= UploadButton::widget([
                    'id' => $uploadId,
                    'label' => ButtonLabel::html('Upload file'),
                    'tooltip' => false,
                    'cssButtonClass' => 'btn-primary btn-sm',
                    'single' => true,
                    'multiple' => false,
                    'hideInStream' => true,
                    'url' => Url::toFillUpload($formModel, (int)$field->id),
                    'submitName' => $inputName,
                    'progress' => '#' . $uploadId . '_progress',
                    'preview' => '#' . $uploadId . '_preview',
                ]) ?>
                <?php
                $fileRules = $field->getFileRules();
                $fileTypes = $fileRules['types'] ?: array_keys(\humhub\modules\thiscoveryForms\services\UploadQuota::TYPES);
                $fileMb = $fileRules['maxMb'] ?? (int)floor(\humhub\modules\thiscoveryForms\services\UploadQuota::MAX_FILE_BYTES / 1048576);
                ?>
                <p class="cf-field-help mb-1"><?= Yii::t('ThiscoveryFormsModule.base', 'Allowed: {types}. Up to {mb} MB.', [
                    'types' => implode(', ', $fileTypes),
                    'mb' => $fileMb,
                ]) ?></p>
                <?= UploadProgress::widget(['id' => $uploadId . '_progress']) ?>
                <?= FilePreview::widget([
                    'id' => $uploadId . '_preview',
                    'edit' => true,
                    'items' => $existingFile ? [$existingFile] : [],
                    'options' => ['style' => 'margin-top:8px'],
                ]) ?>
            </div>
        <?php elseif ($field->type === FormField::TYPE_GRID_SINGLE || $field->type === FormField::TYPE_GRID_MULTI): ?>
            <?php
            $grid = $field->getGridConfig();
            $multi = $field->type === FormField::TYPE_GRID_MULTI;
            $gridValue = is_array($value) ? $value : [];
            $mobileStack = ($grid['mobile_layout'] ?? 'scroll') === 'stack';
            $gridCell = static function (array $gridValue, array $row) {
                foreach ([$row['value'] ?? '', $row['code'] ?? '', $row['label'] ?? ''] as $key) {
                    if ($key !== '' && array_key_exists($key, $gridValue)) {
                        return $gridValue[$key];
                    }
                }
                return null;
            };
            ?>
            <div role="group" aria-labelledby="<?= Html::encode($labelId) ?>"<?= $field->required ? ' aria-required="true"' : '' ?> class="cf-grid-wrap<?= $mobileStack ? ' cf-grid-wrap--stack-mobile' : '' ?>"
                 data-cf-grid="<?= $multi ? 'multi' : 'single' ?>"
                 data-cf-mobile-layout="<?= $mobileStack ? 'stack' : 'scroll' ?>">
                <p class="cf-grid-hint" data-cf-grid-hint hidden>
                    <span class="cf-grid-hint__icon" aria-hidden="true"></span>
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Scroll sideways to see all options') ?>
                </p>
                <div class="cf-grid-fade">
                    <div class="cf-grid-scroll" data-cf-grid-scroll tabindex="0" role="region"
                         aria-label="<?= Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Answer grid. Scroll sideways to see all options.')) ?>">
                        <table class="cf-grid">
                            <thead>
                            <tr>
                                <th scope="col"></th>
                                <?php foreach ($grid['columns'] as $col): ?>
                                    <th scope="col"><?= Html::encode($col['label']) ?></th>
                                <?php endforeach; ?>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($grid['rows'] as $row): ?>
                                <?php
                                $cell = $gridCell($gridValue, $row);
                                $picked = is_array($cell) ? $cell : (($cell !== null && $cell !== '') ? [(string)$cell] : []);
                                $rowKey = (string)$row['value'];
                                ?>
                                <tr>
                                    <th scope="row"><?= Html::encode($row['label']) ?></th>
                                    <?php foreach ($grid['columns'] as $col): ?>
                                        <td>
                                            <?php
                                            $colVal = (string)$col['value'];
                                            $matchVals = array_filter([(string)$col['value'], (string)$col['code'], (string)$col['label']]);
                                            $isPicked = false;
                                            foreach ($matchVals as $mv) {
                                                if ($choiceIsPicked($picked, (string)$mv)) {
                                                    $isPicked = true;
                                                    break;
                                                }
                                            }
                                            $cellLabel = trim((string)$row['label'] . ', ' . (string)$col['label']);
                                            ?>
                                            <?php if ($multi): ?>
                                                <?= Html::checkbox($inputName . '[' . $rowKey . '][]', $isPicked, $choiceInputOpts(['value' => $colVal, 'aria-label' => $cellLabel])) ?>
                                            <?php else: ?>
                                                <?= Html::radio($inputName . '[' . $rowKey . ']', $isPicked, $choiceInputOpts(['value' => $colVal, 'aria-label' => $cellLabel])) ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <span class="cf-grid-more" aria-hidden="true"></span>
                </div>
                <?php if ($mobileStack): ?>
                    <div class="cf-grid-stack" data-cf-grid-stack>
                        <?php foreach ($grid['rows'] as $row): ?>
                            <?php
                            $cell = $gridCell($gridValue, $row);
                            $picked = is_array($cell) ? $cell : (($cell !== null && $cell !== '') ? [(string)$cell] : []);
                            $rowKey = (string)$row['value'];
                            ?>
                            <fieldset class="cf-grid-stack__row">
                                <legend class="cf-grid-stack__legend"><?= Html::encode($row['label']) ?></legend>
                                <div class="cf-grid-stack__options">
                                    <?php foreach ($grid['columns'] as $col): ?>
                                        <?php
                                        $colVal = (string)$col['value'];
                                        $matchVals = array_filter([(string)$col['value'], (string)$col['code'], (string)$col['label']]);
                                        $isPicked = false;
                                        foreach ($matchVals as $mv) {
                                            if ($choiceIsPicked($picked, (string)$mv)) {
                                                $isPicked = true;
                                                break;
                                            }
                                        }
                                        ?>
                                        <label class="cf-grid-stack__option">
                                            <?php if ($multi): ?>
                                                <?= Html::checkbox($inputName . '[' . $rowKey . '][]', $isPicked, $choiceInputOpts(['value' => $colVal, 'class' => 'cf-grid-stack__input'])) ?>
                                            <?php else: ?>
                                                <?= Html::radio($inputName . '[' . $rowKey . ']', $isPicked, $choiceInputOpts(['value' => $colVal, 'class' => 'cf-grid-stack__input'])) ?>
                                            <?php endif; ?>
                                            <span><?= Html::encode($col['label']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($field->type === FormField::TYPE_BEST_WORST): ?>
            <?php
            $items = $field->getItemsConfig()['items'];
            $bw = is_array($value) ? $value : [];
            $best = (string)($bw['best'] ?? '');
            $worst = (string)($bw['worst'] ?? '');
            ?>
            <div class="cf-best-worst" data-cf-best-worst role="group" aria-labelledby="<?= Html::encode($labelId) ?>"<?= $field->required ? ' aria-required="true"' : '' ?>>
                <table class="cf-grid">
                    <thead>
                    <tr>
                        <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Best') ?></th>
                        <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Item') ?></th>
                        <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Worst') ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php $itemLabel = $field->itemDisplayLabel((string)$item); ?>
                        <tr>
                            <td><?= Html::radio($inputName . '[best]', $best !== '' && $best === $item, $choiceInputOpts(['value' => $item, 'aria-label' => Yii::t('ThiscoveryFormsModule.base', 'Best: {item}', ['item' => $itemLabel])])) ?></td>
                            <th scope="row"><?= Html::encode($itemLabel) ?></th>
                            <td><?= Html::radio($inputName . '[worst]', $worst !== '' && $worst === $item, $choiceInputOpts(['value' => $item, 'aria-label' => Yii::t('ThiscoveryFormsModule.base', 'Worst: {item}', ['item' => $itemLabel])])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($field->type === FormField::TYPE_MAXDIFF): ?>
            <?php
            $md = $field->getItemsConfig();
            $mdVersion = $field->maxDiffVersionFor($value);
            $md['sets'] = $field->maxDiffSets($mdVersion);
            $mdValue = is_array($value) ? ($value['sets'] ?? $value) : [];
            ?>
            <div class="cf-maxdiff" data-cf-maxdiff role="group" aria-labelledby="<?= Html::encode($labelId) ?>"<?= $field->required ? ' aria-required="true"' : '' ?>>
                <?= Html::hiddenInput($inputName . '[version]', (string)$mdVersion) ?>
                <?php foreach ($md['sets'] as $si => $set): ?>
                    <?php
                    $pair = is_array($mdValue[$si] ?? null) ? $mdValue[$si] : [];
                    $best = (string)($pair['best'] ?? '');
                    $worst = (string)($pair['worst'] ?? '');
                    ?>
                    <?php $setTitleId = $inputId . '-set-' . $si; ?>
                    <div class="cf-maxdiff-set" role="group" aria-labelledby="<?= Html::encode($setTitleId) ?>">
                        <div class="cf-maxdiff-set__title" id="<?= Html::encode($setTitleId) ?>"><?= Yii::t('ThiscoveryFormsModule.base', 'Set {n} of {count}', ['n' => $si + 1, 'count' => count($md['sets'])]) ?></div>
                        <table class="cf-grid">
                            <thead>
                            <tr>
                                <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Best') ?></th>
                                <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Item') ?></th>
                                <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Worst') ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($set as $item): ?>
                                <?php $itemLabel = $field->itemDisplayLabel((string)$item); ?>
                                <tr>
                                    <td><?= Html::radio($inputName . '[sets][' . $si . '][best]', $best !== '' && $best === (string)$item, $choiceInputOpts(['value' => $item, 'aria-label' => Yii::t('ThiscoveryFormsModule.base', 'Best: {item}', ['item' => $itemLabel])])) ?></td>
                                    <th scope="row"><?= Html::encode($itemLabel) ?></th>
                                    <td><?= Html::radio($inputName . '[sets][' . $si . '][worst]', $worst !== '' && $worst === (string)$item, $choiceInputOpts(['value' => $item, 'aria-label' => Yii::t('ThiscoveryFormsModule.base', 'Worst: {item}', ['item' => $itemLabel])])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($field->type === FormField::TYPE_DRILLDOWN): ?>
            <?php
            $path = is_array($value) ? array_values(array_map('strval', $value)) : [];
            ?>
            <div class="cf-drilldown" role="group" aria-labelledby="<?= Html::encode($labelId) ?>"<?= $field->required ? ' aria-required="true"' : '' ?> data-cf-drilldown data-cf-tree="<?= Html::encode(json_encode($field->getDrilldownTree(), JSON_UNESCAPED_UNICODE)) ?>">
                <div data-cf-drilldown-levels></div>
                <?= Html::hiddenInput($inputName, json_encode($path, JSON_UNESCAPED_UNICODE), ['data-cf-drilldown-value' => true]) ?>
            </div>
        <?php elseif ($field->type === FormField::TYPE_IMAGE_AREA): ?>
            <?php
            $img = $field->getImageAreaConfig();
            $picked = [];
            if (is_array($value)) {
                $picked = $value['regions'] ?? (array_is_list($value) ? $value : []);
            }
            $picked = array_map('strval', is_array($picked) ? $picked : []);
            ?>
            <div class="cf-hotspot" role="group" aria-labelledby="<?= Html::encode($labelId) ?>"<?= $field->required ? ' aria-required="true"' : '' ?> data-cf-hotspot data-cf-multi="<?= !empty($img['multi']) ? '1' : '0' ?>">
                <div class="cf-hotspot-stage">
                    <?php if ($img['src'] !== ''): ?>
                        <img src="<?= Html::encode($rewriteFileUrls($img['src'])) ?>" alt="">
                    <?php endif; ?>
                    <div class="cf-hotspot-overlay">
                        <?php foreach ($img['regions'] as $region): ?>
                            <?php
                            $rid = (string)($region['id'] ?? '');
                            $selected = in_array($rid, $picked, true);
                            ?>
                            <button type="button"
                                    class="cf-hotspot-region<?= $selected ? ' is-selected' : '' ?>"
                                    data-cf-region="<?= Html::encode($rid) ?>"
                                    aria-pressed="<?= $selected ? 'true' : 'false' ?>"
                                    style="left:<?= (float)($region['x'] ?? 0) ?>%;top:<?= (float)($region['y'] ?? 0) ?>%;width:<?= (float)($region['w'] ?? 10) ?>%;height:<?= (float)($region['h'] ?? 10) ?>%;"
                                    title="<?= Html::encode((string)($region['label'] ?? '')) ?>">
                                <span><?= Html::encode((string)($region['label'] ?? '')) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?= Html::hiddenInput($inputName, json_encode(['regions' => $picked], JSON_UNESCAPED_UNICODE), [
                    'data-cf-hotspot-value' => true,
                ]) ?>
            </div>
        <?php elseif ($field->type === FormField::TYPE_MAP): ?>
            <?php
            $mapCfg = $field->getMapConfig();
            $geo = '';
            if (is_array($value) && ($value['type'] ?? '') === 'FeatureCollection') {
                $geo = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_string($value) && $value !== '') {
                $geo = $value;
            }
            ?>
            <div class="cf-map-field" role="group" aria-labelledby="<?= Html::encode($labelId) ?>"<?= $field->required ? ' aria-required="true"' : '' ?>>
                <?= Html::hiddenInput($inputName, $geo, ['data-cf-map-value' => true]) ?>
                <?php if (class_exists(\humhub\modules\thiscoveryMapping\widgets\MapWidget::class)
                    && \humhub\modules\thiscoveryForms\helpers\MappingAvailability::isEnabled()): ?>
                    <?= \humhub\modules\thiscoveryMapping\widgets\MapWidget::widget([
                        'mode' => 'form',
                        'inputName' => $inputName,
                        'inputValue' => $geo,
                        'height' => 360,
                        'formConfig' => $mapCfg,
                    ]) ?>
                <?php else: ?>
                    <p class="text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'Thiscovery Mapping must be installed and enabled to answer map questions.') ?></p>
                <?php endif; ?>
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
                'aria-required' => $field->required ? 'true' : null,
                'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Your answer'),
            ] + ($field->type === FormField::TYPE_TEXT ? $textRuleAttrs($field) : [])) ?>
        <?php endif; ?>
        <?php
        $justMode = $field->getEffectiveJustification($formModel);
        $justifications = $justifications ?? [];
        $justValue = (string)($justifications[$field->id] ?? '');
        if ($justMode !== FormField::JUSTIFY_NONE):
        ?>
            <div class="cf-justify">
                <label class="cf-label" for="<?= Html::encode($inputId) ?>-just">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Why did you choose this?') ?>
                    <?php if ($justMode === FormField::JUSTIFY_REQUIRED): ?>
                        <span class="cf-required">*</span>
                    <?php else: ?>
                        <span class="cf-optional"><?= Yii::t('ThiscoveryFormsModule.base', 'optional') ?></span>
                    <?php endif; ?>
                </label>
                <?= Html::textarea('SubmitForm[justifications][' . $field->id . ']', $justValue, [
                    'class' => 'form-control',
                    'id' => $inputId . '-just',
                    'rows' => 3,
                    'required' => $justMode === FormField::JUSTIFY_REQUIRED,
                ]) ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
