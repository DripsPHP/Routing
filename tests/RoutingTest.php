<?php

namespace tests;

use PHPUnit_Framework_TestCase;
use Drips\Routing\Router;
use Drips\Routing\Error404Exception;
use Drips\HTTP\Request;

session_start();
$_SERVER["SCRIPT_FILENAME"] = "";

class RoutingTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider routeProvider
     */
    public function testRouting($route, $url, $result) {
        $request = new Request;
        $request->server->set('REQUEST_URI', $url);
        $router = new Router($request);
        $router->add("test", $route, function() {});
        try {
            $res = $router->route();
            $this->assertEquals($res, $result);
        } catch(Error404Exception $e) {
            if($result){
                $this->fail();
            } else {
                $this->assertFalse($result);
            }
        }
    }

    public function testMatchingRouter() {
        $request = new Request;
        $request->server->set('REQUEST_URI', "/users/admin");
        $router = new Router($request);
        $router->add("users", "/users/{name}", function() {}, array("pattern" => ["name" => "([A-Z]+)"]));
        try {
            $router->route();
            $this->fail();
        } catch(Error404Exception $e){
            $this->assertTrue(true);
        }
        $router->add("users2", "/users/{name}", function() {}, array("pattern" => ["name" => "([a-z]+)"]));
        $this->assertTrue($router->route());
        $this->assertFalse($router->add("users2", "/userz/{name}", function() {}, array("pattern" => ["name" => "([a-z]+)"])));
    }

    public function testSecureRoute() {
        $request = new Request;
        $request->server->set('REQUEST_URI', "/secusers/asdf");
        $router = new Router($request);
        $router->add("secure", "/secusers/{name}", function() {}, array("pattern" => ["name" => "([a-z]+)"], "https" => true));
        try {
            $router->route();
            $this->fail();
        } catch(Error404Exception $e) {
            $this->assertTrue(true);
        }
    }

    public function testAsset() {
        $router = new Router(new Request);
        $result = $router->getRoot()."images/rei.jpg";
        $this->assertEquals($router->asset("images/rei.jpg"), $result);
    }

    /**
     * @dataProvider verbProvider
     * @preserveGlobalState
     */
    public function testValidVerb($route_url, $params, $url, $verb, $expected) {
        $_SERVER['REQUEST_METHOD'] = $verb;
        $request = new Request;
        $request->server->set('REQUEST_URI',$url);
        $router = new Router($request);
        $router->add("verb", $route_url, function() {}, $params);
        try {
            $this->assertEquals($router->route(), $expected);
        } catch(Error404Exception $e) {
            if($expected){
                $this->fail();
            } else {
                $this->assertFalse($expected);
            }
        }
    }

    /**
     * @dataProvider linkProvider
     */
    public function testRedirectWithHeadersAlreadySent($route_url, $params, $url) {
        ob_start();
        $router = new Router(new Request);
        $router->add("redirectTo", $route_url, function() {});
        $expected = "<meta http-equiv='refresh' content='0, URL=".dirname($_SERVER["SCRIPT_FILENAME"]).$url."'>";
        $router->redirect("redirectTo", $params);
        $result = ob_get_clean();
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider linkProvider
     */
    public function testLink($route_url, $params, $url) {
        $request = new Request;
        $request->server->set('REQUEST_URI', "/");
        $router = new Router($request);
        $router->add("users", $route_url, function() {});
        $this->assertEquals($router->link("users", $params), dirname($_SERVER["SCRIPT_FILENAME"]).$url);
    }

    public function routeProvider() {
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

    public function linkProvider() {
        return array(
            ["/users/{name}", ["name" => "Loas"], "/users/Loas"],
            ["/users/{name}/dashboard", ["name" => "Loas"], "/users/Loas/dashboard"],
            ["/messages", [], "/messages"]
        );
    }

    public function verbProvider() {
        return array(
            ["/verb/{name}", ["name" => "get", "verb" => "GET"], "/verb/get", "GET", true],
            ["/verb/{name}", ["name" => "post", "verb" => "POST"], "/verb/post", "POST", true],
            ["/test", ["verb" => "GET"], "/test", "GET", true],
            ["/verb/{name}", ["name" => "get", "verb" => "GET"], "/verb/get", "POST", false],
            ["/verb/{name}", ["name" => "post", "verb" => "POST"], "/verb/post", "GET", false],
            ["/test", ["verb" => "GET"], "/test", "PUT", false],
            ["/test", [], "/test", "GET", true],
            ["/test", [], "/test", "POST", true],
            ["/test", [], "/test", "PATCH", true],
            ["/test", [], "/test", "PUT", true],
            ["/test", [], "/test", "DELETE", true]
        );
    }

}
