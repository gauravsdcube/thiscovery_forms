<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\commands;

use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Query;

/**
 * Forms whose questions, including removed ones, share a variable name.
 * Read-only. Prints a count.
 */
class DetectDuplicateVariablesController extends Controller
{
    /**
     * @return array<int, array{form_id:int,variable:string,fields:int}>
     */
    public function duplicateForms(): array
    {
        $rows = (new Query())
            ->select(['form_id', 'variable' => 'LOWER(variable)', 'fields' => 'COUNT(*)'])
            ->from('custom_form_field')
            ->where(['not', ['variable' => null]])
            ->andWhere(['<>', 'variable', ''])
            ->groupBy(['form_id', 'LOWER(variable)'])
            ->having('COUNT(*) > 1')
            ->all();
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'form_id' => (int)$row['form_id'],
                'variable' => (string)$row['variable'],
                'fields' => (int)$row['fields'],
            ];
        }
        return $out;
    }

    public function actionIndex(): int
    {
        $rows = $this->duplicateForms();
        $forms = [];
        foreach ($rows as $row) {
            $forms[$row['form_id']] = true;
            $this->stdout('form=' . $row['form_id'] . ' variable=' . $row['variable'] . ' fields=' . $row['fields'] . "\n");
        }
        $this->stdout('duplicate_variables=' . count($rows) . ' forms=' . count($forms) . "\n");
        return ExitCode::OK;
    }
}
