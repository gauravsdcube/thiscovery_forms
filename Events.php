<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 * @package humhub\modules\thiscoveryForms
 */

namespace humhub\modules\thiscoveryForms;

use humhub\commands\IntegrityController;
use humhub\helpers\ControllerHelper;
use humhub\modules\admin\permissions\ManageModules;
use humhub\modules\admin\widgets\AdminMenu;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\permissions\CreateGlobalForm;
use humhub\modules\thiscoveryForms\permissions\ManageGlobalForm;
use humhub\modules\space\models\Space;
use humhub\modules\space\widgets\Menu;
use humhub\modules\ui\menu\MenuLink;
use humhub\modules\user\models\User;
use humhub\widgets\TopMenu;
use Yii;
use yii\base\Event;

class Events
{
    public static function onSpaceMenuInit($event): void
    {
        /** @var Menu $menu */
        $menu = $event->sender;
        /** @var Space|null $space */
        $space = $menu->space ?? null;

        if ($space === null || !$space->moduleManager->isEnabled('thiscovery-forms')) {
            return;
        }

        $module = Yii::$app->getModule('thiscovery-forms');

        foreach (CustomForm::findShownInMenu($space) as $form) {
            if (!$form->content->canView()) {
                continue;
            }
            $menu->addEntry(new MenuLink([
                'label' => $form->title,
                'url' => Url::toView($form),
                'icon' => 'wpforms',
                'htmlOptions' => $form->fillHtmlOptions(),
                'isActive' => ControllerHelper::isActivePath('thiscovery-forms', 'form', 'view')
                    && (int)Yii::$app->request->get('id') === (int)$form->id,
                'sortOrder' => 400,
            ]));
        }

        $probe = new CustomForm($space);
        if ($probe->canCreate() || $probe->canManage() || CustomForm::find()->contentContainer($space)->count() > 0) {
            $menu->addEntry(new MenuLink([
                'label' => Yii::t('ThiscoveryFormsModule.base', 'Forms'),
                'url' => Url::toIndex($space),
                'icon' => 'wpforms',
                'isActive' => ControllerHelper::isActivePath('thiscovery-forms', 'form')
                    && !Yii::$app->request->get('id'),
                'sortOrder' => 399,
            ]));
        }
    }

    public static function onTopMenuInit($event): void
    {
        /** @var TopMenu $menu */
        $menu = $event->sender;
        $module = Yii::$app->getModule('thiscovery-forms');
        if (!$module || !$module->getIsEnabled()) {
            return;
        }

        foreach (CustomForm::findShownInMenu(null) as $form) {
            if (!$form->content->canView()) {
                continue;
            }
            $menu->addEntry(new MenuLink([
                'label' => $form->title,
                'id' => 'thiscovery-form-global-' . $form->id,
                'url' => Url::toView($form),
                'icon' => 'wpforms',
                'htmlOptions' => $form->fillHtmlOptions(),
                'isActive' => ControllerHelper::isActivePath('thiscovery-forms', 'global', 'view')
                    && (int)Yii::$app->request->get('id') === (int)$form->id,
                'sortOrder' => 400,
            ]));
        }
    }

    public static function onAdminMenuInit($event): void
    {
        if (Yii::$app->user->isGuest) {
            return;
        }

        // Don't put permission checks only in isVisible — same pattern as Homepage module.
        $allowed = Yii::$app->user->isAdmin()
            || Yii::$app->user->can(ManageModules::class)
            || Yii::$app->user->can(ManageGlobalForm::class)
            || Yii::$app->user->can(CreateGlobalForm::class);

        if (!$allowed) {
            return;
        }

        /** @var Module $module */
        $module = Yii::$app->getModule('thiscovery-forms');
        if (!$module) {
            return;
        }

        /** @var AdminMenu $menu */
        $menu = $event->sender;
        $menu->addEntry(new MenuLink([
            'label' => Yii::t('ThiscoveryFormsModule.base', 'Thiscovery Forms'),
            'id' => 'thiscovery-forms-admin',
            'icon' => 'wpforms',
            'url' => ['/thiscovery-forms/admin/index'],
            'sortOrder' => 550,
            'isActive' => ControllerHelper::isActivePath('thiscovery-forms', 'admin')
                || ControllerHelper::isActivePath('thiscovery-forms', 'global'),
            'isVisible' => true,
        ]));
    }

    public static function onUserDelete(Event $event): void
    {
        /** @var User $user */
        $user = $event->sender;
        foreach (FormAnswer::find()->where(['created_by' => $user->id])->each(100) as $answer) {
            $answer->delete();
        }
    }

    public static function onHourlyCron(): void
    {
        try {
            (new \humhub\modules\thiscoveryForms\services\WaveService())->dispatchDueInvites();
        } catch (\Throwable $e) {
            Yii::error('Thiscovery Forms hourly cron failed: ' . $e->getMessage(), 'thiscovery-forms');
        }
        try {
            (new \humhub\modules\thiscoveryForms\services\EmailTemplateService())->dispatchDueReminders();
        } catch (\Throwable $e) {
            Yii::error('Thiscovery Forms reminder cron failed: ' . $e->getMessage(), 'thiscovery-forms');
        }
    }

    public static function onIntegrityCheck($event): void
    {
        /** @var IntegrityController $integrity */
        $integrity = $event->sender;
        $integrity->showTestHeadline('Thiscovery Forms - Answers (' . FormAnswer::find()->count() . ' entries)');

        foreach (FormAnswer::find()->joinWith('form')->each(100) as $answer) {
            if ($answer->form === null) {
                if ($integrity->showFix('Delete orphan answer ' . $answer->id)) {
                    $answer->delete();
                }
            }
        }
    }
}
