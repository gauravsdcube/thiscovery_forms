<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormPanelActivity;
use Yii;

class FillContextService
{
    public function resolve(CustomForm $form): FillContext
    {
        $ctx = new FillContext($form);
        $ctx->language = (new TranslationService())->resolve($form);
        ParticipantMessages::bind($form, (string)$ctx->language);
        $ctx->accessToken = self::readAccessToken();

        $token = trim((string)Yii::$app->request->get('token', Yii::$app->request->post('panel_token', '')));
        $panel = new PanelService();

        if ($form->usesWaves()) {
            $this->resolveWaves($ctx, $panel, $token);
        } elseif ($form->isConsensus()) {
            $this->resolveConsensus($ctx, $panel, $token);
        } else {
            $ctx->member = $panel->resolveMember($form, $token !== '' ? $token : null);
            $ctx->tokenAccess = $ctx->member && $token !== '' && (int)(\humhub\modules\thiscoveryForms\models\FormPanelMember::fromLinkToken($token)->id ?? 0) === (int)$ctx->member->id;
        }

        return $ctx;
    }

    /**
     * Invitation links use ?access=. The fill form posts that value back as
     * access_token. Panel member tokens are a different parameter.
     */
    public static function readAccessToken(): string
    {
        $access = trim((string)Yii::$app->request->get('access', ''));
        if ($access !== '') {
            return $access;
        }
        return trim((string)Yii::$app->request->post('access_token', ''));
    }

    private function resolveWaves(FillContext $ctx, PanelService $panel, string $token): void
    {
        $form = $ctx->form;
        $ctx->wave = (new WaveService())->getCurrentOpen($form);
        if (!$ctx->wave) {
            $ctx->blockReason = Yii::t('ThiscoveryFormsModule.base', 'There is no open wave for this survey yet.');
            return;
        }

        $ctx->member = $panel->resolveMember($form, $token ?: null);
        $ctx->tokenAccess = $ctx->member && $token !== '' && (int)(\humhub\modules\thiscoveryForms\models\FormPanelMember::fromLinkToken($token)->id ?? 0) === (int)$ctx->member->id;

        if ($ctx->member) {
            return;
        }

        $firstWave = (int)$ctx->wave->wave_number < 2;
        $openFill = $form->canAnswer() || $form->allowsAnonymous();
        if ($firstWave && $openFill) {
            return;
        }

        $ctx->blockReason = Yii::t(
            'ThiscoveryFormsModule.base',
            'You need a panel invitation to take part in this survey.'
        );
    }

    private function resolveConsensus(FillContext $ctx, PanelService $panel, string $token): void
    {
        $form = $ctx->form;
        $rounds = new RoundService();
        $ctx->round = $rounds->getCurrentOpen($form);
        if (!$ctx->round) {
            $ctx->blockReason = Yii::t('ThiscoveryFormsModule.base', 'There is no open round for this survey yet.');
            return;
        }

        $ctx->member = $panel->resolveMember($form, $token ?: null);
        $ctx->tokenAccess = $ctx->member && $token !== '' && (int)(\humhub\modules\thiscoveryForms\models\FormPanelMember::fromLinkToken($token)->id ?? 0) === (int)$ctx->member->id;

        $previous = $rounds->previousRound($ctx->round);
        if ($previous) {
            if ($previous->hasPublishedSummary()) {
                $ctx->roundSummaryHtml = (string)$previous->summary_html;
            }
            // Frozen items do not wait for the summary to be published (SCO-9).
            $ctx->frozenFieldIds = $rounds->frozenFor($form, $previous);
            $ctx->previousRoundAnswer = $this->findScopedAnswer($form, $ctx, $previous->id, 'round_id');
        }
    }

    public function findScopedAnswer(CustomForm $form, FillContext $ctx, ?int $scopeId, string $column): ?FormAnswer
    {
        if (!$scopeId) {
            return null;
        }
        $query = FormAnswer::find()->where(['form_id' => $form->id, $column => $scopeId, 'is_test' => 0]);
        if ($ctx->member) {
            $query->andWhere(['panel_member_id' => $ctx->member->id]);
        } else {
            $user = Yii::$app->user->getIdentity();
            if (!$user) {
                return $this->anonymousWaveMarker($form, $ctx, $scopeId, $column);
            }
            $query->andWhere(['created_by' => $user->id]);
        }
        $found = $query->orderBy(['id' => SORT_DESC])->one();
        if ($found) {
            return $found;
        }
        return $this->anonymousWaveMarker($form, $ctx, $scopeId, $column);
    }

    /**
     * A fully anonymous completion is not stored on the answer. The completion
     * row is what stops the same member submitting the wave again.
     */
    private function anonymousWaveMarker(CustomForm $form, FillContext $ctx, ?int $scopeId, string $column): ?FormAnswer
    {
        if ($column !== 'wave_id' || !$scopeId || !$ctx->member || !$form->hidesIdentityFromManagers()) {
            return null;
        }
        if (!\humhub\modules\thiscoveryForms\Module::identityEnforced()) {
            return null;
        }
        $done = FormPanelActivity::find()->where([
            'form_id' => (int)$form->id,
            'member_id' => (int)$ctx->member->id,
            'wave_id' => (int)$scopeId,
            'answer_id' => null,
        ])->exists();
        if (!$done) {
            return null;
        }
        $marker = new FormAnswer();
        $marker->form_id = (int)$form->id;
        $marker->status = FormAnswer::STATUS_COMPLETE;
        $marker->wave_id = (int)$scopeId;
        $marker->is_test = 0;
        return $marker;
    }
}
