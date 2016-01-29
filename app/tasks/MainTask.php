<?php

namespace Tasks;

use \Cli\Output as Output;
use \Controllers\CacheController;

class MainTask extends \Phalcon\Cli\Task
{

    public function test1Action()
    {
        Output::stdout("Hello World!");
    }

    public function mainAction()
    {
        $cache = new ExampleController();
        $cache->flushAction();
        Output::stdout("flush ok");
    }

    public function cmdAction()
    {
        $cmd = \Cli\Execute::singleton();
        $success = $cmd->execute("whoami", __FILE__, __LINE__, $output);

        Output::stdout("You're running this script under $output user");
    }


    public function test2Action($paramArray)
    {
        Output::stdout("First param: $paramArray[0]");
        Output::stdout("Second param: $paramArray[1]");
    }


    /**
     * Action to trigger a fatal error
     */
    public function fatalAction()
    {
        strpos();
    }
}