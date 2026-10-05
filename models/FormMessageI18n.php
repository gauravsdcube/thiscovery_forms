<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;

/**
 * A translated participant sentence.
 * form_id 0 is the site wording. Any other id is that form's own wording.
 *
 * @property int $id
 * @property int $form_id
 * @property string $language
 * @property string $message_key
 * @property string $text
 * @property int $locked
 * @property string|null $source_hash
 * @property string|null $updated_at
 */
class FormMessageI18n extends ActiveRecord
{
    public const SITE_FORM_ID = 0;

    public static function tableName()
    {
        return 'custom_form_message_i18n';
    }

    public function rules()
    {
        return [
            [['form_id', 'language', 'message_key', 'text'], 'required'],
            [['form_id', 'locked'], 'integer'],
            [['language'], 'string', 'max' => 16],
            [['message_key'], 'string', 'max' => 80],
            [['source_hash'], 'string', 'max' => 64],
            [['text'], 'string'],
        ];
    }
}
