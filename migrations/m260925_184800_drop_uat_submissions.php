<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;

/**
 * Remove the UAT submissions table shipped in 1.28.1.
 */
class m260925_184800_drop_uat_submissions extends Migration
{
    public function safeUp()
    {
        $table = '{{%custom_form_uat_submission}}';
        if ($this->db->getTableSchema($table, true) !== null) {
            $this->dropTable($table);
        }
    }

    public function safeDown()
    {
        return false;
    }
}
