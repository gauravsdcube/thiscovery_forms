<?php
/**
 * Boot HumHub and load the module test helpers.
 * Refuses to run unless THISCOVERY_FORMS_TEST_DB=1.
 */

if (getenv('THISCOVERY_FORMS_TEST_DB') !== '1') {
    fwrite(STDERR, "Refusing to run. Set THISCOVERY_FORMS_TEST_DB=1 to use the test database.\n");
    exit(2);
}

require_once __DIR__ . '/helpers.php';

if (defined('THISCOVERY_FORMS_TEST_BOOTED')) {
    return;
}
define('THISCOVERY_FORMS_TEST_BOOTED', true);

$protected = dirname(__DIR__, 4);
$root = dirname($protected);
$loader = require $protected . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createMutable($root, '.env');
$dotenv->safeLoad();

$loader->addClassMap([
    'humhub\\services\\BootstrapService' => $protected . '/humhub/services/BootstrapService.php',
]);

$bootstrap = new humhub\services\BootstrapService(true);
$ref = new ReflectionClass($bootstrap);
$prepare = $ref->getMethod('prepare');
$prepare->setAccessible(true);
$prepare->invoke($bootstrap);
$getConfig = $ref->getMethod('getConfig');
$getConfig->setAccessible(true);

defined('STDIN') or define('STDIN', fopen('php://stdin', 'r'));
defined('STDOUT') or define('STDOUT', fopen('php://stdout', 'w'));

new humhub\components\console\Application($getConfig->invoke($bootstrap, 'console'));
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
$_SERVER['SCRIPT_NAME'] = '/index.php';
Yii::$app->set('request', new yii\web\Request([
    'cookieValidationKey' => 'thiscovery-forms-tests',
    'scriptFile' => $root . '/index.php',
    'scriptUrl' => '/index.php',
    'enableCsrfValidation' => false,
]));
if (Yii::$app->controller === null) {
    $module = new yii\base\Module('thiscovery-forms-tests', Yii::$app);
    Yii::$app->controller = new yii\base\Controller('review', $module);
}

require_once __DIR__ . '/ReviewLib.php';
