<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var bool $isNew */
/** @var FormField[] $fieldList */

$resolved = \humhub\modules\thiscoveryForms\services\integrity\IntegritySettings::forForm($formModel);
$rules = $resolved['consistency_rules'] ?? [];
if (!$rules) {
    $rules = [['id' => '', 'label' => '', 'conditions' => [['field_id' => '', 'operator' => 'equals', 'value' => ''], ['field_id' => '', 'operator' => 'equals', 'value' => '']]]];
}
$fieldOpts = ['' => Yii::t('ThiscoveryFormsModule.base', 'Question…')];
foreach ($fieldList as $cfField) {
    if ($cfField->collectsAnswer()) {
        $fieldOpts[(int)$cfField->id] = $cfField->label ?: ('#' . $cfField->id);
    }
}
?>
<div class="cf-studio__settings" data-cf-integrity-settings>
    <div class="cf-field mb-3">
        <h5 class="cf-section__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Response integrity') ?></h5>
        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Tick Enable integrity checks to record scores on complete responses. Leave a setting on “Use site default” to inherit Administration → Modules → Thiscovery Forms. Open Help from this tab for the full guide.')]) ?>
    </div>
    <?= $this->render('_integrity_settings_fields', [
        'namePrefix' => 'integrity',
        'values' => \humhub\modules\thiscoveryForms\services\integrity\IntegritySettings::overlayForForm($formModel),
        'defaults' => \humhub\modules\thiscoveryForms\services\integrity\IntegritySettings::global(),
        'allowInherit' => true,
    ]) ?>

    <div data-cf-integrity-when-on>
    <div class="cf-field mt-4">
        <h5 class="mb-0"><?= Yii::t('ThiscoveryFormsModule.base', 'Consistency rules') ?></h5>
        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Each rule needs at least two conditions. The response is flagged only when every condition matches, for example Q1 equals “Never used the service” and Q10 equals “I use it every day”. Give the rule a short id and a label that reviewers will understand. Rules never auto-reject.')]) ?>
    </div>
    <p class="help-block"><?= Yii::t('ThiscoveryFormsModule.base', 'Flag a response when all conditions match. Add further rules if you need more than one check.') ?></p>
    <div data-cf-consistency-rules>
        <?php foreach ($rules as $ri => $rule): ?>
            <div class="cf-consistency-rule mb-3" data-cf-consistency-rule>
                <div class="row g-2 mb-2">
                    <div class="col-md-4">
                        <div class="cf-field">
                            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Rule id') ?></label>
                            <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Short code stored on the flag, for example r1 or never-vs-daily. If you leave this blank, the form assigns r1, r2, and so on.')]) ?>
                            <?= Html::textInput('integrity[consistency_rules][' . $ri . '][id]', $rule['id'] ?? '', ['class' => 'form-control', 'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Rule id')]) ?>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="cf-field">
                            <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'What this flags') ?></label>
                            <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Shown to reviewers when this rule matches. Describe the contradiction in plain language.')]) ?>
                            <?= Html::textInput('integrity[consistency_rules][' . $ri . '][label]', $rule['label'] ?? '', ['class' => 'form-control', 'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'What this flags')]) ?>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-sm btn-light" data-cf-remove-consistency title="<?= Yii::t('ThiscoveryFormsModule.base', 'Remove') ?>"><i class="fa fa-times"></i></button>
                    </div>
                </div>
                <?php foreach (($rule['conditions'] ?? []) as $ci => $cond): ?>
                    <div class="row g-2 mb-1" data-cf-consistency-cond>
                        <div class="col-md-5">
                            <?= Html::dropDownList('integrity[consistency_rules][' . $ri . '][conditions][' . $ci . '][field_id]', $cond['field_id'] ?? '', $fieldOpts, ['class' => 'form-control']) ?>
                        </div>
                        <div class="col-md-3">
                            <?= Html::dropDownList('integrity[consistency_rules][' . $ri . '][conditions][' . $ci . '][operator]', $cond['operator'] ?? 'equals', [
                                'equals' => Yii::t('ThiscoveryFormsModule.base', 'equals'),
                                'not_equals' => Yii::t('ThiscoveryFormsModule.base', 'does not equal'),
                                'contains' => Yii::t('ThiscoveryFormsModule.base', 'contains'),
                            ], ['class' => 'form-control']) ?>
                        </div>
                        <div class="col-md-4">
                            <?= Html::textInput('integrity[consistency_rules][' . $ri . '][conditions][' . $ci . '][value]', $cond['value'] ?? '', ['class' => 'form-control', 'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Value')]) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-sm btn-light" data-cf-add-consistency>
        <i class="fa fa-plus"></i> <?= Yii::t('ThiscoveryFormsModule.base', 'Add consistency rule') ?>
    </button>
    </div>
    <?php if (!$isNew): ?>
        <div class="cf-field mt-4">
            <p class="mb-0">
                <a href="<?= Html::encode(Url::toAccessTokens($formModel)) ?>">
                    <?= Yii::t('ThiscoveryFormsModule.base', 'Manage unique invitation links') ?>
                </a>
            </p>
            <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Create signed one-time or multi-use links for Unique invitation link access mode. Raw tokens appear only in the CSV you download at generation time. Only hashes are stored.')]) ?>
        </div>
    <?php endif; ?>
</div>
