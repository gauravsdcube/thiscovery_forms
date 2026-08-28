<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\admin\permissions\ManageModules;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\ModuleSettings;
use humhub\modules\thiscoveryForms\permissions\CreateGlobalForm;
use humhub\modules\thiscoveryForms\permissions\ManageGlobalForm;
use humhub\modules\thiscoveryForms\services\FolderService;
use humhub\modules\thiscoveryForms\services\FormListService;
use Yii;
use yii\web\ForbiddenHttpException;

/**
 * Global forms management inside the Administration layout.
 */
class AdminController extends Controller
{
    use FolderTrait;
    use PanelAdminTrait;
    use EmailAdminTrait;
    use HelpTrait;

    /**
     * @inheritdoc
     */
    public $adminOnly = false;

    /**
     * @inheritdoc
     */
    protected function getAccessRules()
    {
        return [
            ['login'],
            ['checkCanManageForms'],
        ];
    }

    public function checkCanManageForms($rule, $access): bool
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }

        $action = Yii::$app->controller->action->id ?? '';
        if ($action === 'settings') {
            return Yii::$app->user->isAdmin() || Yii::$app->user->can(ManageModules::class);
        }

        return Yii::$app->user->isAdmin()
            || Yii::$app->user->can(ManageModules::class)
            || Yii::$app->user->can(ManageGlobalForm::class)
            || Yii::$app->user->can(CreateGlobalForm::class);
    }

    public function actionIndex()
    {
        if (!$this->checkCanManageForms([], null)) {
            throw new ForbiddenHttpException();
        }

        $canManage = Yii::$app->user->can(ManageGlobalForm::class)
            || Yii::$app->user->can(CreateGlobalForm::class)
            || Yii::$app->user->isAdmin()
            || Yii::$app->user->can(ManageModules::class);

        $query = CustomForm::findLive()->joinWith('content')
            ->andWhere(['content.contentcontainer_id' => null]);

        if (!$canManage) {
            $query->andWhere(['custom_form.status' => CustomForm::STATUS_OPEN]);
        }

        [$provider, $filters] = FormListService::provider($query, Yii::$app->request->queryParams, null);
        $browse = FolderService::browse(null, Yii::$app->request->queryParams);

        return $this->render('@thiscovery-forms/views/global/index', [
            'dataProvider' => $provider,
            'filters' => $filters,
            'canCreate' => Yii::$app->user->can(CreateGlobalForm::class) || Yii::$app->user->isAdmin(),
            'canConfigure' => Yii::$app->user->isAdmin() || Yii::$app->user->can(ManageModules::class),
            'templates' => Yii::$app->user->can(CreateGlobalForm::class) || Yii::$app->user->isAdmin()
                ? CustomForm::findAvailableTemplates(null)
                : [],
            'folderBrowse' => $browse,
            'canManagePanels' => true,
            'canViewHelp' => true,
        ]);
    }

    public function actionSettings()
    {
        if (!Yii::$app->user->isAdmin() && !Yii::$app->user->can(ManageModules::class)) {
            throw new ForbiddenHttpException();
        }

        $model = new ModuleSettings();
        if (Yii::$app->request->isPost) {
            $model->load(Yii::$app->request->post());
            if (!isset(Yii::$app->request->post('ModuleSettings')['enabledKinds'])) {
                $model->enabledKinds = [];
            }
            $integrityPost = Yii::$app->request->post('integrity');
            $ok = $model->save();
            if ($ok && is_array($integrityPost)) {
                \humhub\modules\thiscoveryForms\services\integrity\IntegritySettings::saveGlobal($integrityPost);
            }
            if ($ok) {
                $this->view->saved();
                return $this->redirect(['settings']);
            }
        }

        return $this->render('settings', [
            'model' => $model,
            'integrity' => \humhub\modules\thiscoveryForms\services\integrity\IntegritySettings::global(),
        ]);
    }
}
