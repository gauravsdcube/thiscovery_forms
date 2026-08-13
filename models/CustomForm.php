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
 * @property string|null $description
 * @property string|null $thank_you_content
 * @property string|null $custom_css
 * @property int $status
 * @property int $allow_multiple
 * @property int $allow_anonymous
 * @property int $allow_edit
 * @property int $show_in_menu
 * @property string $answers_visibility
 *
 * @property-read FormField[] $fields
 * @property-read FormAnswer[] $answers
 */
class CustomForm extends ContentActiveRecord implements Searchable
{
    public const STATUS_DRAFT = 0;
    public const STATUS_OPEN = 1;
    public const STATUS_CLOSED = 2;

    public const ANSWERS_MANAGERS = 'managers';
    public const ANSWERS_RESPONDENTS = 'respondents';
    public const ANSWERS_PERMISSION = 'permission';

    public $wallEntryClass = WallEntry::class;
    public $moduleId = 'thiscovery-forms';
    protected $createPermission = CreateForm::class;
    protected $managePermission = ManageForm::class;
    protected $canMove = true;
    public $silentContentCreation = false;

    public static function tableName()
    {
        return 'custom_form';
    }

    public function rules()
    {
        return [
            [['title'], 'required'],
            [['title'], 'string', 'max' => 255],
            [['description', 'thank_you_content', 'custom_css'], 'string'],
            [['status'], 'in', 'range' => [self::STATUS_DRAFT, self::STATUS_OPEN, self::STATUS_CLOSED]],
            [['allow_multiple', 'show_in_menu', 'allow_anonymous', 'allow_edit'], 'boolean'],
            [['allow_edit'], 'default', 'value' => 1],
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
            'description' => Yii::t('ThiscoveryFormsModule.base', 'Description'),
            'thank_you_content' => Yii::t('ThiscoveryFormsModule.base', 'Thank you message'),
            'custom_css' => Yii::t('ThiscoveryFormsModule.base', 'Custom CSS'),
            'status' => Yii::t('ThiscoveryFormsModule.base', 'Status'),
            'allow_multiple' => Yii::t('ThiscoveryFormsModule.base', 'Allow multiple submissions'),
            'allow_anonymous' => Yii::t('ThiscoveryFormsModule.base', 'Allow anonymous submissions'),
            'allow_edit' => Yii::t('ThiscoveryFormsModule.base', 'Allow respondents to edit their answers'),
            'show_in_menu' => Yii::t('ThiscoveryFormsModule.base', 'Show in side menu'),
            'answers_visibility' => Yii::t('ThiscoveryFormsModule.base', 'Who can view answers'),
        ];
    }

    public function getIcon()
    {
        return 'fa-wpforms';
    }

    public function getContentName()
    {
        return Yii::t('ThiscoveryFormsModule.base', 'Form');
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

    public function getAnswers(): ActiveQuery
    {
        return $this->hasMany(FormAnswer::class, ['form_id' => 'id'])
            ->andWhere(['status' => FormAnswer::STATUS_COMPLETE])
            ->orderBy(['created_at' => SORT_DESC]);
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

        if (!empty($this->thank_you_content)) {
            try {
                \humhub\modules\content\widgets\richtext\RichText::postProcess($this->thank_you_content, $this);
            } catch (\Throwable $e) {
                Yii::warning('Thiscovery Forms thank-you postProcess failed: ' . $e->getMessage(), 'thiscovery-forms');
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
            return $container->getPermissionManager($user)->can(ManageForm::class);
        }

        return (new PermissionManager(['subject' => $user]))->can(ManageGlobalForm::class);
    }

    public function canAnswer($user = null): bool
    {
        if (!$this->isOpen()) {
            return false;
        }

        $user = $user ?: Yii::$app->user->getIdentity();

        // Anonymous mode: guests and users may submit; identity is never stored.
        if ($this->allow_anonymous) {
            if (!$this->allow_multiple && $this->hasGuestAnswered()) {
                return false;
            }
            return true;
        }

        if (!$user) {
            return false;
        }

        if (!$this->allow_multiple && $this->hasUserAnswered($user)) {
            return false;
        }

        return $this->canAnswerPermissionOnly($user);
    }

    public function allowsAnonymous(): bool
    {
        return (bool)$this->allow_anonymous;
    }

    public function hasGuestAnswered(): bool
    {
        return (bool)Yii::$app->session->get($this->guestAnswerSessionKey(), false);
    }

    public function markGuestAnswered(): void
    {
        Yii::$app->session->set($this->guestAnswerSessionKey(), true);
    }

    public function guestAnswerSessionKey(): string
    {
        return 'cf_anon_answered_' . (int)$this->id;
    }

    /**
     * Sanitize custom CSS before injecting into the fill page.
     */
    public function getSafeCustomCss(): string
    {
        $css = trim((string)$this->custom_css);
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

        // Anonymous forms must be publicly viewable so guests can open the share link.
        if ($this->allow_anonymous && $this->content) {
            $this->content->visibility = Content::VISIBILITY_PUBLIC;
        }

        return true;
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
        if (!$this->allowsEdit()) {
            return false;
        }

        if ($this->allowsAnonymous() || $answer->isAnonymous()) {
            return false;
        }

        $user = $user ?: Yii::$app->user->getIdentity();
        if (!$user || !$this->isOpen()) {
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
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();
    }

    /**
     * @return static[]
     */
    public static function findShownInMenu(?Space $space = null): array
    {
        $query = static::find()->joinWith('content')
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

        foreach ($orderedRows as $item) {
            $tempKey = $item['key'];
            $row = $item['row'];
            $type = (string)($row['type'] ?? '');
            if ($type === '' || !isset(FormField::getTypeLabels()[$type])) {
                continue;
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
            $field->sort_order = $sort++;

            if ($type === FormField::TYPE_RATING) {
                $field->setRatingScale([
                    'min' => $row['rating_min'] ?? 1,
                    'max' => $row['rating_max'] ?? 5,
                    'step' => $row['rating_step'] ?? 1,
                    'lowLabel' => $row['rating_low_label'] ?? '',
                    'highLabel' => $row['rating_high_label'] ?? '',
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
                if ($richContent !== '') {
                    try {
                        \humhub\modules\content\widgets\richtext\RichText::postProcess($richContent, $this);
                    } catch (\Throwable $e) {
                        Yii::warning('Thiscovery Forms richtext postProcess failed: ' . $e->getMessage(), 'thiscovery-forms');
                    }
                }
            } elseif ($type === FormField::TYPE_HTML) {
                $field->setHtmlConfig([
                    'html' => (string)($row['html_content'] ?? ''),
                    'collect' => !empty($row['html_collect']),
                    'variable' => (string)($row['html_variable'] ?? 'value'),
                    'instructions' => (string)($row['html_instructions'] ?? ''),
                    'required' => !empty($row['html_required']),
                ]);
                $field->required = !empty($row['html_collect']) && !empty($row['html_required']);
            } elseif (FormField::isChoiceType($type)) {
                $maxSelect = null;
                if (array_key_exists('max_select', $row) && $row['max_select'] !== '' && $row['max_select'] !== null) {
                    $maxSelect = (int)$row['max_select'];
                }
                $exclusive = array_key_exists('exclusive_option', $row)
                    ? (string)$row['exclusive_option']
                    : null;
                $field->setOptionsFromText(
                    $row['options'] ?? '',
                    !empty($row['randomize']),
                    $maxSelect,
                    $exclusive
                );
            } else {
                $prefill = array_key_exists('prefill_profile', $row)
                    ? trim((string)$row['prefill_profile'])
                    : $field->getPrefillProfileAttribute();
                $field->options_json = $prefill
                    ? json_encode(['prefillProfile' => $prefill], JSON_UNESCAPED_UNICODE)
                    : null;
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
        }

        // Second pass: conditions + resolve page-break branch field keys that used temp keys
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

            if (!empty($row['condition_field'])) {
                $condKey = (string)$row['condition_field'];
                $condId = $createdMap[$condKey] ?? (ctype_digit($condKey) ? (int)$condKey : null);
                if ($condId && $condId !== $field->id) {
                    $field->condition_field_id = $condId;
                    $field->condition_operator = $row['condition_operator'] ?? FormField::OP_EQUALS;
                    $field->condition_value = $row['condition_value'] ?? '';
                }
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

            $field->save(false);
        }

        foreach ($existing as $id => $field) {
            if (!in_array($id, $keptIds, true)) {
                $field->delete();
            }
        }

        return true;
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
