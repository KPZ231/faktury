<?php
// Simple test file to verify PHP is working
echo "<html><body>";
echo "<h1>PHP Test Page</h1>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Test if we can access environment
echo "<h2>Environment Test:</h2>";
if (file_exists(__DIR__ . '/../.env')) {
    echo "<p>✅ .env file exists</p>";
} else {
    echo "<p>❌ .env file not found</p>";
}

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    echo "<p>✅ vendor/autoload.php exists</p>";
} else {
    echo "<p>❌ vendor/autoload.php not found</p>";
}

echo "<h2>Directory Structure:</h2>";
echo "<pre>";
// List parent directory
$parentDir = scandir(__DIR__ . '/..');
echo "Parent directory contains: \n";
print_r($parentDir);
echo "</pre>";

echo "</body></html>"; 