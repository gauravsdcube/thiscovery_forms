<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\commands;

use humhub\modules\file\models\File;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Query;

/**
 * File answers whose file is not attached to that answer.
 * Read-only. Prints a count.
 */
class DetectUnattachedFilesController extends Controller
{
    /**
     * @return array<int, array{answer_id:int,field_id:int}>
     */
    public function unattachedFileAnswers(): array
    {
        $rows = (new Query())
            ->select(['answer_id' => 'af.answer_id', 'field_id' => 'af.field_id'])
            ->from(['af' => 'custom_form_answer_field'])
            ->innerJoin(['f' => 'custom_form_field'], 'f.id = af.field_id')
            ->leftJoin(
                ['file' => File::tableName()],
                'BINARY file.guid = BINARY af.value AND file.object_model = :model AND file.object_id = af.answer_id',
                [':model' => FormAnswer::class]
            )
            ->where(['f.type' => FormField::TYPE_FILE])
            ->andWhere(['not', ['af.value' => null]])
            ->andWhere(['<>', 'af.value', ''])
            ->andWhere(['file.id' => null])
            ->all();
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'answer_id' => (int)$row['answer_id'],
                'field_id' => (int)$row['field_id'],
            ];
        }
        return $out;
    }

    public function actionIndex(): int
    {
        $rows = $this->unattachedFileAnswers();
        $this->stdout('unattached_files=' . count($rows) . "\n");
        return ExitCode::OK;
    }
}
