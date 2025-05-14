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
        for ($i = 1; $i <= 3; $i++) {
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

        // Jeśli wykryto błąd walidacji, wypisz je i zatrzymaj działanie
        if (count($errors) > 0) {
            error_log("WizardController::store - Znaleziono " . count($errors) . " błędów walidacji");
            echo "<div style='color:red;'><h3>Błędy walidacji:</h3><ul>";
            foreach ($errors as $error) {
                echo "<li>" . htmlspecialchars($error, ENT_QUOTES) . "</li>";
            }
            echo "</ul></div>";
            exit;
        }

        // Jeśli walidacja się powiodła, przetwarzamy dalszą część zapisu
        error_log("WizardController::store - Walidacja zakończona sukcesem, przygotowanie do zapisu");

        // Lista kolumn zgodna ze strukturą tabeli test2 (pomijamy id, auto_increment)
        $columns = [
            'case_name',
            'is_completed',
            'amount_won',
            'upfront_fee',
            'success_fee_percentage',
            'kuba_percentage',
            'installment1_amount',
            'installment1_paid',
            'installment2_amount',
            'installment2_paid',
            'installment3_amount',
            'installment3_paid',
            'final_installment_paid', // dodałem to pole
        ];
        error_log("WizardController::store - Przygotowano " . count($columns) . " kolumn do zapisu");

        // Zdefiniuj od razu, które kolumny traktujesz jako boolean
        $booleanFields = [
            'is_completed',
            'installment1_paid',
            'installment2_paid',
            'installment3_paid',
            'final_installment_paid',
        ];

        $cols   = [];
        $values = [];

        foreach ($columns as $col) {
            $cols[] = "`$col`";

            // Jeśli to pole boolean – zawsze wstawiamy 0
            if (in_array($col, $booleanFields, true)) {
                $values[] = '0';
                error_log("WizardController::store - Pole boolean {$col}: ustawiono 0");
                continue;
            }

            // Pozostałe pola przetwarzamy normalnie
            $val = $data[$col] ?? null;
            if ($val === '' || $val === null) {
                $values[] = 'NULL';
                error_log("WizardController::store - Pole {$col}: ustawiono NULL");
            } else {
                $values[] = $this->db->quote($val);
                error_log("WizardController::store - Pole {$col}: ustawiono {$val}");
            }
        }

        $sql = "INSERT INTO `test2` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $values) . ")";
        error_log("WizardController::store - SQL insert: " . $sql);
        $this->db->exec($sql);
        
        // Pobierz ID ostatnio wstawionego rekordu
        $newCaseId = $this->db->lastInsertId();
        error_log("WizardController::store - Dodano nową sprawę, ID: {$newCaseId}");
        
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

        error_log("WizardController::store - Zakończono dodawanie sprawy, przekierowanie do /wizard");
        header('Location: /wizard?success=1', true, 302);
        exit;
    }
}