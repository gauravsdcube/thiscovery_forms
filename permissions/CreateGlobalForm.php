<?php

namespace humhub\modules\thiscoveryForms\permissions;

use humhub\modules\admin\components\BaseAdminPermission;
use Yii;

class CreateGlobalForm extends BaseAdminPermission
{
    protected $id = 'custom_forms_create_global';
    protected $moduleId = 'thiscovery-forms';

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->title = Yii::t('ThiscoveryFormsModule.base', 'Create global forms');
        $this->description = Yii::t('ThiscoveryFormsModule.base', 'Allows creating network-level forms.');
    }
}
