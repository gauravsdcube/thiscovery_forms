<?php

namespace humhub\modules\thiscoveryForms\permissions;

use humhub\modules\admin\components\BaseAdminPermission;
use Yii;

class ViewGlobalAnswers extends BaseAdminPermission
{
    protected $id = 'custom_forms_view_global_answers';
    protected $moduleId = 'thiscovery-forms';

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->title = Yii::t('ThiscoveryFormsModule.base', 'View global form answers');
        $this->description = Yii::t('ThiscoveryFormsModule.base', 'Allows viewing answers to network-level forms when permission-based access is enabled.');
    }
}
