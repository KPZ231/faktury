<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Connect to database
require_once __DIR__ . '/config/database.php';
global $pdo;

try {
    echo "Checking all required commission invoice columns...\n";
    
    // Get existing columns
    $stmt = $pdo->query("DESCRIBE test2");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Define the columns we need
    $requiredColumns = [
        'installment1_commission_invoice',
        'installment2_commission_invoice',
        'installment3_commission_invoice',
        'final_installment_commission_invoice'
    ];
    
    // Check which columns are missing
    $missingColumns = [];
    foreach ($requiredColumns as $column) {
        if (!in_array($column, $columns)) {
            $missingColumns[] = $column;
        }
    }
    
    if (empty($missingColumns)) {
        echo "All required columns exist!\n";
    } else {
        echo "Missing columns: " . implode(', ', $missingColumns) . "\n";
        
        // Add each missing column individually
        foreach ($missingColumns as $column) {
            echo "Adding column: {$column}...\n";
            
            $alterQuery = "ALTER TABLE test2 ADD COLUMN {$column} VARCHAR(255) NULL";
            $result = $pdo->exec($alterQuery);
            
            if ($result !== false) {
                echo "  - Added successfully!\n";
            } else {
                echo "  - Failed to add column.\n";
                print_r($pdo->errorInfo());
            }
        }
    }
    
    // Verify all columns exist now
    echo "\nVerifying all commission invoice columns:\n";
    $stmt = $pdo->query("DESCRIBE test2");
    $updatedColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($requiredColumns as $column) {
        if (in_array($column, $updatedColumns)) {
            echo "✓ {$column} - exists\n";
        } else {
            echo "✗ {$column} - missing\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
} 