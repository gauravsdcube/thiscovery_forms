<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;
use yii\db\Query;

/**
 * Rolls back before the answer-field restriction. Removed questions are
 * deleted here when they have no answers. If any removed question still
 * has answers, the rollback stops so dropping deleted_at cannot make
 * that question live again.
 */
class m260928_190000_soft_delete_rollback_guard extends Migration
{
    public function safeUp()
    {
        return true;
    }

    public function safeDown()
    {
        $schema = $this->db->getTableSchema('custom_form_field', true);
        if ($schema === null || !isset($schema->columns['deleted_at'])) {
            return true;
        }
        $answered = (new Query())
            ->from(['f' => 'custom_form_field'])
            ->innerJoin(['a' => 'custom_form_answer_field'], 'a.field_id = f.id')
            ->where(['not', ['f.deleted_at' => null]])
            ->exists();
        if ($answered) {
            echo "Refusing to roll back m260928_190000_soft_delete_rollback_guard: a removed question still has answers.\n";
            return false;
        }
        $this->delete('custom_form_field', ['not', ['deleted_at' => null]]);
        return true;
    }
}
