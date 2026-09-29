<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

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

    /** @var int */
    public $randomisationEnabled = 0;

    /** @var int */
    public $fromBriefEnabled = 0;

    /** @var int */
    public $fromBriefLlmEnabled = 0;

    /** @var string */
    public $llmProvider = 'openai';

    /** @var string */
    public $llmApiBase = '';

    /** @var string */
    public $llmApiKey = '';

    /** @var string */
    public $llmModel = 'gpt-4o-mini';

    /** @var int */
    public $fromBriefMaxUploadMb = 8;

    /** @var int */
    public $llmMaxBriefChars = 60000;

    /** @var float */
    public $llmCostPer1kInput = 0.15;

    /** @var float */
    public $llmCostPer1kOutput = 0.60;

    /** @var float|string */
    public $llmWarnMonthlyCost = '';

    public function init()
    {
        parent::init();
        $this->enabledKinds = Module::enabledKinds();
        $this->display = DisplaySettings::global();

        /** @var Module $module */
        $module = Yii::$app->getModule('thiscovery-forms');
        $this->randomisationEnabled = (int)$module->settings->get(Module::SETTING_RANDOMISATION, 0);
        $this->fromBriefEnabled = (int)$module->settings->get(Module::SETTING_FROM_BRIEF_ENABLED, 0);
        $this->fromBriefLlmEnabled = (int)$module->settings->get(Module::SETTING_FROM_BRIEF_LLM_ENABLED, 0);
        $this->llmProvider = (string)$module->settings->get(Module::SETTING_LLM_PROVIDER, 'openai');
        $this->llmApiBase = (string)$module->settings->get(Module::SETTING_LLM_API_BASE, '');
        $this->llmApiKey = (string)$module->settings->get(Module::SETTING_LLM_API_KEY, '');
        $this->llmModel = (string)$module->settings->get(Module::SETTING_LLM_MODEL, 'gpt-4o-mini');
        $this->fromBriefMaxUploadMb = (int)$module->settings->get(Module::SETTING_FROM_BRIEF_MAX_UPLOAD_MB, 8);
        $this->llmMaxBriefChars = (int)$module->settings->get(Module::SETTING_LLM_MAX_BRIEF_CHARS, 60000);
        $this->llmCostPer1kInput = (float)$module->settings->get(Module::SETTING_LLM_COST_INPUT, 0.15);
        $this->llmCostPer1kOutput = (float)$module->settings->get(Module::SETTING_LLM_COST_OUTPUT, 0.60);
        $warn = $module->settings->get(Module::SETTING_LLM_WARN_MONTHLY_COST, '');
        $this->llmWarnMonthlyCost = $warn === null || $warn === '' ? '' : (float)$warn;
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
            [['fromBriefEnabled', 'fromBriefLlmEnabled', 'randomisationEnabled'], 'boolean'],
            [['llmProvider', 'llmApiBase', 'llmApiKey', 'llmModel'], 'string'],
            [['fromBriefMaxUploadMb'], 'integer', 'min' => 1, 'max' => 50],
            [['llmMaxBriefChars'], 'integer', 'min' => 2000, 'max' => 500000],
            [['llmCostPer1kInput', 'llmCostPer1kOutput'], 'number', 'min' => 0],
            [['llmWarnMonthlyCost'], 'number', 'min' => 0, 'skipOnEmpty' => true],
            [['llmProvider'], 'in', 'range' => array_keys(\humhub\modules\thiscoveryForms\services\LlmClient::providerLabels())],
        ];
    }

    public static function llmProviderLabels(): array
    {
        return \humhub\modules\thiscoveryForms\services\LlmClient::providerLabels();
    }

    public function attributeLabels()
    {
        return [
            'enabledKinds' => Yii::t('ThiscoveryFormsModule.base', 'Enabled form types'),
            'randomisationEnabled' => Yii::t('ThiscoveryFormsModule.base', 'Enable randomisation'),
            'fromBriefEnabled' => Yii::t('ThiscoveryFormsModule.base', 'Enable create from brief / document'),
            'fromBriefLlmEnabled' => Yii::t('ThiscoveryFormsModule.base', 'Allow LLM assist'),
            'llmProvider' => Yii::t('ThiscoveryFormsModule.base', 'LLM provider'),
            'llmApiBase' => Yii::t('ThiscoveryFormsModule.base', 'API base URL'),
            'llmApiKey' => Yii::t('ThiscoveryFormsModule.base', 'API key'),
            'llmModel' => Yii::t('ThiscoveryFormsModule.base', 'Model'),
            'fromBriefMaxUploadMb' => Yii::t('ThiscoveryFormsModule.base', 'Max upload size (MB)'),
            'llmMaxBriefChars' => Yii::t('ThiscoveryFormsModule.base', 'Max brief characters sent to LLM'),
            'llmCostPer1kInput' => Yii::t('ThiscoveryFormsModule.base', 'Estimated $ per 1K input tokens'),
            'llmCostPer1kOutput' => Yii::t('ThiscoveryFormsModule.base', 'Estimated $ per 1K output tokens'),
            'llmWarnMonthlyCost' => Yii::t('ThiscoveryFormsModule.base', 'Warn when estimated monthly cost exceeds ($)'),
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

        $provider = trim((string)$this->llmProvider) ?: 'openai';
        if (!isset(self::llmProviderLabels()[$provider])) {
            $provider = 'openai';
        }
        $defaultModel = $provider === 'anthropic' ? 'claude-sonnet-4-5' : 'gpt-4o-mini';
        $model = trim((string)$this->llmModel) ?: $defaultModel;

        $module->settings->set(Module::SETTING_RANDOMISATION, !empty($this->randomisationEnabled) ? '1' : '0');
        $module->settings->set(Module::SETTING_FROM_BRIEF_ENABLED, (int)!empty($this->fromBriefEnabled));
        $module->settings->set(Module::SETTING_FROM_BRIEF_LLM_ENABLED, (int)!empty($this->fromBriefLlmEnabled));
        $module->settings->set(Module::SETTING_LLM_PROVIDER, $provider);
        $module->settings->set(Module::SETTING_LLM_API_BASE, trim((string)$this->llmApiBase));
        $key = trim((string)$this->llmApiKey);
        if ($key !== '' && $key !== '********') {
            $module->settings->set(Module::SETTING_LLM_API_KEY, $key);
        }
        $module->settings->set(Module::SETTING_LLM_MODEL, $model);
        $module->settings->set(Module::SETTING_FROM_BRIEF_MAX_UPLOAD_MB, max(1, (int)$this->fromBriefMaxUploadMb));
        $module->settings->set(Module::SETTING_LLM_MAX_BRIEF_CHARS, max(2000, (int)$this->llmMaxBriefChars));
        $module->settings->set(Module::SETTING_LLM_COST_INPUT, (string)(float)$this->llmCostPer1kInput);
        $module->settings->set(Module::SETTING_LLM_COST_OUTPUT, (string)(float)$this->llmCostPer1kOutput);
        $warn = $this->llmWarnMonthlyCost;
        $module->settings->set(
            Module::SETTING_LLM_WARN_MONTHLY_COST,
            ($warn === '' || $warn === null) ? '' : (string)(float)$warn
        );

        return true;
    }
}
