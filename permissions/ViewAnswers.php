<?php

namespace humhub\modules\thiscoveryForms\permissions;

use humhub\libs\BasePermission;
use humhub\modules\space\models\Space;
use Yii;

class ViewAnswers extends BasePermission
{
    protected $moduleId = 'thiscovery-forms';

    public $defaultAllowedGroups = [
        Space::USERGROUP_OWNER,
        Space::USERGROUP_ADMIN,
        Space::USERGROUP_MODERATOR,
    ];

    protected $fixedGroups = [
        Space::USERGROUP_GUEST,
    ];

    public function getTitle()
    {
        return Yii::t('ThiscoveryFormsModule.base', 'View form answers');
    }

    public function getDescription()
    {
        return Yii::t('ThiscoveryFormsModule.base', 'Allows viewing submitted answers when the form grants permission-based access.');
    }
}
