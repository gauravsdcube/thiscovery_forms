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
        if ($contentContainer instanceof Space) {
            return [
                new CreateForm(),
                new ManageForm(),
                new AnswerForm(),
                new ViewAnswers(),
            ];
        }

        if ($contentContainer === null) {
            return [
                new CreateGlobalForm(),
                new ManageGlobalForm(),
                new AnswerGlobalForm(),
                new ViewGlobalAnswers(),
            ];
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
        return Url::to(['/thiscovery-forms/admin/index']);
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
