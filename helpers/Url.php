<?php

namespace humhub\modules\thiscoveryForms\helpers;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use yii\helpers\Url as BaseUrl;

class Url
{
    public static function toView(CustomForm $form, $scheme = false): string
    {
        if ($form->isGlobal()) {
            // Prefer path-style id so query strings are not dropped by HTTP→HTTPS redirects.
            $url = BaseUrl::to(['/thiscovery-forms/global/view', 'id' => $form->id], $scheme);
            return $scheme ? self::ensureHttps($url) : $url;
        }

        $url = $form->content->container->createUrl('/thiscovery-forms/form/view', ['id' => $form->id], $scheme);
        return $scheme ? self::ensureHttps($url) : $url;
    }

    public static function toResume(CustomForm $form, ?string $code = null, $scheme = false): string
    {
        $params = ['id' => $form->id];
        if ($code) {
            $params['resume'] = $code;
        }

        if ($form->isGlobal()) {
            $url = BaseUrl::to(array_merge(['/thiscovery-forms/global/view'], $params), $scheme);
            return $scheme ? self::ensureHttps($url) : $url;
        }

        $url = $form->content->container->createUrl('/thiscovery-forms/form/view', $params, $scheme);
        return $scheme ? self::ensureHttps($url) : $url;
    }

    public static function toSaveProgress(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/save-progress', 'id' => $form->id]);
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/save-progress', ['id' => $form->id]);
    }

    public static function toLookupResume(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/resume', 'id' => $form->id]);
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/resume', ['id' => $form->id]);
    }

    public static function toEmailResume(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/email-resume', 'id' => $form->id]);
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/email-resume', ['id' => $form->id]);
    }

    /**
     * Absolute share links must always be https — the app sits behind an ALB that
     * terminates TLS, so Yii often believes the request is http and would otherwise
     * emit http:// URLs that fail for guests on first visit.
     */
    public static function ensureHttps(string $url): string
    {
        if (str_starts_with($url, 'http://')) {
            return 'https://' . substr($url, 7);
        }
        return $url;
    }

    public static function toEdit(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/edit', 'id' => $form->id]);
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/edit', ['id' => $form->id]);
    }

    public static function toCreate($container = null): string
    {
        if ($container === null) {
            return BaseUrl::to(['/thiscovery-forms/global/create']);
        }

        return $container->createUrl('/thiscovery-forms/form/create');
    }

    public static function toIndex($container = null): string
    {
        if ($container === null) {
            return BaseUrl::to(['/thiscovery-forms/global/index']);
        }

        return $container->createUrl('/thiscovery-forms/form/index');
    }

    public static function toAnswers(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/answers', 'id' => $form->id]);
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/answers', ['id' => $form->id]);
    }

    public static function toExport(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/export', 'id' => $form->id]);
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/export', ['id' => $form->id]);
    }

    public static function toEditAnswer(CustomForm $form, FormAnswer $answer): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/edit-answer', 'id' => $form->id, 'answerId' => $answer->id]);
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/edit-answer', [
            'id' => $form->id,
            'answerId' => $answer->id,
        ]);
    }

    public static function toDelete(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/delete', 'id' => $form->id]);
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/delete', ['id' => $form->id]);
    }

    public static function toDashboard(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/dashboard', 'id' => $form->id]);
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/dashboard', ['id' => $form->id]);
    }

    public static function toOverview($container = null): string
    {
        if ($container === null) {
            return BaseUrl::to(['/thiscovery-forms/global/overview']);
        }

        return $container->createUrl('/thiscovery-forms/form/overview');
    }
}
