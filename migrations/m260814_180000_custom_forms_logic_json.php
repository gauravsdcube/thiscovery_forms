<?php

use humhub\components\Migration;
use yii\db\Query;

class m260814_180000_custom_forms_logic_json extends Migration
{
    public function safeUp()
    {
        $this->safeAddColumn(
            'custom_form_field',
            'logic_json',
            $this->text()->null()->after('condition_value')
        );

        $rows = (new Query())
            ->from('custom_form_field')
            ->where(['not', ['condition_field_id' => null]])
            ->andWhere(['<>', 'condition_field_id', 0])
            ->all();

        foreach ($rows as $row) {
            $logic = json_encode([
                'action' => 'show',
                'combinator' => 'and',
                'gotoPageKey' => '',
                'rules' => [[
                    'fieldKey' => (string)$row['condition_field_id'],
                    'operator' => $row['condition_operator'] ?: 'equals',
                    'value' => (string)($row['condition_value'] ?? ''),
                ]],
            ], JSON_UNESCAPED_UNICODE);
            $this->update('custom_form_field', ['logic_json' => $logic], ['id' => $row['id']]);
        }
    }

    public function safeDown()
    {
        $this->safeDropColumn('custom_form_field', 'logic_json');
    }
}
