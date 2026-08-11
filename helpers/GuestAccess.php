<?php

namespace humhub\modules\thiscoveryForms\helpers;

use humhub\modules\thiscoveryForms\models\CustomForm;
use Yii;
use yii\web\ForbiddenHttpException;

/**
 * Guest access checks for public form fill links.
 */
class GuestAccess
{
    /**
     * Ensures the current guest may open this form without logging in.
     *
     * @throws ForbiddenHttpException when guest access is not allowed (never redirects to login/home).
     */
    public static function assertCanView(CustomForm $form): void
    {
        if (!Yii::$app->user->isGuest) {
            return;
        }

        if (!$form->allowsAnonymous()) {
            throw new ForbiddenHttpException(Yii::t(
                'ThiscoveryFormsModule.base',
                'This form is not available to guests. Enable “Allow anonymous submissions”, set the form to Open, save, then share the link again.'
            ));
        }

        if (!$form->isOpen()) {
            throw new ForbiddenHttpException(Yii::t(
                'ThiscoveryFormsModule.base',
                'This form is not open for responses yet.'
            ));
        }

        if (!$form->content || !$form->content->isPublic()) {
            throw new ForbiddenHttpException(Yii::t(
                'ThiscoveryFormsModule.base',
                'This form is not publicly accessible. Save the form again after enabling anonymous submissions.'
            ));
        }
    }
}
