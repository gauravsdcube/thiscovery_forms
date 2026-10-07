<?php

use humhub\components\Migration;
use yii\db\Query;

/**
 * Question text and its translation can be a long paragraph, not a 255-character title.
 */
class m261007_150000_widen_field_label extends Migration
{
    public function safeUp()
    {
        $this->widen('{{%custom_form_field}}', true);
        $this->widen('{{%custom_form_field_i18n}}', false);
    }

    public function safeDown()
    {
        $field = (new Query())->from('{{%custom_form_field}}')->where('CHAR_LENGTH([[label]]) > 255')->exists();
        $translated = (new Query())->from('{{%custom_form_field_i18n}}')->where('CHAR_LENGTH([[label]]) > 255')->exists();
        if ($field || $translated) {
            echo "Refusing to shorten a question label that is already longer than 255 characters.\n";
            return false;
        }
        $this->narrow('{{%custom_form_field}}', true);
        $this->narrow('{{%custom_form_field_i18n}}', false);
        return true;
    }

    private function widen(string $tableName, bool $required): void
    {
        $schema = $this->db->schema->getTableSchema($tableName, true);
        if (!$schema || !isset($schema->columns['label']) || $schema->columns['label']->type === 'text') {
            return;
        }
        $column = $required ? $this->text()->notNull() : $this->text()->null();
        $this->alterColumn($tableName, 'label', $column);
    }

    private function narrow(string $tableName, bool $required): void
    {
        $schema = $this->db->schema->getTableSchema($tableName, true);
        if (!$schema || !isset($schema->columns['label']) || $schema->columns['label']->type !== 'text') {
            return;
        }
        $column = $required ? $this->string(255)->notNull() : $this->string(255)->null();
        $this->alterColumn($tableName, 'label', $column);
    }
}
