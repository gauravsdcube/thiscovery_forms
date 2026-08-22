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
 * @property int $is_test
 * @property string|null $resume_code
 * @property string|null $resume_email
 * @property int|null $current_page
 * @property string|null $vars_json
 * @property string $created_at
 * @property int|null $created_by
 * @property string $updated_at
 * @property int|null $updated_by
 * @property int|null $panel_member_id
 * @property int|null $wave_id
 * @property int|null $round_id
 * @property float $weight
 * @property string $workflow_status
 * @property int|null $current_stage_id
 * @property string|null $submitted_at
 *
 * @property-read CustomForm $form
 * @property-read FormAnswerField[] $answerFields
 * @property-read User|null $user
 * @property-read FormApprovalStage|null $currentStage
 * @property-read FormAnswerApproval[] $approvals
 * @property-read FormPanelMember|null $panelMember
 * @property-read FormWave|null $wave
 * @property-read FormRound|null $round
 */
class FormAnswer extends ActiveRecord
{
    public const STATUS_IN_PROGRESS = 0;
    public const STATUS_COMPLETE = 1;

    public const WORKFLOW_NONE = 'none';
    public const WORKFLOW_IN_REVIEW = 'in_review';
    public const WORKFLOW_CHANGES_REQUESTED = 'changes_requested';
    public const WORKFLOW_PUBLISHED = 'published';
    public const WORKFLOW_ARCHIVED = 'archived';

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
            [['form_id', 'created_by', 'updated_by', 'status', 'is_test', 'current_page', 'panel_member_id', 'wave_id', 'round_id', 'current_stage_id'], 'integer'],
            [['status'], 'default', 'value' => self::STATUS_COMPLETE],
            [['status'], 'in', 'range' => [self::STATUS_IN_PROGRESS, self::STATUS_COMPLETE]],
            [['is_test'], 'default', 'value' => 0],
            [['is_test'], 'boolean'],
            [['workflow_status'], 'default', 'value' => self::WORKFLOW_NONE],
            [['workflow_status'], 'in', 'range' => [
                self::WORKFLOW_NONE,
                self::WORKFLOW_IN_REVIEW,
                self::WORKFLOW_CHANGES_REQUESTED,
                self::WORKFLOW_PUBLISHED,
                self::WORKFLOW_ARCHIVED,
            ]],
            [['submitted_at'], 'safe'],
            [['weight'], 'number'],
            [['weight'], 'default', 'value' => 1],
            [['resume_code'], 'string', 'max' => 32],
            [['resume_email'], 'email'],
            [['created_at', 'updated_at'], 'safe'],
            [['vars_json'], 'string'],
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

    public function isTest(): bool
    {
        return (int)$this->is_test === 1;
    }

    /**
     * Live participant answers (never preview/test runs).
     */
    public static function findParticipants(): ActiveQuery
    {
        return static::find()->andWhere(['is_test' => 0]);
    }

    public static function getWorkflowLabels(): array
    {
        return [
            self::WORKFLOW_NONE => Yii::t('ThiscoveryFormsModule.base', 'None'),
            self::WORKFLOW_IN_REVIEW => Yii::t('ThiscoveryFormsModule.base', 'In review'),
            self::WORKFLOW_CHANGES_REQUESTED => Yii::t('ThiscoveryFormsModule.base', 'Changes requested'),
            self::WORKFLOW_PUBLISHED => Yii::t('ThiscoveryFormsModule.base', 'Published'),
            self::WORKFLOW_ARCHIVED => Yii::t('ThiscoveryFormsModule.base', 'Archived'),
        ];
    }

    public function getWorkflowLabel(): string
    {
        return self::getWorkflowLabels()[$this->workflow_status] ?? (string)$this->workflow_status;
    }

    public function isInReview(): bool
    {
        return $this->workflow_status === self::WORKFLOW_IN_REVIEW;
    }

    public function isChangesRequested(): bool
    {
        return $this->workflow_status === self::WORKFLOW_CHANGES_REQUESTED;
    }

    public function isPublished(): bool
    {
        return $this->workflow_status === self::WORKFLOW_PUBLISHED;
    }

    public function isArchived(): bool
    {
        return $this->workflow_status === self::WORKFLOW_ARCHIVED;
    }

    public function getRecordTitle(): string
    {
        $form = $this->form;
        if ($form) {
            $preferred = ['text', 'textarea', 'dropdown', 'rich_text'];
            foreach ($preferred as $type) {
                foreach ($form->fields as $field) {
                    if ($field->type !== $type || !$field->collectsAnswer()) {
                        continue;
                    }
                    $value = '';
                    foreach ($this->answerFields as $af) {
                        if ((int)$af->field_id === (int)$field->id) {
                            $value = trim((string)$af->getDisplayValue());
                            break;
                        }
                    }
                    if ($value !== '') {
                        return mb_strimwidth($value, 0, 120, '…');
                    }
                }
            }
        }
        return Yii::t('ThiscoveryFormsModule.base', 'Project #{id}', ['id' => (int)$this->id]);
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

    public function getCurrentStage(): ActiveQuery
    {
        return $this->hasOne(FormApprovalStage::class, ['id' => 'current_stage_id']);
    }

    public function getApprovals(): ActiveQuery
    {
        return $this->hasMany(FormAnswerApproval::class, ['answer_id' => 'id'])->orderBy(['created_at' => SORT_ASC, 'id' => SORT_ASC]);
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

    /**
     * @return array<string,string>
     */
    public function getVars(): array
    {
        $decoded = json_decode((string)$this->vars_json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $key => $value) {
            $name = \humhub\modules\thiscoveryForms\services\FormActionService::sanitizeName((string)$key);
            if ($name === '') {
                continue;
            }
            $out[$name] = is_scalar($value) ? (string)$value : '';
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $vars
     */
    public function setVars(array $vars): void
    {
        $clean = [];
        foreach ($vars as $key => $value) {
            $name = \humhub\modules\thiscoveryForms\services\FormActionService::sanitizeName((string)$key);
            if ($name === '') {
                continue;
            }
            $clean[$name] = is_scalar($value) ? (string)$value : '';
        }
        $this->vars_json = $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE) : null;
    }

    public function getValuesMap(): array
    {
        $map = [];
        foreach ($this->getAnswerFields()->all() as $af) {
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
