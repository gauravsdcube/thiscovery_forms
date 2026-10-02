<?php

use yii\helpers\Html;

/** @var string $message */

$this->title = Yii::t('ThiscoveryFormsModule.base', 'Download');
?>
<div class="container cf-secure-public">
    <div class="panel panel-default">
        <div class="panel-body">
            <h1><?= Yii::t('ThiscoveryFormsModule.base', 'Download a file') ?></h1>
            <p><?= Html::encode($message) ?></p>
        </div>
    </div>
</div>
