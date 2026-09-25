<?php

use humhub\modules\thiscoveryForms\helpers\Url as FormsUrl;
use humhub\modules\thiscoveryForms\models\FormLlmUsage;
use yii\helpers\Html;
use yii\helpers\Url;

/** @var array $summary */
/** @var FormLlmUsage[] $rows */

$this->title = Yii::t('ThiscoveryFormsModule.base', 'AI usage and estimated cost');
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <?= Html::encode($this->title) ?>
        <span class="pull-right">
            <a href="<?= Html::encode(FormsUrl::toAdminSettings()) ?>">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Module configuration') ?>
            </a>
        </span>
    </div>
    <div class="panel-body">
        <p class="help-block">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Estimated costs use the rates in module configuration. Figures are informational only — there is no hard spend cap yet.') ?>
        </p>

        <?php if (!empty($summary['warn'])): ?>
            <div class="alert alert-warning">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Estimated cost this month (${cost}) has reached the warning threshold (${threshold}).', [
                    'cost' => number_format((float)$summary['cost'], 2),
                    'threshold' => number_format((float)$summary['warn_threshold'], 2),
                ]) ?>
            </div>
        <?php endif; ?>

        <div class="row" style="margin-bottom:16px">
            <div class="col-md-4"><strong><?= Yii::t('ThiscoveryFormsModule.base', 'Calls this month') ?></strong><br><?= (int)$summary['calls'] ?></div>
            <div class="col-md-4"><strong><?= Yii::t('ThiscoveryFormsModule.base', 'Tokens this month') ?></strong><br><?= (int)$summary['tokens'] ?></div>
            <div class="col-md-4"><strong><?= Yii::t('ThiscoveryFormsModule.base', 'Estimated cost') ?></strong><br>$<?= number_format((float)$summary['cost'], 4) ?></div>
        </div>

        <table class="table table-striped">
            <thead>
            <tr>
                <th><?= Yii::t('ThiscoveryFormsModule.base', 'When') ?></th>
                <th><?= Yii::t('ThiscoveryFormsModule.base', 'User') ?></th>
                <th><?= Yii::t('ThiscoveryFormsModule.base', 'Purpose') ?></th>
                <th><?= Yii::t('ThiscoveryFormsModule.base', 'Model') ?></th>
                <th><?= Yii::t('ThiscoveryFormsModule.base', 'Tokens') ?></th>
                <th><?= Yii::t('ThiscoveryFormsModule.base', 'Est. $') ?></th>
                <th><?= Yii::t('ThiscoveryFormsModule.base', 'OK') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'No LLM calls logged yet.') ?></td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= Html::encode($row->created_at) ?></td>
                        <td><?= (int)$row->user_id ?></td>
                        <td><?= Html::encode($row->purpose) ?></td>
                        <td><?= Html::encode($row->model) ?></td>
                        <td><?= (int)$row->total_tokens ?></td>
                        <td><?= number_format((float)$row->estimated_cost, 4) ?></td>
                        <td><?= $row->success ? Yii::t('ThiscoveryFormsModule.base', 'Yes') : Html::encode((string)$row->error_code) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
