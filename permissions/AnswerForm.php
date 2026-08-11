<?php

namespace humhub\modules\thiscoveryForms\permissions;

use humhub\libs\BasePermission;
use humhub\modules\space\models\Space;
use Yii;

class AnswerForm extends BasePermission
{
    protected $moduleId = 'thiscovery-forms';

    public $defaultAllowedGroups = [
        Space::USERGROUP_OWNER,
        Space::USERGROUP_ADMIN,
        Space::USERGROUP_MODERATOR,
        Space::USERGROUP_MEMBER,
    ];

    protected $fixedGroups = [
        Space::USERGROUP_GUEST,
    ];

    public function getTitle()
    {
        return Yii::t('ThiscoveryFormsModule.base', 'Answer forms');
    }

    public function getDescription()
    {
        return Yii::t('ThiscoveryFormsModule.base', 'Allows submitting answers to forms in this space.');
    }
}
