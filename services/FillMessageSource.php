<?php

namespace humhub\modules\thiscoveryForms\services;

use yii\i18n\MessageSource;

/**
 * While a participant is filling a form, catalogue sentences use that form's language.
 * Every other module sentence keeps the staff member's language.
 */
class FillMessageSource extends MessageSource
{
    public function __construct(private MessageSource $inner, $config = [])
    {
        parent::__construct($config);
    }

    public function translate($category, $message, $language)
    {
        if (ParticipantMessages::isActive()) {
            $text = ParticipantMessages::catalogText($message);
            if ($text !== null) {
                return $text;
            }
        }
        return $this->inner->translate($category, $message, $language);
    }

    protected function loadMessages($category, $language)
    {
        return [];
    }
}
