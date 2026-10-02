<?php
/**
 * HB-9 / LOG-6. Option order is always per respondent: exclusive and Other stay put,
 * the order shown before the first save is the one stored, and two guests differ.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV HB9 order', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
ReviewLib::clearFields($form);
$field = ReviewLib::field($form, FormField::TYPE_RADIO, 'Colour', ['variable' => 'colour']);
$field->setOptionsFromText("Red\nBlue\nGreen\nYellow\nNone\nOther", true, null, 'None');
$field->save(false);
$form = ReviewLib::publishOpen($form);
$field = FormField::findOne((int)$field->id);

$answer = new FormAnswer();
$answer->form_id = (int)$form->id;
$answer->status = FormAnswer::STATUS_IN_PROGRESS;
$answer->is_test = 0;
$answer->resume_code = 'hb9resume';
$answer->save(false);

// The first page is drawn before any answer exists.
$beforeSave = $field->getShuffledOptions(null, null);
$shown = $field->getShuffledOptions(null, $answer);
$pinnedAtEnd = true;
$seenPinned = false;
foreach ($shown as $code) {
    $pinned = $code === 'None' || $code === 'Other';
    if ($pinned) {
        $seenPinned = true;
    } elseif ($seenPinned) {
        $pinnedAtEnd = false;
    }
}
$check($pinnedAtEnd && $seenPinned, 'exclusive and Other were shuffled into the list');

FormField::storeOptionOrder(ReviewLib::reload($form), $answer);
$answer->refresh();
$decodedVars = json_decode((string)$answer->vars_json, true);
$stored = is_array($decodedVars) ? ($decodedVars['option_order'][(string)$field->id] ?? null) : null;
$check($stored === $shown, 'the stored order is not the order shown');
$check($stored === $beforeSave, 'the order stored at first save differs from the first page');
$check($field->getShuffledOptions(null, $answer) === $shown, 'a later view did not reuse the stored order');

// Two guests in different sessions do not all share one order. Over a few sessions at
// least one order differs (four shuffled options give 24 orders).
$orders = [];
foreach (range(1, 6) as $i) {
    Yii::$app->session->close();
    Yii::$app->session->setId('hb9-guest-' . $i . '-' . bin2hex(random_bytes(4)));
    $orders[implode(',', $field->getShuffledOptions(null, null))] = true;
}
$check(count($orders) > 1, 'every guest session got the same option order');
$answer->delete();

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
