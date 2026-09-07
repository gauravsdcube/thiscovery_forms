<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var string|null $error */

$this->title = Yii::t('ThiscoveryFormsModule.base', 'Import theme');
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <?= Html::encode($this->title) ?>
        <span class="pull-right">
            <a href="<?= Html::encode(Url::to(['/thiscovery-forms/admin/settings'])) ?>">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Back to module settings') ?>
            </a>
        </span>
    </div>
    <div class="panel-body">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= Html::encode($error) ?></div>
        <?php endif; ?>
        <?= Html::beginForm('', 'post', ['enctype' => 'multipart/form-data']) ?>
            <div class="form-group">
                <label><?= Yii::t('ThiscoveryFormsModule.base', 'Theme JSON file') ?></label>
                <input type="file" name="theme_file" accept="application/json,.json" class="form-control">
            </div>
            <div class="form-group">
                <label><?= Yii::t('ThiscoveryFormsModule.base', 'Or paste JSON') ?></label>
                <textarea name="theme_json" class="form-control" rows="12" spellcheck="false"></textarea>
            </div>
            <?= Html::submitButton(Yii::t('ThiscoveryFormsModule.base', 'Import'), ['class' => 'btn btn-primary']) ?>
        <?= Html::endForm() ?>
    </div>
</div>
