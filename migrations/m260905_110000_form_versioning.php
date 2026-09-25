<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;
use yii\db\Query;

/**
 * Forms versioning pointers + answer edition stamp + backfill for open forms.
 */
class m260905_110000_form_versioning extends Migration
{
    public function safeUp()
    {
        $formSchema = $this->db->getTableSchema('{{%custom_form}}', true);
        if ($formSchema && !isset($formSchema->columns['current_edition_id'])) {
            $this->addColumn('{{%custom_form}}', 'current_edition_id', $this->integer()->null());
            $this->createIndex('idx-custom_form-current_edition', '{{%custom_form}}', 'current_edition_id');
        }

        $answerSchema = $this->db->getTableSchema('{{%custom_form_answer}}', true);
        if ($answerSchema && !isset($answerSchema->columns['edition_id'])) {
            $this->addColumn('{{%custom_form_answer}}', 'edition_id', $this->integer()->null());
            $this->createIndex('idx-custom_form_answer-edition', '{{%custom_form_answer}}', ['form_id', 'edition_id']);
        }
    }

    public function safeDown()
    {
        $answerSchema = $this->db->getTableSchema('{{%custom_form_answer}}', true);
        if ($answerSchema && isset($answerSchema->columns['edition_id'])) {
            $this->dropIndex('idx-custom_form_answer-edition', '{{%custom_form_answer}}');
            $this->dropColumn('{{%custom_form_answer}}', 'edition_id');
        }
        $formSchema = $this->db->getTableSchema('{{%custom_form}}', true);
        if ($formSchema && isset($formSchema->columns['current_edition_id'])) {
            $this->dropIndex('idx-custom_form-current_edition', '{{%custom_form}}');
            $this->dropColumn('{{%custom_form}}', 'current_edition_id');
        }
    }
}
