<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\helpers\Url;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use Yii;

/**
 * Save-and-continue-later: draft answers keyed by a human-friendly resume code.
 */
class ResumeService
{
    public const CODE_LENGTH = 12;

    public function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max = strlen($alphabet) - 1;
        do {
            $raw = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $raw .= $alphabet[random_int(0, $max)];
            }
            $code = substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-' . substr($raw, 8, 4);
        } while (FormAnswer::find()->where(['resume_code' => $code])->exists());

        return $code;
    }

    public function normalizeCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = preg_replace('/[^A-Z0-9]/', '', $code) ?? '';
        if (strlen($code) === self::CODE_LENGTH) {
            return substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 4);
        }
        return $code;
    }

    public function findDraftByCode(CustomForm $form, string $code): ?FormAnswer
    {
        $code = $this->normalizeCode($code);
        if ($code === '') {
            return null;
        }

        return FormAnswer::find()
            ->where([
                'form_id' => $form->id,
                'resume_code' => $code,
                'status' => FormAnswer::STATUS_IN_PROGRESS,
            ])
            ->one();
    }

    /**
     * Persist partial answers without requiring required fields.
     */
    public function saveDraft(
        CustomForm $form,
        SubmitForm $submit,
        ?FormAnswer $existing = null,
        bool $anonymous = false,
        ?string $email = null,
        ?int $currentPage = null
    ): ?FormAnswer {
        if ($existing && !$existing->isInProgress()) {
            $submit->addError('values', Yii::t('ThiscoveryFormsModule.base', 'This response has already been submitted.'));
            return null;
        }

        $answer = $submit->save($existing, $anonymous || $form->allowsAnonymous(), true);
        if (!$answer) {
            return null;
        }

        $dirty = [];
        if ($email !== null && $email !== '') {
            $answer->resume_email = $email;
            $dirty[] = 'resume_email';
        }
        if ($currentPage !== null) {
            $answer->current_page = max(0, $currentPage);
            $dirty[] = 'current_page';
        }
        if ($dirty) {
            $dirty[] = 'updated_at';
            $answer->save(false, $dirty);
        }

        return $answer;
    }

    public function sendResumeEmail(CustomForm $form, FormAnswer $answer, string $email): bool
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$answer->resume_code) {
            return false;
        }

        $answer->resume_email = $email;
        $answer->save(false, ['resume_email', 'updated_at']);

        $resumeUrl = Url::toResume($form, $answer->resume_code, true);
        $subject = Yii::t('ThiscoveryFormsModule.base', 'Your saved response code for "{title}"', [
            'title' => $form->title,
        ]);
        $body = Yii::t(
            'ThiscoveryFormsModule.base',
            "You saved your progress on the form \"{title}\".\n\nYour resume code is:\n{code}\n\nOpen this link to continue:\n{url}\n\nKeep this code private. Anyone with it can continue your response.",
            [
                'title' => $form->title,
                'code' => $answer->resume_code,
                'url' => $resumeUrl,
            ]
        );

        try {
            return (bool)Yii::$app->mailer->compose()
                ->setTo($email)
                ->setSubject($subject)
                ->setTextBody($body)
                ->send();
        } catch (\Throwable $e) {
            Yii::error('Thiscovery Forms resume email failed: ' . $e->getMessage(), 'thiscovery-forms');
            return false;
        }
    }
}
