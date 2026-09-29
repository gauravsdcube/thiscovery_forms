<?php

use humhub\components\Migration;
use yii\db\Query;

/**
 * A nested repeat stores parent/child in instance_key. Two codes need more than 64 characters.
 */
class m261001_120000_widen_instance_key extends Migration
{
    public function safeUp()
    {
        $started = microtime(true);
        $this->widen('{{%custom_form_answer_field}}', 'instance_key');
        $this->widen('{{%custom_form_answer}}', 'current_instance_key');
        $seconds = number_format(microtime(true) - $started, 3, '.', '');
        echo "instance_key widened seconds={$seconds}\n";
    }

    public function safeDown()
    {
        $field = (new Query())->from('{{%custom_form_answer_field}}')->where('CHAR_LENGTH([[instance_key]]) > 64')->exists();
        $answer = (new Query())->from('{{%custom_form_answer}}')->where('CHAR_LENGTH([[current_instance_key]]) > 64')->exists();
        if ($field || $answer) {
            echo "Refusing to shorten instance_key while a nested loop answer exists.\n";
            return false;
        }
        $this->narrow('{{%custom_form_answer_field}}', 'instance_key');
        $this->narrow('{{%custom_form_answer}}', 'current_instance_key');
        return true;
    }

    private function widen(string $tableName, string $column): void
    {
        $schema = $this->db->schema->getTableSchema($tableName, true);
        if (!$schema || !isset($schema->columns[$column]) || (int)$schema->columns[$column]->size >= 191) {
            return;
        }
        $table = $this->db->schema->getRawTableName($tableName);
        try {
            $this->execute("ALTER TABLE {$table} MODIFY {$column} VARCHAR(191) NOT NULL DEFAULT '', ALGORITHM=INPLACE, LOCK=NONE");
        } catch (\Throwable $e) {
            $this->alterColumn($tableName, $column, $this->string(191)->notNull()->defaultValue(''));
        }
    }

    private function narrow(string $tableName, string $column): void
    {
        $schema = $this->db->schema->getTableSchema($tableName, true);
        if (!$schema || !isset($schema->columns[$column]) || (int)$schema->columns[$column]->size <= 64) {
            return;
        }
        $this->alterColumn($tableName, $column, $this->string(64)->notNull()->defaultValue(''));
    }
}
