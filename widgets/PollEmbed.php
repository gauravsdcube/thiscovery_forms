<?php

namespace humhub\modules\thiscoveryForms\widgets;

use humhub\components\Widget;
use humhub\modules\thiscoveryForms\assets\ThiscoveryFormsAsset;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use humhub\modules\thiscoveryForms\services\DashboardService;
use Yii;

/**
 * Inline poll fill + live results (stream cards and page blocks).
 */
class PollEmbed extends Widget
{
    public CustomForm $form;

    /** Compact stream card vs page block. */
    public bool $compact = true;

    public function run()
    {
        ThiscoveryFormsAsset::register($this->view);

        $form = $this->form;
        $already = $this->hasAnswered($form);
        $showResults = $form->showsPollResults();
        $canVote = $form->canAnswer() && !$already;

        $results = [];
        if ($showResults && ($already || $form->isClosed() || !$canVote)) {
            $results = (new DashboardService())->getPollResults($form);
        } elseif ($showResults && $form->getAnswers()->count() > 0 && !$canVote) {
            $results = (new DashboardService())->getPollResults($form);
        }

        $submit = new SubmitForm(['form' => $form]);
        $question = $form->getPollQuestion();

        return $this->render('@thiscovery-forms/views/widgets/poll-embed', [
            'formModel' => $form,
            'submit' => $submit,
            'question' => $question,
            'canVote' => $canVote,
            'already' => $already,
            'showResults' => $showResults,
            'results' => $results,
            'compact' => $this->compact,
        ]);
    }

    protected function hasAnswered(CustomForm $form): bool
    {
        if ($form->allowsAnonymous()) {
            return $form->hasGuestAnswered();
        }
        $user = Yii::$app->user->getIdentity();
        if (!$user) {
            return false;
        }
        return $form->hasUserAnswered($user);
    }
}
