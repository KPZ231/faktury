<?php

namespace Dell\Faktury\Controllers;

use PDO;
use PDOException;

class CommissionController
{
    private $pdo;

    public function __construct()
    {
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * Update commission payment status
     * 
     * @return array JSON response
     */
    public function updateCommissionStatus()
    {
        header('Content-Type: application/json');
        
        try {
            // Get request body
            $input_raw = file_get_contents('php://input');
            $input = json_decode($input_raw, true);
            
            error_log("updateCommissionStatus received: " . $input_raw);
            
            if (!isset($input['case_id']) || !isset($input['installment_number']) || !isset($input['status'])) {
                throw new \Exception("Missing required parameters: case_id, installment_number, status");
            }
            
            $caseId = (int)$input['case_id'];
            $installmentNumber = $input['installment_number'];
            $status = (int)$input['status'];
            $invoiceNumber = isset($input['invoice_number']) ? $input['invoice_number'] : '';
            $agentId = isset($input['agent_id']) ? $input['agent_id'] : null;
            
            error_log("Processing commission update: case=$caseId, installment=$installmentNumber, status=$status, invoice=$invoiceNumber, agent=$agentId");
            
            // Validate installment number
            if (!in_array($installmentNumber, ['1', '2', '3', '4', 1, 2, 3, 4])) {
                throw new \Exception("Invalid installment number. Must be 1, 2, 3, or 4.");
            }
            
            // Ensure numeric installment number
            $installmentNumber = (int)$installmentNumber;
            
            // Map installment number to database columns
            $columnMap = [
                '1' => 'installment1_commission_paid',
                '2' => 'installment2_commission_paid',
                '3' => 'installment3_commission_paid',
                '4' => 'final_installment_commission_paid',
                1 => 'installment1_commission_paid',
                2 => 'installment2_commission_paid',
                3 => 'installment3_commission_paid',
                4 => 'final_installment_commission_paid'
            ];
            
            // Map for invoice column
            $invoiceColumnMap = [
                '1' => 'installment1_commission_invoice',
                '2' => 'installment2_commission_invoice',
                '3' => 'installment3_commission_invoice',
                '4' => 'final_installment_commission_invoice',
                1 => 'installment1_commission_invoice',
                2 => 'installment2_commission_invoice',
                3 => 'installment3_commission_invoice',
                4 => 'final_installment_commission_invoice'
            ];
            
            // Ensure columns exist
            $this->ensureCommissionColumnsExist();
            
            // If we're setting status=1 but no agent_id was provided, try to get it from the case
            if ($status == 1 && empty($agentId)) {
                error_log("No agent ID provided, trying to find one from the case");
                $agentQuery = "SELECT agent_id FROM sprawa_agent WHERE sprawa_id = :case_id LIMIT 1";
                $agentStmt = $this->pdo->prepare($agentQuery);
                $agentStmt->execute([':case_id' => $caseId]);
                $fetchedAgentId = $agentStmt->fetchColumn();
                
                if ($fetchedAgentId) {
                    $agentId = $fetchedAgentId;
                    error_log("Found agent ID: $agentId");
                } else {
                    // Default to 'Kuba' if no agent found
                    $agentId = 'Kuba';
                    error_log("No agent found, defaulting to: $agentId");
                }
            }
            
            try {
                // Start transaction to ensure data consistency
                $this->pdo->beginTransaction();
                
                // Delete any existing commission records for this agent, case and installment
                // if we're setting status=0
                if ($status == 0) {
                    $deleteSql = "DELETE FROM commission_payments 
                                WHERE case_id = :case_id 
                                AND installment_number = :installment_number";
                    
                    if ($agentId) {
                        $deleteSql .= " AND agent_id = :agent_id";
                    }
                    
                    $deleteStmt = $this->pdo->prepare($deleteSql);
                    $deleteParams = [
                        ':case_id' => $caseId,
                        ':installment_number' => $installmentNumber
                    ];
                    
                    if ($agentId) {
                        $deleteParams[':agent_id'] = $agentId;
                    }
                    
                    $deleteResult = $deleteStmt->execute($deleteParams);
                    error_log("Deleted existing commission records: " . ($deleteResult ? "success" : "failed") . 
                            ", rows affected: " . $deleteStmt->rowCount() . 
                            ", for case $caseId, installment $installmentNumber" . 
                            ($agentId ? ", agent $agentId" : ""));
                }
                
                // Store commission payment in the commission_payments table if status=1
                if ($status == 1) {
                    // First check if a record already exists for this case, installment, and agent
                    $checkSql = "SELECT id FROM commission_payments 
                                WHERE case_id = :case_id 
                                AND installment_number = :installment_number
                                AND agent_id = :agent_id";
                    
                    $checkStmt = $this->pdo->prepare($checkSql);
                    $checkParams = [
                        ':case_id' => $caseId,
                        ':installment_number' => $installmentNumber,
                        ':agent_id' => $agentId
                    ];
                    
                    $checkStmt->execute($checkParams);
                    $existingRecord = $checkStmt->fetch(\PDO::FETCH_ASSOC);
                    
                    if ($existingRecord) {
                        // Update existing record
                        $updateSql = "UPDATE commission_payments 
                                    SET invoice_number = :invoice_number, 
                                        status = :status, 
                                        updated_at = NOW() 
                                    WHERE id = :id";
                        
                        $updateStmt = $this->pdo->prepare($updateSql);
                        $updateParams = [
                            ':invoice_number' => $invoiceNumber,
                            ':status' => $status,
                            ':id' => $existingRecord['id']
                        ];
                        
                        $updateResult = $updateStmt->execute($updateParams);
                        error_log("Updated existing commission payment record: " . ($updateResult ? "success" : "failed") . 
                                ", for case $caseId, installment $installmentNumber, agent $agentId, invoice $invoiceNumber");
                    } else {
                        // Insert new record
                        $sql = "INSERT INTO commission_payments 
                                (case_id, installment_number, agent_id, invoice_number, status, created_at) 
                                VALUES (:case_id, :installment_number, :agent_id, :invoice_number, :status, NOW())";
                        
                        $stmt = $this->pdo->prepare($sql);
                        $params = [
                            ':case_id' => $caseId,
                            ':installment_number' => $installmentNumber,
                            ':agent_id' => $agentId,
                            ':invoice_number' => $invoiceNumber,
                            ':status' => $status
                        ];
                        
                        $insertResult = $stmt->execute($params);
                        $lastInsertId = $this->pdo->lastInsertId();
                        
                        error_log("Inserted new commission payment record: " . ($insertResult ? "success" : "failed") .
                                ", ID: $lastInsertId" .
                                ", case=$caseId, installment=$installmentNumber, agent=$agentId, invoice=$invoiceNumber");
                    }
                }
                
                // Also update the main case table to mark commission as paid or unpaid
                $updateSql = "UPDATE test2 SET {$columnMap[$installmentNumber]} = :status";
                
                // Only update invoice number if we have one and setting status=1
                if ($status == 1 && !empty($invoiceNumber)) {
                    $updateSql .= ", {$invoiceColumnMap[$installmentNumber]} = :invoice_number";
                }
                
                $updateSql .= " WHERE id = :case_id";
                
                $updateStmt = $this->pdo->prepare($updateSql);
                $updateParams = [
                    ':status' => $status,
                    ':case_id' => $caseId
                ];
                
                if ($status == 1 && !empty($invoiceNumber)) {
                    $updateParams[':invoice_number'] = $invoiceNumber;
                }
                
                $testUpdateResult = $updateStmt->execute($updateParams);
                error_log("Updated test2 table: " . ($testUpdateResult ? "success" : "failed") . 
                        ", rows affected: " . $updateStmt->rowCount() . 
                        ", case=$caseId, column={$columnMap[$installmentNumber]}, status=$status");
                
                // Commit the transaction
                $this->pdo->commit();
                
                // Return success response
                $response = [
                    'success' => true,
                    'message' => 'Commission status updated successfully',
                    'commission_id' => $status == 1 ? $this->pdo->lastInsertId() : null,
                    'case_id' => $caseId,
                    'installment_number' => $installmentNumber,
                    'status' => $status,
                    'agent_id' => $agentId,
                    'invoice_number' => $invoiceNumber
                ];
                
                error_log("updateCommissionStatus returning success response: " . json_encode($response));
                echo json_encode($response);
                
            } catch (\PDOException $dbError) {
                // Rollback the transaction in case of database errors
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                
                $errorMsg = "Database error updating commission status: " . $dbError->getMessage();
                error_log($errorMsg);
                error_log("Stack trace: " . $dbError->getTraceAsString());
                
                echo json_encode([
                    'success' => false,
                    'message' => $errorMsg
                ]);
            }
            
        } catch (\Exception $e) {
            $errorMsg = "Error updating commission status: " . $e->getMessage();
            error_log($errorMsg);
            error_log("Stack trace: " . $e->getTraceAsString());
            
            echo json_encode([
                'success' => false,
                'message' => $errorMsg
            ]);
        }
    }

    /**
     * Ensure commission-related database columns exist
     */
    private function ensureCommissionColumnsExist()
    {
        try {
            // Check if the commission_payments table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'commission_payments'");
            $tableExists = $stmt->rowCount() > 0;
            
            if (!$tableExists) {
                error_log("Creating commission_payments table");
                // Create the commission_payments table
                $sql = "CREATE TABLE commission_payments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    case_id INT NOT NULL,
                    installment_number INT NOT NULL,
                    agent_id VARCHAR(255) NOT NULL,
                    invoice_number VARCHAR(255),
                    status TINYINT DEFAULT 0,
                    created_at DATETIME,
                    updated_at DATETIME,
                    INDEX (case_id),
                    INDEX (agent_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                
                $this->pdo->exec($sql);
                error_log("commission_payments table created");
            }
            
            // Check if commission columns exist in test2 table
            $stmt = $this->pdo->query("SHOW COLUMNS FROM test2 LIKE 'installment1_commission_paid'");
            $columnExists = $stmt->rowCount() > 0;
            
            if (!$columnExists) {
                error_log("Adding commission columns to test2 table");
                // Add commission paid columns
                $sql = "ALTER TABLE test2 
                    ADD COLUMN installment1_commission_paid TINYINT DEFAULT 0,
                    ADD COLUMN installment2_commission_paid TINYINT DEFAULT 0,
                    ADD COLUMN installment3_commission_paid TINYINT DEFAULT 0,
                    ADD COLUMN final_installment_commission_paid TINYINT DEFAULT 0,
                    ADD COLUMN installment1_commission_invoice VARCHAR(255),
                    ADD COLUMN installment2_commission_invoice VARCHAR(255),
                    ADD COLUMN installment3_commission_invoice VARCHAR(255),
                    ADD COLUMN final_installment_commission_invoice VARCHAR(255)";
                
                $this->pdo->exec($sql);
                error_log("Commission columns added to test2 table");
            }
            
            // Check if we need to migrate existing data
            $this->migrateExistingCommissionData();
        } catch (\PDOException $e) {
            // Log error, but continue
            error_log("Error ensuring commission columns: " . $e->getMessage());
        }
    }
    
    /**
     * Migrate existing commission data from old format to new table
     */
    private function migrateExistingCommissionData()
    {
        try {
            // Check if we need to migrate data (if there's data in test2 but not in commission_payments)
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM test2 WHERE installment1_commission_paid = 1 OR installment2_commission_paid = 1 OR installment3_commission_paid = 1 OR final_installment_commission_paid = 1");
            $test2Count = $stmt->fetchColumn();
            
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM commission_payments");
            $paymentsCount = $stmt->fetchColumn();
            
            if ($test2Count > 0 && $paymentsCount == 0) {
                error_log("Migrating existing commission data to commission_payments table");
                
                // Get all cases with commission payments
                $cases = $this->pdo->query("
                    SELECT id, 
                           installment1_commission_paid, installment1_commission_invoice,
                           installment2_commission_paid, installment2_commission_invoice,
                           installment3_commission_paid, installment3_commission_invoice,
                           final_installment_commission_paid, final_installment_commission_invoice
                    FROM test2 
                    WHERE installment1_commission_paid = 1 
                       OR installment2_commission_paid = 1 
                       OR installment3_commission_paid = 1 
                       OR final_installment_commission_paid = 1
                ")->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($cases as $case) {
                    // For each case, add payments to commission_payments table
                    for ($i = 1; $i <= 4; $i++) {
                        $paidField = $i == 4 ? 'final_installment_commission_paid' : "installment{$i}_commission_paid";
                        $invoiceField = $i == 4 ? 'final_installment_commission_invoice' : "installment{$i}_commission_invoice";
                        
                        if ($case[$paidField] == 1 && !empty($case[$invoiceField])) {
                            // Get agent ID from case_agents table or default to 'Kuba'
                            $stmt = $this->pdo->prepare("SELECT agent_id FROM sprawa_agent WHERE sprawa_id = ? LIMIT 1");
                            $stmt->execute([$case['id']]);
                            $agentId = $stmt->fetchColumn();
                            
                            if (!$agentId) {
                                $agentId = 'Kuba'; // Default agent if none found
                            }
                            
                            // Insert payment record
                            $sql = "INSERT INTO commission_payments 
                                    (case_id, installment_number, agent_id, invoice_number, status, created_at) 
                                    VALUES (?, ?, ?, ?, 1, NOW())";
                            $this->pdo->prepare($sql)->execute([
                                $case['id'],
                                $i == 4 ? 4 : $i, // 4 for final installment
                                $agentId,
                                $case[$invoiceField]
                            ]);
                            
                            error_log("Migrated payment for case {$case['id']}, installment {$i}");
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Error migrating commission data: " . $e->getMessage());
        }
    }
    
    /**
     * Get commission payments for a case and installment
     * 
     * @param int $caseId
     * @param int $installmentNumber
     * @return array
     */
    public function getCommissionPayments($caseId, $installmentNumber)
    {
        try {
            error_log("Getting commission payments for case $caseId, installment $installmentNumber");
            
            // First make sure the table exists
            $this->ensureCommissionColumnsExist();
            
            // Build query with special handling for 'Kuba'/'Jakub' agent IDs 
            $sql = "SELECT cp.*, 
                           COALESCE(a.imie, '') as imie, 
                           COALESCE(a.nazwisko, '') as nazwisko, 
                           cp.agent_id
                    FROM commission_payments cp
                    LEFT JOIN agenci a ON (cp.agent_id = CAST(a.agent_id AS CHAR))
                    WHERE cp.case_id = :case_id AND cp.installment_number = :installment_number
                    ORDER BY cp.created_at DESC";
                    
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':case_id' => $caseId,
                ':installment_number' => $installmentNumber
            ]);
            
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Found " . count($payments) . " commission payments for case $caseId, installment $installmentNumber");
            
            // Log each payment for debugging
            foreach ($payments as $index => $payment) {
                error_log("Payment $index: agent_id=" . $payment['agent_id'] . 
                          ", invoice=" . $payment['invoice_number'] . 
                          ", imie=" . ($payment['imie'] ?? 'null') . 
                          ", nazwisko=" . ($payment['nazwisko'] ?? 'null'));
            }
            
            // If no payments found, check if there's legacy data in the test2 table
            if (count($payments) === 0) {
                error_log("No payments found in commission_payments table, checking for legacy data");
                $legacyPayments = $this->getLegacyCommissionPayments($caseId, $installmentNumber);
                if (!empty($legacyPayments)) {
                    error_log("Found " . count($legacyPayments) . " legacy payments");
                    $payments = $legacyPayments;
                }
            }
            
            return [
                'success' => true,
                'payments' => $payments
            ];
        } catch (\Exception $e) {
            error_log("Error getting commission payments: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get legacy commission payments from test2 table
     * 
     * @param int $caseId
     * @param int $installmentNumber
     * @return array
     */
    private function getLegacyCommissionPayments($caseId, $installmentNumber)
    {
        try {
            // Map installment number to column name
            $columnMap = [
                1 => 'installment1_commission_invoice',
                2 => 'installment2_commission_invoice',
                3 => 'installment3_commission_invoice',
                4 => 'final_installment_commission_invoice'
            ];
            
            $statusColumnMap = [
                1 => 'installment1_commission_paid',
                2 => 'installment2_commission_paid',
                3 => 'installment3_commission_paid',
                4 => 'final_installment_commission_paid'
            ];
            
            $column = $columnMap[$installmentNumber] ?? null;
            $statusColumn = $statusColumnMap[$installmentNumber] ?? null;
            
            if (!$column || !$statusColumn) {
                return [];
            }
            
            // Query test2 table for legacy data
            $sql = "SELECT id, $column as invoice_number, $statusColumn as status, 
                           '' as agent_id, 'Legacy' as imie, 'Invoice' as nazwisko
                    FROM test2 
                    WHERE id = :case_id AND $statusColumn = 1 AND $column IS NOT NULL AND $column != ''";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':case_id' => $caseId]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format results to match the structure of commission_payments
            $payments = [];
            foreach ($result as $row) {
                $payments[] = [
                    'id' => null,
                    'case_id' => $row['id'],
                    'installment_number' => $installmentNumber,
                    'agent_id' => 'legacy',
                    'invoice_number' => $row['invoice_number'],
                    'status' => $row['status'],
                    'created_at' => null,
                    'updated_at' => null,
                    'imie' => $row['imie'],
                    'nazwisko' => $row['nazwisko']
                ];
            }
            
            return $payments;
        } catch (\Exception $e) {
            error_log("Error getting legacy payments: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get commission payments for display in the frontend
     * This API endpoint returns nicely formatted commission payments
     */
    public function getCommissionPaymentsForDisplay()
    {
        header('Content-Type: application/json');
        
        try {
            // Get request parameters
            $caseId = isset($_GET['case_id']) ? intval($_GET['case_id']) : null;
            $installmentNumber = isset($_GET['installment_number']) ? intval($_GET['installment_number']) : null;
            
            if (!$caseId || !$installmentNumber) {
                throw new \Exception("Missing case_id or installment_number parameter");
            }
            
            error_log("Getting commission payments for display: case=$caseId, installment=$installmentNumber");
            
            // Get payments from database
            $result = $this->getCommissionPayments($caseId, $installmentNumber);
            
            if (!$result['success']) {
                throw new \Exception($result['message'] ?? "Error retrieving commission payments");
            }
            
            $payments = $result['payments'] ?? [];
            
            // Process each payment for display
            $formattedPayments = [];
            foreach ($payments as $payment) {
                // Get agent name if available
                $agentName = '';
                if (!empty($payment['imie']) || !empty($payment['nazwisko'])) {
                    $agentName = trim($payment['imie'] . ' ' . $payment['nazwisko']);
                } else {
                    // Try to get agent name from database
                    $agentName = $this->getAgentName($payment['agent_id']);
                }
                
                // Format payment for display
                $formattedPayment = [
                    'id' => $payment['id'] ?? null,
                    'case_id' => $payment['case_id'],
                    'installment_number' => $payment['installment_number'],
                    'agent_id' => $payment['agent_id'],
                    'invoice_number' => $payment['invoice_number'] ?? '',
                    'status' => $payment['status'] ?? 0,
                    'agent_name' => $agentName ?: ('Agent ' . $payment['agent_id']),
                    'type' => 'Faktura prowizji', // User-friendly label instead of technical field name
                    'formatted_date' => isset($payment['created_at']) ? 
                        date('d.m.Y H:i', strtotime($payment['created_at'])) : 
                        null
                ];
                
                // Add a display label based on the installment number
                switch ($payment['installment_number']) {
                    case 1:
                        $formattedPayment['display_label'] = 'Faktura prowizji (rata 1)';
                        break;
                    case 2:
                        $formattedPayment['display_label'] = 'Faktura prowizji (rata 2)';
                        break;
                    case 3:
                        $formattedPayment['display_label'] = 'Faktura prowizji (rata 3)';
                        break;
                    case 4:
                        $formattedPayment['display_label'] = 'Faktura prowizji (rata końcowa)';
                        break;
                    default:
                        $formattedPayment['display_label'] = 'Faktura prowizji';
                        break;
                }
                
                $formattedPayments[] = $formattedPayment;
            }
            
            // Return formatted payments
            echo json_encode([
                'success' => true,
                'payments' => $formattedPayments,
                'count' => count($formattedPayments),
                'case_id' => $caseId,
                'installment_number' => $installmentNumber,
                'table_name' => 'commission_payments' // This is for technical reference, not displayed to user
            ]);
            
        } catch (\Exception $e) {
            error_log("Error getting commission payments for display: " . $e->getMessage());
            
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get agent name by ID
     * 
     * @param int $agentId The agent ID
     * @return string The agent name (imie + nazwisko)
     */
    private function getAgentName($agentId)
    {
        try {
            $query = "SELECT imie, nazwisko FROM agenci WHERE agent_id = :agent_id";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':agent_id' => $agentId]);
            $agent = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($agent) {
                return $agent['imie'] . ' ' . $agent['nazwisko'];
            }
            
            return null;
        } catch (\Exception $e) {
            error_log("Error getting agent name: " . $e->getMessage());
            return null;
        }
    }
} 