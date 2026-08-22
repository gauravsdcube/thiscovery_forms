<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use yii\db\Migration;

class m260818_140000_custom_form_folders extends Migration
{
    public function safeUp()
    {
        $folderTable = $this->db->schema->getTableSchema('custom_form_folder', true);
        if ($folderTable === null) {
            $this->createTable('custom_form_folder', [
                'id' => $this->primaryKey(),
                'parent_id' => $this->integer()->null(),
                'contentcontainer_id' => $this->integer()->null(),
                'name' => $this->string(255)->notNull(),
                'description' => $this->text()->null(),
                'inherit_acl' => $this->boolean()->notNull()->defaultValue(true),
                'sort_order' => $this->integer()->notNull()->defaultValue(0),
                'created_at' => $this->dateTime()->null(),
                'created_by' => $this->integer()->null(),
                'updated_at' => $this->dateTime()->null(),
                'updated_by' => $this->integer()->null(),
            ]);
            $this->createIndex('idx_cf_folder_parent', 'custom_form_folder', ['contentcontainer_id', 'parent_id']);
            $this->addForeignKey(
                'fk_cf_folder_parent',
                'custom_form_folder',
                'parent_id',
                'custom_form_folder',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }

        $aclTable = $this->db->schema->getTableSchema('custom_form_folder_acl', true);
        if ($aclTable === null) {
            $this->createTable('custom_form_folder_acl', [
                'id' => $this->primaryKey(),
                'folder_id' => $this->integer()->notNull(),
                'group_id' => $this->integer()->null(),
                'user_id' => $this->integer()->null(),
                'can_view' => $this->boolean()->notNull()->defaultValue(true),
                'can_create' => $this->boolean()->notNull()->defaultValue(false),
                'can_manage' => $this->boolean()->notNull()->defaultValue(false),
            ]);
            $this->addForeignKey(
                'fk_cf_folder_acl_folder',
                'custom_form_folder_acl',
                'folder_id',
                'custom_form_folder',
                'id',
                'CASCADE',
                'CASCADE'
            );
            $this->createIndex('idx_cf_folder_acl_group', 'custom_form_folder_acl', ['folder_id', 'group_id']);
            $this->createIndex('idx_cf_folder_acl_user', 'custom_form_folder_acl', ['folder_id', 'user_id']);
        }

        $formTable = $this->db->schema->getTableSchema('custom_form', true);
        if ($formTable !== null && !isset($formTable->columns['folder_id'])) {
            $this->addColumn('custom_form', 'folder_id', $this->integer()->null());
            $this->createIndex('idx_cf_form_folder', 'custom_form', 'folder_id');
            $this->addForeignKey(
                'fk_cf_form_folder',
                'custom_form',
                'folder_id',
                'custom_form_folder',
                'id',
                'SET NULL',
                'CASCADE'
            );
        }
    }

    public function safeDown()
    {
        $formTable = $this->db->schema->getTableSchema('custom_form', true);
        if ($formTable !== null && isset($formTable->columns['folder_id'])) {
            $this->dropForeignKey('fk_cf_form_folder', 'custom_form');
            $this->dropIndex('idx_cf_form_folder', 'custom_form');
            $this->dropColumn('custom_form', 'folder_id');
        }
        if ($this->db->schema->getTableSchema('custom_form_folder_acl', true) !== null) {
            $this->dropTable('custom_form_folder_acl');
        }
        if ($this->db->schema->getTableSchema('custom_form_folder', true) !== null) {
            $this->dropTable('custom_form_folder');
        }
    }
}
