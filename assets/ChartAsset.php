<?php

namespace humhub\modules\thiscoveryForms\assets;

use yii\web\AssetBundle;

class ChartAsset extends AssetBundle
{
    public $sourcePath = '@thiscovery-forms/resources';

    public $js = [
        'js/chart.min.js',
        'js/humhub.thiscoveryForms.dashboard.js',
    ];

    public $depends = [
        ThiscoveryFormsAsset::class,
    ];
}
