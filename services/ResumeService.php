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
        ?int $currentPage = null,
        bool $isTest = false
    ): ?FormAnswer {
        if ($existing && !$existing->isInProgress()) {
            $submit->addError('values', Yii::t('ThiscoveryFormsModule.base', 'This response has already been submitted.'));
            return null;
        }

        $answer = $submit->save($existing, $anonymous || $form->allowsAnonymous() || $isTest, true, $isTest);
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

    /**
     * Remove autosave snapshot rows: keep one in-progress draft per person per fill session,
     * and drop leftovers from sessions that already completed.
     *
     * @return int Number of in-progress answers deleted
     */
    public function collapseSnapshotDrafts(): int
    {
        $drafts = FormAnswer::find()
            ->where([
                'status' => FormAnswer::STATUS_IN_PROGRESS,
                'is_test' => 0,
            ])
            ->andWhere(['IS NOT', 'created_by', null])
            ->orderBy(['form_id' => SORT_ASC, 'created_by' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $groups = [];
        foreach ($drafts as $draft) {
            $key = (int)$draft->form_id . ':' . (int)$draft->created_by;
            $groups[$key][] = $draft;
        }

        $deleted = 0;
        foreach ($groups as $rows) {
            $chains = $this->splitDraftChains($rows);
            foreach ($chains as $chain) {
                $deleted += $this->collapseChain($chain);
            }
        }

        return $deleted;
    }

    /**
     * @param FormAnswer[] $rows
     * @return FormAnswer[][]
     */
    protected function splitDraftChains(array $rows): array
    {
        $chains = [];
        $current = [];
        $prevTs = null;
        foreach ($rows as $row) {
            $ts = strtotime((string)$row->created_at) ?: 0;
            if ($current && $prevTs !== null && ($ts - $prevTs) > 120) {
                $chains[] = $current;
                $current = [];
            }
            $current[] = $row;
            $prevTs = $ts;
        }
        if ($current) {
            $chains[] = $current;
        }
        return $chains;
    }

    /**
     * @param FormAnswer[] $chain
     */
    protected function collapseChain(array $chain): int
    {
        if (!$chain) {
            return 0;
        }
        $first = $chain[0];
        $last = $chain[count($chain) - 1];
        $start = date('Y-m-d H:i:s', (strtotime((string)$first->created_at) ?: time()) - 5);
        $end = date('Y-m-d H:i:s', (strtotime((string)$last->updated_at ?: $last->created_at) ?: time()) + 15);

        $completedNearby = FormAnswer::find()
            ->where([
                'form_id' => $first->form_id,
                'status' => FormAnswer::STATUS_COMPLETE,
                'is_test' => 0,
            ])
            ->andWhere(['>=', 'created_at', $start])
            ->andWhere(['<=', 'created_at', $end])
            ->andWhere([
                'or',
                ['created_by' => $first->created_by],
                ['created_by' => null],
            ])
            ->exists();

        $keepId = $completedNearby ? 0 : (int)$last->id;
        $deleted = 0;
        foreach ($chain as $row) {
            if ((int)$row->id === $keepId) {
                continue;
            }
            if ($row->delete()) {
                $deleted++;
            }
        }
        return $deleted;
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
