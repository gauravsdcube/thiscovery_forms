<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormPanel;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use humhub\modules\user\models\User;
use Yii;

class PanelService
{
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

        $panel = new FormPanel();
        $panel->title = Yii::t('ThiscoveryFormsModule.base', '{title} panel', [
            'title' => $form->title ?: Yii::t('ThiscoveryFormsModule.base', 'Survey'),
        ]);
        $panel->contentcontainer_id = $form->isGlobal() ? null : ($form->content->contentcontainer_id ?? null);
        $panel->created_by = Yii::$app->user->id;
        if (!$panel->save()) {
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
        $panel = $this->getPanel($form);
        if (!$panel) {
            return null;
        }
        return FormPanelMember::find()
            ->where(['panel_id' => $panel->id, 'token' => $token, 'status' => FormPanelMember::STATUS_ACTIVE])
            ->one();
    }

    public function findMemberForUser(CustomForm $form, ?User $user): ?FormPanelMember
    {
        if (!$user) {
            return null;
        }
        $panel = $this->getPanel($form);
        if (!$panel) {
            return null;
        }
        return FormPanelMember::find()
            ->where(['panel_id' => $panel->id, 'user_id' => $user->id, 'status' => FormPanelMember::STATUS_ACTIVE])
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

    public function addUserMember(FormPanel $panel, User $user, float $weight = 1): FormPanelMember
    {
        $existing = FormPanelMember::find()
            ->where(['panel_id' => $panel->id, 'user_id' => $user->id])
            ->one();
        if ($existing) {
            $existing->status = FormPanelMember::STATUS_ACTIVE;
            $existing->weight = $weight;
            $existing->display_name = $user->displayName;
            $existing->save(false);
            return $existing;
        }

        $member = new FormPanelMember();
        $member->panel_id = $panel->id;
        $member->user_id = $user->id;
        $member->email = $user->email ?: null;
        $member->display_name = $user->displayName;
        $member->weight = $weight;
        $member->status = FormPanelMember::STATUS_ACTIVE;
        $member->token = FormPanelMember::generateToken();
        $member->save(false);
        return $member;
    }

    public function addEmailMember(FormPanel $panel, string $email, ?string $displayName = null, float $weight = 1): FormPanelMember
    {
        $email = strtolower(trim($email));
        $existing = FormPanelMember::find()
            ->where(['panel_id' => $panel->id, 'email' => $email])
            ->one();
        if ($existing) {
            $existing->status = FormPanelMember::STATUS_ACTIVE;
            $existing->weight = $weight;
            if ($displayName) {
                $existing->display_name = $displayName;
            }
            $existing->save(false);
            return $existing;
        }

        $user = User::find()->where(['email' => $email])->one();
        if ($user) {
            return $this->addUserMember($panel, $user, $weight);
        }

        $member = new FormPanelMember();
        $member->panel_id = $panel->id;
        $member->email = $email;
        $member->display_name = $displayName ?: $email;
        $member->weight = $weight;
        $member->status = FormPanelMember::STATUS_ACTIVE;
        $member->token = FormPanelMember::generateToken();
        $member->save(false);
        return $member;
    }

    public function sendInvite(CustomForm $form, FormPanelMember $member, ?FormWave $wave = null): bool
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

        try {
            return (bool)Yii::$app->mailer->compose()
                ->setTo($email)
                ->setSubject($subject)
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
     * @return FormPanel[]
     */
    public function listAvailable(CustomForm $form): array
    {
        $containerId = $form->isGlobal() ? null : ($form->content->contentcontainer_id ?? null);
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
