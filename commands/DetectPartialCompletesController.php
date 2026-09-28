<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\commands;

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\services\FormPager;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Count complete answers that stored fewer rows than the required questions on their route.
 * Read-only. Prints a count.
 */
class DetectPartialCompletesController extends Controller
{
    /**
     * Required on-route questions with no stored answer.
     * Soft-deleted questions are not part of the live route.
     */
    public function missingRequired(FormAnswer $answer): int
    {
        $form = $answer->form;
        if (!$form) {
            return 0;
        }
        $values = [];
        foreach ($answer->answerFields as $cell) {
            $values[(int)$cell->field_id] = $cell->value;
        }
        $visited = (new FormPager())->visitedFieldIds($form->fields, $values);
        $missing = 0;
        foreach ($form->fields as $field) {
            if (!$field->required || !$field->collectsAnswer()) {
                continue;
            }
            $id = (int)$field->id;
            if (!isset($visited[$id])) {
                continue;
            }
            $value = $values[$id] ?? null;
            if ($value === null || $value === '' || $value === []) {
                $missing++;
            }
        }
        return $missing;
    }

    public function actionIndex(): int
    {
        $total = 0;
        $byForm = [];
        $query = FormAnswer::find()
            ->where(['status' => FormAnswer::STATUS_COMPLETE, 'is_test' => 0])
            ->with(['answerFields', 'form']);
        foreach ($query->each(100) as $answer) {
            $form = $answer->form;
            if (!$form) {
                continue;
            }
            $missing = $this->missingRequired($answer);
            if ($missing > 0) {
                $total++;
                $byForm[(int)$form->id] = ($byForm[(int)$form->id] ?? 0) + 1;
            }
            unset($form->fields);
        }
        ksort($byForm);
        foreach ($byForm as $formId => $count) {
            $this->stdout('form=' . $formId . ' partial_completes=' . $count . "\n");
        }
        $this->stdout('partial_completes=' . $total . "\n");
        return ExitCode::OK;
    }
}
