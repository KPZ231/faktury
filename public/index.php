<?php
require __DIR__ . '/../vendor/autoload.php';
use FastRoute\Dispatcher;
use function FastRoute\simpleDispatcher;

$dispatcher = simpleDispatcher(function(FastRoute\RouteCollector $r) {
    $r->addRoute('GET', '/', [Dell\Faktury\Controllers\HomeController::class, 'index']);
    $r->addRoute('POST', '/upload-file', [Dell\Faktury\Controllers\HomeController::class, 'upload']);
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
        echo json_encode(['message' => '404 - Nie znaleziono']);
        break;

    case Dispatcher::METHOD_NOT_ALLOWED:
        http_response_code(405);
        echo json_encode(['message' => '405 - Niedozwolona metoda']);
        break;

    case Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];
        $controllerClass = $handler[0];
        $method = $handler[1];
        $controller = new $controllerClass();
        call_user_func_array([$controller, $method], $vars);
        break;
}