<?php
define('ROOT_PATH', dirname(dirname(str_replace('\\', '/', __DIR__))));

class HttpServer
{
    public static $instance;
    public $http;

    public function __construct()
    {
        $http = new swoole_http_server("0.0.0.0", 9501);
        $http->set(
            array(
                'worker_num' => 50,//the number of task logical process progcessor run you business code
                //'reactor_num' => 8,//packet decode process,change by condition
                'daemonize' => true,
                'max_request' => 10000,
                'dispatch_mode' => 16//to improve the accept performance ,suggest the number of cpu X 2
            )
        );

        $http->setGlobal(HTTP_GLOBAL_ALL);
        $http->on('Start', function () {
            if (function_exists('cli_set_process_title')) {
                cli_set_process_title('app_server');
            } else if (function_exists('swoole_set_process_name')) {
                swoole_set_process_name('app_server');
            }
        });
        $http->on('Request', array($this, 'onRequest'));
        $http->start();
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new HttpServer;
        }
        return self::$instance;
    }

    public function onRequest($request, $response)
    {
        $response->status('200');
        if($request->server['path_info'] == '/favicon.ico' || $request->server['request_uri'] == '/favicon.ico'){
            return $response->end();
        }
        $hea = $request->header;
        $hea['host'] = str_replace(':9501', '', $hea['host']);//如果端口号是80，就不用要此句代码
        ob_start();
        require_once ROOT_PATH . '/app/functions/core.php';
        require_once ROOT_PATH . '/app/config/defined.php';
        require_once ROOT_PATH . '/app/engine/PhpError.php';
        require_once ROOT_PATH . '/app/engine/ApplicationMicro.php';
        $response->header("Access-Control-Allow-Origin", "*");
        register_shutdown_function(['engine\PhpError', 'runtimeShutdown']);

        $app = new engine\Micro();
        try {
            set_error_handler(['engine\PhpError', 'errorHandler']);
            $app->run();
        } catch (Exception $e) {
            echo $e->getMessage() . '<br>';
            echo '<pre>' . $e->getTraceAsString() . '</pre>';
        }
        $result = ob_get_contents();
        ob_end_clean();
        $response->end($result);
        unset($result, $app);
    }
}

HttpServer::getInstance();
