<?php

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\ExportSettings;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var FormField[] $fieldList */

$fields = array_values(array_filter($fieldList, static fn($f) => $f->collectsAnswer()));
$cfg = ExportSettings::get($formModel);
$exclude = array_fill_keys($cfg['exclude_columns'], true);
$scrub = $cfg['pii_scrub'];
$catalogue = ExportSettings::catalogue($formModel, $fields);
$groups = [
    ExportSettings::GROUP_META => Yii::t('ThiscoveryFormsModule.base', 'Response metadata'),
    ExportSettings::GROUP_QUESTION => Yii::t('ThiscoveryFormsModule.base', 'Questions'),
];
$byGroup = [ExportSettings::GROUP_META => [], ExportSettings::GROUP_QUESTION => []];
foreach ($catalogue as $col) {
    $byGroup[$col['group']][] = $col;
}
?>
<div class="cf-studio__settings">
    <h3 class="cf-settings-pane__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Export') ?></h3>
    <p class="cf-hint text-muted">
        <?= Yii::t('ThiscoveryFormsModule.base', 'These settings apply to every answers CSV from Answers, Dashboard, and Response integrity. Untick a column to leave it out. New questions stay included until you exclude them.') ?>
    </p>
    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Column choice and PII scrubbing are saved on the form. Header labels (question text vs variable names) are still chosen on the Answers page when you download.')]) ?>

    <div class="cf-check-setting mt-3">
        <label>
            <?= Html::hiddenInput('export_pii_scrub', '0') ?>
            <?= Html::checkbox('export_pii_scrub', $scrub, [
                'value' => '1',
                'uncheck' => null,
                'data-cf-export-scrub' => true,
            ]) ?>
            <?= Yii::t('ThiscoveryFormsModule.base', 'Scrub PII') ?>
        </label>
        <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Omits identity and personal-data columns, and replaces emails, phone numbers, and IP addresses in other cells with [redacted]. Stored answers are not changed. The file name ends with -scrubbed.csv.')]) ?>
    </div>
    <p class="cf-hint text-muted mb-0">
        <?= Yii::t('ThiscoveryFormsModule.base', 'When on, identity and personal-data columns are omitted and remaining cells have emails, phone numbers, and IP addresses replaced with [redacted]. Stored answers are not changed. The download filename ends with -scrubbed.csv.') ?>
    </p>

    <?php $analysis = \humhub\modules\thiscoveryForms\services\ExportSettings::get($formModel); ?>
    <details class="cf-set-acc" open>
        <summary>
            <span class="cf-set-acc__title"><?= Yii::t('ThiscoveryFormsModule.base', 'Ready for analysis') ?></span>
            <span class="cf-set-acc__summary"><?= Yii::t('ThiscoveryFormsModule.base', 'How empty answers and multiple-choice options are coded.') ?></span>
        </summary>
        <div class="cf-set-acc__body">
    <fieldset class="cf-export-analysis mt-3">
        <legend class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Ready for analysis') ?></legend>
        <?php
        $guides = [
            'export_multi_columns' => Yii::t('ThiscoveryFormsModule.base', 'Adds a 0 or 1 column for each multiple-choice option, as well as the column with the joined answer.'),
            'export_missing_codes' => Yii::t('ThiscoveryFormsModule.base', 'Empty answers use -99 when the question was shown, -98 when logic hid it, and -97 when the person did not reach it.'),
            'export_include_in_progress' => Yii::t('ThiscoveryFormsModule.base', 'Includes responses that are still in progress. Off exports complete responses only, matching the dashboard.'),
        ];
        foreach ([
            'export_multi_columns' => [$analysis['multi_columns'], Yii::t('ThiscoveryFormsModule.base', 'One 0/1 column per multiple-choice option, as well as the joined answer')],
            'export_missing_codes' => [$analysis['missing_codes'], Yii::t('ThiscoveryFormsModule.base', 'Codes for empty answers: -99 shown but not answered, -98 hidden by logic, -97 not reached')],
            'export_include_in_progress' => [$analysis['include_in_progress'], Yii::t('ThiscoveryFormsModule.base', 'Include responses still in progress (off: complete responses only, as on the dashboard)')],
        ] as $name => [$on, $label]): ?>
            <div class="cf-check-setting">
                <label>
                    <?= Html::hiddenInput($name, '0') ?>
                    <?= Html::checkbox($name, $on, ['value' => '1', 'uncheck' => null]) ?>
                    <?= Html::encode($label) ?>
                </label>
                <?= $this->render('_setting_guide', ['text' => $guides[$name]]) ?>
            </div>
        <?php endforeach; ?>
    </fieldset>
        </div>
    </details>

    <p class="cf-hint text-muted mt-3<?= $scrub ? '' : ' d-none' ?>" data-cf-export-pii-note>
        <?= Yii::t('ThiscoveryFormsModule.base', 'Identity and PII-tagged columns are locked off while scrubbing is on. They will be omitted from the CSV even if you had included them.') ?>
    </p>

    <?php foreach ($groups as $groupKey => $groupTitle): ?>
        <?php $items = $byGroup[$groupKey] ?? []; ?>
        <details class="cf-set-acc"<?= $groupKey === \humhub\modules\thiscoveryForms\services\ExportSettings::GROUP_META ? ' open' : '' ?>>
            <summary>
                <span class="cf-set-acc__title"><?= Html::encode($groupTitle) ?></span>
                <span class="cf-set-acc__summary"><?= Yii::t('ThiscoveryFormsModule.base', '{n} columns', ['n' => count($items)]) ?></span>
            </summary>
            <div class="cf-set-acc__body">
        <div class="cf-export-cols" data-cf-export-group="<?= Html::encode($groupKey) ?>">
            <div class="cf-export-cols__head">
                <div class="cf-label-row" style="flex:1 1 auto">
                    <h5 class="cf-section__title mb-0"><?= Html::encode($groupTitle) ?></h5>
                    <?= $this->render('_setting_guide', ['text' => Yii::t('ThiscoveryFormsModule.base', 'Tick a column to include it in every answers CSV. Untick to leave it out. New questions stay included until you exclude them. Columns marked PII are locked off while Scrub PII is on.')]) ?>
                </div>
                <div class="cf-export-cols__toolbar">
                    <button type="button" class="btn btn-link btn-sm" data-cf-export-all="<?= Html::encode($groupKey) ?>">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Select all') ?>
                    </button>
                    <button type="button" class="btn btn-link btn-sm" data-cf-export-none="<?= Html::encode($groupKey) ?>">
                        <?= Yii::t('ThiscoveryFormsModule.base', 'Select none') ?>
                    </button>
                </div>
            </div>
            <?php if (!$items): ?>
                <p class="cf-hint text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'No question columns yet.') ?></p>
            <?php endif; ?>
            <?php foreach ($items as $col): ?>
                <?php
                $key = $col['key'];
                $included = !isset($exclude[$key]);
                $lock = !empty($col['lock']);
                $lockedNow = $scrub && $lock;
                $label = $col['header'];
                if ($col['field'] && trim((string)$col['field']->variable) !== '' && $col['header'] !== $col['field']->variable) {
                    $label = $col['header'] . ' [' . $col['field']->variable . ']';
                }
                ?>
                <?= Html::hiddenInput('export_known_columns[]', $key) ?>
                <label class="cf-export-col<?= $lockedNow ? ' is-locked' : '' ?><?= $lock ? ' is-pii' : '' ?>">
                    <?php if ($lockedNow && $included): ?>
                        <?= Html::hiddenInput('export_include[]', $key, ['data-cf-export-lock-hold' => true]) ?>
                    <?php endif; ?>
                    <?= Html::checkbox('export_include[]', $included && !$lockedNow, [
                        'value' => $key,
                        'uncheck' => null,
                        'data-cf-export-col' => true,
                        'data-cf-export-pref' => $included ? '1' : '0',
                        'data-cf-export-lock' => $lock ? '1' : '0',
                        'disabled' => $lockedNow,
                    ]) ?>
                    <span><?= Html::encode($label) ?></span>
                    <?php if ($lock): ?>
                        <span class="cf-export-col__tag"><?= Yii::t('ThiscoveryFormsModule.base', 'PII') ?></span>
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>
            </div>
        </details>
    <?php endforeach; ?>
</div>
