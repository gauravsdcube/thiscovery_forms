<?php

use humhub\modules\thiscoveryForms\services\ConsentService;
use humhub\modules\thiscoveryForms\services\HtmlSanitizer;
use yii\helpers\Html;

/** @var \humhub\modules\thiscoveryForms\models\CustomForm $formModel */
/** @var array $document */

$consent = new ConsentService();
// Render exactly what the record will hash, in the participant's language (V3-27).
$shown = $consent->presentation($formModel, $document);
$items = $shown['items'];
$signature = $consent->effectiveSignature($formModel, 'typed');
?>
<fieldset class="cf-consent" data-cf-consent="form">
    <legend class="cf-question__label"><?= Html::encode((string)$document['title']) ?></legend>
    <div class="cf-consent__sheet" tabindex="0" role="region" aria-label="<?= Html::encode((string)$document['title']) ?>">
        <h3><?= Html::encode((string)$document['title']) ?></h3>
        <div class="cf-consent__body richtext-output"><?= (new HtmlSanitizer())->sanitize($shown['body']) ?></div>
        <span tabindex="0" data-cf-consent-end><?= Yii::t('ThiscoveryFormsModule.base', 'End of the information sheet') ?></span>
    </div>
    <?php foreach ($items as $item): ?>
        <?= $this->render('_consent_item', ['item' => $item, 'nameBase' => 'consent[form]', 'idBase' => 'cf-consent-form']) ?>
    <?php endforeach; ?>
    <?php if ($signature === 'checkbox'): ?>
        <label><input type="checkbox" name="consent[form][attestation]" value="1"> <?= Yii::t('ThiscoveryFormsModule.base', 'I confirm this is my decision.') ?></label>
    <?php else: ?>
        <label for="cf-consent-name-form"><?= Yii::t('ThiscoveryFormsModule.base', 'Type your name') ?></label>
        <input id="cf-consent-name-form" class="form-control" name="consent[form][signature_name]" autocomplete="name">
    <?php endif; ?>
    <input type="hidden" name="consent[form][signature_method]" value="<?= Html::encode($signature) ?>">
    <input type="hidden" name="consent[form][scrolled_to_end]" value="0" data-cf-consent-scrolled>
    <input type="hidden" name="consent[form][shown_hash]" value="<?= Html::encode($shown['hash']) ?>">
</fieldset>
