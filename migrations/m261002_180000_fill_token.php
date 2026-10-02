<?php

use humhub\components\Migration;

/**
 * A public fill link carries a random token instead of the form id.
 * Issuing a new token replaces this value, so the previous link stops working.
 */
class m261002_180000_fill_token extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%custom_form}}', true);
        if ($table === null) {
            return true;
        }
        if (!isset($table->columns['fill_token'])) {
            $this->addColumn('{{%custom_form}}', 'fill_token', $this->string(64)->null()->unique());
        }
        $rows = (new \yii\db\Query())->select(['id'])->from('{{%custom_form}}')->where(['fill_token' => null])->column($this->db);
        $used = array_flip((new \yii\db\Query())->select('fill_token')->from('{{%custom_form}}')->where(['not', ['fill_token' => null]])->column($this->db));
        foreach ($rows as $id) {
            do {
                $token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
            } while (isset($used[$token]));
            $used[$token] = true;
            $this->update('{{%custom_form}}', ['fill_token' => $token], ['id' => (int)$id]);
        }
        return true;
    }

    public function safeDown()
    {
        $table = $this->db->schema->getTableSchema('{{%custom_form}}', true);
        if ($table !== null && isset($table->columns['fill_token'])) {
            $this->dropColumn('{{%custom_form}}', 'fill_token');
        }
        return true;
    }
}
