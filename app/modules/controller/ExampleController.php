<?php

namespace Controllers;

use Engine\Images;
use Engine\Validation;
use Models\AskSubject;
use Models\Award;
use Models\Hits;
use Models\Advice;
use Phalcon\DI;

class ExampleController extends BaseController
{
    public function hiAction()
    {
        echo 'Hello, world!';
    }

    public function demoAction($id)
    {
        if (empty($id)) {
            callback(false);
        }
        $one = M('demo')->getOne($id, 'id,demo,addtime');
        if (empty($one)) {
            callback(false);
        }
        die($this->toJson($one));
    }

    /**
     * 将缓存点击数更新至DB
     */
    public function flushAction()
    {
        die(M('demo')->flushToDB());
    }
}
