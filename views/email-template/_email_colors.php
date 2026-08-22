<?php

use humhub\modules\thiscoveryForms\models\FormEmailTemplate;
use yii\helpers\Html;

/** @var FormEmailTemplate $template */
/** @var string $prefix */

$bg = $prefix . '_bg_color';
$font = $prefix . '_font_color';
?>
<div class="cf-email-colors">
    <div class="form-group">
        <label class="cf-label" for="<?= Html::getInputId($template, $bg) ?>">
            <?= Html::encode($template->getAttributeLabel($bg)) ?>
        </label>
        <?= Html::activeInput('color', $template, $bg, ['class' => 'form-control cf-email-color']) ?>
    </div>
    <div class="form-group mb-0">
        <label class="cf-label" for="<?= Html::getInputId($template, $font) ?>">
            <?= Html::encode($template->getAttributeLabel($font)) ?>
        </label>
        <?= Html::activeInput('color', $template, $font, ['class' => 'form-control cf-email-color']) ?>
    </div>
</div>
