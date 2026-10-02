<?php
/**
 * DAT-7 / DAT-8. The answer stores the edition that was shown, a missing
 * edition does not leave the draft in place, and the export names that edition.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\ExportService;
use humhub\modules\thiscoveryForms\services\FormVersionService;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();
$form = ReviewLib::form($space, 'EV DAT edition pin', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
ReviewLib::clearFields($form);
$old = ReviewLib::field($form, FormField::TYPE_TEXT, 'Old question', ['variable' => 'dat_old', 'required' => 0]);
$form = ReviewLib::publishOpen($form);
$firstEdition = (int)$form->current_edition_id;
$check($firstEdition > 0, 'the first publish did not record an edition');

$versions = new FormVersionService();
$old->label = 'Old question renamed';
$old->save(false);
$versions->recordSave($form);
$versions->publish($form);
$form = ReviewLib::reload($form);
$secondEdition = (int)$form->current_edition_id;
$check($secondEdition > 0 && $secondEdition !== $firstEdition, 'the second publish did not create a new edition');

Yii::$app->request->setBodyParams([
    'edition_token' => $versions->signEdition((int)$form->id, $firstEdition),
]);
$pinned = ReviewLib::submit($form, [(int)$old->id => 'from-edition']);
$check($pinned && (int)$pinned->edition_id === $firstEdition, 'the signed edition was replaced by the current edition');

Yii::$app->request->setBodyParams(['edition_token' => 'not-a-real-token']);
$rejected = ReviewLib::submit($form, [(int)$old->id => 'tampered']);
$check($rejected === null, 'a bad edition token was accepted');

Yii::$app->request->setBodyParams([]);
$current = ReviewLib::submit($form, [(int)$old->id => 'current']);
$check($current && (int)$current->edition_id === $secondEdition, 'a fill without a token did not keep the current edition');

$missing = ReviewLib::reload($form);
$ghost = new FormAnswer();
$ghost->edition_id = 999999999;
$versions->applyFillDefinition($missing, $ghost, false);
$check(!empty($missing->editionLoadFailed), 'a missing edition was treated as available');
$check($missing->fields === [] || count($missing->fields) === 0, 'a missing edition still showed the draft');

// DAT-8: an unpublished question and a draft relabel do not reach the export while every
// response was filled against a published edition.
$draftOnly = ReviewLib::field($form, FormField::TYPE_TEXT, 'Draft only question', ['variable' => 'dat_draft_only', 'required' => 0]);
$old->label = 'Draft relabel not published';
$old->save(false);
$draftCsv = (new ExportService())->toCsv(ReviewLib::reload($form));
$check(!str_contains($draftCsv, 'Draft only question'), 'an unpublished question became an export column');
$check(!str_contains($draftCsv, 'Draft relabel not published'), 'a draft relabel reached the export');
$check(str_contains($draftCsv, 'Old question renamed'), 'the export does not use the newest published label');
$draftOnly->delete();

$old->softDelete();
$csv = (new ExportService())->toCsv(ReviewLib::reload($form));
$check(str_contains($csv, 'Edition ID'), 'the export has no edition column');
$check(str_contains($csv, (string)$firstEdition), 'the export omitted the edition the response used');
$check(str_contains($csv, 'from-edition'), 'the export dropped the answer from the earlier edition');
$check(str_contains($csv, 'Old question'), 'the export dropped the question from the earlier edition');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
