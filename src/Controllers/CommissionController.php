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
            
            // If we're setting status=1 but no agent_id was provided, try to get it from the case
            if ($status == 1 && empty($agentId)) {
                error_log("No agent ID provided, trying to find one from the case");
                $agentQuery = "SELECT id_agenta FROM prowizje_agentow_spraw WHERE id_sprawy = :case_id LIMIT 1";
                $agentStmt = $this->pdo->prepare($agentQuery);
                $agentStmt->execute([':case_id' => $caseId]);
                $fetchedAgentId = $agentStmt->fetchColumn();
                
                if ($fetchedAgentId) {
                    $agentId = $fetchedAgentId;
                    error_log("Found agent ID: $agentId");
                } else {
                    // Default to '1' (Kuba) if no agent found
                    $agentId = '1';
                    error_log("No agent found, defaulting to: $agentId");
                }
            }
            
            try {
                // Start transaction to ensure data consistency
                $this->pdo->beginTransaction();
                
                // Get the description for this installment
                $desc = 'Prowizja rata ' . $installmentNumber;
                
                // Delete any existing commission records for this agent, case and installment
                // if we're setting status=0
                if ($status == 0) {
                    // Instead of deleting, we'll mark as not paid in agenci_wyplaty
                    $updateSql = "UPDATE agenci_wyplaty 
                                 SET czy_oplacone = 0,
                                     numer_faktury = NULL,
                                     data_platnosci = NULL,
                                     data_modyfikacji = NOW()
                                 WHERE id_sprawy = :case_id 
                                 AND opis_raty = :installment_desc";
                    
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
                        // First, calculate the commission amount from the sprawy table
                        $amount = 0;
                        
                        // Try to get amount from sprawy table first
                        $amountSql = "SELECT wywalczona_kwota, stawka_success_fee FROM sprawy WHERE id_sprawy = :case_id";
                        $amountStmt = $this->pdo->prepare($amountSql);
                        $amountStmt->execute([':case_id' => $caseId]);
                        $sprawaResult = $amountStmt->fetch(\PDO::FETCH_ASSOC);
                        
                        if ($sprawaResult && isset($sprawaResult['wywalczona_kwota']) && isset($sprawaResult['stawka_success_fee'])) {
                            // Calculate commission based on success fee percentage and agent percentage
                            $agentProcSql = "SELECT udzial_prowizji_proc FROM prowizje_agentow_spraw 
                                            WHERE id_sprawy = :case_id AND id_agenta = :agent_id";
                            $agentProcStmt = $this->pdo->prepare($agentProcSql);
                            $agentProcStmt->execute([':case_id' => $caseId, ':agent_id' => $agentId]);
                            $procResult = $agentProcStmt->fetch(\PDO::FETCH_ASSOC);
                            
                            $agentProc = $procResult ? floatval($procResult['udzial_prowizji_proc']) : 0.1; // Default to 10%
                            $amount = round($sprawaResult['wywalczona_kwota'] * $sprawaResult['stawka_success_fee'] * $agentProc, 2);
                            error_log("Calculated amount from sprawy: $amount for agent $agentId with percentage $agentProc");
                        }
                        
                        // If we couldn't calculate from sprawy, try oplaty_spraw
                        if ($amount == 0) {
                            $rataDesc = "Rata " . $installmentNumber;
                            $amountSql = "SELECT oczekiwana_kwota FROM oplaty_spraw WHERE id_sprawy = :case_id AND opis_raty = :rata_desc";
                            $amountStmt = $this->pdo->prepare($amountSql);
                            $amountStmt->execute([':case_id' => $caseId, ':rata_desc' => $rataDesc]);
                            $oplatyResult = $amountStmt->fetch(\PDO::FETCH_ASSOC);
                            
                            if ($oplatyResult && isset($oplatyResult['oczekiwana_kwota'])) {
                                // Default to 10% commission if not specified
                                $agentProcSql = "SELECT udzial_prowizji_proc FROM prowizje_agentow_spraw 
                                                WHERE id_sprawy = :case_id AND id_agenta = :agent_id";
                                $agentProcStmt = $this->pdo->prepare($agentProcSql);
                                $agentProcStmt->execute([':case_id' => $caseId, ':agent_id' => $agentId]);
                                $procResult = $agentProcStmt->fetch(\PDO::FETCH_ASSOC);
                                
                                $agentProc = $procResult ? floatval($procResult['udzial_prowizji_proc']) : 0.1; // Default to 10%
                                $amount = round($oplatyResult['oczekiwana_kwota'] * $agentProc, 2);
                                error_log("Calculated amount from oplaty_spraw: $amount for agent $agentId with percentage $agentProc");
                            } else {
                                // Default amount if all else fails
                                $amount = 1000.00;
                                error_log("Using default amount: $amount for agent $agentId");
                            }
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
                
                // Also update oplaty_spraw to mark the entire installment as paid if this is the main commission payment
                if ($status == 1) {
                    $rataDesc = "Rata " . $installmentNumber;
                    $updateOplatySql = "UPDATE oplaty_spraw 
                                       SET czy_oplacona = 1,
                                           faktura_id = :invoice_number,
                                           data_oplaty = CURDATE()
                                       WHERE id_sprawy = :case_id 
                                       AND opis_raty = :installment_desc";
                    
                    $updateOplatyStmt = $this->pdo->prepare($updateOplatySql);
                    $updateOplatyResult = $updateOplatyStmt->execute([
                        ':case_id' => $caseId,
                        ':installment_desc' => $rataDesc,
                        ':invoice_number' => $invoiceNumber
                    ]);
                    
                    error_log("Updated oplaty_spraw: " . ($updateOplatyResult ? "success" : "failed") . 
                             ", rows affected: " . $updateOplatyStmt->rowCount());
                }
                
                // Commit the transaction
                $this->pdo->commit();
                
                // Return success response
                $response = [
                    'success' => true,
                    'message' => 'Commission status updated successfully',
                    'commission_id' => $status == 1 ? ($lastInsertId ?? null) : null,
                    'case_id' => $caseId,
                    'installment_number' => $installmentNumber,
                    'status' => $status,
                    'agent_id' => $agentId,
                    'invoice_number' => $invoiceNumber,
                    'is_paid' => $status == 1
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
                           COALESCE(a.nazwa_agenta, '') as nazwa_agenta 
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
                // Get agent name from payment data
                $agentName = $payment['nazwa_agenta'] ?? ('Agent ' . $payment['agent_id']);
                
                // Format payment for display
                $formattedPayment = [
                    'id' => $payment['id'] ?? null,
                    'case_id' => $payment['case_id'],
                    'installment_number' => $payment['installment_number'],
                    'agent_id' => $payment['agent_id'],
                    'invoice_number' => $payment['invoice_number'] ?? '',
                    'status' => $payment['status'] ?? 0,
                    'agent_name' => $agentName,
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
                'table_name' => 'agenci_wyplaty' // Updated to reflect actual table name
            ]);
            
        } catch (\Exception $e) {
            error_log("Error getting commission payments for display: " . $e->getMessage());
            
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
} 