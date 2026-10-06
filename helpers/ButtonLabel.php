<?php

namespace humhub\modules\thiscoveryForms\helpers;

use Yii;
use yii\helpers\Html;

/**
 * Participant buttons stay at least as wide as the English label.
 * A shorter translation is drawn over a hidden English copy.
 */
class ButtonLabel
{
    public static function html(string $english, ?string $translated = null): string
    {
        $english = trim($english);
        $translated = trim($translated ?? Yii::t('ThiscoveryFormsModule.base', $english));
        if ($translated === '' || $translated === $english) {
            return Html::encode($translated);
        }

        return '<span class="cf-btn-fit">'
            . '<span class="cf-btn-fit__label" data-cf-btn-label>' . Html::encode($translated) . '</span>'
            . '<span class="cf-btn-fit__min" aria-hidden="true">' . Html::encode($english) . '</span>'
            . '</span>';
    }
}
