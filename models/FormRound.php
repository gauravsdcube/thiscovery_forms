<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use Yii;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $form_id
 * @property int $round_number
 * @property string|null $title
 * @property string $status
 * @property string|null $opens_at
 * @property string|null $closes_at
 * @property string|null $summary_html
 * @property string|null $frozen_field_ids_json
 * @property string|null $published_at
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @property-read CustomForm $form
 */
class FormRound extends ActiveRecord
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    public static function tableName()
    {
        return 'form_round';
    }

    public function rules()
    {
        return [
            [['form_id', 'round_number'], 'required'],
            [['form_id', 'round_number'], 'integer'],
            [['title'], 'string', 'max' => 255],
            [['summary_html', 'frozen_field_ids_json'], 'string'],
            [['status'], 'in', 'range' => [self::STATUS_DRAFT, self::STATUS_OPEN, self::STATUS_CLOSED]],
            [['opens_at', 'closes_at', 'published_at', 'created_at', 'updated_at'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'round_number' => Yii::t('ThiscoveryFormsModule.base', 'Round number'),
            'title' => Yii::t('ThiscoveryFormsModule.base', 'Title'),
            'status' => Yii::t('ThiscoveryFormsModule.base', 'Status'),
            'opens_at' => Yii::t('ThiscoveryFormsModule.base', 'Opens'),
            'closes_at' => Yii::t('ThiscoveryFormsModule.base', 'Closes'),
            'summary_html' => Yii::t('ThiscoveryFormsModule.base', 'Published summary'),
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

    public function getForm(): ActiveQuery
    {
        return $this->hasOne(CustomForm::class, ['id' => 'form_id']);
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
        return Yii::t('ThiscoveryFormsModule.base', 'Round {n}', ['n' => (int)$this->round_number]);
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

    public function getFrozenFieldIds(): array
    {
        $decoded = json_decode((string)$this->frozen_field_ids_json, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_map('intval', $decoded));
    }

    public function setFrozenFieldIds(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $this->frozen_field_ids_json = $ids ? json_encode($ids) : null;
    }

    public function hasPublishedSummary(): bool
    {
        return $this->published_at && trim((string)$this->summary_html) !== '';
    }
}
