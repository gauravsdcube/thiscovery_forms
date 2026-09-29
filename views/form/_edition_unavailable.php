<?php

use humhub\modules\thiscoveryForms\models\CustomForm;
use yii\helpers\Html;

/** @var CustomForm $formModel */
?>
<div class="cf-fill-card">
    <?php if (trim((string)$formModel->title) !== ''): ?>
        <h1 class="cf-fill-hero__title"><?= Html::encode($formModel->title) ?></h1>
    <?php endif; ?>
    <div class="alert alert-warning" role="alert">
        <?= Yii::t('ThiscoveryFormsModule.base', 'This survey edition could not be opened. Please reload the page. If it still fails, contact the person who sent you the link.') ?>
    </div>
</div>
