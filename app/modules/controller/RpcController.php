<?php
namespace Controllers;

class RpcController extends baseController
{
    /**
     * 架构函数
     * @access public
     */
    public function initialize()
    {
        if (!extension_loaded('yar'))
            die('Not Yar');
        $server = new \Yar_Server($this);
        $server->handle();
    }

    public function api()
    {
        return 'Hello, Yar RPC!';
    }

    public function demo($id)
    {
        if (empty($id)) {
            callback(false);
        }
        $one = M('demo')->getOne($id,'id,demo,addtime');
        if (empty($one)) {
            callback(false);
        }
        return $one;
    }

}