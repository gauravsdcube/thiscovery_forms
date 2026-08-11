<?php

/**
 * @link https://www.thiscovery.org/
 * @copyright Copyright (c) D Cube Consulting
 * @license AGPL-3.0-or-later
 */

use yii\db\Migration;

/**
 * Renames stored module identifiers / class namespaces from custom-forms to thiscovery-forms.
 * Safe to run if already applied (updates only matching rows).
 */
class m260804_160000_rename_custom_forms_to_thiscovery_forms extends Migration
{
    public function safeUp()
    {
        $this->update('module_enabled', ['module_id' => 'thiscovery-forms'], ['module_id' => 'custom-forms']);
        $this->update('contentcontainer_module', ['module_id' => 'thiscovery-forms'], ['module_id' => 'custom-forms']);
        $this->update('content', [
            'object_model' => 'humhub\\modules\\thiscoveryForms\\models\\CustomForm',
        ], [
            'object_model' => 'humhub\\modules\\customForms\\models\\CustomForm',
        ]);

        $this->update('notification', ['module' => 'thiscovery-forms'], ['module' => 'custom-forms']);
        $this->update('activity', ['module' => 'thiscovery-forms'], ['module' => 'custom-forms']);

        $this->update('group_permission', ['module_id' => 'thiscovery-forms'], ['module_id' => 'custom-forms']);
        $this->update('contentcontainer_permission', ['module_id' => 'thiscovery-forms'], ['module_id' => 'custom-forms']);
        $this->update('contentcontainer_default_permission', ['module_id' => 'thiscovery-forms'], ['module_id' => 'custom-forms']);
        $this->update('contentcontainer_setting', ['module_id' => 'thiscovery-forms'], ['module_id' => 'custom-forms']);
        $this->update('setting', ['module_id' => 'thiscovery-forms'], ['module_id' => 'custom-forms']);
        $this->update('content_tag', ['module_id' => 'thiscovery-forms'], ['module_id' => 'custom-forms']);

        // Class namespace replacements where present
        foreach (['notification' => ['class', 'source_class'], 'activity' => ['class', 'object_model'], 'file' => ['object_model'], 'group_permission' => ['class'], 'contentcontainer_permission' => ['class'], 'contentcontainer_default_permission' => ['class']] as $table => $cols) {
            foreach ($cols as $col) {
                try {
                    $this->db->createCommand("UPDATE {{%$table}} SET [[$col]] = REPLACE([[$col]], :old, :new) WHERE [[$col]] LIKE :like")
                        ->bindValues([
                            ':old' => 'customForms',
                            ':new' => 'thiscoveryForms',
                            ':like' => '%customForms%',
                        ])->execute();
                } catch (\Throwable $e) {
                    // Column/table may not exist on all installs
                }
            }
        }
    }

    public function safeDown()
    {
        $this->update('module_enabled', ['module_id' => 'custom-forms'], ['module_id' => 'thiscovery-forms']);
        $this->update('contentcontainer_module', ['module_id' => 'custom-forms'], ['module_id' => 'thiscovery-forms']);
        $this->update('content', [
            'object_model' => 'humhub\\modules\\customForms\\models\\CustomForm',
        ], [
            'object_model' => 'humhub\\modules\\thiscoveryForms\\models\\CustomForm',
        ]);
        $this->update('notification', ['module' => 'custom-forms'], ['module' => 'thiscovery-forms']);
        $this->update('activity', ['module' => 'custom-forms'], ['module' => 'thiscovery-forms']);
        $this->update('group_permission', ['module_id' => 'custom-forms'], ['module_id' => 'thiscovery-forms']);
        $this->update('contentcontainer_permission', ['module_id' => 'custom-forms'], ['module_id' => 'thiscovery-forms']);
        $this->update('contentcontainer_default_permission', ['module_id' => 'custom-forms'], ['module_id' => 'thiscovery-forms']);
        $this->update('contentcontainer_setting', ['module_id' => 'custom-forms'], ['module_id' => 'thiscovery-forms']);
        $this->update('setting', ['module_id' => 'custom-forms'], ['module_id' => 'thiscovery-forms']);
        $this->update('content_tag', ['module_id' => 'custom-forms'], ['module_id' => 'thiscovery-forms']);
    }
}
