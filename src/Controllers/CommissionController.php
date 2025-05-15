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
                    // Instead of deleting, we'll mark as not paid in agenci_wyplaty
                    $updateSql = "UPDATE agenci_wyplaty 
                                 SET czy_oplacone = 0,
                                     data_modyfikacji = NOW()
                                 WHERE id_sprawy = :case_id 
                                 AND opis_raty = :installment_desc";
                    
                    $desc = 'Prowizja rata ' . $installmentNumber;
                    $updateStmt = $this->pdo->prepare($updateSql);
                    $updateResult = $updateStmt->execute([
                        ':case_id' => $caseId,
                        ':installment_desc' => $desc
                    ]);
                    
                    error_log("Updated existing commission records in agenci_wyplaty: " . ($updateResult ? "success" : "failed") . 
                            ", rows affected: " . $updateStmt->rowCount() . 
                            ", for case $caseId, installment $installmentNumber");
                }
                
                // Store commission payment in the agenci_wyplaty table if status=1
                if ($status == 1) {
                    $desc = 'Prowizja rata ' . $installmentNumber;
                    // First check if a record already exists for this case and installment description
                    $checkSql = "SELECT id_wyplaty FROM agenci_wyplaty 
                                WHERE id_sprawy = :case_id 
                                AND id_agenta = :agent_id
                                AND opis_raty = :installment_desc";
                    
                    $checkStmt = $this->pdo->prepare($checkSql);
                    $checkParams = [
                        ':case_id' => $caseId,
                        ':agent_id' => $agentId,
                        ':installment_desc' => $desc
                    ];
                    
                    $checkStmt->execute($checkParams);
                    $existingRecord = $checkStmt->fetch(\PDO::FETCH_ASSOC);
                    
                    if ($existingRecord) {
                        // Update existing record in agenci_wyplaty
                        $updateSql = "UPDATE agenci_wyplaty 
                                    SET numer_faktury = :invoice_number, 
                                        czy_oplacone = 1,
                                        data_platnosci = CURDATE(),
                                        data_modyfikacji = NOW()
                                    WHERE id_wyplaty = :id";
                        
                        $updateStmt = $this->pdo->prepare($updateSql);
                        $updateParams = [
                            ':invoice_number' => $invoiceNumber,
                            ':id' => $existingRecord['id_wyplaty']
                        ];
                        
                        $updateResult = $updateStmt->execute($updateParams);
                        $lastInsertId = $existingRecord['id_wyplaty'];
                        
                        error_log("Updated existing commission payment record in agenci_wyplaty: " . ($updateResult ? "success" : "failed") . 
                                ", for case $caseId, installment $installmentNumber, agent $agentId, invoice $invoiceNumber");
                    } else {
                        // Insert new record into agenci_wyplaty
                        // First, we need to get the commission amount from the test2 table
                        $amount = 0;
                        $amountSql = "SELECT kwota_netto FROM test2 WHERE id = :case_id";
                        $amountStmt = $this->pdo->prepare($amountSql);
                        $amountStmt->execute([':case_id' => $caseId]);
                        $amountResult = $amountStmt->fetch(\PDO::FETCH_ASSOC);
                        
                        if ($amountResult && isset($amountResult['kwota_netto'])) {
                            // Default to 10% commission if not specified
                            $amount = round($amountResult['kwota_netto'] * 0.1, 2);
                        }
                        
                        $sql = "INSERT INTO agenci_wyplaty 
                                (id_sprawy, id_agenta, opis_raty, kwota, czy_oplacone, numer_faktury, data_platnosci, data_utworzenia) 
                                VALUES (:case_id, :agent_id, :installment_desc, :amount, 1, :invoice_number, CURDATE(), NOW())";
                        
                        $stmt = $this->pdo->prepare($sql);
                        $params = [
                            ':case_id' => $caseId,
                            ':agent_id' => $agentId,
                            ':installment_desc' => $desc,
                            ':amount' => $amount,
                            ':invoice_number' => $invoiceNumber
                        ];
                        
                        $insertResult = $stmt->execute($params);
                        $lastInsertId = $this->pdo->lastInsertId();
                        
                        error_log("Inserted new commission payment record in agenci_wyplaty: " . ($insertResult ? "success" : "failed") .
                                ", ID: $lastInsertId" .
                                ", case=$caseId, installment=$installmentNumber, agent=$agentId, invoice=$invoiceNumber, amount=$amount");
                    }
                }
                
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
                return $response;
                
            } catch (\PDOException $dbError) {
                // Rollback the transaction in case of database errors
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                
                $errorMsg = "Database error updating commission status: " . $dbError->getMessage();
                error_log($errorMsg);
                error_log("Database error: " . $dbError->getMessage());
                http_response_code(500);
                $errorResponse = [
                    'success' => false,
                    'error' => 'Database error: ' . $dbError->getMessage()
                ];
                echo json_encode($errorResponse);
                return $errorResponse;
            }
            
        } catch (\Exception $e) {
            error_log("Error in updateCommissionStatus: " . $e->getMessage());
            http_response_code(500);
            $errorResponse = [
                'success' => false,
                'error' => 'Error: ' . $e->getMessage()
            ];
            echo json_encode($errorResponse);
            return $errorResponse;
        }
    }

    /**
     * Ensure commission-related database columns exist
     * This is now a no-op since we're using agenci_wyplaty table
     */
    private function ensureCommissionColumnsExist()
    {
        // No need to create tables or columns anymore as we're using agenci_wyplaty
        return;
    }
    
    /**
     * Migrate existing commission data from old format to agenci_wyplaty
     * This is a one-time migration function
     */
    private function migrateExistingCommissionData()
    {
        try {
            // Check if we need to migrate data (if there's data in test2 but not in agenci_wyplaty)
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM test2 WHERE installment1_commission_paid = 1 OR installment2_commission_paid = 1 OR installment3_commission_paid = 1 OR final_installment_commission_paid = 1");
            $test2Count = $stmt->fetchColumn();
            
            if ($test2Count > 0) {
                error_log("Migrating existing commission data to agenci_wyplaty table");
                
                // Get all cases with commission payments
                $cases = $this->pdo->query("
                    SELECT id, 
                           installment1_commission_paid, installment1_commission_invoice,
                           installment2_commission_paid, installment2_commission_invoice,
                           installment3_commission_paid, installment3_commission_invoice,
                           final_installment_commission_paid, final_installment_commission_invoice,
                           kwota_netto
                    FROM test2 
                    WHERE installment1_commission_paid = 1 
                       OR installment2_commission_paid = 1 
                       OR installment3_commission_paid = 1 
                       OR final_installment_commission_paid = 1
                ")->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($cases as $case) {
                    // For each case, add payments to agenci_wyplaty table
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
                            
                            // Calculate commission amount (10% of net amount by default)
                            $amount = 0;
                            if (!empty($case['kwota_netto'])) {
                                $amount = round($case['kwota_netto'] * 0.1, 2);
                            }
                            
                            // Check if this payment already exists in agenci_wyplaty
                            $desc = 'Prowizja rata ' . ($i == 4 ? 'końcowa' : $i);
                            $checkStmt = $this->pdo->prepare("
                                SELECT COUNT(*) FROM agenci_wyplaty 
                                WHERE id_sprawy = ? 
                                AND id_agenta = ?
                                AND opis_raty = ?
                                AND numer_faktury = ?
                            ");
                            $checkStmt->execute([$case['id'], $agentId, $desc, $case[$invoiceField]]);
                            $exists = $checkStmt->fetchColumn() > 0;
                            
                            if (!$exists) {
                                // Insert payment record into agenci_wyplaty
                                $sql = "INSERT INTO agenci_wyplaty 
                                        (id_sprawy, id_agenta, opis_raty, kwota, czy_oplacone, numer_faktury, data_platnosci, data_utworzenia) 
                                        VALUES (?, ?, ?, ?, 1, ?, CURDATE(), NOW())";
                                
                                $this->pdo->prepare($sql)->execute([
                                    $case['id'],
                                    $agentId,
                                    $desc,
                                    $amount,
                                    $case[$invoiceField]
                                ]);
                                
                                error_log("Migrated payment to agenci_wyplaty for case {$case['id']}, installment $i, agent $agentId");
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Error migrating commission data to agenci_wyplaty: " . $e->getMessage());
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
            
            $desc = 'Prowizja rata ' . $installmentNumber;
            
            // Query the agenci_wyplaty table for commission payments
            $sql = "SELECT aw.id_wyplaty as id, 
                           aw.id_sprawy as case_id,
                           :installment_number as installment_number,
                           aw.id_agenta as agent_id,
                           aw.numer_faktury as invoice_number,
                           aw.czy_oplacone as status,
                           aw.data_platnosci as created_at,
                           aw.data_modyfikacji as updated_at,
                           COALESCE(a.imie, '') as imie, 
                           COALESCE(a.nazwisko, '') as nazwisko
                    FROM agenci_wyplaty aw
                    LEFT JOIN agenci a ON (aw.id_agenta = a.id_agenta)
                    WHERE aw.id_sprawy = :case_id 
                    AND aw.opis_raty = :installment_desc
                    ORDER BY aw.data_platnosci DESC, aw.data_utworzenia DESC";
                    
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':case_id' => $caseId,
                ':installment_number' => $installmentNumber,
                ':installment_desc' => $desc
            ]);
            
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Found " . count($payments) . " commission payments in agenci_wyplaty for case $caseId, installment $installmentNumber");
            
            // If no payments found, check if there's legacy data in the test2 table
            if (count($payments) === 0) {
                error_log("No payments found in agenci_wyplaty table, checking for legacy data");
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