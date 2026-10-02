<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use yii\db\ActiveQuery;

/**
 * A frozen file prepared for one named contact.
 *
 * @property int $id
 * @property int $form_id
 * @property string $label
 * @property string $contact_name
 * @property string $contact_email
 * @property string $source
 * @property string|null $storage_name
 * @property string $original_name
 * @property int $byte_size
 * @property int|null $row_count
 * @property string $link_hash
 * @property string $status
 * @property int $failed_attempts
 * @property int|null $created_by
 * @property string $created_at
 * @property string|null $revoked_at
 * @property int|null $revoked_by
 *
 * @property-read CustomForm $form
 * @property-read SecureEvent[] $events
 */
class SecureRelease extends ActiveRecord
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';
    public const SOURCE_EXPORT = 'export';
    public const SOURCE_UPLOAD = 'upload';

    public static function tableName()
    {
        return 'custom_form_secure_release';
    }

    public function getForm(): ActiveQuery
    {
        return $this->hasOne(CustomForm::class, ['id' => 'form_id']);
    }

    public function getEvents(): ActiveQuery
    {
        return $this->hasMany(SecureEvent::class, ['release_id' => 'id'])->orderBy(['id' => SORT_DESC]);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->storage_name;
    }
}
