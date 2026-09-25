<?php

use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAccessToken;
use yii\helpers\Html;

/** @var CustomForm $formModel */
/** @var FormAccessToken[] $tokens */
/** @var array $settings */

ThiscoveryFormsAsset::register($this);
$this->title = Yii::t('ThiscoveryFormsModule.base', 'Invitation links');
?>
<div class="panel panel-default">
    <div class="panel-heading"><?= Yii::t('ThiscoveryFormsModule.base', 'Unique invitation links') ?></div>
    <div class="panel-body">
        <p class="help-block">
            <?= Yii::t('ThiscoveryFormsModule.base', 'Each link is signed for this survey. One-time links can only complete once. Raw tokens are shown only when you generate them — download the CSV immediately.') ?>
        </p>
        <?= Html::beginForm(Url::toAccessTokens($formModel), 'post', ['class' => 'mb-4']) ?>
            <div class="row g-2">
                <div class="col-md-2">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'How many') ?></label>
                    <?= Html::input('number', 'count', 10, ['class' => 'form-control', 'min' => 1, 'max' => 200]) ?>
                </div>
                <div class="col-md-3">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Label') ?></label>
                    <?= Html::textInput('label', '', ['class' => 'form-control']) ?>
                </div>
                <div class="col-md-2">
                    <label class="cf-label"><?= Yii::t('ThiscoveryFormsModule.base', 'Expires in days') ?></label>
                    <?= Html::input('number', 'expires_days', '', [
                        'class' => 'form-control',
                        'min' => 0,
                        'max' => 3650,
                        'placeholder' => Yii::t('ThiscoveryFormsModule.base', 'Never'),
                    ]) ?>
                </div>
                <div class="col-md-2">
                    <label class="cf-label">&nbsp;</label>
                    <label class="checkbox">
                        <?= Html::checkbox('one_time', true, ['value' => '1', 'uncheck' => '0']) ?>
                        <?= Yii::t('ThiscoveryFormsModule.base', 'One-time links') ?>
                    </label>
                </div>
                <div class="col-md-3">
                    <label class="cf-label">&nbsp;</label>
                    <button class="btn btn-primary" type="submit"><?= Yii::t('ThiscoveryFormsModule.base', 'Generate and download CSV') ?></button>
                </div>
            </div>
            <p class="help-block mt-2 mb-0">
                <?= Yii::t('ThiscoveryFormsModule.base', 'Leave expiry empty for links that do not expire. Expired links are rejected when opening or submitting the survey.') ?>
            </p>
        <?= Html::endForm() ?>
        <table class="table">
            <thead>
            <tr>
                <th><?= Yii::t('ThiscoveryFormsModule.base', 'Hint') ?></th>
                <th><?= Yii::t('ThiscoveryFormsModule.base', 'Uses') ?></th>
                <th><?= Yii::t('ThiscoveryFormsModule.base', 'Expires') ?></th>
                <th><?= Yii::t('ThiscoveryFormsModule.base', 'Created') ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($tokens as $token): ?>
                <tr>
                    <td><?= Html::encode($token->token_hint) ?>… <?= Html::encode((string)$token->label) ?></td>
                    <td><?= (int)$token->use_count ?> / <?= (int)$token->max_uses ?></td>
                    <td>
                        <?php if ($token->expires_at): ?>
                            <?= Yii::$app->formatter->asDatetime($token->expires_at, 'short') ?>
                            <?php if (!$token->isUsable() && strtotime((string)$token->expires_at) < time()): ?>
                                <span class="text-danger">(<?= Yii::t('ThiscoveryFormsModule.base', 'Expired') ?>)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?= Yii::t('ThiscoveryFormsModule.base', 'Never') ?>
                        <?php endif; ?>
                    </td>
                    <td><?= Yii::$app->formatter->asDatetime($token->created_at, 'short') ?></td>
                    <td>
                        <?= Html::beginForm(Url::toAccessTokens($formModel), 'post') ?>
                            <?= Html::hiddenInput('revoke', $token->id) ?>
                            <button class="btn btn-sm btn-danger" type="submit"><?= Yii::t('ThiscoveryFormsModule.base', 'Remove') ?></button>
                        <?= Html::endForm() ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$tokens): ?>
                <tr><td colspan="5"><?= Yii::t('ThiscoveryFormsModule.base', 'No invitation links yet.') ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <a href="<?= Html::encode(Url::toEdit($formModel) . (str_contains(Url::toEdit($formModel), '?') ? '&' : '?') . 'tab=integrity') ?>"><?= Yii::t('ThiscoveryFormsModule.base', 'Back to Response integrity') ?></a>
    </div>
</div>
