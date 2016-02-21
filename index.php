<?php

use Drips\Routing\Router;

require_once __DIR__.'/vendor/autoload.php';

$router = new Router();

$router->add('home', '/', function () use ($router) {
    echo '<h1>Hello World</h1>';
    echo "<a href='{$router->link('test')}'>Test</a>";
});

$router->add('test', '/test', function () use ($router) {
    echo 'Test ';
    echo "<a href='{$router->link('param', ['param' => 1])}'>Test with 1</a> ";
    echo "<a href='{$router->link('param', ['param' => 2])}'>Test with 2</a>";
});

$router->add('param', '/test/{param}', function ($param) {
    var_dump($param);
});

$router->add('param2', '/test/{param1}/{param2}', function ($param, $param2) use ($router) {
    var_dump("$param:$param2");
    $router->redirect("home");
});

$router->route();
