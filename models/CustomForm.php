<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\modules\thiscoveryForms\services\formula\FormulaRefs;
use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\content\models\Content;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\permissions\AnswerForm;
use humhub\modules\thiscoveryForms\permissions\AnswerGlobalForm;
use humhub\modules\thiscoveryForms\permissions\CreateForm;
use humhub\modules\thiscoveryForms\permissions\CreateGlobalForm;
use humhub\modules\thiscoveryForms\permissions\ManageForm;
use humhub\modules\thiscoveryForms\permissions\ManageGlobalForm;
use humhub\modules\thiscoveryForms\permissions\ViewAnswers;
use humhub\modules\thiscoveryForms\permissions\ViewGlobalAnswers;
use humhub\modules\thiscoveryForms\services\DisplaySettings;
use humhub\modules\thiscoveryForms\services\FormActionService;
use humhub\modules\thiscoveryForms\services\FormPager;
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
use yii\helpers\Html;

/**
 * @property int $id
 * @property string|null $fill_token random value in the public fill link
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
 * @property int|null $current_edition_id
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

    public const COMPLETION_MESSAGE = 'message';
    public const COMPLETION_REDIRECT = 'redirect';

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

    /** @var string Inclusive start of the Delphi agree band. Empty keeps the single most common code. */
    public $consensus_agree_from = '';

    /** @var string Inclusive end of the Delphi agree band. */
    public $consensus_agree_to = '';

    /** @var string Inclusive start of the Delphi disagree band. */
    public $consensus_disagree_from = '';

    /** @var string Inclusive end of the Delphi disagree band. */
    public $consensus_disagree_to = '';

    /** @var string Comma-separated codes left out of the consensus share. */
    public $consensus_exclude_codes = '';

    /** @var int|null Edition hydrated onto this in-memory form. Not a column. */
    public $fillEditionId;

    /** @var bool The published edition could not be loaded. Not a column. */
    public $editionLoadFailed = false;

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
    public $keep_partials = 1;

    /** @var string none|existing|create */
    public $enrol_panel_mode = 'none';

    /** @var int */
    public $enrol_panel_id = 0;

    /** @var string */
    public $enrol_panel_title = '';

    /** @var int|bool */
    public $log_panel_activity = 0;

    /** @var int|bool Opt in to waves (survey / longitudinal / EQ-5D) */
    public $use_waves = 0;

    /** @var string survey|panel */
    public $wave_scope = Module::WAVE_SCOPE_SURVEY;

    /** @var int|string|null Linked appearance theme id, or '' for custom */
    public $theme_id = '';

    /** @var array Display chrome overrides ('' = inherit global) */
    public $display = [];

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

    /** @var string message|redirect — end of survey behaviour */
    public $completion_mode = self::COMPLETION_MESSAGE;

    /** @var int|bool Show button under thank-you message */
    public $completion_button_enabled = 1;

    /** @var string */
    public $completion_button_label = '';

    /** @var string Empty = reopen this form */
    public $completion_button_url = '';

    /** @var string Absolute or site-relative URL when completion_mode=redirect */
    public $completion_redirect_url = '';

    /** @var int|bool Show button under already-submitted message */
    public $already_submitted_button_enabled = 0;

    /** @var string */
    public $already_submitted_button_label = '';

    /** @var string Empty = forms list / home as resolved at runtime */
    public $already_submitted_button_url = '';

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
            [['description', 'thank_you_content', 'already_submitted_message', 'custom_css'], 'string'],
            [['custom_css'], 'validateCustomCss'],
            [['status'], 'in', 'range' => [self::STATUS_DRAFT, self::STATUS_OPEN, self::STATUS_CLOSED]],
            [['status'], 'validatePublishedBeforeOpen'],
            [['current_edition_id'], 'integer'],
            [['allow_multiple', 'show_in_menu', 'allow_anonymous', 'allow_edit', 'allow_resume', 'is_template', 'show_results', 'freeze_on_consensus', 'require_justification', 'public_dashboard_enabled', 'hide_humhub_header', 'keep_partials', 'log_panel_activity', 'use_waves'], 'boolean'],
            [['wave_scope'], 'in', 'range' => [Module::WAVE_SCOPE_SURVEY, Module::WAVE_SCOPE_PANEL]],
            [['theme_id'], 'safe'],
            [['display', 'style', 'submit_actions', 'custom_functions', 'enabled_languages'], 'safe'],
            [['completion_mode'], 'in', 'range' => [self::COMPLETION_MESSAGE, self::COMPLETION_REDIRECT]],
            [['completion_button_enabled', 'already_submitted_button_enabled'], 'boolean'],
            [['completion_button_label', 'already_submitted_button_label'], 'string', 'max' => 120],
            [['completion_button_url', 'completion_redirect_url', 'already_submitted_button_url'], 'string', 'max' => 2000],
            [['completion_redirect_url'], 'validateCompletionRedirectUrl'],
            [['submit_actions'], 'validateSubmitActions', 'skipOnEmpty' => false],
            [['allow_edit'], 'default', 'value' => 1],
            [['allow_resume'], 'default', 'value' => 0],
            [['is_template'], 'default', 'value' => 0],
            [['public_dashboard_enabled', 'hide_humhub_header'], 'default', 'value' => 0],
            [['keep_partials'], 'default', 'value' => 1],
            [['source_template_id', 'consensus_threshold'], 'integer'],
            [['folder_id'], 'default', 'value' => null],
            [['folder_id'], 'integer'],
            [['folder_id'], 'exist', 'skipOnEmpty' => true, 'targetClass' => FormFolder::class, 'targetAttribute' => 'id'],
            [['title'], 'validateOwnedReferences', 'skipOnEmpty' => false],
            [['consensus_threshold'], 'integer', 'min' => 1, 'max' => 100],
            [['source_language', 'identity_mode'], 'string', 'max' => 32],
            [['consensus_agree_from', 'consensus_agree_to', 'consensus_disagree_from', 'consensus_disagree_to', 'consensus_exclude_codes'], 'safe'],
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
            'completion_mode' => Yii::t('ThiscoveryFormsModule.base', 'After submission'),
            'completion_button_enabled' => Yii::t('ThiscoveryFormsModule.base', 'Show button on thank-you page'),
            'completion_button_label' => Yii::t('ThiscoveryFormsModule.base', 'Thank-you button label'),
            'completion_button_url' => Yii::t('ThiscoveryFormsModule.base', 'Thank-you button URL'),
            'completion_redirect_url' => Yii::t('ThiscoveryFormsModule.base', 'Redirect URL'),
            'already_submitted_button_enabled' => Yii::t('ThiscoveryFormsModule.base', 'Show button on already-submitted page'),
            'already_submitted_button_label' => Yii::t('ThiscoveryFormsModule.base', 'Already-submitted button label'),
            'already_submitted_button_url' => Yii::t('ThiscoveryFormsModule.base', 'Already-submitted button URL'),
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
        $query = $this->hasMany(FormField::class, ['form_id' => 'id'])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
        if (FormField::supportsSoftDelete()) {
            $query->andWhere(['custom_form_field.deleted_at' => null]);
        }
        return $query;
    }

    /**
     * Live questions plus ones removed after answers were stored.
     * Use this for answer detail, export, snapshots of past responses, and the dashboard.
     */
    public function getAllFields(): ActiveQuery
    {
        $query = $this->hasMany(FormField::class, ['form_id' => 'id']);
        if (FormField::supportsSoftDelete()) {
            $query->orderBy(new \yii\db\Expression('(custom_form_field.deleted_at IS NOT NULL) ASC, custom_form_field.sort_order ASC, custom_form_field.id ASC'));
        } else {
            $query->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
        }
        return $query;
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

    /**
     * Whether this fill must be stored with no respondent identity.
     * Preview and a fully anonymous form (when enforcement is on) are always anonymous.
     * allowsAnonymous() only decides whether a guest may open the form.
     */
    public function submitAsAnonymous(bool $isPreview, bool $guestTokenAccess): bool
    {
        if ($isPreview) {
            return true;
        }
        if (Module::identityEnforced() && $this->hidesIdentityFromManagers()) {
            return true;
        }
        return $this->allowsAnonymous() || (Yii::$app->user->isGuest && $guestTokenAccess);
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

    /**
     * Agree and disagree bands for a Delphi round. An empty agree band keeps
     * the single most common code. Exclude codes are dropped from the share.
     *
     * @return array{agree_from:?string,agree_to:?string,disagree_from:?string,disagree_to:?string,exclude:string[]}
     */
    public function getConsensusBands(): array
    {
        $clean = function (string $key): ?string {
            $value = trim((string)$this->getSetting($key, ''));
            return $value === '' ? null : $value;
        };
        $exclude = [];
        foreach (preg_split('/\s*,\s*/', (string)$this->getSetting('consensus_exclude_codes', '')) ?: [] as $code) {
            $code = trim((string)$code);
            if ($code !== '') {
                $exclude[] = $code;
            }
        }

        return [
            'agree_from' => $clean('consensus_agree_from'),
            'agree_to' => $clean('consensus_agree_to'),
            'disagree_from' => $clean('consensus_disagree_from'),
            'disagree_to' => $clean('consensus_disagree_to'),
            'exclude' => $exclude,
        ];
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

    public function containerId(): ?int
    {
        if ($this->isGlobal()) {
            return null;
        }
        $id = (int)($this->content->contentcontainer_id ?? 0);
        return $id > 0 ? $id : null;
    }

    /**
     * A record with no container is global and may be used from a space.
     * A record with a container may be used only from that same container.
     */
    public function referenceAllowed(?int $contentcontainerId): bool
    {
        $other = $contentcontainerId ? (int)$contentcontainerId : null;
        $mine = $this->containerId();
        if ($other === null || $other === $mine) {
            return true;
        }
        return false;
    }

    private function emailTemplateAllowed(\humhub\modules\thiscoveryForms\services\EmailTemplateService $mail, int $templateId): bool
    {
        if ($mail->findOwned($templateId, $this->containerId())) {
            return true;
        }
        return $this->containerId() !== null && (bool)$mail->findOwned($templateId, null);
    }

    public function validateOwnedReferences(): void
    {
        if ($this->folder_id) {
            $folder = FormFolder::findOne((int)$this->folder_id);
            $folderContainer = $folder && $folder->contentcontainer_id ? (int)$folder->contentcontainer_id : null;
            if (!$folder || $folderContainer !== $this->containerId()) {
                $this->addError('folder_id', Yii::t('ThiscoveryFormsModule.base', 'That folder is not in this space.'));
            } elseif ($this->isAttributeChanged('folder_id') && Yii::$app->has('user', true) && !Yii::$app->user->isGuest
                && !\humhub\modules\thiscoveryForms\services\FolderService::canCreateIn($folder)) {
                // Moving a form into a folder needs that folder's create or manage access; a posted
                // folder_id on a studio save no longer bypasses the folder ACL (V3-50).
                $this->addError('folder_id', Yii::t('ThiscoveryFormsModule.base', 'You cannot put forms in that folder.'));
            }
        }
        if ((int)$this->enrol_panel_id > 0) {
            $panel = FormPanel::findOne((int)$this->enrol_panel_id);
            $panelContainer = $panel && $panel->contentcontainer_id ? (int)$panel->contentcontainer_id : null;
            if (!$panel || $panelContainer !== $this->containerId()) {
                $this->addError('enrol_panel_id', Yii::t('ThiscoveryFormsModule.base', 'That panel is not in this space.'));
            }
        }
        $templates = [
            'invite_email_template_id',
            'wave_email_template_id',
            'reminder_email_template_id',
            'completion_email_template_id',
        ];
        $mail = new \humhub\modules\thiscoveryForms\services\EmailTemplateService();
        foreach ($templates as $attribute) {
            $templateId = (int)$this->$attribute;
            if ($templateId > 0 && !$this->emailTemplateAllowed($mail, $templateId)) {
                $this->addError($attribute, Yii::t('ThiscoveryFormsModule.base', 'That email template is not in this space.'));
            }
        }
        if ((int)$this->source_template_id > 0) {
            $template = static::findOne((int)$this->source_template_id);
            $templateContainer = $template && $template->content && $template->content->contentcontainer_id
                ? (int)$template->content->contentcontainer_id
                : null;
            if (!$template || !$template->isTemplate() || !$this->referenceAllowed($templateContainer)) {
                $this->addError('source_template_id', Yii::t('ThiscoveryFormsModule.base', 'That template is not available here.'));
            }
        }
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

    /**
     * A module route for this form. A space form stays in its space. A global form uses the site route.
     *
     * @param array $route
     */
    public function actionUrl(array $route): string
    {
        $container = null;
        if ($this->content && !$this->content->isNewRecord) {
            try {
                $container = $this->content->container;
            } catch (\Throwable $e) {
                $container = null;
            }
        }
        if ($container) {
            $params = $route;
            $path = (string)array_shift($params);
            return $container->createUrl($path, $params);
        }
        return \yii\helpers\Url::to($route);
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
     * Repeating waves when the form has Use waves enabled (surveys, longitudinal, EQ-5D).
     */
    public function usesWaves(): bool
    {
        if ($this->isPoll() || $this->isConsensus() || $this->isProject() || $this->isFeedback()) {
            return false;
        }
        if ($this->isSurvey() || $this->isLongitudinal() || $this->isEq5d()) {
            return !empty($this->use_waves);
        }
        return false;
    }

    public function getWaveScope(): string
    {
        $raw = (string)$this->getSetting('wave_scope', Module::WAVE_SCOPE_SURVEY);
        return $raw === Module::WAVE_SCOPE_PANEL ? Module::WAVE_SCOPE_PANEL : Module::WAVE_SCOPE_SURVEY;
    }

    public function wavesLiveOnPanel(): bool
    {
        return $this->usesWaves() && $this->getWaveScope() === Module::WAVE_SCOPE_PANEL;
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
     * Declared URL parameters. A query value outside the allowed list is empty.
     *
     * @return list<array{name:string,values:list<string>}>
     */
    public function declaredUrlParams(): array
    {
        $raw = $this->getSetting('url_params', []);
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row) || count($out) >= 20) {
                continue;
            }
            $name = trim((string)($row['name'] ?? ''));
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                continue;
            }
            $values = [];
            foreach ((array)($row['values'] ?? []) as $value) {
                $value = trim((string)$value);
                if ($value !== '' && mb_strlen($value) <= 200) {
                    $values[] = $value;
                }
            }
            $values = array_values(array_unique($values));
            if ($values === []) {
                continue;
            }
            $out[] = ['name' => $name, 'values' => $values];
        }
        return $out;
    }

    /**
     * @param array<int|string,mixed> $values
     * @param array<string,mixed> $query
     */
    public function applyDeclaredUrlParams(array &$values, array $query): void
    {
        foreach ($this->declaredUrlParams() as $param) {
            $got = trim((string)($query[$param['name']] ?? ''));
            if ($got === '' || mb_strlen($got) > 200 || !in_array($got, $param['values'], true)) {
                $got = '';
            }
            $values['url:' . $param['name']] = $got;
        }
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
            $this->use_waves = 1;
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
        $this->syncSettingsAttributes();
    }

    /**
     * Copy settings_json into virtual attributes used by fill/studio.
     * Safe to call after an in-memory snapshot hydrate.
     */
    public function syncSettingsAttributes(): void
    {
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
        $this->consensus_agree_from = (string)$this->getSetting('consensus_agree_from', '');
        $this->consensus_agree_to = (string)$this->getSetting('consensus_agree_to', '');
        $this->consensus_disagree_from = (string)$this->getSetting('consensus_disagree_from', '');
        $this->consensus_disagree_to = (string)$this->getSetting('consensus_disagree_to', '');
        $this->consensus_exclude_codes = (string)$this->getSetting('consensus_exclude_codes', '');
        $this->freeze_on_consensus = $this->getSetting('freeze_on_consensus', true) ? 1 : 0;
        $this->require_justification = $this->getSetting('require_justification', false) ? 1 : 0;
        $style = $this->getSetting('style', []);
        $this->style = is_array($style) ? $style : [];
        $this->public_dashboard_enabled = $this->getSetting('public_dashboard_enabled', false) ? 1 : 0;
        $this->hide_humhub_header = $this->getSetting('hide_humhub_header', false) ? 1 : 0;
        $this->keep_partials = $this->getSetting('keep_partials', true) ? 1 : 0;
        $this->enrol_panel_mode = (string)$this->getSetting('enrol_panel_mode', self::ENROL_PANEL_NONE) ?: self::ENROL_PANEL_NONE;
        $this->enrol_panel_id = (int)$this->getSetting('enrol_panel_id', 0);
        $this->enrol_panel_title = (string)$this->getSetting('enrol_panel_title', '');
        $this->log_panel_activity = $this->getSetting('log_panel_activity', false) ? 1 : 0;
        $this->use_waves = $this->getSetting('use_waves', false) ? 1 : 0;
        $scope = (string)$this->getSetting('wave_scope', Module::WAVE_SCOPE_SURVEY);
        $this->wave_scope = $scope === Module::WAVE_SCOPE_PANEL ? Module::WAVE_SCOPE_PANEL : Module::WAVE_SCOPE_SURVEY;
        $themeId = $this->getSetting('theme_id', null);
        if ($themeId === null || $themeId === false) {
            if ($this->isNewRecord) {
                $defaultTheme = FormTheme::findDefault();
                $this->theme_id = $defaultTheme ? (string)$defaultTheme->id : '';
            } else {
                $this->theme_id = '';
            }
        } else {
            $this->theme_id = (string)$themeId;
        }
        $this->display = DisplaySettings::formOverlay($this);
        $this->invite_email_template_id = (int)$this->getSetting('invite_email_template_id', 0);
        $this->wave_email_template_id = (int)$this->getSetting('wave_email_template_id', 0);
        $this->reminder_email_template_id = (int)$this->getSetting('reminder_email_template_id', 0);
        $this->reminder_days = (int)$this->getSetting('reminder_days', 0);
        $this->completion_email_template_id = (int)$this->getSetting('completion_email_template_id', 0);
        $this->submit_actions = \humhub\modules\thiscoveryForms\services\FormActionService::normalizeList($this->getSetting('submit_actions', []));
        $this->custom_functions = \humhub\modules\thiscoveryForms\services\FormActionService::normalizeFunctions($this->getSetting('custom_functions', []));
        $this->loadCompletionSettings();
        if ($this->isTemplate()) {
            $this->silentContentCreation = true;
        }
    }

    protected function loadCompletionSettings(): void
    {
        $mode = (string)$this->getSetting('completion_mode', self::COMPLETION_MESSAGE);
        $this->completion_mode = $mode === self::COMPLETION_REDIRECT ? self::COMPLETION_REDIRECT : self::COMPLETION_MESSAGE;
        $this->completion_button_enabled = $this->getSetting('completion_button_enabled', true) ? 1 : 0;
        $this->completion_button_label = (string)$this->getSetting('completion_button_label', '');
        $this->completion_button_url = (string)$this->getSetting('completion_button_url', '');
        $this->completion_redirect_url = (string)$this->getSetting('completion_redirect_url', '');
        $this->already_submitted_button_enabled = $this->getSetting('already_submitted_button_enabled', false) ? 1 : 0;
        $this->already_submitted_button_label = (string)$this->getSetting('already_submitted_button_label', '');
        $this->already_submitted_button_url = (string)$this->getSetting('already_submitted_button_url', '');
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

        if ($insert && $this->getSetting('theme_id', null) === null) {
            $defaultTheme = FormTheme::findDefault();
            if ($defaultTheme) {
                $this->setSetting('theme_id', (int)$defaultTheme->id);
                $this->theme_id = (string)$defaultTheme->id;
            }
        }

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

        if (!$insert && array_key_exists('status', $changedAttributes)
            && class_exists(\humhub\modules\thiscoveryForms\services\FormVersionService::class)
            && \humhub\modules\thiscoveryForms\services\FormVersionService::isAvailable()) {
            try {
                (new \humhub\modules\thiscoveryForms\services\FormVersionService())->onAvailabilityChanged(
                    $this,
                    (int)$changedAttributes['status'],
                    (int)$this->status
                );
            } catch (\Throwable $e) {
                Yii::warning('Thiscovery Forms open-period tracking failed: ' . $e->getMessage(), 'thiscovery-forms');
            }
        }
    }

    /**
     * Status stays as it is until a published edition exists.
     */
    public function validatePublishedBeforeOpen($attribute): void
    {
        if ($this->isTemplate()) {
            return;
        }
        if (!\humhub\modules\thiscoveryForms\services\FormVersionService::isAvailable()) {
            return;
        }
        if ((new \humhub\modules\thiscoveryForms\services\FormVersionService())->hasPublishedEdition($this)) {
            return;
        }
        $status = (int)$this->$attribute;
        if ($this->isNewRecord) {
            if ($status === self::STATUS_DRAFT) {
                return;
            }
        } elseif ($status === (int)$this->getOldAttribute($attribute)) {
            return;
        }
        $this->addError(
            $attribute,
            Yii::t(
                'ThiscoveryFormsModule.base',
                'Publish an edition on the Versions tab before changing the form status.'
            )
        );
    }

    /** In the trash: the content is in HumHub's deleted state (GOV-7). */
    public function isTrashed(): bool
    {
        return $this->content && (int)$this->content->state === Content::STATE_DELETED;
    }

    /**
     * "Delete" moves the form to the trash: it closes, disappears from lists, fill links and
     * scheduled sends, and keeps every answer. It can be restored, and only a separate
     * permanent delete removes it (GOV-7).
     */
    public function moveToTrash(?string $reason = null): bool
    {
        if (!$this->content || $this->isTrashed()) {
            return false;
        }
        $previous = (int)$this->content->state;
        $tx = Yii::$app->db->beginTransaction();
        try {
            Content::updateAll(['state' => Content::STATE_DELETED], ['id' => (int)$this->content->id]);
            $this->content->state = Content::STATE_DELETED;
            if ((int)$this->status === self::STATUS_OPEN) {
                $this->status = self::STATUS_CLOSED;
                $this->save(false, ['status']);
            }
            $this->logLifecycle('trash', $previous, $reason);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        return true;
    }

    public function restoreFromTrash(): bool
    {
        if (!$this->isTrashed()) {
            return false;
        }
        $last = self::lifecycleLog((int)$this->id)[0] ?? null;
        $state = $last && $last['action'] === 'trash' && $last['previous_state'] !== null
            ? (int)$last['previous_state']
            : Content::STATE_PUBLISHED;
        Content::updateAll(['state' => $state], ['id' => (int)$this->content->id]);
        $this->content->state = $state;
        // It comes back closed; the manager reopens it when ready.
        $this->logLifecycle('restore', Content::STATE_DELETED, null);
        return true;
    }

    /** Permanent delete from the trash only; refused while consent evidence exists. */
    public function purge(?string $reason = null): bool
    {
        if (!$this->isTrashed()) {
            $this->addError('id', Yii::t('ThiscoveryFormsModule.base', 'Move the form to the trash before deleting it permanently.'));
            return false;
        }
        $title = (string)$this->title;
        $id = (int)$this->id;
        $answers = (int)FormAnswer::find()->where(['form_id' => $id, 'is_test' => 0])->count();
        if (!$this->hardDelete()) {
            return false;
        }
        self::writeLifecycle($id, $title, 'purge', Content::STATE_DELETED, $answers, $reason);
        return true;
    }

    /**
     * @return array<int, array<string,mixed>> newest first
     */
    public static function lifecycleLog(int $formId): array
    {
        if (Yii::$app->db->schema->getTableSchema('{{%custom_form_lifecycle_log}}', true) === null) {
            return [];
        }
        return (new \yii\db\Query())->from('{{%custom_form_lifecycle_log}}')->where(['form_id' => $formId])->orderBy(['id' => SORT_DESC])->all();
    }

    private function logLifecycle(string $action, ?int $previous, ?string $reason): void
    {
        $answers = (int)FormAnswer::find()->where(['form_id' => (int)$this->id, 'is_test' => 0])->count();
        self::writeLifecycle((int)$this->id, (string)$this->title, $action, $previous, $answers, $reason);
    }

    private static function writeLifecycle(int $formId, string $title, string $action, ?int $previous, int $answers, ?string $reason): void
    {
        if (Yii::$app->db->schema->getTableSchema('{{%custom_form_lifecycle_log}}', true) === null) {
            return;
        }
        Yii::$app->db->createCommand()->insert('{{%custom_form_lifecycle_log}}', [
            'form_id' => $formId,
            'title' => mb_substr($title, 0, 255),
            'action' => $action,
            'previous_state' => $previous,
            'answers' => $answers,
            'actor_id' => Yii::$app->has('user', true) && !Yii::$app->user->isGuest ? (int)Yii::$app->user->id : null,
            'reason' => $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 255) : null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ])->execute();
    }

    /**
     * Throws 404 for a form in the trash, so fill links, the studio and every manager screen
     * treat it as gone until it is restored (GOV-7).
     */
    public static function assertNotTrashed(?CustomForm $form): void
    {
        if ($form && $form->isTrashed()) {
            throw new \yii\web\NotFoundHttpException();
        }
    }

    public function beforeDelete()
    {
        if (!parent::beforeDelete()) {
            return false;
        }
        // Consent records are evidence that someone agreed; deleting the form must not destroy
        // them (V3-57). Such a form is closed or archived instead.
        if ($this->hasConsentEvidence()) {
            $this->addError('id', Yii::t('ThiscoveryFormsModule.base', 'This form holds consent records, which must be kept. Close or archive the form instead of deleting it.'));
            return false;
        }
        $this->deleteDependentRows();

        foreach (FormAnswer::find()->where(['form_id' => $this->id])->all() as $answer) {
            $answer->delete();
        }
        foreach ($this->getAllFields()->all() as $field) {
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

    private function hasConsentEvidence(): bool
    {
        if (Yii::$app->db->schema->getTableSchema('{{%custom_form_consent_record}}', true) === null) {
            return false;
        }
        return (new \yii\db\Query())->from(['r' => '{{%custom_form_consent_record}}'])
            ->leftJoin(['a' => FormAnswer::tableName()], 'a.id = r.answer_id')
            ->where(['r.form_id' => (int)$this->id])
            ->andWhere(['or', ['r.answer_id' => null], ['a.is_test' => 0]])
            ->andWhere(['<>', 'r.signature_method', 'legacy'])
            ->exists();
    }

    /**
     * A hard delete removes the rows that only make sense with this form, so nothing is left
     * orphaned: quotas, allocation, orders, integrity, audit and erasure rows, tokens, consent
     * documents and test consent (V3-57). Tables a migration has not created yet are skipped.
     */
    private function deleteDependentRows(): void
    {
        $db = Yii::$app->db;
        $has = static fn(string $table): bool => $db->schema->getTableSchema($table, true) !== null;
        $formId = (int)$this->id;
        $answerIds = array_map('intval', FormAnswer::find()->select('id')->where(['form_id' => $formId])->column());
        $byAnswer = [
            '{{%custom_form_arm_assignment}}', '{{%custom_form_arm_override}}', '{{%custom_form_presentation}}',
            '{{%custom_form_answer_audit}}', 'custom_form_integrity_meta', 'custom_form_identity_repair_log',
        ];
        if ($answerIds) {
            foreach ($byAnswer as $table) {
                if ($has($table)) {
                    $db->createCommand()->delete($table, ['answer_id' => $answerIds])->execute();
                }
            }
        }
        if ($has('{{%custom_form_quota}}')) {
            $quotaIds = (new \yii\db\Query())->select('id')->from('{{%custom_form_quota}}')->where(['form_id' => $formId])->column();
            if ($quotaIds) {
                foreach (['{{%custom_form_quota_counter}}', '{{%custom_form_quota_accept}}', '{{%custom_form_quota_audit}}', '{{%custom_form_quota_reservation}}', '{{%custom_form_quota_i18n}}'] as $table) {
                    if ($has($table)) {
                        $db->createCommand()->delete($table, ['quota_id' => $quotaIds])->execute();
                    }
                }
                $db->createCommand()->delete('{{%custom_form_quota}}', ['id' => $quotaIds])->execute();
            }
        }
        if ($has('{{%custom_form_consent_document}}')) {
            $docIds = (new \yii\db\Query())->select('id')->from('{{%custom_form_consent_document}}')->where(['form_id' => $formId])->column();
            $recordIds = $has('{{%custom_form_consent_record}}')
                ? (new \yii\db\Query())->select('id')->from('{{%custom_form_consent_record}}')->where(['form_id' => $formId])->column()
                : [];
            if ($recordIds) {
                foreach (['{{%custom_form_consent_withdrawal}}', '{{%custom_form_consent_audit}}'] as $table) {
                    if ($has($table)) {
                        $db->createCommand()->delete($table, ['record_id' => $recordIds])->execute();
                    }
                }
                $db->createCommand()->delete('{{%custom_form_consent_record}}', ['id' => $recordIds])->execute();
            }
            if ($docIds) {
                foreach (['{{%custom_form_consent_item}}', '{{%custom_form_consent_i18n}}', '{{%custom_form_consent_file}}'] as $table) {
                    if ($has($table)) {
                        $db->createCommand()->delete($table, ['document_id' => $docIds])->execute();
                    }
                }
                $db->createCommand()->delete('{{%custom_form_consent_document}}', ['id' => $docIds])->execute();
            }
        }
        foreach ([
            '{{%custom_form_consent_requirement}}', '{{%custom_form_quota_allowhost}}', '{{%custom_form_arm_allocation}}',
            '{{%custom_form_rotate_seq}}', '{{%custom_form_erasure}}', 'custom_form_access_token', 'custom_form_integrity_audit',
            '{{%custom_form_admin_task}}', '{{%form_email_send}}',
        ] as $table) {
            if ($has($table) && isset($db->schema->getTableSchema($table)->columns['form_id'])) {
                $db->createCommand()->delete($table, ['form_id' => $formId])->execute();
            }
        }
    }

    protected function syncContentState(): void
    {
        try {
            if (!$this->content || $this->content->isNewRecord) {
                return;
            }
            // Trash sets the deleted state, then closes the form. Closing must not publish it again.
            if ((int)$this->content->state === Content::STATE_DELETED) {
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

    /**
     * The value in the public fill link. A new form receives one on save.
     */
    public function getFillToken(): string
    {
        if (!$this->hasAttribute('fill_token')) {
            return '';
        }
        $token = trim((string)$this->fill_token);
        if ($token !== '') {
            return $token;
        }
        $token = self::generateShareToken();
        $this->fill_token = $token;
        if (!$this->isNewRecord) {
            $this->updateAttributes(['fill_token' => $token]);
        }
        return $token;
    }

    /** Replaces the public fill link. The previous link no longer opens the form. */
    public function rotateFillToken(): string
    {
        $token = self::generateShareToken();
        $this->fill_token = $token;
        if (!$this->isNewRecord && $this->hasAttribute('fill_token')) {
            $this->updateAttributes(['fill_token' => $token]);
        }
        return $token;
    }

    public static function findByFillToken(string $token): ?self
    {
        $token = trim($token);
        if ($token === '' || !preg_match('/^[A-Za-z0-9_-]{20,64}$/', $token)) {
            return null;
        }
        $schema = static::getTableSchema();
        if ($schema === null || !isset($schema->columns['fill_token'])) {
            return null;
        }
        return static::find()->andWhere(['fill_token' => $token])->one();
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
     * May contain rich-text HTML when set via the editor.
     */
    public function getAlreadySubmittedMessage(): string
    {
        $title = (string)$this->title;
        $custom = trim((string)$this->already_submitted_message);
        if ($custom !== '') {
            return strtr($custom, [
                '{formName}' => Html::encode($title),
                '{form name}' => Html::encode($title),
                '{title}' => Html::encode($title),
            ]);
        }

        return Yii::t(
            'ThiscoveryFormsModule.base',
            'You have already submitted {formName}. Multiple submissions are not allowed',
            ['formName' => $title]
        );
    }

    public function hasCustomAlreadySubmittedMessage(): bool
    {
        return trim((string)$this->already_submitted_message) !== '';
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
     * Merges linked theme tokens/CSS under form overrides.
     */
    public function getSafeCustomCss(): string
    {
        $theme = $this->resolveTheme();
        $baseStyle = $theme ? $theme->getStyle() : [];
        $formStyle = is_array($this->style) ? $this->style : [];
        $merged = (new FormStyleService())->mergeStyles($baseStyle, $formStyle);
        $compiled = (new FormStyleService())->compile($merged);
        $chunks = [$compiled];
        if ($theme && trim((string)$theme->custom_css) !== '') {
            $chunks[] = (string)$theme->custom_css;
        }
        $chunks[] = (string)$this->custom_css;
        $css = trim(implode("\n", array_filter(array_map('trim', $chunks))));
        if ($css === '') {
            return '';
        }
        $css = preg_replace('/<\/style/i', '', $css);
        $css = preg_replace('/@import\b/i', '', $css);
        $css = preg_replace('/expression\s*\(/i', '', $css);
        $css = preg_replace('/javascript\s*:/i', '', $css);
        $css = preg_replace('/-moz-binding\s*:/i', '', $css);
        $css = preg_replace('/behavior\s*:/i', '', $css);
        $css = str_replace('<', '', (string)$css);

        return trim($css);
    }

    public function validateCustomCss(): void
    {
        if (!$this->isAttributeChanged('custom_css')) {
            return;
        }
        if (str_contains((string)$this->custom_css, '<')) {
            $this->addError('custom_css', Yii::t('ThiscoveryFormsModule.base', 'Custom CSS cannot contain the < character.'));
        }
    }

    public function resolveTheme(): ?FormTheme
    {
        $id = $this->getSetting('theme_id', null);
        if ($id === null || $id === '' || $id === false) {
            return null;
        }
        return FormTheme::findOne((int)$id);
    }

    public function getDisplayFlags(): array
    {
        return DisplaySettings::resolve($this);
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

        if ($this->hasAttribute('fill_token') && trim((string)$this->fill_token) === '') {
            $this->fill_token = self::generateShareToken();
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
        $this->persistRandomisationFromRequest();
        $this->persistEconsentFromRequest();
        $this->persistQuotasFromRequest();
        $this->persistLoopsFromRequest();

        if (!$insert && (int)$this->status === self::STATUS_OPEN && $this->id) {
            $policy = \humhub\modules\thiscoveryForms\services\formula\FormulaPolicy::authoringErrors($this);
            if ($policy) {
                $this->addError('status', $policy[0]);
                return false;
            }
        }

        return true;
    }

    protected function persistEconsentFromRequest(): void
    {
        $request = Yii::$app->request;
        if (!$request->isPost || $request->post('econsent_present') === null) {
            return;
        }
        (new \humhub\modules\thiscoveryForms\services\ConsentService())->saveFormSettings($this, [
            'enabled' => $request->post('econsent_enabled', '0'),
            'reconsent' => $request->post('econsent_reconsent', 'off'),
            'store_client_hashes' => $request->post('consent_store_client_hashes', '0'),
            'not_consented_message' => $request->post('not_consented_message', ''),
        ]);
    }

    protected function persistLoopsFromRequest(): void
    {
        $request = Yii::$app->request;
        if (!$request->isPost || $request->post('loops_present') === null) {
            return;
        }
        (new \humhub\modules\thiscoveryForms\services\LoopService())->saveFormSettings($this, [
            'enabled' => $request->post('loops_enabled', '0'),
        ]);
    }

    protected function persistQuotasFromRequest(): void
    {
        $request = Yii::$app->request;
        if (!$request->isPost || $request->post('quotas_present') === null) {
            return;
        }
        (new \humhub\modules\thiscoveryForms\services\QuotaService())->saveFormSettings($this, [
            'enabled' => $request->post('quotas_enabled', '0'),
            'assign_arm' => $request->post('quota_assign_arm', '0'),
            'full_email' => $request->post('quota_full_email', ''),
        ]);
    }

    protected function persistRandomisationFromRequest(): void
    {
        $request = Yii::$app->request;
        if (!$request->isPost || $request->post('randomisation_present') === null) {
            return;
        }
        (new \humhub\modules\thiscoveryForms\services\RandomisationService())->saveConfig($this, [
            'enabled' => $request->post('randomisation_enabled', '0'),
            'arms' => $request->post('randomisation_arms', ''),
            'strata' => $request->post('randomisation_strata', ''),
            'method' => $request->post('randomisation_method', 'simple'),
            'block_size' => $request->post('randomisation_block_size', 4),
            'assign' => $request->post('randomisation_assign', 'start'),
            'assign_page' => $request->post('randomisation_assign_page', ''),
            'screen_out_message' => $request->post('screen_out_message', ''),
        ]);
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
        $this->setSetting('consensus_agree_from', trim((string)$this->consensus_agree_from));
        $this->setSetting('consensus_agree_to', trim((string)$this->consensus_agree_to));
        $this->setSetting('consensus_disagree_from', trim((string)$this->consensus_disagree_from));
        $this->setSetting('consensus_disagree_to', trim((string)$this->consensus_disagree_to));
        $this->setSetting('consensus_exclude_codes', trim((string)$this->consensus_exclude_codes));
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
        $this->persistCompletionSettings();
        \humhub\modules\thiscoveryForms\services\ExportSettings::persistFromRequest($this);
    }

    protected function persistCompletionSettings(): void
    {
        $mode = (string)$this->completion_mode;
        if ($mode !== self::COMPLETION_REDIRECT) {
            $mode = self::COMPLETION_MESSAGE;
        }
        $this->completion_mode = $mode;
        $this->setSetting('completion_mode', $mode);
        $this->setSetting('completion_button_enabled', !empty($this->completion_button_enabled));
        $this->setSetting('completion_button_label', trim((string)$this->completion_button_label));
        $this->setSetting('completion_button_url', trim((string)$this->completion_button_url));
        $this->setSetting('completion_redirect_url', trim((string)$this->completion_redirect_url));
        $this->setSetting('already_submitted_button_enabled', !empty($this->already_submitted_button_enabled));
        $this->setSetting('already_submitted_button_label', trim((string)$this->already_submitted_button_label));
        $this->setSetting('already_submitted_button_url', trim((string)$this->already_submitted_button_url));
    }

    /**
     * Submit actions arrive as a top-level POST array and are stored in beforeSave, so their
     * conditions are checked here, where an error still stops the save (LOG-11).
     */
    public function validateSubmitActions($attribute): void
    {
        $request = Yii::$app->request;
        $list = ($request instanceof \yii\web\Request && $request->isPost && $request->post('submit_actions') !== null)
            ? $request->post('submit_actions')
            : $this->submit_actions;
        foreach (\humhub\modules\thiscoveryForms\services\FormActionService::conditionErrors($list) as $message) {
            $this->addError($attribute, $message);
        }
    }

    public function validateCompletionRedirectUrl($attribute): void
    {
        if ($this->completion_mode !== self::COMPLETION_REDIRECT) {
            return;
        }
        $url = trim((string)$this->$attribute);
        if ($url === '') {
            $this->addError($attribute, Yii::t('ThiscoveryFormsModule.base', 'Enter a redirect URL.'));
            return;
        }
        if (!$this->isSafeOutboundUrl($url)) {
            $this->addError($attribute, Yii::t('ThiscoveryFormsModule.base', 'Redirect URL must be http(s) or a path starting with /.'));
        }
    }

    public function isSafeOutboundUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return true;
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }
        if (!preg_match('#^https?://#i', $url)) {
            return false;
        }
        return (bool)filter_var($url, FILTER_VALIDATE_URL);
    }

    public static function getCompletionModeLabels(): array
    {
        return [
            self::COMPLETION_MESSAGE => Yii::t('ThiscoveryFormsModule.base', 'Show a message'),
            self::COMPLETION_REDIRECT => Yii::t('ThiscoveryFormsModule.base', 'Redirect to a URL'),
        ];
    }

    public function usesCompletionRedirect(): bool
    {
        return $this->completion_mode === self::COMPLETION_REDIRECT
            && trim((string)$this->completion_redirect_url) !== '';
    }

    public function getCompletionRedirectUrl(): string
    {
        return trim((string)$this->completion_redirect_url);
    }

    public function showsCompletionButton(): bool
    {
        return $this->completion_mode !== self::COMPLETION_REDIRECT
            && !empty($this->completion_button_enabled);
    }

    public function getCompletionButtonLabel(): string
    {
        $label = trim((string)$this->completion_button_label);
        return $label !== ''
            ? $label
            : Yii::t('ThiscoveryFormsModule.base', 'Back to form');
    }

    public function getCompletionButtonUrl(): string
    {
        $url = trim((string)$this->completion_button_url);
        if ($url !== '' && $this->isSafeOutboundUrl($url)) {
            return $url;
        }
        return Url::toView($this);
    }

    public function showsAlreadySubmittedButton(): bool
    {
        return !empty($this->already_submitted_button_enabled);
    }

    public function getAlreadySubmittedButtonLabel(): string
    {
        $label = trim((string)$this->already_submitted_button_label);
        return $label !== ''
            ? $label
            : Yii::t('ThiscoveryFormsModule.base', 'Continue');
    }

    public function getAlreadySubmittedButtonUrl(): string
    {
        $url = trim((string)$this->already_submitted_button_url);
        if ($url !== '' && $this->isSafeOutboundUrl($url)) {
            return $url;
        }
        return Url::toManageIndex($this->isGlobal() ? null : ($this->content->container ?? null));
    }

    protected function persistEnrolSettings(): void
    {
        $mode = (string)$this->enrol_panel_mode;
        if (!in_array($mode, [self::ENROL_PANEL_NONE, self::ENROL_PANEL_EXISTING, self::ENROL_PANEL_CREATE], true)) {
            $mode = self::ENROL_PANEL_NONE;
        }
        $title = trim((string)$this->enrol_panel_title);
        $panelId = (int)$this->enrol_panel_id;
        if ($panelId > 0) {
            $panel = FormPanel::findOne($panelId);
            $panelContainer = $panel && $panel->contentcontainer_id ? (int)$panel->contentcontainer_id : null;
            if (!$panel || $panelContainer !== $this->containerId()) {
                $panelId = 0;
                $this->enrol_panel_id = 0;
            }
        }

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
        if ($this->isSurvey() || $this->isEq5d() || $this->isLongitudinal()) {
            $this->setSetting('use_waves', !empty($this->use_waves));
            $this->use_waves = $this->getSetting('use_waves', false) ? 1 : 0;
            $scope = $this->wave_scope === Module::WAVE_SCOPE_PANEL
                ? Module::WAVE_SCOPE_PANEL
                : Module::WAVE_SCOPE_SURVEY;
            $this->setSetting('wave_scope', $scope);
            $this->wave_scope = $scope;
        } else {
            $this->setSetting('use_waves', false);
            $this->use_waves = 0;
        }
        $themeId = trim((string)$this->theme_id);
        if ($themeId === '') {
            $this->setSetting('theme_id', null);
            $this->theme_id = '';
        } else {
            $this->setSetting('theme_id', (int)$themeId);
            $this->theme_id = (string)(int)$themeId;
        }
        $this->display = DisplaySettings::normalizeOverlay(is_array($this->display) ? $this->display : []);
        $this->setSetting('display', $this->display);
        $mail = new \humhub\modules\thiscoveryForms\services\EmailTemplateService();
        foreach ([
            'invite_email_template_id',
            'wave_email_template_id',
            'reminder_email_template_id',
            'completion_email_template_id',
        ] as $attribute) {
            if ((int)$this->$attribute > 0 && !$this->emailTemplateAllowed($mail, (int)$this->$attribute)) {
                $this->$attribute = 0;
            }
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

    /**
     * CSV download is narrower than viewing answers. A respondent who can see
     * responses only because they submitted one cannot export the file.
     */
    public function canExportAnswers($user = null): bool
    {
        $user = $user ?: Yii::$app->user->getIdentity();
        if (!$user) {
            return false;
        }
        if ($this->canManage($user)) {
            return true;
        }
        if ($this->answers_visibility !== self::ANSWERS_PERMISSION) {
            return false;
        }
        $container = $this->isGlobal() ? null : $this->content->getContainer();
        if ($container instanceof Space) {
            return $container->getPermissionManager($user)->can(ViewAnswers::class);
        }
        return (new PermissionManager(['subject' => $user]))->can(ViewGlobalAnswers::class);
    }

    /**
     * Reviewers who may include or exclude a response from analysis.
     * Managers always can. People with View Answers permission can.
     * Respondents who only see answers because they submitted cannot.
     */
    public function canDecideAnalysis($user = null): bool
    {
        $user = $user ?: Yii::$app->user->getIdentity();
        if (!$user) {
            return false;
        }
        if ($this->canManage($user)) {
            return true;
        }
        $container = $this->isGlobal() ? null : $this->content->getContainer();
        if ($container instanceof Space) {
            return $container->getPermissionManager($user)->can(ViewAnswers::class);
        }
        return (new PermissionManager(['subject' => $user]))->can(ViewGlobalAnswers::class);
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
     *
     * All or nothing (V3-37, DAT-3): the rows are written in a transaction and the design
     * checks (go-tos, loops, quotas, consent, formula policy, legacy rules) run against the
     * written structure; any failure rolls every write back, so an invalid design is never
     * stored. Inside an outer transaction this is a savepoint. Reasons are on getErrors('title')
     * or in the error flash.
     *
     * @param array $rows
     */
    public function saveFieldsFromPost(array $rows): bool
    {
        $tx = Yii::$app->db->beginTransaction();
        try {
            $ok = $this->writeFields($rows);
        } catch (\yii\db\IntegrityException $e) {
            // The one-live-name index (DAT-10), e.g. two questions swapping names in one save.
            $tx->rollBack();
            unset($this->fields);
            $this->addError('title', Yii::t('ThiscoveryFormsModule.base', 'Two questions would share a variable name. Give each its own name, or rename in two saves.'));
            return false;
        } catch (\Throwable $e) {
            $tx->rollBack();
            unset($this->fields);
            throw $e;
        }
        if ($ok) {
            $tx->commit();
            return true;
        }
        $tx->rollBack();
        unset($this->fields);
        return false;
    }

    /**
     * A renamed variable is followed by the form's named formulas and quota rules, so no
     * [old] reference is left dangling (V3-52).
     *
     * @param array<string,string> $renames
     */
    private function applyVariableRenames(array $renames): void
    {
        $functions = \humhub\modules\thiscoveryForms\services\FormActionService::normalizeFunctions($this->custom_functions ?? $this->getSetting('custom_functions', []));
        $changed = false;
        foreach ($functions as $i => $fn) {
            $next = FormulaRefs::renameText((string)$fn['value'], $renames);
            if ($next !== (string)$fn['value']) {
                $functions[$i]['value'] = $next;
                $changed = true;
            }
        }
        // Submit actions' values and conditions follow the rename too (LOG-11).
        $submitActions = \humhub\modules\thiscoveryForms\services\FormActionService::normalizeList($this->getSetting('submit_actions', []));
        $actionsChanged = false;
        foreach ($submitActions as $i => $action) {
            foreach (['value', 'condition'] as $key) {
                $next = FormulaRefs::renameText((string)$action[$key], $renames);
                if ($next !== (string)$action[$key]) {
                    $submitActions[$i][$key] = $next;
                    $actionsChanged = true;
                }
            }
        }
        if ($actionsChanged) {
            $submitActions = \humhub\modules\thiscoveryForms\services\FormActionService::normalizeList($submitActions);
            $this->submit_actions = $submitActions;
            $this->setSetting('submit_actions', $submitActions);
            $changed = true;
        }
        if ($changed) {
            $this->custom_functions = $functions;
            $this->setSetting('custom_functions', $functions);
            $this->save(false, ['settings_json']);
        }
        if (\humhub\modules\thiscoveryForms\services\QuotaService::tablesReady()) {
            $rows = (new \yii\db\Query())->select(['id', 'rules_json'])->from('{{%custom_form_quota}}')->where(['form_id' => (int)$this->id])->all();
            foreach ($rows as $row) {
                $rule = json_decode((string)$row['rules_json'], true);
                if (!is_array($rule)) {
                    continue;
                }
                $next = FormulaRefs::renameRule($rule, $renames);
                if ($next !== $rule) {
                    Yii::$app->db->createCommand()->update('{{%custom_form_quota}}', [
                        'rules_json' => json_encode($next, JSON_UNESCAPED_UNICODE),
                    ], ['id' => (int)$row['id']])->execute();
                }
            }
        }
    }

    /**
     * Why saveFieldsFromPost() refused the design: its checks and any error flash it set.
     */
    public function designRefusalMessage(): string
    {
        $reasons = array_values(array_map('strval', $this->getErrors('title')));
        $flash = Yii::$app->session->getFlash('error', null, true);
        foreach (is_array($flash) ? $flash : [$flash] as $message) {
            $message = trim((string)$message);
            if ($message !== '' && !in_array($message, $reasons, true)) {
                $reasons[] = $message;
            }
        }
        $intro = Yii::t('ThiscoveryFormsModule.base', 'The question changes were not saved; the questions are as they were before this save.');
        return $reasons ? $intro . ' ' . implode(' ', $reasons) : $intro;
    }

    /**
     * The write half of saveFieldsFromPost(); only ever called inside its transaction.
     */
    private function writeFields(array $rows): bool
    {
        $existing = [];
        foreach ($this->getAllFields()->all() as $field) {
            $existing[$field->id] = $field;
        }

        // Guard against accidental wipes (e.g. Settings save when the builder
        // payload did not reach the server). Intentional clear posts clear_fields_confirmed=1.
        $hasRows = false;
        foreach ($rows as $row) {
            if (is_array($row) && trim((string)($row['type'] ?? '')) !== '') {
                $hasRows = true;
                break;
            }
        }
        if (!$hasRows && $existing) {
            if (!Yii::$app->request->post('clear_fields_confirmed')) {
                Yii::warning(
                    'Thiscovery Forms: refused empty field save for form #' . (int)$this->id
                    . ' (' . count($existing) . ' existing fields kept).',
                    'thiscovery-forms'
                );
                return true;
            }
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

        $usedVariables = [];
        /** @var array<string,string> $usedBy variable => label of the question that has it */
        $usedBy = [];
        /** @var array<string,string> $renames old variable => new variable */
        $renames = [];
        $postedIds = [];
        $keptNames = [];
        foreach ($orderedRows as $item) {
            if (isset($item['row']['id']) && $item['row']['id'] !== '') {
                $postedId = (int)$item['row']['id'];
                $postedIds[$postedId] = true;
                $postedName = strtolower(trim((string)($item['row']['variable'] ?? '')));
                if ($postedName !== '' && isset($existing[$postedId])
                    && $postedName === strtolower(trim((string)$existing[$postedId]->variable))) {
                    // The live question is keeping its name, so a removed copy of that name
                    // does not block it.
                    $keptNames[$postedName] = true;
                }
            }
        }
        foreach ($existing as $field) {
            // A removed question keeps its name reserved, unless this save brings it back or a
            // live question is already keeping it. A question this save removes is not posted.
            $name = strtolower(trim((string)$field->variable));
            if ($name !== '' && !isset($postedIds[(int)$field->id]) && !isset($keptNames[$name])) {
                $usedVariables[$name] = true;
                $usedBy[$name] = Yii::t('ThiscoveryFormsModule.base', 'a removed question');
            }
        }
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
            if ($type === FormField::TYPE_MAP && !\humhub\modules\thiscoveryForms\helpers\MappingAvailability::isEnabled()) {
                // Keep existing map questions if Mapping was turned off; reject new ones.
                $idProbe = isset($row['id']) && $row['id'] !== '' ? (int)$row['id'] : null;
                if (!$idProbe || !isset($existing[$idProbe])) {
                    continue;
                }
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
            $oldVariable = $field->isNewRecord ? '' : trim((string)$field->variable);
            $field->label = $label;
            $field->type = $type;
            $field->variable = trim((string)($row['variable'] ?? ''));
            $field->internal_label = trim((string)($row['internal_label'] ?? ''));
            $field->help_text = $row['help_text'] ?? null;
            $field->required = !empty($row['required']);
            if (FormField::isQuestionGroup($type) || FormField::isGroupEnd($type) || $type === FormField::TYPE_PAGE_BREAK || $type === FormField::TYPE_RICH_TEXT || $type === FormField::TYPE_RESPONDENT_META) {
                $field->required = false;
            }
            if (FormField::isGroupEnd($type) && trim($field->label) === '') {
                $field->label = FormField::defaultLabelForType($type);
            }
            $field->sort_order = $sort++;

            if ($type !== FormField::TYPE_GROUP_END) {
                if ($field->variable !== '') {
                    // A name the author chose is never silently suffixed: two questions with
                    // one name would swap columns when reordered (DAT-10).
                    $chosen = strtolower($field->ensureVariable());
                    if (isset($usedVariables[$chosen])) {
                        $this->addError('title', Yii::t('ThiscoveryFormsModule.base', '“{label}” uses the variable name “{name}”, which {other} already has. Each question needs its own name.', [
                            'label' => $label,
                            'name' => (string)$field->variable,
                            'other' => $usedBy[$chosen] ?? Yii::t('ThiscoveryFormsModule.base', 'another question'),
                        ]));
                        return false;
                    }
                }
                $field->ensureVariable($usedVariables);
                $usedVariables[strtolower((string)$field->variable)] = true;
                $usedBy[strtolower((string)$field->variable)] = '“' . $label . '”';
                if ($oldVariable !== '' && strcasecmp($oldVariable, (string)$field->variable) !== 0) {
                    // Renamed: every [old] reference is rewritten below (V3-52).
                    $renames[$oldVariable] = (string)$field->variable;
                }
            }

            try {
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
                        $item = [
                            'formula' => (string)($branch['formula'] ?? $branch['text'] ?? ''),
                            'gotoPageKey' => (string)($branch['gotoPageKey'] ?? ''),
                        ];
                        if (isset($branch['fieldKey']) || isset($branch['operator'])) {
                            $item['fieldKey'] = (string)($branch['fieldKey'] ?? '');
                            $item['operator'] = (string)($branch['operator'] ?? 'equals');
                            $item['value'] = (string)($branch['value'] ?? '');
                        }
                        $branches[] = $item;
                    }
                }
                $field->setPageBreakConfig([
                    'pageKey' => $row['page_key'] ?? '',
                    'title' => $row['page_title'] ?? '',
                    'branches' => $branches,
                    'otherwise' => $row['page_otherwise'] ?? '',
                ], true);
                $field->required = false;
            } elseif ($type === FormField::TYPE_RAND_BLOCK) {
                $blockShow = trim((string)($row['randomise_show'] ?? ''));
                $field->options_json = json_encode([
                    'blockKey' => trim((string)($row['block_key'] ?? $row['page_key'] ?? '')),
                    'randomise' => [
                        'enabled' => true,
                        'method' => (($row['randomise_method'] ?? '') === 'rotate') ? 'rotate' : 'shuffle',
                        // Show N of the block's pages (V3-56).
                        'show' => $blockShow === '' ? null : max(1, (int)$blockShow),
                    ],
                ], JSON_UNESCAPED_UNICODE);
                $field->required = false;
            } elseif ($type === FormField::TYPE_RAND_BLOCK_END) {
                $field->required = false;
            } elseif ($type === FormField::TYPE_CONSENT) {
                $signature = (string)($row['consent_signature'] ?? 'typed');
                if (!in_array($signature, ['typed', 'checkbox', 'drawn'], true)) {
                    $signature = 'typed';
                }
                $field->options_json = json_encode([
                    'document_id' => (int)($row['consent_document_id'] ?? 0),
                    'must_read' => !empty($row['consent_must_read']),
                    'signature' => $signature,
                    'witness' => !empty($row['consent_witness']),
                ], JSON_UNESCAPED_UNICODE);
                $field->required = false;
            } elseif ($type === FormField::TYPE_QUESTION_GROUP && (!empty($row['randomise_enabled']) || !empty($row['loop_enabled']))) {
                $options = [];
                if (!empty($row['randomise_enabled'])) {
                    $show = trim((string)($row['randomise_show'] ?? ''));
                    $options['randomise'] = [
                        'enabled' => true,
                        'method' => (($row['randomise_method'] ?? '') === 'rotate') ? 'rotate' : 'shuffle',
                        'show' => $show === '' ? null : (int)$show,
                        'pinFirst' => array_values(array_filter(array_map('trim', explode(',', (string)($row['randomise_pin_first'] ?? ''))))),
                        'pinLast' => array_values(array_filter(array_map('trim', explode(',', (string)($row['randomise_pin_last'] ?? ''))))),
                    ];
                }
                if (!empty($row['loop_enabled'])) {
                    $items = [];
                    $lines = preg_split('/\r\n|\r|\n/', (string)($row['loop_items'] ?? '')) ?: [];
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line === '') {
                            continue;
                        }
                        $parts = array_map('trim', explode('|', $line, 2));
                        $code = $parts[0];
                        if ($code === '') {
                            continue;
                        }
                        $items[] = ['code' => $code, 'label' => $parts[1] ?? $code];
                    }
                    $source = (string)($row['loop_source'] ?? 'fixed');
                    if (!in_array($source, ['fixed', 'choices', 'number', 'roster'], true)) {
                        $source = 'fixed';
                    }
                    $options['loop'] = [
                        'source' => $source,
                        'field_key' => trim((string)($row['loop_field_key'] ?? '')),
                        'label_field' => trim((string)($row['loop_label_field'] ?? '')),
                        'max' => max(0, (int)($row['loop_max'] ?? 0)),
                        'min' => max(0, (int)($row['loop_min'] ?? 0)),
                        'items' => $items,
                        'randomise' => !empty($row['loop_randomise']),
                        'show' => trim((string)($row['loop_show'] ?? '')) === '' ? null : max(0, (int)$row['loop_show']),
                    ];
                }
                $field->options_json = json_encode($options, JSON_UNESCAPED_UNICODE);
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
                $mobileLayout = 'scroll';
                if (!empty($row['grid_mobile_stack']) || ($row['grid_mobile_layout'] ?? '') === 'stack') {
                    $mobileLayout = 'stack';
                }
                $field->setGridConfig([
                    'rows' => (!empty($row['grid_row_items']) && is_array($row['grid_row_items']))
                        ? $row['grid_row_items']
                        : ($row['grid_rows'] ?? ''),
                    'columns' => (!empty($row['grid_column_items']) && is_array($row['grid_column_items']))
                        ? $row['grid_column_items']
                        : ($row['grid_columns'] ?? ''),
                    'mobile_layout' => $mobileLayout,
                ]);
            } elseif ($type === FormField::TYPE_BEST_WORST || $type === FormField::TYPE_MAXDIFF) {
                $field->setItemsConfig([
                    'items' => $row['items'] ?? ($row['options'] ?? ''),
                    'setSize' => $row['maxdiff_set_size'] ?? 4,
                    'setCount' => $row['maxdiff_set_count'] ?? 0,
                    'versions' => $row['maxdiff_versions'] ?? null,
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
            } elseif ($type === FormField::TYPE_MAP) {
                $types = $row['map_types'] ?? ['Point'];
                if (is_string($types)) {
                    $types = array_filter(array_map('trim', explode(',', $types)));
                }
                $field->setMapConfig([
                    'lat' => $row['map_lat'] ?? 52.4862,
                    'lng' => $row['map_lng'] ?? -1.8904,
                    'zoom' => $row['map_zoom'] ?? 7,
                    'allowedTypes' => $types,
                    'maxFeatures' => $row['map_max'] ?? 1,
                    'style' => $row['map_style'] ?? '',
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
                $optionSource = !empty($row['option_items']) && is_array($row['option_items'])
                    ? $row['option_items']
                    : ($row['options'] ?? '');
                $field->setOptionsFromText(
                    $optionSource,
                    !empty($row['randomize']),
                    $maxSelect,
                    $exclusive,
                    $minSelect,
                    $minSelectAll
                );
                if (array_key_exists('other_specify', $row)) {
                    $field->setAllowsOtherSpecify(!in_array($row['other_specify'], [0, '0', false, 'false', ''], true));
                }
            } elseif ($type === FormField::TYPE_NUMBER) {
                $field->setNumberRange($row['number_min'] ?? null, $row['number_max'] ?? null);
            } elseif ($type === FormField::TYPE_FILE) {
                $field->setFileRules($row['file_types'] ?? '', $row['file_max_mb'] ?? null);
            } elseif ($type === FormField::TYPE_CALCULATED) {
                $field->setFormulaConfig(
                    (string)($row['formula'] ?? ''),
                    (string)($row['formula_result'] ?? 'number'),
                    (string)($row['formula_display'] ?? 'readonly'),
                    $row['formula_places'] ?? 6
                );
            } else {
                $prefill = array_key_exists('prefill_profile', $row)
                    ? trim((string)$row['prefill_profile'])
                    : $field->getPrefillProfileAttribute();
                $field->options_json = $prefill
                    ? json_encode(['prefillProfile' => $prefill], JSON_UNESCAPED_UNICODE)
                    : null;
            }
            } catch (\InvalidArgumentException $e) {
                Yii::$app->session->setFlash('error', $e->getMessage());
                return false;
            }

            $field->setHiddenFromRespondent(!empty($row['hidden']) || $type === FormField::TYPE_RESPONDENT_META);
            if ($field->collectsAnswer()) {
                $field->setAttentionCheck(!empty($row['attention_check']), (string)($row['attention_expected'] ?? ''));
                $field->setStraightlineConfig(!empty($row['straightline_exempt']), !empty($row['reverse_keyed']), (string)($row['reverse_rows'] ?? ''));
            }
            $field->setDefaultValue((string)($row['default_value'] ?? ''));
            if ($type === FormField::TYPE_RESPONDENT_META) {
                $field->setRespondentMetaKey((string)($row['meta_key'] ?? $field->getRespondentMetaKey()));
            }
            if ($type === FormField::TYPE_PANEL_ATTR) {
                $field->setPanelAttrKey((string)($row['panel_key'] ?? $field->getPanelAttrKey()));
            }
            if ($field->collectsAnswer()) {
                $field->setContainsPii(array_key_exists('pii', $row) ? !empty($row['pii']) : $field->defaultContainsPii());
            }
            if ($field->isHiddenFromRespondent()) {
                $field->required = false;
            }

            if (!$field->save()) {
                $first = $field->getFirstErrors();
                $msg = $first ? (string)reset($first) : Yii::t('ThiscoveryFormsModule.base', 'Validation failed.');
                Yii::$app->session->setFlash('error', Yii::t(
                    'ThiscoveryFormsModule.base',
                    'Could not save question "{label}": {error}',
                    [
                        'label' => mb_substr((string)$field->label, 0, 80),
                        'error' => $msg,
                    ]
                ));
                Yii::warning(
                    'Thiscovery Forms: field save failed on form #' . (int)$this->id
                    . ' type=' . $type . ' errors=' . json_encode($field->getErrors()),
                    'thiscovery-forms'
                );
                return false;
            }

            $keptIds[] = $field->id;
            $createdMap[(string)$tempKey] = $field->id;
            if ($id) {
                $createdMap[(string)$id] = $field->id;
                $createdMap[FormField::studioKey((int)$id)] = $field->id;
            }
            $createdMap[FormField::studioKey((int)$field->id)] = $field->id;
            $importKey = trim((string)($row['import_key'] ?? ''));
            if ($importKey !== '' && !isset($createdMap[$importKey])) {
                $createdMap[$importKey] = $field->id;
            }
            $variable = trim((string)$field->variable);
            if ($variable !== '') {
                $createdMap[$variable] = $field->id;
            }
        }

        // Second pass: logic, carry-forward, page-break branch field keys
        $choiceFields = FormField::find()->where(['form_id' => $this->id])->all();
        $choiceNotices = [];
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

            $goto = (string)($row['logic_goto'] ?? '');
            try {
                $formula = LogicEngine::postedFormula($row, $choiceFields) ?? '';
            } catch (\InvalidArgumentException $e) {
                Yii::$app->session->setFlash('error', $e->getMessage());
                return false;
            }
            $formula = FormulaRefs::renameText($formula, $renames);
            if ($formula !== '' || array_key_exists('logic_formula', $row)) {
                try {
                    $rewritten = \humhub\modules\thiscoveryForms\services\formula\FormulaChoiceCodes::rewrite($formula, $choiceFields);
                    $formula = $rewritten['text'];
                    $choiceNotices = array_merge($choiceNotices, $rewritten['notices']);
                    $field->setLogic(LogicEngine::fromFormula($formula, (string)($row['logic_action'] ?? 'show'), $goto));
                } catch (\humhub\modules\thiscoveryForms\services\formula\FormulaException $e) {
                    Yii::$app->session->setFlash('error', $e->getMessage());
                    return false;
                }
            }
            if ($field->type === FormField::TYPE_CALCULATED) {
                $calc = $field->getFormulaConfig();
                $calc['formula'] = FormulaRefs::renameText((string)$calc['formula'], $renames);
                $field->setFormulaConfig($calc['formula'], $calc['result'], $calc['display'], $calc['places']);
                try {
                    $rewritten = \humhub\modules\thiscoveryForms\services\formula\FormulaChoiceCodes::rewrite($calc['formula'], $choiceFields);
                    $choiceNotices = array_merge($choiceNotices, $rewritten['notices']);
                    if ($rewritten['text'] !== $calc['formula']) {
                        $field->setFormulaConfig($rewritten['text'], $calc['result'], $calc['display'], $calc['places']);
                    }
                } catch (\humhub\modules\thiscoveryForms\services\formula\FormulaException $e) {
                    Yii::$app->session->setFlash('error', $e->getMessage());
                    return false;
                }
            }

            if ($field->type === FormField::TYPE_QUESTION_GROUP) {
                // An imported loop names its source by the file's key (for example f12); point it
                // at the question that key became (V3-46).
                $groupOptions = json_decode((string)$field->options_json, true);
                if (is_array($groupOptions) && is_array($groupOptions['loop'] ?? null)) {
                    $known = [];
                    foreach ($choiceFields as $candidate) {
                        $known[strtolower(trim((string)$candidate->variable))] = true;
                        $known[(string)(int)$candidate->id] = true;
                    }
                    foreach (['field_key', 'label_field'] as $loopKey) {
                        $ref = trim((string)($groupOptions['loop'][$loopKey] ?? ''));
                        if ($ref !== '' && !isset($known[strtolower($ref)]) && isset($createdMap[$ref])) {
                            $target = FormField::findOne((int)$createdMap[$ref]);
                            $groupOptions['loop'][$loopKey] = $target && trim((string)$target->variable) !== ''
                                ? (string)$target->variable
                                : (string)(int)$createdMap[$ref];
                            $field->options_json = json_encode($groupOptions, JSON_UNESCAPED_UNICODE);
                        }
                    }
                }
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
                    if (isset($branch['formula']) && is_string($branch['formula'])) {
                        $branch['formula'] = FormulaRefs::renameText($branch['formula'], $renames);
                    }
                    $resolved[] = $branch;
                }
                $field->setPageBreakConfig([
                    'pageKey' => $cfg['pageKey'],
                    'title' => $cfg['title'],
                    'branches' => $resolved,
                    'otherwise' => $cfg['otherwise'],
                ]);
            }

            $postedActions = is_array($row['actions'] ?? null) ? $row['actions'] : [];
            if ($renames) {
                foreach ($postedActions as $i => $action) {
                    foreach (['value', 'condition'] as $key) {
                        if (is_array($action) && isset($action[$key]) && is_string($action[$key])) {
                            $postedActions[$i][$key] = FormulaRefs::renameText($action[$key], $renames);
                        }
                    }
                }
                // Piped text follows the rename too.
                $field->label = FormulaRefs::renameText((string)$field->label, $renames);
                $field->help_text = $field->help_text === null ? null : FormulaRefs::renameText((string)$field->help_text, $renames);
                if ($field->type === FormField::TYPE_RICH_TEXT) {
                    $field->setRichTextContent(FormulaRefs::renameText($field->getRichTextContent(), $renames));
                }
            }
            $actionErrors = \humhub\modules\thiscoveryForms\services\FormActionService::conditionErrors($postedActions);
            if ($actionErrors) {
                Yii::$app->session->setFlash('error', implode(' ', $actionErrors));
                return false;
            }
            $field->setActions($postedActions);
            // Answer rules (LOG-12): refused here if they can't work, so nothing half-valid is stored.
            $postedValidation = is_array($row['validation'] ?? null) ? $row['validation'] : [];
            if ($renames && isset($postedValidation['check']) && is_string($postedValidation['check'])) {
                $postedValidation['check'] = FormulaRefs::renameText($postedValidation['check'], $renames);
            }
            $validationErrors = FormField::validationErrors($postedValidation, (string)$field->type, (string)$field->label);
            if ($validationErrors) {
                Yii::$app->session->setFlash('error', implode(' ', $validationErrors));
                return false;
            }
            $field->setValidation($postedValidation);
            if (FormField::supportsSoftDelete()) {
                $field->deleted_at = null;
            }

            $field->save(false);
        }
        if ($choiceNotices) {
            Yii::$app->session->addFlash('info', implode(' ', array_values(array_unique($choiceNotices))));
        }
        if ($renames) {
            $this->applyVariableRenames($renames);
        }

        $idMap = \humhub\modules\thiscoveryForms\services\FieldRefRewriter::mapFromCreated($createdMap);
        if ($idMap !== []) {
            foreach (array_unique(array_map('intval', $createdMap)) as $mappedId) {
                $mapped = FormField::findOne($mappedId);
                if (!$mapped) {
                    continue;
                }
                \humhub\modules\thiscoveryForms\services\FieldRefRewriter::rewriteField($mapped, $idMap);
                $mapped->save(false);
            }
            \humhub\modules\thiscoveryForms\services\FieldRefRewriter::rewriteForm($this, $idMap);
        }

        // Once any edition has been published, a removed question may still be shown by that
        // edition to people filling it in. Deleting its row would make their answer insert fail
        // the RESTRICT foreign key and roll back the whole submission, so keep it (V3-36).
        $published = (new \humhub\modules\thiscoveryForms\services\FormVersionService())->hasPublishedEdition($this);
        foreach ($existing as $id => $field) {
            if (in_array($id, $keptIds, true) || $field->isRemoved()) {
                continue;
            }
            $hasAnswers = (new \yii\db\Query())
                ->from('custom_form_answer_field')
                ->where(['field_id' => (int)$id])
                ->exists();
            if ($hasAnswers || $published) {
                $field->softDelete();
            } else {
                $field->delete();
            }
        }

        $imageGuids = [];
        foreach (FormField::find()->where(['form_id' => $this->id])->all() as $imgField) {
            if ($imgField->type === FormField::TYPE_IMAGE_AREA) {
                $guid = trim((string)($imgField->getImageAreaConfig()['imageGuid'] ?? ''));
                if ($guid !== '') {
                    $imageGuids[] = $guid;
                }
            }
            $blob = (string)$imgField->options_json;
            if ($blob !== '' && preg_match_all('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $blob, $m)) {
                foreach ($m[0] as $guid) {
                    $imageGuids[] = $guid;
                }
            }
        }
        $imageGuids = array_values(array_unique($imageGuids));
        if ($imageGuids) {
            try {
                $this->fileManager->attach($imageGuids);
            } catch (\Throwable $e) {
                Yii::warning('Thiscovery Forms image attach failed: ' . $e->getMessage(), 'thiscovery-forms');
            }
        }

        unset($this->fields);

        $backward = $this->backwardGotoLabel();
        if ($backward !== null) {
            $this->addError('title', Yii::t('ThiscoveryFormsModule.base', '“{label}” cannot go to an earlier page.', [
                'label' => $backward,
            ]));
            return false;
        }
        foreach (\humhub\modules\thiscoveryForms\services\LogicAudit::errors($this) as $message) {
            $this->addError('title', $message);
        }
        foreach ((new \humhub\modules\thiscoveryForms\services\LoopService())->authoringErrors($this) as $message) {
            $this->addError('title', $message);
        }
        foreach ((new \humhub\modules\thiscoveryForms\services\QuotaService())->authoringErrors($this) as $message) {
            $this->addError('title', $message);
        }
        foreach ((new \humhub\modules\thiscoveryForms\services\ConsentService())->authoringErrors($this) as $message) {
            $this->addError('title', $message);
        }
        foreach ((new \humhub\modules\thiscoveryForms\services\RandomisationService())->authoringErrors($this) as $message) {
            $this->addError('title', $message);
        }
        if ((int)$this->status === self::STATUS_OPEN) {
            foreach (\humhub\modules\thiscoveryForms\services\formula\FormulaPolicy::authoringErrors($this) as $message) {
                $this->addError('title', $message);
            }
        }
        if ($this->hasErrors('title')) {
            return false;
        }

        return true;
    }

    /**
     * A go-to that points at the current page or an earlier one.
     */
    private function backwardGotoLabel(): ?string
    {
        $built = (new FormPager())->buildPages($this->getFields()->all());
        $index = $built['pageKeyIndex'];
        $pointsBack = static function (int $from, string $key) use ($index): bool {
            $key = trim($key);
            return $key !== '' && isset($index[$key]) && (int)$index[$key] <= $from;
        };
        foreach ($built['pages'] as $page) {
            $from = (int)$page['index'];
            $fields = $page['items'];
            if (($page['break'] ?? null) instanceof FormField) {
                $fields[] = $page['break'];
            }
            foreach ($fields as $field) {
                $logic = $field->getLogic();
                if (($logic['action'] ?? '') === LogicEngine::ACTION_GOTO_PAGE && $pointsBack($from, (string)($logic['gotoPageKey'] ?? ''))) {
                    return (string)$field->label;
                }
                foreach ($field->getActions() as $action) {
                    if (($action['fn'] ?? '') === FormActionService::FN_GOTO_PAGE && $pointsBack($from, (string)($action['page_key'] ?? ''))) {
                        return (string)$field->label;
                    }
                }
                if ($field->type === FormField::TYPE_PAGE_BREAK) {
                    foreach ($field->getPageBreakConfig()['branches'] as $branch) {
                        if ($pointsBack($from, (string)($branch['gotoPageKey'] ?? ''))) {
                            return (string)$field->label;
                        }
                    }
                    if ($pointsBack($from, (string)$field->getPageBreakConfig()['otherwise'])) {
                        return (string)$field->label;
                    }
                }
            }
        }
        return null;
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
        // A group end with no open group (its group was deleted or moved) is dropped. It holds
        // no answers, and left in place it would close an outer group early (DAT-18).
        $kept = [];
        $depth = 0;
        foreach ($out as $item) {
            $type = (string)($item['row']['type'] ?? '');
            if ($type === FormField::TYPE_PAGE_BREAK) {
                $depth = 0;
            } elseif ($type === FormField::TYPE_QUESTION_GROUP) {
                $depth++;
            } elseif ($type === FormField::TYPE_GROUP_END) {
                if ($depth === 0) {
                    continue;
                }
                $depth--;
            }
            $kept[] = $item;
        }
        return $kept;
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
     * @return list<array<string,mixed>>
     */
    private function remapPostedLogicRules(array $rawRules, array $createdMap): array
    {
        $logicRules = [];
        foreach ($rawRules as $rule) {
            $mapped = $this->remapPostedLogicRule($rule, $createdMap);
            if ($mapped !== null) {
                $logicRules[] = $mapped;
            }
        }
        return $logicRules;
    }

    /**
     * @param mixed $rule
     * @param array<string,int> $createdMap
     * @return array<string,mixed>|null
     */
    private function remapPostedLogicRule($rule, array $createdMap): ?array
    {
        if (!is_array($rule)) {
            return null;
        }
        if (isset($rule['compound'])) {
            $decoded = is_array($rule['compound']) ? $rule['compound'] : json_decode((string)$rule['compound'], true);
            return is_array($decoded) ? $this->remapPostedLogicRule($decoded, $createdMap) : null;
        }
        if (!empty($rule['all']) && is_array($rule['all'])) {
            $inner = $this->remapPostedLogicRules($rule['all'], $createdMap);
            return $inner ? ['all' => $inner] : null;
        }
        if (!empty($rule['any']) && is_array($rule['any'])) {
            $inner = $this->remapPostedLogicRules($rule['any'], $createdMap);
            return $inner ? ['any' => $inner] : null;
        }
        $fk = (string)($rule['fieldKey'] ?? '');
        if ($fk === '') {
            return null;
        }
        if (isset($createdMap[$fk])) {
            $fk = (string)$createdMap[$fk];
        } elseif (preg_match('/^id(\d+)$/i', $fk, $m)) {
            $n = $m[1];
            if (isset($createdMap[$n])) {
                $fk = (string)$createdMap[$n];
            } elseif (isset($createdMap['id' . $n])) {
                $fk = (string)$createdMap['id' . $n];
            }
        }
        return [
            'fieldKey' => $fk,
            'operator' => $rule['operator'] ?? FormField::OP_EQUALS,
            'value' => LogicEngine::normalizeRuleValue($rule['value'] ?? ''),
        ];
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
