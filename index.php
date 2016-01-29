<?php
define('ROOT_PATH', str_replace('\\', '/', __DIR__));
require_once ROOT_PATH . '/app/functions/core.php';
require_once ROOT_PATH . '/app/config/defined.php';
require_once APP_PATH . '/engine/PhpError.php';
require_once APP_PATH . '/engine/ApplicationMicro.php';
register_shutdown_function(['engine\PhpError', 'runtimeShutdown']);
header('Access-Control-Allow-Origin:*');
use Engine\Micro;

$app = new Micro();
try {
    set_error_handler(['engine\PhpError', 'errorHandler']);
    $app->run();
} catch (Exception $e) {
    $app->response->setStatusCode(500, "Server Error");
    $app->response->setContent($e->getMessage());
    $app->response->send();
}
