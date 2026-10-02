<?php
/**
 * Secure send: hashed link and code, one-time use, expiry, revoke, and a readable IP on the audit.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\SecureCode;
use humhub\modules\thiscoveryForms\models\SecureEvent;
use humhub\modules\thiscoveryForms\models\SecureRelease;
use humhub\modules\thiscoveryForms\Module;
use humhub\modules\thiscoveryForms\services\SecureSendService;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

$module = Yii::$app->getModule('thiscovery-forms');
$previous = (string)$module->settings->get(Module::SETTING_SECURE_SEND, '0');
$module->settings->set(Module::SETTING_SECURE_SEND, '1');
$mailer = Yii::$app->mailer;
$mailDir = sys_get_temp_dir() . '/cf-secure-mail-' . getmypid();
@mkdir($mailDir, 0700, true);
Yii::$app->mailer->useFileTransport = true;
Yii::$app->mailer->fileTransportPath = $mailDir;

ReviewLib::asUser(review_user('review_netadmin'));
$space = review_space();
$form = ReviewLib::form($space, 'EV secure send', ['allow_multiple' => 1]);
$svc = new SecureSendService();
$token = '';

$latestCode = static function () use ($mailDir): string {
    $files = glob($mailDir . '/*.eml') ?: [];
    sort($files, SORT_STRING);
    $code = '';
    foreach ($files as $file) {
        $body = (string)file_get_contents($file);
        if (preg_match_all('/code is ([A-Z0-9]{4}-[A-Z0-9]{4})/', $body, $m)) {
            $code = str_replace('-', '', (string)end($m[1]));
        }
    }
    return $code;
};
$tokenOf = static function (string $link): string {
    $query = parse_url($link, PHP_URL_QUERY);
    parse_str((string)$query, $params);
    return (string)($params['t'] ?? '');
};

try {
    $check($form->canManage(review_user('review_netadmin')), 'a manager was refused the form');
    $check(!$form->canManage(review_user('review_respondent')), 'a respondent was treated as a manager');

    $rejected = $svc->createFromUpload(
        $form,
        'Bad',
        'Ada',
        'ada@example.test',
        tempnam(sys_get_temp_dir(), 'cf'),
        'notes.html',
        (int)Yii::$app->user->id
    );
    $check($rejected === null, 'an HTML upload was accepted');

    $tmp = tempnam(sys_get_temp_dir(), 'cf');
    file_put_contents($tmp, "id,answer\n1,yes\n");
    $created = $svc->createFromUpload(
        $form,
        'Client extract',
        'Ada Lovelace',
        'ada@example.test',
        $tmp,
        'extract.csv',
        (int)Yii::$app->user->id,
        ['ip' => '203.0.113.10', 'user_agent' => 'TestBrowser/1', 'accept_language' => 'en-GB']
    );
    @unlink($tmp);
    $check($created !== null, 'the upload was not prepared');
    $release = $created['release'];
    $token = $tokenOf($created['link']);
    $check(preg_match('/^[A-Za-z0-9_-]{43}$/', $token) === 1, 'the link token was not 43 characters');
    $check(!str_contains((string)$release->link_hash, $token), 'the raw link was stored');
    $check(strlen((string)$release->link_hash) === 64, 'the link hash was not stored');
    $check($svc->findByToken($token)?->id === (int)$release->id, 'the new link did not open the file');

    $check($svc->sendCode($release, SecureCode::SOURCE_MANAGER, (int)Yii::$app->user->id) === 'sent', 'the code email was not accepted');
    $plain = $latestCode();
    $check($plain !== '', 'the code was not in the email');
    $stored = SecureCode::find()->where(['release_id' => (int)$release->id])->orderBy(['id' => SORT_DESC])->one();
    $check($stored && $stored->code_hash !== $plain && !str_contains((string)$stored->code_hash, $plain), 'the raw code was stored');

    $check($svc->redeem($release, 'AAAAAAAA', ['ip' => '203.0.113.10', 'user_agent' => 'TestBrowser/1']) === 'invalid', 'a wrong code was accepted');
    $failed = SecureEvent::find()->where(['release_id' => (int)$release->id, 'event' => 'code_failed'])->orderBy(['id' => SORT_DESC])->one();
    $check($failed && $failed->ip === '203.0.113.10' && str_contains((string)$failed->user_agent, 'TestBrowser'), 'the audit did not keep a readable IP and browser');

    $check($svc->redeem($release, $plain, ['ip' => '203.0.113.11', 'user_agent' => 'Downloader/2']) === 'ok', 'the emailed code was rejected');
    $check($svc->redeem($release, $plain, ['ip' => '203.0.113.11']) === 'invalid', 'a spent code was accepted again');
    $svc->recordDownload($release, ['ip' => '203.0.113.11', 'user_agent' => 'Downloader/2', 'accept_language' => 'en-GB']);
    $download = SecureEvent::find()->where([
        'release_id' => (int)$release->id,
        'event' => 'download',
        'ip' => '203.0.113.11',
    ])->one();
    $check($download !== null, 'the download audit did not keep the IP address');

    $check($svc->sendCode($release, SecureCode::SOURCE_MANAGER, (int)Yii::$app->user->id) === 'sent', 'a second code was not sent');
    $again = $latestCode();
    $check($again !== '' && $again !== $plain, 'the new code did not replace the old one');
    $check($svc->redeem($release, $plain, []) === 'invalid', 'the previous code still worked');
    $check($svc->redeem($release, $again, ['ip' => '203.0.113.12']) === 'ok', 'the new code was rejected');

    $release->refresh();
    $check($svc->sendCode($release, SecureCode::SOURCE_MANAGER, (int)Yii::$app->user->id) === 'sent', 'a code for the expiry check was not sent');
    $expiring = $latestCode();
    $row = SecureCode::find()->where(['release_id' => (int)$release->id, 'used_at' => null, 'revoked_at' => null])->orderBy(['id' => SORT_DESC])->one();
    $row->expires_at = date('Y-m-d H:i:s', time() - 60);
    $row->save(false, ['expires_at']);
    $check($svc->redeem($release, $expiring, []) === 'invalid', 'an expired code was accepted');

    $check($svc->sendCode($release, SecureCode::SOURCE_CONTACT, null, ['ip' => '203.0.113.40']) === 'sent', 'the contact could not request a code');
    $check($svc->sendCode($release, SecureCode::SOURCE_CONTACT, null, ['ip' => '203.0.113.41']) === 'cooldown', 'a second contact code was sent immediately');

    $release->refresh();
    $release->failed_attempts = 0;
    $release->save(false, ['failed_attempts']);
    $live = $latestCode();
    for ($i = 0; $i < 5; $i++) {
        $svc->redeem($release, 'AAAAAAAA', ['ip' => '203.0.113.' . (20 + $i)]);
    }
    $release->refresh();
    $check((int)$release->failed_attempts >= SecureSendService::MAX_FAILURES, 'five wrong codes did not lock the file');
    $check($svc->redeem($release, $live, ['ip' => '203.0.113.30']) === 'locked', 'a correct code was accepted after the lock');

    $rotated = $svc->rotateLink($release, (int)Yii::$app->user->id, ['ip' => '203.0.113.10']);
    $check($rotated !== null && $svc->findByToken($token) === null, 'the old link still worked after a new link was issued');
    $newToken = $tokenOf((string)$rotated);
    $check($svc->findByToken($newToken)?->id === (int)$release->id, 'the new link did not open the file');

    $changed = $svc->changeContact($release, 'Ada Lovelace', 'ada.new@example.test', (int)Yii::$app->user->id, ['ip' => '203.0.113.10']);
    $check($changed && $changed['link'] && $svc->findByToken($newToken) === null, 'the link survived an email change');
    $release->refresh();
    $check($release->contact_email === 'ada.new@example.test', 'the contact email was not saved');

    $module->settings->set(Module::SETTING_SECURE_SEND, '0');
    $offToken = $tokenOf((string)$changed['link']);
    $check($svc->findByToken($offToken) === null, 'a link worked while secure send was off');
    $module->settings->set(Module::SETTING_SECURE_SEND, '1');

    $check($svc->revokeRelease($release, (int)Yii::$app->user->id, ['ip' => '203.0.113.10']), 'the file was not revoked');
    $check($svc->findByToken($offToken) === null, 'a revoked link still opened');
    $check($svc->storedPath($release) === null, 'the revoked file was still on disk');

    $exported = $svc->createFromExport($form, 'Answers export', 'Grace Hopper', 'grace@example.test', [
        'header_mode' => 'label',
        'include_in_progress' => '0',
        'include_excluded' => '0',
    ], (int)Yii::$app->user->id, ['ip' => '203.0.113.10']);
    $check($exported !== null && is_file((string)$svc->storedPath($exported['release'])), 'the answers export was not frozen');
} catch (Throwable $e) {
    $failures[] = $e->getMessage();
    echo 'FAIL ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
} finally {
    $svc->purgeForm((int)$form->id);
    $module->settings->set(Module::SETTING_SECURE_SEND, $previous === '' ? '0' : $previous);
    Yii::$app->set('mailer', $mailer);
    foreach (glob($mailDir . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($mailDir);
}

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
