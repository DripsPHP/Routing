<?php

/**
 * Created by Prowect
 * Author: Raffael Kessler
 * Date: 17.10.2015 - 23:30.
 * Copyright Prowect
 */
namespace Drips\Routing;

use Closure;

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
     * Beinhaltet den virtuellen Document-Root welcher vom Router bestimmt wurde.
     * Dieser ist ausgehend vom Routing-Script.
     *
     * @var string
     */
    protected $document_root;

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
     * Erzeugt eine neue Router-Instanz.
     * Übergeben wird die "aufgerufene" Route. Diese kann optional angegeben werden.
     * Wird diese nicht angegeben, wird automatisch die REQUEST_URI des Servers
     * verwendet.
     *
     * @param string $url aufgerufene URL um beispielsweise URL-Aufrufe zu simulieren.
     */
    public function __construct($url = null)
    {
        $request_uri = filter_input(INPUT_SERVER, 'REQUEST_URI');
        $this->current_path = dirname(filter_input(INPUT_SERVER, 'SCRIPT_FILENAME'));
        $this->document_root = substr($this->current_path, strlen(filter_input(INPUT_SERVER, 'DOCUMENT_ROOT'))).'/';
        $this->request_uri = substr($request_uri, strlen($this->document_root));
        if ($url === null) {
            $this->request_uri = $request_uri;
        } else {
            $this->request_uri = $url;
        }
    }

    /**
     * Diese Methode wird zum Registrieren neuer Routen verwendet.
     * Die $url kann ein regulärer Ausdruck sein, jedoch ohne Delimiter.
     * Außerdem kann die $url auch Platzhalter mit folgendem Format beinhalten:
     * {placeholder}
     * Optional können auch $options übergeben werden. Diese können das Routing
     * einschränken. Dafür gibt es folgende Möglichkeiten:
     *  - "https" ... bool - wenn TRUE, muss die aufgerufene URL über HTTPS aufgerufen worden sein - Standard: FALSE
     *  - "verb" ... string or array - schränkt die Request-Methode ein, also über welche Request-Methoden die Route erreichbar sein soll. - Standard: alle
     *  - "domain" ... string or array - Beschränkt die Route auf eine oder mehrere bestimmte Domains
     *
     * Gibt zurück ob die Route erfolgreich hinzugefügt wurde oder nicht. (TRUE/FALSE)
     * Wenn der Name der Route bereits vergeben ist, kann die Route nicht registriert werden!

     * @param string $name eindeutiger Name der Route
     * @param string $url Routen-Definition - kann Platzhalter beinhalten
     * @param Closure $callback Funktion, die aufgerufen wird, sobald die Route ausgeführt wird
     * @param array $options optional - ermöglicht Zusatzinformationen, wie z.B.: Einschränkungen für die Routen
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
     * Führt die Route aus, die unter dem Namen $name registriert ist.
     * Wurde die Route gefunden und ausgeführt wird TRUE zurückgeliefert, andernfalls
     * FALSE.
     *
     * @param string $name Name der Route, die ausgeführt werden soll.
     *
     * @return bool
     */
    protected function exec($name)
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
                $server_https = filter_input(INPUT_SERVER, 'HTTPS');
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
                $request_method = filter_input(INPUT_SERVER, 'REQUEST_METHOD');
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
                $http_host = filter_input(INPUT_SERVER, 'HTTP_HOST');
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
     * Gibt zurück ob die Route der aufgerufenen URL entspricht
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
     * Gibt die generierte URL zurück.
     *
     * @param array $route Routen-Objekt, wie es gespeichert wurde.
     *
     * @return string
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
        $matches = array();
        $result = preg_match("`^$url$`", trim($this->request_uri, '/'), $matches);
        if (count($matches) >= 2) {
            array_shift($matches);
            $this->params = $matches;
        }

        return $result;
    }
}
