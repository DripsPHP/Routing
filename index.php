<?php

use Drips\Routing\Router;

require_once 'src/router.php';

$router = new Router;

$router->add("home", "/", function(){
    echo "Hello World";
});

$router->add("test", "/test", function(){
    echo "Test";
});

$router->add("param", "/test/{param}", function($param){
    var_dump($param);
});


$router->add("param2", "/test/{param1}/{param2}", function($param, $param2){
    var_dump("$param:$param2");
});

$router->route();
