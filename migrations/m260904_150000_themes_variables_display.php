<?php

/**
 * @copyright Copyright (c) 2026 D Cube Consulting. All rights reserved.
 * @license AGPL-3.0-or-later
 */

use humhub\components\Migration;
use yii\db\Query;

/**
 * Themes table, field variable/internal_label, migrate waves to per-form,
 * migrate choice/grid codes, seed default display settings.
 */
class m260904_150000_themes_variables_display extends Migration
{
    public function safeUp()
    {
        if (!$this->db->getTableSchema('{{%custom_form_theme}}', true)) {
            $this->createTable('{{%custom_form_theme}}', [
                'id' => $this->primaryKey(),
                'name' => $this->string(120)->notNull(),
                'style_json' => $this->text()->null(),
                'custom_css' => $this->text()->null(),
                'is_default' => $this->boolean()->notNull()->defaultValue(0),
                'created_at' => $this->dateTime()->null(),
                'created_by' => $this->integer()->null(),
                'updated_at' => $this->dateTime()->null(),
                'updated_by' => $this->integer()->null(),
            ]);
            $this->createIndex('idx-custom_form_theme-default', '{{%custom_form_theme}}', 'is_default');
        }

        $fieldSchema = $this->db->getTableSchema('{{%custom_form_field}}', true);
        if ($fieldSchema && !isset($fieldSchema->columns['variable'])) {
            $this->addColumn('{{%custom_form_field}}', 'variable', $this->string(120)->null()->after('label'));
        }
        if ($fieldSchema && !isset($fieldSchema->columns['internal_label'])) {
            $this->addColumn('{{%custom_form_field}}', 'internal_label', $this->string(255)->null()->after('variable'));
        }
        if ($fieldSchema && !isset($this->db->getTableSchema('{{%custom_form_field}}', true)->columns['variable'])) {
            // refreshed above
        }
        $this->createIndex('idx-custom_form_field-form-variable', '{{%custom_form_field}}', ['form_id', 'variable'], false);

        // Backfill variables / internal labels
        $fields = (new Query())->from('{{%custom_form_field}}')->select(['id', 'form_id', 'label', 'type', 'variable'])->all();
        $usedByForm = [];
        foreach ($fields as $row) {
            $formId = (int)$row['form_id'];
            if (!isset($usedByForm[$formId])) {
                $usedByForm[$formId] = [];
            }
            $base = $this->slugVariable((string)$row['label'], (string)$row['type'], (int)$row['id']);
            $var = $base;
            $n = 2;
            while (isset($usedByForm[$formId][$var])) {
                $var = $base . '_' . $n;
                $n++;
            }
            $usedByForm[$formId][$var] = true;
            $this->update('{{%custom_form_field}}', [
                'variable' => $var,
                'internal_label' => (string)$row['label'],
            ], ['id' => (int)$row['id']]);
        }

        // Migrate grid lines "[code] Label" → structured rows/columns; keep choice objects as-is
        $gridRows = (new Query())
            ->from('{{%custom_form_field}}')
            ->select(['id', 'options_json', 'type'])
            ->where(['type' => ['grid_single', 'grid_multi']])
            ->all();
        foreach ($gridRows as $row) {
            $decoded = json_decode((string)$row['options_json'], true);
            if (!is_array($decoded)) {
                continue;
            }
            $changed = false;
            foreach (['rows', 'columns'] as $key) {
                if (!isset($decoded[$key]) || !is_array($decoded[$key])) {
                    continue;
                }
                $next = [];
                foreach ($decoded[$key] as $item) {
                    if (is_array($item) && isset($item['label'])) {
                        $next[] = [
                            'code' => trim((string)($item['code'] ?? '')),
                            'label' => trim((string)$item['label']),
                        ];
                        continue;
                    }
                    $line = trim((string)$item);
                    if ($line === '') {
                        continue;
                    }
                    if (preg_match('/^\[([^\]]*)\]\s*(.+)$/u', $line, $m)) {
                        $next[] = ['code' => trim($m[1]), 'label' => trim($m[2])];
                        $changed = true;
                    } else {
                        $next[] = ['code' => '', 'label' => $line];
                    }
                }
                if ($next !== $decoded[$key]) {
                    $decoded[$key] = $next;
                    $changed = true;
                }
            }
            if (!isset($decoded['mobile_layout'])) {
                $decoded['mobile_layout'] = 'scroll';
                $changed = true;
            }
            if ($changed) {
                $this->update('{{%custom_form_field}}', [
                    'options_json' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
                ], ['id' => (int)$row['id']]);
            }
        }

        // Waves: copy global scope onto forms that use waves; enable use_waves for long/eq5d
        $module = Yii::$app->getModule('thiscovery-forms');
        $globalScope = 'survey';
        if ($module) {
            $raw = (string)$module->settings->get('wave_scope', 'survey');
            $globalScope = $raw === 'panel' ? 'panel' : 'survey';
            $allowSurveys = $module->settings->get('waves_for_surveys');
            $allowSurveys = $allowSurveys === '1' || $allowSurveys === 1 || $allowSurveys === true;
        } else {
            $allowSurveys = false;
        }

        $forms = (new Query())->from('{{%custom_form}}')->select(['id', 'kind', 'settings_json'])->all();
        foreach ($forms as $form) {
            $settings = json_decode((string)$form['settings_json'], true);
            if (!is_array($settings)) {
                $settings = [];
            }
            $kind = (string)$form['kind'];
            $useWaves = !empty($settings['use_waves']);
            if ($kind === 'longitudinal' || $kind === 'eq5d') {
                $useWaves = true;
                $settings['use_waves'] = 1;
            } elseif ($kind === 'survey' && $useWaves && !$allowSurveys) {
                // keep as stored
            }
            if ($useWaves || $kind === 'longitudinal' || $kind === 'eq5d') {
                if (empty($settings['wave_scope'])) {
                    $settings['wave_scope'] = $globalScope;
                }
            }
            if (!isset($settings['display'])) {
                $settings['display'] = [
                    'show_title' => '',
                    'show_description' => '',
                    'show_progress' => '',
                    'show_page_indicator' => '',
                ];
            }
            $this->update('{{%custom_form}}', [
                'settings_json' => json_encode($settings, JSON_UNESCAPED_UNICODE),
            ], ['id' => (int)$form['id']]);
        }

        if ($module) {
            if ($module->settings->get('display') === null) {
                $module->settings->set('display', json_encode([
                    'show_title' => 1,
                    'show_description' => 1,
                    'show_progress' => 1,
                    'show_page_indicator' => 1,
                ]));
            }
        }

        // Seed a default theme from empty style so forms have something to select
        $exists = (new Query())->from('{{%custom_form_theme}}')->count();
        if (!(int)$exists) {
            $now = date('Y-m-d H:i:s');
            $this->insert('{{%custom_form_theme}}', [
                'name' => 'Default',
                'style_json' => json_encode(['page' => ['maxWidth' => '1800px']], JSON_UNESCAPED_UNICODE),
                'custom_css' => null,
                'is_default' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function safeDown()
    {
        $fieldSchema = $this->db->getTableSchema('{{%custom_form_field}}', true);
        if ($fieldSchema && isset($fieldSchema->columns['internal_label'])) {
            $this->dropColumn('{{%custom_form_field}}', 'internal_label');
        }
        if ($fieldSchema && isset($fieldSchema->columns['variable'])) {
            $this->dropIndex('idx-custom_form_field-form-variable', '{{%custom_form_field}}');
            $this->dropColumn('{{%custom_form_field}}', 'variable');
        }
        if ($this->db->getTableSchema('{{%custom_form_theme}}', true)) {
            $this->dropTable('{{%custom_form_theme}}');
        }
    }

    private function slugVariable(string $label, string $type, int $id): string
    {
        $s = strtolower(trim($label));
        $s = preg_replace('/[^a-z0-9]+/i', '_', $s) ?: '';
        $s = trim((string)$s, '_');
        if ($s === '') {
            $s = $type !== '' ? $type : 'field';
        }
        if (strlen($s) > 80) {
            $s = substr($s, 0, 80);
            $s = rtrim($s, '_');
        }
        if (!preg_match('/^[a-z]/i', $s)) {
            $s = 'f_' . $s;
        }
        return $s . '_' . $id;
    }
}
