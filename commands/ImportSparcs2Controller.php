<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\commands;

use humhub\modules\content\models\Content;
use humhub\modules\user\models\User;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\services\QuestionImportExportService;
use humhub\modules\thiscoveryForms\services\Sparcs2SurveyDefinition;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\web\Request as WebRequest;

class ImportSparcs2Controller extends Controller
{
    /** @var int Update an existing form instead of creating a new one */
    public $formId = 0;

    /** @var int Publish after import (default draft) */
    public $publish = 0;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['formId', 'publish']);
    }

    public function actionIndex()
    {
        $this->ensureWebRequest();
        $this->ensureAdminUser();

        $meta = Sparcs2SurveyDefinition::meta();
        $form = null;

        if ($this->formId > 0) {
            $form = CustomForm::findOne((int)$this->formId);
            if (!$form) {
                $this->stderr("Form #{$this->formId} not found.\n", Console::FG_RED);
                return ExitCode::DATAERR;
            }
            $this->stdout("Updating form #{$form->id}: {$form->title}\n", Console::FG_YELLOW);
        } else {
            $form = new CustomForm();
            $form->kind = CustomForm::KIND_SURVEY;
            $form->content->visibility = Content::VISIBILITY_PUBLIC;
        }

        $form->title = Sparcs2SurveyDefinition::FORM_TITLE;
        $form->description = $meta['description'];
        $form->thank_you_content = $meta['thank_you_content'];
        $form->allow_anonymous = (int)$meta['allow_anonymous'];
        $form->allow_multiple = (int)$meta['allow_multiple'];
        $form->allow_resume = (int)$meta['allow_resume'];
        $form->hide_humhub_header = (int)$meta['hide_humhub_header'];
        $form->status = $this->publish ? CustomForm::STATUS_OPEN : CustomForm::STATUS_DRAFT;
        $form->source_language = 'en-GB';
        $form->enabled_languages = ['en-GB'];

        if (!$form->save()) {
            $this->stderr("Could not save form.\n", Console::FG_RED);
            print_r($form->getErrors());
            return ExitCode::DATAERR;
        }

        $svc = new QuestionImportExportService();
        $err = $svc->appendFieldPayloads($form, Sparcs2SurveyDefinition::fields(), true);
        if ($err) {
            $this->stderr("Import failed: {$err}\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $form->refresh();
        $count = count($form->fields);
        $editUrl = '/thiscovery-forms/global/edit?id=' . (int)$form->id;
        $viewUrl = '/thiscovery-forms/global/view?id=' . (int)$form->id;

        $this->stdout("SPARCS2 survey ready.\n", Console::FG_GREEN);
        $this->stdout("Form ID: {$form->id}\n");
        $this->stdout("Fields: {$count}\n");
        $this->stdout("Status: " . ($form->status === CustomForm::STATUS_OPEN ? 'open' : 'draft') . "\n");
        $this->stdout("Studio: {$editUrl}\n");
        $this->stdout("Fill: {$viewUrl}\n");

        return ExitCode::OK;
    }

    /**
     * Write resources/samples/sparcs2-survey-questions.json
     */
    public function actionExportJson()
    {
        $path = dirname(__DIR__) . '/resources/samples/sparcs2-survey-questions.json';
        file_put_contents($path, Sparcs2SurveyDefinition::exportJson());
        $this->stdout("Wrote {$path}\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    private function ensureWebRequest(): void
    {
        if (Yii::$app->request instanceof WebRequest) {
            return;
        }
        Yii::$app->set('request', new WebRequest([
            'scriptFile' => Yii::getAlias('@app/../index.php'),
            'scriptUrl' => '/index.php',
            'enableCsrfValidation' => false,
        ]));
    }

    private function ensureAdminUser(): void
    {
        if (!Yii::$app->user->isGuest) {
            return;
        }
        $admin = User::find()
            ->joinWith('groups')
            ->where(['group.is_admin_group' => 1])
            ->andWhere(['user.status' => User::STATUS_ENABLED])
            ->orderBy(['user.id' => SORT_ASC])
            ->one();
        if (!$admin) {
            throw new \RuntimeException('No admin user found to own the imported form.');
        }
        Yii::$app->user->setIdentity($admin);
    }
}
