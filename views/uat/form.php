<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\UatSubmission;
use humhub\modules\thiscoveryForms\services\UatCatalogService;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\widgets\ActiveForm;

/** @var UatSubmission $model */
/** @var array $groupedOptions */
/** @var array $catalog */
/** @var int $scenarioCount */
/** @var bool $done */

$this->title = Yii::t('ThiscoveryFormsModule.base', 'UAT test results');
$isGuest = Yii::$app->user->isGuest;
$catalog = $catalog ?? [];
$selected = null;
if ($model->test_id) {
    foreach ($catalog as $row) {
        if (($row['id'] ?? '') === $model->test_id) {
            $selected = $row;
            break;
        }
    }
}
$selectedSteps = $selected ? UatCatalogService::stepList((string)$selected['steps']) : [];
$catalogById = [];
foreach ($catalog as $row) {
    $catalogById[$row['id']] = $row;
}
?>

<style>
.cf-uat-brief { margin: 1.25rem 0 1.5rem; border: 1px solid #d9dee6; border-radius: 8px; background: #f8fafc; }
.cf-uat-brief__empty { padding: 1.25rem 1.5rem; color: #5d6a7a; margin: 0; }
.cf-uat-brief__body { padding: 1.25rem 1.5rem 1.5rem; }
.cf-uat-brief__meta { display: flex; flex-wrap: wrap; gap: 0.4rem 0.75rem; align-items: center; margin-bottom: 0.5rem; }
.cf-uat-brief__id { font-weight: 700; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.cf-uat-brief h2 { margin: 0 0 0.25rem; font-size: 1.35rem; line-height: 1.3; }
.cf-uat-brief__feature { margin: 0 0 1rem; color: #5d6a7a; }
.cf-uat-brief section { margin-bottom: 1.1rem; }
.cf-uat-brief h3 { margin: 0 0 0.4rem; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.04em; color: #3d4a5c; }
.cf-uat-brief p { margin: 0; }
.cf-uat-brief ol { margin: 0; padding-left: 1.3rem; }
.cf-uat-brief li { margin: 0.35rem 0; }
.cf-uat-brief__expected { background: #fff; border: 1px solid #c5d4c8; border-left: 4px solid #3d8b5a; border-radius: 6px; padding: 0.9rem 1rem; }
.cf-uat-brief__expected h3 { color: #2d6a45; }
.cf-uat-page .label { display: inline-block; padding: 0.2em 0.55em; font-weight: 600; }
/* Keep Submit above the fixed site footer so the click is not swallowed. */
.cf-uat-page { padding-bottom: 5.5rem; }
.cf-uat-submit {
    position: sticky;
    bottom: 4.25rem;
    z-index: 1100;
    background: #fff;
    padding: 0.75rem 0 0.25rem;
}
</style>

<div class="container cf-uat-page">
    <div class="row">
        <div class="col-lg-8 offset-lg-2 col-md-10 offset-md-1">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <strong><?= Yii::t('ThiscoveryFormsModule.base', 'Thiscovery Forms — UAT') ?></strong>
                </div>
                <div class="panel-body">
                    <p>
                        <?= Yii::t(
                            'ThiscoveryFormsModule.base',
                            'Pick a Test ID to see the full scenario, steps, and expected behaviour. Carry out that test, then record Pass, Fail, or Blocked. You can also propose a missing scenario.'
                        ) ?>
                    </p>
                    <p>
                        <a class="btn btn-default" href="<?= Html::encode(Url::toUatCsv()) ?>" data-pjax="0">
                            <i class="fa fa-download" aria-hidden="true"></i>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Download scenarios CSV') ?>
                            (<?= (int)$scenarioCount ?>)
                        </a>
                        <?php if (!$isGuest): ?>
                            <a class="btn btn-default" href="<?= Html::encode(Url::toUatResults($contentContainer ?? null)) ?>">
                                <i class="fa fa-list" aria-hidden="true"></i>
                                <?= Yii::t('ThiscoveryFormsModule.base', 'Review submissions') ?>
                            </a>
                        <?php endif; ?>
                    </p>

                    <?php if (!empty($done)): ?>
                        <div class="alert alert-success">
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Thank you — your UAT submission was recorded. You can submit another below.') ?>
                        </div>
                    <?php endif; ?>

                    <?php $form = ActiveForm::begin([
                        'id' => 'cf-uat-form',
                        'action' => Url::toUat(),
                        'method' => 'post',
                        'enableClientValidation' => false,
                        'enableAjaxValidation' => false,
                        'enableClientScript' => false,
                        'options' => [
                            'enctype' => 'multipart/form-data',
                            'novalidate' => true,
                            'data-pjax' => '0',
                            'data-pjax-prevent' => '1',
                        ],
                    ]); ?>

                    <?= $form->errorSummary($model, [
                        'header' => Yii::t('ThiscoveryFormsModule.base', 'Please fix the following:'),
                        'class' => 'alert alert-danger',
                    ]) ?>

                    <?= $form->field($model, 'kind', [
                        'selectors' => ['input' => 'input[name="UatSubmission[kind]"][type=radio]:checked'],
                    ])->radioList(UatSubmission::kindLabels()) ?>

                    <div data-cf-uat-result-fields>
                        <?= $form->field($model, 'test_id')->dropDownList($groupedOptions, [
                            'id' => 'cf-uat-test-id',
                            'prompt' => Yii::t('ThiscoveryFormsModule.base', '— Select Test ID —'),
                        ])->hint(Yii::t('ThiscoveryFormsModule.base', 'The full test (scenario, steps, and expected behaviour) appears below as soon as you pick an ID.')) ?>

                        <div class="cf-uat-brief" data-cf-uat-preview>
                            <p class="cf-uat-brief__empty" data-cf-uat-empty<?= $selected ? ' hidden' : '' ?>>
                                <?= Yii::t('ThiscoveryFormsModule.base', 'Select a Test ID above to see the scenario, numbered steps, and expected behaviour for the test you need to carry out.') ?>
                            </p>
                            <div class="cf-uat-brief__body" data-cf-uat-body<?= $selected ? '' : ' hidden' ?>>
                                <div class="cf-uat-brief__meta">
                                    <span class="cf-uat-brief__id" data-cf-uat-f-id><?= $selected ? Html::encode($selected['id']) : '' ?></span>
                                    <span class="label label-default" data-cf-uat-f-priority><?= $selected ? Html::encode($selected['priority']) : '' ?></span>
                                    <span class="text-muted" data-cf-uat-f-roles><?= $selected && $selected['roles'] !== '' ? Html::encode(Yii::t('ThiscoveryFormsModule.base', 'Roles: {roles}', ['roles' => $selected['roles']])) : '' ?></span>
                                </div>
                                <h2 data-cf-uat-f-scenario><?= $selected ? Html::encode($selected['scenario']) : '' ?></h2>
                                <p class="cf-uat-brief__feature" data-cf-uat-f-feature><?= $selected ? Html::encode($selected['feature']) : '' ?></p>

                                <section data-cf-uat-sec-explanation<?= ($selected && $selected['explanation'] !== '') ? '' : ' hidden' ?>>
                                    <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Explanation') ?></h3>
                                    <p data-cf-uat-f-explanation><?= $selected ? Html::encode($selected['explanation']) : '' ?></p>
                                </section>
                                <section data-cf-uat-sec-preconditions<?= ($selected && $selected['preconditions'] !== '') ? '' : ' hidden' ?>>
                                    <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Preconditions') ?></h3>
                                    <p data-cf-uat-f-preconditions><?= $selected ? Html::encode($selected['preconditions']) : '' ?></p>
                                </section>
                                <section>
                                    <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Steps') ?></h3>
                                    <ol data-cf-uat-f-steps>
                                        <?php foreach ($selectedSteps as $step): ?>
                                            <li><?= Html::encode($step) ?></li>
                                        <?php endforeach; ?>
                                    </ol>
                                </section>
                                <section class="cf-uat-brief__expected">
                                    <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Expected behaviour') ?></h3>
                                    <p data-cf-uat-f-expected><?= $selected ? Html::encode($selected['expected']) : '' ?></p>
                                </section>
                            </div>
                        </div>

                        <?= $form->field($model, 'result', [
                            'selectors' => ['input' => 'input[name="UatSubmission[result]"][type=radio]:checked'],
                        ])->radioList(UatSubmission::resultLabels()) ?>
                    </div>

                    <div data-cf-uat-proposal-fields hidden>
                        <?= $form->field($model, 'feature')->textInput(['maxlength' => true]) ?>
                        <?= $form->field($model, 'scenario')->textInput(['maxlength' => true]) ?>
                        <?= $form->field($model, 'explanation')->textarea(['rows' => 2]) ?>
                        <?= $form->field($model, 'preconditions')->textarea(['rows' => 2]) ?>
                        <?= $form->field($model, 'steps')->textarea(['rows' => 3]) ?>
                        <?= $form->field($model, 'expected_behaviour')->textarea(['rows' => 2]) ?>
                        <?= $form->field($model, 'priority')->dropDownList([
                            'Must' => 'Must',
                            'Should' => 'Should',
                            'Could' => 'Could',
                        ], ['prompt' => '']) ?>
                        <?= $form->field($model, 'roles')->textInput(['maxlength' => true]) ?>
                    </div>

                    <?= $form->field($model, 'comments')->textarea([
                        'rows' => 4,
                        'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'What happened? Include URLs, browsers, and any defect IDs.'),
                    ]) ?>

                    <?= $form->field($model, 'evidenceUpload')->fileInput([
                        'accept' => '.png,.jpg,.jpeg,.gif,.webp,.pdf,.txt,.csv,.log',
                    ])->hint(Yii::t('ThiscoveryFormsModule.base', 'Optional screenshot or log (max 10 MB).')) ?>

                    <div class="row">
                        <div class="col-md-6">
                            <?= $form->field($model, 'tester_name')->textInput(['maxlength' => true])->hint(
                                $isGuest
                                    ? Yii::t('ThiscoveryFormsModule.base', 'Required — enter your name so we can follow up.')
                                    : ''
                            ) ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'tester_email')->textInput(['maxlength' => true]) ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <?= $form->field($model, 'environment_url')->textInput(['maxlength' => true]) ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'module_version')->textInput(['maxlength' => true]) ?>
                        </div>
                    </div>

                    <div class="form-group cf-uat-submit">
                        <?= Html::submitButton(
                            Yii::t('ThiscoveryFormsModule.base', 'Submit UAT result'),
                            [
                                'class' => 'btn btn-primary',
                                'data-pjax-prevent' => '1',
                            ]
                        ) ?>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script <?= \humhub\helpers\Html::nonce() ?>>
(function () {
    var catalog = <?= Json::htmlEncode($catalogById) ?>;
    var form = document.getElementById('cf-uat-form');
    if (!form) return;
    var preview = form.querySelector('[data-cf-uat-preview]');
    var emptyEl = form.querySelector('[data-cf-uat-empty]');
    var bodyEl = form.querySelector('[data-cf-uat-body]');
    var rolesPrefix = <?= Json::htmlEncode(Yii::t('ThiscoveryFormsModule.base', 'Roles: {roles}', ['roles' => '{roles}'])) ?>;

    function selectedKind() {
        var el = form.querySelector('input[name="UatSubmission[kind]"]:checked');
        return el ? el.value : 'result';
    }

    function toggleKind() {
        var kind = selectedKind();
        var resultBox = form.querySelector('[data-cf-uat-result-fields]');
        var proposalBox = form.querySelector('[data-cf-uat-proposal-fields]');
        if (resultBox) resultBox.hidden = kind !== 'result';
        if (proposalBox) proposalBox.hidden = kind !== 'proposal';
    }

    function splitSteps(steps) {
        return String(steps || '').split(/\s+\|\s+/).map(function (s) {
            return s.replace(/^\d+\.\s*/, '').trim();
        }).filter(Boolean);
    }

    function setText(sel, text) {
        var node = preview.querySelector(sel);
        if (node) node.textContent = text || '';
    }

    function toggleSec(sel, show) {
        var node = preview.querySelector(sel);
        if (node) node.hidden = !show;
    }

    function fillPreview(item, scroll) {
        if (!preview) return;
        if (!item) {
            if (emptyEl) emptyEl.hidden = false;
            if (bodyEl) bodyEl.hidden = true;
            return;
        }
        if (emptyEl) emptyEl.hidden = true;
        if (bodyEl) bodyEl.hidden = false;
        setText('[data-cf-uat-f-id]', item.id);
        setText('[data-cf-uat-f-scenario]', item.scenario);
        setText('[data-cf-uat-f-feature]', item.feature);
        setText('[data-cf-uat-f-explanation]', item.explanation);
        setText('[data-cf-uat-f-preconditions]', item.preconditions);
        setText('[data-cf-uat-f-expected]', item.expected);
        setText('[data-cf-uat-f-priority]', item.priority);
        setText('[data-cf-uat-f-roles]', item.roles ? rolesPrefix.replace('{roles}', item.roles) : '');
        toggleSec('[data-cf-uat-sec-explanation]', !!item.explanation);
        toggleSec('[data-cf-uat-sec-preconditions]', !!item.preconditions);
        var ol = preview.querySelector('[data-cf-uat-f-steps]');
        if (ol) {
            ol.textContent = '';
            splitSteps(item.steps).forEach(function (step) {
                var li = document.createElement('li');
                li.textContent = step;
                ol.appendChild(li);
            });
        }
        if (scroll && bodyEl && typeof bodyEl.scrollIntoView === 'function') {
            bodyEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function loadTest(id, scroll) {
        if (!id) {
            fillPreview(null, false);
            return;
        }
        if (catalog[id]) {
            fillPreview(catalog[id], scroll);
            return;
        }
        fillPreview(null, false);
    }

    form.querySelectorAll('input[name="UatSubmission[kind]"]').forEach(function (r) {
        r.addEventListener('change', toggleKind);
    });
    var sel = document.getElementById('cf-uat-test-id');
    if (sel) {
        sel.addEventListener('change', function () { loadTest(sel.value, true); });
        if (sel.value) loadTest(sel.value, false);
    }
    toggleKind();
})();
</script>
