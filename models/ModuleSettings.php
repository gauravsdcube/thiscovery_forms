<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\services\DisplaySettings;
use Yii;
use yii\base\Model;

/**
 * Administration → Modules → Thiscovery Forms configuration.
 */
class ModuleSettings extends Model
{
    /** @var string[] */
    public $enabledKinds = [];

    /** @var array */
    public $display = [];

    public function init()
    {
        parent::init();
        $this->enabledKinds = Module::enabledKinds();
        $this->display = DisplaySettings::global();
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
            [['display'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'enabledKinds' => Yii::t('ThiscoveryFormsModule.base', 'Enabled form types'),
        ] + DisplaySettings::labels();
    }

    public static function waveScopeLabels(): array
    {
        return [
            Module::WAVE_SCOPE_SURVEY => Yii::t('ThiscoveryFormsModule.base', 'Per survey — each form has its own wave calendar'),
            Module::WAVE_SCOPE_PANEL => Yii::t('ThiscoveryFormsModule.base', 'Per panel — forms that share a panel share the same waves'),
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
        DisplaySettings::saveGlobal(is_array($this->display) ? $this->display : []);

        return true;
    }
}
