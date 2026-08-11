<?php

namespace humhub\modules\thiscoveryForms\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\admin\permissions\ManageModules;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\permissions\CreateGlobalForm;
use humhub\modules\thiscoveryForms\permissions\ManageGlobalForm;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\ForbiddenHttpException;

/**
 * Global forms management inside the Administration layout.
 */
class AdminController extends Controller
{
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

        $query = CustomForm::find()->joinWith('content')
            ->andWhere(['content.contentcontainer_id' => null]);

        if (!$canManage) {
            $query->andWhere(['custom_form.status' => CustomForm::STATUS_OPEN]);
        }

        $provider = new ActiveDataProvider([
            'query' => $query->with('fields'),
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('@thiscovery-forms/views/global/index', [
            'dataProvider' => $provider,
            'canCreate' => Yii::$app->user->can(CreateGlobalForm::class) || Yii::$app->user->isAdmin(),
        ]);
    }
}
