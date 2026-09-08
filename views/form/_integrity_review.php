<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormIntegrityAudit;
use humhub\modules\thiscoveryForms\models\FormIntegrityMeta;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var FormAnswer $answer */
/** @var bool $canManage */
/** @var bool $canDecideAnalysis */

$canManage = !empty($canManage) || $formModel->canManage();
$canDecideAnalysis = !empty($canDecideAnalysis) || $formModel->canDecideAnalysis();
$meta = $answer->integrityMeta;
$labels = FormIntegrityMeta::componentLabels();
?>
<div class="cf-integrity-review">
    <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Integrity detail') ?></h3>
    <p class="help-block"><?= Yii::t('ThiscoveryFormsModule.base', 'These flags are screening aids for review. They are not a finding of misconduct, fraud, or that the person is not genuine. Include or exclude from analysis using the box at the top of this response.') ?></p>
    <?php if (!$meta): ?>
        <p class="help-block"><?= Yii::t('ThiscoveryFormsModule.base', 'No quality metadata has been recorded for this response yet.') ?></p>
    <?php else: ?>
    <div class="cf-integrity-score">
        <strong><?= Yii::t('ThiscoveryFormsModule.base', 'Quality score') ?>:</strong>
        <?= Html::encode(number_format((float)$meta->overall_score, 1)) ?>
        <span class="cf-form-row__chip"><?= Html::encode($meta->getStatusLabel()) ?></span>
        <span class="cf-form-row__chip cf-form-row__chip--analysis"><?= Html::encode($meta->getAnalysisLabel()) ?></span>
        <?php if ($meta->status_override): ?>
            <span class="cf-form-row__chip"><?= Yii::t('ThiscoveryFormsModule.base', 'Manually overridden') ?></span>
        <?php endif; ?>
    </div>
    <dl class="cf-integrity-dl">
        <dt><?= Yii::t('ThiscoveryFormsModule.base', 'Response ID') ?></dt>
        <dd><?= (int)$answer->id ?></dd>
        <dt><?= Yii::t('ThiscoveryFormsModule.base', 'Started') ?></dt>
        <dd><?= $meta->started_at ? Yii::$app->formatter->asDatetime($meta->started_at, 'medium') : '—' ?></dd>
        <dt><?= Yii::t('ThiscoveryFormsModule.base', 'Completed') ?></dt>
        <dd><?= $meta->completed_at ? Yii::$app->formatter->asDatetime($meta->completed_at, 'medium') : '—' ?></dd>
        <dt><?= Yii::t('ThiscoveryFormsModule.base', 'Duration') ?></dt>
        <dd><?= $meta->duration_seconds !== null ? Yii::t('ThiscoveryFormsModule.base', '{n} seconds', ['n' => (int)$meta->duration_seconds]) : '—' ?></dd>
        <dt><?= Yii::t('ThiscoveryFormsModule.base', 'Typical completion') ?></dt>
        <dd><?= $meta->median_seconds ? Yii::t('ThiscoveryFormsModule.base', '{n} seconds', ['n' => (int)$meta->median_seconds]) : Yii::t('ThiscoveryFormsModule.base', 'Not enough data yet') ?></dd>
    </dl>
    <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Component scores') ?></h4>
    <ul class="cf-integrity-components">
        <?php foreach ($meta->getComponentScoresForViewer($canManage) as $key => $score): ?>
            <li><?= Html::encode($labels[$key] ?? $key) ?>: <?= Html::encode(number_format((float)$score, 1)) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php
    $flags = $meta->getFlagsForViewer($canManage);
    $groups = [];
    foreach ($flags as $flag) {
        $groups[$flag['category'] ?? 'other'][] = $flag;
    }
    ?>
    <?php if ($groups): ?>
        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Flags') ?></h4>
        <?php foreach ($groups as $cat => $items): ?>
            <div class="cf-integrity-flag-group">
                <strong><?= Html::encode($cat) ?></strong>
                <ul>
                    <?php foreach ($items as $flag): ?>
                        <li><?= Html::encode($flag['message'] ?? $flag['code'] ?? '') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($meta->getSimilarAnswerIds()): ?>
        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Similar responses') ?></h4>
        <p>
            <?php foreach ($meta->getSimilarAnswerIds() as $sid): ?>
                <a href="<?= Html::encode(Url::toAnswers($formModel, ['answer' => $sid])) ?>">#<?= (int)$sid ?></a>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>
    <?php if ($meta->page_timings_json): ?>
        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Time on pages') ?></h4>
        <p class="help-block"><?= Yii::t('ThiscoveryFormsModule.base', 'Reported by the browser. Supporting signal only — timings can be incomplete or altered.') ?></p>
        <ul>
            <?php foreach ($meta->getPageTimings() as $page => $ms): ?>
                <li><?= Yii::t('ThiscoveryFormsModule.base', 'Page {page}: {n}s', ['page' => $page, 'n' => round(((int)$ms) / 1000, 1)]) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($meta->getQuestionTimings()): ?>
        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Time on questions') ?></h4>
        <ul>
            <?php
            $fieldLabels = [];
            foreach ($formModel->fields as $f) {
                $fieldLabels[(string)$f->id] = $f->label;
            }
            foreach ($meta->getQuestionTimings() as $fid => $ms):
                $label = $fieldLabels[(string)$fid] ?? Yii::t('ThiscoveryFormsModule.base', 'Question {id}', ['id' => $fid]);
                ?>
                <li><?= Html::encode($label) ?>: <?= Yii::t('ThiscoveryFormsModule.base', '{n}s', ['n' => round(((int)$ms) / 1000, 1)]) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($canDecideAnalysis): ?>
        <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Review notes') ?></h4>
        <?php if ($meta->notes): ?>
            <pre class="cf-integrity-notes"><?= Html::encode($meta->notes) ?></pre>
        <?php endif; ?>
        <?= Html::beginForm(Url::toIntegrityNote($formModel, (int)$answer->id), 'post') ?>
            <?= Html::textarea('note', '', ['class' => 'form-control', 'rows' => 3, 'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Review notes')]) ?>
            <button class="btn btn-sm btn-default mt-2" type="submit"><?= Yii::t('ThiscoveryFormsModule.base', 'Add note') ?></button>
        <?= Html::endForm() ?>
        <?php
        $audits = FormIntegrityAudit::find()
            ->where(['answer_id' => $answer->id])
            ->with('user')
            ->orderBy(['id' => SORT_DESC])
            ->limit(30)
            ->all();
        ?>
        <?php if ($audits): ?>
            <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Audit history') ?></h4>
            <ul class="cf-integrity-audit">
                <?php foreach ($audits as $audit): ?>
                    <li>
                        <?= Html::encode(Yii::$app->formatter->asDatetime($audit->created_at, 'short')) ?>
                        <?php
                        $who = $audit->user->displayName ?? null;
                        if ($who): ?>
                            — <?= Html::encode($who) ?>
                        <?php endif; ?>
                        — <?= Html::encode($audit->action) ?>
                        <?php if ($audit->from_value || $audit->to_value): ?>
                            (<?= Html::encode((string)$audit->from_value) ?> → <?= Html::encode((string)$audit->to_value) ?>)
                        <?php endif; ?>
                        <?php if ($audit->reason): ?>
                            — <?= Html::encode($audit->reason) ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
    <?php endif; ?>
</div>
