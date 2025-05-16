<?php

namespace Dell\Faktury\Controllers;

use PDO;
use PDOException;

class TestController {
    private $pdo;

    /**
     * Delete a case and all related records
     */
    public function delete(int $id): void {
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        $this->pdo = $pdo;

        try {
            $this->pdo->beginTransaction();

            // Delete related records first
            $tables = [
                'agenci_wyplaty',
                'prowizje_agentow_spraw',
                'oplaty_spraw'
            ];

            foreach ($tables as $table) {
                $stmt = $this->pdo->prepare("DELETE FROM $table WHERE id_sprawy = ?");
                $stmt->execute([$id]);
            }

            // Finally delete the case itself
            $stmt = $this->pdo->prepare("DELETE FROM sprawy WHERE id_sprawy = ?");
            $stmt->execute([$id]);

            $this->pdo->commit();
            header('Location: /test?deleted=1');
            exit;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error deleting case: " . $e->getMessage());
            http_response_code(500);
            echo "Wystąpił błąd podczas usuwania sprawy.";
        }
    }

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
        
        // Pobierz ID agenta z parametru URL
        $selectedAgentId = isset($_GET['agent_id']) ? $_GET['agent_id'] : null;
        
        // Przekaż ID agenta do widoku
        $filterByAgentId = $selectedAgentId;
        
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
    public function syncPaymentStatuses(): void {
        try {
            // Globalnie czyścimy wszystkie przypisania faktur - aby zapewnić, że faktury zostaną przypisane na nowo
            $globalResetQuery = "UPDATE oplaty_spraw SET faktura_id = NULL, czy_oplacona = 0 WHERE opis_raty LIKE 'Rata %'"; 
            $this->pdo->exec($globalResetQuery);
            error_log("Globally reset all invoice assignments");
            // First, ensure the agenci_wyplaty table is properly configured
            $this->ensureAgenciWyplatyTable();
            
            // Update payment statuses based on agenci_wyplaty table - handle all formats
            $updateQuery = "UPDATE oplaty_spraw os
                           JOIN agenci_wyplaty aw ON os.id_sprawy = aw.id_sprawy
                           AND (
                               REPLACE(aw.opis_raty, 'Prowizja ', '') = os.opis_raty COLLATE utf8mb4_polish_ci
                               OR REPLACE(aw.opis_raty, 'Prowizja Rata ', '') = REPLACE(os.opis_raty, 'Rata ', '') COLLATE utf8mb4_polish_ci
                               OR REPLACE(aw.opis_raty, 'Prowizja rata ', '') = REPLACE(os.opis_raty, 'Rata ', '') COLLATE utf8mb4_polish_ci
                           )
                           SET os.czy_oplacona = 1
                           WHERE aw.czy_oplacone = 1";
            $this->pdo->exec($updateQuery);
            
            // Chronologiczne przypisanie faktur do rat - najstarsze faktury do raty 1, nowsze do kolejnych
            // 1. Pobierz wszystkie sprawy
            $sprawyQuery = "SELECT DISTINCT id_sprawy FROM sprawy";
            $sprawy = $this->pdo->query($sprawyQuery)->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($sprawy as $id_sprawy) {
                // 2. Pobierz identyfikator sprawy (potrzebny do wyszukiwania faktur)
                $sprawaQuery = "SELECT identyfikator_sprawy FROM sprawy WHERE id_sprawy = ?";
                $stmt = $this->pdo->prepare($sprawaQuery);
                $stmt->execute([$id_sprawy]);
                $identyfikator_sprawy = $stmt->fetchColumn();
                
                if (empty($identyfikator_sprawy)) {
                    continue; // Pomiń jeśli nie ma identyfikatora
                }
                
                // 3. Pobierz wszystkie raty dla tej sprawy
                $ratyQuery = "SELECT id_oplaty_sprawy, opis_raty, oczekiwana_kwota FROM oplaty_spraw 
                             WHERE id_sprawy = ? AND opis_raty LIKE 'Rata %' 
                             ORDER BY CASE 
                                WHEN opis_raty = 'Rata 1' THEN 1
                                WHEN opis_raty = 'Rata 2' THEN 2
                                WHEN opis_raty = 'Rata 3' THEN 3
                                WHEN opis_raty = 'Rata 4' THEN 4
                                WHEN opis_raty = 'Rata 5' THEN 5
                                WHEN opis_raty = 'Rata 6' THEN 6
                                ELSE 100 END";
                $ratyStmt = $this->pdo->prepare($ratyQuery);
                $ratyStmt->execute([$id_sprawy]);
                $raty = $ratyStmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($raty)) {
                    continue; // Pomiń jeśli nie ma rat
                }
                
                // 4. Pobierz wszystkie faktury dla tej sprawy
                $fakturyQuery = "SELECT numer, `Data wystawienia`, `Data płatności`, `Kwota opłacona` 
                                FROM faktury 
                                WHERE Nabywca = ? AND Status = 'Opłacona'";
                $fakturyStmt = $this->pdo->prepare($fakturyQuery);
                $fakturyStmt->execute([$identyfikator_sprawy]);
                $faktury = $fakturyStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Dodaj również faktury już przypisane do tej sprawy, ale czyścimy je przed nowym przypisaniem
                $przypisaneFakturyQuery = "SELECT f.numer, f.`Data wystawienia`, f.`Data płatności`, f.`Kwota opłacona` 
                                         FROM faktury f
                                         JOIN oplaty_spraw os ON f.numer = os.faktura_id
                                         WHERE os.id_sprawy = ?";
                $przypisaneFakturyStmt = $this->pdo->prepare($przypisaneFakturyQuery);
                $przypisaneFakturyStmt->execute([$id_sprawy]);
                $przypisaneFaktury = $przypisaneFakturyStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Faktury już nie mają przypisań dzięki globalnemu czyszczeniu, więc wszystkie są dostępne
                // Usuwamy duplikaty (faktury mogły pojawić się zarówno w $faktury jak i $przypisaneFaktury)
                $wszystkieFaktury = array_unique(array_merge($faktury, $przypisaneFaktury), SORT_REGULAR);
                
                // Dodaj log z informacją o liczbie faktur dla tej sprawy
                error_log("Found " . count($wszystkieFaktury) . " invoices for case ID: $id_sprawy, identifier: $identyfikator_sprawy");
                
                if (empty($wszystkieFaktury)) {
                    continue; // Pomiń jeśli nie ma faktur
                }
                
                // 5. Sortuj faktury według daty wystawienia (od najstarszej do najnowszej)
                usort($wszystkieFaktury, function($a, $b) {
                    // Pierwszeństwo dla daty wystawienia
                    $dateA = !empty($a['Data wystawienia']) ? strtotime($a['Data wystawienia']) : PHP_INT_MAX;
                    $dateB = !empty($b['Data wystawienia']) ? strtotime($b['Data wystawienia']) : PHP_INT_MAX;
                    
                    if ($dateA != $dateB) {
                        return $dateA - $dateB; // Od najstarszej do najnowszej
                    }
                    
                    // Jeśli daty wystawienia są takie same, sprawdź datę płatności
                    $payDateA = !empty($a['Data płatności']) ? strtotime($a['Data płatności']) : PHP_INT_MAX;
                    $payDateB = !empty($b['Data płatności']) ? strtotime($b['Data płatności']) : PHP_INT_MAX;
                    
                    if ($payDateA != $payDateB) {
                        return $payDateA - $payDateB; // Od najstarszej do najnowszej
                    }
                    
                    // Jeśli obie daty są takie same, sprawdź numer faktury
                    if (preg_match('/FV\/([0-9]+)\//', $a['numer'], $matchesA) && 
                        preg_match('/FV\/([0-9]+)\//', $b['numer'], $matchesB)) {
                        return intval($matchesA[1]) - intval($matchesB[1]); // Po numerze faktury
                    }
                    
                    return 0;
                });
                
                // 6. Przypisz faktury do rat w kolejności chronologicznej
                $i = 0;
                foreach ($raty as $rata) {
                    if ($i < count($wszystkieFaktury)) {
                        $faktura = $wszystkieFaktury[$i];
                        $kwotaRaty = floatval($rata['oczekiwana_kwota'] ?? 0);
                        $kwotaFaktury = floatval($faktura['Kwota opłacona'] ?? 0);
                        
                        // Sprawdź, czy kwota faktury odpowiada kwocie raty
                        // Zastosuj margines błędu (epsilon) przy porównywaniu kwot
                        $epsilon = 0.01;
                        if (abs($kwotaRaty - $kwotaFaktury) <= $epsilon || $kwotaRaty == 0 || $kwotaFaktury == 0) {
                            // Przypisz fakturę do raty
                            $updateQuery = "UPDATE oplaty_spraw SET faktura_id = ?, czy_oplacona = 1 WHERE id_oplaty_sprawy = ?";
                            $this->pdo->prepare($updateQuery)->execute([$faktura['numer'], $rata['id_oplaty_sprawy']]);
                            $i++; // Przejdź do następnej faktury
                        }
                    }
                }
            }
            
            // Uzupełniamy faktury dla rat, które mogły zostać pominięte powyżej
            $updateInvoicesQuery = "UPDATE oplaty_spraw os
                                   JOIN agenci_wyplaty aw ON os.id_sprawy = aw.id_sprawy
                                   AND (
                                       REPLACE(aw.opis_raty, 'Prowizja ', '') = os.opis_raty COLLATE utf8mb4_polish_ci
                                       OR REPLACE(aw.opis_raty, 'Prowizja Rata ', '') = REPLACE(os.opis_raty, 'Rata ', '') COLLATE utf8mb4_polish_ci
                                       OR REPLACE(aw.opis_raty, 'Prowizja rata ', '') = REPLACE(os.opis_raty, 'Rata ', '') COLLATE utf8mb4_polish_ci
                                   )
                                   SET os.faktura_id = aw.numer_faktury
                                   WHERE aw.czy_oplacone = 1 
                                   AND aw.numer_faktury IS NOT NULL 
                                   AND aw.numer_faktury != ''
                                   AND os.faktura_id IS NULL";
            $this->pdo->exec($updateInvoicesQuery);
            
            // Check for missing prowizje_agentow_spraw entries
            $this->checkAgentCommissions();
            
            // Standardize payment description formats
            $standardizeDescriptions = [
                // Fix "Prowizja rata X" -> "Prowizja Rata X"
                "UPDATE agenci_wyplaty 
                 SET opis_raty = CONCAT('Prowizja Rata ', SUBSTRING(opis_raty, LENGTH('Prowizja rata ') + 1))
                 WHERE opis_raty LIKE 'Prowizja rata %'",
                
                // Fix "Prowizja RataX" -> "Prowizja Rata X"
                "UPDATE agenci_wyplaty 
                 SET opis_raty = CONCAT('Prowizja Rata ', SUBSTRING(opis_raty, LENGTH('Prowizja Rata')))
                 WHERE opis_raty LIKE 'Prowizja Rata_' AND opis_raty NOT LIKE 'Prowizja Rata %'",
                
                // Fix "Prowizja rataX" -> "Prowizja Rata X"
                "UPDATE agenci_wyplaty 
                 SET opis_raty = CONCAT('Prowizja Rata ', SUBSTRING(opis_raty, LENGTH('Prowizja rata')))
                 WHERE opis_raty LIKE 'Prowizja rata_' AND opis_raty NOT LIKE 'Prowizja rata %'"
            ];
            
            foreach ($standardizeDescriptions as $query) {
                $affectedRows = $this->pdo->exec($query);
                if ($affectedRows > 0) {
                    error_log("Standardized {$affectedRows} payment descriptions with: {$query}");
                }
            }
            
            // Update all agent payments in database to load at page refresh
            $refreshAgentPayments = "UPDATE agenci_wyplaty
                                    SET data_modyfikacji = NOW()
                                    WHERE czy_oplacone = 1";
            $this->pdo->exec($refreshAgentPayments);
            
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
