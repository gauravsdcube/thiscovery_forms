<?php

use yii\helpers\Html;

/** @var \humhub\modules\thiscoveryForms\models\CustomForm $formModel */
/** @var array $documents */
/** @var array $records */
/** @var bool $canViewRecords */

?>
<div class="panel">
    <div class="panel-heading"><?= Html::encode($formModel->title) ?></div>
    <div class="panel-body">
        <p><?= Html::a(Yii::t('ThiscoveryFormsModule.base', 'New draft'), $formModel->actionUrl(['/thiscovery-forms/consent/edit', 'id' => $formModel->id]), ['class' => 'btn btn-primary btn-sm']) ?></p>
        <table class="table">
            <thead><tr><th><?= Yii::t('ThiscoveryFormsModule.base', 'Version') ?></th><th><?= Yii::t('ThiscoveryFormsModule.base', 'Title') ?></th><th><?= Yii::t('ThiscoveryFormsModule.base', 'Status') ?></th></tr></thead>
            <tbody>
            <?php foreach ($documents as $doc): ?>
                <tr>
                    <td><?= (int)$doc['version'] ?></td>
                    <td><?= Html::a(Html::encode((string)$doc['title']), $formModel->actionUrl(['/thiscovery-forms/consent/edit', 'id' => $formModel->id, 'documentId' => $doc['id']])) ?></td>
                    <td><?= Html::encode((string)$doc['status']) ?><?= (int)$doc['version'] === 0 ? ' · ' . Yii::t('ThiscoveryFormsModule.base', 'Legacy / unverified') : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($canViewRecords): ?>
            <h3><?= Yii::t('ThiscoveryFormsModule.base', 'Records') ?></h3>
            <table class="table">
                <thead><tr><th><?= Yii::t('ThiscoveryFormsModule.base', 'Signed') ?></th><th><?= Yii::t('ThiscoveryFormsModule.base', 'Method') ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?= Html::encode((string)$record['signed_at']) ?></td>
                        <td><?= Html::encode((string)$record['signature_method']) ?><?= $record['answer_id'] === null ? ' · ' . Yii::t('ThiscoveryFormsModule.base', 'Unlinked') : '' ?></td>
                        <td>
                            <?php if ($record['answer_id'] !== null && (string)$record['signature_method'] !== 'legacy'): ?>
                                <?= Html::a(Yii::t('ThiscoveryFormsModule.base', 'Download certificate'), $formModel->actionUrl(['/thiscovery-forms/consent/certificate', 'id' => $formModel->id, 'recordId' => $record['id']])) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
