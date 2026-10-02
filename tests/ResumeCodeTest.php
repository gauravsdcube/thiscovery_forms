<?php
/**
 * DAT-14. Resume codes are stored hashed, the stored value is not itself a code, codes
 * expire, and failed lookups are limited.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormAnswer;
use humhub\modules\thiscoveryForms\models\FormField;
use humhub\modules\thiscoveryForms\services\ResumeService;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$form = ReviewLib::form(review_space(), 'EV DAT-14 resume', ['allow_anonymous' => 1, 'allow_multiple' => 1, 'allow_resume' => 1]);
ReviewLib::clearFields($form);
$q = ReviewLib::field($form, FormField::TYPE_TEXT, 'Q', ['variable' => 'dat14']);
$form = ReviewLib::publishOpen($form);
$svc = new ResumeService();
$plain = $svc->generateCode();
$draft = new FormAnswer();
$draft->form_id = (int)$form->id;
$draft->status = FormAnswer::STATUS_IN_PROGRESS;
$draft->resume_code = ResumeService::hash($plain);
$draft->save(false);

$check((string)$draft->resume_code !== $plain && strlen((string)$draft->resume_code) === 32, 'the code was stored in plain text');
$found = $svc->findDraftByCode(ReviewLib::reload($form), strtolower(str_replace('-', '', $plain)));
$check($found && (int)$found->id === (int)$draft->id, 'the plain code (any case, no dashes) did not find the draft');
$check($svc->findDraftByCode(ReviewLib::reload($form), (string)$draft->resume_code) === null, 'the stored hash worked as a code');

$draft->updateAttributes(['updated_at' => date('Y-m-d H:i:s', time() - (ResumeService::codeDays() + 1) * 86400)]);
$check($svc->findDraftByCode(ReviewLib::reload($form), $plain) === null, 'an expired code still worked');

for ($i = 0; $i < ResumeService::LOOKUP_LIMIT + 1; $i++) {
    $svc->findDraftByCode(ReviewLib::reload($form), 'ZZZZ-ZZZZ-ZZ' . sprintf('%02d', $i));
}
$check(ResumeService::lookupsBlocked(), 'repeated failed lookups were not limited');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
