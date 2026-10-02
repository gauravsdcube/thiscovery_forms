<?php

use humhub\components\Migration;
use yii\db\Query;

/**
 * One live question per variable name on a form, enforced by the database (DAT-10).
 *
 * variable_live holds the variable while the question is live and NULL once it is removed,
 * so removed questions keep their name without blocking the index. Where a test server
 * already has two live questions with one name, only the oldest gets the name in this
 * column; the studio then asks for a new name on the next save.
 */
class m261005_110000_unique_live_variable extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%custom_form_field}}', true);
        if ($table === null || isset($table->columns['variable_live'])) {
            return true;
        }
        $this->addColumn('{{%custom_form_field}}', 'variable_live', $this->string(120)->null()->after('variable'));
        $hasDeleted = isset($table->columns['deleted_at']);
        $query = (new Query())->select(['id', 'form_id', 'variable'])->from('{{%custom_form_field}}')
            ->where(['not', ['variable' => null]])->andWhere(['<>', 'variable', ''])->orderBy(['id' => SORT_ASC]);
        if ($hasDeleted) {
            $query->andWhere(['deleted_at' => null]);
        }
        $seen = [];
        foreach ($query->each(500, $this->db) as $row) {
            $key = (int)$row['form_id'] . ':' . strtolower((string)$row['variable']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $this->update('{{%custom_form_field}}', ['variable_live' => (string)$row['variable']], ['id' => (int)$row['id']]);
        }
        $this->createIndex('ux_cff_form_variable_live', '{{%custom_form_field}}', ['form_id', 'variable_live'], true);
        return true;
    }

    public function safeDown()
    {
        $table = $this->db->schema->getTableSchema('{{%custom_form_field}}', true);
        if ($table !== null && isset($table->columns['variable_live'])) {
            $this->dropIndex('ux_cff_form_variable_live', '{{%custom_form_field}}');
            $this->dropColumn('{{%custom_form_field}}', 'variable_live');
        }
        return true;
    }
}
