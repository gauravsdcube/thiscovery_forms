<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\helpers\FormEmailLayout;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormPanel;
use humhub\modules\thiscoveryForms\models\FormPanelActivity;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use humhub\modules\thiscoveryForms\models\FormWave;
use humhub\modules\user\models\User;
use Yii;
use yii\helpers\Html;

class PanelService
{
    public const ENROL_NONE = 'none';
    public const ENROL_EXISTING = 'existing';
    public const ENROL_CREATE = 'create';

    public function createPanel(string $title, ?string $description = null, $containerId = null): ?FormPanel
    {
        $panel = new FormPanel();
        $panel->title = trim($title) !== '' ? trim($title) : Yii::t('ThiscoveryFormsModule.base', 'Panel');
        $panel->description = $description;
        $panel->contentcontainer_id = $containerId ? (int)$containerId : null;
        $panel->created_by = Yii::$app->user->id;
        return $panel->save() ? $panel : null;
    }

    /**
     * @return FormPanel[]
     */
    public function listForContainer($containerId = null): array
    {
        $query = FormPanel::find()->orderBy(['title' => SORT_ASC, 'id' => SORT_ASC]);
        if ($containerId) {
            $query->andWhere(['contentcontainer_id' => (int)$containerId]);
        } else {
            $query->andWhere(['contentcontainer_id' => null]);
        }
        return $query->all();
    }

    public function ensurePanel(CustomForm $form): ?FormPanel
    {
        if (!$form->id || $form->isTemplate()) {
            return null;
        }

        $panelId = (int)$form->getSetting('panel_id', 0);
        if ($panelId) {
            $panel = FormPanel::findOne($panelId);
            if ($panel) {
                return $panel;
            }
        }

        $panel = $this->createPanel(
            Yii::t('ThiscoveryFormsModule.base', '{title} panel', [
                'title' => $form->title ?: Yii::t('ThiscoveryFormsModule.base', 'Survey'),
            ]),
            null,
            $form->isGlobal() ? null : ($form->content->contentcontainer_id ?? null)
        );
        if (!$panel) {
            return null;
        }

        $form->setSetting('panel_id', (int)$panel->id);
        $form->save(false, ['settings_json']);
        return $panel;
    }

    public function findMemberByToken(CustomForm $form, string $token): ?FormPanelMember
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        foreach ($this->panelsForForm($form) as $panel) {
            $found = FormPanelMember::find()
                ->where(['panel_id' => $panel->id, 'token' => $token, 'status' => FormPanelMember::STATUS_ACTIVE])
                ->one();
            if ($found) {
                return $found;
            }
        }
        return null;
    }

    public function findMemberForUser(CustomForm $form, ?User $user): ?FormPanelMember
    {
        if (!$user) {
            return null;
        }
        foreach ($this->panelsForForm($form) as $panel) {
            $found = $this->findMemberOnPanel($panel, $user, (string)$user->email);
            if ($found) {
                return $found;
            }
        }
        return null;
    }

    /**
     * @return FormPanel[]
     */
    public function panelsForForm(CustomForm $form): array
    {
        $out = [];
        foreach ([$this->getPanel($form), $this->getEnrolPanel($form)] as $panel) {
            if ($panel && !isset($out[(int)$panel->id])) {
                $out[(int)$panel->id] = $panel;
            }
        }
        return array_values($out);
    }

    public function findMemberOnPanel(FormPanel $panel, ?User $user = null, string $email = ''): ?FormPanelMember
    {
        $email = strtolower(trim($email));
        if ($user) {
            $byUser = FormPanelMember::find()
                ->where(['panel_id' => $panel->id, 'user_id' => $user->id, 'status' => FormPanelMember::STATUS_ACTIVE])
                ->one();
            if ($byUser) {
                return $byUser;
            }
            if ($email === '') {
                $email = strtolower(trim((string)$user->email));
            }
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return FormPanelMember::find()
            ->where(['panel_id' => $panel->id, 'status' => FormPanelMember::STATUS_ACTIVE])
            ->andWhere('LOWER(email) = :email', [':email' => $email])
            ->one();
    }

    public function resolveMember(CustomForm $form, ?string $token = null, ?User $user = null): ?FormPanelMember
    {
        $token = $token ?? (string)Yii::$app->request->get('token', Yii::$app->request->post('panel_token', ''));
        if ($token !== '') {
            $byToken = $this->findMemberByToken($form, $token);
            if ($byToken) {
                return $byToken;
            }
        }
        $user = $user ?: Yii::$app->user->getIdentity();
        return $this->findMemberForUser($form, $user instanceof User ? $user : null);
    }

    public function getPanel(CustomForm $form): ?FormPanel
    {
        $panelId = (int)$form->getSetting('panel_id', 0);
        return $panelId ? FormPanel::findOne($panelId) : null;
    }

    public function getEnrolPanel(CustomForm $form): ?FormPanel
    {
        $id = (int)$form->getSetting('enrol_panel_id', 0);
        return $id ? FormPanel::findOne($id) : null;
    }

    /**
     * Forms that use this panel for waves (panel_id or enrolment panel).
     *
     * @return CustomForm[]
     */
    public function waveFormsForPanel(FormPanel $panel): array
    {
        $query = CustomForm::findLive()->joinWith('content');
        if ($panel->contentcontainer_id) {
            $query->andWhere(['content.contentcontainer_id' => (int)$panel->contentcontainer_id]);
        } else {
            $query->andWhere(['content.contentcontainer_id' => null]);
        }
        $out = [];
        foreach ($query->all() as $form) {
            if (!$form->usesWaves()) {
                continue;
            }
            $attached = (int)$form->getSetting('panel_id', 0);
            $enrol = (int)$form->getSetting('enrol_panel_id', 0);
            if ($attached === (int)$panel->id || $enrol === (int)$panel->id) {
                $out[] = $form;
            }
        }
        return $out;
    }

    public function addUserMember(FormPanel $panel, User $user, float $weight = 1): FormPanelMember
    {
        $first = trim((string)($user->profile->firstname ?? ''));
        $last = trim((string)($user->profile->lastname ?? ''));
        return $this->upsertMember($panel, [
            'user' => $user,
            'email' => strtolower(trim((string)$user->email)),
            'first' => $first,
            'last' => $last,
            'weight' => $weight,
        ]);
    }

    public function addEmailMember(FormPanel $panel, string $email, ?string $displayName = null, float $weight = 1): FormPanelMember
    {
        $first = '';
        $last = '';
        $name = trim((string)$displayName);
        if ($name !== '') {
            $parts = preg_split('/\s+/', $name, 2) ?: [];
            $first = $parts[0] ?? '';
            $last = $parts[1] ?? '';
        }
        return $this->upsertMember($panel, [
            'email' => $email,
            'first' => $first,
            'last' => $last,
            'weight' => $weight,
        ]);
    }

    /**
     * @param array{email?:string,first?:string,last?:string,user?:User|null,weight?:float,attrs?:array<string,string>} $data
     */
    public function upsertMember(FormPanel $panel, array $data): FormPanelMember
    {
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $first = trim((string)($data['first'] ?? ''));
        $last = trim((string)($data['last'] ?? ''));
        $weight = (float)($data['weight'] ?? 1) ?: 1;
        $user = $data['user'] ?? null;
        if (!$user instanceof User && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $user = User::find()->where('LOWER(email) = :email', [':email' => $email])->one();
        }

        $existing = null;
        if ($user) {
            $existing = FormPanelMember::find()->where(['panel_id' => $panel->id, 'user_id' => $user->id])->one();
        }
        if (!$existing && $email !== '') {
            $existing = FormPanelMember::find()
                ->where(['panel_id' => $panel->id])
                ->andWhere('LOWER(email) = :email', [':email' => $email])
                ->one();
        }

        $member = $existing ?: new FormPanelMember();
        $member->panel_id = $panel->id;
        if ($user) {
            $member->user_id = $user->id;
            if ($email === '') {
                $email = strtolower(trim((string)$user->email));
            }
            if ($first === '') {
                $first = trim((string)($user->profile->firstname ?? ''));
            }
            if ($last === '') {
                $last = trim((string)($user->profile->lastname ?? ''));
            }
        }
        $member->email = $email !== '' ? $email : ($member->email ?: null);
        if ($first !== '') {
            $member->first_name = $first;
        }
        if ($last !== '') {
            $member->last_name = $last;
        }
        $member->weight = $weight;
        $member->status = FormPanelMember::STATUS_ACTIVE;
        if (!$member->token) {
            $member->token = FormPanelMember::generateToken();
        }
        $attrs = $data['attrs'] ?? [];
        if (is_array($attrs) && $attrs) {
            PanelFieldService::applyAttrs($member, $attrs, $panel);
        }
        $member->display_name = $member->buildDisplayName() ?: ($email ?: $member->display_name);
        $member->save(false);
        return $member;
    }

    /**
     * @param string[]|string $guids
     * @return int Number of members added or updated
     */
    public function addMembersFromGuids(FormPanel $panel, $guids): int
    {
        $count = 0;
        foreach ($this->normalizeGuids($guids) as $guid) {
            $user = User::findOne(['guid' => $guid]);
            if (!$user) {
                continue;
            }
            $this->addUserMember($panel, $user);
            $count++;
        }
        return $count;
    }

    /**
     * @param string[]|string $guids
     * @return string[]
     */
    public function normalizeGuids($guids): array
    {
        if (is_string($guids)) {
            $decoded = json_decode($guids, true);
            $guids = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                ? $decoded
                : ($guids === '' ? [] : [$guids]);
        }
        if (!is_array($guids)) {
            return [];
        }
        $out = [];
        foreach ($guids as $guid) {
            $guid = trim((string)$guid);
            if ($guid !== '') {
                $out[] = $guid;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * @return array{added:int,updated:int,skipped:int,errors:string[]}
     */
    public function importCsv(FormPanel $panel, string $raw): array
    {
        $added = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $header = null;
        $rowNum = 0;
        foreach ($lines as $line) {
            $rowNum++;
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $cols = str_getcsv($line);
            if ($header === null) {
                $header = $this->csvHeaderMap($cols, $panel);
                if ($header !== null) {
                    continue;
                }
                $header = ['email' => 0, 'first' => 1, 'last' => 2, 'attrs' => []];
            }
            $email = strtolower(trim((string)($cols[$header['email']] ?? '')));
            $first = trim((string)($cols[$header['first']] ?? ''));
            $last = trim((string)($cols[$header['last']] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                $errors[] = Yii::t('ThiscoveryFormsModule.base', 'Row {n}: missing or invalid email.', ['n' => $rowNum]);
                continue;
            }
            $attrs = [];
            foreach ($header['attrs'] ?? [] as $key => $idx) {
                $attrs[$key] = trim((string)($cols[$idx] ?? ''));
            }
            $before = FormPanelMember::find()->where(['panel_id' => $panel->id, 'email' => $email])->exists();
            if (!$before) {
                $user = User::find()->where(['email' => $email])->one();
                $before = $user && FormPanelMember::find()->where(['panel_id' => $panel->id, 'user_id' => $user->id])->exists();
            }
            $this->upsertMember($panel, ['email' => $email, 'first' => $first, 'last' => $last, 'attrs' => $attrs]);
            if ($before) {
                $updated++;
            } else {
                $added++;
            }
        }
        return compact('added', 'updated', 'skipped', 'errors');
    }

    /**
     * @param string[] $cols
     * @return array{email:int,first:int,last:int,attrs:array<string,int>}|null
     */
    private function csvHeaderMap(array $cols, FormPanel $panel): ?array
    {
        $norm = [];
        foreach ($cols as $i => $col) {
            $key = strtolower(trim((string)$col));
            $key = str_replace([' ', '-'], '_', $key);
            $norm[$key] = $i;
        }
        if (!isset($norm['email']) && !isset($norm['e_mail'])) {
            return null;
        }
        $attrs = [];
        foreach ($panel->getMemberFields() as $field) {
            $key = $field['key'];
            $labelKey = str_replace([' ', '-'], '_', strtolower($field['label']));
            if (isset($norm[$key])) {
                $attrs[$key] = $norm[$key];
            } elseif (isset($norm[$labelKey])) {
                $attrs[$key] = $norm[$labelKey];
            }
        }
        return [
            'email' => $norm['email'] ?? $norm['e_mail'],
            'first' => $norm['first_name'] ?? $norm['firstname'] ?? $norm['first'] ?? $norm['given_name'] ?? 1,
            'last' => $norm['last_name'] ?? $norm['lastname'] ?? $norm['last'] ?? $norm['surname'] ?? $norm['family_name'] ?? 2,
            'attrs' => $attrs,
        ];
    }

    /**
     * @return array{email:string,first:string,last:string,user:?User}
     */
    public function extractIdentity(CustomForm $form, FormAnswer $answer): array
    {
        $values = $answer->getValuesMap();
        $email = '';
        $first = '';
        $last = '';
        $attrs = [];
        foreach ($form->fields as $field) {
            if ($field->isDisplayOnly() || in_array($field->type, [
                FormField::TYPE_MAP,
                FormField::TYPE_IMAGE_AREA,
                FormField::TYPE_GRID_SINGLE,
                FormField::TYPE_GRID_MULTI,
                FormField::TYPE_BEST_WORST,
                FormField::TYPE_MAXDIFF,
                FormField::TYPE_DRILLDOWN,
                FormField::TYPE_FILE,
            ], true)) {
                continue;
            }
            $raw = $values[$field->id] ?? '';
            $text = $this->scalarText($raw);
            if ($text === '') {
                continue;
            }
            $panelKey = $field->getPanelAttrKey();
            if ($panelKey !== '') {
                if ($panelKey === PanelFieldService::KEY_EMAIL && filter_var($text, FILTER_VALIDATE_EMAIL) && $email === '') {
                    $email = strtolower($text);
                } elseif ($panelKey === PanelFieldService::KEY_FIRST && $first === '') {
                    $first = $text;
                } elseif ($panelKey === PanelFieldService::KEY_LAST && $last === '') {
                    $last = $text;
                } elseif (!in_array($panelKey, [PanelFieldService::KEY_EMAIL, PanelFieldService::KEY_FIRST, PanelFieldService::KEY_LAST, PanelFieldService::KEY_DISPLAY], true)) {
                    $attrs[$panelKey] = $text;
                }
                continue;
            }
            $label = mb_strtolower((string)$field->label);
            $emailLabel = (bool)preg_match('/\b(e-?mail|email\s*address)\b/u', $label);
            if (
                $email === ''
                && filter_var($text, FILTER_VALIDATE_EMAIL)
                && ($field->type === FormField::TYPE_EMAIL || $emailLabel)
            ) {
                $email = strtolower($text);
            } elseif (preg_match('/\b(first\s*name|given\s*name|forename)\b/u', $label) && $first === '') {
                $first = $text;
            } elseif (preg_match('/\b(last\s*name|surname|family\s*name)\b/u', $label) && $last === '') {
                $last = $text;
            }
        }
        $user = null;
        if (!$answer->isAnonymous() && $answer->created_by) {
            $user = User::findOne((int)$answer->created_by);
        }
        if ($user) {
            if ($email === '' && $user->email) {
                $email = strtolower(trim((string)$user->email));
            }
            if ($first === '') {
                $first = trim((string)($user->profile->firstname ?? ''));
            }
            if ($last === '') {
                $last = trim((string)($user->profile->lastname ?? ''));
            }
        }
        if ($email === '') {
            $resume = strtolower(trim((string)$answer->resume_email));
            if (filter_var($resume, FILTER_VALIDATE_EMAIL)) {
                $email = $resume;
            }
        }
        return ['email' => $email, 'first' => $first, 'last' => $last, 'user' => $user, 'attrs' => $attrs];
    }

    public function resolveEnrolPanel(CustomForm $form): ?FormPanel
    {
        $mode = (string)$form->getSetting('enrol_panel_mode', self::ENROL_NONE);
        if ($mode === self::ENROL_NONE || $mode === '') {
            return null;
        }
        if ($mode === self::ENROL_CREATE) {
            $existingId = (int)$form->getSetting('enrol_panel_id', 0);
            if ($existingId) {
                $existing = FormPanel::findOne($existingId);
                if ($existing) {
                    $form->enrol_panel_id = (int)$existing->id;
                    $form->enrol_panel_mode = self::ENROL_EXISTING;
                    $form->setSetting('enrol_panel_mode', self::ENROL_EXISTING);
                    $form->save(false, ['settings_json']);
                    return $existing;
                }
            }
            $title = trim((string)$form->getSetting('enrol_panel_title', ''));
            if ($title === '') {
                $title = Yii::t('ThiscoveryFormsModule.base', '{title} panel', [
                    'title' => $form->title ?: Yii::t('ThiscoveryFormsModule.base', 'Survey'),
                ]);
            }
            $panel = $this->createPanel(
                $title,
                null,
                $form->isGlobal() ? null : ($form->content->contentcontainer_id ?? null)
            );
            if ($panel) {
                $form->enrol_panel_id = (int)$panel->id;
                $form->enrol_panel_mode = self::ENROL_EXISTING;
                $form->setSetting('enrol_panel_id', (int)$panel->id);
                $form->setSetting('enrol_panel_mode', self::ENROL_EXISTING);
                if ($form->usesWaves() && !(int)$form->getSetting('panel_id', 0)) {
                    $form->setSetting('panel_id', (int)$panel->id);
                }
                $form->save(false, ['settings_json']);
            }
            return $panel;
        }
        return $this->getEnrolPanel($form);
    }

    public function handleCompletion(CustomForm $form, FormAnswer $answer): void
    {
        $mode = (string)$form->getSetting('enrol_panel_mode', self::ENROL_NONE);
        $enrol = $mode !== self::ENROL_NONE && $mode !== '';
        $log = $form->getSetting('log_panel_activity', false);
        $log = $log === true || $log === 1 || $log === '1';

        unset($answer->answerFields);
        $identity = $this->extractIdentity($form, $answer);
        $member = $answer->panel_member_id ? FormPanelMember::findOne((int)$answer->panel_member_id) : null;

        $panel = $enrol ? $this->resolveEnrolPanel($form) : null;
        if (!$panel) {
            $panel = $this->getPanel($form) ?: $this->getEnrolPanel($form);
        }
        if (!$panel && $member) {
            $panel = $member->panel;
        }

        $formEmail = strtolower(trim((string)$identity['email']));
        $user = $identity['user'] instanceof User ? $identity['user'] : null;
        if ($user && $formEmail !== '') {
            $userEmail = strtolower(trim((string)$user->email));
            if ($userEmail !== '' && $userEmail !== $formEmail) {
                $user = null;
                $identity['user'] = null;
            }
        }

        if ($panel && !$member) {
            $member = $this->findMemberOnPanel($panel, $user, $formEmail);
        }
        if ($panel && !$member && $formEmail === '') {
            $sessionUser = Yii::$app->user->getIdentity();
            if ($sessionUser instanceof User && !Yii::$app->user->isGuest) {
                $member = $this->findMemberOnPanel($panel, $sessionUser, (string)$sessionUser->email);
                if ($member) {
                    $identity['user'] = $sessionUser;
                    $identity['email'] = strtolower(trim((string)($member->email ?: $sessionUser->email)));
                } elseif (!$user) {
                    $identity['user'] = $sessionUser;
                    $identity['email'] = strtolower(trim((string)$sessionUser->email));
                }
            }
        }

        if ($member && $identity['email'] === '') {
            $identity['email'] = (string)$member->email;
        }

        $shouldAdd = $enrol || (bool)$this->getPanel($form) || (bool)$this->getEnrolPanel($form);
        $canIdentify = $identity['email'] !== '' || ($identity['user'] instanceof User);
        if ($shouldAdd && $panel && $canIdentify) {
            $member = $this->upsertMember($panel, $identity);
        } elseif ($panel && $member && !empty($identity['attrs'])) {
            $member = $this->upsertMember($panel, $identity);
        }

        if ($panel && $member) {
            if ((int)$answer->panel_member_id !== (int)$member->id) {
                $answer->panel_member_id = $member->id;
                $answer->save(false, ['panel_member_id', 'updated_at']);
            }
            if ($log || $this->getPanel($form) || $enrol) {
                $this->recordActivity($panel, $member, $form, $answer);
            }
        }

        if (!$answer->isTest()) {
            (new EmailTemplateService())->sendCompletionEmail($form, $answer, $member);
        }
    }

    public function recordActivity(FormPanel $panel, FormPanelMember $member, CustomForm $form, FormAnswer $answer): void
    {
        $exists = FormPanelActivity::find()
            ->where(['member_id' => $member->id, 'answer_id' => $answer->id])
            ->exists();
        if ($exists) {
            return;
        }
        $row = new FormPanelActivity();
        $row->panel_id = $panel->id;
        $row->member_id = $member->id;
        $row->form_id = $form->id;
        $row->answer_id = $answer->id;
        $row->save(false);
    }

    public function sendInvite(CustomForm $form, FormPanelMember $member, ?FormWave $wave = null): bool
    {
        return (new EmailTemplateService())->sendInviteEmail($form, $member, $wave);
    }

    public function sendDefaultInvite(CustomForm $form, FormPanelMember $member, ?FormWave $wave = null): bool
    {
        $email = trim((string)($member->email ?: ($member->user->email ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $url = Url::toPanelInvite($form, $member->token);
        if ($wave) {
            $subject = Yii::t('ThiscoveryFormsModule.base', '{wave} of “{title}” is open', [
                'wave' => $wave->getDisplayTitle(),
                'title' => $form->title,
            ]);
            $body = Yii::t(
                'ThiscoveryFormsModule.base',
                "A new wave of \"{title}\" is open: {wave}.\n\nOpen this personal link to take part:\n{url}\n\nPlease do not share this link. It is unique to you.",
                [
                    'title' => $form->title,
                    'wave' => $wave->getDisplayTitle(),
                    'url' => $url,
                ]
            );
        } else {
            $subject = Yii::t('ThiscoveryFormsModule.base', 'You are invited to “{title}”', [
                'title' => $form->title,
            ]);
            $body = Yii::t(
                'ThiscoveryFormsModule.base',
                "You have been invited to take part in \"{title}\".\n\nOpen this personal link:\n{url}\n\nPlease do not share this link. It is unique to you.",
                [
                    'title' => $form->title,
                    'url' => $url,
                ]
            );
        }

        $html = FormEmailLayout::wrap(nl2br(Html::encode($body)));

        try {
            return (bool)Yii::$app->mailer->compose()
                ->setTo($email)
                ->setSubject($subject)
                ->setHtmlBody($html)
                ->setTextBody($body)
                ->send();
        } catch (\Throwable $e) {
            Yii::error('Thiscovery Forms panel invite failed: ' . $e->getMessage(), 'thiscovery-forms');
            return false;
        }
    }

    /**
     * @return array{sent:int,failed:int}
     */
    public function inviteAll(CustomForm $form, ?FormWave $wave = null): array
    {
        $panel = $this->getPanel($form);
        $sent = 0;
        $failed = 0;
        if (!$panel) {
            return ['sent' => 0, 'failed' => 0];
        }
        foreach ($panel->getActiveMembers()->all() as $member) {
            if ($this->sendInvite($form, $member, $wave)) {
                $sent++;
            } else {
                $failed++;
            }
        }
        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Flatten a form answer into a single line of text. Nested arrays (e.g. map GeoJSON) are skipped.
     */
    private function scalarText($raw): string
    {
        if (!is_array($raw)) {
            return trim((string)$raw);
        }
        $parts = [];
        foreach ($raw as $item) {
            if (is_scalar($item) || $item === null) {
                $parts[] = (string)$item;
            }
        }
        return trim(implode(' ', $parts));
    }

    /**
     * @return FormPanel[]
     */
    public function listAvailable(CustomForm $form): array
    {
        $containerId = $form->isGlobal() ? null : ($form->content->contentcontainer_id ?? null);
        return $this->listAvailableForContainer($containerId);
    }

    /**
     * @return FormPanel[]
     */
    public function listAvailableForContainer($containerId = null): array
    {
        $query = FormPanel::find()->orderBy(['title' => SORT_ASC]);
        if ($containerId) {
            $query->andWhere([
                'or',
                ['contentcontainer_id' => $containerId],
                ['contentcontainer_id' => null],
            ]);
        } else {
            $query->andWhere(['contentcontainer_id' => null]);
        }
        return $query->all();
    }
}
