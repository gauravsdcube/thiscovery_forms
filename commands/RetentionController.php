<?php

namespace humhub\modules\thiscoveryForms\commands;

use humhub\modules\thiscoveryForms\services\RetentionService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * php yii thiscovery-forms/retention/run            reports what would be removed
 * php yii thiscovery-forms/retention/run --apply=1  removes it
 */
class RetentionController extends Controller
{
    /** @var int 1 to remove, 0 to report */
    public $apply = 0;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['apply']);
    }

    public function actionRun(): int
    {
        foreach (RetentionService::DEFAULTS as $setting => $default) {
            $this->stdout($setting . '=' . RetentionService::days($setting) . "\n");
        }
        foreach ((new RetentionService())->run(!(int)$this->apply) as $name => $count) {
            $this->stdout($name . '=' . $count . "\n");
        }
        $this->stdout((int)$this->apply ? "applied\n" : "dry-run\n");
        return ExitCode::OK;
    }
}
