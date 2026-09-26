<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\commands;

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormPanelActivity;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Null identity columns on fully anonymous answers. Dry-run unless --apply=1.
 * Prints counts only.
 */
class RepairIdentityController extends Controller
{
    /** @var int 1 to write, 0 to report */
    public $apply = 0;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['apply']);
    }

    public function actionIndex(): int
    {
        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        try {
            $answerIds = FormAnswer::find()
                ->alias('a')
                ->select('a.id')
                ->innerJoin('{{%custom_form}} f', 'f.id = a.form_id')
                ->andWhere(['like', 'f.settings_json', 'fully_anonymous'])
                ->column();

            $createdBy = 0;
            $panelMember = 0;
            $resumeEmail = 0;
            $activity = 0;
            if ($answerIds) {
                $createdBy = (int)FormAnswer::find()->where(['id' => $answerIds])->andWhere(['not', ['created_by' => null]])->count();
                $panelMember = (int)FormAnswer::find()->where(['id' => $answerIds])->andWhere(['not', ['panel_member_id' => null]])->count();
                $resumeEmail = (int)FormAnswer::find()->where(['id' => $answerIds])->andWhere(['not', ['resume_email' => null]])->andWhere(['<>', 'resume_email', ''])->count();
                $activity = (int)FormPanelActivity::find()->where(['answer_id' => $answerIds])->andWhere(['not', ['answer_id' => null]])->count();
            }

            $this->stdout('created_by=' . $createdBy . "\n");
            $this->stdout('panel_member_id=' . $panelMember . "\n");
            $this->stdout('resume_email=' . $resumeEmail . "\n");
            $this->stdout('activity_answer_id=' . $activity . "\n");

            if (!(int)$this->apply) {
                $tx->rollBack();
                $this->stdout("dry-run\n");
                return ExitCode::OK;
            }

            if ($answerIds) {
                FormAnswer::updateAll(
                    ['created_by' => null, 'updated_by' => null, 'panel_member_id' => null, 'resume_email' => null],
                    ['id' => $answerIds]
                );
                FormPanelActivity::updateAll(['answer_id' => null], ['answer_id' => $answerIds]);
            }
            $tx->commit();
            $this->stdout("applied\n");
            return ExitCode::OK;
        } catch (\Throwable $e) {
            $tx->rollBack();
            $this->stderr("repair failed\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
