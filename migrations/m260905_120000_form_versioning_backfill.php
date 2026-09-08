<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;
use yii\db\Query;

/**
 * Backfill synthetic edition #1 for forms that are Open (or have answers).
 * Requires thiscovery-versioning tables.
 */
class m260905_120000_form_versioning_backfill extends Migration
{
    public function safeUp()
    {
        if (!$this->db->getTableSchema('{{%tc_version_revision}}', true)
            || !$this->db->getTableSchema('{{%tc_version_edition}}', true)) {
            echo "Skipping versioning backfill — thiscovery-versioning tables not present.\n";
            return true;
        }

        // Prefer service when autoloadable during migrate.
        if (class_exists(\humhub\modules\thiscoveryForms\services\FormVersionBackfill::class)) {
            (new \humhub\modules\thiscoveryForms\services\FormVersionBackfill())->run();
            return true;
        }

        return true;
    }

    public function safeDown()
    {
        echo "Backfill cannot be reversed automatically.\n";
        return true;
    }
}
