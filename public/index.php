<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

// Define the base directory
define('BASE_DIR', dirname(__DIR__));

// Determine base path in case the application is in a subdirectory
$scriptName = dirname($_SERVER['SCRIPT_NAME']);
$baseUrl = $scriptName === '/' ? '' : $scriptName;

// If public/ is part of the path, keep it (we need it for accurate paths)
if (strpos($baseUrl, '/public') === false && $baseUrl !== '') {
    $baseUrl .= '/public';
}

error_log("=== REQUEST STARTED ===");
error_log("Script name: " . $_SERVER['SCRIPT_NAME']);
error_log("Base URL detected: " . $baseUrl);
error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);

// Check if request is for a file in the src or assets directory
$uri = $_SERVER['REQUEST_URI'];
// Remove the base URL from the URI if needed
if (!empty($baseUrl) && strpos($uri, $baseUrl) === 0) {
    $uri = substr($uri, strlen($baseUrl));
    error_log("Adjusted URI after removing base URL: " . $uri);
}

// Handle various potential asset paths - extended patterns to catch common issues
$assetPatterns = [
    '/^\/src\/(.*)$/' => BASE_DIR . '/src/$1',
    '/^\/assets\/(.*)$/' => __DIR__ . '/assets/$1',
    '/^\/\.\.\/\.\.\/assets\/(.*)$/' => __DIR__ . '/assets/$1', // Handle ../../assets/ pattern
    '/^\/\.\.\/assets\/(.*)$/' => __DIR__ . '/assets/$1', // Handle ../assets/ pattern
    '/^\/public\/assets\/(.*)$/' => __DIR__ . '/assets/$1', // Handle /public/assets/ pattern
    '/^\/css\/(.*)$/' => __DIR__ . '/assets/css/$1', // Direct access to CSS files
    '/^\/js\/(.*)$/' => __DIR__ . '/assets/js/$1', // Direct access to JS files
    '/^\/images\/(.*)$/' => __DIR__ . '/assets/images/$1', // Direct access to image files
    // Additional patterns for direct access
    '/^\/?style\.css$/' => __DIR__ . '/assets/css/style.css',
    '/^\/?table\.css$/' => __DIR__ . '/assets/css/table.css',
    '/^\/assets\/css\/style\.css$/' => __DIR__ . '/assets/css/style.css',
    '/^\/assets\/css\/table\.css$/' => __DIR__ . '/assets/css/table.css'
];

// Log the request path for debugging
error_log("Checking URI for assets: " . $uri);

// Test each pattern to see if it matches
foreach ($assetPatterns as $pattern => $replacement) {
    if (preg_match($pattern, $uri, $matches)) {
        $filePath = str_replace('$1', $matches[1] ?? '', $replacement);
        error_log("Looking for file: " . $filePath);
        if (file_exists($filePath)) {
            serveStaticFile($filePath);
            exit;
        } else {
            error_log("File not found: " . $filePath);
        }
    }
}

// Add specific check for style.css since it's frequently causing 404 errors
$styleFiles = [
    __DIR__ . '/assets/css/style.css',
    __DIR__ . '/assets/css/table.css'
];

foreach ($styleFiles as $styleFile) {
    if (strpos($uri, basename($styleFile)) !== false && file_exists($styleFile)) {
        error_log("Serving style file directly: " . $styleFile);
        serveStaticFile($styleFile);
        exit;
    }
}

// Function to serve static files with proper content type
function serveStaticFile($filePath) {
    // Determine content type based on file extension
    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
    $contentType = 'application/octet-stream'; // default
    
    $contentTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'json' => 'application/json',
        'html' => 'text/html',
        'txt' => 'text/plain',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
    ];
    
    if (isset($contentTypes[$extension])) {
        $contentType = $contentTypes[$extension];
    }
    
    // Set cache headers for better performance
    $timestamp = filemtime($filePath);
    $etag = '"' . md5($timestamp . $filePath) . '"';
    
    header('Content-Type: ' . $contentType);
    header('ETag: ' . $etag);
    header('Cache-Control: public, max-age=86400'); // Cache for 1 day
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $timestamp) . ' GMT');
    
    readfile($filePath);
}

use Dell\Faktury\Controllers\AgentController;
use FastRoute\Dispatcher;
use function FastRoute\simpleDispatcher;
use Dell\Faktury\Controllers\HomeController;
use Dell\Faktury\Controllers\WizardController;
use Dell\Faktury\Controllers\TableController;
use Dell\Faktury\Controllers\LoginController;
use Dell\Faktury\Controllers\DatabaseManageController;
use Dell\Faktury\Controllers\InvoicesController;
use Dell\Faktury\Controllers\CommissionController;
use Dell\Faktury\Controllers\PodsumowanieController;
use Dell\Faktury\Controllers\PaymentController;
use Dell\Faktury\Controllers\UserManagementController;

// Sprawdź, czy użytkownik jest zalogowany i przekieruj na stronę logowania jeśli nie
$uri = $_SERVER['REQUEST_URI'];
// Remove the base URL from the URI if needed
if (!empty($baseUrl) && strpos($uri, $baseUrl) === 0) {
    $uri = substr($uri, strlen($baseUrl));
    error_log("Adjusted URI after removing base URL: " . $uri);
}

// Usuwamy parametry z URI przed sprawdzeniem czy to trasa publiczna
$uriWithoutQuery = parse_url($uri, PHP_URL_PATH);
$publicRoutes = ['/login', '/logout']; // Trasy dostępne bez logowania

if (!isset($_SESSION['user']) && !in_array($uriWithoutQuery, $publicRoutes)) {
    error_log("Unauthorized access attempt to {$uri}, redirecting to login");
    header('Location: ' . $baseUrl . '/login?required=1');
    exit;
}

// Sprawdź uprawnienia do stron zastrzeżonych dla superadmina
$superadminRoutes = ['/database', '/zarzadzanie-uzytkownikami']; // Trasy dostępne tylko dla superadminów 
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'superadmin' && 
    (in_array($uriWithoutQuery, $superadminRoutes) || strpos($uriWithoutQuery, '/database/') === 0 || 
     strpos($uriWithoutQuery, '/zarzadzanie-uzytkownikami/') === 0)) {
    error_log("Access denied to {$uri} for role: " . $_SESSION['user_role']);
    header('Location: ' . $baseUrl . '/?access_denied=1');
    exit;
}

error_log("Setting up routes...");
$dispatcher = simpleDispatcher(function(FastRoute\RouteCollector $r) {
    // Home routes – redirects to invoices
    $r->addRoute('GET',    '/',            [HomeController::class, 'index']);
    
    // Invoices routes - CSV import
    $r->addRoute('GET',    '/invoices',       [InvoicesController::class, 'index']);
    $r->addRoute('POST',   '/invoices',       [InvoicesController::class, 'importCsv']);

    // Legacy routes handled by Home controller
    $r->addRoute('POST',   '/upload-file', [HomeController::class, 'uploadFile']);

    // Wizard routes
    $r->addRoute('GET',    '/wizard',      [WizardController::class, 'show']);
    $r->addRoute('POST',   '/wizard',      [WizardController::class, 'store']);

    // Add new route for syncing payment statuses
    $r->addRoute('GET', '/sync-payments', [TableController::class, 'syncPaymentStatuses']);
    $r->addRoute('POST', '/sync-payments-ajax', [TableController::class, 'syncPaymentsAjax']);
    
    // Add new route for updating commission payment status
    $r->addRoute('POST', '/update-commission-status', [TableController::class, 'updateCommissionStatusAjax']);
    
    // Add new route for getting case agents
    $r->addRoute('GET', '/get-case-agents', [TableController::class, 'getCaseAgentsAjax']);
    
    // Add new route for getting commission payments
    $r->addRoute('GET', '/get-commission-payments', [CommissionController::class, 'getCommissionPaymentsForDisplay']);
    
    // Add new route for getting agent commission information
    $r->addRoute('GET', '/get-agent-commission', [AgentController::class, 'getAgentCommission']);
    
    // Add new route for checking agent hierarchy
    $r->addRoute('GET', '/api/agent-hierarchy', [AgentController::class, 'getAgentHierarchy']);
    
    // Case edit routes
    $r->addRoute('GET',    '/case/edit/{id:\d+}', [TableController::class, 'edit']);
    $r->addRoute('POST',   '/case/edit/{id:\d+}', [TableController::class, 'update']);

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

    $r->addRoute('GET',    '/podsumowanie-spraw',       [PodsumowanieController::class, 'index']);
    $r->addRoute('POST',   '/sprawy/{id:\d+}/delete', [PodsumowanieController::class, 'delete']);
    
    // Payment API endpoints
    $r->addRoute('POST',   '/update-payment',       [PaymentController::class, 'updatePayment']);
    $r->addRoute('GET',    '/get-payment-status',   [PaymentController::class, 'getPaymentStatus']);
    
    // User Management Routes
    $r->addRoute('GET',    '/zarzadzanie-uzytkownikami',              [UserManagementController::class, 'index']);
    $r->addRoute('POST',   '/zarzadzanie-uzytkownikami/add',          [UserManagementController::class, 'addUser']);
    $r->addRoute('POST',   '/zarzadzanie-uzytkownikami/change-password', [UserManagementController::class, 'changePassword']);
    $r->addRoute('POST',   '/zarzadzanie-uzytkownikami/change-role',  [UserManagementController::class, 'changeRole']);
    $r->addRoute('POST',   '/zarzadzanie-uzytkownikami/delete',       [UserManagementController::class, 'deleteUser']);
});
error_log("Routes setup complete");

$httpMethod = $_SERVER['REQUEST_METHOD'];
// Make sure we're using the URI without the base URL
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