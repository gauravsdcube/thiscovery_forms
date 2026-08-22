<?php

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\widgets\bootstrap\Button;

/** @var $dataProvider */
/** @var $canCreate */
/** @var $templates */
/** @var $canConfigure */

echo $this->render('@thiscovery-forms/views/form/index', [
    'dataProvider' => $dataProvider,
    'filters' => $filters ?? [],
    'contentContainer' => null,
    'canCreate' => $canCreate,
    'templates' => $templates ?? [],
    'canConfigure' => $canConfigure ?? false,
    'folderBrowse' => $folderBrowse ?? [],
    'canManagePanels' => $canManagePanels ?? false,
    'canViewHelp' => $canViewHelp ?? false,
]);
