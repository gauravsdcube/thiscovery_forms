<?php

use yii\helpers\Html;

/** @var array $results */

$total = (int)($results['total'] ?? 0);
$options = $results['options'] ?? [];
$question = (string)($results['label'] ?? '');
?>
<div class="cf-poll-results" data-cf-poll-results>
    <?php if ($question !== ''): ?>
        <div class="cf-poll-results__label"><?= Html::encode($question) ?></div>
    <?php endif; ?>
    <?php if ($total === 0 || !$options): ?>
        <p class="text-muted mb-0"><?= Yii::t('ThiscoveryFormsModule.base', 'No votes yet.') ?></p>
    <?php else: ?>
        <ul class="cf-poll-results__list">
            <?php foreach ($options as $option): ?>
                <?php
                $count = (int)($option['count'] ?? 0);
                $pct = $total > 0 ? round(100 * $count / $total) : 0;
                ?>
                <li class="cf-poll-results__row">
                    <div class="cf-poll-results__row-head">
                        <span><?= Html::encode((string)($option['label'] ?? '')) ?></span>
                        <span class="text-muted"><?= $pct ?>% (<?= $count ?>)</span>
                    </div>
                    <div class="cf-poll-results__bar" aria-hidden="true">
                        <span style="width: <?= (int)$pct ?>%"></span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="cf-poll-results__total text-muted">
            <?= Yii::t('ThiscoveryFormsModule.base', '{n,plural,=1{1 vote} other{# votes}}', ['n' => $total]) ?>
        </div>
    <?php endif; ?>
</div>
