<?php

use yii\helpers\Html;

/** @var string $title */
/** @var string $hint */
/** @var bool $open */
/** @var string $body */
/** @var array $attrs */
$attrs = array_merge([
    'class' => 'cf-set-acc',
], $attrs ?? []);
if (!empty($open)) {
    $attrs['open'] = true;
}
?>
<?= Html::beginTag('details', $attrs) ?>
    <summary>
        <span class="cf-set-acc__title"><?= Html::encode($title) ?></span>
        <?php if (!empty($hint)): ?>
            <span class="cf-set-acc__summary"><?= Html::encode($hint) ?></span>
        <?php endif; ?>
    </summary>
    <div class="cf-set-acc__body">
        <?= $body ?>
    </div>
</details>
