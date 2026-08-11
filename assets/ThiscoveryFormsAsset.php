<?php

namespace humhub\modules\thiscoveryForms\assets;

use yii\web\AssetBundle;

class ThiscoveryFormsAsset extends AssetBundle
{
    public $sourcePath = '@thiscovery-forms/resources';

    public $js = [
        'js/humhub.thiscoveryForms.js',
    ];

    public $css = [
        'css/thiscovery-forms.css',
    ];

    public $depends = [
        'humhub\assets\CoreApiAsset',
    ];

    public $publishOptions = [
        'forceCopy' => true,
    ];
}
