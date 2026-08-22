<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\FormWave;
use yii\helpers\Html;

/** @var FormWave[] $waves */
/** @var string $statusUrl */
/** @var bool $canManage */

$canManage = $canManage ?? true;
$waves = $waves ?? [];
?>
<?php if (!$waves): ?>
    <p class="text-muted"><?= Yii::t('ThiscoveryFormsModule.base', 'No waves yet.') ?></p>
<?php endif; ?>
<?php foreach ($waves as $wave): ?>
    <?php /** @var FormWave $wave */ ?>
    <div class="cf-programme-card">
        <div>
            <strong><?= Html::encode($wave->getDisplayTitle()) ?></strong>
            <span class="cf-answer-card__chip"><?= Html::encode(FormWave::getStatusLabels()[$wave->status] ?? $wave->status) ?></span>
            <?php if ($wave->opens_at || $wave->closes_at): ?>
                <div class="text-muted small">
                    <?= Html::encode(trim(($wave->opens_at ?: '—') . ' → ' . ($wave->closes_at ?: '—'))) ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($canManage): ?>
            <div>
                <?php if ($wave->status !== FormWave::STATUS_OPEN): ?>
                    <?= Html::beginForm($statusUrl, 'post', ['class' => 'd-inline']) ?>
                        <?= Html::hiddenInput('wave_id', $wave->id) ?>
                        <?= Html::hiddenInput('status', FormWave::STATUS_OPEN) ?>
                        <button type="submit" class="btn btn-sm btn-primary"><?= Yii::t('ThiscoveryFormsModule.base', 'Open') ?></button>
                    <?= Html::endForm() ?>
                <?php endif; ?>
                <?php if ($wave->status !== FormWave::STATUS_CLOSED): ?>
                    <?= Html::beginForm($statusUrl, 'post', ['class' => 'd-inline']) ?>
                        <?= Html::hiddenInput('wave_id', $wave->id) ?>
                        <?= Html::hiddenInput('status', FormWave::STATUS_CLOSED) ?>
                        <button type="submit" class="btn btn-sm btn-light"><?= Yii::t('ThiscoveryFormsModule.base', 'Close') ?></button>
                    <?= Html::endForm() ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
