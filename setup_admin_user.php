<?php
// Include database connection
require_once __DIR__ . '/config/database.php';

// Check if the users table exists
try {
    global $pdo;
    
    echo "Checking if users table exists...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "Creating users table...\n";
        // Create users table
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('user', 'admin', 'superadmin') NOT NULL DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        echo "Users table created successfully.\n";
    } else {
        echo "Users table already exists.\n";
    }
    
    // Check if the superadmin user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'six'");
    $stmt->execute();
    $userExists = $stmt->fetch();
    
    if (!$userExists) {
        echo "Creating superadmin user 'six'...\n";
        // Create a clear text password and hash it
        $password = 'superadmin';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert the superadmin user
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'superadmin')");
        $result = $stmt->execute(['six', $hashedPassword]);
        
        if ($result) {
            echo "Superadmin user 'six' created successfully. You can now login with:\n";
            echo "Username: six\n";
            echo "Password: superadmin\n";
        } else {
            echo "Failed to create superadmin user.\n";
        }
    } else {
        echo "Superadmin user 'six' already exists. Updating password...\n";
        
        // Update the existing user's password
        $password = 'superadmin';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'six'");
        $result = $stmt->execute([$hashedPassword]);
        
        if ($result) {
            echo "Password for 'six' updated successfully. You can now login with:\n";
            echo "Username: six\n";
            echo "Password: superadmin\n";
        } else {
            echo "Failed to update password for 'six'.\n";
        }
    }
    
    echo "Setup completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} 