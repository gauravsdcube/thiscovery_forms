<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\admin\permissions\ManageModules;
use humhub\modules\space\models\Space;
use humhub\modules\thiscoveryForms\permissions\CreateForm;
use humhub\modules\thiscoveryForms\permissions\CreateGlobalForm;
use humhub\modules\thiscoveryForms\permissions\ManageForm;
use humhub\modules\thiscoveryForms\permissions\ManageGlobalForm;
use humhub\modules\thiscoveryForms\services\HelpService;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * In-product Help for form creators and administrators.
 */
trait HelpTrait
{
    protected function helpContainer()
    {
        return property_exists($this, 'contentContainer') ? $this->contentContainer : null;
    }

    public function canViewHelp(): bool
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }
        if (Yii::$app->user->isAdmin() || Yii::$app->user->can(ManageModules::class)) {
            return true;
        }

        $container = $this->helpContainer();
        if ($container instanceof Space) {
            $pm = $container->getPermissionManager();
            return $pm->can(CreateForm::class) || $pm->can(ManageForm::class);
        }

        return Yii::$app->user->can(CreateGlobalForm::class)
            || Yii::$app->user->can(ManageGlobalForm::class);
    }

    public function actionHelp($page = null)
    {
        if (!$this->canViewHelp()) {
            throw new ForbiddenHttpException();
        }

        $container = $this->helpContainer();
        $page = trim((string)$page);
        if ($page !== '') {
            $article = HelpService::render($page, $container);
            if (!$article) {
                throw new NotFoundHttpException();
            }
            return $this->render('@thiscovery-forms/views/help/page', [
                'article' => $article,
                'contentContainer' => $container,
                'sections' => HelpService::sections(),
                'pages' => HelpService::pages(),
                'downloads' => HelpService::downloadsFor($page),
            ]);
        }

        return $this->render('@thiscovery-forms/views/help/index', [
            'contentContainer' => $container,
            'sections' => HelpService::sections(),
            'pages' => HelpService::pages(),
        ]);
    }

    /**
     * Download a whitelisted Help attachment (login + Help permission required).
     */
    public function actionHelpDownload($file = null)
    {
        if (!$this->canViewHelp()) {
            throw new ForbiddenHttpException();
        }

        $path = HelpService::resolveDownload((string)$file);
        if ($path === null) {
            throw new NotFoundHttpException();
        }

        return Yii::$app->response->sendFile($path, basename($path), [
            'mimeType' => HelpService::downloadMime((string)$file),
            'inline' => false,
        ]);
    }
}
