<?php
/**
 * SEC-15..20. Signed expiring panel links, namespaced creator-HTML inputs, LLM host
 * allowlist, and redacted briefs.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormPanel;
use humhub\modules\thiscoveryForms\models\FormPanelMember;
use humhub\modules\thiscoveryForms\services\HtmlSanitizer;
use humhub\modules\thiscoveryForms\services\LlmClient;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
$panel = FormPanel::findOne(['title' => 'EV SEC-16 panel']) ?: new FormPanel();
$panel->title = 'EV SEC-16 panel';
$panel->contentcontainer_id = (int)review_space()->contentcontainer_id;
$panel->save(false);
$member = new FormPanelMember();
$member->panel_id = (int)$panel->id;
$member->email = 'sec16-' . uniqid() . '@example.org';
$member->status = FormPanelMember::STATUS_ACTIVE;
$member->save(false);

$link = $member->linkToken();
$check(!str_contains($link, (string)$member->token), 'the stored token appears in the link');
$check((int)(FormPanelMember::fromLinkToken($link)->id ?? 0) === (int)$member->id, 'a valid link did not resolve');
$check(FormPanelMember::fromLinkToken((string)$member->token) === null, 'the raw stored token worked as a link');
$check(FormPanelMember::fromLinkToken(substr($link, 0, -1) . (substr($link, -1) === 'a' ? 'b' : 'a')) === null, 'a forged link resolved');
$expired = (int)$member->id . '-' . base_convert((string)(intdiv(time(), 86400) - 1), 10, 36) . '-' . str_repeat('0', 24);
$check(FormPanelMember::fromLinkToken($expired) === null, 'an expired link resolved');

$html = (new HtmlSanitizer())->sanitize('<input type="text" name="SubmitForm[values][12]"><select name="consent[form][items][x]"></select>');
$check(str_contains($html, 'name="cfhtml_SubmitForm[values][12]"') && str_contains($html, 'name="cfhtml_consent'), 'creator HTML kept a fill-field name: ' . $html);

$check(LlmClient::baseAllowed('https://api.openai.com/v1'), 'the OpenAI host was refused');
$check(!LlmClient::baseAllowed('http://api.openai.com/v1'), 'plain http was allowed for the API key');
$check(!LlmClient::baseAllowed('https://attacker.example/v1'), 'an unknown host was allowed for the API key');

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
