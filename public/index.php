<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

error_log("=== REQUEST STARTED ===");
error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);

use Dell\Faktury\Controllers\AgentController;
use FastRoute\Dispatcher;
use function FastRoute\simpleDispatcher;
use Dell\Faktury\Controllers\HomeController;
use Dell\Faktury\Controllers\WizardController;
use Dell\Faktury\Controllers\TableController;
use Dell\Faktury\Controllers\LoginController;
use Dell\Faktury\Controllers\DatabaseManageController;

// Sprawdź, czy użytkownik jest zalogowany i przekieruj na stronę logowania jeśli nie
$uri = $_SERVER['REQUEST_URI'];
// Usuwamy parametry z URI przed sprawdzeniem czy to trasa publiczna
$uriWithoutQuery = parse_url($uri, PHP_URL_PATH);
$publicRoutes = ['/login', '/logout']; // Trasy dostępne bez logowania

if (!isset($_SESSION['user']) && !in_array($uriWithoutQuery, $publicRoutes)) {
    error_log("Unauthorized access attempt to {$uri}, redirecting to login");
    header('Location: /login?required=1');
    exit;
}

error_log("Setting up routes...");
$dispatcher = simpleDispatcher(function(FastRoute\RouteCollector $r) {
    // Home routes – CSV import
    $r->addRoute('GET',    '/',            [HomeController::class, 'index']);
    $r->addRoute('POST',   '/',            [HomeController::class, 'importCsv']);
    $r->addRoute('POST',   '/upload-file', [HomeController::class, 'uploadFile']);

    // Wizard routes
    $r->addRoute('GET',    '/wizard',      [WizardController::class, 'show']);
    $r->addRoute('POST',   '/wizard',      [WizardController::class, 'store']);

    $r->addRoute('GET', '/table',          [TableController::class, 'index']);
    $r->addRoute('GET', '/recalculate', [TableController::class, 'recalculateCase']);

    $r->addRoute('GET', "/agents", [AgentController::class, 'index']);
    $r->addRoute('POST', "/agents", [AgentController::class, 'addAgent']);

    // Database Management routes
    $r->addRoute('GET',    '/database',         [DatabaseManageController::class, 'index']);
    $r->addRoute('POST',   '/database/backup',  [DatabaseManageController::class, 'backupTable']);
    $r->addRoute('POST',   '/database/truncate',[DatabaseManageController::class, 'truncateTable']);
    $r->addRoute('POST',   '/database/drop',    [DatabaseManageController::class, 'dropTable']);
    $r->addRoute('POST',   '/database/connect', [DatabaseManageController::class, 'testConnection']);

    $r->addRoute('GET',    '/login',       [LoginController::class, 'showLoginForm']);
    $r->addRoute('POST',   '/login',       [LoginController::class, 'login']);
    $r->addRoute('GET',    '/logout',      [LoginController::class, 'logout']);
});
error_log("Routes setup complete");

$httpMethod = $_SERVER['REQUEST_METHOD'];
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
    error_log("URI with query string removed: " . $uri);
}
$uri = rawurldecode($uri);
error_log("Processing URI: " . $uri);

error_log("Dispatching route...");
$routeInfo = $dispatcher->dispatch($httpMethod, $uri);
error_log("Route dispatch result: " . $routeInfo[0]);

switch ($routeInfo[0]) {
    case Dispatcher::NOT_FOUND:
        error_log("Route NOT_FOUND: " . $uri);
        http_response_code(404);
        echo "404 – Strona nie znaleziona";
        break;
    case Dispatcher::METHOD_NOT_ALLOWED:
        error_log("Method NOT_ALLOWED: " . $httpMethod . " for " . $uri);
        http_response_code(405);
        echo "405 – Niedozwolona metoda";
        break;
    case Dispatcher::FOUND:
        [$class, $method] = $routeInfo[1];
        $vars = $routeInfo[2];
        error_log("Route FOUND: " . $class . "->" . $method);
        error_log("Route parameters: " . json_encode($vars));
        
        try {
            error_log("Initializing controller: " . $class);
            $ctrl = new $class();
            error_log("Calling method: " . $method);
            call_user_func_array([$ctrl, $method], $vars ?: []);
            error_log("Controller method executed successfully");
        } catch (Exception $e) {
            error_log("ERROR in controller: " . $e->getMessage());
            error_log("Exception stack trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo "Wystąpił błąd: " . $e->getMessage();
        }
        break;
}

error_log("=== REQUEST COMPLETED ===");