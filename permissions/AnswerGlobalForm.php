<?php

namespace humhub\modules\thiscoveryForms\permissions;

use humhub\modules\admin\components\BaseAdminPermission;
use Yii;

class AnswerGlobalForm extends BaseAdminPermission
{
    protected $id = 'custom_forms_answer_global';
    protected $moduleId = 'thiscovery-forms';
    protected $defaultState = self::STATE_ALLOW;

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->title = Yii::t('ThiscoveryFormsModule.base', 'Answer global forms');
        $this->description = Yii::t('ThiscoveryFormsModule.base', 'Allows submitting answers to network-level forms.');
    }
}
