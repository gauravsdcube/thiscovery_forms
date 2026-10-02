<?php
/**
 * GOV-8. The IP metadata question stores a truncated address (none on fully anonymous
 * forms), and retention removes old drafts, email logs and integrity hashes.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\CustomForm;
use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\services\RespondentMetaService;
use humhub\modules\thiscoveryForms\services\RetentionService;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

$check(RespondentMetaService::truncateIp('203.0.113.42') === '203.0.113.0', 'IPv4 was not truncated to /24');
$check(RespondentMetaService::truncateIp('2001:db8:1:2::9') === '2001:db8:1::', 'IPv6 was not truncated to /48');
$check(RespondentMetaService::truncateIp('not an ip') === '', 'a bad address was kept');

ReviewLib::asUser(review_user('review_netadmin'));
$anon = ReviewLib::form(review_space(), 'EV GOV-8 anonymous', ['allow_anonymous' => 1, 'allow_multiple' => 1, 'identity_mode' => CustomForm::IDENTITY_FULLY_ANONYMOUS]);
$meta = new RespondentMetaService();
$check($meta->valueFor('ip', null, null, ReviewLib::reload($anon)) === '', 'a fully anonymous form stored an address');

// An abandoned draft older than the draft retention is removed; a recent one is not.
$form = ReviewLib::form(review_space(), 'EV GOV-8 retention', ['allow_anonymous' => 1, 'allow_multiple' => 1]);
$old = new FormAnswer();
$old->form_id = (int)$form->id;
$old->status = FormAnswer::STATUS_IN_PROGRESS;
$old->save(false);
$old->updateAttributes(['updated_at' => gmdate('Y-m-d H:i:s', time() - (RetentionService::days(RetentionService::SETTING_DRAFT_DAYS) + 2) * 86400)]);
$fresh = new FormAnswer();
$fresh->form_id = (int)$form->id;
$fresh->status = FormAnswer::STATUS_IN_PROGRESS;
$fresh->save(false);
$report = (new RetentionService())->run(true);
$check($report['drafts'] >= 1 && FormAnswer::findOne((int)$old->id) !== null, 'a dry run did not report, or removed, the old draft');
(new RetentionService())->run(false);
$check(FormAnswer::findOne((int)$old->id) === null, 'the abandoned draft was kept');
$check(FormAnswer::findOne((int)$fresh->id) !== null, 'a recent draft was removed');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
