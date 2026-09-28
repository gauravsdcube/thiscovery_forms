<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;
use yii\db\Query;

/**
 * Stop a question delete from cascading into stored answers.
 * safeUp is unchanged from the release that added deleted_at.
 * safeDown will not drop deleted_at while a removed question still has
 * answers, because that would make the question live again. Removed
 * questions with no answers are deleted first, so they cannot reappear.
 */
class m260926_130000_answer_field_restrict extends Migration
{
    public function safeUp()
    {
        $schema = $this->db->getTableSchema('custom_form_field', true);
        if ($schema !== null && !isset($schema->columns['deleted_at'])) {
            $this->addColumn('custom_form_field', 'deleted_at', $this->dateTime()->null());
        }
        $this->dropForeignKey('fk_cfaf_field', 'custom_form_answer_field');
        $this->addForeignKey(
            'fk_cfaf_field',
            'custom_form_answer_field',
            'field_id',
            'custom_form_field',
            'id',
            'RESTRICT',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        if ($this->removedQuestionsHaveAnswers()) {
            echo "Refusing to roll back m260926_130000_answer_field_restrict: a removed question still has answers. Delete those answers, or leave deleted_at in place, before rolling back.\n";
            return false;
        }
        $this->deleteRemovedQuestions();
        $this->dropForeignKey('fk_cfaf_field', 'custom_form_answer_field');
        $this->addForeignKey(
            'fk_cfaf_field',
            'custom_form_answer_field',
            'field_id',
            'custom_form_field',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $schema = $this->db->getTableSchema('custom_form_field', true);
        if ($schema !== null && isset($schema->columns['deleted_at'])) {
            $this->dropColumn('custom_form_field', 'deleted_at');
        }
        return true;
    }

    public function removedQuestionsHaveAnswers(): bool
    {
        $schema = $this->db->getTableSchema('custom_form_field', true);
        if ($schema === null || !isset($schema->columns['deleted_at'])) {
            return false;
        }
        return (new Query())
            ->from(['f' => 'custom_form_field'])
            ->innerJoin(['a' => 'custom_form_answer_field'], 'a.field_id = f.id')
            ->where(['not', ['f.deleted_at' => null]])
            ->exists();
    }

    public function deleteRemovedQuestions(): void
    {
        $schema = $this->db->getTableSchema('custom_form_field', true);
        if ($schema === null || !isset($schema->columns['deleted_at'])) {
            return;
        }
        $this->delete('custom_form_field', ['not', ['deleted_at' => null]]);
    }
}
