<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;
use Yii;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property int $form_id
 * @property int $status
 * @property string|null $resume_code
 * @property string|null $resume_email
 * @property int|null $current_page
 * @property string $created_at
 * @property int|null $created_by
 * @property string $updated_at
 * @property int|null $updated_by
 * @property int|null $panel_member_id
 * @property int|null $wave_id
 * @property int|null $round_id
 * @property float $weight
 *
 * @property-read CustomForm $form
 * @property-read FormAnswerField[] $answerFields
 * @property-read User|null $user
 * @property-read FormPanelMember|null $panelMember
 * @property-read FormWave|null $wave
 * @property-read FormRound|null $round
 */
class FormAnswer extends ActiveRecord
{
    public const STATUS_IN_PROGRESS = 0;
    public const STATUS_COMPLETE = 1;

    /** @var bool When true, skip recording submitter identity. */
    public $forceAnonymous = false;

    public static function tableName()
    {
        return 'custom_form_answer';
    }

    public function rules()
    {
        return [
            [['form_id'], 'required'],
            [['form_id', 'created_by', 'updated_by', 'status', 'current_page', 'panel_member_id', 'wave_id', 'round_id'], 'integer'],
            [['status'], 'default', 'value' => self::STATUS_COMPLETE],
            [['status'], 'in', 'range' => [self::STATUS_IN_PROGRESS, self::STATUS_COMPLETE]],
            [['weight'], 'number'],
            [['weight'], 'default', 'value' => 1],
            [['resume_code'], 'string', 'max' => 32],
            [['resume_email'], 'email'],
            [['created_at', 'updated_at'], 'safe'],
            [['created_by', 'updated_by'], 'required', 'when' => function ($model) {
                return !$model->forceAnonymous;
            }],
        ];
    }

    public function isInProgress(): bool
    {
        return (int)$this->status === self::STATUS_IN_PROGRESS;
    }

    public function isComplete(): bool
    {
        return (int)$this->status === self::STATUS_COMPLETE;
    }

    public function beforeValidate()
    {
        if ($this->forceAnonymous) {
            $this->created_by = null;
            $this->updated_by = null;
            if ($this->isNewRecord) {
                $this->created_at = date('Y-m-d H:i:s');
            }
            $this->updated_at = date('Y-m-d H:i:s');
            return parent::beforeValidate();
        }

        if ($this->isNewRecord) {
            $this->created_at = date('Y-m-d H:i:s');
            $this->created_by = $this->created_by ?: Yii::$app->user->id;
        }
        $this->updated_at = date('Y-m-d H:i:s');
        $this->updated_by = Yii::$app->user->id ?: $this->updated_by;

        return parent::beforeValidate();
    }

    public function isAnonymous(): bool
    {
        return $this->created_by === null || $this->created_by === '';
    }

    public function getSubmitterDisplayName(?CustomForm $form = null): string
    {
        $form = $form ?: $this->form;
        if ($form && $form->shouldHideIdentity()) {
            return Yii::t('ThiscoveryFormsModule.base', 'Anonymous');
        }
        if ($this->isAnonymous()) {
            if ($this->panelMember && !$form->shouldHideIdentity()) {
                return $this->panelMember->getDisplayLabel();
            }
            return Yii::t('ThiscoveryFormsModule.base', 'Anonymous');
        }

        return $this->user
            ? $this->user->displayName
            : Yii::t('ThiscoveryFormsModule.base', 'Unknown user');
    }

    public function getForm(): ActiveQuery
    {
        return $this->hasOne(CustomForm::class, ['id' => 'form_id']);
    }

    public function getAnswerFields(): ActiveQuery
    {
        return $this->hasMany(FormAnswerField::class, ['answer_id' => 'id']);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }

    public function getPanelMember(): ActiveQuery
    {
        return $this->hasOne(FormPanelMember::class, ['id' => 'panel_member_id']);
    }

    public function getWave(): ActiveQuery
    {
        return $this->hasOne(FormWave::class, ['id' => 'wave_id']);
    }

    public function getRound(): ActiveQuery
    {
        return $this->hasOne(FormRound::class, ['id' => 'round_id']);
    }

    public function getFieldValue(int $fieldId): ?string
    {
        foreach ($this->answerFields as $af) {
            if ((int)$af->field_id === $fieldId) {
                return $af->value;
            }
        }
        return null;
    }

    public function getValuesMap(): array
    {
        $map = [];
        foreach ($this->answerFields as $af) {
            $decoded = json_decode((string)$af->value, true);
            $map[$af->field_id] = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : $af->value;
        }
        return $map;
    }

    public function getJustificationsMap(): array
    {
        $map = [];
        foreach ($this->answerFields as $af) {
            $just = trim((string)$af->justification);
            if ($just !== '') {
                $map[$af->field_id] = $just;
            }
        }
        return $map;
    }

    public function beforeDelete()
    {
        if (!parent::beforeDelete()) {
            return false;
        }
        foreach ($this->answerFields as $af) {
            $af->delete();
        }
        return true;
    }
}
