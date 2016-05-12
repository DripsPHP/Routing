<?php

use Drips\Routing\Router;
use Drips\Routing\Error404Exception;
use Drips\HTTP\Request;

require_once __DIR__.'/vendor/autoload.php';

$router = Router::getInstance();

$router->add('home', '/', function () use ($router) {
    echo '<h1>Hello World</h1>';
    echo "<a href='{$router->link('test')}'>Test</a>";
});

$router->add('test', '/test', function (Request $request) {
    echo 'Test ';
    echo "<a href='{$request->router->link('param', ['param' => 1])}'>Test with 1</a> ";
    echo "<a href='{$request->router->link('param', ['param' => 2])}'>Test with 2</a>";

    echo "<pre>";
    var_dump($request);
});

$router->add('param', '/test/{param}', function (Request $request, $param) {
    var_dump($param);
});

$router->add('param2', '/test/{param1}/{param2}', function (Request $request, $param, $param2) use ($router) {
    var_dump("$param:$param2");
    $request->router->redirect("home");
});


try {
    $router->route();
} catch(Error404Exception $e) {
    echo "Error  404";
}
