<?php
/**
 * HB-3. The access token is read once, in one order, and a one-time
 * token can be consumed only once.
 */
require __DIR__ . '/support/bootstrap.php';

use humhub\modules\thiscoveryForms\models\FormAccessToken;
use humhub\modules\thiscoveryForms\services\FillContext;
use humhub\modules\thiscoveryForms\services\FillContextService;
use humhub\modules\thiscoveryForms\services\integrity\IntegrityService;
use humhub\modules\thiscoveryForms\services\integrity\IntegritySettings;

$failures = [];
$check = static function (bool $ok, string $message) use (&$failures): void {
    if (!$ok) {
        $failures[] = $message;
        echo "FAIL {$message}\n";
    }
};

ReviewLib::asUser(review_user('review_netadmin'));
Yii::$app->request->setQueryParams(['access' => 'from-query', 'token' => 'panel-query']);
Yii::$app->request->setBodyParams(['access_token' => 'from-body', 'panel_token' => 'panel-body']);
$check(FillContextService::readAccessToken() === 'from-query', 'query access did not win');
Yii::$app->request->setQueryParams([]);
$check(FillContextService::readAccessToken() === 'from-body', 'posted access_token was ignored');
$check(FillContextService::readAccessToken() !== 'panel-body', 'panel token was used as an access token');

$form = ReviewLib::form(review_space(), 'EV HB3 token', [
    'allow_anonymous' => 1,
    'allow_multiple' => 1,
]);
$form = ReviewLib::publishOpen($form);
$svc = new IntegrityService();
$made = $svc->generateAccessTokens($form, 1, true, 'hb3');
$raw = $made['plaintext'][0];
$first = $svc->consumeAccessToken($form, $raw);
$second = $svc->consumeAccessToken($form, $raw);
$row = FormAccessToken::findOne(['form_id' => (int)$form->id, 'token_hash' => IntegritySettings::hashValue('tok:' . $raw)]);
$check($first === true, 'first consume failed');
$check($second === false, 'second consume succeeded');
$check($row && (int)$row->use_count === 1, 'use count is not 1');

IntegritySettings::saveForm($form, [
    'enabled' => 0,
    'access_mode' => IntegritySettings::ACCESS_UNIQUE,
]);
$again = $svc->generateAccessTokens($form, 1, true, 'hb3-gate');
$ctx = new FillContext($form);
$ctx->accessToken = $again['plaintext'][0];
$open = $svc->gateSubmit($form, [], $ctx);
$repeat = $svc->gateSubmit($form, [], $ctx);
$check($open === null, 'first unique submit was rejected');
$check($repeat !== null, 'second unique submit was accepted');

Yii::$app->request->setQueryParams([]);
Yii::$app->request->setBodyParams([]);

if ($failures) {
    echo 'FAILED ' . count($failures) . "\n";
    exit(1);
}
echo "PASS\n";
exit(0);
