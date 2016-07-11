<?php

/**
 * Created by Prowect
 * Author: Raffael Kessler
 * Date: 17.10.2015 - 23:30.
 * Copyright Prowect.
 */
namespace Drips\Routing;

use Exception;
use Drips\HTTP\Request;
use Drips\HTTP\Response;
use Drips\Utils\OutputBuffer;

/**
 * Class Router.
 *
 * Diese Klasse dient als Routing-System. Es können URLs (sogenannte Route)
 * registriert werden und eine dazugehörige Funktion. Wird die entsprechende URL
 * aufgerufen, so wird die verknüpfte Funktion ausgeführt.
 */
class Router
{
    /**
     * Beinhaltet alle registrierten Routen, sowie deren Eigenschaften.
     *
     * @var array
     */
    protected $routes = array();

    /**
     * Beinhaltet die aktuelle Route, die vom Router gefunden beziehungsweise
     * ausgeführt wurde.
     *
     * @var string
     */
    protected $current_route;

    /**
     * Beinhaltet die aufgerufene URL.
     *
     * @var string
     */
    protected $request_uri;

    /**
     * Beinhaltet den aktuellen Pfad, unter dem sich dieses Script befindet.
     *
     * @var string
     */
    protected $current_path;

    /**
     * Beinhaltet den virtuellen Root von Drips welcher vom Router bestimmt wurde.
     * Dieser ist ausgehend vom Routing-Script.
     *
     * @var string
     */
    protected $drips_root;

    /**
     * Beinhaltet die aufgerufene URL.
     *
     * @var string
     */
    protected $url;

    /**
     * Beinhaltet die Parameter-Informationen zur entsprechenden Route.
     *
     * @var array
     */
    protected $params = array();

    /**
     * Beinhaltet den eingegangen Request.
     *
     * @var Request
     */
    protected $request;

    /**
     * Beinhaltet die Router-Instanz
     *
     * @var Router
     */
    private static $instance;

    /**
     * Gibt die Router-Instanz zurück (Singleton)
     *
     * @return Router
     */
    public static function getInstance()
    {
        if(static::$instance === null){
            static::$instance = new Router;
        }

        return static::$instance;
    }

    /**
     * Erzeugt eine neue Router-Instanz.
     */
    private function __construct()
    {
        $this->request = Request::getInstance();
        $request_uri = $this->request->server->get('REQUEST_URI');
        $this->current_path = dirname($this->request->server->get('SCRIPT_FILENAME'));
        $this->drips_root = substr($this->current_path, strlen($this->request->server->get('DOCUMENT_ROOT'))).'/';
        if(defined('DRIPS_PUBLIC')){
            $this->drips_root = substr($this->drips_root, 0, strlen($this->drips_root) - strlen('/public'));
        }
        $this->request_uri = substr($request_uri, strlen($this->drips_root));
        if(strlen($this->drips_root) < 1 || $this->drips_root[0] != "/"){
            $this->drips_root = "/".$this->drips_root;
        }
        $parts = explode("#", $this->request_uri);
        $this->request_uri = $parts[0];
        $parts = explode("?", $this->request_uri);
        $this->request_uri = $parts[0];
    }

    private function __clone(){}

    /**
     * Diese Methode wird zum Registrieren neuer Routen verwendet.
     * Die $url kann ein regulärer Ausdruck sein, jedoch ohne Delimiter.
     * Außerdem kann die $url auch Platzhalter mit folgendem Format beinhalten:
     * {placeholder}
     * Optional können auch $options übergeben werden. Diese können das Routing
     * einschränken. Dafür gibt es folgende Möglichkeiten:
     *  - "https" ... bool - wenn TRUE, muss die aufgerufene URL über HTTPS aufgerufen worden sein - Standard: FALSE
     *  - "verb" ... string or array - schränkt die Request-Methode ein, also über welche Request-Methoden die Route erreichbar sein soll. - Standard: alle
     *  - "domain" ... string or array - Beschränkt die Route auf eine oder mehrere bestimmte Domains.
     *
     * Gibt zurück ob die Route erfolgreich hinzugefügt wurde oder nicht. (TRUE/FALSE)
     * Wenn der Name der Route bereits vergeben ist, kann die Route nicht registriert werden!

     * @param string $name     eindeutiger Name der Route
     * @param string $url      Routen-Definition - kann Platzhalter beinhalten
     * @param mixed  $callback Funktion, die aufgerufen wird, sobald die Route ausgeführt wird oder ein Controller (MVC)
     * @param array  $options  optional - ermöglicht Zusatzinformationen, wie z.B.: Einschränkungen für die Routen
     *
     * @return bool
     */
    public function add($name, $url, $callback, array $options = array())
    {
        if (!is_callable($callback) && !class_exists($callback)) {
            throw new Exception("Ungültiges Callback bei Route: $name");
        }

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
     * Liefert TRUE oder FALSE, je nachdem ob eine Route mit dem angebenen Namen
     * bereits existiert oder nicht.
     *
     * @param string $name Name der Route, die überprüft werden soll
     *
     * @return bool
     */
    public function has($name)
    {
        return array_key_exists($name, $this->routes);
    }

    /**
     * Führt die "gefundene" Route aus.
     * Gibt TRUE/FALSE zurück, je nachdem ob die Route ausgeführt werden konnte
     * oder nicht.
     * Wurde keine Route gefunden, wird eine Error404Exception geworfen.
     *
     * @return bool
     */
    public function route()
    {
        if (isset($this->current_route)) {
            return $this->exec($this->current_route);
        }

        throw new Error404Exception;
    }

    /**
     * Liefert den DocumentRoot des Routers.
     *
     * @return string
     */
    public function getRoot()
    {
        return $this->drips_root;
    }




    /**
     * Führt die Route aus, die unter dem Namen $name registriert ist.
     * Wurde die Route gefunden und ausgeführt wird TRUE zurückgeliefert, andernfalls
     * FALSE.
     *
     * @param string $name Name der Route, die ausgeführt werden soll.
     *
     * @return bool
     */
    protected function exec($name, array $params = array())
    {
        if ($this->has($name)) {
            if (empty($params)) {
                $params = $this->params;
            }
            $callback = $this->routes[$name]['callback'];
            if (is_callable($callback)) {
                $response = new Response();
                $buffer = new OutputBuffer();
                $buffer->start();
                echo call_user_func_array($callback, $params);
                $response->body = $buffer->end();
                $response->send();
            } elseif (class_exists($callback)) {
                $controller = new $callback($params);
            }

            return true;
        }

        return false;
    }

    /**
     * Gibt zurück ob die angegebene Route existiert und ob diese, falls erforderlich
     * auch mit HTTPS aufgerufen wurde.
     *
     * @param string $name Name der Route, die überprüft werden soll.
     *
     * @return bool
     */
    protected function isHTTPS($name)
    {
        if ($this->has($name)) {
            $route = $this->routes[$name];
            if (isset($route['options']['https'])) {
                $https = $route['options']['https'];
                $server_https = $this->request->server->get('HTTPS');
                if ($https === true && (empty($server_https) || $server_https == 'off')) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Gibt zurück ob die angegebene Route existiert und ob diese, falls erforderlich
     * auch über die richtige Request-Methode aufgerufen wurde.
     *
     * @param string $name Name der Route, die überprüft werden soll.
     *
     * @return bool
     */
    protected function isValidVerb($name)
    {
        if ($this->has($name)) {
            $route = $this->routes[$name];
            if (isset($route['options']['verb'])) {
                $verbs = $route['options']['verb'];
                $request_method = $this->request->server->get('REQUEST_METHOD');
                if (is_array($verbs) && !in_array(strtoupper($request_method), $verbs)) {
                    return false;
                } elseif (!is_array($verbs) && strtoupper($request_method) != $verbs) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Gibt zurück ob die angegebene Route existiert und ob diese, falls erforderlich
     * auch über die richtige Domain aufgerufen wurde.
     *
     * @param string $name Name der Route, die überprüft werden soll.
     *
     * @return bool
     */
    protected function isValidDomain($name)
    {
        if ($this->has($name)) {
            $route = $this->routes[$name];
            if (isset($route['options']['domain'])) {
                $domains = $route['options']['domain'];
                $http_host = $this->request->server('HTTP_HOST');
                if (is_array($domains) && !in_array($http_host, $domains)) {
                    return false;
                } elseif (!is_array($domains) && $http_host != $domains) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Gibt zurück ob die Route der aufgerufenen URL entspricht.
     *
     * @param string $name Name der Route, die überprüft werden soll.
     *
     * @return bool
     */
    protected function match($name)
    {
        if ($this->has($name)) {
            $route = $this->routes[$name];
            if (!$this->isHTTPS($name) || !$this->isValidVerb($name) || !$this->isValidDomain($name)) {
                return false;
            }
            $url = $this->findPlaceholders($route);

            return $this->getParams($url);
        }

        return false;
    }

    /**
     * Sucht nach Platzhaltern in der Routen-Definition und ersetzt diese durch
     * reguläre Ausdrücke.
     * Gibt die generierte URL zurück oder false wenn es sich um eine leere URL
     * bzw. um / handelt.
     *
     * @param array $route Routen-Objekt, wie es gespeichert wurde.
     *
     * @return string|false
     */
    protected function findPlaceholders($route)
    {
        $url = trim($route['url'], '/');
        if (empty($url) && trim($this->request_uri, '/') != '') {
            return false;
        }
        $matches = array();
        if (preg_match_all("/\{([\w-]+)\}/", $url, $matches) && isset($matches[1])) {
            foreach ($matches[1] as $match) {
                $replace = "([\w-]+)?";
                if (isset($route['options']['pattern'][$match])) {
                    $replace = $route['options']['pattern'][$match];
                }
                $url = str_replace('{'.$match.'}', $replace, $url);
            }
        }

        return $url;
    }

    /**
     * Speichert die Parameter der übergebenen URL als Array ($this->params)
     * anhand der aufgerufenen URL.
     *
     * @param string $url URL der die Parameter entnommen werden sollen
     *
     * @return bool
     */
    protected function getParams($url)
    {
        $request = trim($this->request_uri, '/');
        if(stripos($url, AUTO_ROUTE) !== false){
            $url = str_replace('[auto]', '', $url);
            if(preg_match("`^$url`", $request)){
                $this->params = explode('/', substr($request, strlen($url) + 1));

                return true;
            }
        }

        $matches = array();
        $result = preg_match("`^$url$`", $request, $matches);
        if (count($matches) >= 2) {
            array_shift($matches);
            $this->params = $matches;
        }

        return $result;
    }

    /**
     * Liefert alle registrierten Routen zurück.
     *
     * @return array
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * Gibt zurück ob bereits Routen registriert wurden.
     *
     * @return bool
     */
    public function hasRoutes()
    {
        return !empty($this->routes);
    }

    /**
     * Generiert einen Link zu einer bestimmten Route.
     *
     * @param $name Name der Route oder URL
     *
     * @return string
     */
    public function link($name, array $params = array())
    {
        if ($this->has($name)) {
            $route = $this->routes[$name];
            $url = $route['url'];
            foreach ($params as $key => $val) {
                $url = str_replace('{'.$key.'}', $val, $url);
            }
            $url = preg_replace("/\{\w+\}/", '', $url);
            $name = ltrim($url, '/');
        }

        return $this->asset($name);
    }

    /**
     * Führt eine Umleitung auf eine bestimmte Route oder URL durch.
     *
     * @param $name Name der Route oder URL
     * @param array $params Parameter für eine zugehörige Route
     */
    public function redirect($name, array $params = array())
    {
        $url = $this->link($name, $params);
        if ($url === null && filter_var($name, FILTER_VALIDATE_URL)) {
            $url = $name;
        }
        if (headers_sent()) {
            echo "<meta http-equiv='refresh' content='0, URL=$url'>";
        } else {
            header("Location: $url");
            exit();
        }
    }

    /**
     * Erzeugt einen absoluten Pfad für die aufgerufene URL eines bestimmten
     * Assets, beispielsweise einer CSS-Datei oder eines Bildes.
     *
     * @param $name
     *
     * @return string
     */
    public function asset($name)
    {
        return preg_replace('`/{2,}`', '/', $this->drips_root.$name);
    }

    /**
     * Liefert die aktuell gewählte Route zurück oder null, wenn keine Route
     * ausgewählt wurde.
     *
     * @return string
     */
    public function getCurrent()
    {
        return $this->current_route;
    }
}
