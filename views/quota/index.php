<?php

use yii\helpers\Html;

/** @var \humhub\modules\thiscoveryForms\models\CustomForm $formModel */
/** @var array $rows */

?>
<div class="panel">
    <div class="panel-heading">
        <?= Html::encode($formModel->title) ?>
        <?= Html::a(Yii::t('ThiscoveryFormsModule.base', 'Add quota'), $formModel->content->container->createUrl('/thiscovery-forms/quota/edit', ['id' => $formModel->id]), ['class' => 'btn btn-primary btn-sm pull-right']) ?>
    </div>
    <div class="panel-body">
        <p class="help-block"><?= Yii::t('ThiscoveryFormsModule.base', 'Numbers come from the counter. Lowering a target does not remove people who already completed. Reconcile is the command that repairs a bad edit.') ?></p>
        <table class="table">
            <thead>
            <tr>
                <th><?= Yii::t('ThiscoveryFormsModule.base', 'Name') ?></th>
                <th><?= Yii::t('ThiscoveryFormsModule.base', 'Target') ?></th>
                <th><?= Yii::t('ThiscoveryFormsModule.base', 'Accepted') ?></th>
                <th><?= Yii::t('ThiscoveryFormsModule.base', 'Reserved') ?></th>
                <th><?= Yii::t('ThiscoveryFormsModule.base', 'Remaining') ?></th>
                <th><?= Yii::t('ThiscoveryFormsModule.base', 'Fill') ?></th>
                <th><?= Yii::t('ThiscoveryFormsModule.base', 'Status') ?></th>
                <th><?= Yii::t('ThiscoveryFormsModule.base', 'Reconciled') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= Html::encode((string)$row['name']) ?></td>
                    <td><?= (int)$row['target'] ?></td>
                    <td><?= (int)$row['accepted'] ?></td>
                    <td><?= (int)$row['reserved'] ?></td>
                    <td><?= (int)$row['remaining'] ?></td>
                    <td><?= (int)$row['fill_percent'] ?>%</td>
                    <td><?= Html::encode((string)$row['status']) ?></td>
                    <td><?= Html::encode((string)$row['reconciled_at']) ?></td>
                    <td>
                        <?= Html::a(Yii::t('ThiscoveryFormsModule.base', 'Edit'), $formModel->content->container->createUrl('/thiscovery-forms/quota/edit', ['id' => $formModel->id, 'quotaId' => $row['id']])) ?>
                        <?= Html::beginForm($formModel->content->container->createUrl('/thiscovery-forms/quota/index', ['id' => $formModel->id])) ?>
                        <?= Html::hiddenInput('quota_id', (int)$row['id']) ?>
                        <?php if ((string)$row['status'] === 'closed'): ?>
                            <?= Html::submitButton(Yii::t('ThiscoveryFormsModule.base', 'Reopen'), ['name' => 'open', 'value' => 1, 'class' => 'btn btn-default btn-xs']) ?>
                        <?php else: ?>
                            <?= Html::submitButton(Yii::t('ThiscoveryFormsModule.base', 'Close'), ['name' => 'close', 'value' => 1, 'class' => 'btn btn-default btn-xs']) ?>
                        <?php endif; ?>
                        <?= Html::endForm() ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
