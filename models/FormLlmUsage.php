<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $created_at
 * @property int|null $user_id
 * @property int|null $form_id
 * @property string|null $session_id
 * @property string $purpose
 * @property string $provider
 * @property string $model
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property int $total_tokens
 * @property float $estimated_cost
 * @property int $latency_ms
 * @property int $success
 * @property string|null $error_code
 */
class FormLlmUsage extends ActiveRecord
{
    public static function tableName()
    {
        return 'custom_form_llm_usage';
    }

    public function rules()
    {
        return [
            [['created_at', 'purpose'], 'required'],
            [['user_id', 'form_id', 'prompt_tokens', 'completion_tokens', 'total_tokens', 'latency_ms', 'success'], 'integer'],
            [['estimated_cost'], 'number'],
            [['session_id', 'purpose', 'provider', 'model', 'error_code'], 'string', 'max' => 255],
            [['created_at'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'purpose' => Yii::t('ThiscoveryFormsModule.base', 'Purpose'),
            'model' => Yii::t('ThiscoveryFormsModule.base', 'Model'),
            'total_tokens' => Yii::t('ThiscoveryFormsModule.base', 'Tokens'),
            'estimated_cost' => Yii::t('ThiscoveryFormsModule.base', 'Estimated cost'),
        ];
    }
}
