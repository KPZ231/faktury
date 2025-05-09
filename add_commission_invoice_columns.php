<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Connect to database
require_once __DIR__ . '/config/database.php';
global $pdo;

try {
    echo "Starting column addition process...\n";
    
    // Check if columns exist
    $checkQuery = "SHOW COLUMNS FROM test2 LIKE 'installment1_commission_invoice'";
    $stmt = $pdo->query($checkQuery);
    $exists = $stmt->rowCount() > 0;
    
    if ($exists) {
        echo "Commission invoice columns already exist.\n";
    } else {
        echo "Adding commission invoice columns...\n";
        
        // Add the columns
        $alterQuery = "ALTER TABLE test2 
            ADD COLUMN installment1_commission_invoice VARCHAR(255) NULL,
            ADD COLUMN installment2_commission_invoice VARCHAR(255) NULL,
            ADD COLUMN installment3_commission_invoice VARCHAR(255) NULL,
            ADD COLUMN final_installment_commission_invoice VARCHAR(255) NULL";
        
        $result = $pdo->exec($alterQuery);
        
        if ($result !== false) {
            echo "Columns added successfully!\n";
        } else {
            echo "Failed to add columns.\n";
            print_r($pdo->errorInfo());
        }
    }
    
    // Verify columns exist
    echo "\nVerifying columns in the test2 table:\n";
    $columns = $pdo->query("DESCRIBE test2")->fetchAll(PDO::FETCH_COLUMN);
    echo implode("\n", $columns);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
} 