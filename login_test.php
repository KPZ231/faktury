<?php
session_start();
require_once __DIR__ . '/config/database.php';

global $pdo;

// Setup part
$username = 'six';
$password = 'superadmin';

echo "Checking if users table exists...<br>";
$stmt = $pdo->query("SHOW TABLES LIKE 'users'");
$tableExists = $stmt->rowCount() > 0;

if (!$tableExists) {
    echo "Creating users table...<br>";
    // Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('user', 'admin', 'superadmin') NOT NULL DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "Users table created successfully.<br>";
} else {
    echo "Users table already exists.<br>";
}

// Check if the superadmin user already exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "Creating superadmin user 'six'...<br>";
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'superadmin')");
    $result = $stmt->execute([$username, $hashedPassword]);
    
    if ($result) {
        echo "Superadmin user 'six' created successfully.<br>";
    } else {
        echo "Failed to create superadmin user.<br>";
    }
} else {
    echo "User found in database:<br>";
    echo "ID: " . $user['id'] . "<br>";
    echo "Username: " . $user['username'] . "<br>";
    echo "Role: " . $user['role'] . "<br>";
    
    // Test password verification
    if (password_verify($password, $user['password'])) {
        echo "Password is correct!<br>";
    } else {
        echo "Password is incorrect. Updating password...<br>";
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
        $result = $stmt->execute([$hashedPassword, $username]);
        
        if ($result) {
            echo "Password updated successfully.<br>";
        } else {
            echo "Failed to update password.<br>";
        }
    }
}

// Test login and session
echo "<h2>Testing login...</h2>";

// Clear existing session
$_SESSION = array();

// Try to login
$stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($password, $user['password'])) {
    echo "Login successful!<br>";
    $_SESSION['user'] = $user['username'];
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_time'] = time();
    
    echo "<h2>Session data:</h2>";
    echo "user: " . $_SESSION['user'] . "<br>";
    echo "user_id: " . $_SESSION['user_id'] . "<br>";
    echo "user_role: " . $_SESSION['user_role'] . "<br>";
    echo "login_time: " . $_SESSION['login_time'] . "<br>";
} else {
    echo "Login failed!<br>";
}

echo "<h2>Navigation bar test:</h2>";
echo "This will show if the navbar would display the user management link with the current session:<br>";

if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin') {
    echo "YES - User management link would be visible (user role is superadmin)<br>";
} else {
    echo "NO - User management link would NOT be visible (user role is not superadmin)<br>";
    echo "Current value of \$_SESSION['user_role']: " . (isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'not set') . "<br>";
}

echo "<p><a href='/login'>Go to login page</a></p>";
echo "<p><a href='/'>Go to home page</a></p>"; 