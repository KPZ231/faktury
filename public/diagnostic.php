<?php
/**
 * This file helps diagnose path and configuration issues
 * Access it via: http://yourdomain.com/public/diagnostic.php
 */

header('Content-Type: text/plain');

echo "=== FAKTURA SYSTEM DIAGNOSTIC ===\n\n";

// Basic PHP information
echo "PHP Version: " . phpversion() . "\n";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "\n\n";

// Request information
echo "=== REQUEST INFO ===\n";
echo "HTTP Host: " . $_SERVER['HTTP_HOST'] . "\n";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "Script Name: " . $_SERVER['SCRIPT_NAME'] . "\n\n";

// Path tests
echo "=== PATH TESTS ===\n";

$basePath = dirname(__DIR__);
echo "Base path: $basePath\n";

$paths = [
    'public' => __DIR__,
    'assets' => __DIR__ . '/assets',
    'css' => __DIR__ . '/assets/css',
    'js' => __DIR__ . '/assets/js',
    'images' => __DIR__ . '/assets/images',
    'src' => $basePath . '/src',
    'views' => $basePath . '/src/Views',
    'vendor' => $basePath . '/vendor',
    'config' => $basePath . '/config'
];

foreach ($paths as $name => $path) {
    echo "$name path: $path - " . (is_dir($path) ? "EXISTS" : "MISSING") . "\n";
}

echo "\n=== CSS FILES ===\n";
$cssFiles = [
    'style.css' => __DIR__ . '/assets/css/style.css',
    'table.css' => __DIR__ . '/assets/css/table.css'
];

foreach ($cssFiles as $name => $path) {
    echo "$name: $path - " . (file_exists($path) ? "EXISTS (" . filesize($path) . " bytes)" : "MISSING") . "\n";
}

echo "\n=== HTACCESS FILES ===\n";
$htaccessFiles = [
    'root' => $basePath . '/.htaccess',
    'public' => __DIR__ . '/.htaccess',
    'src' => $basePath . '/src/.htaccess',
    'assets' => __DIR__ . '/assets/.htaccess'
];

foreach ($htaccessFiles as $name => $path) {
    echo "$name .htaccess: " . (file_exists($path) ? "EXISTS" : "MISSING") . "\n";
}

echo "\n=== MOD_REWRITE TEST ===\n";
if (function_exists('apache_get_modules')) {
    echo "mod_rewrite: " . (in_array('mod_rewrite', apache_get_modules()) ? "ENABLED" : "DISABLED") . "\n";
} else {
    echo "mod_rewrite: Unknown (apache_get_modules function not available)\n";
}

echo "\n=== FILE PERMISSIONS ===\n";
foreach ($paths as $name => $path) {
    if (is_dir($path)) {
        echo "$name permissions: " . decoct(fileperms($path) & 0777) . "\n";
    }
}

echo "\n=== INCLUDE PATH ===\n";
echo get_include_path() . "\n";

echo "\n=== PHP EXTENSIONS ===\n";
$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'json'];
foreach ($requiredExtensions as $ext) {
    echo "$ext: " . (extension_loaded($ext) ? "LOADED" : "MISSING") . "\n";
}

echo "\n=== ENVIRONMENT VARIABLES ===\n";
$envVars = ['DOCUMENT_ROOT', 'SCRIPT_NAME', 'SCRIPT_FILENAME', 'REQUEST_URI', 'PATH_INFO'];
foreach ($envVars as $var) {
    echo "$var: " . (isset($_SERVER[$var]) ? $_SERVER[$var] : "Not set") . "\n";
}

echo "\n=== END OF DIAGNOSTIC ===\n"; 