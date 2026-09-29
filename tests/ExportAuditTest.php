<?php
/**
 * GOV-6. Export is a POST from someone allowed to download it, writes an
 * audit row, and scrubs personal data unless the form turns that off.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormExportLog;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\ExportAudit;
use humhub\modules\thiscoveryForms\services\ExportSettings;
use yii\web\MethodNotAllowedHttpException;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();
$form = ReviewLib::form($space, 'EV GOV export', [
    'allow_anonymous' => 0,
    'allow_multiple' => 1,
]);
ReviewLib::clearFields($form);
ReviewLib::field($form, FormField::TYPE_EMAIL, 'Email', ['variable' => 'gov_email', 'required' => 0]);
$form->setSetting('export', []);
$form->save(false);
$form = ReviewLib::publishOpen($form);
$check(ExportSettings::isPiiScrub($form), 'a personal field did not turn scrubbing on');

ExportSettings::persist($form, [], false);
$form->save(false);
$form = ReviewLib::reload($form);
$check(!ExportSettings::isPiiScrub($form), 'an explicit scrub-off setting was ignored');

$form->answers_visibility = CustomForm::ANSWERS_RESPONDENTS;
$form->save(false);
$respondent = review_user('review_respondent');
$own = new FormAnswer();
$own->form_id = (int)$form->id;
$own->status = FormAnswer::STATUS_COMPLETE;
$own->created_by = (int)$respondent->id;
$own->is_test = 0;
$own->save(false);
$check($form->canViewAnswers($respondent), 'the respondent could not see their own answers');
$check(!$form->canExportAnswers($respondent), 'the respondent was allowed to export every response');
$check($form->canExportAnswers(review_user('review_netadmin')), 'a manager was refused the export');

$refused = false;
try {
    ExportAudit::authorize($form);
} catch (MethodNotAllowedHttpException $e) {
    $refused = true;
}
$check($refused, 'a GET export was accepted');

FormExportLog::deleteAll(['form_id' => (int)$form->id]);
ExportAudit::record($form, "Answer ID\n1\n");
$logged = FormExportLog::find()->where(['form_id' => (int)$form->id])->count();
$check((int)$logged === 1, 'the export was not written to the audit log');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
