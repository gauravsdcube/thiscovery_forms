<?php

use humhub\modules\thiscoveryForms\helpers\RichHtml;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormEmailTemplateI18n;
use humhub\modules\thiscoveryForms\models\FormI18n;
use humhub\modules\thiscoveryForms\services\ParticipantMessageCatalog;
use humhub\modules\thiscoveryForms\services\ParticipantMessages;
use humhub\modules\thiscoveryForms\services\QuotaService;
use yii\db\Query;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var string $lang */
/** @var string $targetDir */
/** @var FormI18n $formI18n */

$labels = ParticipantMessageCatalog::groupLabels();
$saved = ParticipantMessages::textsFor($formModel, $lang);
$site = ParticipantMessages::siteTexts($lang);
$thankYou = RichHtml::forTranslation(trim((string)($saved['author.thank_you'] ?? '')));
if ($thankYou === '') {
    $thankYou = RichHtml::forTranslation((string)$formI18n->thank_you_content);
}
?>

<h6 class="mt-4"><?= Yii::t('ThiscoveryFormsModule.base', 'Messages') ?></h6>
<p class="cf-hint text-muted">
    <?= Yii::t('ThiscoveryFormsModule.base', 'Buttons, checks, captcha, and the messages written for this form. Leave a line blank to use the site wording. Generate fills these from the site, and the first time a language is generated that wording is kept for every form. A line you edit is kept when you generate again.') ?>
</p>

<h6 class="mt-3"><?= Yii::t('ThiscoveryFormsModule.base', 'Written for this form') ?></h6>
<div class="cf-i18n-grid">
    <div>
        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Thank-you message') ?></label>
        <div class="form-control-plaintext richtext-output"><?= RichHtml::toHtml((string)$formModel->thank_you_content) ?></div>
    </div>
    <div>
        <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Translated thank-you message') ?></label>
        <textarea name="form_i18n[thank_you_content]" class="form-control" dir="<?= Html::encode($targetDir) ?>" rows="4"><?= Html::encode($thankYou) ?></textarea>
    </div>
    <?php foreach (ParticipantMessages::authorSources($formModel) as $key => $meta): ?>
            <div>
                <label class="cf-label"><?= Html::encode(Yii::t('ThiscoveryFormsModule.base', (string)$meta['label'])) ?></label>
                <?php if (!empty($meta['long'])): ?>
                    <div class="form-control-plaintext richtext-output"><?= RichHtml::toHtml((string)$meta['source']) ?></div>
                <?php else: ?>
                    <div class="form-control-plaintext"><?= nl2br(Html::encode((string)$meta['source'])) ?></div>
                <?php endif; ?>
            </div>
            <div>
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Translation') ?></label>
                <?php $posted = !empty($meta['long']) ? RichHtml::forTranslation((string)($saved[$key] ?? '')) : (string)($saved[$key] ?? ''); ?>
            <?php if (!empty($meta['long'])): ?>
                <textarea name="message_i18n[<?= Html::encode($key) ?>]" class="form-control" dir="<?= Html::encode($targetDir) ?>" rows="3"><?= Html::encode($posted) ?></textarea>
            <?php else: ?>
                <input type="text" name="message_i18n[<?= Html::encode($key) ?>]" class="form-control" dir="<?= Html::encode($targetDir) ?>" value="<?= Html::encode($posted) ?>">
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php
$quotaUnits = (new QuotaService())->translationUnits($formModel);
if ($quotaUnits):
?>
    <h6 class="mt-3"><?= Yii::t('ThiscoveryFormsModule.base', 'Quotas') ?></h6>
    <div class="cf-i18n-grid">
        <?php foreach ($quotaUnits as $unit): ?>
            <?php
            if (!preg_match('/^quota\.(\d+)\.(name|message)$/', (string)$unit['key'], $match)) {
                continue;
            }
            $quotaRow = (new Query())->from('{{%custom_form_quota_i18n}}')->where([
                'quota_id' => (int)$match[1],
                'language' => $lang,
            ])->one();
            $quotaValue = $quotaRow ? (string)($quotaRow[$match[2]] ?? '') : '';
            ?>
            <div>
                <label class="cf-label"><?= Html::encode(Yii::t('ThiscoveryFormsModule.base', $match[2] === 'name' ? 'Quota name' : 'Quota message')) ?></label>
                <div class="form-control-plaintext"><?= nl2br(Html::encode((string)$unit['source'])) ?></div>
            </div>
            <div>
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Translation') ?></label>
                <textarea name="quota_i18n[<?= (int)$match[1] ?>][<?= Html::encode($match[2]) ?>]" class="form-control" dir="<?= Html::encode($targetDir) ?>" rows="2"><?= Html::encode($quotaValue) ?></textarea>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php foreach (ParticipantMessages::emailTemplates($formModel) as $template): ?>
    <?php $email = FormEmailTemplateI18n::findOne(['template_id' => (int)$template->id, 'language' => $lang]) ?: new FormEmailTemplateI18n(); ?>
    <h6 class="mt-3"><?= Html::encode($template->title) ?></h6>
    <div class="cf-i18n-grid">
        <?php foreach (['subject' => 'Subject', 'header_html' => 'Email header', 'body_html' => 'Email body', 'footer_html' => 'Email footer'] as $part => $partLabel): ?>
            <div>
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', $partLabel) ?></label>
                <div class="form-control-plaintext<?= $part === 'subject' ? '' : ' richtext-output' ?>">
                    <?php if ($part === 'subject'): ?>
                        <?= Html::encode((string)$template->$part) ?>
                    <?php else: ?>
                        <?= RichHtml::toHtml((string)$template->$part) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Translation') ?></label>
                <textarea name="email_i18n[<?= (int)$template->id ?>][<?= Html::encode($part) ?>]" class="form-control" dir="<?= Html::encode($targetDir) ?>" rows="<?= $part === 'subject' ? 2 : 4 ?>"><?= Html::encode($part === 'subject' ? (string)$email->$part : RichHtml::forTranslation((string)$email->$part)) ?></textarea>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<?php foreach (ParticipantMessageCatalog::grouped() as $group => $entries): ?>
    <details class="mt-3">
        <summary><?= Html::encode(Yii::t('ThiscoveryFormsModule.base', $labels[$group] ?? $group)) ?></summary>
        <div class="cf-i18n-grid mt-2">
            <?php foreach ($entries as $key => $entry): ?>
                <div>
                    <div class="form-control-plaintext"><?= Html::encode((string)$entry['source']) ?></div>
                    <?php if (trim((string)($saved[$key] ?? '')) === '' && trim((string)($site[$key] ?? '')) !== ''): ?>
                        <p class="cf-hint text-muted mb-0"><?= Html::encode((string)$site[$key]) ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <input type="text" name="message_i18n[<?= Html::encode($key) ?>]" class="form-control" dir="<?= Html::encode($targetDir) ?>" value="<?= Html::encode((string)($saved[$key] ?? '')) ?>">
                </div>
            <?php endforeach; ?>
        </div>
    </details>
<?php endforeach; ?>
