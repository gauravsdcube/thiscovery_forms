<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\helpers;

use Yii;

/**
 * Soft dependency on Thiscovery Mapping for the Map question type.
 */
class MappingAvailability
{
    public static function isEnabled(): bool
    {
        if (!Yii::$app->hasModule('thiscovery-mapping')) {
            return false;
        }
        try {
            $module = Yii::$app->getModule('thiscovery-mapping');
        } catch (\Throwable $e) {
            return false;
        }
        return $module && $module->getIsEnabled();
    }
}
