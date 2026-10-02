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
 * @property string|null $first_name
 * @property string|null $last_name
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
            [['email', 'display_name', 'first_name', 'last_name'], 'string', 'max' => 255],
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
            $this->display_name = $this->buildDisplayName();
        }
        return true;
    }

    public function buildDisplayName(): string
    {
        $parts = array_filter([trim((string)$this->first_name), trim((string)$this->last_name)]);
        if ($parts) {
            return implode(' ', $parts);
        }
        if ($this->user) {
            return $this->user->displayName;
        }
        return (string)$this->email;
    }

    public function getActivities(): ActiveQuery
    {
        return $this->hasMany(FormPanelActivity::class, ['member_id' => 'id'])
            ->orderBy(['created_at' => SORT_DESC, 'id' => SORT_DESC]);
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
        $built = $this->buildDisplayName();
        if (trim($built) !== '') {
            return $built;
        }
        if (trim((string)$this->display_name) !== '') {
            return (string)$this->display_name;
        }
        return (string)($this->email ?: Yii::t('ThiscoveryFormsModule.base', 'Panel member'));
    }

    public function getDemographics(): array
    {
        $decoded = json_decode((string)$this->demographics_json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function setDemographics(array $data): void
    {
        $clean = [];
        foreach ($data as $key => $value) {
            $key = trim((string)$key);
            if (is_array($value)) {
                $parts = [];
                foreach ($value as $item) {
                    if (is_scalar($item) || $item === null) {
                        $parts[] = (string)$item;
                    }
                }
                $value = trim(implode(', ', $parts));
            } else {
                $value = trim((string)$value);
            }
            if ($key === '' || $value === '') {
                continue;
            }
            $clean[$key] = $value;
        }
        $this->demographics_json = $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE) : null;
    }

    /**
     * The panel's "Consent recorded" date: the latest eConsent record agreed to (V3-43).
     */
    public function markConsent(?int $recordId = null, ?string $signedAt = null): void
    {
        if ($recordId === null || $recordId < 1) {
            return;
        }
        $this->consent_at = $signedAt ?: date('Y-m-d H:i:s');
        $this->save(false, ['consent_at', 'updated_at']);
    }

    /** Days a panel link works after it is issued (SEC-16). */
    public const SETTING_LINK_DAYS = 'panel_link_days';
    public const DEFAULT_LINK_DAYS = 180;

    /**
     * The token for invite links (SEC-16): member id, expiry day and an HMAC keyed by this
     * member's private token and a server secret. The stored token itself never leaves the
     * server, and a link stops working when it expires or the member is deactivated.
     */
    public function linkToken(?int $days = null): string
    {
        $days = $days ?? self::linkDays();
        $expires = intdiv(time(), 86400) + max(1, $days);
        $exp = base_convert((string)$expires, 10, 36);
        return (int)$this->id . '-' . $exp . '-' . self::linkMac((int)$this->id, $exp, (string)$this->token);
    }

    /** The active member a link token names, or null when it is forged, expired or unknown. */
    public static function fromLinkToken(string $token): ?self
    {
        if (!preg_match('/^(\d{1,10})-([a-z0-9]{1,8})-([a-f0-9]{24})$/', trim($token), $m)) {
            return null;
        }
        if ((int)base_convert($m[2], 36, 10) < intdiv(time(), 86400)) {
            return null;
        }
        $member = static::findOne((int)$m[1]);
        if (!$member || (string)$member->token === '') {
            return null;
        }
        return hash_equals(self::linkMac((int)$member->id, $m[2], (string)$member->token), $m[3]) ? $member : null;
    }

    public static function linkDays(): int
    {
        $module = Yii::$app->getModule('thiscovery-forms');
        $raw = $module ? $module->settings->get(self::SETTING_LINK_DAYS) : null;
        return max(1, (int)($raw === null || $raw === '' ? self::DEFAULT_LINK_DAYS : $raw));
    }

    private static function linkMac(int $id, string $exp, string $memberToken): string
    {
        $module = Yii::$app->getModule('thiscovery-forms');
        $secret = $module ? (string)$module->settings->get('panel_link_secret', '') : '';
        if ($secret === '' && $module) {
            $secret = bin2hex(random_bytes(32));
            $module->settings->set('panel_link_secret', $secret);
        }
        return substr(hash_hmac('sha256', $id . '|' . $exp, $memberToken . '|' . $secret), 0, 24);
    }

    public static function generateToken(): string
    {
        do {
            $token = bin2hex(random_bytes(16));
        } while (static::find()->where(['token' => $token])->exists());
        return $token;
    }
}
