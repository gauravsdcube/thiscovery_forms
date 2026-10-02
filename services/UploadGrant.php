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
        if (!isset($list[$guid]) && count($list) >= 50) {
            array_shift($list);
        }
        $list[$guid] = true;
        Yii::$app->session->set(self::key($formId), $list);
    }

    public static function forget(int $formId, string $guid): void
    {
        $list = Yii::$app->session->get(self::key($formId), []);
        if (!is_array($list) || !isset($list[$guid])) {
            return;
        }
        unset($list[$guid]);
        Yii::$app->session->set(self::key($formId), $list);
    }

    public static function forgetAll(int $formId): void
    {
        Yii::$app->session->remove(self::key($formId));
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

    /**
     * Grants only files already attached to this response (V3-50). It no longer grants the
     * user's last 50 unattached HumHub files from any module, nor an unattached GUID that a
     * draft happens to name: a fresh upload is granted when it is uploaded, in this session.
     */
    public static function grantLegacy(int $formId, ?FormAnswer $answer): void
    {
        if (!$answer || !$answer->id) {
            return;
        }
        foreach ($answer->answerFields as $field) {
            $value = trim((string)$field->value);
            if (!preg_match('/^[0-9a-f-]{36}$/i', $value)) {
                continue;
            }
            $file = File::findOne(['guid' => $value]);
            if ($file && self::attachedTo($file, $answer)) {
                self::remember($formId, $value);
            }
        }
    }

    public static function mayRemove(CustomForm $form, File $file, ?FormAnswer $answer, bool $editingAllowed = false): bool
    {
        if (!self::granted((int)$form->id, (string)$file->guid)) {
            self::grantLegacy((int)$form->id, $answer);
        }
        $completed = $answer !== null && $answer->status === FormAnswer::STATUS_COMPLETE;
        if ($completed && self::attachedTo($file, $answer) && !$editingAllowed) {
            return false;
        }
        if (self::granted((int)$form->id, (string)$file->guid)) {
            return true;
        }
        return $answer !== null && self::attachedTo($file, $answer) && (!$completed || $editingAllowed);
    }

    private static function key(int $formId): string
    {
        return 'cf_upload_guids_' . $formId;
    }
}
