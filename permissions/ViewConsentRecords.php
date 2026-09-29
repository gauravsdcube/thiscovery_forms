<?php

namespace humhub\modules\thiscoveryForms\permissions;

use humhub\libs\BasePermission;
use humhub\modules\space\models\Space;
use Yii;

/**
 * Open the consent list and a certificate. Space admins have it. Manage-form does not include it.
 */
class ViewConsentRecords extends BasePermission
{
    protected $moduleId = 'thiscovery-forms';

    public $defaultAllowedGroups = [
        Space::USERGROUP_OWNER,
        Space::USERGROUP_ADMIN,
    ];

    protected $fixedGroups = [
        Space::USERGROUP_OWNER,
        Space::USERGROUP_ADMIN,
        Space::USERGROUP_USER,
        Space::USERGROUP_GUEST,
    ];

    public function getTitle()
    {
        return Yii::t('ThiscoveryFormsModule.base', 'View consent records');
    }

    public function getDescription()
    {
        return Yii::t('ThiscoveryFormsModule.base', 'Allows opening consent records and certificates. It does not allow editing the consent text.');
    }
}
