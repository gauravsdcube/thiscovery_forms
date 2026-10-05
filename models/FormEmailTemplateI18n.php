<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;

/**
 * One language of an email template. The template row itself stays the source wording.
 *
 * @property int $id
 * @property int $template_id
 * @property string $language
 * @property string|null $subject
 * @property string|null $header_html
 * @property string|null $body_html
 * @property string|null $footer_html
 * @property int $locked
 * @property string|null $updated_at
 */
class FormEmailTemplateI18n extends ActiveRecord
{
    public static function tableName()
    {
        return 'form_email_template_i18n';
    }

    public function rules()
    {
        return [
            [['template_id', 'language'], 'required'],
            [['template_id', 'locked'], 'integer'],
            [['language'], 'string', 'max' => 16],
            [['subject'], 'string', 'max' => 255],
            [['header_html', 'body_html', 'footer_html'], 'string'],
        ];
    }
}
