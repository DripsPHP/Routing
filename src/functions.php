<?php

if(!defined('AUTO_ROUTE')){
    define('AUTO_ROUTE', '[auto]');
}

use Drips\Routing\Router;

function route($name, array $params = array())
{
    return Router::getInstance()->link($name, $params);
}

function redirect($name, array $params = array())
{
    return Router::getInstance()->redirect($name, $params);
}

function asset($name)
{
    return Router::getInstance()->asset($name);
}
