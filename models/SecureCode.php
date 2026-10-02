<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;

/**
 * One-time download code. Only the keyed hash is stored.
 *
 * @property int $id
 * @property int $release_id
 * @property string $code_hash
 * @property string $expires_at
 * @property string|null $used_at
 * @property string|null $revoked_at
 * @property string $source
 * @property int|null $created_by
 * @property string $created_at
 */
class SecureCode extends ActiveRecord
{
    public const SOURCE_MANAGER = 'manager';
    public const SOURCE_CONTACT = 'contact';

    public static function tableName()
    {
        return 'custom_form_secure_code';
    }
}
