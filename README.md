# Routing

[![Build Status](https://travis-ci.org/Prowect/Routing.svg)](https://travis-ci.org/Prowect/Routing)
[![Code Climate](https://codeclimate.com/github/Prowect/Routing/badges/gpa.svg)](https://codeclimate.com/github/Prowect/Routing)
[![Test Coverage](https://codeclimate.com/github/Prowect/Routing/badges/coverage.svg)](https://codeclimate.com/github/Prowect/Routing/coverage)
[![Latest Release](https://img.shields.io/packagist/v/drips/Routing.svg)](https://packagist.org/packages/drips/routing)

## Beschreibung

Das Routing ist zuständig für die Auslösung der URLs zu PHP-Funktionen. Somit können (schöne) URLs manuell festgelegt werden.

## Installation

1. Die entsprechende PHP-Datei (`router.php`) muss included werden.
2. Je nach Webserver muss berücksichtigt werden, dass alle Requests, an das entsprechende Script weitergegeben werden.

### Apache 2

Einfach eine `.htaccess` Datei im entsprechenden Verzeichnis hinzufügen:

```apacheconf
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ index.php?__route__=$1 [QSA]
</IfModule>
```

> Hierfür muss das Rewrite-Modul (`mod_rewrite`) des Webservers aktiviert sein.

## Verwendung

### Anlegen eines Routers

1. Zuerst muss der Router geladen und angelegt werden.

```php
<?php
use Drips\Routing\Router;
require_once 'router.php';
$router = new Router;
```

### Hinzufügen von Routen

2. Nachdem ein entsprechender Router angelegt wurde, können für diesen Routen registriert werden.

```php
<?php
$router->add("name_der_route", "/my/url", function(){
    echo "Hello World!";
});
```

> Wird die URL `/my/url` aufgerufen, so wird die festgelegte Funktion ausgeführt und es wird `Hello World!` angezeigt.

> **Achtung:** der Name der Route muss eindeutig sein. Ist der Name bereits vergeben, kann die Route nicht hinzugefügt werden.

#### MVC-Unterstützung

Das Routing-System unterstützt zusätzlich die Verwendung des [Drips-MVC](https://github.com/Prowect/MVC) Systems. Dementsprechend kann beim Hinzufügen von Routen nicht nur eine Funktion sondern auch ein Controller übergeben werden:

```php
$router->add("name_der_route", "/my/url", MyController::class);
```

#### Routen mit Platzhaltern

Oftmals sind Routen dynamisch. Deshalb können die Routen auch mit Platzhaltern versehen werden. Ein Platzhalter verwendet folgende Schreibweise: `{name_des_platzhalters}`.
Die übergebene Funktion erhält zunächst als ersten Parameter immer ein `Request`-Objekt (siehe [HTTP](http://github.com/Prowect/HTTP)). Alle folgenden Parameter entsprechen den jeweiligen Platzhaltern. (Die Reihenfolge der Parameter ist zwingend einzuhalten!)

```php
<?php
$router->add("name_der_route", "/my/url/{name}", function(Request $request, $name){
    echo "Hello $name!";
});
```

Außerdem können die Platzhalter mithilfe von regulären Ausdrücken eingeschränkt werden.

```php
<?php
$router->add("name_der_route", "/my/url/{name}", function(Request $request, $name){
    echo "Hello $name!";
}, array(
    "pattern" => array(
        "name" => "([A-Za-z]+)"
    );
));
```

#### Routen einschränken

Die Routen können mehrfach eingeschränkt werden:

 - auf bestimmte Domains
 - nur für HTTPS-Verbindungen
 - auf bestimmte Verbs (Request-Methoden) ... GET, POST, DELETE, PUT

Um eine Einschränkung festlegen zu können wird beim Hinzufügen einer Route ein weiterer Parameter angegeben. Dieser ist ein Array und beinhaltet alle gewünschten Einschränkungen.

```php
<?php
$router->add("name_der_route", "/my/url", function(){
    echo "Hello World!";
}, array(
    "https" => true,
    "verb" => ["POST", "GET"],
    "domain" => ["example.com", "example.at"]
));
```

### Routing starten

1. Wenn der Router angelegt ist und alle Routen registriert sind, dann kann mit dem Routing-Prozess begonnen werden. Dies funktioniert vollkommen automatisch - es muss lediglich die Funktion, die das Routen übernimmt, aufgerufen werden.

```php
<?php
$router->route();
```

### Seite nicht gefunden - Error 404

Wird keine entsprechende Route gefunden, wird eine `Error404Exception` geworfen.

```php
<?php
try {
    $router->route();
} catch(Error404Exception $e) {
    echo "Error 404 - Die Seite wurde nicht gefunden!";
}
```

### Links generieren

Damit sich die URLs jederzeit ändern können und auch von unterschiedlichen Verzeichnissen aus aufgerufen werden können, werden die Verlinkungen mit Hilfe der `link` Methode erzeugt.

Ein Link kann wie folgt generiert werden:

```php
<?php
$url = $router->link("testRoute");
```

Übergeben wird der Name der Route. Des Weiteren können bei Routen mit Platzhaltern die Links auch entsprechend erzeugt werden, indem einfach die Parameter als Array übergeben werden.

Angenommen es gibt eine Route `/users/{username}`:

```php
<?php
$url = $router->link("users", array("username" => "admin"));
```

### Assets

Das Link-System funktioniert auch für "Dateien", die nicht im Router registriert sind. Gibt es beispielsweise eine CSS-Datei, welche auf einer bestimmten Seite angezeigt werden soll, kann für diese ebenfalls ein absoluter Pfad generiert werden.

```php
<?php
$url = $router->asset("css/style.css");
```

### Umleitungen

Oftmals ist es notwendig auf eine andere Seite weiterzuleiten. Dies erfolgt über die `redirect`-Methode. Diese funktioniert im Prinzip genau gleich, wie die `link`-Methode, mit dem einzigen Unterschied, dass kein Link zurückgeliefert wird, sondern direkt auf diese Seite weitergeleitet wird.

```php
<?php
$router->redirect("home");
```
