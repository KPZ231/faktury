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
        
        // No need to ensure table exists as we're using agenci_wyplaty which should already exist
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
            
            // Query the agenci_wyplaty table for payment status
            $desc = 'Prowizja rata ' . $installmentNumber;
            
            $query = "SELECT 
                        CASE WHEN czy_oplacone = 1 THEN 1 ELSE 0 END as status, 
                        numer_faktury as invoice_number, 
                        data_utworzenia as created_at,
                        kwota as amount
                      FROM agenci_wyplaty 
                      WHERE id_sprawy = ? 
                      AND id_agenta = ?
                      AND opis_raty = ?
                      LIMIT 1";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$caseId, $agentId, $desc]);
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
            
            // Validate required fields
            if (!isset($data['case_id']) || !isset($data['agent_id']) || !isset($data['installment_number'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields']);
                return;
            }
            
            // Check if a payment record already exists in agenci_wyplaty
            $desc = 'Prowizja rata ' . $data['installment_number'];
            
            $checkQuery = "SELECT id_wyplaty FROM agenci_wyplaty 
                         WHERE id_sprawy = ? 
                         AND id_agenta = ?
                         AND opis_raty = ?
                         LIMIT 1";
            
            $checkStmt = $this->pdo->prepare($checkQuery);
            $checkStmt->execute([$data['case_id'], $data['agent_id'], $desc]);
            $existingPayment = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get the net amount from test2 table to calculate commission (10% by default)
            $amount = $data['amount'] ?? null;
            if ($amount === null) {
                $amountSql = "SELECT kwota_netto FROM test2 WHERE id = ?";
                $amountStmt = $this->pdo->prepare($amountSql);
                $amountStmt->execute([$data['case_id']]);
                $amountResult = $amountStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($amountResult && isset($amountResult['kwota_netto'])) {
                    $amount = round($amountResult['kwota_netto'] * 0.1, 2);
                } else {
                    $amount = 0;
                }
            }
            
            if ($existingPayment) {
                // Update existing payment record
                $updateQuery = "UPDATE agenci_wyplaty 
                              SET czy_oplacone = 1, 
                                  numer_faktury = ?,
                                  kwota = ?,
                                  data_platnosci = CURDATE(),
                                  data_modyfikacji = NOW()
                              WHERE id_wyplaty = ?";
                
                $updateStmt = $this->pdo->prepare($updateQuery);
                $success = $updateStmt->execute([$data['invoice_number'] ?? null, $amount, $existingPayment['id_wyplaty']]);
                $action = 'updated';
            } else {
                // Insert new payment record
                $insertQuery = "INSERT INTO agenci_wyplaty 
                              (id_sprawy, id_agenta, opis_raty, kwota, czy_oplacone, numer_faktury, data_platnosci, data_utworzenia) 
                              VALUES (?, ?, ?, ?, 1, ?, CURDATE(), NOW())";
                
                $insertStmt = $this->pdo->prepare($insertQuery);
                $success = $insertStmt->execute([
                    $data['case_id'],
                    $data['agent_id'],
                    $desc,
                    $amount,
                    $data['invoice_number'] ?? null
                ]);
                $action = 'created';
                
                error_log("Inserted new record, result: " . ($success ? 'success' : 'failed') . ", last insert ID: " . $this->pdo->lastInsertId());
            }
            
            // Also update the corresponding commission_paid field in test2 table
            $this->updateCommissionPaidStatus($data['case_id'], $data['installment_number'], $data['status'], $data['invoice_number'] ?? null);
            
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
    
    /**
     * Helper method to update commission paid status in test2 table
     */
    private function updateCommissionPaidStatus(int $caseId, int $installmentNumber, int $status, ?string $invoiceNumber): void {
        try {
            $fieldMapping = [
                1 => ['paid' => 'installment1_commission_paid', 'invoice' => 'installment1_commission_invoice'],
                2 => ['paid' => 'installment2_commission_paid', 'invoice' => 'installment2_commission_invoice'],
                3 => ['paid' => 'installment3_commission_paid', 'invoice' => 'installment3_commission_invoice'],
                4 => ['paid' => 'final_installment_commission_paid', 'invoice' => 'final_installment_commission_invoice']
            ];
            
            if (!isset($fieldMapping[$installmentNumber])) {
                error_log("Invalid installment number: $installmentNumber");
                return;
            }
            
            $paidField = $fieldMapping[$installmentNumber]['paid'];
            $invoiceField = $fieldMapping[$installmentNumber]['invoice'];
            
            $query = "UPDATE test2 SET {$paidField} = ?, {$invoiceField} = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($query);
            $result = $stmt->execute([$status, $invoiceNumber, $caseId]);
            
            error_log("Updated test2 table for case $caseId, installment $installmentNumber, result: " . ($result ? 'success' : 'failed'));
            
        } catch (PDOException $e) {
            error_log("Error updating commission paid status: " . $e->getMessage());
            // Let this error pass through as it's not critical
        }
    }
} 