<?php
/**
 * V3-50 (SEC-11). A respondent who can see answers because they responded sees only their
 * own, and cannot open the integrity dashboard.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\AnswerListService;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV V3-50 respondent visibility', ['allow_anonymous' => 0, 'allow_multiple' => 1]);
$form->answers_visibility = CustomForm::ANSWERS_RESPONDENTS;
$form->save(false);
ReviewLib::clearFields($form);
$note = ReviewLib::field($form, FormField::TYPE_TEXT, 'Note', ['variable' => 'v350_note']);
$form = ReviewLib::publishOpen($form);
$theirs = ReviewLib::submit($form, [(int)$note->id => 'not yours']);

ReviewLib::asUser(review_user('review_respondent'));
$mine = ReviewLib::submit($form, [(int)$note->id => 'mine']);
$form = ReviewLib::reload($form);
$check($form->canViewAnswers(), 'the respondent could not see answers at all');
[$query] = AnswerListService::query($form, []);
$ids = array_map('intval', $query->select('custom_form_answer.id')->column());
$check($mine && in_array((int)$mine->id, $ids, true), 'the respondent could not see their own response');
$check($theirs && !in_array((int)$theirs->id, $ids, true), 'the respondent could see someone else\'s response');
$check($theirs && AnswerListService::findAnswer($form, (int)$theirs->id) === null, 'the respondent could open someone else\'s response');
$check(!$form->canExportAnswers() && !$form->canDecideAnalysis(), 'the respondent passes the integrity dashboard check');

ReviewLib::asUser(review_user('review_netadmin'));

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
