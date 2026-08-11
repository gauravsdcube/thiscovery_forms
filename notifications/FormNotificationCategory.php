<?php

namespace humhub\modules\thiscoveryForms\notifications;

use humhub\modules\notification\components\NotificationCategory;
use Yii;

class FormNotificationCategory extends NotificationCategory
{
    public $id = 'thiscoveryForms';

    public function getTitle()
    {
        return Yii::t('ThiscoveryFormsModule.base', 'Thiscovery Forms');
    }

    public function getDescription()
    {
        return Yii::t('ThiscoveryFormsModule.base', 'Receive notifications when someone submits a form.');
    }
}
