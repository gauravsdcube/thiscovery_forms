<?php

use yii\helpers\Html;

/** @var string|null $text Plain guidance (encoded). */
/** @var string|null $html Optional HTML guidance. */

$text = trim((string)($text ?? ''));
$html = $html ?? null;
if ($html === null && $text === '') {
    return;
}
$id = 'cf-guide-' . str_replace('.', '', uniqid('', true));
$label = Yii::t('ThiscoveryFormsModule.base', 'Guidance');
?>
<div class="cf-guide">
    <button type="button" class="cf-guide__toggle" data-cf-guide-toggle
            aria-expanded="false" aria-controls="<?= Html::encode($id) ?>"
            title="<?= Html::encode($label) ?>" aria-label="<?= Html::encode($label) ?>">
        <i class="fa fa-question-circle" aria-hidden="true"></i>
    </button>
    <div id="<?= Html::encode($id) ?>" class="cf-guide__panel" hidden>
        <?php if ($html !== null): ?>
            <?= $html ?>
        <?php else: ?>
            <p><?= Html::encode($text) ?></p>
        <?php endif; ?>
    </div>
</div>
