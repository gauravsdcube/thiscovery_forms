<?php

use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryForms\models\FormField;
use yii\helpers\Html;

/** @var FormField $field */
/** @var FormAnswerField $answerField */

$geo = '';
$decoded = json_decode((string)$answerField->value, true);
$n = 0;
if (is_array($decoded) && ($decoded['type'] ?? '') === 'FeatureCollection') {
    $geo = json_encode($decoded, JSON_UNESCAPED_UNICODE);
    $n = is_array($decoded['features'] ?? null) ? count($decoded['features']) : 0;
}
?>
<?php if ($geo !== '' && class_exists(\humhub\modules\thiscoveryMapping\widgets\MapWidget::class)
    && \humhub\modules\thiscoveryForms\helpers\MappingAvailability::isEnabled()): ?>
    <div class="cf-answer-map">
        <?= \humhub\modules\thiscoveryMapping\widgets\MapWidget::widget([
            'mode' => 'form',
            'readOnly' => true,
            'inputValue' => $geo,
            'height' => 280,
            'formConfig' => $field->getMapConfig(),
        ]) ?>
        <p class="cf-answer-map__count">
            <?= Html::encode(Yii::t('ThiscoveryFormsModule.base', '{n,plural,=1{1 map drawing} other{# map drawings}}', ['n' => $n])) ?>
        </p>
    </div>
<?php else: ?>
    <?= $answerField->getAnswerHtml() ?>
<?php endif; ?>
