<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Controllers/WizardController.php';

use Dell\Faktury\Controllers\WizardController;

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Nieprawidłowa metoda żądania.'
    ]);
    exit;
}

// Get raw POST data
$rawData = file_get_contents('php://input');
error_log("Raw POST data: " . $rawData);

$data = json_decode($rawData, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg());
    $data = $_POST;
}

// Get table structure
$stmt = $pdo->query("DESCRIBE test2");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a new array with all columns initialized
$processedData = [];
foreach ($columns as $column) {
    $fieldName = $column['Field'];
    $fieldType = $column['Type'];
    
    if (isset($data[$fieldName])) {
        if (strpos($fieldType, 'decimal') !== false) {
            $processedData[$fieldName] = $data[$fieldName] === '' ? null : (float)$data[$fieldName];
        } elseif (strpos($fieldType, 'int') !== false) {
            $processedData[$fieldName] = $data[$fieldName] === '' ? null : (int)$data[$fieldName];
        } else {
            $processedData[$fieldName] = $data[$fieldName] === '' ? null : $data[$fieldName];
        }
    } else {
        $processedData[$fieldName] = null;
    }
}

// Debug information
error_log("Original data: " . print_r($data, true));
error_log("Processed data: " . print_r($processedData, true));
error_log("Number of columns in table: " . count($columns));
error_log("Number of fields in processed data: " . count($processedData));

$controller = new WizardController($pdo);
$result = $controller->saveRecord($processedData);

// Add debug information to response
$result['debug'] = [
    'received_data' => $data,
    'sql_error' => $pdo->errorInfo()
];

echo json_encode($result); 