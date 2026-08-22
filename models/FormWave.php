<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use Yii;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int|null $form_id
 * @property int|null $panel_id
 * @property int $wave_number
 * @property string|null $title
 * @property string $status
 * @property string|null $opens_at
 * @property string|null $closes_at
 * @property string|null $invited_at
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property-read CustomForm|null $form
 * @property-read FormPanel|null $panel
 */
class FormWave extends ActiveRecord
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    public static function tableName()
    {
        return 'form_wave';
    }

    public function rules()
    {
        return [
            [['form_id', 'panel_id', 'wave_number'], 'integer'],
            [['form_id', 'panel_id'], 'validateOwner'],
            [['title'], 'string', 'max' => 255],
            [['status'], 'in', 'range' => [self::STATUS_DRAFT, self::STATUS_OPEN, self::STATUS_CLOSED]],
            [['opens_at', 'closes_at', 'invited_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'wave_number' => Yii::t('ThiscoveryFormsModule.base', 'Wave number'),
            'title' => Yii::t('ThiscoveryFormsModule.base', 'Title'),
            'status' => Yii::t('ThiscoveryFormsModule.base', 'Status'),
            'opens_at' => Yii::t('ThiscoveryFormsModule.base', 'Opens'),
            'closes_at' => Yii::t('ThiscoveryFormsModule.base', 'Closes'),
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
        }
        $this->updated_at = $now;
        return true;
    }

    public function validateOwner($attribute): void
    {
        if (!(int)$this->form_id && !(int)$this->panel_id) {
            $this->addError($attribute, Yii::t('ThiscoveryFormsModule.base', 'A wave must belong to a form or a panel.'));
        }
    }

    public function getForm(): ActiveQuery
    {
        return $this->hasOne(CustomForm::class, ['id' => 'form_id']);
    }

    public function getPanel(): ActiveQuery
    {
        return $this->hasOne(FormPanel::class, ['id' => 'panel_id']);
    }

    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_DRAFT => Yii::t('ThiscoveryFormsModule.base', 'Draft'),
            self::STATUS_OPEN => Yii::t('ThiscoveryFormsModule.base', 'Open'),
            self::STATUS_CLOSED => Yii::t('ThiscoveryFormsModule.base', 'Closed'),
        ];
    }

    public function getDisplayTitle(): string
    {
        $title = trim((string)$this->title);
        if ($title !== '') {
            return $title;
        }
        return Yii::t('ThiscoveryFormsModule.base', 'Wave {n}', ['n' => (int)$this->wave_number]);
    }

    public function isOpenNow(): bool
    {
        if ($this->status !== self::STATUS_OPEN) {
            return false;
        }
        $now = time();
        if ($this->opens_at && strtotime($this->opens_at) > $now) {
            return false;
        }
        if ($this->closes_at && strtotime($this->closes_at) < $now) {
            return false;
        }
        return true;
    }
}
