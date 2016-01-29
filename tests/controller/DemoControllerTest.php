<?php
/**
 * Created by IntelliJ IDEA.
 * User: kevin
 * Date: 2015/12/14
 * Time: 14:50
 */

namespace Controllers\Test;

use Controllers\DemoController;

use Phalcon\DI;

class CacheControllerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var AdsController
     */
    protected $controller;

    /**
     * @var DI
     */
    protected $di;

    protected function setUp()
    {
        parent::setUp();
        $this->controller = new DemoController();
        $this->di = DI::getDefault();
    }

    public function testadsAction()
    {
        $_GET['type'] = 'gaga';
        $_GET['spaceid'] = '44444';

        $this->assertEquals(777, $_GET['type']);
        $this->assertEquals(888, $_GET['spaceid']);

    }

}
