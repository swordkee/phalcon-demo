<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('ROOT_PATH', __DIR__ . '/../');
require_once ROOT_PATH . '/app/functions/core.php';
require_once ROOT_PATH . '/app/config/defined.php';
require_once APP_PATH . '/engine/PhpError.php';
require_once APP_PATH . '/engine/ApplicationMicro.php';


$loader = new \Phalcon\Loader();
$loader->registerNamespaces([
    'Cli\Test' => ROOT_PATH . '/test/library/cli/',
    'Controllers\Test' => ROOT_PATH . '/tests/controller/'
])->register();
use Engine\Micro;
use Phalcon\DI\FactoryDefault;

$app = new Micro();
$app->run();