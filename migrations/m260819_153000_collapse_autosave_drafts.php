<?php

use humhub\components\Migration;
use humhub\modules\thiscoveryForms\services\ResumeService;

class m260819_153000_collapse_autosave_drafts extends Migration
{
    public function safeUp()
    {
        (new ResumeService())->collapseSnapshotDrafts();
    }

    public function safeDown()
    {
        echo "m260819_153000_collapse_autosave_drafts cannot be reverted.\n";
        return false;
    }
}
