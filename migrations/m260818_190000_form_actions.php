<?php

use humhub\components\Migration;
use yii\db\Query;

class m260818_190000_form_actions extends Migration
{
    public function safeUp()
    {
        $this->safeAddColumn('custom_form_field', 'actions_json', $this->text()->null()->after('logic_json'));
        $this->safeAddColumn('custom_form_answer', 'vars_json', $this->text()->null()->after('current_page'));

        $rows = (new Query())
            ->from('custom_form_field')
            ->where(['type' => 'action_button'])
            ->all();
        foreach ($rows as $row) {
            $decoded = json_decode((string)($row['options_json'] ?? ''), true);
            $templateId = (int)($decoded['template_id'] ?? 0);
            if ($templateId > 0) {
                $form = (new Query())->from('custom_form')->where(['id' => (int)$row['form_id']])->one();
                $settings = [];
                if ($form && !empty($form['settings_json'])) {
                    $parsed = json_decode((string)$form['settings_json'], true);
                    $settings = is_array($parsed) ? $parsed : [];
                }
                $actions = is_array($settings['submit_actions'] ?? null) ? $settings['submit_actions'] : [];
                $actions[] = [
                    'fn' => 'send_email',
                    'template_id' => $templateId,
                    'page_key' => '',
                    'name' => '',
                    'value' => '',
                ];
                $settings['submit_actions'] = $actions;
                $this->update('custom_form', [
                    'settings_json' => json_encode($settings, JSON_UNESCAPED_UNICODE),
                ], ['id' => (int)$row['form_id']]);
            }
            $this->delete('custom_form_field', ['id' => (int)$row['id']]);
        }
    }

    public function safeDown()
    {
        $this->safeDropColumn('custom_form_answer', 'vars_json');
        $this->safeDropColumn('custom_form_field', 'actions_json');
    }
}
