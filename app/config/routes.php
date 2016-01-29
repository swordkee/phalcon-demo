<?php
$routes[] = [
    'method' => 'get',
    'route' => '/hi',
    'handler' => ['Controllers\ExampleController', 'hiAction']
];
$routes[] = [
    'method' => 'get',
    'route' => '/demo/{id}',
    'handler' => ['Controllers\ExampleController', 'demoAction']
];

$routes[] = [
    'method' => 'post',
    'route' => '/rpc',
    'handler' => ['Controllers\RpcController', 'initialize']
];
return $routes;
