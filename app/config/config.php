<?php
return array(
    'debug' => true,
    'profiler' => true,
    'dbMaster' =>
        array(
            'adapter' => 'Mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'username' => 'root',
            'password' => 'root',
            'dbname' => 'test',
            'prefix' => 'wj_',
            'charset' => 'UTF8',
        ),
    'dbSlave' =>
        array(
            'adapter' => 'Mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'username' => 'root',
            'password' => 'root',
            'dbname' => 'test',
            'prefix' => 'wj_',
            'charset' => 'UTF8',
        ),
    'cache' =>
        array(
            'lifetime' => '86400',
            'adapter' => 'Redis',
            'host' => '172.16.8.38',
            'port' => 6379,
            'index' => 0,
            'prefix' => 'wj_',
            'persistent' => true,
            'cacheDir' => CACHE_PATH . '/data/',
        ),
    'cacheSlave' =>
        array(
            0 =>
                array(
                    'lifetime' => '86400',
                    'adapter' => 'Redis',
                    'host' => '127.0.0.1',
                    'port' => 6379,
                    'prefix' => 'wj_',
                    'persistent' => true,
                    'cacheDir' => CACHE_PATH . '/data/',
                    'weight' => 1,
                ),
        ),
    'queue' =>
        array(
            'host' => '127.0.0.1',
            'port' => 6379,
//            'auth'=>'',
            'persistent' => true,
        ),
    'logger' =>
        array(
            'enabled' => true,
            'path' => DATA_PATH . '/logs/',
            'format' => '[%date%][%type%] %message%',
        ),
    'upload' =>
        array(
            'path' => 'E:/workspace/uploadfile/'
        ),

);

