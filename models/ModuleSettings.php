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

    /** @var int */
    public $wavesForSurveys = 0;

    /** @var string */
    public $waveScope = Module::WAVE_SCOPE_SURVEY;

    public function init()
    {
        parent::init();
        $this->enabledKinds = Module::enabledKinds();
        /** @var Module $module */
        $module = Yii::$app->getModule('thiscovery-forms');
        if ($module instanceof Module) {
            $this->wavesForSurveys = $module->wavesEnabledForSurveys() ? 1 : 0;
            $this->waveScope = $module->getWaveScope();
        }
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
            [['wavesForSurveys'], 'boolean'],
            [['waveScope'], 'in', 'range' => [Module::WAVE_SCOPE_SURVEY, Module::WAVE_SCOPE_PANEL]],
        ];
    }

    public function attributeLabels()
    {
        return [
            'enabledKinds' => Yii::t('ThiscoveryFormsModule.base', 'Enabled form types'),
            'wavesForSurveys' => Yii::t('ThiscoveryFormsModule.base', 'Allow waves on surveys'),
            'waveScope' => Yii::t('ThiscoveryFormsModule.base', 'Where waves live'),
        ];
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
        $scope = $this->waveScope === Module::WAVE_SCOPE_PANEL
            ? Module::WAVE_SCOPE_PANEL
            : Module::WAVE_SCOPE_SURVEY;

        /** @var Module $module */
        $module = Yii::$app->getModule('thiscovery-forms');
        $module->settings->set(Module::SETTING_ENABLED_KINDS, json_encode($kinds));
        $module->settings->set(Module::SETTING_WAVES_FOR_SURVEYS, !empty($this->wavesForSurveys) ? '1' : '0');
        $module->settings->set(Module::SETTING_WAVE_SCOPE, $scope);

        return true;
    }
}
