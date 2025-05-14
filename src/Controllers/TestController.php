<?php

namespace Dell\Faktury\Controllers;

use PDO;
use PDOException;

class TestController {
    private $pdo;

    public function __construct() {
        // Constructor initializes database connection in index()
    }

    public function index(): void {
        // Initialize database connection
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        $this->pdo = $pdo;
        
        // Ensure database structure is as expected
        $this->ensureRequiredColumnsExist();
        
        // Calculate any missing values for cases
        $this->calculateCommissions();
        
        // Sync payment status with commission_payments table
        $this->syncPaymentStatuses();
        
        // Render the view
        include __DIR__ . '/../Views/test.php';
    }
    
    /**
     * Ensure required columns exist in the test2 table
     */
    private function ensureRequiredColumnsExist(): void {
        try {
            // Check if installment commission columns exist in test2 table
            $columnsToCheck = [
                'installment1_commission_paid',
                'installment2_commission_paid',
                'installment3_commission_paid',
                'final_installment_commission_paid',
                'installment1_commission_invoice',
                'installment2_commission_invoice',
                'installment3_commission_invoice',
                'final_installment_commission_invoice'
            ];
            
            foreach ($columnsToCheck as $column) {
                $checkQuery = "SHOW COLUMNS FROM test2 LIKE '{$column}'";
                $result = $this->pdo->query($checkQuery)->fetchAll();
                
                if (empty($result)) {
                    // Add missing column
                    $addColumnQuery = "";
                    if (strpos($column, '_paid') !== false) {
                        $addColumnQuery = "ALTER TABLE test2 ADD COLUMN {$column} TINYINT(1) DEFAULT 0";
                    } else {
                        $addColumnQuery = "ALTER TABLE test2 ADD COLUMN {$column} VARCHAR(255) DEFAULT NULL";
                    }
                    $this->pdo->exec($addColumnQuery);
                }
            }
        } catch (PDOException $e) {
            // Log error but continue
            error_log("Error checking/adding columns: " . $e->getMessage());
        }
    }
    
    /**
     * Calculate commission values for all cases
     */
    private function calculateCommissions(): void {
        try {
            // Get all records from test2 table
            $query = "SELECT * FROM test2";
            $stmt = $this->pdo->query($query);
            $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($cases as $case) {
                $id = $case['id'];
                $updates = [];
                $params = [];
                
                // 1. Calculate total commission if not set
                if (empty($case['total_commission'])) {
                    $upfrontFee = !empty($case['upfront_fee']) ? floatval($case['upfront_fee']) : 0;
                    $amountWon = !empty($case['amount_won']) ? floatval($case['amount_won']) : 0;
                    $successFeePercentage = !empty($case['success_fee_percentage']) ? floatval($case['success_fee_percentage']) : 0;
                    
                    // Correctly apply the percentage by dividing by 100
                    $totalCommission = $upfrontFee + ($amountWon * ($successFeePercentage / 100));
                    $updates[] = "total_commission = :total_commission";
                    $params[':total_commission'] = $totalCommission;
                }
                
                // 2. Calculate kuba_payout if not set
                if (empty($case['kuba_payout'])) {
                    $kubaPercentage = !empty($case['kuba_percentage']) ? floatval($case['kuba_percentage']) : 0;
                    $agentTotalPercentage = 0;
                    
                    // Get agent percentages
                    $agentsQuery = "SELECT percentage FROM sprawa_agent WHERE sprawa_id = ?";
                    $agentsStmt = $this->pdo->prepare($agentsQuery);
                    $agentsStmt->execute([$id]);
                    
                    while ($agentData = $agentsStmt->fetch(PDO::FETCH_ASSOC)) {
                        $agentTotalPercentage += floatval($agentData['percentage']);
                    }
                    
                    // The percentages are stored as-is (25 means 25%), so no need to multiply or divide
                    $kubaPayout = max(0, min(100, $kubaPercentage - $agentTotalPercentage));
                    $updates[] = "kuba_payout = :kuba_payout";
                    $params[':kuba_payout'] = $kubaPayout;
                }
                
                // 3. Update if we have changes
                if (!empty($updates) && !empty($params)) {
                    $updateQuery = "UPDATE test2 SET " . implode(", ", $updates) . " WHERE id = :id";
                    $params[':id'] = $id;
                    
                    $updateStmt = $this->pdo->prepare($updateQuery);
                    $updateStmt->execute($params);
                }
            }
        } catch (PDOException $e) {
            // Log error but continue
            error_log("Error calculating commissions: " . $e->getMessage());
        }
    }
    
    /**
     * Sync payment statuses with commission_payments table
     */
    private function syncPaymentStatuses(): void {
        try {
            // Update commission statuses based on commission_payments table
            $updateQuery = "UPDATE test2 t
                           JOIN commission_payments cp ON t.id = cp.case_id
                           SET t.installment1_commission_paid = CASE WHEN cp.installment_number = 1 THEN cp.status ELSE t.installment1_commission_paid END,
                               t.installment2_commission_paid = CASE WHEN cp.installment_number = 2 THEN cp.status ELSE t.installment2_commission_paid END,
                               t.installment3_commission_paid = CASE WHEN cp.installment_number = 3 THEN cp.status ELSE t.installment3_commission_paid END,
                               t.final_installment_commission_paid = CASE WHEN cp.installment_number = 4 THEN cp.status ELSE t.final_installment_commission_paid END,
                               t.installment1_commission_invoice = CASE WHEN cp.installment_number = 1 THEN cp.invoice_number ELSE t.installment1_commission_invoice END,
                               t.installment2_commission_invoice = CASE WHEN cp.installment_number = 2 THEN cp.invoice_number ELSE t.installment2_commission_invoice END,
                               t.installment3_commission_invoice = CASE WHEN cp.installment_number = 3 THEN cp.invoice_number ELSE t.installment3_commission_invoice END,
                               t.final_installment_commission_invoice = CASE WHEN cp.installment_number = 4 THEN cp.invoice_number ELSE t.final_installment_commission_invoice END
                           WHERE cp.status = 1 OR cp.invoice_number IS NOT NULL";
            $this->pdo->exec($updateQuery);
        } catch (PDOException $e) {
            // Log error but continue
            error_log("Error syncing payment statuses: " . $e->getMessage());
        }
    }
}
