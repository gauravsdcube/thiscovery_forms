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
    public function actionIndex(): int
    {
        $count = 0;
        $pager = new FormPager();
        $query = FormAnswer::find()
            ->where(['status' => FormAnswer::STATUS_COMPLETE, 'is_test' => 0])
            ->with(['answerFields', 'form']);
        foreach ($query->each(100) as $answer) {
            $form = $answer->form;
            if (!$form) {
                continue;
            }
            $values = [];
            foreach ($answer->answerFields as $cell) {
                $values[(int)$cell->field_id] = $cell->value;
            }
            $visited = $pager->visitedFieldIds($form->fields, $values);
            $required = 0;
            foreach ($form->fields as $field) {
                if (!$field->required || !$field->collectsAnswer()) {
                    continue;
                }
                if (!in_array((int)$field->id, $visited, true)) {
                    continue;
                }
                $required++;
            }
            $stored = 0;
            foreach ($answer->answerFields as $cell) {
                if ($cell->value !== null && $cell->value !== '') {
                    $stored++;
                }
            }
            if ($stored < $required) {
                $count++;
            }
            unset($form->fields);
        }
        $this->stdout('partial_completes=' . $count . "\n");
        return ExitCode::OK;
    }
}
