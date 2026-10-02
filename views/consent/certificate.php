<?php

use yii\helpers\Html;

/** @var \yii\web\View $this */
/** @var string $html */

$this->registerJs("document.getElementById('cf-print-certificate').addEventListener('click', function () { window.print(); });");
$this->registerCss('@media print { .cf-no-print, #topbar, .layout-nav-container, .space-nav, footer { display: none !important; } }');
?>
<div class="cf-consent-certificate-page">
    <p class="cf-no-print">
        <?= Html::button(Yii::t('ThiscoveryFormsModule.base', 'Print or save as PDF'), [
            'class' => 'btn btn-default',
            'id' => 'cf-print-certificate',
        ]) ?>
    </p>
    <?= $html ?>
</div>
