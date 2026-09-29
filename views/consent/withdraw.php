<?php

use yii\helpers\Html;

/** @var \humhub\modules\thiscoveryForms\models\CustomForm $formModel */
/** @var bool $done */
?>
<div class="panel">
    <div class="panel-body">
        <?php if ($done): ?>
            <p><?= Yii::t('ThiscoveryFormsModule.base', 'Your withdrawal has been recorded. Answers are not deleted. A request to delete data is an admin task.') ?></p>
        <?php else: ?>
            <?= Html::beginForm() ?>
            <div class="form-group">
                <label><?= Yii::t('ThiscoveryFormsModule.base', 'Withdrawal code') ?></label>
                <input class="form-control" name="token" value="<?= Html::encode((string)Yii::$app->request->get('token', '')) ?>">
            </div>
            <div class="form-group">
                <label><?= Yii::t('ThiscoveryFormsModule.base', 'What should happen') ?></label>
                <select class="form-control" name="scope">
                    <option value="stop_contact"><?= Yii::t('ThiscoveryFormsModule.base', 'Stop contact and keep my answers') ?></option>
                    <option value="keep_data"><?= Yii::t('ThiscoveryFormsModule.base', 'Record withdrawal and keep contact') ?></option>
                    <option value="delete_requested"><?= Yii::t('ThiscoveryFormsModule.base', 'Ask an administrator to delete my data') ?></option>
                </select>
            </div>
            <div class="form-group">
                <label><?= Yii::t('ThiscoveryFormsModule.base', 'Reason') ?></label>
                <textarea class="form-control" name="reason"></textarea>
            </div>
            <button class="btn btn-primary" type="submit"><?= Yii::t('ThiscoveryFormsModule.base', 'Withdraw') ?></button>
            <?= Html::endForm() ?>
        <?php endif; ?>
    </div>
</div>
