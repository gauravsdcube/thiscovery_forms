<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\widgets\bootstrap\Button;

/** @var $dataProvider */
/** @var $canCreate */
/** @var $templates */
/** @var $canConfigure */

echo $this->render('@thiscovery-forms/views/form/index', [
    'dataProvider' => $dataProvider,
    'contentContainer' => null,
    'canCreate' => $canCreate,
    'templates' => $templates ?? [],
    'canConfigure' => $canConfigure ?? false,
]);
