<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 * @package humhub\modules\thiscoveryForms
 */

namespace humhub\modules\thiscoveryForms;

use humhub\components\console\Application as ConsoleApplication;
use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\modules\content\components\ContentContainerModule;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\permissions\AnswerForm;
use humhub\modules\thiscoveryForms\permissions\AnswerGlobalForm;
use humhub\modules\thiscoveryForms\permissions\CreateForm;
use humhub\modules\thiscoveryForms\permissions\CreateGlobalForm;
use humhub\modules\thiscoveryForms\permissions\ManageForm;
use humhub\modules\thiscoveryForms\permissions\ManageGlobalForm;
use humhub\modules\thiscoveryForms\permissions\ViewAnswers;
use humhub\modules\thiscoveryForms\permissions\ViewGlobalAnswers;
use humhub\modules\space\models\Space;
use Yii;
use yii\helpers\Url;

class Module extends ContentContainerModule
{
    public const SETTING_ENABLED_KINDS = 'enabled_kinds';
    public const SETTING_WAVES_FOR_SURVEYS = 'waves_for_surveys';
    public const SETTING_WAVE_SCOPE = 'wave_scope';
    public const SETTING_INTEGRITY = 'integrity';
    public const SETTING_DISPLAY = 'display';

    public const WAVE_SCOPE_SURVEY = 'survey';
    public const WAVE_SCOPE_PANEL = 'panel';

    public $resourcesPath = 'resources';
    public $icon = 'fa-wpforms';

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return Yii::t('ThiscoveryFormsModule.base', 'Thiscovery Forms');
    }

    /**
     * @inheritdoc
     */
    public function getDescription()
    {
        return Yii::t('ThiscoveryFormsModule.base', 'Create and manage forms for spaces and the network. Members can fill forms from the stream or side menu.');
    }

    public function init()
    {
        parent::init();

        if (Yii::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'humhub\modules\thiscoveryForms\commands';
        }

        if (Yii::$app->hasModule('thiscovery-versioning')
            && class_exists(\humhub\modules\thiscoveryVersioning\Module::class)) {
            \humhub\modules\thiscoveryVersioning\Module::registerAdapter(
                new \humhub\modules\thiscoveryForms\services\FormVersionAdapter()
            );
        }
    }

    public function getContentContainerTypes()
    {
        return [Space::class];
    }

    public function getContentClasses(): array
    {
        return [CustomForm::class];
    }

    public function getPermissions($contentContainer = null)
    {
        $versionPerms = [];
        if (Yii::$app->hasModule('thiscovery-versioning')) {
            $vm = Yii::$app->getModule('thiscovery-versioning');
            if ($vm instanceof \humhub\modules\thiscoveryVersioning\Module) {
                $versionPerms = $vm->getBasePermissions();
            }
        }

        if ($contentContainer instanceof Space) {
            return array_merge([
                new CreateForm(),
                new ManageForm(),
                new AnswerForm(),
                new ViewAnswers(),
            ], $versionPerms);
        }

        if ($contentContainer === null) {
            return array_merge([
                new CreateGlobalForm(),
                new ManageGlobalForm(),
                new AnswerGlobalForm(),
                new ViewGlobalAnswers(),
            ], $versionPerms);
        }

        return [];
    }

    public function getContentContainerName(ContentContainerActiveRecord $container)
    {
        return Yii::t('ThiscoveryFormsModule.base', 'Forms');
    }

    public function getContentContainerDescription(ContentContainerActiveRecord $container)
    {
        return Yii::t('ThiscoveryFormsModule.base', 'Create forms for members to complete.');
    }

    public function getConfigUrl()
    {
        return Url::to(['/thiscovery-forms/admin/settings']);
    }

    /**
     * @return string[]
     */
    public function getEnabledKinds(): array
    {
        $all = array_keys(CustomForm::getKindLabels());
        $raw = $this->settings->get(self::SETTING_ENABLED_KINDS);
        if ($raw === null || $raw === '') {
            return $all;
        }
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return $all;
        }
        return array_values(array_intersect($decoded, $all));
    }

    public function isKindEnabled(string $kind): bool
    {
        return in_array($kind, $this->getEnabledKinds(), true);
    }

    public function wavesEnabledForSurveys(): bool
    {
        // Legacy site toggle removed — waves are controlled per form.
        return true;
    }

    public function getWaveScope(): string
    {
        // Legacy fallback when a form has no wave_scope of its own.
        $raw = (string)$this->settings->get(self::SETTING_WAVE_SCOPE, self::WAVE_SCOPE_SURVEY);
        return $raw === self::WAVE_SCOPE_PANEL ? self::WAVE_SCOPE_PANEL : self::WAVE_SCOPE_SURVEY;
    }

    /**
     * @deprecated Use CustomForm::wavesLiveOnPanel()
     */
    public function wavesLiveOnPanel(): bool
    {
        return $this->getWaveScope() === self::WAVE_SCOPE_PANEL;
    }

    public static function wavesEnabledForSurveysStatic(): bool
    {
        return true;
    }

    public static function waveScope(): string
    {
        $module = Yii::$app->getModule('thiscovery-forms');
        if (!$module instanceof self) {
            return self::WAVE_SCOPE_SURVEY;
        }
        return $module->getWaveScope();
    }

    /**
     * @deprecated Use CustomForm::wavesLiveOnPanel()
     */
    public static function wavesLiveOnPanelStatic(): bool
    {
        return self::waveScope() === self::WAVE_SCOPE_PANEL;
    }

    /**
     * @return string[]
     */
    public static function enabledKinds(): array
    {
        $module = Yii::$app->getModule('thiscovery-forms');
        if (!$module instanceof self) {
            return array_keys(CustomForm::getKindLabels());
        }
        return $module->getEnabledKinds();
    }

    public function disable()
    {
        foreach (CustomForm::find()->each(100) as $form) {
            $form->hardDelete();
        }
        parent::disable();
    }

    public function disableContentContainer(ContentContainerActiveRecord $container)
    {
        foreach (CustomForm::find()->contentContainer($container)->each(100) as $form) {
            $form->hardDelete();
        }
        parent::disableContentContainer($container);
    }

    public function getNotifications()
    {
        return [
            notifications\FormAnsweredNotification::class,
        ];
    }
}
