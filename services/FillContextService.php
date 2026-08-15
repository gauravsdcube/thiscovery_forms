<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use Yii;

class FillContextService
{
    public function resolve(CustomForm $form): FillContext
    {
        $ctx = new FillContext($form);
        $ctx->language = (new TranslationService())->resolve($form);

        $token = trim((string)Yii::$app->request->get('token', Yii::$app->request->post('panel_token', '')));
        $panel = new PanelService();

        if ($form->isLongitudinal()) {
            $this->resolveLongitudinal($ctx, $panel, $token);
        } elseif ($form->isConsensus()) {
            $this->resolveConsensus($ctx, $panel, $token);
        }

        return $ctx;
    }

    private function resolveLongitudinal(FillContext $ctx, PanelService $panel, string $token): void
    {
        $form = $ctx->form;
        $ctx->wave = (new WaveService())->getCurrentOpen($form);
        if (!$ctx->wave) {
            $ctx->blockReason = Yii::t('ThiscoveryFormsModule.base', 'There is no open wave for this survey yet.');
            return;
        }

        $ctx->member = $panel->resolveMember($form, $token ?: null);
        $ctx->tokenAccess = $ctx->member && $token !== '' && $ctx->member->token === $token;

        if (!$ctx->member) {
            $ctx->blockReason = Yii::t(
                'ThiscoveryFormsModule.base',
                'You need a panel invitation to take part in this survey.'
            );
        }
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
        $ctx->tokenAccess = $ctx->member && $token !== '' && $ctx->member->token === $token;

        $previous = $rounds->previousRound($ctx->round);
        if ($previous) {
            if ($previous->hasPublishedSummary()) {
                $ctx->roundSummaryHtml = (string)$previous->summary_html;
            }
            $ctx->frozenFieldIds = $previous->getFrozenFieldIds();
            $ctx->previousRoundAnswer = $this->findScopedAnswer($form, $ctx, $previous->id, 'round_id');
        }
    }

    public function findScopedAnswer(CustomForm $form, FillContext $ctx, ?int $scopeId, string $column): ?FormAnswer
    {
        if (!$scopeId) {
            return null;
        }
        $query = FormAnswer::find()->where(['form_id' => $form->id, $column => $scopeId]);
        if ($ctx->member) {
            $query->andWhere(['panel_member_id' => $ctx->member->id]);
        } else {
            $user = Yii::$app->user->getIdentity();
            if (!$user) {
                return null;
            }
            $query->andWhere(['created_by' => $user->id]);
        }
        return $query->orderBy(['id' => SORT_DESC])->one();
    }
}
