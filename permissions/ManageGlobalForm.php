<?php

namespace humhub\modules\thiscoveryForms\permissions;

use humhub\modules\admin\components\BaseAdminPermission;
use Yii;

class ManageGlobalForm extends BaseAdminPermission
{
    protected $id = 'custom_forms_manage_global';
    protected $moduleId = 'thiscovery-forms';

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->title = Yii::t('ThiscoveryFormsModule.base', 'Manage global forms');
        $this->description = Yii::t('ThiscoveryFormsModule.base', 'Allows editing and deleting network-level forms.');
    }
}
