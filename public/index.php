<?php
// public/index.php
session_start();
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use FastRoute\Dispatcher;
use function FastRoute\simpleDispatcher;

$dispatcher = simpleDispatcher(function(FastRoute\RouteCollector $r) {
    // Strona główna – GET i POST (import CSV)
    $r->addRoute('GET',  '/', [Dell\Faktury\Controllers\HomeController::class, 'index']);
    $r->addRoute('POST', '/', [Dell\Faktury\Controllers\HomeController::class, 'importCsv']);
    $r->addRoute('POST', '/upload-file', [Dell\Faktury\Controllers\HomeController::class, 'uploadFile']);
    
    // Wizard
    $r->addRoute('GET', '/wizard', [Dell\Faktury\Controllers\WizardController::class, 'showWizard']);
    $r->addRoute('GET', '/wizard/{step}', [Dell\Faktury\Controllers\WizardController::class, 'showWizard']);
    $r->addRoute('POST', '/wizard/{step}', [Dell\Faktury\Controllers\WizardController::class, 'saveRecord']);
});

$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
if (false !== $qpos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $qpos);
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
            // Create controller instance with PDO connection if it's WizardController
            if ($class === Dell\Faktury\Controllers\WizardController::class) {
                if (!isset($pdo)) {
                    throw new Exception('Database connection not initialized');
                }
                $ctrl = new $class($pdo);
            } else {
                $ctrl = new $class();
            }
            
            // Pass step parameter if it exists
            if (isset($vars['step'])) {
                call_user_func_array([$ctrl, $method], [$vars['step']]);
            } else {
                call_user_func([$ctrl, $method]);
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo "Wystąpił błąd: " . $e->getMessage();
        }
        break;
}
