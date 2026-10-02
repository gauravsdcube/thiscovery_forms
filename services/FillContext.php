<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use humhub\modules\thiscoveryForms\models\FormRound;
use humhub\modules\thiscoveryForms\models\FormWave;

/**
 * Request-scoped fill state for waves, rounds, panel tokens, and language.
 */
class FillContext
{
    public CustomForm $form;
    public string $language;
    public ?FormPanelMember $member = null;
    public ?FormWave $wave = null;
    public ?FormRound $round = null;
    public ?FormAnswer $previousRoundAnswer = null;
    public string $roundSummaryHtml = '';
    /** @var int[] */
    public array $frozenFieldIds = [];
    public bool $tokenAccess = false;
    public string $accessToken = '';
    public bool $accessTokenConsumed = false;
    /** This request was counted towards the submit rate limit, so a rejected save refunds it (INT-8). */
    public bool $rateCounted = false;
    public ?string $blockReason = null;

    public function __construct(CustomForm $form)
    {
        $this->form = $form;
        $this->language = $form->getSourceLanguage();
    }

    public function canSubmit(?FormAnswer $existing = null): bool
    {
        return $this->blockReason === null && $this->form->isOpen();
    }
}
