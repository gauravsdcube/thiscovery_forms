<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\file\models\File;
use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use Yii;

/**
 * A fill session may only keep or delete a file it uploaded, or a file already
 * attached to the answer being edited.
 */
class UploadGrant
{
    public static function remember(int $formId, string $guid): void
    {
        $guid = trim($guid);
        if ($guid === '') {
            return;
        }
        $list = Yii::$app->session->get(self::key($formId), []);
        if (!is_array($list)) {
            $list = [];
        }
        $list[$guid] = true;
        Yii::$app->session->set(self::key($formId), $list);
    }

    public static function granted(int $formId, string $guid): bool
    {
        $list = Yii::$app->session->get(self::key($formId), []);
        return is_array($list) && !empty($list[$guid]);
    }

    public static function attachedTo(File $file, FormAnswer $answer): bool
    {
        return $file->object_model === FormAnswer::class && (int)$file->object_id === (int)$answer->id;
    }

    public static function mayRemove(CustomForm $form, File $file, ?FormAnswer $answer): bool
    {
        if (self::granted((int)$form->id, (string)$file->guid)) {
            return true;
        }
        return $answer !== null && self::attachedTo($file, $answer);
    }

    private static function key(int $formId): string
    {
        return 'cf_upload_guids_' . $formId;
    }
}
