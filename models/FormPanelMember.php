<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;
use Yii;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $panel_id
 * @property int|null $user_id
 * @property string|null $email
 * @property string|null $display_name
 * @property string $token
 * @property string|null $demographics_json
 * @property float $weight
 * @property string|null $consent_at
 * @property string $status
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property-read FormPanel $panel
 * @property-read User|null $user
 */
class FormPanelMember extends ActiveRecord
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public static function tableName()
    {
        return 'form_panel_member';
    }

    public function rules()
    {
        return [
            [['panel_id', 'token'], 'required'],
            [['panel_id', 'user_id'], 'integer'],
            [['email', 'display_name'], 'string', 'max' => 255],
            [['email'], 'email'],
            [['token'], 'string', 'max' => 64],
            [['demographics_json'], 'string'],
            [['weight'], 'number'],
            [['weight'], 'default', 'value' => 1],
            [['status'], 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_INACTIVE]],
            [['consent_at', 'created_at', 'updated_at'], 'safe'],
            [['user_id'], 'required', 'when' => function (self $model) {
                return trim((string)$model->email) === '';
            }],
            [['email'], 'required', 'when' => function (self $model) {
                return empty($model->user_id);
            }],
        ];
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        if ($insert) {
            $this->created_at = $this->created_at ?: $now;
            if (!$this->token) {
                $this->token = self::generateToken();
            }
        }
        $this->updated_at = $now;
        if ($this->weight === null || $this->weight === '') {
            $this->weight = 1;
        }
        if (!$this->status) {
            $this->status = self::STATUS_ACTIVE;
        }
        if (!$this->display_name) {
            $this->display_name = $this->user
                ? $this->user->displayName
                : (string)$this->email;
        }
        return true;
    }

    public function getPanel(): ActiveQuery
    {
        return $this->hasOne(FormPanel::class, ['id' => 'panel_id']);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getDisplayLabel(): string
    {
        if (trim((string)$this->display_name) !== '') {
            return (string)$this->display_name;
        }
        if ($this->user) {
            return $this->user->displayName;
        }
        return (string)($this->email ?: Yii::t('ThiscoveryFormsModule.base', 'Panel member'));
    }

    public function getDemographics(): array
    {
        $decoded = json_decode((string)$this->demographics_json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function markConsent(): void
    {
        if ($this->consent_at) {
            return;
        }
        $this->consent_at = date('Y-m-d H:i:s');
        $this->save(false, ['consent_at', 'updated_at']);
    }

    public static function generateToken(): string
    {
        do {
            $token = bin2hex(random_bytes(16));
        } while (static::find()->where(['token' => $token])->exists());
        return $token;
    }
}
