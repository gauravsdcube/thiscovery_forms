<?php

use humhub\modules\thiscoveryForms\services\ConsentService;
use humhub\modules\thiscoveryForms\services\HtmlSanitizer;
use yii\helpers\Html;

/** @var \humhub\modules\thiscoveryForms\models\CustomForm $formModel */
/** @var array $document */

$consent = new ConsentService();
$items = $consent->items((int)$document['id']);
?>
<fieldset class="cf-consent" data-cf-consent="form">
    <legend class="cf-question__label"><?= Html::encode((string)$document['title']) ?></legend>
    <div class="cf-consent__sheet" tabindex="0" role="region" aria-label="<?= Html::encode((string)$document['title']) ?>">
        <h3><?= Html::encode((string)$document['title']) ?></h3>
        <div class="cf-consent__body richtext-output"><?= (new HtmlSanitizer())->sanitize((string)$document['body_html']) ?></div>
        <span tabindex="0" data-cf-consent-end><?= Yii::t('ThiscoveryFormsModule.base', 'End of the information sheet') ?></span>
    </div>
    <?php foreach ($items as $item): ?>
        <div class="cf-consent__item">
            <p id="cf-consent-form-<?= Html::encode((string)$item['code']) ?>"><?= Html::encode((string)$item['label']) ?></p>
            <?php if (($item['input'] ?? '') === 'checkbox'): ?>
                <?= Html::checkbox('consent[form][items][' . $item['code'] . ']', false, [
                    'value' => 'yes',
                    'uncheck' => 'no',
                    'aria-describedby' => 'cf-consent-form-' . $item['code'],
                ]) ?>
            <?php else: ?>
                <label><input type="radio" name="consent[form][items][<?= Html::encode((string)$item['code']) ?>]" value="yes"> <?= Yii::t('ThiscoveryFormsModule.base', 'Yes') ?></label>
                <label><input type="radio" name="consent[form][items][<?= Html::encode((string)$item['code']) ?>]" value="no"> <?= Yii::t('ThiscoveryFormsModule.base', 'No') ?></label>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <label for="cf-consent-name-form"><?= Yii::t('ThiscoveryFormsModule.base', 'Type your name') ?></label>
    <input id="cf-consent-name-form" class="form-control" name="consent[form][signature_name]" autocomplete="name">
    <input type="hidden" name="consent[form][signature_method]" value="typed">
    <input type="hidden" name="consent[form][scrolled_to_end]" value="0" data-cf-consent-scrolled>
</fieldset>
