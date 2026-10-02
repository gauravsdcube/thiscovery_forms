<?php

/**
 * SCO-11. An EQ-5D level is the option code, not the position in the list.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\Eq5dService;

$failures = [];
$service = new Eq5dService();

function eq5d_field(array $options): FormField
{
    $field = new FormField();
    $field->type = FormField::TYPE_RADIO;
    $field->setOptionsFromText($options);
    return $field;
}

$forward = eq5d_field(['1 | No problems', '2 | Slight', '3 | Moderate', '4 | Severe', '5 | Extreme', '9 | Missing']);
if ($service->dimensionLevel($forward, '5') !== 5) {
    $failures[] = 'code 5';
}
if ($service->dimensionLevel($forward, '9') !== Eq5dService::MISSING_DIMENSION) {
    $failures[] = 'code 9';
}
if ($service->dimensionLevel($forward, '6') !== Eq5dService::MISSING_DIMENSION) {
    $failures[] = 'code 6';
}

$reversed = eq5d_field(['5 | Extreme', '1 | No problems']);
if ($service->dimensionLevel($reversed, '5') !== 5) {
    $failures[] = 'reversed code 5 was ' . $service->dimensionLevel($reversed, '5');
}
if ($service->dimensionLevel($reversed, 'Extreme') !== 5) {
    $failures[] = 'label Extreme';
}

// V3-42: with four dimensions tagged, the fifth is missing (9), not an untagged question.
ReviewLib::asUser(review_user('review_netadmin'));
$partial = ReviewLib::form(review_space(), 'EV V3-42 eq5d partial', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
ReviewLib::clearFields($partial);
$stray = ReviewLib::field($partial, \humhub\modules\thiscoveryForms\models\FormField::TYPE_RADIO, 'Unrelated', ['variable' => 'stray42', 'options' => ['1', '2']]);
$dims = [];
foreach (array_slice(Eq5dService::dimensionRoles(), 0, 4) as $i => $role) {
    $f = ReviewLib::field($partial, \humhub\modules\thiscoveryForms\models\FormField::TYPE_RADIO, 'D' . $i, ['variable' => 'd42_' . $i, 'options' => ['1', '2', '3', '4', '5']]);
    $f->setInstrumentRole($role);
    $f->save(false);
    $dims[] = $f;
}
$values = [(int)$stray->id => '2'];
foreach ($dims as $f) {
    $values[(int)$f->id] = '1';
}
$profile = $service->score(ReviewLib::reload($partial), $values)['profile'];
if ($profile !== '11119') {
    $failures[] = 'partly tagged EQ-5D scored ' . $profile . ' (want 11119, no borrowed question)';
}

// SCO-11: an untagged form is not guessed at: no profile from the first five radio questions,
// and an EQ-5D form cannot be published until all five dimensions are tagged.
$untagged = ReviewLib::form(review_space(), 'EV SCO-11 eq5d untagged', ['allow_anonymous' => 1, 'allow_multiple' => 1, 'kind' => 'eq5d']);
ReviewLib::clearFields($untagged);
$guessValues = [];
for ($i = 1; $i <= 5; $i++) {
    $q = ReviewLib::field($untagged, \humhub\modules\thiscoveryForms\models\FormField::TYPE_RADIO, 'Q' . $i, ['variable' => 's11_q' . $i, 'options' => ['1', '2', '3', '4', '5']]);
    $guessValues[(int)$q->id] = '2';
}
$guess = $service->score(ReviewLib::reload($untagged), $guessValues)['profile'];
if ($guess !== '99999') {
    $failures[] = 'an untagged form was scored from its first radio questions: ' . $guess;
}
if ($service->authoringErrors(ReviewLib::reload($untagged)) === []) {
    $failures[] = 'an EQ-5D form with untagged dimensions could be published';
}

if ($failures) {
    fwrite(STDERR, "FAIL\n" . implode("\n", $failures) . "\n");
    exit(1);
}
fwrite(STDOUT, "PASS\n");
