<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use humhub\modules\content\models\ContentContainer;
use humhub\modules\thiscoveryForms\helpers\FormEmailLayout;
use humhub\modules\user\models\User;
use Yii;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int|null $contentcontainer_id
 * @property int|null $created_by
 * @property string $title
 * @property string $subject
 * @property string|null $header_html
 * @property string|null $body_html
 * @property string|null $footer_html
 * @property string|null $header_bg_color
 * @property string|null $header_font_color
 * @property string|null $footer_bg_color
 * @property string|null $footer_font_color
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property-read User|null $creator
 */
class FormEmailTemplate extends ActiveRecord
{
    public static function tableName()
    {
        return 'form_email_template';
    }

    public function init()
    {
        parent::init();
        if ($this->isNewRecord) {
            $this->applyColorDefaults();
        }
    }

    public function afterFind()
    {
        parent::afterFind();
        $this->applyColorDefaults();
    }

    public function rules()
    {
        return [
            [['title', 'subject'], 'required'],
            [['title', 'subject'], 'string', 'max' => 255],
            [['header_html', 'body_html', 'footer_html'], 'string'],
            [['header_bg_color', 'header_font_color', 'footer_bg_color', 'footer_font_color'], 'string', 'max' => 7],
            [['header_bg_color', 'header_font_color', 'footer_bg_color', 'footer_font_color'], 'match',
                'pattern' => '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/',
                'skipOnEmpty' => true,
            ],
            [['contentcontainer_id', 'created_by'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'title' => Yii::t('ThiscoveryFormsModule.base', 'Template name'),
            'subject' => Yii::t('ThiscoveryFormsModule.base', 'Subject'),
            'header_html' => Yii::t('ThiscoveryFormsModule.base', 'Email header'),
            'body_html' => Yii::t('ThiscoveryFormsModule.base', 'Email body'),
            'footer_html' => Yii::t('ThiscoveryFormsModule.base', 'Email footer'),
            'header_bg_color' => Yii::t('ThiscoveryFormsModule.base', 'Header background'),
            'header_font_color' => Yii::t('ThiscoveryFormsModule.base', 'Header text colour'),
            'footer_bg_color' => Yii::t('ThiscoveryFormsModule.base', 'Footer background'),
            'footer_font_color' => Yii::t('ThiscoveryFormsModule.base', 'Footer text colour'),
        ];
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $this->applyColorDefaults();
        $now = date('Y-m-d H:i:s');
        if ($insert) {
            $this->created_at = $this->created_at ?: $now;
            $this->created_by = $this->created_by ?: Yii::$app->user->id;
        }
        $this->updated_at = $now;
        return true;
    }

    public function applyColorDefaults(): void
    {
        $defaults = FormEmailLayout::defaultColors();
        foreach ($defaults as $attr => $fallback) {
            $this->$attr = FormEmailLayout::hex($this->$attr, $fallback);
        }
    }

    /**
     * Editor HTML and {{placeholders}} are posted as cfb64:… so admin/WAF
     * rules do not treat the save as a forbidden XSS or template-injection request.
     */
    public static function decodePostedFields(array $fields): array
    {
        foreach (['title', 'subject', 'header_html', 'body_html', 'footer_html'] as $key) {
            if (!isset($fields[$key]) || !is_string($fields[$key])) {
                continue;
            }
            $fields[$key] = self::decodePostedValue($fields[$key]);
        }
        return $fields;
    }

    public static function decodePostedValue(string $value): string
    {
        if (!str_starts_with($value, 'cfb64:')) {
            return $value;
        }
        $decoded = base64_decode(substr($value, 6), true);
        return $decoded === false ? $value : $decoded;
    }

    public function getCreator(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }

    public function getContentContainer()
    {
        if (!$this->contentcontainer_id) {
            return null;
        }
        $row = ContentContainer::findOne((int)$this->contentcontainer_id);
        return $row ? $row->getPolymorphicRelation() : null;
    }
}
