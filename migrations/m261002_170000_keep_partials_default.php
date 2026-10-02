<?php

use humhub\components\Migration;

/**
 * Keep incomplete responses is on unless a form has it turned off.
 * Existing rows stored the previous off default, so those are switched on.
 */
class m261002_170000_keep_partials_default extends Migration
{
    public function safeUp()
    {
        $this->enableOnForms();
        $this->enableOnFormSnapshots();
    }

    public function safeDown()
    {
        return false;
    }

    private function enableOnForms(): void
    {
        $schema = $this->db->getTableSchema('{{%custom_form}}', true);
        if ($schema === null || !isset($schema->columns['settings_json'])) {
            return;
        }
        $rows = (new \yii\db\Query())
            ->select(['id', 'settings_json'])
            ->from('{{%custom_form}}')
            ->all($this->db);
        foreach ($rows as $row) {
            $updated = $this->withKeepPartialsOn($row['settings_json'] ?? null);
            if ($updated === null) {
                continue;
            }
            $this->update('{{%custom_form}}', ['settings_json' => $updated], ['id' => (int)$row['id']]);
        }
    }

    private function enableOnFormSnapshots(): void
    {
        $schema = $this->db->getTableSchema('{{%tc_version_revision}}', true);
        if ($schema === null || !isset($schema->columns['snapshot_json'])) {
            return;
        }
        $rows = (new \yii\db\Query())
            ->select(['id', 'snapshot_json'])
            ->from('{{%tc_version_revision}}')
            ->where(['owner_type' => 'form'])
            ->all($this->db);
        foreach ($rows as $row) {
            $snapshot = json_decode((string)($row['snapshot_json'] ?? ''), true);
            if (!is_array($snapshot)) {
                continue;
            }
            $meta = is_array($snapshot['meta'] ?? null) ? $snapshot['meta'] : [];
            if (!array_key_exists('settings_json', $meta)) {
                continue;
            }
            $updated = $this->withKeepPartialsOn($meta['settings_json']);
            if ($updated === null) {
                continue;
            }
            $meta['settings_json'] = $updated;
            $snapshot['meta'] = $meta;
            $this->update('{{%tc_version_revision}}', [
                'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            ], ['id' => (int)$row['id']]);
        }
    }

    /**
     * @return string|null encoded settings when a change is required
     */
    private function withKeepPartialsOn($json): ?string
    {
        $settings = json_decode((string)$json, true);
        if (!is_array($settings)) {
            $settings = [];
        }
        if (!empty($settings['keep_partials'])) {
            return null;
        }
        $settings['keep_partials'] = true;
        return json_encode($settings, JSON_UNESCAPED_UNICODE);
    }
}
