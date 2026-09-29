<?php

namespace humhub\modules\thiscoveryForms\commands;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\services\QuotaService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Expire quota reservations and compare counters with completed responses.
 * Dry-run unless --apply=1.
 */
class QuotaController extends Controller
{
    /** @var int 1 to write the recounted total */
    public $apply = 0;

    /** @var int Limit reconcile to one form */
    public $form = 0;

    public function options($actionID)
    {
        $options = [];
        if ($actionID === 'reconcile') {
            $options = ['apply', 'form'];
        }
        return array_merge(parent::options($actionID), $options);
    }

    public function actionExpire(): int
    {
        $removed = (new QuotaService())->expire();
        $this->stdout("Expired reservations: {$removed}\n");
        return ExitCode::OK;
    }

    public function actionReconcile(): int
    {
        $svc = new QuotaService();
        $query = CustomForm::find();
        if ((int)$this->form > 0) {
            $query->andWhere(['custom_form.id' => (int)$this->form]);
        }
        $drifted = 0;
        foreach ($query->each(50) as $form) {
            foreach ($svc->reconcile($form, (int)$this->apply === 1) as $row) {
                $drifted++;
                $this->stdout(sprintf(
                    "quota %d stored %d recount %d\n",
                    (int)$row['quota_id'],
                    (int)$row['stored'],
                    (int)$row['recount']
                ));
            }
        }
        if ((int)$this->apply !== 1) {
            $this->stdout("Dry run. Pass --apply=1 to set the counter to the recount.\n");
        }
        return $drifted > 0 && (int)$this->apply !== 1 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }
}
