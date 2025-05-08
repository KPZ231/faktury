<?php
// This script adds the required invoice fields to the test2 table

// Enable error display
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
echo "Starting script...\n";

require_once __DIR__ . '/config/database.php';
global $pdo;

try {
    echo "Adding invoice fields to test2 table...\n";
    
    // Check if fields already exist
    $checkQuery = "SHOW COLUMNS FROM test2 LIKE 'installment1_paid_invoice'";
    $stmt = $pdo->query($checkQuery);
    
    if ($stmt->rowCount() > 0) {
        echo "Fields already exist. No changes needed.\n";
    } else {
        // Add the fields
        $alterQuery = "
            ALTER TABLE test2 
            ADD COLUMN installment1_paid_invoice VARCHAR(50) NULL,
            ADD COLUMN installment2_paid_invoice VARCHAR(50) NULL, 
            ADD COLUMN installment3_paid_invoice VARCHAR(50) NULL, 
            ADD COLUMN final_installment_paid_invoice VARCHAR(50) NULL
        ";
        
        $pdo->exec($alterQuery);
        echo "Successfully added invoice fields to test2 table.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "Script completed.\n";
echo "</pre>"; 