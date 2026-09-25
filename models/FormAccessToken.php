<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use yii\db\ActiveQuery;

/**
 * Unique / one-time survey access tokens. The raw token is never stored.
 *
 * @property int $id
 * @property int $form_id
 * @property string $token_hash
 * @property string|null $token_hint
 * @property string|null $label
 * @property int $one_time
 * @property int $max_uses
 * @property int $use_count
 * @property string|null $expires_at
 * @property string|null $last_used_at
 * @property int|null $created_by
 * @property string $created_at
 *
 * @property-read CustomForm $form
 */
class FormAccessToken extends ActiveRecord
{
    public static function tableName()
    {
        return 'custom_form_access_token';
    }

    public function rules()
    {
        return [
            [['form_id', 'token_hash'], 'required'],
            [['form_id', 'one_time', 'max_uses', 'use_count', 'created_by'], 'integer'],
            [['token_hash'], 'string', 'max' => 64],
            [['token_hint'], 'string', 'max' => 8],
            [['label'], 'string', 'max' => 120],
            [['expires_at', 'last_used_at', 'created_at'], 'safe'],
        ];
    }

    public function getForm(): ActiveQuery
    {
        return $this->hasOne(CustomForm::class, ['id' => 'form_id']);
    }

    public function isUsable(): bool
    {
        if ($this->expires_at && strtotime($this->expires_at) < time()) {
            return false;
        }
        return (int)$this->use_count < max(1, (int)$this->max_uses);
    }
}
