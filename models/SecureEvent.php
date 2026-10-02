<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;
use yii\db\ActiveQuery;

/**
 * Append-only history for one prepared file.
 *
 * @property int $id
 * @property int $release_id
 * @property int $form_id
 * @property string $event
 * @property int|null $actor_id
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $accept_language
 * @property string|null $detail
 * @property string $created_at
 *
 * @property-read User|null $actor
 */
class SecureEvent extends ActiveRecord
{
    public static function tableName()
    {
        return 'custom_form_secure_event';
    }

    public function getActor(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'actor_id']);
    }
}
