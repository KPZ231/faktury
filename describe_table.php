<?php
require_once __DIR__ . '/config/database.php';
global $pdo;

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Get table structure
    $table = 'test2';
    $stmt = $pdo->query("DESCRIBE {$table}");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Structure of table '{$table}':\n";
    echo str_repeat('-', 80) . "\n";
    echo sprintf("%-30s %-15s %-8s %-8s %-15s\n", 'Field', 'Type', 'Null', 'Key', 'Default');
    echo str_repeat('-', 80) . "\n";
    
    foreach ($columns as $column) {
        echo sprintf(
            "%-30s %-15s %-8s %-8s %-15s\n",
            $column['Field'],
            $column['Type'],
            $column['Null'],
            $column['Key'],
            $column['Default'] ?? 'NULL'
        );
    }
    
    echo "\n\ncommission invoice columns we need:\n";
    echo "- installment1_commission_invoice\n";
    echo "- installment2_commission_invoice\n";
    echo "- installment3_commission_invoice\n";
    echo "- final_installment_commission_invoice\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
} 