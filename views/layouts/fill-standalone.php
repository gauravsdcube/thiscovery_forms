<?php

use humhub\assets\AppAsset;
use humhub\components\View;
use humhub\helpers\DeviceDetectorHelper;
use humhub\helpers\Html;

/* @var $this View */
/* @var $content string */

AppAsset::register($this);

$bodyClasses = DeviceDetectorHelper::getBodyClasses();
$bodyClasses[] = 'cf-fill-standalone';
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
    <title><?= strip_tags((string)$this->pageTitle) ?></title>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, viewport-fit=cover">
    <?php $this->head() ?>
    <?= $this->render('@humhub/views/layouts/head') ?>
</head>
<?= Html::beginTag('body', ['class' => $bodyClasses]) ?>
    <?php $this->beginBody() ?>
    <?= Html::script(<<<'JS'
document.addEventListener('click', function (e) {
    var a = e.target && e.target.closest ? e.target.closest('a') : null;
    if (a) {
        a.setAttribute('data-pjax-prevent', '1');
    }
}, true);
JS
    ) ?>
    <div class="cf-fill-standalone__wrap">
        <?= $content ?>
    </div>
    <?php $this->endBody() ?>
<?= Html::endTag('body') ?>
</html>
<?php $this->endPage() ?>
