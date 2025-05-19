<?php
/**
 * This script creates the missing .htaccess files
 * Upload and run this on your server, then delete it
 */

// Set content type to plain text for easier reading
header('Content-Type: text/plain');

// Define the content for src/.htaccess
$srcHtaccess = <<<EOT
# Prevent direct access to PHP files
<FilesMatch "\.php$">
    # Apache 2.2
    <IfModule !mod_authz_core.c>
        Order Deny,Allow
        Deny from all
    </IfModule>
    
    # Apache 2.4+
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
</FilesMatch>

# Allow access to specific asset files
<FilesMatch "\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)$">
    # Apache 2.2
    <IfModule !mod_authz_core.c>
        Order Allow,Deny
        Allow from all
    </IfModule>
    
    # Apache 2.4+
    <IfModule mod_authz_core.c>
        Require all granted
    </IfModule>
</FilesMatch>

# Prevent browsing of directories
Options -Indexes
EOT;

// Path to the src directory (relative to this script)
$srcPath = __DIR__ . '/src';

// Path to the .htaccess file in the src directory
$srcHtaccessFile = $srcPath . '/.htaccess';

echo "Checking if src/.htaccess exists... ";
if (file_exists($srcHtaccessFile)) {
    echo "YES\n";
    echo "Content:\n";
    echo file_get_contents($srcHtaccessFile);
} else {
    echo "NO\n";
    echo "Trying to create src/.htaccess...\n";
    
    // Check if src directory exists
    if (!is_dir($srcPath)) {
        echo "ERROR: src directory does not exist at: $srcPath\n";
    } else {
        // Try to create the .htaccess file
        $result = file_put_contents($srcHtaccessFile, $srcHtaccess);
        if ($result !== false) {
            echo "SUCCESS: src/.htaccess created successfully.\n";
        } else {
            echo "ERROR: Failed to create src/.htaccess. Check permissions.\n";
            
            // Try to get server configuration info
            echo "\nServer information:\n";
            echo "PHP Version: " . phpversion() . "\n";
            echo "User: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'unknown') . "\n";
            echo "Script path: " . __FILE__ . "\n";
            echo "src path: " . $srcPath . "\n";
            
            // Check if we can create a test file
            $testFile = __DIR__ . '/test_permissions.txt';
            $testResult = file_put_contents($testFile, 'test');
            echo "Test file creation: " . ($testResult !== false ? "SUCCESS" : "FAILED") . "\n";
            if ($testResult !== false) {
                unlink($testFile); // Clean up
            }
        }
    }
}

echo "\nDone. Please delete this file after use for security reasons.\n"; 