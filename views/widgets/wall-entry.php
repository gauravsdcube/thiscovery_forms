<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\widgets\bootstrap\Badge;
use humhub\widgets\bootstrap\Button;
use yii\helpers\Html;

/** @var CustomForm $formModel */

$status = CustomForm::getStatusLabels()[$formModel->status] ?? '';
$fieldCount = count($formModel->fields);
?>
<div class="thiscovery-forms-wall-card">
    <?php if ($formModel->description): ?>
        <div class="text-muted"><?= nl2br(Html::encode(mb_strimwidth($formModel->description, 0, 220, '…'))) ?></div>
    <?php endif; ?>

    <div class="cf-meta">
        <?php if ($formModel->isClosed()): ?>
            <?= Badge::danger($status) ?>
        <?php elseif ($formModel->isDraft()): ?>
            <?= Badge::warning($status) ?>
        <?php else: ?>
            <?= Badge::success($status) ?>
        <?php endif; ?>
        <?php if ($fieldCount): ?>
            <span class="text-muted small"><?= Yii::t('ThiscoveryFormsModule.base', '{n} fields', ['n' => $fieldCount]) ?></span>
        <?php endif; ?>
        <?= Button::primary(Yii::t('ThiscoveryFormsModule.base', 'Open form'))
            ->link(Url::toView($formModel))
            ->sm() ?>
    </div>
</div>
