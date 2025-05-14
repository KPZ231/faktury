<?php

namespace Dell\Faktury\Controllers;

use PDO;
use PDOException;

class PaymentController {
    private $pdo;

    public function __construct() {
        // Initialize database connection
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        $this->pdo = $pdo;

        // Ensure the commission_payments table exists
        $this->ensureTableExists();
    }

    /**
     * Ensure necessary table exists
     */
    private function ensureTableExists(): void {
        try {
            // Check if commission_payments table exists
            $tableCheck = $this->pdo->query("SHOW TABLES LIKE 'commission_payments'");
            if ($tableCheck->rowCount() === 0) {
                // Table doesn't exist, create it to match the expected structure
                $createTable = "CREATE TABLE IF NOT EXISTS commission_payments (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    case_id INT(11) NOT NULL,
                    agent_id VARCHAR(255) NOT NULL,
                    installment_number INT(11) NOT NULL,
                    amount DECIMAL(10,2) DEFAULT 0.00,
                    status TINYINT(1) DEFAULT 0,
                    invoice_number VARCHAR(255) DEFAULT NULL,
                    created_at DATETIME DEFAULT NULL,
                    updated_at DATETIME DEFAULT NULL,
                    PRIMARY KEY (id),
                    KEY idx_case_agent_installment (case_id, agent_id, installment_number)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                
                $this->pdo->exec($createTable);
                error_log("Created commission_payments table");
            } else {
                // Table exists, make sure it has the amount column
                try {
                    $columnCheck = $this->pdo->query("SHOW COLUMNS FROM commission_payments LIKE 'amount'");
                    if ($columnCheck->rowCount() === 0) {
                        // Add the amount column if it doesn't exist
                        $this->pdo->exec("ALTER TABLE commission_payments ADD COLUMN amount DECIMAL(10,2) DEFAULT 0.00 AFTER installment_number");
                        error_log("Added amount column to commission_payments table");
                    }
                } catch (PDOException $e) {
                    error_log("Error checking/adding column: " . $e->getMessage());
                }
            }
        } catch (PDOException $e) {
            error_log("Error checking/creating table: " . $e->getMessage());
        }
    }

    /**
     * Get payment status information
     * Endpoint: /get-payment-status
     */
    public function getPaymentStatus(): void {
        try {
            // Validate input parameters
            $caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
            $installmentNumber = isset($_GET['installment_number']) ? (int)$_GET['installment_number'] : 0;
            // Don't cast agent_id to int since it's VARCHAR in the database
            $agentId = isset($_GET['agent_id']) ? $_GET['agent_id'] : '';
            
            error_log("getPaymentStatus request: case_id=$caseId, installment_number=$installmentNumber, agent_id=$agentId");
            
            if (!$caseId || !$installmentNumber || $agentId === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required parameters']);
                return;
            }
            
            // Query the database for payment status
            $query = "SELECT status, invoice_number, created_at";
            
            // Only include amount if the column exists
            $columnCheck = $this->pdo->query("SHOW COLUMNS FROM commission_payments LIKE 'amount'");
            if ($columnCheck->rowCount() > 0) {
                $query .= ", amount";
            }
            
            $query .= " FROM commission_payments 
                      WHERE case_id = ? AND installment_number = ? AND agent_id = ?
                      LIMIT 1";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$caseId, $installmentNumber, $agentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Return JSON response
            header('Content-Type: application/json');
            echo json_encode($payment ?: null);
            
        } catch (PDOException $e) {
            error_log("DB Error in getPaymentStatus: " . $e->getMessage());
            error_log("SQL State: " . $e->getCode());
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        } catch (\Exception $e) {
            error_log("General Error in getPaymentStatus: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Update payment information
     * Endpoint: /update-payment
     */
    public function updatePayment(): void {
        try {
            // Get JSON data from the request
            $jsonData = file_get_contents('php://input');
            error_log("updatePayment request data: " . $jsonData);
            
            $data = json_decode($jsonData, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode error: " . json_last_error_msg());
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
                return;
            }
            
            // Log received data
            error_log("Received payment data: " . print_r($data, true));
            
            // Validate required fields
            if (!isset($data['case_id']) || !isset($data['agent_id']) || !isset($data['installment_number'])) {
                error_log("Missing required fields in payment data");
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                return;
            }

            // Validate installment number (should be 1-6)
            $installmentNumber = intval($data['installment_number']);
            if ($installmentNumber < 1 || $installmentNumber > 6) {
                error_log("Invalid installment number: " . $installmentNumber);
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid installment number. Must be between 1 and 6.']);
                return;
            }
            
            error_log("Processing payment update for case_id: " . $data['case_id'] . ", agent_id: " . $data['agent_id'] . ", installment: " . $installmentNumber);
            
            // Check if record exists
            $checkQuery = "SELECT id FROM commission_payments 
                          WHERE case_id = ? AND agent_id = ? AND installment_number = ?";
            $checkStmt = $this->pdo->prepare($checkQuery);
            $checkStmt->execute([$data['case_id'], $data['agent_id'], $installmentNumber]);
            $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingRecord) {
                // Update existing record
                $updateQuery = "UPDATE commission_payments SET 
                               status = ?,
                               invoice_number = ?,
                               updated_at = NOW()";
                
                $params = [
                    $data['status'],
                    $data['invoice_number'] ?? null
                ];
                
                if (isset($data['amount'])) {
                    $updateQuery .= ", amount = ?";
                    $params[] = $data['amount'];
                }
                
                $updateQuery .= " WHERE id = ?";
                $params[] = $existingRecord['id'];
                
                error_log("Executing update query: " . $updateQuery);
                error_log("With params: " . print_r($params, true));
                
                $updateStmt = $this->pdo->prepare($updateQuery);
                $updateResult = $updateStmt->execute($params);
                
                error_log("Updated record id: " . $existingRecord['id'] . ", result: " . ($updateResult ? 'success' : 'failed'));
                
            } else {
                // Insert new record
                $insertQuery = "INSERT INTO commission_payments 
                               (case_id, agent_id, installment_number, status, invoice_number, created_at, updated_at) 
                               VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
                
                $params = [
                    $data['case_id'],
                    $data['agent_id'],
                    $installmentNumber,
                    $data['status'],
                    $data['invoice_number'] ?? null
                ];
                
                if (isset($data['amount'])) {
                    $insertQuery = "INSERT INTO commission_payments 
                                   (case_id, agent_id, installment_number, status, invoice_number, amount, created_at, updated_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
                    $params[] = $data['amount'];
                }
                
                error_log("Executing insert query: " . $insertQuery);
                error_log("With params: " . print_r($params, true));
                
                $insertStmt = $this->pdo->prepare($insertQuery);
                $insertResult = $insertStmt->execute($params);
                
                error_log("Inserted new record, result: " . ($insertResult ? 'success' : 'failed'));
            }
            
            // Update the corresponding commission_paid field in test2 table
            $columnName = "installment" . $installmentNumber . "_commission_paid";
            $invoiceColumnName = "installment" . $installmentNumber . "_commission_invoice";
            
            $updateTest2Query = "UPDATE test2 SET 
                                {$columnName} = ?,
                                {$invoiceColumnName} = ?
                                WHERE id = ?";
            
            $updateTest2Stmt = $this->pdo->prepare($updateTest2Query);
            $updateTest2Result = $updateTest2Stmt->execute([
                $data['status'],
                $data['invoice_number'] ?? null,
                $data['case_id']
            ]);
            
            error_log("Updated test2 table, result: " . ($updateTest2Result ? 'success' : 'failed'));
            
            // Return success response
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            
        } catch (PDOException $e) {
            error_log("DB Error in updatePayment: " . $e->getMessage());
            error_log("SQL State: " . $e->getCode());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        } catch (\Exception $e) {
            error_log("General Error in updatePayment: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
        }
    }
} 