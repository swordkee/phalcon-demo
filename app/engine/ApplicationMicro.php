<?php

/**
 * Small Micro application to run simple/rest based applications
 *
 * @package ApplicationMicro
 * @version 1.0
 * @link http://docs.phalconphp.com/en/latest/reference/micro.html
 * @example
 * $app = new Micro();
 * $app->get('/api/looks/1', function() { echo "Hi"; });
 * $app->finish(function() { echo "Finished"; });
 * $app->run();
 */

namespace Engine;

use Phalcon\Config;
use Phalcon\Db\Profiler as DatabaseProfiler;
use Phalcon\DI;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Http\Response;
use Phalcon\Loader;
use Phalcon\Logger;
use Phalcon\Logger\Formatter\Line as FormatterLine;
use Phalcon\Mvc\Micro as MvcMicro;
use Phalcon\Mvc\Model\Manager as ModelsManager;
use Phalcon\Mvc\Model\Transaction\Manager as TxManager;
use Phalcon\Registry;

class Micro extends MvcMicro
{
    /**
     * Application configuration.
     *
     * @var Config
     */
    protected $_config;
    /**
     * Pages that doesn't require authentication
     * @var array
     */
    protected $_noAuthPages;
    /**
     * Loaders for different modes.
     *
     * @var array
     */
    private $_loaders =
        [
            'cache',
            'routes',
            'database',
            'engine'
        ];

    /**
     * Constructor of the App
     */
    public function __construct()
    {
        $di = new DI\FactoryDefault();
        $this->_config = config('config');
        $registry = new Registry();
        $registry->directories = (object)[
            'modules' => APP_PATH . '/modules/',
            'engine' => APP_PATH . '/engine/',
            'library' => APP_PATH . '/library/'
        ];
        $di->set('registry', $registry);
        $di->setShared('config', $this->_config);
        $eventsManager = new EventsManager();
        $this->setEventsManager($eventsManager);

        $this->_initLogger($di, $this->_config);
        $this->_initLoader($di, $this->_config, $eventsManager);

        foreach ($this->_loaders as $service) {
            $serviceName = ucfirst($service);
            $eventsManager->fire('init:before' . $serviceName, null);
            $result = $this->{'_init' . $serviceName}($di, $this->_config, $eventsManager);
            $eventsManager->fire('init:after' . $serviceName, $result);
        }
        $di->setShared('eventsManager', $eventsManager);
        $this->_noAuthPages = array();
    }


    /**
     * Set events to be triggered before/after certain stages in Micro App
     *
     * @param \Phalcon\Events\Manager $events
     * @internal param object $event events to add
     */
    public function setEvents(\Phalcon\Events\Manager $events)
    {
        $this->setEventsManager($events);
    }

    /**
     * Main run block that executes the micro application
     *
     */
    public function run()
    {
        // Handle any routes not found
        $this->notFound(function () {
            $response = new Response();
            $response->setStatusCode(404, 'Not Found')->sendHeaders();
            $response->setContent('Page doesn\'t exist.');
            $response->send();
        });
        $this->handle();
    }

    /**
     * Init logger.
     *
     * @param DI $di Dependency Injection.
     * @param Config $config Config object.
     *
     * @return void
     */
    protected function _initLogger($di, $config)
    {
        if ($config->logger->enabled) {
            $di->set(
                'logger',
                function ($file = 'errors', $format = null) use ($config) {
                    $logger = write_log($file, "");
                    $formatter = new FormatterLine(($format ? $format : $config->logger->format));
                    $logger->setFormatter($formatter);
                    return $logger;
                },
                false
            );
        }
    }

    /**
     * Init loader.
     *
     * @param DI $di Dependency Injection.
     * @param Config $config Config object.
     * @param EventsManager $eventsManager Event manager.
     *
     * @return Loader
     */
    protected function _initLoader($di, $config, $eventsManager)
    {
        // Add all required namespaces and modules.
        $registry = $di->get('registry');
        $namespaces = [];

        $namespaces['Controllers'] = $registry->directories->modules . '/controller/';
        $namespaces['Models'] = $registry->directories->modules . '/models/';
        $namespaces['Cli'] = $registry->directories->library . '/cli/';
        $namespaces['Engine'] = $registry->directories->engine;
        $dirs['libraryDir'] = $registry->directories->library;
        $dirs['engineDir'] = $registry->directories->engine;
        $dirs['modulesDir'] = $registry->directories->modules;

        $loader = new Loader();
        $loader->registerDirs($dirs);
        $loader->registerNamespaces($namespaces);

        $loader->register();
        $di->set('loader', $loader);
        return $loader;
    }

    /**
     * Set Routes\Handlers for the application
     *
     *
     * @param $di
     * @param $config
     * @return Config
     * @throws \Exception
     * @internal param File $file
     * @internal param File $file thats array of routes to load
     */
    public function _initRoutes($di, $config)
    {
        $routes = config('routes');
        if (!empty($routes)) {
            foreach ($routes as $obj) {
                $obj = (array)$obj;
                $action = $obj['handler'][1];
                $control = $obj['handler'][0];
                $controllerName = class_exists($control) ? $control : false;

                if (!$controllerName) {
                    throw new \Exception("Wrong controller name in routes ({$control})");
                }

                $controller = new $controllerName;
                $methods = ['get', 'post', 'delete', 'put', 'head', 'options', 'patch'];
                $method = strtolower($obj['method']);
                if (in_array($method, $methods)) {
                    $this->$method($obj['route'], [$controller, $action]);
                }
            }
        }
        $di->set('routes', $routes);
        return $routes;
    }

    /**
     * Init database.
     *
     * @param DI $di Dependency Injection.
     * @param Config $config Config object.
     * @param EventsManager $eventsManager Event manager.
     *
     * @return Pdo
     */
    protected function _initDatabase($di, $config, $eventsManager)
    {
        $adapter = '\Phalcon\Db\Adapter\Pdo\\' . ucfirst($config->dbMaster->adapter);
        /** @var Pdo $connMaster */

        $connMaster = new $adapter(
            [
                "host" => $config->dbMaster->host,
                "port" => $config->dbMaster->port,
                "username" => $config->dbMaster->username,
                "password" => $config->dbMaster->password,
                "dbname" => $config->dbMaster->dbname,
                "prefix" => $config->dbMaster->prefix,
                'options' => [
                    \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '" . $config->dbMaster->charset . "'",
                    \PDO::ATTR_CASE => \PDO::CASE_LOWER,
                    \PDO::ATTR_PERSISTENT => true
                ]
            ]
        );
        $connSlave = new $adapter(
            [
                "host" => $config->dbSlave->host,
                "port" => $config->dbSlave->port,
                "username" => $config->dbSlave->username,
                "password" => $config->dbSlave->password,
                "dbname" => $config->dbSlave->dbname,
                "prefix" => $config->dbSlave->prefix,
                'options' => [
                    \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '" . $config->dbSlave->charset . "'",
                    \PDO::ATTR_CASE => \PDO::CASE_LOWER,
                    \PDO::ATTR_PERSISTENT => true
                ]
            ]
        );

        $isDebug = $config->debug;
        $isProfiler = $config->profiler;
        if ($isDebug || $isProfiler) {
            // Attach logger & profiler.
            $logger = null;
            $profiler = null;

            if ($isDebug) {
                $logger = write_log("database", "");
            }
            if ($isProfiler) {
                $profiler = new DatabaseProfiler();
                $di->set('profiler', $profiler);
            }

            $eventsManager->attach(
                'db',
                function ($event, $connection) use ($logger, $profiler) {
                    if ($event->getType() == 'beforeQuery') {
                        $statement = $connection->getSQLStatement();
                        if ($logger) {
                            $logger->log($statement, Logger::INFO);
                        }
                        if ($profiler) {
                            $profiler->startProfile($statement);
                        }
                    }
                    if ($event->getType() == 'afterQuery') {
                        // Stop the active profile.
                        if ($profiler) {
                            $profiler->stopProfile();
                        }
                    }
                }
            );

            $connMaster->setEventsManager($eventsManager);
            $connSlave->setEventsManager($eventsManager);
        }

        $di->set('dbMaster', $connMaster);
        $di->set('db', $connSlave);
        $di->set(
            'modelsManager',
            function () use ($config, $eventsManager) {
                $modelsManager = new ModelsManager();
                $modelsManager->setEventsManager($eventsManager);
                return $modelsManager;
            },
            true
        );

        return $connMaster;
    }

    /**
     * Init cache.
     *
     * @param DI $di Dependency Injection.
     * @param Config $config Config object.
     *
     * @return void
     */
    protected function _initCache($di, $config)
    {
        $caches['cache'] = $config->cache->toArray();
        if (!empty($config->cacheSlave)) {
            foreach ($config->cacheSlave as $cache)
                $caches['cacheSlave'][] = $cache->toArray();
        }
        $adapter = ucfirst($config->cache->adapter);
        if ($adapter == "Redis") {
            $cacheAdapter = '\Engine\\' . $adapter;
        } else {
            $cacheAdapter = '\Phalcon\Cache\Backend\\' . $adapter;
        }
        $frontEndOptions = ['lifetime' => $config->cache->lifetime];
        $cacheDataAdapter = new $cacheAdapter($frontEndOptions, $caches);
        $di->set('cacheData', $cacheDataAdapter, true);
    }

    /**
     * Init engine.
     *
     * @param DI $di Dependency Injection.
     *
     * @return void
     */
    protected function _initEngine($di)
    {
        $di->setShared(
            'transactions',
            function () {
                $manager = new TxManager();
                return $manager->setDbService("dbMaster");

            }
        );
    }
}
