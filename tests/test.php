<?php

namespace tests;

use PHPUnit_Framework_TestCase;
use Drips\Routing\Router;

require_once __DIR__."/../vendor/autoload.php";

class Test extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider routeProvider
     */
    public function testRouting($route, $url, $result)
    {
        $router = new Router($url);
        $router->add("test", $route, function(){});
        $this->assertEquals($router->route(), $result);
    }

    public function testMatchingRouter()
    {
        $router = new Router("/users/admin");
        $router->add("users", "/users/{name}", function(){}, array("pattern" => ["name" => "([A-Z]+)"]));
        $this->assertFalse($router->route());
        $router->add("users2", "/users/{name}", function(){}, array("pattern" => ["name" => "([a-z]+)"]));
        $this->assertTrue($router->route());
        $this->assertFalse($router->add("users2", "/userz/{name}", function(){}, array("pattern" => ["name" => "([a-z]+)"])));
    }

    public function testSecureRoute() {
        $router = new Router("/secusers/asdf");
        $router->add("secure", "/secusers/{name}", function(){}, array("pattern" => ["name" => "([a-z]+)"], "https" => true));
        $this->assertFalse($router->route());
    }

    public function routeProvider()
    {
        return array(
            ["/users", "/users", true],
            ["/users", "/users/", true],
            ["/users/{username}", "/users", false],
            ["/users/{username}", "/users/", false],
            ["/users/{username}", "/users/asdf", true],
            ["/users/{username}", "/users/123", true],
            ["/", "", true],
            ["/", "/", true],
            ["/{lang}/home", "/de/home", true],
            ["/{lang}/home", "/de/home/", true],
            ["/{lang}/home", "de/home/", true],
            ["/{lang}/home", "de/home", true],
            ["/test/(a|b|c)", "/test/a", true],
            ["/test/(a|b|c)", "/test/b", true],
            ["/test/(a|b|c)", "/test/c", true],
            ["/test/(a|b|c)", "/test/d", false],
            ["/", "/das/ist/sicher/nicht/home", false],
            ["/test", "/test/falsch", false]
        );
    }
}
