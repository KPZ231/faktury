<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use Dell\Faktury\Controllers\AgentController;
use FastRoute\Dispatcher;
use function FastRoute\simpleDispatcher;
use Dell\Faktury\Controllers\HomeController;
use Dell\Faktury\Controllers\WizardController;
use Dell\Faktury\Controllers\TableController;

$dispatcher = simpleDispatcher(function(FastRoute\RouteCollector $r) {
    // Home routes – CSV import
    $r->addRoute('GET',    '/',            [HomeController::class, 'index']);
    $r->addRoute('POST',   '/',            [HomeController::class, 'importCsv']);
    $r->addRoute('POST',   '/upload-file', [HomeController::class, 'uploadFile']);

    // Wizard routes
    $r->addRoute('GET',    '/wizard',      [WizardController::class, 'show']);
    $r->addRoute('POST',   '/wizard',      [WizardController::class, 'store']);

    $r->addRoute('GET', '/table',          [TableController::class, 'index']);

    $r->addRoute('GET', "/agents", [AgentController::class, 'index']);
    $r->addRoute('POST', "/agents", [AgentController::class, 'addAgent']);
});

$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);
switch ($routeInfo[0]) {
    case Dispatcher::NOT_FOUND:
        http_response_code(404);
        echo "404 – Strona nie znaleziona";
        break;
    case Dispatcher::METHOD_NOT_ALLOWED:
        http_response_code(405);
        echo "405 – Niedozwolona metoda";
        break;
    case Dispatcher::FOUND:
        [$class, $method] = $routeInfo[1];
        $vars = $routeInfo[2];
        try {
            $ctrl = new $class();
            call_user_func_array([$ctrl, $method], $vars ?: []);
        } catch (Exception $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo "Wystąpił błąd: " . $e->getMessage();
        }
        break;
}