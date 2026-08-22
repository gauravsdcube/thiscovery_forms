<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\content\models\Content;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\permissions\AnswerForm;
use humhub\modules\thiscoveryForms\permissions\AnswerGlobalForm;
use humhub\modules\thiscoveryForms\permissions\CreateForm;
use humhub\modules\thiscoveryForms\permissions\CreateGlobalForm;
use humhub\modules\thiscoveryForms\permissions\ManageForm;
use humhub\modules\thiscoveryForms\permissions\ManageGlobalForm;
use humhub\modules\thiscoveryForms\permissions\ViewAnswers;
use humhub\modules\thiscoveryForms\permissions\ViewGlobalAnswers;
use humhub\modules\thiscoveryForms\services\FormStyleService;
use humhub\modules\thiscoveryForms\services\LogicEngine;
use humhub\modules\thiscoveryForms\services\PanelService;
use humhub\modules\thiscoveryForms\widgets\WallEntry;
use humhub\modules\search\interfaces\Searchable;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\Group;
use humhub\modules\user\models\User;
use humhub\modules\user\components\PermissionManager;
use Yii;
use yii\db\ActiveQuery;

/**
 * @property int $id
 * @property string $title
 * @property string $kind
 * @property string|null $description
 * @property string|null $thank_you_content
 * @property string|null $already_submitted_message
 * @property string|null $custom_css
 * @property string|null $settings_json
 * @property int $status
 * @property int $allow_multiple
 * @property int $allow_anonymous
 * @property int $allow_edit
 * @property int $allow_resume
 * @property int $show_in_menu
 * @property int $is_template
 * @property int|null $source_template_id
 * @property int|null $folder_id
 * @property string $answers_visibility
 *
 * @property-read FormField[] $fields
 * @property-read FormAnswer[] $answers
 * @property-read FormFolder|null $folder
 */
class CustomForm extends ContentActiveRecord implements Searchable
{
    public const STATUS_DRAFT = 0;
    public const STATUS_OPEN = 1;
    public const STATUS_CLOSED = 2;

    public const ANSWERS_MANAGERS = 'managers';
    public const ANSWERS_RESPONDENTS = 'respondents';
    public const ANSWERS_PERMISSION = 'permission';

    public const KIND_SURVEY = 'survey';
    public const KIND_POLL = 'poll';
    public const KIND_FEEDBACK = 'feedback';
    public const KIND_LONGITUDINAL = 'longitudinal';
    public const KIND_CONSENSUS = 'consensus';
    public const KIND_PROJECT = 'project';
    public const KIND_EQ5D = 'eq5d';

    public const IDENTITY_IDENTIFIED = 'identified';
    public const IDENTITY_MANAGERS_ONLY = 'managers_only';
    public const IDENTITY_FULLY_ANONYMOUS = 'fully_anonymous';

    public const ENROL_PANEL_NONE = 'none';
    public const ENROL_PANEL_EXISTING = 'existing';
    public const ENROL_PANEL_CREATE = 'create';

    public $wallEntryClass = WallEntry::class;
    public $moduleId = 'thiscovery-forms';
    protected $createPermission = CreateForm::class;
    protected $managePermission = ManageForm::class;
    protected $canMove = true;
    public $silentContentCreation = false;

    /** @var int|bool Poll setting persisted in settings_json */
    public $show_results = 1;

    /** @var string Source locale for translations */
    public $source_language = 'en-GB';

    /** @var string[] Enabled fill languages */
    public $enabled_languages = ['en-GB'];

    /** @var string Consensus identity mode */
    public $identity_mode = self::IDENTITY_IDENTIFIED;

    /** @var int Consensus agreement threshold (percent) */
    public $consensus_threshold = 70;

    /** @var int|bool Freeze items that reached consensus */
    public $freeze_on_consensus = 1;

    /** @var int|bool Require a comment after choice questions */
    public $require_justification = 0;

    /** @var array Visual tokens for the fill page (empty = site theme) */
    public $style = [];

    /** @var int|bool Share dashboard without sign-in */
    public $public_dashboard_enabled = 0;

    /** @var int|bool Fill without HumHub header / space chrome */
    public $hide_humhub_header = 0;

    /** @var int|bool Keep incomplete responses for dashboard/export */
    public $keep_partials = 0;

    /** @var string none|existing|create */
    public $enrol_panel_mode = 'none';

    /** @var int */
    public $enrol_panel_id = 0;

    /** @var string */
    public $enrol_panel_title = '';

    /** @var int|bool */
    public $log_panel_activity = 0;

    /** @var int|bool Survey-only: use waves when the module allows it */
    public $use_waves = 0;

    /** @var int */
    public $invite_email_template_id = 0;

    /** @var int */
    public $wave_email_template_id = 0;

    /** @var int */
    public $reminder_email_template_id = 0;

    /** @var int */
    public $reminder_days = 0;

    /** @var int */
    public $completion_email_template_id = 0;

    /** @var array */
    public $submit_actions = [];

    /** @var array */
    public $custom_functions = [];

    public static function tableName()
    {
        return 'custom_form';
    }

    public function rules()
    {
        return [
            [['title'], 'required'],
            [['title'], 'string', 'max' => 255],
            [['kind'], 'default', 'value' => self::KIND_SURVEY],
            [['kind'], 'in', 'range' => array_keys(self::getKindLabels())],
            [['description', 'thank_you_content', 'already_submitted_message', 'custom_css', 'settings_json'], 'string'],
            [['status'], 'in', 'range' => [self::STATUS_DRAFT, self::STATUS_OPEN, self::STATUS_CLOSED]],
            [['allow_multiple', 'show_in_menu', 'allow_anonymous', 'allow_edit', 'allow_resume', 'is_template', 'show_results', 'freeze_on_consensus', 'require_justification', 'public_dashboard_enabled', 'hide_humhub_header', 'keep_partials', 'log_panel_activity', 'use_waves'], 'boolean'],
            [['allow_edit'], 'default', 'value' => 1],
            [['allow_resume'], 'default', 'value' => 0],
            [['is_template'], 'default', 'value' => 0],
            [['public_dashboard_enabled', 'hide_humhub_header', 'keep_partials'], 'default', 'value' => 0],
            [['source_template_id', 'consensus_threshold'], 'integer'],
            [['folder_id'], 'default', 'value' => null],
            [['folder_id'], 'integer'],
            [['folder_id'], 'exist', 'skipOnEmpty' => true, 'targetClass' => FormFolder::class, 'targetAttribute' => 'id'],
            [['consensus_threshold'], 'integer', 'min' => 1, 'max' => 100],
            [['source_language', 'identity_mode'], 'string', 'max' => 32],
            [['identity_mode'], 'in', 'range' => array_keys(self::getIdentityModeLabels())],
            [['enabled_languages', 'style', 'enrol_panel_mode', 'enrol_panel_title', 'submit_actions', 'custom_functions'], 'safe'],
            [['enrol_panel_id', 'invite_email_template_id', 'wave_email_template_id', 'reminder_email_template_id', 'reminder_days', 'completion_email_template_id'], 'integer'],
            [['answers_visibility'], 'in', 'range' => [
                self::ANSWERS_MANAGERS,
                self::ANSWERS_RESPONDENTS,
                self::ANSWERS_PERMISSION,
            ]],
        ];
    }

    public function attributeLabels()
    {
        return [
            'title' => Yii::t('ThiscoveryFormsModule.base', 'Title'),
            'kind' => Yii::t('ThiscoveryFormsModule.base', 'Type'),
            'description' => Yii::t('ThiscoveryFormsModule.base', 'Description'),
            'thank_you_content' => Yii::t('ThiscoveryFormsModule.base', 'Thank you message'),
            'already_submitted_message' => Yii::t('ThiscoveryFormsModule.base', 'Already submitted message'),
            'custom_css' => Yii::t('ThiscoveryFormsModule.base', 'Custom CSS'),
            'status' => Yii::t('ThiscoveryFormsModule.base', 'Status'),
            'allow_multiple' => Yii::t('ThiscoveryFormsModule.base', 'Allow multiple submissions'),
            'allow_anonymous' => Yii::t('ThiscoveryFormsModule.base', 'Allow anonymous submissions'),
            'allow_edit' => Yii::t('ThiscoveryFormsModule.base', 'Allow respondents to edit their answers'),
            'allow_resume' => Yii::t('ThiscoveryFormsModule.base', 'Allow save and resume'),
            'keep_partials' => Yii::t('ThiscoveryFormsModule.base', 'Keep incomplete responses'),
            'folder_id' => Yii::t('ThiscoveryFormsModule.base', 'Folder'),
            'public_dashboard_enabled' => Yii::t('ThiscoveryFormsModule.base', 'Share dashboard without sign-in'),
            'hide_humhub_header' => Yii::t('ThiscoveryFormsModule.base', 'Run without HumHub header'),
            'show_in_menu' => Yii::t('ThiscoveryFormsModule.base', 'Show in side menu'),
            'show_results' => Yii::t('ThiscoveryFormsModule.base', 'Show results after voting'),
            'answers_visibility' => Yii::t('ThiscoveryFormsModule.base', 'Who can view answers'),
            'source_language' => Yii::t('ThiscoveryFormsModule.base', 'Source language'),
            'enabled_languages' => Yii::t('ThiscoveryFormsModule.base', 'Languages'),
            'identity_mode' => Yii::t('ThiscoveryFormsModule.base', 'Identity'),
            'consensus_threshold' => Yii::t('ThiscoveryFormsModule.base', 'Consensus threshold (%)'),
            'freeze_on_consensus' => Yii::t('ThiscoveryFormsModule.base', 'Freeze items that reach consensus'),
            'require_justification' => Yii::t('ThiscoveryFormsModule.base', 'Require a comment after each choice'),
        ];
    }

    public function getIcon()
    {
        return match ($this->kind) {
            self::KIND_POLL => 'fa-bar-chart',
            self::KIND_FEEDBACK => 'fa-commenting-o',
            self::KIND_LONGITUDINAL => 'fa-line-chart',
            self::KIND_CONSENSUS => 'fa-balance-scale',
            self::KIND_PROJECT => 'fa-folder-open',
            self::KIND_EQ5D => 'fa-thermometer-half',
            default => 'fa-wpforms',
        };
    }

    public function getContentName()
    {
        return self::getKindLabels()[$this->kind] ?? Yii::t('ThiscoveryFormsModule.base', 'Form');
    }

    public function getContentDescription()
    {
        return $this->title;
    }

    public function getSearchAttributes()
    {
        return [
            'title' => $this->title,
            'description' => (string)$this->description,
        ];
    }

    public function getFields(): ActiveQuery
    {
        return $this->hasMany(FormField::class, ['form_id' => 'id'])->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getFolder(): ActiveQuery
    {
        return $this->hasOne(FormFolder::class, ['id' => 'folder_id']);
    }

    public function getAnswers(): ActiveQuery
    {
        return $this->hasMany(FormAnswer::class, ['form_id' => 'id'])
            ->andWhere(['custom_form_answer.status' => FormAnswer::STATUS_COMPLETE, 'custom_form_answer.is_test' => 0])
            ->orderBy(['custom_form_answer.created_at' => SORT_DESC]);
    }

    public function getInProgressAnswers(): ActiveQuery
    {
        return $this->hasMany(FormAnswer::class, ['form_id' => 'id'])
            ->andWhere(['custom_form_answer.status' => FormAnswer::STATUS_IN_PROGRESS, 'custom_form_answer.is_test' => 0])
            ->orderBy(['custom_form_answer.updated_at' => SORT_DESC]);
    }

    public function getExportableAnswers(): ActiveQuery
    {
        $query = $this->hasMany(FormAnswer::class, ['form_id' => 'id'])
            ->andWhere(['custom_form_answer.is_test' => 0])
            ->orderBy(['custom_form_answer.created_at' => SORT_DESC]);
        if (!$this->keepsPartials()) {
            $query->andWhere(['custom_form_answer.status' => FormAnswer::STATUS_COMPLETE]);
        }
        return $query;
    }

    public function getWaves(): ActiveQuery
    {
        return $this->hasMany(FormWave::class, ['form_id' => 'id'])->orderBy(['wave_number' => SORT_ASC]);
    }

    public function getRounds(): ActiveQuery
    {
        return $this->hasMany(FormRound::class, ['form_id' => 'id'])->orderBy(['round_number' => SORT_ASC]);
    }

    public function getApprovalStages(): ActiveQuery
    {
        return $this->hasMany(FormApprovalStage::class, ['form_id' => 'id'])->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getI18nRows(): ActiveQuery
    {
        return $this->hasMany(FormI18n::class, ['form_id' => 'id']);
    }

    public function getSourceLanguage(): string
    {
        $lang = trim((string)$this->source_language) ?: (string)$this->getSetting('source_language', 'en-GB');
        return $lang !== '' ? $lang : 'en-GB';
    }

    /**
     * @return string[]
     */
    public function getEnabledLanguages(): array
    {
        $langs = $this->enabled_languages;
        if (!is_array($langs) || !$langs) {
            $langs = $this->getSetting('enabled_languages', [$this->getSourceLanguage()]);
        }
        if (!is_array($langs) || !$langs) {
            $langs = [$this->getSourceLanguage()];
        }
        $langs = array_values(array_unique(array_filter(array_map('strval', $langs))));
        $source = $this->getSourceLanguage();
        if (!in_array($source, $langs, true)) {
            array_unshift($langs, $source);
        }
        return $langs;
    }

    public function getIdentityMode(): string
    {
        $mode = (string)($this->identity_mode ?: $this->getSetting('identity_mode', self::IDENTITY_IDENTIFIED));
        return isset(self::getIdentityModeLabels()[$mode]) ? $mode : self::IDENTITY_IDENTIFIED;
    }

    public function hidesIdentityFromManagers(): bool
    {
        return $this->getIdentityMode() === self::IDENTITY_FULLY_ANONYMOUS;
    }

    public function shouldHideIdentity($viewer = null): bool
    {
        $mode = $this->getIdentityMode();
        if ($mode === self::IDENTITY_IDENTIFIED) {
            return false;
        }
        if ($mode === self::IDENTITY_FULLY_ANONYMOUS) {
            return true;
        }
        return !$this->canManage($viewer);
    }

    public function getConsensusThreshold(): int
    {
        $n = (int)($this->consensus_threshold ?: $this->getSetting('consensus_threshold', 70));
        return max(1, min(100, $n ?: 70));
    }

    public function freezesOnConsensus(): bool
    {
        return !empty($this->freeze_on_consensus) || (bool)$this->getSetting('freeze_on_consensus', false);
    }

    public function requiresJustification(): bool
    {
        return !empty($this->require_justification) || (bool)$this->getSetting('require_justification', false);
    }

    public function emailsOnWaveOpen(): bool
    {
        $value = $this->getSetting('email_on_wave_open', true);
        return $value === true || $value === 1 || $value === '1';
    }

    public function isGlobal(): bool
    {
        try {
            if ($this->content && !$this->content->isNewRecord) {
                return empty($this->content->contentcontainer_id);
            }
            return $this->content->getContainer() === null;
        } catch (\Throwable $e) {
            return empty($this->content->contentcontainer_id ?? null);
        }
    }

    public function isOpen(): bool
    {
        return (int)$this->status === self::STATUS_OPEN;
    }

    public function isDraft(): bool
    {
        return (int)$this->status === self::STATUS_DRAFT;
    }

    public function isClosed(): bool
    {
        return (int)$this->status === self::STATUS_CLOSED;
    }

    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_DRAFT => Yii::t('ThiscoveryFormsModule.base', 'Draft'),
            self::STATUS_OPEN => Yii::t('ThiscoveryFormsModule.base', 'Open'),
            self::STATUS_CLOSED => Yii::t('ThiscoveryFormsModule.base', 'Closed'),
        ];
    }

    public static function getAnswersVisibilityLabels(): array
    {
        return [
            self::ANSWERS_MANAGERS => Yii::t('ThiscoveryFormsModule.base', 'Author and managers only'),
            self::ANSWERS_RESPONDENTS => Yii::t('ThiscoveryFormsModule.base', 'Managers and respondents'),
            self::ANSWERS_PERMISSION => Yii::t('ThiscoveryFormsModule.base', 'Anyone with View Answers permission'),
        ];
    }

    public static function getKindLabels(): array
    {
        return [
            self::KIND_SURVEY => Yii::t('ThiscoveryFormsModule.base', 'Survey'),
            self::KIND_POLL => Yii::t('ThiscoveryFormsModule.base', 'Quick poll'),
            self::KIND_FEEDBACK => Yii::t('ThiscoveryFormsModule.base', 'Feedback form'),
            self::KIND_EQ5D => Yii::t('ThiscoveryFormsModule.base', 'EQ-5D survey'),
            self::KIND_LONGITUDINAL => Yii::t('ThiscoveryFormsModule.base', 'Longitudinal survey'),
            self::KIND_CONSENSUS => Yii::t('ThiscoveryFormsModule.base', 'Consensus / Delphi'),
            self::KIND_PROJECT => Yii::t('ThiscoveryFormsModule.base', 'Project'),
        ];
    }

    public static function getKindDescriptions(): array
    {
        return [
            self::KIND_SURVEY => Yii::t('ThiscoveryFormsModule.base', 'Full multi-page form with any question type.'),
            self::KIND_POLL => Yii::t('ThiscoveryFormsModule.base', 'One question. Drop it on a page or post.'),
            self::KIND_FEEDBACK => Yii::t('ThiscoveryFormsModule.base', 'Short rating plus comments, ready to edit.'),
            self::KIND_EQ5D => Yii::t('ThiscoveryFormsModule.base', 'Five one-question pages plus a vertical 0–100 scale, for repeating waves. Paste licensed wording — this is a layout, not the official instrument.'),
            self::KIND_LONGITUDINAL => Yii::t('ThiscoveryFormsModule.base', 'The same panel answers repeating waves of this survey.'),
            self::KIND_CONSENSUS => Yii::t('ThiscoveryFormsModule.base', 'Multi-round consensus or Delphi, with summaries between rounds.'),
            self::KIND_PROJECT => Yii::t('ThiscoveryFormsModule.base', 'Structured project record with a configurable approval workflow and a published catalogue.'),
        ];
    }

    public static function isKindEnabled(string $kind): bool
    {
        $module = Yii::$app->getModule('thiscovery-forms');
        if ($module instanceof \humhub\modules\thiscoveryForms\Module) {
            return $module->isKindEnabled($kind);
        }
        return isset(self::getKindLabels()[$kind]);
    }

    /**
     * @return array<string, string>
     */
    public static function getEnabledKindLabels(): array
    {
        return array_filter(
            self::getKindLabels(),
            static fn($label, $kind) => self::isKindEnabled((string)$kind),
            ARRAY_FILTER_USE_BOTH
        );
    }

    public function isPoll(): bool
    {
        return $this->kind === self::KIND_POLL;
    }

    public function isFeedback(): bool
    {
        return $this->kind === self::KIND_FEEDBACK;
    }

    public function isLongitudinal(): bool
    {
        return $this->kind === self::KIND_LONGITUDINAL;
    }

    public function isEq5d(): bool
    {
        return $this->kind === self::KIND_EQ5D;
    }

    public function isSurvey(): bool
    {
        return $this->kind === self::KIND_SURVEY;
    }

    /**
     * Repeating waves for this form (EQ-5D, longitudinal, or a survey that opted in).
     */
    public function usesWaves(): bool
    {
        if ($this->isLongitudinal() || $this->isEq5d()) {
            return true;
        }
        if ($this->isSurvey()) {
            return \humhub\modules\thiscoveryForms\Module::wavesEnabledForSurveysStatic()
                && !empty($this->use_waves);
        }
        return false;
    }

    public function isConsensus(): bool
    {
        return $this->kind === self::KIND_CONSENSUS;
    }

    public function isProject(): bool
    {
        return $this->kind === self::KIND_PROJECT;
    }

    public static function getIdentityModeLabels(): array
    {
        return [
            self::IDENTITY_IDENTIFIED => Yii::t('ThiscoveryFormsModule.base', 'Identified — names are visible to managers'),
            self::IDENTITY_MANAGERS_ONLY => Yii::t('ThiscoveryFormsModule.base', 'Managers only — respondents never see names'),
            self::IDENTITY_FULLY_ANONYMOUS => Yii::t('ThiscoveryFormsModule.base', 'Fully anonymous — even managers see no names'),
        ];
    }

    public function isTemplate(): bool
    {
        return (bool)$this->is_template;
    }

    public function getSettings(): array
    {
        $decoded = json_decode((string)$this->settings_json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getSetting(string $key, $default = null)
    {
        $settings = $this->getSettings();
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public function setSetting(string $key, $value): void
    {
        $settings = $this->getSettings();
        $settings[$key] = $value;
        $this->settings_json = json_encode($settings, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Panel attached for waves/invites, or the enrolment panel.
     */
    public function getAttachedPanel(): ?FormPanel
    {
        $id = (int)$this->getSetting('panel_id', 0);
        if (!$id) {
            $id = (int)$this->enrol_panel_id ?: (int)$this->getSetting('enrol_panel_id', 0);
        }
        return $id ? FormPanel::findOne($id) : null;
    }

    public function showsPollResults(): bool
    {
        $value = $this->getSetting('show_results', true);
        return $value === true || $value === 1 || $value === '1';
    }

    /**
     * @return string[]|null null means all types
     */
    public function getAllowedFieldTypes(): ?array
    {
        if ($this->isPoll()) {
            return [FormField::TYPE_RADIO, FormField::TYPE_CHECKBOX, FormField::TYPE_RICH_TEXT];
        }
        return null;
    }

    public function applyKindDefaults(): void
    {
        if ($this->kind === self::KIND_POLL) {
            $this->allow_edit = 0;
            $this->allow_multiple = 0;
            $this->allow_resume = 0;
            $this->show_in_menu = 0;
            if ($this->title === '' || $this->title === null) {
                $this->title = Yii::t('ThiscoveryFormsModule.base', 'Quick poll');
            }
            $settings = $this->getSettings();
            if (!array_key_exists('show_results', $settings)) {
                $this->setSetting('show_results', true);
            }
            if ($this->isGlobal()) {
                $this->allow_anonymous = 1;
            }
        } elseif ($this->kind === self::KIND_FEEDBACK) {
            if ($this->title === '' || $this->title === null) {
                $this->title = Yii::t('ThiscoveryFormsModule.base', 'Feedback');
            }
        } elseif ($this->kind === self::KIND_LONGITUDINAL) {
            $this->allow_multiple = 0;
            if ($this->title === '' || $this->title === null) {
                $this->title = Yii::t('ThiscoveryFormsModule.base', 'Longitudinal survey');
            }
        } elseif ($this->kind === self::KIND_EQ5D) {
            $this->allow_multiple = 0;
            $this->allow_anonymous = 1;
            $this->use_waves = 1;
            if ($this->title === '' || $this->title === null) {
                $this->title = Yii::t('ThiscoveryFormsModule.base', 'EQ-5D survey');
            }
            if (($this->enrol_panel_mode === self::ENROL_PANEL_NONE || $this->enrol_panel_mode === '') && !$this->id) {
                $this->enrol_panel_mode = self::ENROL_PANEL_CREATE;
            }
        } elseif ($this->kind === self::KIND_CONSENSUS) {
            $this->allow_multiple = 0;
            if ($this->title === '' || $this->title === null) {
                $this->title = Yii::t('ThiscoveryFormsModule.base', 'Consensus survey');
            }
            if ($this->getSetting('identity_mode') === null) {
                $this->identity_mode = self::IDENTITY_IDENTIFIED;
            }
        } elseif ($this->kind === self::KIND_PROJECT) {
            $this->allow_anonymous = 0;
            $this->allow_multiple = 1;
            $this->allow_edit = 1;
            $this->answers_visibility = self::ANSWERS_PERMISSION;
            if ($this->title === '' || $this->title === null) {
                $this->title = Yii::t('ThiscoveryFormsModule.base', 'Project record');
            }
        }
    }

    public static function seedPollFields(): array
    {
        $field = new FormField([
            'type' => FormField::TYPE_RADIO,
            'label' => Yii::t('ThiscoveryFormsModule.base', 'Your question'),
            'required' => 1,
        ]);
        $field->setOptionsFromText(
            Yii::t('ThiscoveryFormsModule.base', "Yes\nNo")
        );
        return [$field];
    }

    public static function seedFeedbackFields(): array
    {
        $rating = new FormField([
            'type' => FormField::TYPE_RATING,
            'label' => Yii::t('ThiscoveryFormsModule.base', 'How would you rate your experience?'),
            'required' => 1,
        ]);
        $rating->setRatingScale([
            'min' => 1,
            'max' => 5,
            'step' => 1,
            'lowLabel' => Yii::t('ThiscoveryFormsModule.base', 'Poor'),
            'highLabel' => Yii::t('ThiscoveryFormsModule.base', 'Excellent'),
        ]);
        $comment = new FormField([
            'type' => FormField::TYPE_TEXTAREA,
            'label' => Yii::t('ThiscoveryFormsModule.base', 'Comments'),
            'required' => 0,
            'help_text' => Yii::t('ThiscoveryFormsModule.base', 'Optional — tell us more'),
        ]);
        return [$rating, $comment];
    }

    /**
     * Licence-safe multi-page starter: five one-question pages plus a vertical 0–100 scale.
     * Replace placeholder wording with text from your own instrument licence.
     *
     * @return FormField[]
     */
    public static function seedHealthStatusFields(): array
    {
        $levels = implode("\n", [
            Yii::t('ThiscoveryFormsModule.base', 'Level 1 (replace with licensed text)'),
            Yii::t('ThiscoveryFormsModule.base', 'Level 2 (replace with licensed text)'),
            Yii::t('ThiscoveryFormsModule.base', 'Level 3 (replace with licensed text)'),
            Yii::t('ThiscoveryFormsModule.base', 'Level 4 (replace with licensed text)'),
            Yii::t('ThiscoveryFormsModule.base', 'Level 5 (replace with licensed text)'),
        ]);
        $dimensionHelp = Yii::t(
            'ThiscoveryFormsModule.base',
            'Choose the one answer that best describes your health today. Replace this instruction with your licensed wording.'
        );
        $footerHtml = '<p class="cf-licence-footer">© '
            . htmlspecialchars(Yii::t('ThiscoveryFormsModule.base', 'Replace with the copyright line required by your licence.'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</p>';

        $makeFooter = static function () use ($footerHtml) {
            $footer = new FormField([
                'type' => FormField::TYPE_HTML,
                'label' => Yii::t('ThiscoveryFormsModule.base', 'Page footer'),
                'required' => 0,
            ]);
            $footer->setHtmlConfig([
                'html' => $footerHtml,
                'collect' => false,
            ]);
            return $footer;
        };

        $out = [];
        for ($n = 1; $n <= 5; $n++) {
            $radio = new FormField([
                'type' => FormField::TYPE_RADIO,
                'label' => Yii::t('ThiscoveryFormsModule.base', 'Dimension {n} (replace with licensed text)', ['n' => $n]),
                'help_text' => $dimensionHelp,
                'required' => 1,
            ]);
            $radio->setOptionsFromText($levels);
            $radio->setInstrumentRole('eq5d_d' . $n);
            $out[] = $radio;
            $out[] = $makeFooter();
            if ($n < 5) {
                $break = new FormField([
                    'type' => FormField::TYPE_PAGE_BREAK,
                    'label' => Yii::t('ThiscoveryFormsModule.base', 'Page {n}', ['n' => $n + 1]),
                    'required' => 0,
                ]);
                $break->setPageBreakConfig([
                    'pageKey' => 'hs' . ($n + 1),
                    'title' => Yii::t('ThiscoveryFormsModule.base', 'Dimension {n}', ['n' => $n + 1]),
                ]);
                $out[] = $break;
            }
        }

        $vasBreak = new FormField([
            'type' => FormField::TYPE_PAGE_BREAK,
            'label' => Yii::t('ThiscoveryFormsModule.base', 'Overall scale page'),
            'required' => 0,
        ]);
        $vasBreak->setPageBreakConfig([
            'pageKey' => 'hs-vas',
            'title' => Yii::t('ThiscoveryFormsModule.base', 'Overall scale'),
        ]);
        $out[] = $vasBreak;

        $vas = new FormField([
            'type' => FormField::TYPE_RATING,
            'label' => Yii::t('ThiscoveryFormsModule.base', 'Overall health today (replace with licensed text)'),
            'help_text' => Yii::t(
                'ThiscoveryFormsModule.base',
                "Replace this help text with the licensed instructions for the vertical scale.\nClick or drag the thermometer, or type an integer in the box."
            ),
            'required' => 1,
        ]);
        $vas->setRatingScale([
            'min' => 0,
            'max' => 100,
            'step' => 1,
            'lowLabel' => Yii::t('ThiscoveryFormsModule.base', 'Low end (replace with licensed text)'),
            'highLabel' => Yii::t('ThiscoveryFormsModule.base', 'High end (replace with licensed text)'),
            'display' => FormField::RATING_DISPLAY_THERMOMETER,
        ]);
        $vas->setInstrumentRole('eq5d_vas');
        $out[] = $vas;
        $out[] = $makeFooter();

        return $out;
    }

    public function getPollQuestion(): ?FormField
    {
        foreach ($this->fields as $field) {
            if ($field->collectsAnswer()) {
                return $field;
            }
        }
        return null;
    }

    /**
     * Live (non-template) forms that have not been soft-deleted.
     *
     * CustomForm extends ContentActiveRecord, so delete() only marks content.state
     * as deleted. Lists must exclude that state or the form still appears.
     */
    public static function findLive()
    {
        return static::find()
            ->joinWith('content')
            ->andWhere(['custom_form.is_template' => 0])
            ->andWhere(['<>', 'content.state', Content::STATE_DELETED]);
    }

    /**
     * @return static[]
     */
    public static function findAvailableTemplates($container = null): array
    {
        $query = static::find()
            ->joinWith('content')
            ->andWhere(['custom_form.is_template' => 1])
            ->andWhere(['<>', 'content.state', Content::STATE_DELETED]);
        if ($container) {
            $query->andWhere([
                'or',
                ['content.contentcontainer_id' => $container->contentcontainer_id],
                ['content.contentcontainer_id' => null],
            ]);
        } else {
            $query->andWhere(['content.contentcontainer_id' => null]);
        }
        $templates = $query->orderBy(['custom_form.title' => SORT_ASC])->all();
        return array_values(array_filter(
            $templates,
            static fn(self $template) => self::isKindEnabled((string)$template->kind)
        ));
    }

    /**
     * Options for engagement-page form pickers.
     * @return array<int|string,string>
     */
    public static function pickerOptions($container = null, ?string $kind = null, bool $includeGlobal = true): array
    {
        $options = [];
        $add = static function (array $forms, string $prefix = '') use (&$options) {
            foreach ($forms as $form) {
                $options[$form->id] = $prefix !== '' ? ($prefix . $form->title) : $form->title;
            }
        };

        $filter = static function ($query) use ($kind) {
            $query->andWhere(['custom_form.is_template' => 0]);
            if ($kind !== null) {
                $query->andWhere(['custom_form.kind' => $kind]);
            } else {
                $query->andWhere(['<>', 'custom_form.kind', self::KIND_POLL]);
            }
            return $query->orderBy(['custom_form.title' => SORT_ASC]);
        };

        if ($includeGlobal) {
            $global = $filter(static::find()->joinWith('content')->andWhere(['content.contentcontainer_id' => null]))->all();
            $add($global, $container ? (Yii::t('ThiscoveryFormsModule.base', 'Global') . ': ') : '');
        }

        if ($container) {
            $space = $filter(static::find()->contentContainer($container))->all();
            $add($space);
        }

        return $options;
    }

    public function afterFind()
    {
        parent::afterFind();
        $this->show_results = $this->showsPollResults() ? 1 : 0;
        $this->source_language = (string)$this->getSetting('source_language', 'en-GB') ?: 'en-GB';
        $langs = $this->getSetting('enabled_languages', [$this->source_language]);
        $this->enabled_languages = is_array($langs) ? array_values($langs) : [$this->source_language];
        if (!in_array($this->source_language, $this->enabled_languages, true)) {
            array_unshift($this->enabled_languages, $this->source_language);
        }
        $mode = (string)$this->getSetting('identity_mode', self::IDENTITY_IDENTIFIED);
        $this->identity_mode = isset(self::getIdentityModeLabels()[$mode]) ? $mode : self::IDENTITY_IDENTIFIED;
        $this->consensus_threshold = (int)$this->getSetting('consensus_threshold', 70) ?: 70;
        $this->freeze_on_consensus = $this->getSetting('freeze_on_consensus', true) ? 1 : 0;
        $this->require_justification = $this->getSetting('require_justification', false) ? 1 : 0;
        $style = $this->getSetting('style', []);
        $this->style = is_array($style) ? $style : [];
        $this->public_dashboard_enabled = $this->getSetting('public_dashboard_enabled', false) ? 1 : 0;
        $this->hide_humhub_header = $this->getSetting('hide_humhub_header', false) ? 1 : 0;
        $this->keep_partials = $this->getSetting('keep_partials', false) ? 1 : 0;
        $this->enrol_panel_mode = (string)$this->getSetting('enrol_panel_mode', self::ENROL_PANEL_NONE) ?: self::ENROL_PANEL_NONE;
        $this->enrol_panel_id = (int)$this->getSetting('enrol_panel_id', 0);
        $this->enrol_panel_title = (string)$this->getSetting('enrol_panel_title', '');
        $this->log_panel_activity = $this->getSetting('log_panel_activity', false) ? 1 : 0;
        $this->use_waves = $this->getSetting('use_waves', false) ? 1 : 0;
        $this->invite_email_template_id = (int)$this->getSetting('invite_email_template_id', 0);
        $this->wave_email_template_id = (int)$this->getSetting('wave_email_template_id', 0);
        $this->reminder_email_template_id = (int)$this->getSetting('reminder_email_template_id', 0);
        $this->reminder_days = (int)$this->getSetting('reminder_days', 0);
        $this->completion_email_template_id = (int)$this->getSetting('completion_email_template_id', 0);
        $this->submit_actions = \humhub\modules\thiscoveryForms\services\FormActionService::normalizeList($this->getSetting('submit_actions', []));
        $this->custom_functions = \humhub\modules\thiscoveryForms\services\FormActionService::normalizeFunctions($this->getSetting('custom_functions', []));
        if ($this->isTemplate()) {
            $this->silentContentCreation = true;
        }
    }

    public function getUrl($scheme = false): string
    {
        return Url::toView($this, $scheme);
    }

    public function getEditUrl(): string
    {
        return Url::toEdit($this);
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        $this->syncContentState();

        if (!$this->isTemplate()) {
            try {
                if ($this->usesWaves()) {
                    (new \humhub\modules\thiscoveryForms\services\WaveService())->ensureSetup($this);
                }
                if ($this->isConsensus()) {
                    (new \humhub\modules\thiscoveryForms\services\RoundService())->ensureSetup($this);
                }
            } catch (\Throwable $e) {
                Yii::warning('Thiscovery Forms programme setup failed: ' . $e->getMessage(), 'thiscovery-forms');
            }
        }
    }

    public function beforeDelete()
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        foreach ($this->answers as $answer) {
            $answer->delete();
        }
        foreach ($this->fields as $field) {
            $field->delete();
        }
        foreach ($this->getWaves()->all() as $wave) {
            $wave->delete();
        }
        foreach ($this->getRounds()->all() as $round) {
            $round->delete();
        }
        foreach ($this->getI18nRows()->all() as $row) {
            $row->delete();
        }

        return true;
    }

    protected function syncContentState(): void
    {
        try {
            if (!$this->content || $this->content->isNewRecord) {
                return;
            }
            $target = $this->isDraft() ? Content::STATE_DRAFT : Content::STATE_PUBLISHED;
            if ((int)$this->content->state !== $target) {
                $this->content->getStateService()->update($target);
            }
        } catch (\Throwable $e) {
            Yii::error('Thiscovery Forms content state sync failed: ' . $e->getMessage(), 'thiscovery-forms');
        }
    }

    public function canCreate($user = null): bool
    {
        $user = $user ?: Yii::$app->user->getIdentity();
        if (!$user) {
            return false;
        }

        $container = null;
        try {
            $container = $this->content->getContainer();
        } catch (\Throwable $e) {
            $container = null;
        }

        if ($container instanceof Space) {
            return $container->getPermissionManager($user)->can(CreateForm::class);
        }

        return (new PermissionManager(['subject' => $user]))->can(CreateGlobalForm::class);
    }

    public function canManage($user = null): bool
    {
        $user = $user ?: Yii::$app->user->getIdentity();
        if (!$user) {
            return false;
        }

        if ((int)$this->content->created_by === (int)$user->id) {
            return true;
        }

        $container = $this->isGlobal() ? null : $this->content->getContainer();
        if ($container instanceof Space) {
            $ok = $container->getPermissionManager($user)->can(ManageForm::class);
        } else {
            $ok = (new PermissionManager(['subject' => $user]))->can(ManageGlobalForm::class);
        }

        if ($ok && $this->folder && !\humhub\modules\thiscoveryForms\services\FolderService::canView($this->folder, $user)) {
            return false;
        }

        return $ok;
    }

    public function canAnswer($user = null): bool
    {
        if (!$this->isOpen()) {
            return false;
        }

        $user = $user ?: Yii::$app->user->getIdentity();

        // Anonymous mode: guests and users may submit; identity is never stored.
        if ($this->allow_anonymous) {
            if (!$this->allow_multiple && !$this->usesWaves() && !$this->isConsensus() && $this->hasGuestAnswered()) {
                return false;
            }
            return true;
        }

        if (!$user) {
            return false;
        }

        if (!$this->allow_multiple && !$this->usesWaves() && !$this->isConsensus() && $this->hasUserAnswered($user)) {
            return false;
        }

        return $this->canAnswerPermissionOnly($user);
    }

    public function allowsAnonymous(): bool
    {
        return (bool)$this->allow_anonymous;
    }

    public function allowsResume(): bool
    {
        return (bool)$this->allow_resume;
    }

    public function keepsPartials(): bool
    {
        return (bool)$this->keep_partials;
    }

    public function allowsPublicDashboard(): bool
    {
        return (bool)$this->public_dashboard_enabled && !$this->isTemplate();
    }

    public function hidesHumhubHeader(): bool
    {
        return (bool)$this->hide_humhub_header && !$this->isTemplate();
    }

    /**
     * HTML options for links that open this form's fill page.
     * Headerless fill uses a different document layout, so PJAX must not swap only the body.
     */
    public function fillHtmlOptions(array $options = []): array
    {
        if ($this->hidesHumhubHeader()) {
            $options['data-pjax-prevent'] = 1;
        }
        return $options;
    }

    public static function generateShareToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }

    public function getTestToken(): string
    {
        $token = trim((string)$this->getSetting('test_token', ''));
        if ($token !== '') {
            return $token;
        }
        $token = self::generateShareToken();
        $this->setSetting('test_token', $token);
        if (!$this->isNewRecord) {
            $this->updateAttributes(['settings_json' => $this->settings_json]);
        }
        return $token;
    }

    public function rotateTestToken(): string
    {
        $token = self::generateShareToken();
        $this->setSetting('test_token', $token);
        $this->updateAttributes(['settings_json' => $this->settings_json]);
        return $token;
    }

    public function isValidTestToken(?string $token): bool
    {
        $token = trim((string)$token);
        $expected = $this->getTestToken();
        return $token !== '' && $expected !== '' && hash_equals($expected, $token);
    }

    public function getPublicDashboardToken(): string
    {
        $token = trim((string)$this->getSetting('public_dashboard_token', ''));
        if ($token !== '') {
            return $token;
        }
        $token = self::generateShareToken();
        $this->setSetting('public_dashboard_token', $token);
        if (!$this->isNewRecord) {
            $this->updateAttributes(['settings_json' => $this->settings_json]);
        }
        return $token;
    }

    public function rotatePublicDashboardToken(): string
    {
        $token = self::generateShareToken();
        $this->setSetting('public_dashboard_token', $token);
        $this->updateAttributes(['settings_json' => $this->settings_json]);
        return $token;
    }

    public function isValidPublicDashboardToken(?string $token): bool
    {
        $token = trim((string)$token);
        $expected = trim((string)$this->getSetting('public_dashboard_token', ''));
        return $this->allowsPublicDashboard()
            && $token !== ''
            && $expected !== ''
            && hash_equals($expected, $token);
    }

    /**
     * Message shown when a second submission is blocked. Uses {formName} as the form title.
     */
    public function getAlreadySubmittedMessage(): string
    {
        $title = (string)$this->title;
        $custom = trim((string)$this->already_submitted_message);
        if ($custom !== '') {
            return strtr($custom, [
                '{formName}' => $title,
                '{form name}' => $title,
                '{title}' => $title,
            ]);
        }

        return Yii::t(
            'ThiscoveryFormsModule.base',
            'You have already submitted {formName}. Multiple submissions are not allowed',
            ['formName' => $title]
        );
    }

    public function hasGuestAnswered(?int $waveId = null, ?int $roundId = null): bool
    {
        return (bool)Yii::$app->session->get($this->guestAnswerSessionKey($waveId, $roundId), false);
    }

    public function markGuestAnswered(?int $waveId = null, ?int $roundId = null): void
    {
        Yii::$app->session->set($this->guestAnswerSessionKey($waveId, $roundId), true);
    }

    public function guestAnswerSessionKey(?int $waveId = null, ?int $roundId = null): string
    {
        $key = 'cf_anon_answered_' . (int)$this->id;
        if ($waveId) {
            $key .= '_w' . $waveId;
        }
        if ($roundId) {
            $key .= '_r' . $roundId;
        }
        return $key;
    }

    /**
     * Sanitize custom CSS before injecting into the fill page.
     */
    public function getSafeCustomCss(): string
    {
        $compiled = (new FormStyleService())->compile(
            is_array($this->style) ? $this->style : []
        );
        $css = trim($compiled . "\n" . (string)$this->custom_css);
        if ($css === '') {
            return '';
        }
        $css = preg_replace('/<\/style/i', '', $css);
        $css = preg_replace('/@import\b/i', '', $css);
        $css = preg_replace('/expression\s*\(/i', '', $css);
        $css = preg_replace('/javascript\s*:/i', '', $css);
        $css = preg_replace('/-moz-binding\s*:/i', '', $css);
        $css = preg_replace('/behavior\s*:/i', '', $css);

        return trim((string)$css);
    }

    public function hasThankYouContent(): bool
    {
        return trim((string)$this->thank_you_content) !== '';
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($this->kind === '' || $this->kind === null) {
            $this->kind = self::KIND_SURVEY;
        }

        // Anonymous forms must be publicly viewable so guests can open the share link.
        if ($this->allow_anonymous && $this->content) {
            $this->content->visibility = Content::VISIBILITY_PUBLIC;
        }

        if ($this->isTemplate()) {
            $this->silentContentCreation = true;
            $this->show_in_menu = 0;
            $this->status = self::STATUS_DRAFT;
        }

        if ($this->isPoll()) {
            $this->allow_edit = 0;
            $this->setSetting('show_results', !empty($this->show_results));
        }

        if ($this->usesWaves() || $this->isConsensus()) {
            $this->allow_multiple = 0;
        }

        if ($this->isProject()) {
            $this->allow_anonymous = 0;
        }

        $this->persistProgrammeSettings();

        return true;
    }

    protected function persistProgrammeSettings(): void
    {
        $source = trim((string)$this->source_language) ?: 'en-GB';
        $this->setSetting('source_language', $source);

        $enabled = $this->enabled_languages;
        if (!is_array($enabled)) {
            $enabled = [$source];
        }
        $enabled = array_values(array_unique(array_filter(array_map('strval', $enabled))));
        if (!in_array($source, $enabled, true)) {
            array_unshift($enabled, $source);
        }
        $this->enabled_languages = $enabled;
        $this->setSetting('enabled_languages', $enabled);

        $mode = (string)$this->identity_mode;
        if (!isset(self::getIdentityModeLabels()[$mode])) {
            $mode = self::IDENTITY_IDENTIFIED;
        }
        $this->setSetting('identity_mode', $mode);
        $this->setSetting('consensus_threshold', max(1, min(100, (int)$this->consensus_threshold)));
        $this->setSetting('freeze_on_consensus', !empty($this->freeze_on_consensus));
        $this->setSetting('require_justification', !empty($this->require_justification));

        $style = is_array($this->style) ? $this->style : [];
        $this->style = (new FormStyleService())->normalize($style);
        $this->setSetting('style', $this->style);
        $this->setSetting('keep_partials', !empty($this->keep_partials));
        $this->persistEnrolSettings();
        $this->setSetting('public_dashboard_enabled', !empty($this->public_dashboard_enabled));
        $this->setSetting('hide_humhub_header', !empty($this->hide_humhub_header));
        if (!empty($this->public_dashboard_enabled) && trim((string)$this->getSetting('public_dashboard_token', '')) === '') {
            $this->setSetting('public_dashboard_token', self::generateShareToken());
        }
        if (trim((string)$this->getSetting('test_token', '')) === '') {
            $this->setSetting('test_token', self::generateShareToken());
        }
    }

    protected function persistEnrolSettings(): void
    {
        $mode = (string)$this->enrol_panel_mode;
        if (!in_array($mode, [self::ENROL_PANEL_NONE, self::ENROL_PANEL_EXISTING, self::ENROL_PANEL_CREATE], true)) {
            $mode = self::ENROL_PANEL_NONE;
        }
        $title = trim((string)$this->enrol_panel_title);
        $panelId = (int)$this->enrol_panel_id;

        if ($mode === self::ENROL_PANEL_CREATE && !$this->isTemplate()) {
            $panel = (new PanelService())->createPanel(
                $title !== '' ? $title : Yii::t('ThiscoveryFormsModule.base', '{title} panel', [
                    'title' => $this->title ?: Yii::t('ThiscoveryFormsModule.base', 'Survey'),
                ]),
                null,
                $this->isGlobal() ? null : ($this->content->contentcontainer_id ?? null)
            );
            if ($panel) {
                $panelId = (int)$panel->id;
                $mode = self::ENROL_PANEL_EXISTING;
                $this->enrol_panel_id = $panelId;
                $this->enrol_panel_mode = $mode;
                if ($this->usesWaves() && !(int)$this->getSetting('panel_id', 0)) {
                    $this->setSetting('panel_id', $panelId);
                }
            }
        }

        $this->setSetting('enrol_panel_mode', $mode);
        $this->setSetting('enrol_panel_id', $panelId);
        $this->setSetting('enrol_panel_title', $title);
        $this->setSetting('log_panel_activity', !empty($this->log_panel_activity));
        if ($this->isSurvey()) {
            $this->setSetting('use_waves', !empty($this->use_waves) && \humhub\modules\thiscoveryForms\Module::wavesEnabledForSurveysStatic());
            $this->use_waves = $this->getSetting('use_waves', false) ? 1 : 0;
        } elseif ($this->isEq5d() || $this->isLongitudinal()) {
            $this->setSetting('use_waves', true);
            $this->use_waves = 1;
        }
        $this->setSetting('invite_email_template_id', (int)$this->invite_email_template_id);
        $this->setSetting('wave_email_template_id', (int)$this->wave_email_template_id);
        $this->setSetting('reminder_email_template_id', (int)$this->reminder_email_template_id);
        $this->setSetting('reminder_days', max(0, (int)$this->reminder_days));
        $this->setSetting('completion_email_template_id', (int)$this->completion_email_template_id);
        if (Yii::$app->request->isPost) {
            if (Yii::$app->request->post('submit_actions') !== null) {
                $this->submit_actions = Yii::$app->request->post('submit_actions');
            }
            if (Yii::$app->request->post('custom_functions') !== null) {
                $this->custom_functions = Yii::$app->request->post('custom_functions');
            }
        }
        $this->setSetting('submit_actions', \humhub\modules\thiscoveryForms\services\FormActionService::normalizeList($this->submit_actions));
        $this->setSetting('custom_functions', \humhub\modules\thiscoveryForms\services\FormActionService::normalizeFunctions($this->custom_functions));
    }

    public function canViewAnswers($user = null): bool
    {
        $user = $user ?: Yii::$app->user->getIdentity();
        if (!$user) {
            return false;
        }

        if ($this->canManage($user)) {
            return true;
        }

        if ($this->answers_visibility === self::ANSWERS_RESPONDENTS && $this->hasUserAnswered($user)) {
            return true;
        }

        if ($this->answers_visibility === self::ANSWERS_PERMISSION) {
            $container = $this->isGlobal() ? null : $this->content->getContainer();
            if ($container instanceof Space) {
                return $container->getPermissionManager($user)->can(ViewAnswers::class);
            }
            return (new PermissionManager(['subject' => $user]))->can(ViewGlobalAnswers::class);
        }

        return false;
    }

    public function allowsEdit(): bool
    {
        return (bool)$this->allow_edit;
    }

    public function canEditOwnAnswer(FormAnswer $answer, $user = null): bool
    {
        $user = $user ?: Yii::$app->user->getIdentity();
        if (!$user || $answer->isAnonymous()) {
            return false;
        }

        if ($this->isProject() && (int)$answer->created_by === (int)$user->id && $answer->isChangesRequested()) {
            return true;
        }

        if (!$this->allowsEdit()) {
            return false;
        }

        if ($this->allowsAnonymous() || $answer->isAnonymous()) {
            return false;
        }

        if (!$this->isOpen()) {
            return false;
        }

        if ($this->isProject() && $answer->isInReview()) {
            return false;
        }

        return (int)$answer->created_by === (int)$user->id && $this->canAnswerPermissionOnly($user);
    }

    public function canAnswerPermissionOnly($user = null): bool
    {
        $user = $user ?: Yii::$app->user->getIdentity();
        if (!$user) {
            return false;
        }

        $container = $this->isGlobal() ? null : $this->content->getContainer();
        if ($container instanceof Space) {
            return $container->getPermissionManager($user)->can(AnswerForm::class);
        }

        return (new PermissionManager(['subject' => $user]))->can(AnswerGlobalForm::class);
    }

    public function hasUserAnswered(?User $user = null): bool
    {
        $user = $user ?: Yii::$app->user->getIdentity();
        if (!$user) {
            return false;
        }

        return FormAnswer::find()
            ->where([
                'form_id' => $this->id,
                'created_by' => $user->id,
                'status' => FormAnswer::STATUS_COMPLETE,
                'is_test' => 0,
            ])
            ->exists();
    }

    public function getUserAnswer(?User $user = null): ?FormAnswer
    {
        $user = $user ?: Yii::$app->user->getIdentity();
        if (!$user) {
            return null;
        }

        return FormAnswer::find()
            ->where([
                'form_id' => $this->id,
                'created_by' => $user->id,
                'status' => FormAnswer::STATUS_COMPLETE,
                'is_test' => 0,
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();
    }

    public function getUserInProgressAnswer(?User $user = null): ?FormAnswer
    {
        $user = $user ?: Yii::$app->user->getIdentity();
        if (!$user) {
            return null;
        }

        return FormAnswer::find()
            ->where([
                'form_id' => $this->id,
                'created_by' => $user->id,
                'status' => FormAnswer::STATUS_IN_PROGRESS,
                'is_test' => 0,
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();
    }

    /**
     * @return static[]
     */
    public static function findShownInMenu(?Space $space = null): array
    {
        $query = static::findLive()->joinWith('content')
            ->andWhere(['custom_form.show_in_menu' => 1, 'custom_form.status' => self::STATUS_OPEN]);

        if ($space) {
            $query->contentContainer($space);
        } else {
            $query->andWhere(['content.contentcontainer_id' => null]);
        }

        return $query->all();
    }

    /**
     * Save field definitions from posted field rows.
     * @param array $rows
     */
    public function saveFieldsFromPost(array $rows): bool
    {
        $existing = [];
        foreach ($this->fields as $field) {
            $existing[$field->id] = $field;
        }

        $keptIds = [];
        $sort = 0;
        $createdMap = []; // tempKey => id for condition wiring
        $answerableKept = 0;

        // PHP reorders numeric $_POST keys (fields[22] before fields[1]).
        // Walk rows in the builder's posted sort_order, then original sequence.
        $orderedRows = [];
        $seq = 0;
        foreach ($rows as $tempKey => $row) {
            if (!is_array($row)) {
                continue;
            }
            $postedSort = $row['sort_order'] ?? '';
            $orderedRows[] = [
                'key' => (string)$tempKey,
                'row' => $row,
                'sort' => ($postedSort !== '' && $postedSort !== null) ? (int)$postedSort : PHP_INT_MAX,
                'seq' => $seq++,
            ];
        }
        usort($orderedRows, static function (array $a, array $b): int {
            if ($a['sort'] !== $b['sort']) {
                return $a['sort'] <=> $b['sort'];
            }
            return $a['seq'] <=> $b['seq'];
        });

        $orderedRows = $this->closeUnclosedQuestionGroups($orderedRows);

        foreach ($orderedRows as $item) {
            $tempKey = $item['key'];
            $row = $item['row'];
            $type = (string)($row['type'] ?? '');
            if ($type === '' || !isset(FormField::getTypeLabels()[$type])) {
                continue;
            }
            $allowed = $this->getAllowedFieldTypes();
            if ($allowed !== null && !in_array($type, $allowed, true)) {
                continue;
            }

            $probeType = new FormField(['type' => $type]);
            if ($this->isPoll() && $probeType->collectsAnswer()) {
                if ($answerableKept >= 1) {
                    continue;
                }
                $answerableKept++;
            }

            $label = trim((string)($row['label'] ?? ''));
            if ($label === '') {
                $label = FormField::defaultLabelForType($type);
            }

            $id = isset($row['id']) && $row['id'] !== '' ? (int)$row['id'] : null;
            $field = ($id && isset($existing[$id])) ? $existing[$id] : new FormField();
            $field->form_id = $this->id;
            $field->label = $label;
            $field->type = $type;
            $field->help_text = $row['help_text'] ?? null;
            $field->required = !empty($row['required']);
            if (FormField::isQuestionGroup($type) || FormField::isGroupEnd($type) || $type === FormField::TYPE_PAGE_BREAK || $type === FormField::TYPE_RICH_TEXT || $type === FormField::TYPE_RESPONDENT_META) {
                $field->required = false;
            }
            if (FormField::isGroupEnd($type) && trim($field->label) === '') {
                $field->label = FormField::defaultLabelForType($type);
            }
            $field->sort_order = $sort++;

            if ($type === FormField::TYPE_RATING) {
                $field->setRatingScale([
                    'min' => $row['rating_min'] ?? 1,
                    'max' => $row['rating_max'] ?? 5,
                    'step' => $row['rating_step'] ?? 1,
                    'lowLabel' => $row['rating_low_label'] ?? '',
                    'highLabel' => $row['rating_high_label'] ?? '',
                    'display' => $row['rating_display'] ?? FormField::RATING_DISPLAY_PILLS,
                ]);
            } elseif ($type === FormField::TYPE_PAGE_BREAK) {
                $branches = [];
                $rawBranches = $row['branches'] ?? [];
                if (is_array($rawBranches)) {
                    foreach ($rawBranches as $branch) {
                        if (!is_array($branch)) {
                            continue;
                        }
                        $branches[] = [
                            'fieldKey' => (string)($branch['fieldKey'] ?? ''),
                            'operator' => (string)($branch['operator'] ?? FormField::OP_EQUALS),
                            'value' => (string)($branch['value'] ?? ''),
                            'gotoPageKey' => (string)($branch['gotoPageKey'] ?? ''),
                        ];
                    }
                }
                $field->setPageBreakConfig([
                    'pageKey' => $row['page_key'] ?? '',
                    'title' => $row['page_title'] ?? '',
                    'branches' => $branches,
                ]);
                $field->required = false;
            } elseif ($type === FormField::TYPE_RICH_TEXT) {
                $richContent = (string)($row['rich_content'] ?? '');
                $field->setRichTextContent($richContent);
                $field->required = false;
            } elseif ($type === FormField::TYPE_HTML) {
                $field->setHtmlConfig([
                    'html' => (string)($row['html_content'] ?? ''),
                    'collect' => !empty($row['html_collect']),
                    'variable' => (string)($row['html_variable'] ?? 'value'),
                    'instructions' => (string)($row['html_instructions'] ?? ''),
                    'required' => !empty($row['html_required']),
                ]);
                $field->required = !empty($row['html_collect']) && !empty($row['html_required']);
            } elseif ($type === FormField::TYPE_GRID_SINGLE || $type === FormField::TYPE_GRID_MULTI) {
                $field->setGridConfig([
                    'rows' => $row['grid_rows'] ?? '',
                    'columns' => $row['grid_columns'] ?? '',
                ]);
            } elseif ($type === FormField::TYPE_BEST_WORST || $type === FormField::TYPE_MAXDIFF) {
                $field->setItemsConfig([
                    'items' => $row['items'] ?? ($row['options'] ?? ''),
                    'setSize' => $row['maxdiff_set_size'] ?? 4,
                    'setCount' => $row['maxdiff_set_count'] ?? 0,
                ]);
            } elseif ($type === FormField::TYPE_DRILLDOWN) {
                $tree = $row['drilldown_tree'] ?? '';
                if (is_string($tree) && $tree !== '' && ($tree[0] ?? '') === '[') {
                    $decoded = json_decode($tree, true);
                    if (is_array($decoded)) {
                        $tree = $decoded;
                    }
                }
                $field->setDrilldownTree($tree);
            } elseif ($type === FormField::TYPE_IMAGE_AREA) {
                $regions = $row['image_regions'] ?? [];
                if (is_string($regions)) {
                    $decoded = json_decode($regions, true);
                    $regions = is_array($decoded) ? $decoded : [];
                }
                $field->setImageAreaConfig([
                    'imageUrl' => $row['image_url'] ?? '',
                    'imageGuid' => $row['image_guid'] ?? '',
                    'mode' => $row['image_mode'] ?? 'select',
                    'multi' => !empty($row['image_multi']),
                    'regions' => is_array($regions) ? $regions : [],
                ]);
            } elseif ($type === FormField::TYPE_RESPONDENT_META) {
                $field->required = false;
                $field->setRespondentMetaKey((string)($row['meta_key'] ?? ''));
            } elseif ($type === FormField::TYPE_PANEL_ATTR) {
                $field->setPanelAttrKey((string)($row['panel_key'] ?? ''));
            } elseif (FormField::isChoiceType($type)) {
                $maxSelect = null;
                if (array_key_exists('max_select', $row) && $row['max_select'] !== '' && $row['max_select'] !== null) {
                    $maxSelect = (int)$row['max_select'];
                }
                $minSelect = null;
                if (array_key_exists('min_select', $row)) {
                    $minSelect = ($row['min_select'] === '' || $row['min_select'] === null) ? 0 : (int)$row['min_select'];
                }
                $minSelectAll = array_key_exists('min_select_all', $row) ? !empty($row['min_select_all']) : null;
                $exclusive = array_key_exists('exclusive_option', $row)
                    ? (string)$row['exclusive_option']
                    : null;
                $field->setOptionsFromText(
                    $row['options'] ?? '',
                    !empty($row['randomize']),
                    $maxSelect,
                    $exclusive,
                    $minSelect,
                    $minSelectAll
                );
            } else {
                $prefill = array_key_exists('prefill_profile', $row)
                    ? trim((string)$row['prefill_profile'])
                    : $field->getPrefillProfileAttribute();
                $field->options_json = $prefill
                    ? json_encode(['prefillProfile' => $prefill], JSON_UNESCAPED_UNICODE)
                    : null;
            }

            $field->setHiddenFromRespondent(!empty($row['hidden']) || $type === FormField::TYPE_RESPONDENT_META);
            $field->setDefaultValue((string)($row['default_value'] ?? ''));
            if ($type === FormField::TYPE_RESPONDENT_META) {
                $field->setRespondentMetaKey((string)($row['meta_key'] ?? $field->getRespondentMetaKey()));
            }
            if ($type === FormField::TYPE_PANEL_ATTR) {
                $field->setPanelAttrKey((string)($row['panel_key'] ?? $field->getPanelAttrKey()));
            }
            if ($field->isHiddenFromRespondent()) {
                $field->required = false;
            }

            // conditions applied in second pass
            $field->condition_field_id = null;
            $field->condition_operator = null;
            $field->condition_value = null;

            if (!$field->save()) {
                return false;
            }

            $keptIds[] = $field->id;
            $createdMap[(string)$tempKey] = $field->id;
            if ($id) {
                $createdMap[(string)$id] = $field->id;
            }
            $importKey = trim((string)($row['import_key'] ?? ''));
            if ($importKey !== '' && !isset($createdMap[$importKey])) {
                $createdMap[$importKey] = $field->id;
            }
        }

        // Second pass: logic, carry-forward, page-break branch field keys
        foreach ($rows as $tempKey => $row) {
            if (!is_array($row)) {
                continue;
            }
            $fieldId = $createdMap[(string)$tempKey] ?? null;
            if (!$fieldId) {
                continue;
            }
            $field = FormField::findOne($fieldId);
            if (!$field) {
                continue;
            }

            $logicRules = $this->remapPostedLogicRules(is_array($row['logic_rules'] ?? null) ? $row['logic_rules'] : [], $createdMap);
            if (!$logicRules && !empty($row['condition_field'])) {
                $condKey = (string)$row['condition_field'];
                $condId = $createdMap[$condKey] ?? (ctype_digit($condKey) ? (int)$condKey : null);
                if ($condId && (int)$condId !== (int)$field->id) {
                    $logicRules[] = [
                        'fieldKey' => (string)$condId,
                        'operator' => $row['condition_operator'] ?? FormField::OP_EQUALS,
                        'value' => (string)($row['condition_value'] ?? ''),
                    ];
                }
            }
            $keep = json_decode((string)($row['logic_keep'] ?? ''), true);
            $keepHasSnapshot = is_array($keep) && array_key_exists('rules', $keep);
            if (!$logicRules && $keepHasSnapshot) {
                $logicRules = $this->remapPostedLogicRules(is_array($keep['rules'] ?? null) ? $keep['rules'] : [], $createdMap);
                if ($logicRules) {
                    $row['logic_action'] = $keep['action'] ?? ($row['logic_action'] ?? 'show');
                    $row['logic_combinator'] = $keep['combinator'] ?? ($row['logic_combinator'] ?? 'and');
                    $row['logic_goto'] = $keep['gotoPageKey'] ?? ($row['logic_goto'] ?? '');
                }
            }
            $goto = (string)($row['logic_goto'] ?? '');
            if ($logicRules || $keepHasSnapshot || !$field->hasCondition()) {
                $field->setLogic([
                    'action' => $row['logic_action'] ?? 'show',
                    'combinator' => $row['logic_combinator'] ?? 'and',
                    'gotoPageKey' => $goto,
                    'rules' => $logicRules,
                ]);
            }

            if (FormField::isCarryForwardType($field->type)) {
                $carryFrom = (string)($row['carry_from'] ?? '');
                if ($carryFrom !== '' && isset($createdMap[$carryFrom])) {
                    $carryFrom = (string)$createdMap[$carryFrom];
                }
                $field->setCarryForward($carryFrom, (string)($row['carry_mode'] ?? FormField::CARRY_SELECTED));
            }

            if ($field->supportsJustification()) {
                $field->setJustification((string)($row['justification'] ?? ''));
            }

            if ($field->type === FormField::TYPE_PAGE_BREAK) {
                $cfg = $field->getPageBreakConfig();
                $resolved = [];
                foreach ($cfg['branches'] as $branch) {
                    $fk = (string)($branch['fieldKey'] ?? '');
                    if (isset($createdMap[$fk])) {
                        $branch['fieldKey'] = (string)$createdMap[$fk];
                    }
                    $resolved[] = $branch;
                }
                $field->setPageBreakConfig([
                    'pageKey' => $cfg['pageKey'],
                    'title' => $cfg['title'],
                    'branches' => $resolved,
                ]);
            }

            $field->setActions($row['actions'] ?? []);

            $field->save(false);
        }

        foreach ($existing as $id => $field) {
            if (!in_array($id, $keptIds, true)) {
                $field->delete();
            }
        }

        $imageGuids = [];
        foreach (FormField::find()->where(['form_id' => $this->id, 'type' => FormField::TYPE_IMAGE_AREA])->all() as $imgField) {
            $guid = trim((string)($imgField->getImageAreaConfig()['imageGuid'] ?? ''));
            if ($guid !== '') {
                $imageGuids[] = $guid;
            }
        }
        if ($imageGuids) {
            try {
                $this->fileManager->attach($imageGuids);
            } catch (\Throwable $e) {
                Yii::warning('Thiscovery Forms image attach failed: ' . $e->getMessage(), 'thiscovery-forms');
            }
        }

        return true;
    }

    /**
     * An unclosed question group must not swallow the rest of the page.
     * Insert a group_end immediately after the group when no closer exists.
     *
     * @param list<array{key:string,row:array,sort:int,seq:int}> $orderedRows
     * @return list<array{key:string,row:array,sort:int,seq:int}>
     */
    private function closeUnclosedQuestionGroups(array $orderedRows): array
    {
        $out = [];
        $n = count($orderedRows);
        $auto = 0;
        for ($i = 0; $i < $n; $i++) {
            $out[] = $orderedRows[$i];
            $type = (string)($orderedRows[$i]['row']['type'] ?? '');
            if ($type !== FormField::TYPE_QUESTION_GROUP) {
                continue;
            }
            $depth = 1;
            $closes = false;
            for ($j = $i + 1; $j < $n; $j++) {
                $nextType = (string)($orderedRows[$j]['row']['type'] ?? '');
                if ($nextType === FormField::TYPE_QUESTION_GROUP) {
                    $depth++;
                } elseif ($nextType === FormField::TYPE_GROUP_END) {
                    $depth--;
                    if ($depth === 0) {
                        $closes = true;
                        break;
                    }
                } elseif ($nextType === FormField::TYPE_PAGE_BREAK) {
                    break;
                }
            }
            if (!$closes) {
                $out[] = $this->syntheticGroupEndRow($auto++);
            }
        }
        return $out;
    }

    /**
     * @return array{key:string,row:array,sort:int,seq:int}
     */
    private function syntheticGroupEndRow(int $autoIndex): array
    {
        return [
            'key' => 'group_end_auto_' . $autoIndex,
            'row' => [
                'type' => FormField::TYPE_GROUP_END,
                'label' => FormField::defaultLabelForType(FormField::TYPE_GROUP_END),
                'required' => '',
            ],
            'sort' => PHP_INT_MAX,
            'seq' => 100000 + $autoIndex,
        ];
    }

    /**
     * @param array $rawRules
     * @param array<string,int> $createdMap
     * @return list<array{fieldKey:string,operator:string,value:string}>
     */
    private function remapPostedLogicRules(array $rawRules, array $createdMap): array
    {
        $logicRules = [];
        foreach ($rawRules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $fk = (string)($rule['fieldKey'] ?? '');
            if ($fk === '') {
                continue;
            }
            if (isset($createdMap[$fk])) {
                $fk = (string)$createdMap[$fk];
            }
            $logicRules[] = [
                'fieldKey' => $fk,
                'operator' => $rule['operator'] ?? FormField::OP_EQUALS,
                'value' => LogicEngine::normalizeRuleValue($rule['value'] ?? ''),
            ];
        }
        return $logicRules;
    }

    /**
     * Users to notify on new submission: author + managers.
     * @return User[]
     */
    public function getNotificationTargets(): array
    {
        $users = [];
        $author = $this->content->createdBy;
        if ($author) {
            $users[$author->id] = $author;
        }

        $container = $this->isGlobal() ? null : $this->content->getContainer();
        if ($container instanceof Space) {
            foreach ($container->getMembershipUser()->active()->all() as $member) {
                if ($this->canManage($member)) {
                    $users[$member->id] = $member;
                }
            }
        } else {
            $adminGroup = Group::getAdminGroup();
            if ($adminGroup) {
                foreach ($adminGroup->getUsers()->active()->all() as $admin) {
                    $users[$admin->id] = $admin;
                }
            }
        }

        return array_values($users);
    }
}
