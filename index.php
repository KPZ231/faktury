<?php
/**
 * Automatically redirect or include public/index.php
 * Modified to work with hostings where document root is in public_html
 */

// Get the current host and URI
$host = $_SERVER['HTTP_HOST'];
$uri = $_SERVER['REQUEST_URI'];
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';

// Get the directory name (which might be empty or /)
$dirName = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

// Define the path to the public directory
$publicPath = $dirName . '/public';

// Check if we're directly accessing the root directory
if ($uri === '/' || $uri === $dirName . '/' || $uri === $dirName) {
    // Redirect to public/ directory
    header("Location: $protocol://$host$publicPath/");
    exit;
}

// Otherwise include the public/index.php file directly
// This allows the application's routing to work transparently
require __DIR__ . '/public/index.php'; 