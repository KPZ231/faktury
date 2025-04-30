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
            'installment3_paid'
        ];

        $cols = [];
        $values = [];

        foreach ($columns as $col) {
            $cols[] = "`$col`";
            $val = $data[$col] ?? null;
            if ($val === '' || $val === null) {
                $values[] = 'NULL';
            } else {
                // boolean fields
                if (in_array($col, ['is_completed', 'installment1_paid', 'installment2_paid', 'installment3_paid', 'final_installment_paid'])) {
                    $values[] = 0; // Zawsze ustawiamy wartość 0 dla pól typu boolean
                } else {
                    $values[] = $this->db->quote($val);
                }
            }
        }

        $sql = "INSERT INTO `test2` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $values) . ")";
        $this->db->exec($sql);

        header('Location: /wizard?success=1', true, 302);
        exit;
    }
}
