<?php

namespace humhub\modules\thiscoveryForms\models;

use humhub\components\ActiveRecord;

/**
 * @property int $id
 * @property int $form_id
 * @property int|null $user_id
 * @property string $created_at
 * @property int $scrubbed
 * @property int $row_count
 */
class FormExportLog extends ActiveRecord
{
    public static function tableName()
    {
        return 'custom_form_export_log';
    }

    public function rules()
    {
        return [
            [['form_id', 'created_at'], 'required'],
            [['form_id', 'user_id', 'scrubbed', 'row_count'], 'integer'],
            [['created_at'], 'safe'],
        ];
    }
}
