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
            // Check if installment commission columns exist in agenci_wyplaty table
            $columnsToCheck = [
                'czy_oplacone',
                'numer_faktury'
            ];
            
            foreach ($columnsToCheck as $column) {
                $checkQuery = "SHOW COLUMNS FROM agenci_wyplaty LIKE '{$column}'";
                $result = $this->pdo->query($checkQuery)->fetchAll();
                
                if (empty($result)) {
                    // Add missing column
                    $addColumnQuery = "";
                    if ($column === 'czy_oplacone') {
                        $addColumnQuery = "ALTER TABLE agenci_wyplaty ADD COLUMN {$column} TINYINT(1) DEFAULT 0";
                    } else {
                        $addColumnQuery = "ALTER TABLE agenci_wyplaty ADD COLUMN {$column} VARCHAR(255) DEFAULT NULL";
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
            // Get all records from sprawy table
            $query = "SELECT * FROM sprawy";
            $stmt = $this->pdo->query($query);
            $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($cases as $case) {
                $id = $case['id_sprawy'];
                
                // Calculate total commission
                $upfrontFee = !empty($case['oplata_wstepna']) ? floatval($case['oplata_wstepna']) : 0;
                $amountWon = !empty($case['wywalczona_kwota']) ? floatval($case['wywalczona_kwota']) : 0;
                $successFeePercentage = !empty($case['stawka_success_fee']) ? floatval($case['stawka_success_fee']) : 0;
                
                // Calculate total commission
                $totalCommission = $upfrontFee + ($amountWon * $successFeePercentage);
                
                // Get all installments except the final one
                $stmt = $this->pdo->prepare("SELECT SUM(oczekiwana_kwota) as suma_rat FROM oplaty_spraw WHERE id_sprawy = ? AND opis_raty != 'Rata końcowa'");
                $stmt->execute([$id]);
                $sumaRat = $stmt->fetch(PDO::FETCH_ASSOC)['suma_rat'] ?? 0;
                
                // Calculate final installment as the difference between total commission and sum of all other installments
                $rataKoncowa = $totalCommission - $sumaRat;
                
                // Update only the final installment
                $updateQuery = "UPDATE oplaty_spraw SET 
                    oczekiwana_kwota = :kwota 
                    WHERE id_sprawy = :id_sprawy AND opis_raty = 'Rata końcowa'";
                
                    $stmt = $this->pdo->prepare($updateQuery);
                    $stmt->execute([
                    ':kwota' => $rataKoncowa,
                    ':id_sprawy' => $id
                    ]);
            }
        } catch (\PDOException $e) {
            error_log("Error in calculateCommissions: " . $e->getMessage());
        }
    }
    
    /**
     * Sync payment statuses with agenci_wyplaty table
     */
    private function syncPaymentStatuses(): void {
        try {
            // Update payment statuses based on agenci_wyplaty table
            $updateQuery = "UPDATE oplaty_spraw os
                           JOIN agenci_wyplaty aw ON os.id_sprawy = aw.id_sprawy AND os.opis_raty = aw.opis_raty
                           SET os.czy_oplacona = aw.czy_oplacone
                           WHERE aw.czy_oplacone = 1";
            $this->pdo->exec($updateQuery);
        } catch (PDOException $e) {
            // Log error but continue
            error_log("Error syncing payment statuses: " . $e->getMessage());
        }
    }
}
