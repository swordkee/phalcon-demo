<?php

namespace Engine;

use \Cli\Output as Output;


class Cli extends \Phalcon\Cli\Console
{

    /**
     * @const
     */
    const ERROR_SINGLE = 999;

    /**
     * @var string filename that will store the pid
     */
    protected $_pidFile;

    /**
     * @var string directory path that will contain the pid file
     */
    protected $_pidDir = '/tmp';

    /**
     * @var bool    if debug mode is on or off
     */
    protected $_isDebug;

    /**
     * @var
     */
    protected $_argc;

    /**
     * @var
     */
    protected $_argv;

    /**
     * @var
     */
    protected $_isSingleInstance;

    /**
     * Task for cli handler to run
     */
    protected $_task;
    /**
     * Action for cli handler to run
     */
    protected $_action;

    /**
     * Parameters to be passed to Task
     * @var
     */
    protected $_params;

    /**
     * Task Id from the database
     * @var
     */
    protected $_taskId;

    /**
     * constructor
     *
     * @param string $pidDir
     * @internal param of $directory the pid file
     */
    public function __construct($pidDir = '/tmp')
    {
        $this->_pidDir = $pidDir;
        $this->_stderr = $this->_stdout = '';
        $this->_task = $this->_action = NULL;
        $this->_params = array();
        $this->_taskId = NULL;
    }

    /**
     * Set Dependency Injector with configuration variables
     *
     *
     * @param string $file full path to configuration file
     * @throws \Exception
     */
    public function setConfig($file)
    {
//        static $config;

        $di = new \Phalcon\DI\FactoryDefault\CLI();
        $di->set('config', new \Phalcon\Config(require $file));
        $config = $di->get('config');

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

        $di->set('dbMaster', $connMaster);

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
        $di->setShared(
            'transactions',
            function () {
                $manager = new \Phalcon\Mvc\Model\Transaction\Manager();
                return $manager->setDbService("dbMaster");

            }
        );

        $this->setDI($di);
    }


    /**
     * Set namespaces to tranverse through in the autoloader
     *
     * @link http://docs.phalconphp.com/en/latest/reference/loader.html
     *
     * @param string $appDir location of the app directory
     * @internal param string $file map of namespace to directories
     */
    public function setAutoload($appDir)
    {
        $namespaces = [
            'Engine' => $appDir . '/engine/',
            'Controllers' => $appDir . '/modules/controller/',
            'NewModels' => $appDir . '/modules/models/',
            'Tasks' => $appDir . '/tasks/',
            'Cli' => $appDir . '/library/cli/'
        ];

        $loader = new \Phalcon\Loader();
        $loader->registerNamespaces($namespaces)->register();
    }


    /**
     * Set Application arguments
     *
     * @param array of arguments
     * @param count of arguments
     */
    public function setArgs($argv, $argc)
    {
        $this->_argv = $argv;
        $this->_argc = $argc;
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
     * kick off the task
     */
    public function run()
    {
        try {
            $exit = 0;
            $taskId = NULL;
            $this->preTaskCheck($this->_argc);
            $this->determineTask($this->_argv);
            $this->checkProcessInstance($this->_argv);


            $args['task'] = 'Tasks' . "\\" . ucfirst($this->_task);
            $args['action'] = !empty($this->_action) ? $this->_action : 'main';
            if (!empty($this->_params)) {
                $args['params'] = $this->_params;
            }

            // Kick off Task
            $this->handle($args);
            $this->removeProcessInstance();

        } catch (\cli\Exception $e) {
            $exit = 3;
            $this->handleException($e, $this->_taskId, $exit);

        } catch (\Phalcon\Exception $e) {
            $exit = 2;
            $this->handleException($e, $this->_taskId, $exit);

        } catch (\Exception $e) {
            $exit = 1;
            $this->handleException($e, $this->_taskId, $exit);
        }

        return $exit;
    }


    /**
     * Check if pid file needs to be created
     *
     * @param array $argv cli arguments
     * @throws \Cli\Exception
     * @throws \Exception
     * @throws \exceptions\System
     */
    public function checkProcessInstance($argv)
    {

        // Single Instance
        if ($this->isSingleInstance()) {

            $file = sprintf('%s-%s.pid', $this->_task, $this->_action);
            $pid = \Cli\Pid::singleton($file);

            // Default
            $this->_pidFile = $file;

            // Make sure only 1 app at a time is running
            if ($pid->exists()) {
                throw new \Exception('Instance of task is already running', self::ERROR_SINGLE);
            } else {

                if ($pid->create()) {
                    if ($this->isDebug()) {
                        Output::stdout("[DEBUG] Created Pid File: " . $pid->getFileName());
                    }
                } else {
                    $desc = '';
                }

            }
        }
    }

    /**
     * Remove Pid File
     *
     * @return bool
     */
    public function removeProcessInstance()
    {
        if ($this->isSingleInstance()) {
            $pid = \Cli\Pid::singleton('');
            if ($pid->created() && !$pid->removed()) {
                if ($result = $pid->remove()) {
                    if ($this->isDebug()) {
                        Output::stdout("[DEBUG] Removed Pid File: " . $pid->getFileName());
                    }
                } else {
                    $msg = Output::COLOR_RED . "[ERROR]" . Output::COLOR_NONE . " Failed to remove Pid File: $this->_pidFile";
                    Output::stderr($msg);
                }
                return $result;
            }
        }

        return TRUE;
    }

    /**
     * Get the task/action to direct to
     *
     * @param array $flags cli arguments to determine tasks/action/param
     * @throws Exception
     */
    protected function determineTask($flags)
    {

        // Since first argument is the name so script executing (pop it off the list)
        array_shift($flags);

        if (is_array($flags) && !empty($flags)) {
            foreach ($flags as $flag) {
                if (empty($this->_task) && !$this->isFlag($flag)) {
                    $this->_task = $flag;
                } else if (empty($this->_action) && !$this->isFlag($flag)) {
                    $this->_action = $flag;
                } else if (!$this->isFlag($flag)) {
                    $this->_params[] = $flag;
                }
            }
        } else {
            // throw new Exception('Unable to determine task/action/params');
        }
    }

    /**
     * set mode of multiple or single instance at a time
     *
     * @param int $mode
     */
    public function setMode($mode)
    {
        $this->_mode = $mode;
    }

    /**
     * set the directory location of the pid file
     *
     * @param $dir string
     */
    public function setPidDir($dir)
    {
        $this->_pidDir = $dir;
    }


    /**
     * make sure everything required is setup before starting the task
     *
     * @param int $argc count of arguments
     * @throws \Exception
     * @internal param array $argv array of arguments
     */
    protected function preTaskCheck($argc)
    {
        // Make sure task is added
        if ($argc < 2) {
            throw new \Exception('Bad Number of Params needed for script');
        }
    }

    /**
     * sets debug mode
     *
     * @param bool $debug
     */
    public function setDebug($debug)
    {
        $this->_isDebug = $debug;
        if ($debug) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        }
    }

    public function isDebug()
    {
        return $this->_isDebug;
    }

    /**
     * Set Single Instance Flag
     *
     * @param bool
     */
    public function setSingleInstance($single)
    {
        $this->_isSingleInstance = $single;
    }

    /**
     * determine if only a single instance is allowed to run
     *
     * @return bool
     */
    public function isSingleInstance()
    {
        return $this->_isSingleInstance;
    }


    /**
     * handle script ending exception
     *
     * @param \Exception $e
     * @param $taskId
     * @param code $exit
     * @internal param that $Exception caused the failure
     * @internal param of $id the task you started
     * @internal param code $exit status of the process
     */
    protected function handleException(\Exception $e, $taskId, $exit)
    {

        $sub = '%s[ERROR]%s %s file: %s line: %d';

        // Remove Process Instance
        if ($e->getCode() != self::ERROR_SINGLE) {
            $this->removeProcessInstance();
        }

        $msg = sprintf($sub, Output::COLOR_RED, Output::COLOR_NONE, $e->getMessage(), $e->getFile(), $e->getLine());

        // Let user that ran this know it failed
        Output::stderr($msg);
    }

    /**
     * Determine if argument is a special flag
     *
     * @param string
     * @return bool
     */
    protected function isFlag($flag)
    {
        return substr(trim($flag), 2) == '--';
    }

    /**
     * Get the PID File location
     * @return string
     */
    public function getPidFile()
    {
        return $this->_pidFile;
    }

    /**
     * Get the auto incremented value for current task
     * @return int|NULL
     */
    public function getTaskId()
    {
        return $this->_taskId;
    }
}
