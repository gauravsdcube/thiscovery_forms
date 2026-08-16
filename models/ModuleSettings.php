<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\modules\thiscoveryForms\Module;
use Yii;
use yii\base\Model;

/**
 * Administration → Modules → Thiscovery Forms configuration.
 */
class ModuleSettings extends Model
{
    /** @var string[] */
    public $enabledKinds = [];

    public function init()
    {
        parent::init();
        $this->enabledKinds = Module::enabledKinds();
    }

    public function rules()
    {
        $kinds = array_keys(CustomForm::getKindLabels());
        return [
            [['enabledKinds'], 'required', 'message' => Yii::t(
                'ThiscoveryFormsModule.base',
                'Enable at least one form type.'
            )],
            [['enabledKinds'], 'each', 'rule' => ['in', 'range' => $kinds]],
        ];
    }

    public function attributeLabels()
    {
        return [
            'enabledKinds' => Yii::t('ThiscoveryFormsModule.base', 'Enabled form types'),
        ];
    }

    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $kinds = array_values(array_unique(array_map('strval', (array)$this->enabledKinds)));
        /** @var Module $module */
        $module = Yii::$app->getModule('thiscovery-forms');
        $module->settings->set(Module::SETTING_ENABLED_KINDS, json_encode($kinds));

        return true;
    }
}
