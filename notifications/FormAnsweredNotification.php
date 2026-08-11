<?php

namespace humhub\modules\thiscoveryForms\notifications;

use humhub\helpers\Html;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\notification\components\BaseNotification;
use humhub\modules\space\models\Space;
use Yii;

/**
 * @property FormAnswer $source
 */
class FormAnsweredNotification extends BaseNotification
{
    public $moduleId = 'thiscovery-forms';

    public function category()
    {
        return new FormNotificationCategory();
    }

    public function html()
    {
        $form = $this->source->form;
        $title = Html::encode($form->title);

        if (!$form->isGlobal() && $form->content->container instanceof Space) {
            return Yii::t('ThiscoveryFormsModule.base', '{displayName} submitted the form "{title}" in space {spaceName}.', [
                'displayName' => Html::tag('strong', Html::encode($this->originator->displayName)),
                'title' => $title,
                'spaceName' => Html::encode($form->content->container->displayName),
            ]);
        }

        return Yii::t('ThiscoveryFormsModule.base', '{displayName} submitted the form "{title}".', [
            'displayName' => Html::tag('strong', Html::encode($this->originator->displayName)),
            'title' => $title,
        ]);
    }

    public function getMailSubject()
    {
        return Yii::t('ThiscoveryFormsModule.base', 'New submission for "{title}"', [
            'title' => $this->source->form->title,
        ]);
    }

    public function getUrl()
    {
        return Url::toAnswers($this->source->form);
    }
}
