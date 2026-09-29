<?php

use humhub\components\Migration;
use yii\db\Query;

/**
 * Versioned consent documents and append-only consent records.
 * A legacy row is written for each panel member who only has consent_at.
 */
class m260929_210000_econsent extends Migration
{
    public function safeUp()
    {
        $answer = $this->db->schema->getTableSchema('{{%custom_form_answer}}', true);
        if ($answer && !isset($answer->columns['consent_version'])) {
            $this->addColumn('{{%custom_form_answer}}', 'consent_version', $this->integer()->null());
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_consent_document}}', true)) {
            $this->createTable('{{%custom_form_consent_document}}', [
                'id' => $this->primaryKey(),
                'form_id' => $this->integer()->notNull(),
                'version' => $this->integer()->notNull(),
                'title' => $this->string(255)->notNull(),
                'body_html' => $this->text()->null(),
                'approval_reference' => $this->string(64)->notNull()->defaultValue(''),
                'effective_on' => $this->date()->null(),
                'content_hash' => $this->char(64)->null(),
                'status' => $this->string(16)->notNull()->defaultValue('draft'),
                'published_at' => $this->dateTime()->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('uidx_cf_consent_doc_version', '{{%custom_form_consent_document}}', ['form_id', 'version'], true);
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_consent_item}}', true)) {
            $this->createTable('{{%custom_form_consent_item}}', [
                'id' => $this->primaryKey(),
                'document_id' => $this->integer()->notNull(),
                'code' => $this->string(64)->notNull(),
                'label' => $this->text()->notNull(),
                'required' => $this->tinyInteger()->notNull()->defaultValue(0),
                'input' => $this->string(16)->notNull()->defaultValue('yes_no'),
                'sort_order' => $this->integer()->notNull()->defaultValue(0),
            ]);
            $this->createIndex('uidx_cf_consent_item_code', '{{%custom_form_consent_item}}', ['document_id', 'code'], true);
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_consent_file}}', true)) {
            $this->createTable('{{%custom_form_consent_file}}', [
                'document_id' => $this->integer()->notNull(),
                'file_id' => $this->integer()->notNull(),
            ]);
            $this->addPrimaryKey('pk_cf_consent_file', '{{%custom_form_consent_file}}', ['document_id', 'file_id']);
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_consent_i18n}}', true)) {
            $this->createTable('{{%custom_form_consent_i18n}}', [
                'document_id' => $this->integer()->notNull(),
                'language' => $this->string(16)->notNull(),
                'body_html' => $this->text()->null(),
                'items_json' => $this->text()->null(),
            ]);
            $this->addPrimaryKey('pk_cf_consent_i18n', '{{%custom_form_consent_i18n}}', ['document_id', 'language']);
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_consent_record}}', true)) {
            $this->createTable('{{%custom_form_consent_record}}', [
                'id' => $this->primaryKey(),
                'form_id' => $this->integer()->notNull(),
                'answer_id' => $this->integer()->null(),
                'user_id' => $this->integer()->null(),
                'panel_member_id' => $this->integer()->null(),
                'document_id' => $this->integer()->notNull(),
                'content_hash' => $this->char(64)->null(),
                'items_json' => $this->text()->null(),
                'signature_method' => $this->string(16)->notNull(),
                'signature_name' => $this->string(255)->null(),
                'signature_file_id' => $this->integer()->null(),
                'witness_name' => $this->string(255)->null(),
                'witness_role' => $this->string(255)->null(),
                'signed_at' => $this->dateTime()->notNull(),
                'language' => $this->string(16)->notNull()->defaultValue(''),
                'channel' => $this->string(16)->notNull()->defaultValue('self'),
                'ip_hash' => $this->char(64)->null(),
                'ua_hash' => $this->char(64)->null(),
                'withdrawal_token_hash' => $this->char(64)->null(),
                'scrolled_to_end' => $this->tinyInteger()->notNull()->defaultValue(0),
            ]);
            $this->createIndex('idx_cf_consent_record_form', '{{%custom_form_consent_record}}', 'form_id');
            $this->createIndex('idx_cf_consent_record_answer', '{{%custom_form_consent_record}}', 'answer_id');
            $this->createIndex('idx_cf_consent_record_token', '{{%custom_form_consent_record}}', 'withdrawal_token_hash');
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_consent_requirement}}', true)) {
            $this->createTable('{{%custom_form_consent_requirement}}', [
                'id' => $this->primaryKey(),
                'form_id' => $this->integer()->notNull(),
                'panel_member_id' => $this->integer()->null(),
                'user_id' => $this->integer()->null(),
                'document_id' => $this->integer()->notNull(),
                'satisfied_record_id' => $this->integer()->null(),
            ]);
            $this->createIndex('idx_cf_consent_req_form', '{{%custom_form_consent_requirement}}', 'form_id');
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_consent_withdrawal}}', true)) {
            $this->createTable('{{%custom_form_consent_withdrawal}}', [
                'id' => $this->primaryKey(),
                'record_id' => $this->integer()->null(),
                'panel_member_id' => $this->integer()->null(),
                'user_id' => $this->integer()->null(),
                'scope' => $this->string(32)->notNull(),
                'reason' => $this->text()->null(),
                'actor_id' => $this->integer()->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_admin_task}}', true)) {
            $this->createTable('{{%custom_form_admin_task}}', [
                'id' => $this->primaryKey(),
                'kind' => $this->string(32)->notNull(),
                'form_id' => $this->integer()->notNull(),
                'member_id' => $this->integer()->null(),
                'status' => $this->string(16)->notNull()->defaultValue('open'),
                'created_at' => $this->dateTime()->notNull(),
            ]);
        }

        if (!$this->db->schema->getTableSchema('{{%custom_form_consent_audit}}', true)) {
            $this->createTable('{{%custom_form_consent_audit}}', [
                'id' => $this->primaryKey(),
                'record_id' => $this->integer()->null(),
                'event' => $this->string(32)->notNull(),
                'payload_json' => $this->text()->null(),
                'actor_id' => $this->integer()->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
        }

        $this->backfillLegacy();
    }

    private function backfillLegacy(): void
    {
        $members = (new Query())->from('{{%form_panel_member}}')->where(['not', ['consent_at' => null]])->all();
        foreach ($members as $member) {
            $forms = (new Query())->from('{{%custom_form}}')->all();
            foreach ($forms as $form) {
                $settings = json_decode((string)($form['settings_json'] ?? ''), true);
                $settings = is_array($settings) ? $settings : [];
                $panelId = (int)($settings['panel_id'] ?? 0);
                if ($panelId < 1) {
                    $panelId = (int)($form['enrol_panel_id'] ?? 0);
                }
                if ($panelId !== (int)$member['panel_id']) {
                    continue;
                }
                $docId = $this->legacyDocumentId((int)$form['id']);
                $exists = (new Query())->from('{{%custom_form_consent_record}}')->where([
                    'form_id' => (int)$form['id'],
                    'panel_member_id' => (int)$member['id'],
                    'signature_method' => 'legacy',
                ])->exists();
                if ($exists) {
                    continue;
                }
                $this->insert('{{%custom_form_consent_record}}', [
                    'form_id' => (int)$form['id'],
                    'answer_id' => null,
                    'user_id' => $member['user_id'] ?: null,
                    'panel_member_id' => (int)$member['id'],
                    'document_id' => $docId,
                    'content_hash' => null,
                    'items_json' => null,
                    'signature_method' => 'legacy',
                    'signed_at' => $member['consent_at'],
                    'language' => '',
                    'channel' => 'self',
                    'scrolled_to_end' => 0,
                ]);
            }
        }
    }

    private function legacyDocumentId(int $formId): int
    {
        $row = (new Query())->from('{{%custom_form_consent_document}}')->where([
            'form_id' => $formId,
            'version' => 0,
        ])->one();
        if ($row) {
            return (int)$row['id'];
        }
        $this->insert('{{%custom_form_consent_document}}', [
            'form_id' => $formId,
            'version' => 0,
            'title' => 'Legacy / unverified',
            'body_html' => '',
            'approval_reference' => '',
            'content_hash' => null,
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return (int)$this->db->getLastInsertID();
    }

    public function safeDown()
    {
        $table = $this->db->schema->getTableSchema('{{%custom_form_consent_record}}', true);
        if ($table) {
            $other = (new Query())->from('{{%custom_form_consent_record}}')
                ->where(['not', ['signature_method' => 'legacy']])
                ->exists();
            if ($other) {
                echo "Refusing to roll back m260929_210000_econsent: a consent record is not legacy.\n";
                return false;
            }
        }
        $this->dropTable('{{%custom_form_consent_audit}}');
        $this->dropTable('{{%custom_form_admin_task}}');
        $this->dropTable('{{%custom_form_consent_withdrawal}}');
        $this->dropTable('{{%custom_form_consent_requirement}}');
        $this->dropTable('{{%custom_form_consent_record}}');
        $this->dropTable('{{%custom_form_consent_i18n}}');
        $this->dropTable('{{%custom_form_consent_file}}');
        $this->dropTable('{{%custom_form_consent_item}}');
        $this->dropTable('{{%custom_form_consent_document}}');
        $answer = $this->db->schema->getTableSchema('{{%custom_form_answer}}', true);
        if ($answer && isset($answer->columns['consent_version'])) {
            $this->dropColumn('{{%custom_form_answer}}', 'consent_version');
        }
        return true;
    }
}
