<?php

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormExportLog;
use yii\helpers\Html;
use yii\widgets\LinkPager;

/** @var CustomForm $formModel */
/** @var yii\data\ActiveDataProvider|null $downloadProvider */

$downloadProvider = $downloadProvider ?? null;
$total = $downloadProvider ? (int)$downloadProvider->getTotalCount() : 0;
?>

<p class="cf-download-log__intro">
    <?= Yii::t('ThiscoveryFormsModule.base', 'Each CSV download from Answers, the dashboard, and Response integrity is listed here.') ?>
</p>

<?php if (!$total): ?>
    <div class="cf-list-empty">
        <i class="fa fa-download"></i>
        <h3><?= Yii::t('ThiscoveryFormsModule.base', 'No downloads yet.') ?></h3>
        <p><?= Yii::t('ThiscoveryFormsModule.base', 'When someone downloads the answers CSV, their name, the time, and the row count will appear here.') ?></p>
    </div>
<?php else: ?>
    <div class="cf-form-table-wrap">
        <table class="cf-form-table">
            <thead>
            <tr>
                <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Downloaded by') ?></th>
                <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'When') ?></th>
                <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Rows') ?></th>
                <th scope="col"><?= Yii::t('ThiscoveryFormsModule.base', 'Personal data') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($downloadProvider->getModels() as $log): ?>
                <?php
                /** @var FormExportLog $log */
                $who = $log->user ? $log->user->displayName : null;
                if (!$who) {
                    $who = $log->user_id
                        ? Yii::t('ThiscoveryFormsModule.base', 'Deleted user')
                        : Yii::t('ThiscoveryFormsModule.base', 'Unknown');
                }
                ?>
                <tr>
                    <td><?= Html::encode($who) ?></td>
                    <td class="cf-form-table__date-col"><?= Html::encode(Yii::$app->formatter->asDatetime($log->created_at, 'short')) ?></td>
                    <td><?= (int)$log->row_count ?></td>
                    <td>
                        <?= (int)$log->scrubbed
                            ? Yii::t('ThiscoveryFormsModule.base', 'Removed')
                            : Yii::t('ThiscoveryFormsModule.base', 'Included') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="cf-list-pager">
        <?= LinkPager::widget(['pagination' => $downloadProvider->pagination]) ?>
    </div>
<?php endif; ?>
