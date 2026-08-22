<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\commands\CronController;
use humhub\commands\IntegrityController;
use humhub\modules\admin\widgets\AdminMenu;
use humhub\modules\thiscoveryForms\Events;
use humhub\modules\thiscoveryForms\Module;
use humhub\modules\space\widgets\Menu;
use humhub\modules\user\models\User;
use humhub\widgets\TopMenu;

return [
    'id' => 'thiscovery-forms',
    'class' => Module::class,
    'namespace' => 'humhub\modules\thiscoveryForms',
    'events' => [
        ['class' => Menu::class, 'event' => Menu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
        ['class' => TopMenu::class, 'event' => TopMenu::EVENT_INIT, 'callback' => [Events::class, 'onTopMenuInit']],
        ['class' => AdminMenu::class, 'event' => AdminMenu::EVENT_INIT, 'callback' => [Events::class, 'onAdminMenuInit']],
        ['class' => User::class, 'event' => User::EVENT_BEFORE_DELETE, 'callback' => [Events::class, 'onUserDelete']],
        ['class' => IntegrityController::class, 'event' => IntegrityController::EVENT_ON_RUN, 'callback' => [Events::class, 'onIntegrityCheck']],
        ['class' => CronController::class, 'event' => CronController::EVENT_ON_HOURLY_RUN, 'callback' => [Events::class, 'onHourlyCron']],
    ],
    'urlManagerRules' => [
        'thiscovery-forms/global/view/<id:\d+>' => 'thiscovery-forms/global/view',
        'thiscovery-forms/global/edit/<id:\d+>' => 'thiscovery-forms/global/edit',
        'thiscovery-forms/global/project/<id:\d+>/<answerId:\d+>' => 'thiscovery-forms/global/project',
        'thiscovery-forms/global/catalogue/<id:\d+>' => 'thiscovery-forms/global/catalogue',
        'thiscovery-forms/admin/help' => 'thiscovery-forms/admin/help',
        'thiscovery-forms/admin/help/<page:[\\w\\-]+>' => 'thiscovery-forms/admin/help',
    ],
];
