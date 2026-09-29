<?php

use humhub\components\Migration;
use yii\db\Query;

/**
 * One question can have one cell per loop instance.
 * safeDown restores the old unique key only when every instance_key is blank.
 */
class m261001_100000_answer_instance_key extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getRawTableName('{{%custom_form_answer_field}}');
        $rows = (int)(new Query())->from('{{%custom_form_answer_field}}')->count();
        $started = microtime(true);
        $answerField = $this->db->schema->getTableSchema('{{%custom_form_answer_field}}', true);
        if ($answerField && !isset($answerField->columns['instance_key'])) {
            try {
                $this->execute("ALTER TABLE {$table} ADD COLUMN instance_key VARCHAR(64) NOT NULL DEFAULT '', ALGORITHM=INPLACE, LOCK=NONE");
            } catch (\Throwable $e) {
                $this->addColumn('{{%custom_form_answer_field}}', 'instance_key', $this->string(64)->notNull()->defaultValue(''));
            }
        }
        $this->db->schema->refresh();
        $answerField = $this->db->schema->getTableSchema('{{%custom_form_answer_field}}', true);
        if ($answerField && isset($answerField->columns['instance_key'])) {
            $legacy = $this->db->createCommand(
                'SHOW INDEX FROM ' . $table . ' WHERE Key_name = ' . $this->db->quoteValue('idx_cfaf_answer_field')
            )->queryAll();
            if ($legacy !== []) {
                $this->execute('ALTER TABLE ' . $table . ' DROP INDEX idx_cfaf_answer_field');
            }
            $this->db->schema->refresh();
            $existing = $this->db->createCommand('SHOW INDEX FROM ' . $table . ' WHERE Key_name = :name', [
                ':name' => 'idx_cfaf_answer_field_instance',
            ])->queryAll();
            if ($existing === []) {
                $this->createIndex('idx_cfaf_answer_field_instance', '{{%custom_form_answer_field}}', ['answer_id', 'field_id', 'instance_key'], true);
            }
        }
        $answer = $this->db->schema->getTableSchema('{{%custom_form_answer}}', true);
        if ($answer && !isset($answer->columns['current_instance_key'])) {
            $this->addColumn('{{%custom_form_answer}}', 'current_instance_key', $this->string(64)->notNull()->defaultValue(''));
        }
        $seconds = round(microtime(true) - $started, 3);
        echo "instance_key migration rows={$rows} seconds={$seconds}\n";
    }

    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema('{{%custom_form_answer_field}}', true);
        if ($schema && isset($schema->columns['instance_key'])) {
            $busy = (new Query())->from('{{%custom_form_answer_field}}')->where(['<>', 'instance_key', ''])->exists();
            if ($busy) {
                echo "Refusing to drop instance_key while a loop answer exists.\n";
                return false;
            }
            try {
                $this->dropIndex('idx_cfaf_answer_field_instance', '{{%custom_form_answer_field}}');
            } catch (\Throwable $e) {
            }
            $this->dropColumn('{{%custom_form_answer_field}}', 'instance_key');
            $this->createIndex('idx_cfaf_answer_field', '{{%custom_form_answer_field}}', ['answer_id', 'field_id'], true);
        }
        $answer = $this->db->schema->getTableSchema('{{%custom_form_answer}}', true);
        if ($answer && isset($answer->columns['current_instance_key'])) {
            $this->dropColumn('{{%custom_form_answer}}', 'current_instance_key');
        }
        return true;
    }
}
