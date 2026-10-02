<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

namespace humhub\modules\thiscoveryForms\commands;

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormIntegrityMeta;
use humhub\modules\thiscoveryForms\models\FormPanelActivity;
use humhub\modules\thiscoveryForms\services\IdentityRepair;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Null identity columns on complete answers for fully anonymous forms.
 * Dry-run unless --apply=1. Pass --since=YYYY-MM-DD when revision history
 * does not show when the form became anonymous.
 */
class RepairIdentityController extends Controller
{
    /** @var int 1 to write, 0 to report */
    public $apply = 0;

    /** @var string Cutoff for forms whose mode-change date is unknown */
    public $since = '';

    /** @var string Audit run to restore */
    public $run = '';

    public function options($actionID)
    {
        $options = ['apply'];
        if ($actionID === 'index') {
            $options[] = 'since';
        }
        if ($actionID === 'reverse' || $actionID === 'finalise') {
            $options[] = 'run';
        }
        return array_merge(parent::options($actionID), $options);
    }

    public function actionIndex(): int
    {
        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        try {
            $repair = new IdentityRepair();
            $selected = [];
            $unknown = [];
            foreach ($repair->candidateForms() as $form) {
                $since = $repair->sinceFor($form);
                if ($since === null) {
                    if (trim((string)$this->since) === '') {
                        $unknown[] = (int)$form->id;
                        continue;
                    }
                    $since = trim((string)$this->since);
                }
                foreach ($repair->selectAnswers($form, $since) as $answer) {
                    $selected[] = $answer;
                }
            }

            $answerIds = array_map(static fn(FormAnswer $answer): int => (int)$answer->id, $selected);
            $createdBy = 0;
            $panelMember = 0;
            $resumeEmail = 0;
            $activity = 0;
            $tokens = 0;
            if ($answerIds) {
                $createdBy = (int)FormAnswer::find()->where(['id' => $answerIds])->andWhere(['not', ['created_by' => null]])->count();
                $panelMember = (int)FormAnswer::find()->where(['id' => $answerIds])->andWhere(['not', ['panel_member_id' => null]])->count();
                $resumeEmail = (int)FormAnswer::find()->where(['id' => $answerIds])->andWhere(['not', ['resume_email' => null]])->andWhere(['<>', 'resume_email', ''])->count();
                $activity = (int)FormPanelActivity::find()->where(['answer_id' => $answerIds])->count();
                $tokens = (int)FormIntegrityMeta::find()->where(['answer_id' => $answerIds])->andWhere(['not', ['access_token_hash' => null]])->andWhere(['<>', 'access_token_hash', ''])->count();
            }

            $this->stdout('created_by=' . $createdBy . "\n");
            $this->stdout('panel_member_id=' . $panelMember . "\n");
            $this->stdout('resume_email=' . $resumeEmail . "\n");
            $this->stdout('activity_answer_id=' . $activity . "\n");
            $this->stdout('access_token_hash=' . $tokens . "\n");
            $this->stdout('since_unknown=' . ($unknown === [] ? '0' : implode(',', $unknown)) . "\n");

            if (!(int)$this->apply) {
                $tx->rollBack();
                $this->stdout("dry-run\n");
                return ExitCode::OK;
            }

            $userId = Yii::$app->user->isGuest ? null : (int)Yii::$app->user->id;
            $runId = $repair->apply($selected, $userId ?: null);
            $tx->commit();
            $this->stdout('applied run=' . $runId . "\n");
            return ExitCode::OK;
        } catch (\Throwable $e) {
            $tx->rollBack();
            $this->stderr('repair failed: ' . $e->getMessage() . "\n");
            Yii::error($e, 'thiscovery-forms');
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    public function actionReverse(): int
    {
        $runId = trim((string)$this->run);
        if ($runId === '') {
            $this->stderr("pass --run=<id>\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        try {
            $repair = new IdentityRepair();
            if (!(int)$this->apply) {
                $count = (int)(new \yii\db\Query())->from('custom_form_identity_repair_log')->where(['run_id' => $runId])->count();
                $tx->rollBack();
                $this->stdout('rows=' . $count . "\n");
                $this->stdout("dry-run\n");
                return ExitCode::OK;
            }
            $restored = $repair->reverse($runId);
            $tx->commit();
            $this->stdout('restored=' . $restored . "\n");
            return ExitCode::OK;
        } catch (\Throwable $e) {
            $tx->rollBack();
            $this->stderr('reverse failed: ' . $e->getMessage() . "\n");
            Yii::error($e, 'thiscovery-forms');
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Confirm a run: its logged identities are deleted and it can no longer be reversed.
     * Runs are finalised automatically after IdentityRepair::REVERSAL_DAYS days.
     */
    public function actionFinalise(): int
    {
        $runId = trim((string)$this->run);
        if ($runId === '') {
            $this->stderr("pass --run=<id>\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        if (!(int)$this->apply) {
            $count = (int)(new \yii\db\Query())->from('custom_form_identity_repair_log')->where(['run_id' => $runId])->count();
            $this->stdout('rows=' . $count . "\n");
            $this->stdout("dry-run\n");
            return ExitCode::OK;
        }
        $this->stdout('deleted=' . (new IdentityRepair())->finalise($runId) . "\n");
        return ExitCode::OK;
    }
}
