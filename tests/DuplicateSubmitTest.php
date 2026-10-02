<?php
/**
 * DAT-11. A single-response form refuses a second completed response from the same person,
 * and a resubmitted page token returns the response it already created.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\models\SubmitForm;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_respondent'));
$single = ReviewLib::form(review_space(), 'EV DAT-11 single ' . uniqid(), ['allow_anonymous' => 0, 'allow_multiple' => 0]);
ReviewLib::clearFields($single);
$q = ReviewLib::field($single, FormField::TYPE_TEXT, 'Q', ['variable' => 'dat11']);
$single = ReviewLib::publishOpen($single);
$first = ReviewLib::submit($single, [(int)$q->id => 'one']);
$check($first instanceof FormAnswer, 'the first response did not save');
$second = new SubmitForm();
$second->form = ReviewLib::reload($single);
$second->values = [(int)$q->id => 'two'];
$check($second->save(null, false, false, false) === null && str_contains(implode(' ', $second->getErrorSummary(true)), 'already submitted'), 'a second response was created on a single-response form');

$multi = ReviewLib::form(review_space(), 'EV DAT-11 multi ' . uniqid(), ['allow_anonymous' => 1, 'allow_multiple' => 1]);
ReviewLib::clearFields($multi);
$m = ReviewLib::field($multi, FormField::TYPE_TEXT, 'Q', ['variable' => 'dat11m']);
$multi = ReviewLib::publishOpen($multi);
Yii::$app->request->setBodyParams(['submit_token' => bin2hex(random_bytes(16))]);
$a = ReviewLib::submit($multi, [(int)$m->id => 'click']);
$b = ReviewLib::submit($multi, [(int)$m->id => 'click']);
$check($a && $b && (int)$a->id === (int)$b->id, 'a double submit with one token created two responses');
Yii::$app->request->setBodyParams([]);

ReviewLib::asUser(review_user('review_netadmin'));
if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
