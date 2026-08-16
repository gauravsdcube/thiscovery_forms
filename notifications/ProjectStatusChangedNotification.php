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
class ProjectStatusChangedNotification extends BaseNotification
{
    public $moduleId = 'thiscovery-forms';

    public string $status = '';

    public string $moderatorComment = '';

    public function category()
    {
        return new FormNotificationCategory();
    }

    public function html()
    {
        $title = Html::encode($this->source->form->title ?? '');
        if ($this->status === FormAnswer::WORKFLOW_PUBLISHED) {
            return Yii::t('ThiscoveryFormsModule.base', '{displayName} published your project in "{title}".', [
                'displayName' => Html::tag('strong', Html::encode($this->originator->displayName)),
                'title' => $title,
            ]);
        }

        $html = Yii::t('ThiscoveryFormsModule.base', '{displayName} requested changes to your project in "{title}".', [
            'displayName' => Html::tag('strong', Html::encode($this->originator->displayName)),
            'title' => $title,
        ]);
        if (trim($this->moderatorComment) !== '') {
            $html .= ' ' . Html::encode($this->moderatorComment);
        }
        return $html;
    }

    public function getMailSubject()
    {
        if ($this->status === FormAnswer::WORKFLOW_PUBLISHED) {
            return Yii::t('ThiscoveryFormsModule.base', 'Your project was published');
        }
        return Yii::t('ThiscoveryFormsModule.base', 'Changes requested on your project');
    }

    public function getUrl()
    {
        return Url::toProject($this->source->form, $this->source);
    }
}
