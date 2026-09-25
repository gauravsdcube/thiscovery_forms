<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryEditor\helpers\EditorHtml;
use humhub\modules\thiscoveryForms\helpers\FormEmailLayout;
use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormEmailSend;
use humhub\modules\thiscoveryForms\models\FormEmailTemplate;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\FormPanel;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use humhub\modules\thiscoveryForms\models\FormWave;
use humhub\modules\thiscoveryForms\services\PanelFieldService;
use Yii;
use yii\helpers\Html;

class EmailTemplateService
{
    /**
     * @return FormEmailTemplate[]
     */
    public function listForContainer($containerId = null): array
    {
        $query = FormEmailTemplate::find()->orderBy(['title' => SORT_ASC, 'id' => SORT_ASC]);
        if ($containerId) {
            $query->andWhere([
                'or',
                ['contentcontainer_id' => (int)$containerId],
                ['contentcontainer_id' => null],
            ]);
        } else {
            $query->andWhere(['contentcontainer_id' => null]);
        }
        return $query->all();
    }

    /**
     * @return array<int,string>
     */
    public function optionsForContainer($containerId = null): array
    {
        $out = [0 => Yii::t('ThiscoveryFormsModule.base', 'Default email text')];
        foreach ($this->listForContainer($containerId) as $template) {
            $out[(int)$template->id] = $template->title;
        }
        return $out;
    }

    public function findOwned($id, $containerId = null): ?FormEmailTemplate
    {
        $query = FormEmailTemplate::find()->andWhere(['id' => (int)$id]);
        if ($containerId) {
            $query->andWhere(['contentcontainer_id' => (int)$containerId]);
        } else {
            $query->andWhere(['contentcontainer_id' => null]);
        }
        return $query->one();
    }

    public function render(FormEmailTemplate $template, array $vars): array
    {
        $subject = $this->replaceVars((string)$template->subject, $vars);
        $header = $this->renderPart((string)$template->header_html, $vars);
        $body = $this->renderPart((string)$template->body_html, $vars);
        $footer = $this->renderPart((string)$template->footer_html, $vars);
        $html = FormEmailLayout::wrap($body, $header, $footer, $template);
        $text = $this->htmlToText(trim($header . "\n\n" . $body . "\n\n" . $footer));
        return ['subject' => $subject, 'html' => $html, 'text' => $text];
    }

    public function renderPart(string $content, array $vars): string
    {
        $html = EditorHtml::toHtml($content);
        $html = $this->replaceVars($html, $vars);
        return FormEmailLayout::emailSafeHtml($html);
    }

    public function htmlToText(string $html): string
    {
        $html = str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</h1>', '</h2>', '</h3>'], ["\n", "\n", "\n", "\n\n", "\n", "\n\n", "\n\n", "\n\n"], $html);
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * @param array<string,string> $vars
     */
    public function replaceVars(string $text, array $vars): string
    {
        $map = [];
        foreach ($vars as $key => $value) {
            $value = (string)$value;
            $map['{{' . $key . '}}'] = $value;
            $map['{' . $key . '}'] = $value;
        }
        return strtr($text, $map);
    }

    public function varsFor(
        ?CustomForm $form = null,
        ?FormPanelMember $member = null,
        ?FormWave $wave = null,
        ?FormPanel $panel = null,
        array $extra = []
    ): array {
        $first = $member ? trim((string)$member->first_name) : '';
        $last = $member ? trim((string)$member->last_name) : '';
        $email = $member ? trim((string)($member->email ?: ($member->user->email ?? ''))) : '';
        $display = $member ? $member->getDisplayLabel() : '';
        $panel = $panel ?: ($member ? $member->panel : null);
        $formUrl = $form ? Url::toView($form, true) : '';
        if ($form && $member && $member->token) {
            $formUrl = Url::toPanelInvite($form, $member->token, true);
        }
        $vars = [
            'firstName' => $first,
            'lastName' => $last,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'displayName' => $display,
            'formTitle' => $form ? (string)$form->title : '',
            'form.title' => $form ? (string)$form->title : '',
            'formUrl' => $formUrl,
            'form.url' => $formUrl,
            'panelName' => $panel ? (string)$panel->title : '',
            'panel.name' => $panel ? (string)$panel->title : '',
            'waveTitle' => $wave ? $wave->getDisplayTitle() : '',
            'wave.title' => $wave ? $wave->getDisplayTitle() : '',
        ];
        if ($member) {
            foreach (PanelFieldService::variableMap($member, $panel) as $key => $value) {
                if (!array_key_exists($key, $vars)) {
                    $vars[$key] = $value;
                }
            }
        }
        return array_merge($vars, $extra);
    }

    public function sendTemplate(
        FormEmailTemplate $template,
        string $to,
        array $vars,
        array $meta = []
    ): bool {
        $to = strtolower(trim($to));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $rendered = $this->render($template, $vars);
        if (trim($rendered['subject']) === '' || trim($rendered['text']) === '') {
            return false;
        }
        try {
            $ok = (bool)Yii::$app->mailer->compose()
                ->setTo($to)
                ->setSubject($rendered['subject'])
                ->setHtmlBody($rendered['html'] !== '' ? $rendered['html'] : nl2br(Html::encode($rendered['text'])))
                ->setTextBody($rendered['text'] !== '' ? $rendered['text'] : strip_tags($rendered['html']))
                ->send();
        } catch (\Throwable $e) {
            Yii::error('Thiscovery Forms email template send failed: ' . $e->getMessage(), 'thiscovery-forms');
            return false;
        }
        if ($ok) {
            $this->logSend($template, $to, $meta);
        }
        return $ok;
    }

    protected function logSend(FormEmailTemplate $template, string $to, array $meta): void
    {
        $row = new FormEmailSend();
        $row->template_id = $template->id;
        $row->form_id = $meta['form_id'] ?? null;
        $row->member_id = $meta['member_id'] ?? null;
        $row->answer_id = $meta['answer_id'] ?? null;
        $row->wave_id = $meta['wave_id'] ?? null;
        $row->field_id = $meta['field_id'] ?? null;
        $row->kind = (string)($meta['kind'] ?? FormEmailSend::KIND_INVITE);
        $row->email = $to;
        $row->save(false);
    }

    public function hasSent(string $kind, array $where): bool
    {
        $query = FormEmailSend::find()->where(['kind' => $kind]);
        foreach (['form_id', 'member_id', 'answer_id', 'wave_id', 'field_id', 'template_id'] as $col) {
            if (array_key_exists($col, $where)) {
                $query->andWhere([$col => $where[$col]]);
            }
        }
        return $query->exists();
    }

    public function templateForForm(CustomForm $form, string $settingKey): ?FormEmailTemplate
    {
        $id = (int)$form->getSetting($settingKey, 0);
        return $id ? FormEmailTemplate::findOne($id) : null;
    }

    public function sendInviteEmail(CustomForm $form, FormPanelMember $member, ?FormWave $wave = null): bool
    {
        $kind = $wave && (int)$wave->wave_number >= 2 ? FormEmailSend::KIND_WAVE : FormEmailSend::KIND_INVITE;
        $key = $kind === FormEmailSend::KIND_WAVE ? 'wave_email_template_id' : 'invite_email_template_id';
        $template = $this->templateForForm($form, $key);
        if (!$template && $kind === FormEmailSend::KIND_WAVE) {
            $template = $this->templateForForm($form, 'invite_email_template_id');
        }
        $to = strtolower(trim((string)($member->email ?: ($member->user->email ?? ''))));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if ($template) {
            return $this->sendTemplate(
                $template,
                $to,
                $this->varsFor($form, $member, $wave),
                [
                    'form_id' => $form->id,
                    'member_id' => $member->id,
                    'wave_id' => $wave->id ?? null,
                    'kind' => $kind,
                ]
            );
        }
        return (new PanelService())->sendDefaultInvite($form, $member, $wave);
    }

    public function sendCompletionEmail(CustomForm $form, FormAnswer $answer, ?FormPanelMember $member = null): bool
    {
        $template = $this->templateForForm($form, 'completion_email_template_id');
        if (!$template) {
            return false;
        }
        if ($this->hasSent(FormEmailSend::KIND_COMPLETION, ['form_id' => $form->id, 'answer_id' => $answer->id])) {
            return false;
        }
        $to = $this->emailFromAnswer($form, $answer, $member, $answer->getValuesMap());
        if ($to === '') {
            return false;
        }
        return $this->sendTemplate(
            $template,
            $to,
            $this->varsFor($form, $member, $answer->wave ?? null),
            [
                'form_id' => $form->id,
                'member_id' => $member->id ?? null,
                'answer_id' => $answer->id,
                'kind' => FormEmailSend::KIND_COMPLETION,
            ]
        );
    }

    public function dispatchDueReminders(): void
    {
        $forms = CustomForm::findLive()
            ->andWhere(['like', 'settings_json', 'reminder_email_template_id'])
            ->all();
        foreach ($forms as $form) {
            $template = $this->templateForForm($form, 'reminder_email_template_id');
            $days = max(0, (int)$form->getSetting('reminder_days', 0));
            if (!$template || $days < 1 || !$form->isOpen() || !$form->usesWaves()) {
                continue;
            }
            $wave = (new WaveService())->getCurrentOpen($form);
            if (!$wave) {
                continue;
            }
            $panel = (new PanelService())->getPanel($form) ?: (new PanelService())->getEnrolPanel($form);
            if (!$panel) {
                continue;
            }
            $since = date('Y-m-d H:i:s', time() - ($days * 86400));
            $anchor = $wave && $wave->invited_at ? $wave->invited_at : ($wave->opens_at ?? null);
            if ($anchor && $anchor > $since) {
                continue;
            }
            foreach ($panel->getActiveMembers()->all() as $member) {
                $where = [
                    'form_id' => $form->id,
                    'member_id' => $member->id,
                    'wave_id' => $wave->id ?? null,
                ];
                if ($this->hasSent(FormEmailSend::KIND_REMINDER, $where)) {
                    continue;
                }
                $done = FormAnswer::find()
                    ->where([
                        'form_id' => $form->id,
                        'wave_id' => $wave->id,
                        'panel_member_id' => $member->id,
                        'is_test' => 0,
                    ])
                    ->andWhere(['status' => \humhub\modules\thiscoveryForms\models\FormAnswer::STATUS_COMPLETE])
                    ->exists();
                if ($done) {
                    continue;
                }
                $to = strtolower(trim((string)($member->email ?: ($member->user->email ?? ''))));
                if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $this->sendTemplate(
                    $template,
                    $to,
                    $this->varsFor($form, $member, $wave, $panel),
                    $where + ['kind' => FormEmailSend::KIND_REMINDER]
                );
            }
        }
    }

    /**
     * Address to email the respondent. Empty when none is known — never send or store a blank address.
     * Prefers an email question on this response, then save-and-resume (unless excluded), panel member,
     * the signed-in account on an identified answer, then the current logged-in user.
     *
     * @param array<int|string,mixed> $values Live field values (field actions may run before save)
     * @param bool $includeResumeEmail Save-and-resume addresses are for the resume code only, not action emails
     */
    public function emailFromAnswer(
        CustomForm $form,
        ?FormAnswer $answer = null,
        ?FormPanelMember $member = null,
        array $values = [],
        bool $includeResumeEmail = true
    ): string {
        foreach ($this->respondentEmailCandidates($form, $answer, $member, $values, $includeResumeEmail) as $candidate) {
            $email = strtolower(trim((string)$candidate));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }
        return '';
    }

    /**
     * @param array<int|string,mixed> $values
     * @return string[]
     */
    protected function respondentEmailCandidates(
        CustomForm $form,
        ?FormAnswer $answer,
        ?FormPanelMember $member,
        array $values,
        bool $includeResumeEmail = true
    ): array {
        $out = [];
        if ($values === [] && $answer) {
            $values = $answer->getValuesMap();
        }
        foreach ($form->fields as $field) {
            if ($field->type !== FormField::TYPE_EMAIL) {
                continue;
            }
            $raw = $values[$field->id]
                ?? $values[FormField::studioKey((int)$field->id)]
                ?? $values[(string)$field->id]
                ?? '';
            if (is_array($raw)) {
                $parts = [];
                foreach ($raw as $item) {
                    if (is_scalar($item) || $item === null) {
                        $parts[] = (string)$item;
                    }
                }
                $out[] = trim(implode(' ', $parts));
            } else {
                $out[] = (string)$raw;
            }
        }
        if ($answer) {
            if ($includeResumeEmail) {
                $out[] = (string)$answer->resume_email;
            }
            $identity = (new PanelService())->extractIdentity($form, $answer);
            $identityEmail = strtolower(trim((string)($identity['email'] ?? '')));
            $resumeEmail = strtolower(trim((string)$answer->resume_email));
            if ($includeResumeEmail || ($identityEmail !== '' && $identityEmail !== $resumeEmail)) {
                $out[] = $identityEmail;
            }
            if (!empty($identity['user'])) {
                $out[] = (string)($identity['user']->email ?? '');
            }
        }
        if ($member) {
            $out[] = (string)$member->email;
            $out[] = (string)($member->user->email ?? '');
        }
        if (!Yii::$app->user->isGuest) {
            $out[] = (string)(Yii::$app->user->identity->email ?? '');
        }
        return $out;
    }
}
