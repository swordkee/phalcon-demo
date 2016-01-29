<?php
/**
 * cli
 *
 * @example php cli.php [task] [action] [param1 [param2 ...]]
 * @example php cli.php Example index
 * @example php cli.php Example index --debug --single
 */
if (PHP_SAPI != 'cli') {
    exit;
}
define('ROOT_PATH', str_replace('\\', '/', __DIR__));
$dir = dirname(__DIR__);
$appDir = $dir . '/app/';
require $appDir . '/app/functions/core.php';
require $appDir . '/app/config/defined.php';
require $appDir . '/app/engine/PhpError.php';
require $appDir . '/app/engine/Cli.php';

register_shutdown_function(['engine\PhpError', 'runtimeShutdown']);

$configPath = $appDir . '/app/config/';
$config = $configPath . 'config.php';
try {
    $app = new Engine\Cli();
    set_error_handler(['engine\PhpError', 'errorHandler']);
    $app->setAutoload($appDir.'app/');
    $app->setConfig($config);

    if ($key = array_search('--single', $argv)) {
        $app->setSingleInstance(TRUE);
        register_shutdown_function([$app, 'removeProcessInstance']);
    }

    if ($key = array_search('--debug', $argv)) {
        $app->setDebug(TRUE);
    }
    $app->setArgs($argv, $argc);

    $app->run();
} catch (Exception $e) {
    echo $e;
    exit(255);
}