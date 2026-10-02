<?php

use yii\helpers\Html;

/** @var array $item one consent statement: code, label, required, input */
/** @var string $nameBase e.g. consent[12] or consent[form] */
/** @var string $idBase e.g. cf-consent-12 */

$code = (string)$item['code'];
$labelId = $idBase . '-' . preg_replace('/[^a-z0-9_-]/i', '', $code);
$name = $nameBase . '[items][' . $code . ']';
$required = !empty($item['required']);
?>
<div class="cf-consent__item">
    <p id="<?= Html::encode($labelId) ?>"><?= Html::encode((string)$item['label']) ?><?php if ($required): ?> <span class="text-danger" aria-hidden="true">*</span><?php endif; ?></p>
    <?php if (($item['input'] ?? '') === 'checkbox'): ?>
        <?php // A required box left unticked is a missing answer, not a refusal (V3-43). ?>
        <?= Html::checkbox($name, false, [
            'value' => 'yes',
            'uncheck' => $required ? null : 'no',
            'aria-labelledby' => $labelId,
            'aria-required' => $required ? 'true' : null,
        ]) ?>
    <?php else: ?>
        <div role="radiogroup" aria-labelledby="<?= Html::encode($labelId) ?>"<?= $required ? ' aria-required="true"' : '' ?>>
            <label><input type="radio" name="<?= Html::encode($name) ?>" value="yes"> <?= Yii::t('ThiscoveryFormsModule.base', 'Yes') ?></label>
            <label><input type="radio" name="<?= Html::encode($name) ?>" value="no"> <?= Yii::t('ThiscoveryFormsModule.base', 'No') ?></label>
        </div>
    <?php endif; ?>
</div>
