<?php

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\SecureRelease;
use yii\helpers\Html;

/** @var CustomForm $formModel */

$releases = [];
if ($formModel->id && Yii::$app->db->schema->getTableSchema(SecureRelease::tableName(), true)) {
    $releases = SecureRelease::find()->where(['form_id' => (int)$formModel->id, 'status' => SecureRelease::STATUS_ACTIVE])->all();
}
$route = static function (string $action, array $params = []) use ($formModel): array {
    return array_merge(['/thiscovery-forms/secure/' . $action, 'id' => (int)$formModel->id], $params);
};
?>
<?= Html::beginForm($route('lifetime'), 'post', ['id' => 'cf-secure-lifetime', 'class' => 'd-none', 'data-pjax-prevent' => true]) ?>
<?= Html::endForm() ?>
<?= Html::beginForm($route('prepare'), 'post', ['id' => 'cf-secure-prepare', 'class' => 'd-none', 'data-pjax-prevent' => true]) ?>
<?= Html::endForm() ?>
<?= Html::beginForm($route('upload'), 'post', [
    'id' => 'cf-secure-upload',
    'class' => 'd-none',
    'enctype' => 'multipart/form-data',
    'data-pjax-prevent' => true,
]) ?>
<?= Html::endForm() ?>
<?php foreach ($releases as $release): ?>
    <?php $rid = (int)$release->id; ?>
    <?= Html::beginForm($route('send-code', ['releaseId' => $rid]), 'post', ['id' => 'cf-secure-send-' . $rid, 'class' => 'd-none', 'data-pjax-prevent' => true]) ?>
    <?= Html::endForm() ?>
    <?= Html::beginForm($route('revoke-code', ['releaseId' => $rid]), 'post', ['id' => 'cf-secure-revoke-code-' . $rid, 'class' => 'd-none', 'data-pjax-prevent' => true]) ?>
    <?= Html::endForm() ?>
    <?= Html::beginForm($route('rotate-link', ['releaseId' => $rid]), 'post', ['id' => 'cf-secure-rotate-' . $rid, 'class' => 'd-none', 'data-pjax-prevent' => true]) ?>
    <?= Html::endForm() ?>
    <?= Html::beginForm($route('revoke', ['releaseId' => $rid]), 'post', ['id' => 'cf-secure-revoke-' . $rid, 'class' => 'd-none', 'data-pjax-prevent' => true]) ?>
    <?= Html::endForm() ?>
    <?= Html::beginForm($route('contact', ['releaseId' => $rid]), 'post', ['id' => 'cf-secure-contact-' . $rid, 'class' => 'd-none', 'data-pjax-prevent' => true]) ?>
    <?= Html::endForm() ?>
<?php endforeach; ?>
