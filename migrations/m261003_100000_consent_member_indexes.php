<?php

use humhub\components\Migration;

/**
 * Reminders and blocksContact() look consent records and withdrawals up by panel member and
 * user; without these indexes each lookup scanned the table (V3-53).
 */
class m261003_100000_consent_member_indexes extends Migration
{
    private const INDEXES = [
        ['idx_cf_consent_record_member', '{{%custom_form_consent_record}}', ['panel_member_id']],
        ['idx_cf_consent_record_user', '{{%custom_form_consent_record}}', ['user_id']],
        ['idx_cf_consent_withdrawal_member', '{{%custom_form_consent_withdrawal}}', ['panel_member_id']],
        ['idx_cf_consent_withdrawal_user', '{{%custom_form_consent_withdrawal}}', ['user_id']],
    ];

    public function safeUp()
    {
        foreach (self::INDEXES as [$name, $table, $columns]) {
            if ($this->db->schema->getTableSchema($table, true) !== null && !$this->hasIndex($table, $name)) {
                $this->createIndex($name, $table, $columns);
            }
        }
        return true;
    }

    public function safeDown()
    {
        foreach (self::INDEXES as [$name, $table]) {
            if ($this->db->schema->getTableSchema($table, true) !== null && $this->hasIndex($table, $name)) {
                $this->dropIndex($name, $table);
            }
        }
        return true;
    }

    private function hasIndex(string $table, string $name): bool
    {
        $raw = $this->db->schema->getRawTableName($table);
        return $this->db->createCommand('SHOW INDEX FROM ' . $this->db->quoteTableName($raw) . ' WHERE Key_name = :name', [
            ':name' => $name,
        ])->queryAll() !== [];
    }
}
