<?php
/**
 * V3-44. Manager edits need a reason and are attributed (even on anonymous forms),
 * filling in a blank is audited, and filling in a draft is not.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\SubmitForm;
use yii\db\Query;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

$admin = review_user('review_netadmin');
ReviewLib::asUser($admin);
$form = ReviewLib::form(review_space(), 'EV V3-44 audit', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
ReviewLib::clearFields($form);
$q1 = ReviewLib::field($form, FormField::TYPE_TEXT, 'One', ['variable' => 'a44_one', 'required' => 0]);
$q2 = ReviewLib::field($form, FormField::TYPE_TEXT, 'Two', ['variable' => 'a44_two', 'required' => 0]);
$form = ReviewLib::publishOpen($form);
$audit = static fn(int $answerId): array => (new Query())->from('{{%custom_form_answer_audit}}')->where(['answer_id' => $answerId])->orderBy(['id' => SORT_ASC])->all();
$edit = static function (FormAnswer $answer, array $values, ?string $reason) use ($form): array {
    $submit = new SubmitForm();
    $submit->form = ReviewLib::reload($form);
    $submit->values = $values;
    $submit->changeReason = $reason;
    $saved = $submit->save(FormAnswer::findOne((int)$answer->id), false, false, false);
    return [$saved, implode(' ', $submit->getErrorSummary(true))];
};

// An anonymous response: no created_by.
$answer = ReviewLib::submit($form, [(int)$q1->id => 'first']);
$check($answer instanceof FormAnswer, 'the response did not save');
if ($answer) {
    $answer->updateAttributes(['created_by' => null]);
    $check($audit((int)$answer->id) === [], 'the first submission wrote audit rows');

    [$saved, $errors] = $edit($answer, [(int)$q1->id => 'second'], null);
    $check($saved === null && str_contains($errors, 'reason'), 'a manager edit saved without a reason');

    [$saved] = $edit($answer, [(int)$q1->id => 'second', (int)$q2->id => 'added'], 'Participant phoned with a correction');
    $check($saved instanceof FormAnswer, 'a manager edit with a reason did not save');
    $rows = $audit((int)$answer->id);
    $byField = [];
    foreach ($rows as $row) {
        $byField[(int)$row['field_id']] = $row;
    }
    $changed = $byField[(int)$q1->id] ?? null;
    $added = $byField[(int)$q2->id] ?? null;
    $check($changed && (string)$changed['old_value'] === 'first' && (string)$changed['new_value'] === 'second', 'the change was not audited');
    $check($changed && (int)$changed['actor_id'] === (int)$admin->id, 'the manager was not named on an anonymous response');
    $check($changed && (string)$changed['reason'] === 'Participant phoned with a correction', 'the reason was not kept');
    $check($added && (string)$added['old_value'] === '' && (string)$added['new_value'] === 'added', 'filling in a blank was not audited');
}

// A draft being filled in is not audited.
$draft = ReviewLib::submit($form, [(int)$q1->id => 'draft one'], true);
if ($draft) {
    ReviewLib::submit($form, [(int)$q1->id => 'draft two', (int)$q2->id => 'more'], true, $draft);
    $check($audit((int)$draft->id) === [], 'filling in a draft wrote audit rows');
}

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
