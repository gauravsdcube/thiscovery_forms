<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\widgets\bootstrap\Button;

/** @var $dataProvider */
/** @var $canCreate */

echo $this->render('@thiscovery-forms/views/form/index', [
    'dataProvider' => $dataProvider,
    'contentContainer' => null,
    'canCreate' => $canCreate,
]);
