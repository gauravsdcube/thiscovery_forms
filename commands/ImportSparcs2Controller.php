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
use humhub\modules\thiscoveryForms\services\Sparcs2110926SurveyDefinition;
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

    /** @var int Complete participant answers to insert (seed-responses) */
    public $count = 40;

    public function options($actionID)
    {
        $opts = array_merge(parent::options($actionID), ['formId', 'publish']);
        if ($actionID === 'seed-responses') {
            $opts[] = 'count';
        }
        return $opts;
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
     * Create a new unpublished SPARCS2 110926 survey from IRAS 376326 v1.0.
     * Never publishes. Pass --formId to replace fields on an existing draft of this title.
     */
    public function actionCreate110926()
    {
        $this->ensureWebRequest();
        $this->ensureAdminUser();

        $meta = Sparcs2110926SurveyDefinition::meta();
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

        $form->title = Sparcs2110926SurveyDefinition::FORM_TITLE;
        $form->description = $meta['description'];
        $form->thank_you_content = $meta['thank_you_content'];
        $form->allow_anonymous = (int)$meta['allow_anonymous'];
        $form->allow_multiple = (int)$meta['allow_multiple'];
        $form->allow_resume = (int)$meta['allow_resume'];
        $form->hide_humhub_header = (int)$meta['hide_humhub_header'];
        $form->status = CustomForm::STATUS_DRAFT;
        $form->source_language = 'en-GB';
        $form->enabled_languages = ['en-GB'];

        if (!$form->save()) {
            $this->stderr("Could not save form.\n", Console::FG_RED);
            print_r($form->getErrors());
            return ExitCode::DATAERR;
        }

        $svc = new QuestionImportExportService();
        $err = $svc->appendFieldPayloads($form, Sparcs2110926SurveyDefinition::fields(), true);
        if ($err) {
            $this->stderr("Import failed: {$err}\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $form->refresh();
        $count = count($form->fields);
        $editUrl = '/thiscovery-forms/global/edit?id=' . (int)$form->id;
        $viewUrl = '/thiscovery-forms/global/view?id=' . (int)$form->id;

        $this->stdout("SPARCS2 110926 survey ready (draft, not published).\n", Console::FG_GREEN);
        $this->stdout("Form ID: {$form->id}\n");
        $this->stdout("Fields: {$count}\n");
        $this->stdout("Status: draft\n");
        $this->stdout("Studio: {$editUrl}\n");
        $this->stdout("Fill: {$viewUrl}\n");

        return ExitCode::OK;
    }

    /**
     * Insert complete, non-test SPARCS2 answers so dashboards have enough n.
     * Skips compensation PII fields. Eligible path only (16+, UK, since 2020).
     */
    public function actionSeedResponses()
    {
        $this->ensureWebRequest();
        $count = max(5, min(200, (int)$this->count));
        $form = $this->findSparcs2Form();
        if (!$form) {
            $this->stderr("SPARCS2 survey not found. Pass --formId=N or import the survey first.\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $this->stdout("Seeding {$count} complete answers into form #{$form->id} ({$form->title})\n");

        $created = 0;
        for ($i = 0; $i < $count; $i++) {
            $answer = new \humhub\modules\thiscoveryForms\models\FormAnswer();
            $answer->forceAnonymous = true;
            $answer->form_id = (int)$form->id;
            $answer->status = \humhub\modules\thiscoveryForms\models\FormAnswer::STATUS_COMPLETE;
            $answer->is_test = 0;
            $answer->workflow_status = \humhub\modules\thiscoveryForms\models\FormAnswer::WORKFLOW_NONE;
            $answer->weight = 1;
            $when = date('Y-m-d H:i:s', time() - (($count - $i) * 86400 * 2));
            $answer->created_at = $when;
            $answer->updated_at = $when;
            $answer->submitted_at = $when;
            $answer->created_by = null;
            $answer->updated_by = null;
            if ($form->current_edition_id) {
                $answer->edition_id = (int)$form->current_edition_id;
            }
            $answer->setVars(['seed' => 'sparcs2-dashboard']);
            if (!$answer->save(false)) {
                $this->stderr("Could not save answer {$i}.\n", Console::FG_RED);
                continue;
            }
            $answer->updateAttributes(['created_at' => $when, 'updated_at' => $when, 'submitted_at' => $when]);

            foreach ($form->fields as $field) {
                $value = $this->seedValue($field, $i, $count);
                if ($value === null || $value === '') {
                    continue;
                }
                $af = new \humhub\modules\thiscoveryForms\models\FormAnswerField();
                $af->answer_id = (int)$answer->id;
                $af->field_id = (int)$field->id;
                $af->value = $value;
                $af->save(false);
            }
            $created++;
        }

        $this->stdout("Inserted {$created} answers.\n", Console::FG_GREEN);

        if (Yii::$app->hasModule('thiscovery-dashboard')) {
            try {
                $dash = Yii::$app->getModule('thiscovery-dashboard');
                if ($dash instanceof \humhub\modules\thiscoveryDashboard\Module) {
                    $dash->discoverProviders();
                    (new \humhub\modules\thiscoveryDashboard\services\IngestService())
                        ->ensureSnapshot('forms', (string)$form->id, true);
                    $this->stdout("Dashboard snapshot rebuilt for this form.\n", Console::FG_GREEN);
                }
            } catch (\Throwable $e) {
                $this->stderr("Snapshot rebuild skipped: {$e->getMessage()}\n", Console::FG_YELLOW);
            }
        }

        return ExitCode::OK;
    }

    private function findSparcs2Form(): ?CustomForm
    {
        if ($this->formId > 0) {
            return CustomForm::findOne((int)$this->formId);
        }
        return CustomForm::find()
            ->where(['or',
                ['like', 'title', 'SPARCS2'],
                ['like', 'title', 'Sparcs2'],
            ])
            ->orderBy(['id' => SORT_DESC])
            ->one();
    }

    private function seedValue(\humhub\modules\thiscoveryForms\models\FormField $field, int $i, int $total): ?string
    {
        if (!$field->collectsAnswer()) {
            return null;
        }
        $label = mb_strtolower(trim((string)$field->label));
        if (preg_match('/^(name|email|phone)\b/', $label)) {
            return null;
        }

        $pairs = $field->getChoicePairs();
        $codeAt = static function (array $pairs, int $idx): string {
            if (!$pairs) {
                return '';
            }
            return (string)$pairs[$idx % count($pairs)]['code'];
        };
        $codeMatching = static function (array $pairs, string $want): string {
            foreach ($pairs as $pair) {
                if (strcasecmp((string)$pair['code'], $want) === 0 || strcasecmp((string)$pair['label'], $want) === 0) {
                    return (string)$pair['code'];
                }
            }
            return $pairs ? (string)$pairs[0]['code'] : '';
        };

        if (str_contains($label, '16 or older') || str_contains($label, 'live in the uk')) {
            return $codeMatching($pairs, 'Yes');
        }
        if (str_contains($label, 'what year was your baby')) {
            $years = array_values(array_filter($pairs, static fn($p) => stripos((string)$p['label'], 'Before') === false));
            return $years ? $codeAt($years, $i) : $codeAt($pairs, $i);
        }
        if (str_contains($label, 'confirm each statement')) {
            return json_encode(array_column($pairs, 'code'), JSON_UNESCAPED_UNICODE);
        }
        if (str_contains($label, 'how many of your children')) {
            return (string)(1 + ($i % 3));
        }
        if (str_contains($label, 'still with you today')) {
            return $codeMatching($pairs, 'Yes') ?: $codeAt($pairs, 0);
        }

        switch ($field->type) {
            case \humhub\modules\thiscoveryForms\models\FormField::TYPE_RADIO:
            case \humhub\modules\thiscoveryForms\models\FormField::TYPE_DROPDOWN:
            case \humhub\modules\thiscoveryForms\models\FormField::TYPE_PANEL_ATTR:
                return $pairs ? $codeAt($pairs, $i) : null;
            case \humhub\modules\thiscoveryForms\models\FormField::TYPE_CHECKBOX:
                if (!$pairs) {
                    return null;
                }
                $n = min(count($pairs), 1 + ($i % max(1, count($pairs))));
                $codes = [];
                for ($k = 0; $k < $n; $k++) {
                    $codes[] = $pairs[($i + $k) % count($pairs)]['code'];
                }
                return json_encode(array_values(array_unique($codes)), JSON_UNESCAPED_UNICODE);
            case \humhub\modules\thiscoveryForms\models\FormField::TYPE_NUMBER:
                return (string)(1 + ($i % 5));
            case \humhub\modules\thiscoveryForms\models\FormField::TYPE_RATING:
                $scale = $field->getRatingScale();
                $min = (int)$scale['min'];
                $max = (int)$scale['max'];
                $span = max(1, $max - $min + 1);
                return (string)($min + ($i % $span));
            case \humhub\modules\thiscoveryForms\models\FormField::TYPE_TEXT:
            case \humhub\modules\thiscoveryForms\models\FormField::TYPE_TEXTAREA:
            case \humhub\modules\thiscoveryForms\models\FormField::TYPE_HTML:
                return 'Seeded SPARCS2 test response ' . ($i + 1) . '. Neonatal follow-up support varied after discharge.';
            case \humhub\modules\thiscoveryForms\models\FormField::TYPE_DATE:
                return date('Y-m-d', strtotime('2022-03-01') + ($i * 21 * 86400));
            case \humhub\modules\thiscoveryForms\models\FormField::TYPE_GRID_SINGLE:
            case \humhub\modules\thiscoveryForms\models\FormField::TYPE_GRID_MULTI:
                $grid = $field->getGridConfig();
                $rows = $grid['rows'] ?? [];
                $cols = $grid['columns'] ?? [];
                if (!$rows || !$cols) {
                    return null;
                }
                $out = [];
                foreach ($rows as $ri => $row) {
                    $rk = (string)($row['value'] ?? $row['code'] ?? $row['label'] ?? $ri);
                    $col = $cols[($i + $ri) % count($cols)];
                    $ck = (string)($col['value'] ?? $col['code'] ?? $col['label'] ?? '');
                    if ($field->type === \humhub\modules\thiscoveryForms\models\FormField::TYPE_GRID_MULTI) {
                        $out[$rk] = [$ck => 1];
                    } else {
                        $out[$rk] = $ck;
                    }
                }
                return json_encode($out, JSON_UNESCAPED_UNICODE);
            default:
                return null;
        }
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
