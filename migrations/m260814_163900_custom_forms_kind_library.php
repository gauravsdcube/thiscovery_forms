<?php

use humhub\components\Migration;

class m260814_163900_custom_forms_kind_library extends Migration
{
    public function safeUp()
    {
        $this->safeAddColumn(
            'custom_form',
            'kind',
            $this->string(32)->notNull()->defaultValue('survey')->after('title')
        );
        $this->safeAddColumn(
            'custom_form',
            'settings_json',
            $this->text()->null()->after('custom_css')
        );
        $this->safeAddColumn(
            'custom_form',
            'is_template',
            $this->boolean()->notNull()->defaultValue(0)->after('show_in_menu')
        );
        $this->safeAddColumn(
            'custom_form',
            'source_template_id',
            $this->integer()->null()->after('is_template')
        );

        $this->safeCreateIndex('idx-custom_form-kind', 'custom_form', 'kind');
        $this->safeCreateIndex('idx-custom_form-is_template', 'custom_form', 'is_template');

        $this->safeCreateTable('form_library_item', [
            'id' => $this->primaryKey(),
            'contentcontainer_id' => $this->integer()->null(),
            'created_by' => $this->integer()->null(),
            'type' => $this->string(32)->notNull(),
            'title' => $this->string(255)->notNull(),
            'payload_json' => $this->text()->notNull(),
            'created_at' => $this->dateTime()->null(),
            'updated_at' => $this->dateTime()->null(),
        ]);
        $this->safeCreateIndex('idx-form_library_item-container', 'form_library_item', 'contentcontainer_id');
        $this->safeCreateIndex('idx-form_library_item-type', 'form_library_item', 'type');
    }

    public function safeDown()
    {
        $this->safeDropTable('form_library_item');
        $this->safeDropIndex('idx-custom_form-is_template', 'custom_form');
        $this->safeDropIndex('idx-custom_form-kind', 'custom_form');
        $this->safeDropColumn('custom_form', 'source_template_id');
        $this->safeDropColumn('custom_form', 'is_template');
        $this->safeDropColumn('custom_form', 'settings_json');
        $this->safeDropColumn('custom_form', 'kind');
    }
}
