<?php

namespace Dell\Faktury\Controllers;

use PDO;

class WizardController
{
    protected PDO $db;

    public function __construct()
    {
        global $pdo; // PDO instance from config/database.php
        $this->db = $pdo;
    }

    // Wyświetla formularz wizard
    public function show(): void
    {
        include __DIR__ . '../../views/wizard.php';
    }

    // Przetwarza i zapisuje dane z formularza
    public function store(): void
    {
        $data = $_POST;

        // Lista kolumn zgodna ze strukturą tabeli test2 (pomijamy id, auto_increment)
        $columns = [
            'case_name',
            'is_completed',
            'amount_won',
            'upfront_fee',
            'success_fee_percentage',
            'kuba_percentage',
            'agent1_percentage',
            'agent2_percentage',
            'agent3_percentage',
            'agent4_percentage',
            'agent5_percentage',
            'installment1_amount',
            'installment1_paid',
            'installment2_amount',
            'installment2_paid',
            'installment3_amount',
            'installment3_paid',
            'final_installment_paid', // dodałem to pole
        ];

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
                continue;
            }

            // Pozostałe pola przetwarzamy normalnie
            $val = $data[$col] ?? null;
            if ($val === '' || $val === null) {
                $values[] = 'NULL';
            } else {
                $values[] = $this->db->quote($val);
            }
        }

        $sql = "INSERT INTO `test2` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $values) . ")";
        $this->db->exec($sql);
        
        // Pobierz ID ostatnio wstawionego rekordu
        $newCaseId = $this->db->lastInsertId();
        
        // 1. Aktualizacja wybranych agentów – dodajemy do ich kolumny "sprawy" ID sprawy
        for ($i = 1; $i <= 5; $i++) {
            if (isset($data["agent{$i}_id"]) && !empty($data["agent{$i}_id"])) {
                $agentId = (int)$data["agent{$i}_id"];
                $stmt = $this->db->prepare(
                    "UPDATE agenci 
                     SET sprawy = JSON_ARRAY_APPEND(sprawy, '$', ?)
                     WHERE agent_id = ?"
                );
                $stmt->execute([$newCaseId, $agentId]);
            }
        }
        
        // 2. Dodatkowo – zawsze dodajemy sprawę do agenta o imieniu Kuba.
        // Możemy najpierw wyszukać jego ID:
        $stmt = $this->db->prepare("SELECT agent_id FROM agenci WHERE LOWER(imie) = 'jakub' LIMIT 1");
        $stmt->execute();
        $kuba = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($kuba) {
            $stmt = $this->db->prepare(
                "UPDATE agenci 
                 SET sprawy = JSON_ARRAY_APPEND(sprawy, '$', ?)
                 WHERE agent_id = ?"
            );
            $stmt->execute([$newCaseId, $kuba['agent_id']]);
        }

        header('Location: /wizard?success=1', true, 302);
        exit;
    }
}
