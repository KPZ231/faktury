<?php
// Load database configuration
require_once __DIR__ . '/config/database.php';

// Add commission payment tracking columns to test2 table
try {
    global $pdo;
    
    // Check if columns already exist
    $columnsQuery = "SHOW COLUMNS FROM test2 LIKE 'installment1_commission_paid'";
    $stmt = $pdo->query($columnsQuery);
    
    if ($stmt->rowCount() == 0) {
        // Columns don't exist, add them
        $alterQuery = "ALTER TABLE test2 
            ADD COLUMN installment1_commission_paid TINYINT(1) DEFAULT 0,
            ADD COLUMN installment2_commission_paid TINYINT(1) DEFAULT 0,
            ADD COLUMN installment3_commission_paid TINYINT(1) DEFAULT 0,
            ADD COLUMN final_installment_commission_paid TINYINT(1) DEFAULT 0";
        
        $pdo->exec($alterQuery);
        echo "Successfully added commission payment tracking columns to test2 table.";
    } else {
        echo "Commission payment columns already exist in the test2 table.";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
} 