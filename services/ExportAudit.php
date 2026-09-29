<?php

namespace humhub\modules\thiscoveryForms\services;

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormExportLog;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;

/**
 * Answers CSV is a POST from someone allowed to export, and each download is logged.
 */
class ExportAudit
{
    public static function authorize(CustomForm $form): void
    {
        if (!Yii::$app->request->isPost) {
            throw new MethodNotAllowedHttpException(Yii::t('ThiscoveryFormsModule.base', 'Export must be submitted as a form.'));
        }
        if (!$form->canExportAnswers()) {
            throw new ForbiddenHttpException();
        }
    }

    public static function record(CustomForm $form, string $csv): void
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        $rows = max(0, count($lines) - 1);
        $log = new FormExportLog();
        $log->form_id = (int)$form->id;
        $log->user_id = Yii::$app->user->id ?: null;
        $log->created_at = date('Y-m-d H:i:s');
        $log->scrubbed = ExportSettings::isPiiScrub($form) ? 1 : 0;
        $log->row_count = $rows;
        $log->save(false);
    }
}
