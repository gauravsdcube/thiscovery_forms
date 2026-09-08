<?php

namespace humhub\modules\thiscoveryForms\helpers;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use Yii;
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

    public static function toStartNew(CustomForm $form): string
    {
        return self::withFillContext(self::toView($form), ['start' => 'new']);
    }

    public static function toContinueOwn(CustomForm $form): string
    {
        return self::withFillContext(self::toView($form), ['continue' => '1']);
    }

    /**
     * Keep panel token and language on fill-page links.
     */
    protected static function withFillContext(string $url, array $extra = []): string
    {
        $token = (string)Yii::$app->request->get('token', '');
        if ($token !== '' && !isset($extra['token'])) {
            $extra['token'] = $token;
        }
        $preview = (string)Yii::$app->request->get('preview', '');
        if ($preview !== '' && !isset($extra['preview'])) {
            $extra['preview'] = $preview;
        }
        $lang = (string)Yii::$app->request->get('lang', '');
        if ($lang !== '' && !isset($extra['lang'])) {
            $extra['lang'] = $lang;
        }
        $extra = array_filter($extra, static fn($v) => $v !== null && $v !== '');
        if (!$extra) {
            return $url;
        }
        $sep = str_contains($url, '?') ? '&' : '?';
        return $url . $sep . http_build_query($extra);
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

    public static function toFillUpload(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/upload', 'id' => $form->id]);
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/upload', ['id' => $form->id]);
    }

    public static function toFillDeleteFile(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/delete-file', 'id' => $form->id]);
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/delete-file', ['id' => $form->id]);
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

    public static function toEdit(CustomForm $form, array $params = []): string
    {
        $params = array_merge(['id' => $form->id], $params);
        if ($form->isGlobal()) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/global/edit'], $params));
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/edit', $params);
    }

    public static function toCreate($container = null, array $params = []): string
    {
        if ($container === null) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/global/create'], $params));
        }

        return $container->createUrl('/thiscovery-forms/form/create', $params);
    }

    public static function toIndex($container = null, array $params = []): string
    {
        if ($container === null) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/global/index'], $params));
        }

        return $container->createUrl('/thiscovery-forms/form/index', $params);
    }

    /**
     * Forms list used when managing (admin sidebar for network forms).
     */
    public static function toManageIndex($container = null, array $params = []): string
    {
        if ($container === null) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/admin/index'], $params));
        }

        return $container->createUrl('/thiscovery-forms/form/index', $params);
    }

    public static function toFolderEdit($container = null, $folderId = null, array $params = []): string
    {
        if ($folderId) {
            $params['id'] = (int)$folderId;
        }
        if ($container === null) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/admin/folder-edit'], $params));
        }
        return $container->createUrl('/thiscovery-forms/form/folder-edit', $params);
    }

    public static function toFolderDelete($container = null, int $folderId): string
    {
        $params = ['id' => $folderId];
        if ($container === null) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/admin/folder-delete'], $params));
        }
        return $container->createUrl('/thiscovery-forms/form/folder-delete', $params);
    }

    public static function toMoveForm(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/admin/move-form', 'id' => $form->id]);
        }
        return $form->content->container->createUrl('/thiscovery-forms/form/move-form', ['id' => $form->id]);
    }

    public static function toAdminSettings(): string
    {
        return BaseUrl::to(['/thiscovery-forms/admin/settings']);
    }

    public static function toAnswers(CustomForm $form, array $params = []): string
    {
        $params = array_merge(['id' => $form->id], $params);
        if ($form->isGlobal()) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/global/answers'], $params));
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/answers', $params);
    }

    public static function toAnswerDetail(CustomForm $form, $answerId): string
    {
        $params = ['id' => $form->id, 'answerId' => (int)$answerId];
        if ($form->isGlobal()) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/global/answer-detail'], $params));
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/answer-detail', $params);
    }

    public static function toExport(CustomForm $form, array $params = []): string
    {
        $params = array_merge(['id' => $form->id], $params);
        if ($form->isGlobal()) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/global/export'], $params));
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/export', $params);
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

    public static function toProject(CustomForm $form, FormAnswer $answer, $scheme = false): string
    {
        $params = ['id' => $form->id, 'answerId' => $answer->id];
        if ($form->isGlobal()) {
            $url = BaseUrl::to(array_merge(['/thiscovery-forms/global/project'], $params), $scheme);
            return $scheme ? self::ensureHttps($url) : $url;
        }
        $url = $form->content->container->createUrl('/thiscovery-forms/form/project', $params, $scheme);
        return $scheme ? self::ensureHttps($url) : $url;
    }

    public static function toCatalogue(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/catalogue', 'id' => $form->id]);
        }
        return $form->content->container->createUrl('/thiscovery-forms/form/catalogue', ['id' => $form->id]);
    }

    public static function toAnswerApprove(CustomForm $form, FormAnswer $answer): string
    {
        $params = ['id' => $form->id, 'answerId' => $answer->id];
        if ($form->isGlobal()) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/global/answer-approve'], $params));
        }
        return $form->content->container->createUrl('/thiscovery-forms/form/answer-approve', $params);
    }

    public static function toAnswerChanges(CustomForm $form, FormAnswer $answer): string
    {
        $params = ['id' => $form->id, 'answerId' => $answer->id];
        if ($form->isGlobal()) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/global/answer-changes'], $params));
        }
        return $form->content->container->createUrl('/thiscovery-forms/form/answer-changes', $params);
    }

    public static function toAnswerArchive(CustomForm $form, FormAnswer $answer): string
    {
        $params = ['id' => $form->id, 'answerId' => $answer->id];
        if ($form->isGlobal()) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/global/answer-archive'], $params));
        }
        return $form->content->container->createUrl('/thiscovery-forms/form/answer-archive', $params);
    }

    public static function toDelete(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/delete', 'id' => $form->id]);
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/delete', ['id' => $form->id]);
    }

    public static function toPreview(CustomForm $form, $scheme = true): string
    {
        $url = self::toView($form, $scheme);
        $token = $form->getTestToken();
        $sep = str_contains($url, '?') ? '&' : '?';
        return $url . $sep . http_build_query(['preview' => $token]);
    }

    public static function toPublicDashboard(CustomForm $form, $scheme = true): string
    {
        $params = ['id' => $form->id, 'share' => $form->getPublicDashboardToken()];
        if ($form->isGlobal()) {
            $url = BaseUrl::to(array_merge(['/thiscovery-forms/global/public-dashboard'], $params), $scheme);
            return $scheme ? self::ensureHttps($url) : $url;
        }
        $url = $form->content->container->createUrl('/thiscovery-forms/form/public-dashboard', $params, $scheme);
        return $scheme ? self::ensureHttps($url) : $url;
    }

    public static function toRegeneratePreview(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/regenerate-preview', 'id' => $form->id]);
        }
        return $form->content->container->createUrl('/thiscovery-forms/form/regenerate-preview', ['id' => $form->id]);
    }

    public static function toRegenerateDashboardShare(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/regenerate-dashboard-share', 'id' => $form->id]);
        }
        return $form->content->container->createUrl('/thiscovery-forms/form/regenerate-dashboard-share', ['id' => $form->id]);
    }

    public static function toDashboard(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/dashboard', 'id' => $form->id]);
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/dashboard', ['id' => $form->id]);
    }

    public static function toIntegrity(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/integrity', 'id' => $form->id]);
        }
        return $form->content->container->createUrl('/thiscovery-forms/form/integrity', ['id' => $form->id]);
    }

    public static function toIntegrityStatus(CustomForm $form, int $answerId): string
    {
        $params = ['id' => $form->id, 'answerId' => $answerId];
        if ($form->isGlobal()) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/global/integrity-status'], $params));
        }
        return $form->content->container->createUrl('/thiscovery-forms/form/integrity-status', $params);
    }

    public static function toIntegrityNote(CustomForm $form, int $answerId): string
    {
        $params = ['id' => $form->id, 'answerId' => $answerId];
        if ($form->isGlobal()) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/global/integrity-note'], $params));
        }
        return $form->content->container->createUrl('/thiscovery-forms/form/integrity-note', $params);
    }

    public static function toAccessTokens(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/access-tokens', 'id' => $form->id]);
        }
        return $form->content->container->createUrl('/thiscovery-forms/form/access-tokens', ['id' => $form->id]);
    }

    public static function toUniqueInvite(CustomForm $form, string $token): string
    {
        $url = self::toView($form, true);
        $sep = str_contains($url, '?') ? '&' : '?';
        return $url . $sep . 'access=' . urlencode($token);
    }

    public static function toOverview($container = null): string
    {
        if ($container === null) {
            return BaseUrl::to(['/thiscovery-forms/global/overview']);
        }

        return $container->createUrl('/thiscovery-forms/form/overview');
    }

    public static function toSubmitJson(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/submit-json', 'id' => $form->id]);
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/submit-json', ['id' => $form->id]);
    }

    public static function toSaveTemplate(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/save-template', 'id' => $form->id]);
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/save-template', ['id' => $form->id]);
    }

    public static function toExportQuestions(CustomForm $form, string $format = 'json'): string
    {
        $params = ['id' => $form->id, 'format' => $format];
        if ($form->isGlobal()) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/global/export-questions'], $params));
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/export-questions', $params);
    }

    public static function toSampleQuestions($container = null, string $format = 'json'): string
    {
        $params = ['format' => $format];
        if ($container === null) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/global/sample-questions'], $params));
        }

        return $container->createUrl('/thiscovery-forms/form/sample-questions', $params);
    }

    public static function toImportQuestions(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/import-questions', 'id' => $form->id]);
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/import-questions', ['id' => $form->id]);
    }

    public static function toExportTranslations(CustomForm $form, string $format = 'csv'): string
    {
        $params = ['id' => $form->id, 'format' => $format];
        if ($form->isGlobal()) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/global/export-translations'], $params));
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/export-translations', $params);
    }

    public static function toImportTranslations(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/import-translations', 'id' => $form->id]);
        }

        return $form->content->container->createUrl('/thiscovery-forms/form/import-translations', ['id' => $form->id]);
    }

    public static function toLibraryList($container = null): string
    {
        if ($container === null) {
            return BaseUrl::to(['/thiscovery-forms/global/library-list']);
        }

        return $container->createUrl('/thiscovery-forms/form/library-list');
    }

    public static function toLibrarySave($container = null): string
    {
        if ($container === null) {
            return BaseUrl::to(['/thiscovery-forms/global/library-save']);
        }

        return $container->createUrl('/thiscovery-forms/form/library-save');
    }

    public static function toLibraryDelete($container = null): string
    {
        if ($container === null) {
            return BaseUrl::to(['/thiscovery-forms/global/library-delete']);
        }

        return $container->createUrl('/thiscovery-forms/form/library-delete');
    }

    public static function toLibraryInsert($container = null): string
    {
        if ($container === null) {
            return BaseUrl::to(['/thiscovery-forms/global/library-insert']);
        }

        return $container->createUrl('/thiscovery-forms/form/library-insert');
    }

    public static function toHealthStatusInsert($container = null): string
    {
        if ($container === null) {
            return BaseUrl::to(['/thiscovery-forms/global/insert-health-status']);
        }

        return $container->createUrl('/thiscovery-forms/form/insert-health-status');
    }

    public static function toPanelInvite(CustomForm $form, string $token, $scheme = true): string
    {
        $url = self::toView($form, $scheme);
        $sep = str_contains($url, '?') ? '&' : '?';
        return $url . $sep . 'token=' . urlencode($token);
    }

    public static function studioAction(CustomForm $form, string $action): string
    {
        $route = $form->isGlobal()
            ? '/thiscovery-forms/global/' . $action
            : '/thiscovery-forms/form/' . $action;
        $params = ['id' => $form->id];
        if ($form->isGlobal()) {
            return BaseUrl::to(array_merge([$route], $params));
        }
        return $form->content->container->createUrl($route, $params);
    }

    public static function toFillLanguage(CustomForm $form, string $lang): string
    {
        $extra = ['lang' => $lang];
        foreach (['start', 'resume', 'continue'] as $key) {
            $val = (string)Yii::$app->request->get($key, '');
            if ($val !== '') {
                $extra[$key] = $val;
            }
        }
        return self::withFillContext(self::toView($form), $extra);
    }

    public static function toPanelIndex($container = null, array $params = []): string
    {
        if ($container === null) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/admin/panels'], $params));
        }
        return $container->createUrl('/thiscovery-forms/form/panels', $params);
    }

    public static function toPanelEdit($container = null, $panelId = null, array $params = []): string
    {
        if ($panelId) {
            $params['id'] = (int)$panelId;
        }
        if ($container === null) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/admin/panel-edit'], $params));
        }
        return $container->createUrl('/thiscovery-forms/form/panel-edit', $params);
    }

    public static function toPanelView($panel, $container = null): string
    {
        $id = is_object($panel) ? (int)$panel->id : (int)$panel;
        if (is_object($panel) && $container === null) {
            $container = $panel->getContentContainer();
        }
        if ($container === null) {
            return BaseUrl::to(['/thiscovery-forms/admin/panel-view', 'id' => $id]);
        }
        return $container->createUrl('/thiscovery-forms/form/panel-view', ['id' => $id]);
    }

    public static function toPanelDelete($panel, $container = null): string
    {
        $id = is_object($panel) ? (int)$panel->id : (int)$panel;
        if (is_object($panel) && $container === null) {
            $container = $panel->getContentContainer();
        }
        if ($container === null) {
            return BaseUrl::to(['/thiscovery-forms/admin/panel-delete', 'id' => $id]);
        }
        return $container->createUrl('/thiscovery-forms/form/panel-delete', ['id' => $id]);
    }

    public static function toPanelMemberAdd($panel, $container = null): string
    {
        return self::panelAction($panel, 'panel-member-add', $container);
    }

    public static function toPanelMemberRemove($panel, $container = null): string
    {
        return self::panelAction($panel, 'panel-member-remove', $container);
    }

    public static function toPanelImport($panel, $container = null): string
    {
        return self::panelAction($panel, 'panel-import', $container);
    }

    public static function toPanelSample($container = null, $panel = null): string
    {
        $params = [];
        if ($panel) {
            $params['id'] = is_object($panel) ? (int)$panel->id : (int)$panel;
        }
        if ($container === null) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/admin/panel-sample'], $params));
        }
        return $container->createUrl('/thiscovery-forms/form/panel-sample', $params);
    }

    public static function toPanelWaveSave($panel, $container = null): string
    {
        return self::panelAction($panel, 'panel-wave-save', $container);
    }

    public static function toPanelWaveStatus($panel, $container = null): string
    {
        return self::panelAction($panel, 'panel-wave-status', $container);
    }

    public static function toPanelMember($member, $container = null): string
    {
        $id = is_object($member) ? (int)$member->id : (int)$member;
        if (is_object($member) && $container === null && $member->panel) {
            $container = $member->panel->getContentContainer();
        }
        if ($container === null) {
            return BaseUrl::to(['/thiscovery-forms/admin/panel-member', 'id' => $id]);
        }
        return $container->createUrl('/thiscovery-forms/form/panel-member', ['id' => $id]);
    }

    public static function toEmailTemplateIndex($container = null, array $params = []): string
    {
        if ($container === null) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/admin/email-templates'], $params));
        }
        return $container->createUrl('/thiscovery-forms/form/email-templates', $params);
    }

    public static function toEmailTemplateEdit($container = null, $templateId = null, array $params = []): string
    {
        if ($templateId) {
            $params['id'] = (int)$templateId;
        }
        if ($container === null) {
            // Use GlobalController, not Administration. POSTing Thiscovery Editor HTML
            // to /thiscovery-forms/admin/... is often rejected as 403.
            return BaseUrl::to(array_merge(['/thiscovery-forms/global/email-template-edit'], $params));
        }
        return $container->createUrl('/thiscovery-forms/form/email-template-edit', $params);
    }

    public static function toEmailTemplateDelete($template, $container = null): string
    {
        $id = is_object($template) ? (int)$template->id : (int)$template;
        if ($container === null) {
            return BaseUrl::to(['/thiscovery-forms/global/email-template-delete', 'id' => $id]);
        }
        return $container->createUrl('/thiscovery-forms/form/email-template-delete', ['id' => $id]);
    }

    public static function toRunActions(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/run-actions', 'id' => $form->id]);
        }
        return $form->content->container->createUrl('/thiscovery-forms/form/run-actions', ['id' => $form->id]);
    }

    public static function toHelp($container = null, ?string $page = null): string
    {
        $params = [];
        if ($page) {
            $params['page'] = $page;
        }
        if ($container === null) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/admin/help'], $params));
        }
        return $container->createUrl('/thiscovery-forms/form/help', $params);
    }

    /**
     * Secured Help attachment download.
     */
    public static function toHelpDownload($container = null, string $file = ''): string
    {
        $params = ['file' => $file];
        if ($container === null) {
            return BaseUrl::to(array_merge(['/thiscovery-forms/admin/help-download'], $params));
        }
        return $container->createUrl('/thiscovery-forms/form/help-download', $params);
    }

    /**
     * Base URL for the public form-file endpoint (without guid).
     * Returns e.g. "/thiscovery-forms/global/form-file?id=42"
     */
    public static function toFormFile(CustomForm $form): string
    {
        if ($form->isGlobal()) {
            return BaseUrl::to(['/thiscovery-forms/global/form-file', 'id' => $form->id]);
        }
        return $form->content->container->createUrl('/thiscovery-forms/form/form-file', ['id' => $form->id]);
    }

    protected static function panelAction($panel, string $action, $container = null): string
    {
        $id = is_object($panel) ? (int)$panel->id : (int)$panel;
        if (is_object($panel) && $container === null) {
            $container = $panel->getContentContainer();
        }
        if ($container === null) {
            return BaseUrl::to(['/thiscovery-forms/admin/' . $action, 'id' => $id]);
        }
        return $container->createUrl('/thiscovery-forms/form/' . $action, ['id' => $id]);
    }
}
