<?php

namespace humhub\modules\thiscoveryForms\notifications;

use humhub\helpers\Html;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\notification\components\BaseNotification;
use Yii;

/**
 * @property FormAnswer $source
 */
class ProjectApprovalNeededNotification extends BaseNotification
{
    public $moduleId = 'thiscovery-forms';

    public function category()
    {
        return new FormNotificationCategory();
    }

    public function html()
    {
        $title = Html::encode($this->source->form->title ?? '');
        return Yii::t('ThiscoveryFormsModule.base', '{displayName} submitted a project for "{title}" that needs your review.', [
            'displayName' => Html::tag('strong', Html::encode($this->originator->displayName)),
            'title' => $title,
        ]);
    }

    public function getMailSubject()
    {
        return Yii::t('ThiscoveryFormsModule.base', 'Project awaiting review: "{title}"', [
            'title' => $this->source->form->title ?? '',
        ]);
    }

    public function getUrl()
    {
        return Url::toProject($this->source->form, $this->source);
    }
}
