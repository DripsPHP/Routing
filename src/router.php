<?php

/**
 * Created by Prowect
 * Author: Raffael Kessler
 * Date: 17.10.2015 - 23:30.
 */
namespace Drips\Routing;

use Closure;

/**
 * Class Router.
 *
 * This class is used for routing. This means you can register routes
 * and if you're calling a specific url the router can check if this route is registered
 * and can respond.
 */
class Router
{
    /**
     * Contains the registered routes.
     * Array-keys are the names of the routes and values are arrays of attributes of
     * a route.
     *
     * @var array
     */
    protected $routes = array();

    /**
     * Contains the current route, determined by the router.
     *
     * @var string
     */
    protected $current_route;

    /**
     * Contains the requested uri.
     *
     * @var string
     */
    protected $request_uri;

    /**
     * Contains the current directory in which the requested script is located.
     *
     * @var string
     */
    protected $current_path;

    /**
     * Contains the virtual document root of the router, which means the absolute
     * path for the requested page.
     *
     * @var string
     */
    protected $document_root;

    /**
     * Contains the requested url.
     *
     * @var string
     */
    protected $url;

    /**
     * Contains the parameters and information of the current route
     *
     * @var array
     */
    protected $params = array();

    /**
     * Creates a new router instance.
     * The given $url is used for simulating a page request.
     * If $url is not set, it will automatically choose $_SERVER['REQUEST_URI'].
     *
     * @param string $url "simulate url request"
     */
    public function __construct($url = null)
    {
        $this->current_path = dirname($_SERVER['SCRIPT_FILENAME']);
        $this->document_root = substr($this->current_path, strlen($_SERVER['DOCUMENT_ROOT'])).'/';
        $this->url = substr(@$_SERVER['REQUEST_URI'], strlen($this->document_root));
        if ($url === null) {
            $url = '/';
            if (isset($_SERVER['REQUEST_URI'])) {
                $url = $this->url;
            }
        }
        $this->request_uri = $url;
    }

    /**
     * Used for registering new routes to the router.
     * The $url definition can be a regular expression without delimiters or
     * a string containing placeholders in the form of {placeholder}.
     * $options can contain following keys:
     *  - "https" ... boolean - if true, this route only matches if this is an https-request - default: false
     *  - "verb" ... string or array - specify if this is only allowed using a GET, POST, DELETE or PUT request - default: all allowed
     *  - "domain" ... string or array - restriction to domains - default: all domains allowed
     *
     * Returns whether the route has successfully been added.
     * This method only fails if $name is already registered.
     *
     * @param string  $name     unique identifier of the route
     * @param string  $url      route definition, which needs to be matched - can contain placeholders
     * @param Closure $callback callback function which would be called if route was matched
     * @param array   $options  optional parameter which can contain more informations about the route (or restrictions)
     *
     * @return bool
     */
    public function add($name, $url, Closure $callback, array $options = array())
    {
        if (!$this->has($name)) {
            $this->routes[$name] = array('url' => $url, 'callback' => $callback, 'options' => $options);
            if (!isset($this->current_route)) {
                if ($this->match($name)) {
                    $this->current_route = $name;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Returns whether $name is already registered to the router.
     *
     * @param string $name name of the route to be checked
     *
     * @return bool
     */
    public function has($name)
    {
        return array_key_exists($name, $this->routes);
    }

    /**
     * Executes the found route.
     * Returns whether the requested url was found.
     *
     * @return bool
     */
    public function route()
    {
        if (isset($this->current_route)) {
            return $this->exec($this->current_route);
        }

        return false;
    }

    /**
     * Executes the route named $name.
     * Returns whether the execution was successful or not.
     *
     * @param string $name the name of the route to be executed
     *
     * @return bool
     */
    public function exec($name)
    {
        if ($this->has($name)) {
            $params = $this->params;
            $params['route'] = $this->routes[$name];
            call_user_func_array($this->routes[$name]['callback'], $params);

            return true;
        }

        return false;
    }

    /**
     * Returns true, if the requested route requires https and
     * https is enabled.
     *
     * @param string $name the name of the route to be checked
     *
     * @return bool
     */
    public function isHTTPS($name)
    {
        if ($this->has($name)) {
            $route = $this->routes[$name];
            if (isset($route['options']['https'])) {
                $https = $route['options']['https'];
                if ($https == true && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'off')) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Returns if the route was requested with the correct request_method (verb).
     *
     * @param string $name the name of the route to be checked
     *
     * @return bool
     */
    public function isValidVerb($name)
    {
        if ($this->has($name)) {
            $route = $this->routes[$name];
            if (isset($route['options']['verb'])) {
                $verbs = $route['options']['verb'];
                if (is_array($verbs) && !in_array(strtoupper($_SERVER['REQUEST_METHOD']), $verbs)) {
                    return false;
                } elseif (!is_array($verbs) && strtoupper($_SERVER['REQUEST_METHOD']) != $verbs) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Returns if the route was requested using the correct domain.
     * You can restrict your route to specific domains.
     *
     * @param string $name the name of the route to be checked
     *
     * @return bool
     */
    public function isValidDomain($name)
    {
        if ($this->has($name)) {
            $route = $this->routes[$name];
            if (isset($route['options']['domain'])) {
                $domains = $route['options']['domain'];
                if (is_array($domains) && !in_array($_SERVER['HTTP_HOST'], $domains)) {
                    return false;
                } elseif (!is_array($domains) && $_SERVER['HTTP_HOST'] != $domains) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Returns whether the route matches the requested url.
     *
     * @param string $name the name of the route to be checked
     *
     * @return bool
     */
    public function match($name)
    {
        if ($this->has($name)) {
            $route = $this->routes[$name];
            if (!$this->isHTTPS($name) || !$this->isValidVerb($name) || !$this->isValidDomain($name)) {
                return false;
            }
            $matches = array();
            $url = trim($route['url'], '/');
            if ($url == '' && trim($this->request_uri, '/') != '') {
                return false;
            }
            if (preg_match_all("/\{([\w-]+)\}/", $url, $matches) && isset($matches[1])) {
                foreach ($matches[1] as $match) {
                    $replace = "([\w-]+)?";
                    if (isset($route['options']['pattern'][$match])) {
                        $replace = $route['options']['pattern'][$match];
                    }
                    $url = str_replace('{'.$match.'}', $replace, $url);
                }
            }
            $matches = array();
            $result = preg_match("`^$url$`", trim($this->request_uri, '/'), $matches);
            if (count($matches) >= 2) {
                array_shift($matches);
                $this->params = $matches;
            }

            return $result;
        }

        return false;
    }
}
