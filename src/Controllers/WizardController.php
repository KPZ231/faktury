<?php

namespace Dell\Faktury\Controllers;

use PDO;

class WizardController
{
    protected PDO $db;

    public function __construct()
    {
        error_log("WizardController::__construct - Inicjalizacja kontrolera wizarda");
        global $pdo; // PDO instance from config/database.php
        $this->db = $pdo;
        error_log("WizardController::__construct - Połączenie z bazą danych zainicjalizowane");
    }

    // Wyświetla formularz wizard
    public function show(): void
    {
        error_log("WizardController::show - Start");
        
        // Initialize variables for case data
        $caseData = null;
        $agents = [];
        
        // Check if we're editing an existing case (id passed in URL)
        $caseId = isset($_GET['id']) ? (int)$_GET['id'] : null;
        
        // If we have a case ID, fetch the data for editing
        if ($caseId) {
            error_log("WizardController::show - Editing existing case with ID: {$caseId}");
            $caseData = $this->getCaseData($caseId);
            
            if ($caseData) {
                // Store case data in the session for form population
                $_SESSION['wizard_form_data'] = $caseData;
                error_log("WizardController::show - Successfully loaded case data for editing");
            } else {
                error_log("WizardController::show - Failed to load case data for ID: {$caseId}");
                // Redirect to wizard with error message
                $_SESSION['wizard_errors'] = ["Nie znaleziono sprawy o ID: {$caseId}"];
                header('Location: /wizard', true, 302);
                exit;
            }
        }
        
        // Fetch buyers (nabywcy) from faktury table to populate case name dropdown
        try {
            $stmt = $this->db->prepare("SELECT DISTINCT Nabywca FROM faktury WHERE Nabywca IS NOT NULL ORDER BY Nabywca");
            $stmt->execute();
            $buyers = $stmt->fetchAll(PDO::FETCH_COLUMN);
            error_log("WizardController::show - Fetched " . count($buyers) . " buyers from faktury table");
        } catch (\PDOException $e) {
            error_log("WizardController::show - Error fetching buyers: " . $e->getMessage());
            $buyers = [];
        }
        
        // Fetch all agents for dropdown selection
        try {
            $stmt = $this->db->prepare("SELECT id_agenta, nazwa_agenta FROM agenci ORDER BY nazwa_agenta");
            $stmt->execute();
            $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("WizardController::show - Fetched " . count($agents) . " agents from agenci table");
        } catch (\PDOException $e) {
            error_log("WizardController::show - Error fetching agents: " . $e->getMessage());
        }
        
        include __DIR__ . '../../views/wizard.php';
        error_log("WizardController::show - Widok wizarda wyrenderowany");
    }

    // Przetwarza i zapisuje dane z formularza z walidacją
    public function store(): void
    {
        error_log("WizardController::store - Start");
        $data = $_POST;
        error_log("WizardController::store - Otrzymane dane: " . print_r($data, true));
        $errors = [];

        // Check if we're updating an existing case
        $isUpdate = isset($data['case_id']) && !empty($data['case_id']);
        $caseId = $isUpdate ? (int)$data['case_id'] : null;
        
        if ($isUpdate) {
            error_log("WizardController::store - Updating existing case with ID: {$caseId}");
        } else {
            error_log("WizardController::store - Creating new case");
        }

        // Sprawdź, czy mamy nazwę sprawy (albo z selecta albo z ręcznego wprowadzania)
        $hasValidCaseName = false;
        
        // Priorytet dla ręcznie wprowadzonej nazwy
        if (isset($data['manual_case_name']) && trim($data['manual_case_name']) !== '') {
            error_log("WizardController::store - Używam ręcznie wprowadzonej nazwy sprawy: " . $data['manual_case_name']);
            $data['case_name'] = trim($data['manual_case_name']);
            $hasValidCaseName = true;
        } 
        // Jeśli nie ma ręcznej nazwy, sprawdź czy wybrano z listy
        elseif (isset($data['case_name']) && trim($data['case_name']) !== '') {
            error_log("WizardController::store - Używam nazwy sprawy wybranej z listy: " . $data['case_name']);
            $hasValidCaseName = true;
        } 
        else {
            error_log("WizardController::store - BŁĄD: Brak nazwy sprawy");
            $errors[] = "Nazwa sprawy jest wymagana. Wybierz z listy lub wprowadź ręcznie.";
        }

        // Walidacja procentowych pól prowizji
        error_log("WizardController::store - Rozpoczęcie walidacji danych");

        // 1. Walidacja prowizji Kuby
        if (isset($data['kuba_percentage']) && $data['kuba_percentage'] !== '') {
            $kubaPercentage = floatval($data['kuba_percentage']);
            error_log("WizardController::store - Prowizja Kuby: {$kubaPercentage}");
            if ($kubaPercentage < 0 || $kubaPercentage > 100) {
                error_log("WizardController::store - BŁĄD: Prowizja Kuby poza zakresem 0-100");
                $errors[] = "Prowizja Kuby musi być wartością z przedziału 0-100.";
            }
        } else {
            error_log("WizardController::store - BŁĄD: Brak prowizji Kuby");
            $errors[] = "Prowizja Kuby jest wymagana.";
            $kubaPercentage = 0;
        }

        // 2. Walidacja prowizji agentów
        $agentPercents = [];
        for ($i = 1; $i <= 5; $i++) {
            if (isset($data["agent{$i}_percentage"]) && $data["agent{$i}_percentage"] !== '') {
                $agentValue = floatval($data["agent{$i}_percentage"]);
                error_log("WizardController::store - Prowizja Agenta {$i}: {$agentValue}");
                if ($agentValue < 0 || $agentValue > 100) {
                    error_log("WizardController::store - BŁĄD: Prowizja Agenta {$i} poza zakresem 0-100");
                    $errors[] = "Prowizja agenta {$i} musi być wartością z przedziału 0-100.";
                }
                if ($agentValue > $kubaPercentage) {
                    error_log("WizardController::store - BŁĄD: Prowizja Agenta {$i} > prowizja Kuby");
                    $errors[] = "Prowizja agenta {$i} nie może być większa niż prowizja Kuby ({$kubaPercentage}%).";
                }
                $agentPercents[] = $agentValue;
            } else {
                error_log("WizardController::store - Agent {$i} nie ma określonej prowizji");
            }
        }
        if (array_sum($agentPercents) > $kubaPercentage) {
            error_log("WizardController::store - BŁĄD: Suma prowizji agentów > prowizja Kuby");
            $errors[] = "Suma prowizji agentów (" . array_sum($agentPercents) . "%) nie może być większa niż prowizja Kuby ({$kubaPercentage}%).";
        }

        // 3. Walidacja innych pól numerycznych
        $fieldsToValidate = ['amount_won', 'upfront_fee', 'success_fee_percentage'];
        foreach ($fieldsToValidate as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                error_log("WizardController::store - Walidacja pola {$field}: " . $data[$field]);
                if (!is_numeric($data[$field]) || floatval($data[$field]) < 0) {
                    error_log("WizardController::store - BŁĄD: Pole {$field} ma nieprawidłową wartość");
                    $errors[] = "Pole '{$field}' musi być liczbą nieujemną.";
                }
            } else {
                error_log("WizardController::store - Pole {$field} nie jest ustawione");
            }
        }

        // 4. Walidacja pól rat (jeśli są podane)
        $totalInstallments = 0;
        for ($i = 1; $i <= 6; $i++) {
            $installmentField = "installment{$i}_amount";
            if (isset($data[$installmentField]) && $data[$installmentField] !== '') {
                error_log("WizardController::store - Walidacja raty {$i}: " . $data[$installmentField]);
                if (!is_numeric($data[$installmentField]) || floatval($data[$installmentField]) < 0) {
                    error_log("WizardController::store - BŁĄD: Rata {$i} ma nieprawidłową wartość");
                    $errors[] = "Rata {$i} musi być liczbą nieujemną.";
                } else {
                    $totalInstallments += floatval($data[$installmentField]);
                }
            } else {
                error_log("WizardController::store - Rata {$i} nie jest ustawiona");
            }
        }
        
        // 5. Sprawdzenie czy suma rat jest równa opłacie wstępnej
        if (isset($data['upfront_fee']) && $data['upfront_fee'] !== '') {
            $upfrontFee = floatval($data['upfront_fee']);
            if ($totalInstallments != $upfrontFee) {
                error_log("WizardController::store - BŁĄD: Suma rat ($totalInstallments) nie jest równa opłacie wstępnej ($upfrontFee)");
                $errors[] = "Suma rat ({$totalInstallments} zł) musi być równa opłacie wstępnej ({$upfrontFee} zł).";
            }
        }

        // Jeśli wykryto błąd walidacji, przekieruj z powrotem do formularza z błędami
        if (count($errors) > 0) {
            error_log("WizardController::store - Znaleziono " . count($errors) . " błędów walidacji");
            
            // Przygotuj komunikat błędu i przekieruj do formularza
            $_SESSION['wizard_errors'] = $errors;
            $_SESSION['wizard_form_data'] = $data; // Zachowanie danych formularza

            // If updating, include the case ID in the redirect
            $redirectUrl = $isUpdate ? "/wizard?id={$caseId}" : "/wizard";
            header('Location: ' . $redirectUrl, true, 302);
            exit;
        }

        // Jeśli walidacja się powiodła, przetwarzamy dalszą część zapisu
        error_log("WizardController::store - Walidacja zakończona sukcesem, przygotowanie do zapisu");

        // Lista kolumn zgodna ze strukturą tabeli sprawy (pomijamy id_sprawy, auto_increment)
        $columns = [
            'identyfikator_sprawy', // zamiast case_name
            'czy_zakonczona',       // zamiast is_completed
            'wywalczona_kwota',     // zamiast amount_won
            'oplata_wstepna',       // zamiast upfront_fee
            'stawka_success_fee',   // zamiast success_fee_percentage (wymaga konwersji)
            'uwagi'                 // nowe pole na ewentualne uwagi
        ];
        error_log("WizardController::store - Przygotowano " . count($columns) . " kolumn do zapisu");

        // Zdefiniuj, które kolumny traktujesz jako boolean
        $booleanFields = [
            'czy_zakonczona',
        ];

        // Mapowanie pól formularza na kolumny tabeli
        $fieldMapping = [
            'identyfikator_sprawy' => 'case_name',
            'czy_zakonczona' => 'is_completed',
            'wywalczona_kwota' => 'amount_won',
            'oplata_wstepna' => 'upfront_fee',
            'stawka_success_fee' => 'success_fee_percentage',
            'uwagi' => 'uwagi'
        ];

        $cols   = [];
        $values = [];

        foreach ($columns as $col) {
            $cols[] = "`$col`";

            // Jeśli to pole boolean – zawsze wstawiamy wartość z formularza lub 0
            if (in_array($col, $booleanFields, true)) {
                $formField = $fieldMapping[$col];
                $value = isset($data[$formField]) ? 1 : 0; // checkbox - jeśli istnieje to 1, jeśli nie to 0
                $values[] = $value;
                error_log("WizardController::store - Pole boolean {$col}: ustawiono {$value}");
                continue;
            }

            // Specjalna obsługa dla stawki success fee - konwersja z procentów (np. 8%) na format dziesiętny (0.0800)
            if ($col === 'stawka_success_fee') {
                $formField = $fieldMapping[$col];
                if (isset($data[$formField]) && $data[$formField] !== '') {
                    $successFee = floatval($data[$formField]) / 100; // Konwersja z % na format dziesiętny
                    $values[] = $this->db->quote($successFee);
                    error_log("WizardController::store - Pole {$col}: ustawiono {$successFee} (z {$data[$formField]}%)");
                } else {
                    $values[] = 'NULL';
                    error_log("WizardController::store - Pole {$col}: ustawiono NULL");
                }
                continue;
            }

            // Pozostałe pola przetwarzamy normalnie
            $formField = $fieldMapping[$col];
            $val = $data[$formField] ?? null;
            if ($val === '' || $val === null) {
                $values[] = 'NULL';
                error_log("WizardController::store - Pole {$col}: ustawiono NULL");
            } else {
                $values[] = $this->db->quote($val);
                error_log("WizardController::store - Pole {$col}: ustawiono {$val}");
            }
        }

        // If we're updating an existing case
        if ($isUpdate) {
            // Prepare SET statements for UPDATE
            $setStatements = [];
            for ($i = 0; $i < count($columns); $i++) {
                $setStatements[] = "`{$columns[$i]}` = {$values[$i]}";
            }
            
            $sql = "UPDATE `sprawy` SET " . implode(', ', $setStatements) . " WHERE id_sprawy = " . $this->db->quote($caseId);
            error_log("WizardController::store - SQL update: " . $sql);
            $this->db->exec($sql);
            
            // Delete existing agent associations to recreate them
            $this->db->exec("DELETE FROM prowizje_agentow_spraw WHERE id_sprawy = " . $this->db->quote($caseId));
            error_log("WizardController::store - Deleted existing agent associations for case ID: {$caseId}");
            
            // Delete existing installments to recreate them
            $this->db->exec("DELETE FROM oplaty_spraw WHERE id_sprawy = " . $this->db->quote($caseId));
            error_log("WizardController::store - Deleted existing installments for case ID: {$caseId}");
            
            $newCaseId = $caseId;
        } else {
            // Insert new case
            $sql = "INSERT INTO `sprawy` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $values) . ")";
            error_log("WizardController::store - SQL insert: " . $sql);
            $this->db->exec($sql);
            
            // Pobierz ID ostatnio wstawionego rekordu
            $newCaseId = $this->db->lastInsertId();
            error_log("WizardController::store - Dodano nową sprawę, ID: {$newCaseId}");
        }
        
        // Dodajemy powiązania agentów z nową sprawą
        error_log("WizardController::store - Rozpoczęcie dodawania powiązań agentów");
        for ($i = 1; $i <= 5; $i++) {
            if (isset($data["agent{$i}_id_agenta"]) && !empty($data["agent{$i}_id_agenta"]) && 
                isset($data["agent{$i}_percentage"]) && $data["agent{$i}_percentage"] !== '') {
                
                $agentId = (int)$data["agent{$i}_id_agenta"];
                $percentage = (float)$data["agent{$i}_percentage"];
                error_log("WizardController::store - Dodawanie powiązania dla Agenta {$i}, ID: {$agentId}, procent: {$percentage}");
                
                // 1. Dodaj wpis do tabeli prowizje_agentow_spraw
                $stmt = $this->db->prepare(
                    "INSERT INTO prowizje_agentow_spraw (id_sprawy, id_agenta, udzial_prowizji_proc) 
                     VALUES (?, ?, ?)"
                );
                $stmt->execute([$newCaseId, $agentId, $percentage/100]); // Dzielimy przez 100, bo w bazie jest w formacie 0.xxxx
                error_log("WizardController::store - Dodano powiązanie w tabeli prowizje_agentow_spraw");
            } else {
                error_log("WizardController::store - Agent {$i} nie jest wybrany lub nie ma określonego procentu");
            }
        }
        
        // 2. Dodatkowo – zawsze dodajemy sprawę do agenta o imieniu Kuba.
        // Możemy najpierw wyszukać jego ID:
        error_log("WizardController::store - Wyszukiwanie agenta Kuba");
        $stmt = $this->db->prepare("SELECT id_agenta FROM agenci WHERE LOWER(nazwa_agenta) IN ('jakub', 'kuba') LIMIT 1");
        $stmt->execute();
        $kuba = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($kuba) {
            error_log("WizardController::store - Znaleziono Kubę, id_agenta: " . $kuba['id_agenta']);
            // Sprawdź, czy Kuba nie jest już przypisany do tej sprawy
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM prowizje_agentow_spraw 
                 WHERE id_sprawy = ? AND id_agenta = ?"
            );
            $stmt->execute([$newCaseId, $kuba['id_agenta']]);
            $exists = (int)$stmt->fetchColumn();
            error_log("WizardController::store - Kuba już przypisany do tej sprawy: " . ($exists ? 'Tak' : 'Nie'));
            
            if ($exists === 0) {
                error_log("WizardController::store - Dodawanie Kuby z procentem: " . $kubaPercentage);
                // Dodaj Kubę z procentem z formularza
                $stmt = $this->db->prepare(
                    "INSERT INTO prowizje_agentow_spraw (id_sprawy, id_agenta, udzial_prowizji_proc) 
                     VALUES (?, ?, ?)"
                );
                $stmt->execute([$newCaseId, $kuba['id_agenta'], $kubaPercentage/100]); // Dzielimy przez 100, bo w bazie jest w formacie 0.xxxx
                error_log("WizardController::store - Zaktualizowano dane Kuby");
            }
        } else {
            error_log("WizardController::store - UWAGA: Nie znaleziono agenta Kuba w bazie danych");
        }
        
        // Dodajemy raty do tabeli oplaty_spraw
        error_log("WizardController::store - Rozpoczęcie dodawania rat do tabeli oplaty_spraw");
        
        // Policz liczbę rat na podstawie danych z formularza
        $installmentsCount = 0;
        for ($i = 1; $i <= 6; $i++) { // Maksymalnie 6 rat
            $installmentField = "installment{$i}_amount";
            if (isset($data[$installmentField]) && $data[$installmentField] !== '' && floatval($data[$installmentField]) > 0) {
                $installmentsCount++;
            }
        }
        
        error_log("WizardController::store - Wykryto {$installmentsCount} rat do dodania");
        
        // Dodaj raty do tabeli oplaty_spraw
        for ($i = 1; $i <= $installmentsCount; $i++) {
            $installmentField = "installment{$i}_amount";
            if (isset($data[$installmentField]) && $data[$installmentField] !== '' && floatval($data[$installmentField]) > 0) {
                $amount = floatval($data[$installmentField]);
                error_log("WizardController::store - Dodawanie raty {$i}, kwota: {$amount} zł");
                
                $stmt = $this->db->prepare(
                    "INSERT INTO oplaty_spraw (id_sprawy, opis_raty, oczekiwana_kwota, czy_oplacona) 
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([$newCaseId, "Rata {$i}", $amount, 0]);
                error_log("WizardController::store - Dodano ratę {$i} do tabeli oplaty_spraw");
            }
        }
        
        // Sprawdź, czy należy dodać ratę ostateczną na podstawie całkowitej prowizji
        $upfrontFee = floatval($data['upfront_fee'] ?? 0);
        $amountWon = floatval($data['amount_won'] ?? 0);
        $successFeePercentage = floatval($data['success_fee_percentage'] ?? 0);
        
        $totalCommission = $upfrontFee + ($amountWon * ($successFeePercentage / 100));
        $totalInstallments = 0;
        
        for ($i = 1; $i <= $installmentsCount; $i++) {
            $installmentField = "installment{$i}_amount";
            if (isset($data[$installmentField]) && $data[$installmentField] !== '') {
                $totalInstallments += floatval($data[$installmentField]);
            }
        }
        
        $finalInstallment = $totalCommission - $totalInstallments;
        if ($finalInstallment > 0) {
            error_log("WizardController::store - Dodawanie ostatniej raty, kwota: {$finalInstallment} zł");
            
            $stmt = $this->db->prepare(
                "INSERT INTO oplaty_spraw (id_sprawy, opis_raty, oczekiwana_kwota, czy_oplacona) 
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$newCaseId, "Rata końcowa", $finalInstallment, 0]);
            error_log("WizardController::store - Dodano ostatnią ratę do tabeli oplaty_spraw");
        }

        error_log("WizardController::store - Zakończono " . ($isUpdate ? "aktualizację" : "dodawanie") . " sprawy, przekierowanie do /wizard");
        
        // Ustaw komunikat powodzenia i przekieruj
        header('Location: /wizard?success=1', true, 302);
        exit;
    }

    /**
     * Get case data for editing
     * 
     * @param int $caseId The ID of the case to retrieve
     * @return array|null Case data or null if not found
     */
    private function getCaseData(int $caseId): ?array
    {
        error_log("WizardController::getCaseData - Loading case with ID: {$caseId}");
        
        try {
            // Fetch basic case data
            $stmt = $this->db->prepare("
                SELECT 
                    id_sprawy,
                    identyfikator_sprawy,
                    czy_zakonczona,
                    wywalczona_kwota,
                    oplata_wstepna,
                    stawka_success_fee,
                    uwagi
                FROM sprawy
                WHERE id_sprawy = ?
            ");
            $stmt->execute([$caseId]);
            $caseData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$caseData) {
                error_log("WizardController::getCaseData - Case not found with ID: {$caseId}");
                return null;
            }
            
            // Map database fields to form fields
            $formData = [
                'case_id' => $caseData['id_sprawy'],
                'case_name' => $caseData['identyfikator_sprawy'],
                'manual_case_name' => $caseData['identyfikator_sprawy'],
                'is_completed' => $caseData['czy_zakonczona'] ? 1 : 0,
                'amount_won' => $caseData['wywalczona_kwota'],
                'upfront_fee' => $caseData['oplata_wstepna'],
                'success_fee_percentage' => $caseData['stawka_success_fee'] * 100, // Convert from decimal to percentage
                'uwagi' => $caseData['uwagi'] ?? '',
            ];
            
            // Get Kuba's commission percentage
            $stmt = $this->db->prepare("
                SELECT pas.udzial_prowizji_proc
                FROM prowizje_agentow_spraw pas
                JOIN agenci a ON pas.id_agenta = a.id_agenta
                WHERE pas.id_sprawy = ?
                AND LOWER(a.nazwa_agenta) IN ('jakub', 'kuba')
                LIMIT 1
            ");
            $stmt->execute([$caseId]);
            $kubaData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($kubaData) {
                $formData['kuba_percentage'] = $kubaData['udzial_prowizji_proc'] * 100; // Convert from decimal to percentage
            }
            
            // Get agents associated with this case
            $stmt = $this->db->prepare("
                SELECT pas.id_agenta, pas.udzial_prowizji_proc
                FROM prowizje_agentow_spraw pas
                JOIN agenci a ON pas.id_agenta = a.id_agenta
                WHERE pas.id_sprawy = ?
                AND LOWER(a.nazwa_agenta) NOT IN ('jakub', 'kuba')
            ");
            $stmt->execute([$caseId]);
            $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add agent data to form data
            $agentIndex = 1;
            foreach ($agents as $agent) {
                if ($agentIndex <= 5) { // Maximum 5 agents
                    $formData["agent{$agentIndex}_id_agenta"] = $agent['id_agenta'];
                    $formData["agent{$agentIndex}_percentage"] = $agent['udzial_prowizji_proc'] * 100; // Convert from decimal to percentage
                    $agentIndex++;
                }
            }
            
            // Get installments
            $stmt = $this->db->prepare("
                SELECT opis_raty, oczekiwana_kwota
                FROM oplaty_spraw
                WHERE id_sprawy = ?
                AND opis_raty != 'Rata końcowa'
                ORDER BY opis_raty
            ");
            $stmt->execute([$caseId]);
            $installments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add installment data to form data
            $installmentIndex = 1;
            foreach ($installments as $installment) {
                if ($installmentIndex <= 6) { // Maximum 6 installments
                    // Extract installment number from "Rata X"
                    if (preg_match('/Rata (\d+)/', $installment['opis_raty'], $matches)) {
                        $index = (int)$matches[1];
                        if ($index >= 1 && $index <= 6) {
                            $formData["installment{$index}_amount"] = $installment['oczekiwana_kwota'];
                        }
                    }
                    $installmentIndex++;
                }
            }
            
            error_log("WizardController::getCaseData - Successfully loaded case data with " . count($agents) . " agents and " . count($installments) . " installments");
            return $formData;
            
        } catch (\PDOException $e) {
            error_log("WizardController::getCaseData - Error loading case data: " . $e->getMessage());
            return null;
        }
    }
}