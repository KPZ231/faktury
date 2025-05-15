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
            $installmentNumber = isset($_GET['installment_number']) ? $_GET['installment_number'] : '';
            // Don't cast agent_id to int since it's VARCHAR in the database
            $agentId = isset($_GET['agent_id']) ? $_GET['agent_id'] : '';
            
            error_log("getPaymentStatus request: case_id=$caseId, installment_number=$installmentNumber, agent_id=$agentId");
            
            if (!$caseId || empty($installmentNumber) || $agentId === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required parameters']);
                return;
            }
            
            // Normalize installment number
            $installmentNumber = is_numeric($installmentNumber) ? (int)$installmentNumber : $installmentNumber;
            
            // Create all possible variations of payment description
            $descOptions = [];
            if (is_numeric($installmentNumber)) {
                $descOptions = [
                    'Prowizja Rata ' . $installmentNumber,
                    'Prowizja rata ' . $installmentNumber,
                    'Prowizja Rata' . $installmentNumber,
                    'Prowizja rata' . $installmentNumber,
                    'Prowizja ' . 'Rata ' . $installmentNumber
                ];
            } else {
                // Handle "Końcowa" or other special cases
                $descOptions = [
                    'Prowizja Rata ' . $installmentNumber,
                    'Prowizja rata ' . $installmentNumber,
                    'Prowizja ' . 'Rata ' . $installmentNumber
                ];
            }
            
            // Create placeholders for SQL query
            $placeholders = implode(',', array_fill(0, count($descOptions), '?'));
            $params = array_merge([$caseId, $agentId], $descOptions);
            
            // Query the agenci_wyplaty table for payment status
            $query = "SELECT 
                        CASE WHEN czy_oplacone = 1 THEN 1 ELSE 0 END as status, 
                        numer_faktury as invoice_number, 
                        data_utworzenia as created_at,
                        kwota as amount,
                        opis_raty as payment_desc
                      FROM agenci_wyplaty 
                      WHERE id_sprawy = ? 
                      AND id_agenta = ?
                      AND opis_raty IN ($placeholders)
                      LIMIT 1";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
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
            
            // Store whether we're setting paid status
            $isPaid = isset($data['status']) && $data['status'] == 1;
            $invoiceNumber = $data['invoice_number'] ?? null;
            
            // Log detailed information about the update request
            error_log("Processing payment update: case={$data['case_id']}, installment={$data['installment_number']}, " .
                     "agent={$data['agent_id']}, isPaid=" . ($isPaid ? 'true' : 'false') . 
                     ", invoice=" . ($invoiceNumber ?? 'none'));
            
            // Ensure we have an invoice number if setting to paid
            if ($isPaid && empty($invoiceNumber)) {
                error_log("Warning: Setting paid status but no invoice number provided");
            }

            // Create payment description using the new consistent format
            $desc = 'Prowizja Rata ' . $data['installment_number'];
            
            // Check if a payment record already exists in agenci_wyplaty with any format variant
            $descOptions = [
                'Prowizja Rata ' . $data['installment_number'],
                'Prowizja rata ' . $data['installment_number'],
                'Prowizja ' . 'Rata ' . $data['installment_number']
            ];
            
            $placeholders = implode(',', array_fill(0, count($descOptions), '?'));
            $checkParams = array_merge([$data['case_id'], $data['agent_id']], $descOptions);
            
            $checkQuery = "SELECT id_wyplaty, czy_oplacone, opis_raty FROM agenci_wyplaty 
                         WHERE id_sprawy = ? 
                         AND id_agenta = ?
                         AND opis_raty IN ($placeholders)
                         LIMIT 1";
            
            $checkStmt = $this->pdo->prepare($checkQuery);
            $checkStmt->execute($checkParams);
            $existingPayment = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Existing payment check: " . ($existingPayment ? "Found record ID: {$existingPayment['id_wyplaty']}, Description: {$existingPayment['opis_raty']}" : "No existing record"));
            
            // Start transaction
            $this->pdo->beginTransaction();
            
            try {
                // Use the existing description format if found, otherwise use new format
                if ($existingPayment) {
                    $desc = $existingPayment['opis_raty'];
                }

                // Calculate the amount if not provided
                $amount = $data['amount'] ?? null;
                if ($amount === null || $amount == 0) {
                    // First try to get amount from sprawy table
                    $amountSql = "SELECT wywalczona_kwota, stawka_success_fee FROM sprawy WHERE id_sprawy = ?";
                    $amountStmt = $this->pdo->prepare($amountSql);
                    $amountStmt->execute([$data['case_id']]);
                    $sprawaResult = $amountStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($sprawaResult && isset($sprawaResult['wywalczona_kwota']) && isset($sprawaResult['stawka_success_fee'])) {
                        // Calculate commission based on success fee percentage
                        $amount = round($sprawaResult['wywalczona_kwota'] * $sprawaResult['stawka_success_fee'] * 0.1, 2);
                        error_log("Calculated amount from sprawy: $amount");
                    } else {
                        // Check if there's one in oplaty_spraw
                        $amountSql = "SELECT oczekiwana_kwota FROM oplaty_spraw WHERE id_sprawy = ? AND opis_raty = ?";
                        $amountStmt = $this->pdo->prepare($amountSql);
                        $amountStmt->execute([$data['case_id'], "Rata " . $data['installment_number']]);
                        $oplatyResult = $amountStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($oplatyResult && isset($oplatyResult['oczekiwana_kwota'])) {
                            $amount = round($oplatyResult['oczekiwana_kwota'] * 0.1, 2);
                            error_log("Calculated amount from oplaty_spraw: $amount");
                        } else {
                            // Last resort - use a default amount or the one from the request
                            $amount = $data['amount'] ?? 1000.00;
                            error_log("Using fallback amount: $amount");
                        }
                    }
                }
                
                // Ensure amount is numeric
                $amount = is_numeric($amount) ? $amount : 0;
                
                if ($existingPayment) {
                    // Update existing payment record
                    $updateQuery = "UPDATE agenci_wyplaty 
                                  SET czy_oplacone = :is_paid, 
                                      numer_faktury = :invoice_number,
                                      kwota = :amount,
                                      data_platnosci = " . ($isPaid ? "CURDATE()" : "NULL") . ",
                                      data_modyfikacji = NOW()
                                  WHERE id_wyplaty = :id";
                    
                    $updateStmt = $this->pdo->prepare($updateQuery);
                    $updateStmt->bindValue(':is_paid', $isPaid ? 1 : 0, PDO::PARAM_INT);
                    $updateStmt->bindValue(':invoice_number', $invoiceNumber, PDO::PARAM_STR);
                    $updateStmt->bindValue(':amount', $amount, PDO::PARAM_STR);
                    $updateStmt->bindValue(':id', $existingPayment['id_wyplaty'], PDO::PARAM_INT);
                    
                    $success = $updateStmt->execute();
                    $paymentId = $existingPayment['id_wyplaty'];
                    $action = 'updated';
                    
                    $rowCount = $updateStmt->rowCount();
                    error_log("Updated existing record, result: " . ($success ? 'success' : 'failed') . 
                            ", payment ID: {$paymentId}" .
                            ", set to paid: " . ($isPaid ? 'yes' : 'no') .
                            ", rows affected: {$rowCount}");
                } else {
                    // Insert new payment record
                    $insertQuery = "INSERT INTO agenci_wyplaty 
                                  (id_sprawy, id_agenta, opis_raty, kwota, czy_oplacone, numer_faktury, data_platnosci, data_utworzenia) 
                                  VALUES (:case_id, :agent_id, :desc, :amount, :is_paid, :invoice_number, " . 
                                    ($isPaid ? "CURDATE()" : "NULL") . ", NOW())";
                    
                    $insertStmt = $this->pdo->prepare($insertQuery);
                    $insertStmt->bindValue(':case_id', $data['case_id'], PDO::PARAM_INT);
                    $insertStmt->bindValue(':agent_id', $data['agent_id'], PDO::PARAM_STR);
                    $insertStmt->bindValue(':desc', $desc, PDO::PARAM_STR);
                    $insertStmt->bindValue(':amount', $amount, PDO::PARAM_STR);
                    $insertStmt->bindValue(':is_paid', $isPaid ? 1 : 0, PDO::PARAM_INT);
                    $insertStmt->bindValue(':invoice_number', $invoiceNumber, PDO::PARAM_STR);
                    
                    $success = $insertStmt->execute();
                    $paymentId = $this->pdo->lastInsertId();
                    $action = 'created';
                    
                    error_log("Inserted new record, result: " . ($success ? 'success' : 'failed') . 
                            ", last insert ID: {$paymentId}" .
                            ", set to paid: " . ($isPaid ? 'yes' : 'no'));
                }
                
                // Also update the oplaty_spraw table to mark the installment as paid
                if ($isPaid) {
                    $updateOplatySql = "UPDATE oplaty_spraw 
                                      SET czy_oplacona = 1,
                                          faktura_id = :invoice_number,
                                          data_oplaty = CURDATE()
                                      WHERE id_sprawy = :case_id 
                                      AND opis_raty = :installment_desc";
                    
                    $updateOplatyStmt = $this->pdo->prepare($updateOplatySql);
                    $updateOplatyStmt->bindValue(':invoice_number', $invoiceNumber, PDO::PARAM_STR);
                    $updateOplatyStmt->bindValue(':case_id', $data['case_id'], PDO::PARAM_INT);
                    $updateOplatyStmt->bindValue(':installment_desc', "Rata " . $data['installment_number'], PDO::PARAM_STR);
                    
                    $updateOplatyResult = $updateOplatyStmt->execute();
                    error_log("Updated oplaty_spraw: " . ($updateOplatyResult ? 'success' : 'failed') . 
                             ", rows affected: " . $updateOplatyStmt->rowCount());
                }
                
                // Double-check that the record was actually saved
                $verifyQuery = "SELECT id_wyplaty, czy_oplacone FROM agenci_wyplaty 
                              WHERE id_sprawy = ? 
                              AND id_agenta = ?
                              AND opis_raty = ?
                              LIMIT 1";
                
                $verifyStmt = $this->pdo->prepare($verifyQuery);
                $verifyStmt->execute([$data['case_id'], $data['agent_id'], $desc]);
                $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($verifyResult) {
                    error_log("Verification successful - record exists with ID: {$verifyResult['id_wyplaty']}, " .
                             "paid status: " . ($verifyResult['czy_oplacone'] ? 'paid' : 'not paid'));
                } else {
                    error_log("WARNING: Verification failed - record not found after save operation");
                }
                
                // Commit the transaction
                $this->pdo->commit();
                
                // Return success response
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'action' => $action,
                    'is_paid' => $isPaid,
                    'invoice_number' => $invoiceNumber,
                    'payment_id' => $paymentId,
                    'amount' => $amount,
                    'payment_desc' => $desc // Include the description that was used
                ]);
            } catch (PDOException $e) {
                // Rollback on error
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                error_log("Database error during transaction: " . $e->getMessage() . ", SQL state: " . $e->getCode());
                throw $e; // Re-throw to be caught by outer catch block
            }
            
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