<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\admin\permissions\ManageModules;
use humhub\modules\thiscoveryForms\models\FormTheme;
use humhub\modules\thiscoveryForms\services\FormStyleService;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

trait ThemeAdminTrait
{
    protected function assertCanConfigureThemes(): void
    {
        if (Yii::$app->user->isGuest) {
            throw new ForbiddenHttpException();
        }
        if (!Yii::$app->user->isAdmin() && !Yii::$app->user->can(ManageModules::class)) {
            throw new ForbiddenHttpException();
        }
    }

    public function actionThemeEdit($id = null)
    {
        $this->assertCanConfigureThemes();
        $theme = $id ? FormTheme::findOne((int)$id) : new FormTheme();
        if ($id && !$theme) {
            throw new NotFoundHttpException();
        }
        if ($theme->isNewRecord) {
            $theme->name = Yii::t('ThiscoveryFormsModule.base', 'New theme');
            $default = FormTheme::findDefault();
            if ($default) {
                $theme->setStyle($default->getStyle());
                $theme->custom_css = $default->custom_css;
            } else {
                $theme->setStyle(['page' => ['maxWidth' => '1800px']]);
            }
        }

        if (Yii::$app->request->isPost) {
            $theme->name = trim((string)Yii::$app->request->post('name', $theme->name));
            $theme->is_default = Yii::$app->request->post('is_default') ? 1 : 0;
            $theme->custom_css = (string)Yii::$app->request->post('custom_css', '');
            $style = Yii::$app->request->post('style', []);
            if (!is_array($style)) {
                $style = [];
            }
            $theme->setStyle($style);
            if ($theme->save()) {
                $this->view->saved();
                return $this->redirect(['settings']);
            }
        }

        return $this->render('@thiscovery-forms/views/admin/theme_edit', [
            'theme' => $theme,
            'groups' => (new FormStyleService())->groups(),
            'style' => $theme->getStyle(),
        ]);
    }

    public function actionThemeDelete($id)
    {
        $this->assertCanConfigureThemes();
        if (!Yii::$app->request->isPost) {
            return $this->redirect(['settings']);
        }
        $theme = FormTheme::findOne((int)$id);
        if ($theme && !$theme->is_default) {
            $theme->delete();
        }
        return $this->redirect(['settings']);
    }

    public function actionThemeExport($id)
    {
        $this->assertCanConfigureThemes();
        $theme = FormTheme::findOne((int)$id);
        if (!$theme) {
            throw new NotFoundHttpException();
        }
        $payload = json_encode($theme->toExportArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-Type', 'application/json; charset=UTF-8');
        Yii::$app->response->headers->set(
            'Content-Disposition',
            'attachment; filename="theme-' . preg_replace('/[^a-z0-9_-]+/i', '-', $theme->name) . '.json"'
        );
        return $payload;
    }

    public function actionThemeImport()
    {
        $this->assertCanConfigureThemes();
        $error = null;
        if (Yii::$app->request->isPost) {
            $file = UploadedFile::getInstanceByName('theme_file');
            $raw = $file ? file_get_contents($file->tempName) : (string)Yii::$app->request->post('theme_json', '');
            $decoded = json_decode((string)$raw, true);
            if (!is_array($decoded)) {
                $error = Yii::t('ThiscoveryFormsModule.base', 'Could not read that theme file.');
            } else {
                $theme = FormTheme::fromImportArray($decoded);
                $theme->is_default = 0;
                if ($theme->save()) {
                    $this->view->saved();
                    return $this->redirect(['theme-edit', 'id' => $theme->id]);
                }
                $error = Yii::t('ThiscoveryFormsModule.base', 'Could not save the imported theme.');
            }
        }
        return $this->render('@thiscovery-forms/views/admin/theme_import', [
            'error' => $error,
        ]);
    }
}
