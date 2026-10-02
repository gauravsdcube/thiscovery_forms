<?php

use humhub\components\Migration;
use humhub\modules\thiscoveryForms\services\ResumeService;

/**
 * Resume codes are stored as a keyed hash (DAT-14). Existing plain codes are hashed in place,
 * so they keep working when typed in. Not reversible: the plain codes are not kept.
 */
class m261004_110000_hash_resume_codes extends Migration
{
    public function safeUp()
    {
        $rows = (new \yii\db\Query())->select(['id', 'resume_code'])->from('{{%custom_form_answer}}')
            ->where(['not', ['resume_code' => null]])
            ->andWhere(['<>', 'resume_code', '']);
        foreach ($rows->each(500) as $row) {
            $code = (string)$row['resume_code'];
            if (!preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code)) {
                continue;
            }
            $this->update('{{%custom_form_answer}}', ['resume_code' => ResumeService::hash($code)], ['id' => (int)$row['id']]);
        }
        return true;
    }

    public function safeDown()
    {
        echo "m261004_110000_hash_resume_codes cannot restore plain codes.\n";
        return true;
    }
}
