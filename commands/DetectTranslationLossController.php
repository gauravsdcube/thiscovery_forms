<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\commands;

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormAnswerField;
use humhub\modules\thiscoveryForms\models\FormField;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Query;

/**
 * Count completed answers on translated forms whose choice cells are empty.
 * Read-only. Prints a count.
 */
class DetectTranslationLossController extends Controller
{
    public function actionIndex(): int
    {
        $choiceTypes = [FormField::TYPE_RADIO, FormField::TYPE_CHECKBOX, FormField::TYPE_DROPDOWN, FormField::TYPE_GRID_SINGLE, FormField::TYPE_GRID_MULTI];
        $fieldIds = (new Query())
            ->select('f.id')
            ->from(['f' => FormField::tableName()])
            ->innerJoin('{{%custom_form_field_i18n}} i', 'i.field_id = f.id')
            ->where(['f.type' => $choiceTypes])
            ->andWhere(['not', ['i.options_json' => null]])
            ->andWhere(['<>', 'i.options_json', ''])
            ->column();

        $count = 0;
        if ($fieldIds) {
            $count = (int)FormAnswerField::find()
                ->alias('af')
                ->innerJoin(['a' => FormAnswer::tableName()], 'a.id = af.answer_id')
                ->where(['af.field_id' => $fieldIds, 'a.status' => FormAnswer::STATUS_COMPLETE, 'a.is_test' => 0])
                ->andWhere(['or', ['af.value' => null], ['af.value' => '']])
                ->count();
        }

        $this->stdout('empty_choice_cells=' . $count . "\n");
        return ExitCode::OK;
    }
}
