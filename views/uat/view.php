<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\UatSubmission;
use yii\helpers\Html;

/** @var UatSubmission $model */
/** @var $contentContainer */

ThiscoveryFormsAsset::register($this);
$this->title = Yii::t('ThiscoveryFormsModule.base', 'UAT submission') . ' #' . $model->id;
$file = $model->getEvidenceFile();
$container = $contentContainer ?? null;
?>

<div class="cf-uat-view">
    <div class="cf-list-header">
        <div>
            <div class="cf-dash-kicker"><?= Yii::t('ThiscoveryFormsModule.base', 'UAT') ?></div>
            <h1 class="cf-list-title">
                <?= Html::encode(($model->test_id ?: Yii::t('ThiscoveryFormsModule.base', 'Proposal')) . ' — ' . (string)$model->scenario) ?>
            </h1>
        </div>
        <div class="cf-list-header__actions">
            <?= Html::a(Yii::t('ThiscoveryFormsModule.base', 'All submissions'), Url::toUatResults($container), ['class' => 'btn btn-default']) ?>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-body">
            <dl class="dl-horizontal">
                <dt><?= Yii::t('ThiscoveryFormsModule.base', 'Type') ?></dt>
                <dd><?= Html::encode(UatSubmission::kindLabels()[$model->kind] ?? $model->kind) ?></dd>
                <dt><?= Yii::t('ThiscoveryFormsModule.base', 'Result') ?></dt>
                <dd><?= Html::encode(UatSubmission::resultLabels()[$model->result] ?? ($model->result ?: '—')) ?></dd>
                <dt><?= Yii::t('ThiscoveryFormsModule.base', 'Feature') ?></dt>
                <dd><?= Html::encode((string)$model->feature) ?></dd>
                <dt><?= Yii::t('ThiscoveryFormsModule.base', 'Priority') ?></dt>
                <dd><?= Html::encode((string)$model->priority) ?></dd>
                <dt><?= Yii::t('ThiscoveryFormsModule.base', 'Roles') ?></dt>
                <dd><?= Html::encode((string)$model->roles) ?></dd>
                <dt><?= Yii::t('ThiscoveryFormsModule.base', 'Tester') ?></dt>
                <dd>
                    <?= Html::encode((string)$model->tester_name) ?>
                    <?php if ($model->tester_email): ?>
                        &lt;<?= Html::encode($model->tester_email) ?>&gt;
                    <?php endif; ?>
                </dd>
                <dt><?= Yii::t('ThiscoveryFormsModule.base', 'When') ?></dt>
                <dd><?= Html::encode($model->created_at) ?></dd>
                <dt><?= Yii::t('ThiscoveryFormsModule.base', 'Environment URL') ?></dt>
                <dd><?= Html::encode((string)$model->environment_url) ?></dd>
                <dt><?= Yii::t('ThiscoveryFormsModule.base', 'Module version') ?></dt>
                <dd><?= Html::encode((string)$model->module_version) ?></dd>
            </dl>

            <?php if ($model->explanation): ?>
                <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Explanation') ?></h4>
                <p><?= nl2br(Html::encode($model->explanation)) ?></p>
            <?php endif; ?>
            <?php if ($model->preconditions): ?>
                <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Preconditions') ?></h4>
                <p><?= nl2br(Html::encode($model->preconditions)) ?></p>
            <?php endif; ?>
            <?php if ($model->steps): ?>
                <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Steps') ?></h4>
                <p><?= nl2br(Html::encode($model->steps)) ?></p>
            <?php endif; ?>
            <?php if ($model->expected_behaviour): ?>
                <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Expected behaviour') ?></h4>
                <p><?= nl2br(Html::encode($model->expected_behaviour)) ?></p>
            <?php endif; ?>
            <?php if ($model->comments): ?>
                <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Comments') ?></h4>
                <p><?= nl2br(Html::encode($model->comments)) ?></p>
            <?php endif; ?>

            <?php if ($file): ?>
                <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Evidence') ?></h4>
                <p>
                    <?= Html::a(
                        Html::encode($file->file_name),
                        Url::toUatFile($file->guid, $container),
                        ['data-pjax' => '0', 'target' => '_blank']
                    ) ?>
                </p>
            <?php endif; ?>

            <hr>
            <h4><?= Yii::t('ThiscoveryFormsModule.base', 'Review') ?></h4>
            <form method="post">
                <?= Html::hiddenInput(Yii::$app->request->csrfParam, Yii::$app->request->csrfToken) ?>
                <div class="form-group">
                    <label><?= Yii::t('ThiscoveryFormsModule.base', 'Review status') ?></label>
                    <?= Html::dropDownList('status', $model->status, [
                        UatSubmission::STATUS_NEW => Yii::t('ThiscoveryFormsModule.base', 'New'),
                        UatSubmission::STATUS_REVIEWED => Yii::t('ThiscoveryFormsModule.base', 'Reviewed'),
                        UatSubmission::STATUS_CLOSED => Yii::t('ThiscoveryFormsModule.base', 'Closed'),
                    ], ['class' => 'form-control']) ?>
                </div>
                <div class="form-group">
                    <label><?= Yii::t('ThiscoveryFormsModule.base', 'Admin notes') ?></label>
                    <?= Html::textarea('admin_notes', $model->admin_notes, ['class' => 'form-control', 'rows' => 3]) ?>
                </div>
                <button type="submit" class="btn btn-primary"><?= Yii::t('ThiscoveryFormsModule.base', 'Save review') ?></button>
            </form>
        </div>
    </div>
</div>
