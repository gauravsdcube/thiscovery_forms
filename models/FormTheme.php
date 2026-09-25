<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use humhub\modules\thiscoveryForms\services\FormStyleService;
use Yii;

/**
 * Named appearance theme (global). Forms can link to a theme and keep local overrides.
 *
 * @property int $id
 * @property string $name
 * @property string|null $style_json
 * @property string|null $custom_css
 * @property int $is_default
 * @property string|null $created_at
 * @property int|null $created_by
 * @property string|null $updated_at
 * @property int|null $updated_by
 */
class FormTheme extends ActiveRecord
{
    public static function tableName()
    {
        return 'custom_form_theme';
    }

    public function rules()
    {
        return [
            [['name'], 'required'],
            [['name'], 'string', 'max' => 120],
            [['style_json', 'custom_css'], 'string'],
            [['is_default'], 'boolean'],
            [['created_by', 'updated_by'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'name' => Yii::t('ThiscoveryFormsModule.base', 'Theme name'),
            'custom_css' => Yii::t('ThiscoveryFormsModule.base', 'Custom CSS'),
            'is_default' => Yii::t('ThiscoveryFormsModule.base', 'Default theme'),
        ];
    }

    public function getStyle(): array
    {
        $decoded = json_decode((string)$this->style_json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function setStyle(array $style): void
    {
        $this->style_json = json_encode((new FormStyleService())->normalize($style), JSON_UNESCAPED_UNICODE);
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        $uid = Yii::$app->user->isGuest ? null : (int)Yii::$app->user->id;
        if ($insert) {
            $this->created_at = $now;
            $this->created_by = $uid;
        }
        $this->updated_at = $now;
        $this->updated_by = $uid;
        return true;
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        if (!empty($this->is_default)) {
            static::updateAll(['is_default' => 0], ['and', ['<>', 'id', $this->id], ['is_default' => 1]]);
        }
    }

    public static function findDefault(): ?self
    {
        $theme = static::find()->where(['is_default' => 1])->one();
        if ($theme) {
            return $theme;
        }
        return static::find()->orderBy(['id' => SORT_ASC])->one();
    }

    /**
     * @return array<int, string> id => name (default marked)
     */
    public static function dropdownOptions(bool $includeCustom = true): array
    {
        $out = [];
        foreach (static::find()->orderBy(['is_default' => SORT_DESC, 'name' => SORT_ASC])->all() as $theme) {
            $label = $theme->name;
            if ($theme->is_default) {
                $label .= ' (' . Yii::t('ThiscoveryFormsModule.base', 'default') . ')';
            }
            $out[(string)$theme->id] = $label;
        }
        if ($includeCustom) {
            $out[''] = Yii::t('ThiscoveryFormsModule.base', 'Custom (detached from theme)');
        }
        return $out;
    }

    public function toExportArray(): array
    {
        return [
            'name' => $this->name,
            'style' => $this->getStyle(),
            'custom_css' => (string)$this->custom_css,
            'is_default' => (int)$this->is_default,
        ];
    }

    public static function fromImportArray(array $payload): self
    {
        $theme = new self();
        $theme->name = trim((string)($payload['name'] ?? 'Imported theme')) ?: 'Imported theme';
        $style = $payload['style'] ?? [];
        if (!is_array($style)) {
            $style = [];
        }
        $theme->setStyle($style);
        $theme->custom_css = (string)($payload['custom_css'] ?? '');
        $theme->is_default = !empty($payload['is_default']) ? 1 : 0;
        return $theme;
    }
}
