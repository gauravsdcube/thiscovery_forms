<?php

use humhub\components\Migration;
use yii\db\Query;

/**
 * Shown and hidden roster rows live on the answer. Removing a row keeps its cells.
 */
class m261001_130000_roster_json extends Migration
{
    public function safeUp()
    {
        $started = microtime(true);
        $schema = $this->db->schema->getTableSchema('{{%custom_form_answer}}', true);
        if ($schema && !isset($schema->columns['roster_json'])) {
            $table = $this->db->schema->getRawTableName('{{%custom_form_answer}}');
            try {
                $this->execute("ALTER TABLE {$table} ADD COLUMN roster_json TEXT NULL, ALGORITHM=INPLACE, LOCK=NONE");
            } catch (\Throwable $e) {
                $this->addColumn('{{%custom_form_answer}}', 'roster_json', $this->text()->null());
            }
        }
        $seconds = number_format(microtime(true) - $started, 3, '.', '');
        echo "roster_json migration seconds={$seconds}\n";
    }

    public function safeDown()
    {
        $schema = $this->db->schema->getTableSchema('{{%custom_form_answer}}', true);
        if (!$schema || !isset($schema->columns['roster_json'])) {
            return true;
        }
        $busy = (new Query())->from('{{%custom_form_answer}}')
            ->where(['not', ['roster_json' => null]])
            ->andWhere(['not', ['roster_json' => '']])
            ->andWhere(['not', ['roster_json' => '{}']])
            ->exists();
        if ($busy) {
            echo "Refusing to drop roster rows while a roster answer exists.\n";
            return false;
        }
        $this->dropColumn('{{%custom_form_answer}}', 'roster_json');
        return true;
    }
}
