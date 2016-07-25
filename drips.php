<?php

use Drips\App;
use Drips\Routing\Router;
use Drips\Routing\ModRewriteNotEnabledException;
use Drips\Routing\AllowOverrideAllException;
use Drips\Routing\NoRoutesException;

if (class_exists('Drips\App')) {
    App::on('create', function (App $app) {
        // Apache?
        if (PHP_SAPI != 'cli' && stripos($_SERVER['SERVER_SOFTWARE'], 'apache') !== false) {
            if(!isset($_SERVER['DRIPS_HTACCESS'])){
                throw new AllowOverrideAllException();
            }
            if(function_exists('apache_get_modules')){
                if (!in_array('mod_rewrite', apache_get_modules())) {
                    throw new ModRewriteNotEnabledException();
                }
            }
        }
    });

    App::on('startup', function (App $app) {
        $router = Router::getInstance();
        if ($router->hasRoutes()) {
            $router->route();
        } else {
            throw new NoRoutesException();
        }
    });
}
