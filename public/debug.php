<?php
// Set maximum error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Output a basic HTML structure
echo "<!DOCTYPE html>
<html>
<head>
    <title>Debug Page</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; }
        .error { color: red; }
        .success { color: green; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow: auto; }
    </style>
</head>
<body>
    <h1>Debug Information</h1>";

// Check PHP version
echo "<h2>PHP Environment</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Current Script: " . $_SERVER['SCRIPT_FILENAME'] . "</p>";

// File structure checks
echo "<h2>File Structure Checks</h2>";
echo "<ul>";

$requiredFiles = [
    __DIR__ . '/../vendor/autoload.php' => 'Vendor autoload file',
    __DIR__ . '/../config/database.php' => 'Database configuration file',
    __DIR__ . '/../.env' => 'Environment configuration file',
    __DIR__ . '/index.php' => 'Main index file'
];

foreach ($requiredFiles as $file => $description) {
    if (file_exists($file)) {
        echo "<li class='success'>✅ $description found ($file)</li>";
    } else {
        echo "<li class='error'>❌ $description NOT found ($file)</li>";
    }
}

echo "</ul>";

// Database connection test
echo "<h2>Database Connection Test</h2>";
try {
    if (file_exists(__DIR__ . '/../.env')) {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        // Load environment variables
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();
        
        // Display database settings (hiding password)
        echo "<p>Database Host: " . $_ENV['DB_HOST'] . "</p>";
        echo "<p>Database Name: " . $_ENV['DB_NAME'] . "</p>";
        echo "<p>Database User: " . $_ENV['DB_USERNAME'] . "</p>";
        
        // Try to connect
        $host = $_ENV['DB_HOST'];
        $dbname = $_ENV['DB_NAME'];
        $username = $_ENV['DB_USERNAME'];
        $password = $_ENV['DB_PASSWORD'];
        
        echo "<p>Attempting to connect to database...</p>";
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<p class='success'>✅ Database connection successful!</p>";
        
        // Check if tables exist
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>Found " . count($tables) . " tables in database:</p>";
        echo "<pre>" . print_r($tables, true) . "</pre>";
    } else {
        echo "<p class='error'>Cannot test database connection because .env file is missing</p>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ Database connection failed: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

// Directory structure
echo "<h2>Directory Structure</h2>";
function listDir($dir, $basePath) {
    $results = scandir($dir);
    echo "<ul>";
    foreach ($results as $result) {
        if ($result === '.' || $result === '..') continue;
        $path = $dir . '/' . $result;
        $relativePath = str_replace($basePath, '', $path);
        if (is_dir($path)) {
            echo "<li>📁 $relativePath</li>";
        } else {
            echo "<li>📄 $relativePath (" . filesize($path) . " bytes)</li>";
        }
    }
    echo "</ul>";
}

echo "<p>Root directory:</p>";
listDir(__DIR__ . '/../', __DIR__ . '/../');

echo "<p>Public directory:</p>";
listDir(__DIR__, __DIR__);

echo "</body></html>"; 