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
     * Ensure required columns exist in the agenci_wyplaty table
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
            // First, ensure the agenci_wyplaty table is properly configured
            $this->ensureAgenciWyplatyTable();
            
            // Update payment statuses based on agenci_wyplaty table
            // First, mark any installments as paid if there's a paid agent commission
            $updateQuery = "UPDATE oplaty_spraw os
                           JOIN agenci_wyplaty aw ON os.id_sprawy = aw.id_sprawy
                           AND os.opis_raty = CONVERT(REPLACE(aw.opis_raty, 'Prowizja ', '') USING utf8)
                           SET os.czy_oplacona = 1
                           WHERE aw.czy_oplacone = 1";
            $this->pdo->exec($updateQuery);
            
            // Update any paid invoice references
            $updateInvoicesQuery = "UPDATE oplaty_spraw os
                                   JOIN agenci_wyplaty aw ON os.id_sprawy = aw.id_sprawy
                                   AND os.opis_raty = CONVERT(REPLACE(aw.opis_raty, 'Prowizja ', '') USING utf8)
                                   SET os.faktura_id = aw.numer_faktury
                                   WHERE aw.czy_oplacone = 1 
                                   AND aw.numer_faktury IS NOT NULL 
                                   AND aw.numer_faktury != ''
                                   AND (os.faktura_id IS NULL OR os.faktura_id = '')";
            $this->pdo->exec($updateInvoicesQuery);
            
            // Check for missing prowizje_agentow_spraw entries
            $this->checkAgentCommissions();
            
            // Log sync completion
            error_log("Payment statuses synchronized successfully");
        } catch (PDOException $e) {
            // Log error but continue
            error_log("Error syncing payment statuses: " . $e->getMessage());
        }
    }
    
    /**
     * Check and fix missing agent commission entries
     */
    private function checkAgentCommissions(): void {
        try {
            // Find all agenci_wyplaty entries that don't have corresponding prowizje_agentow_spraw entries
            $checkSql = "SELECT DISTINCT aw.id_sprawy, aw.id_agenta 
                         FROM agenci_wyplaty aw
                         LEFT JOIN prowizje_agentow_spraw pas ON aw.id_sprawy = pas.id_sprawy
                         AND aw.id_agenta = pas.id_agenta
                         WHERE pas.id_prowizji_agenta_sprawy IS NULL";
            $missingEntries = $this->pdo->query($checkSql)->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($missingEntries)) {
                error_log("Found " . count($missingEntries) . " missing agent commission entries in prowizje_agentow_spraw");
                
                $insertSql = "INSERT INTO prowizje_agentow_spraw (id_sprawy, id_agenta, udzial_prowizji_proc) VALUES (?, ?, 0.1)";
                $insertStmt = $this->pdo->prepare($insertSql);
                
                foreach ($missingEntries as $entry) {
                    $insertStmt->execute([$entry['id_sprawy'], $entry['id_agenta']]);
                    error_log("Added missing agent commission entry for case {$entry['id_sprawy']}, agent {$entry['id_agenta']}");
                }
            }
        } catch (PDOException $e) {
            error_log("Error checking agent commissions: " . $e->getMessage());
        }
    }
    
    /**
     * Ensure agenci_wyplaty table exists with correct columns
     */
    private function ensureAgenciWyplatyTable(): void {
        try {
            // Check if agenci_wyplaty table exists
            $tableExists = $this->pdo->query("SHOW TABLES LIKE 'agenci_wyplaty'")->fetchColumn();
            
            if (!$tableExists) {
                // Create the table with utf8 character set instead of utf8mb4
                $createTableQuery = "CREATE TABLE IF NOT EXISTS `agenci_wyplaty` (
                    `id_wyplaty` int(11) NOT NULL AUTO_INCREMENT,
                    `id_sprawy` int(11) NOT NULL,
                    `id_agenta` int(11) NOT NULL,
                    `opis_raty` varchar(50) NOT NULL,
                    `kwota` decimal(10,2) NOT NULL,
                    `czy_oplacone` tinyint(1) DEFAULT 0,
                    `numer_faktury` varchar(100) DEFAULT NULL,
                    `data_platnosci` date DEFAULT NULL,
                    `data_utworzenia` timestamp NOT NULL DEFAULT current_timestamp(),
                    `data_modyfikacji` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    PRIMARY KEY (`id_wyplaty`),
                    UNIQUE KEY `unique_wyplata` (`id_sprawy`,`id_agenta`,`opis_raty`),
                    KEY `id_agenta` (`id_agenta`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
                
                $this->pdo->exec($createTableQuery);
                error_log("Created agenci_wyplaty table");
            }
            
            // Check if required columns exist and add them if they don't
            $requiredColumns = [
                'czy_oplacone' => "ALTER TABLE agenci_wyplaty ADD COLUMN czy_oplacone TINYINT(1) DEFAULT 0",
                'numer_faktury' => "ALTER TABLE agenci_wyplaty ADD COLUMN numer_faktury VARCHAR(100) DEFAULT NULL",
                'data_platnosci' => "ALTER TABLE agenci_wyplaty ADD COLUMN data_platnosci DATE DEFAULT NULL",
                'data_utworzenia' => "ALTER TABLE agenci_wyplaty ADD COLUMN data_utworzenia TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP",
                'data_modyfikacji' => "ALTER TABLE agenci_wyplaty ADD COLUMN data_modyfikacji TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE current_timestamp"
            ];
            
            foreach ($requiredColumns as $column => $addQuery) {
                $columnExists = $this->pdo->query("SHOW COLUMNS FROM agenci_wyplaty LIKE '$column'")->fetchColumn();
                if (!$columnExists) {
                    $this->pdo->exec($addQuery);
                    error_log("Added column $column to agenci_wyplaty");
                }
            }
            
            // Check for orphaned records in agenci_wyplaty table
            $orphanedCheck = "SELECT aw.id_wyplaty, aw.id_sprawy 
                             FROM agenci_wyplaty aw 
                             LEFT JOIN sprawy s ON aw.id_sprawy = s.id_sprawy
                             WHERE s.id_sprawy IS NULL";
            $orphanedRecords = $this->pdo->query($orphanedCheck)->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($orphanedRecords)) {
                error_log("Found " . count($orphanedRecords) . " orphaned records in agenci_wyplaty");
                // We could delete them, but it's safer to just log them
            }
            
            // Fix any incorrect payment descriptions that don't follow the "Prowizja rata X" format
            $fixDescriptionsQuery = "UPDATE agenci_wyplaty 
                                   SET opis_raty = CONCAT('Prowizja rata ', SUBSTRING(opis_raty, -1)) 
                                   WHERE opis_raty LIKE '%rata%' 
                                   AND opis_raty NOT LIKE 'Prowizja rata %'";
            $fixCount = $this->pdo->exec($fixDescriptionsQuery);
            if ($fixCount > 0) {
                error_log("Fixed $fixCount payment descriptions in agenci_wyplaty");
            }
            
            // Check for records in agenci_wyplaty where there's no matching oplaty_spraw
            $missingOplatySql = "SELECT aw.id_wyplaty, aw.id_sprawy, aw.opis_raty
                                FROM agenci_wyplaty aw
                                LEFT JOIN oplaty_spraw os ON aw.id_sprawy = os.id_sprawy
                                AND CONVERT(REPLACE(aw.opis_raty, 'Prowizja ', '') USING utf8) = os.opis_raty
                                WHERE os.id_oplaty_sprawy IS NULL";
            $missingRecords = $this->pdo->query($missingOplatySql)->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($missingRecords)) {
                error_log("Found " . count($missingRecords) . " records in agenci_wyplaty without matching oplaty_spraw");
                
                foreach ($missingRecords as $record) {
                    // Create corresponding oplaty_spraw record
                    $rataDesc = str_replace('Prowizja ', '', $record['opis_raty']);
                    $insertSql = "INSERT INTO oplaty_spraw (id_sprawy, opis_raty, oczekiwana_kwota) 
                                 VALUES (?, ?, 0)";
                    $this->pdo->prepare($insertSql)->execute([
                        $record['id_sprawy'],
                        $rataDesc
                    ]);
                    error_log("Created missing oplaty_spraw record for case {$record['id_sprawy']}, rata $rataDesc");
                }
            }
        } catch (PDOException $e) {
            error_log("Error ensuring agenci_wyplaty table: " . $e->getMessage());
        }
    }
}
