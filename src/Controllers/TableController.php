<?php

namespace Dell\Faktury\Controllers;

use PDO;
use PDOException;

class TableController
{
    private $pdo;

    public function __construct()
    {
        // Konstruktor bez parametrów - połączenie z bazą będzie inicjalizowane w index()
    }

    public function index(): void
    {
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        $this->pdo = $pdo;
        include __DIR__ . '/../Views/table.php';
    }

    /**
     * Calculate commission fields before displaying the table
     */
    public function calculateCommissions(): void
    {
        try {
            $query = "SELECT * FROM test2";
            $stmt = $this->pdo->query($query);
            $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($cases as $case) {
                $updated = false;
                $updates = [];
                $params = [];
                $id = $case['id'];

                // 1. Oblicz prowizję całkowitą
                if (empty($case['total_commission']) && !empty($case['amount_won']) && !empty($case['success_fee_percentage'])) {
                    $upfrontFee = !empty($case['upfront_fee']) ? floatval($case['upfront_fee']) : 0;
                    $amountWon = floatval($case['amount_won']);
                    $successFeePercentage = floatval($case['success_fee_percentage']) / 100;

                    $totalCommission = $upfrontFee + ($amountWon * $successFeePercentage);
                    $updates[] = "total_commission = :total_commission";
                    $params[':total_commission'] = $totalCommission;
                    $updated = true;
                } else {
                    $totalCommission = !empty($case['total_commission']) ? floatval($case['total_commission']) : 0;
                }

                // 2. Oblicz wypłatę dla Kuby jako procent (od 0 do 100)
                if (empty($case['kuba_payout']) && isset($case['kuba_percentage'])) {
                    $kubaPercentage = floatval($case['kuba_percentage']);

                    $agentPercentages = [
                        !empty($case['agent1_percentage']) ? floatval($case['agent1_percentage']) : 0,
                        !empty($case['agent2_percentage']) ? floatval($case['agent2_percentage']) : 0,
                        !empty($case['agent3_percentage']) ? floatval($case['agent3_percentage']) : 0,
                        !empty($case['agent4_percentage']) ? floatval($case['agent4_percentage']) : 0,
                        !empty($case['agent5_percentage']) ? floatval($case['agent5_percentage']) : 0
                    ];

                    $totalAgentPercentage = array_sum($agentPercentages);
                    $kubaPayout = $kubaPercentage - $totalAgentPercentage;

                    // Upewniamy się, że wynik nie przekroczy zakresu 0-100
                    $kubaPayout = max(0, min(100, $kubaPayout));

                    $updates[] = "kuba_payout = :kuba_payout";
                    $params[':kuba_payout'] = $kubaPayout;
                    $updated = true;
                }


                // 3. Ostatnia rata całości
                if (empty($case['final_installment_amount']) && $totalCommission > 0) {
                    $installments = [
                        !empty($case['installment1_amount']) ? floatval($case['installment1_amount']) : 0,
                        !empty($case['installment2_amount']) ? floatval($case['installment2_amount']) : 0,
                        !empty($case['installment3_amount']) ? floatval($case['installment3_amount']) : 0
                    ];
                    $finalInstallment = $totalCommission - array_sum($installments);

                    if ($finalInstallment > 0) {
                        $updates[] = "final_installment_amount = :final_installment_amount";
                        $params[':final_installment_amount'] = $finalInstallment;
                        $updated = true;
                    }
                }

                // 4. Ostatnia rata Kuby
                if (empty($case['kuba_final_installment_amount']) && !empty($case['kuba_payout'])) {
                    $kubaPayout = floatval($case['kuba_payout']);
                    $kubaInstallments = [
                        !empty($case['kuba_installment1_amount']) ? floatval($case['kuba_installment1_amount']) : 0,
                        !empty($case['kuba_installment2_amount']) ? floatval($case['kuba_installment2_amount']) : 0,
                        !empty($case['kuba_installment3_amount']) ? floatval($case['kuba_installment3_amount']) : 0
                    ];
                    $kubaFinalInstallment = $kubaPayout - array_sum($kubaInstallments);

                    if ($kubaFinalInstallment > 0) {
                        $updates[] = "kuba_final_installment_amount = :kuba_final_installment_amount";
                        $params[':kuba_final_installment_amount'] = $kubaFinalInstallment;
                        $updated = true;
                    }
                }

                // 5. Ostatnie raty agentów (dla 1 i 2 przykładowo, resztę możesz rozszerzyć tak samo)
                for ($i = 1; $i <= 3; $i++) {
                    $percentageField = "agent{$i}_percentage";
                    $payoutField = "agent{$i}_final_installment_amount";
                    $installmentFields = [
                        "agent{$i}_installment1_amount",
                        "agent{$i}_installment2_amount",
                        "agent{$i}_installment3_amount"
                    ];

                    if (!empty($case[$percentageField]) && empty($case[$payoutField]) && $totalCommission > 0) {
                        $agentPercentage = floatval($case[$percentageField]) / 100;
                        $agentPayout = $agentPercentage * $totalCommission;

                        $installments = array_map(function ($field) use ($case) {
                            return !empty($case[$field]) ? floatval($case[$field]) : 0;
                        }, $installmentFields);

                        $finalInstallment = $agentPayout - array_sum($installments);
                        if ($finalInstallment > 0) {
                            $updates[] = "$payoutField = :$payoutField";
                            $params[":$payoutField"] = $finalInstallment;
                            $updated = true;
                        }
                    }
                }

                // Zapisz zmiany w bazie
                if ($updated && !empty($updates)) {
                    $updateQuery = "UPDATE test2 SET " . implode(", ", $updates) . " WHERE id = :id";
                    $params[':id'] = $id;

                    $updateStmt = $this->pdo->prepare($updateQuery);
                    $updateStmt->execute($params);
                }
            }
        } catch (PDOException $e) {
            error_log('Error calculating commissions: ' . $e->getMessage());
        }
    }


    public function renderTable(): void
    {
        try {
            // Najpierw przeliczamy prowizje
            $this->calculateCommissions();

            // Zapytanie SQL z aliasami dla ładniejszych nagłówków
            $query = "SELECT 
                case_name AS 'Sprawa',
                CASE WHEN is_completed = 1 THEN 'Tak' WHEN is_completed = 0 THEN 'Nie' ELSE '' END AS 'Zakończona?',
                amount_won AS 'Wywalczona kwota',
                upfront_fee AS 'Opłata wstępna',
                success_fee_percentage AS 'Success fee %',
                total_commission AS 'Całość prowizji',
                kuba_percentage AS 'Prowizja % Kuba',
                kuba_payout AS 'Do wypłaty Kuba',
                agent1_percentage AS 'Prowizja % Agent 1',
                agent2_percentage AS 'Prowizja % Agent 2',
                agent3_percentage AS 'Prowizja % Agent 3',
                agent4_percentage AS 'Prowizja % Agent 4',
                agent5_percentage AS 'Prowizja % Agent 5',
                installment1_amount AS 'Rata 1',
                CASE WHEN installment1_paid = 1 THEN 'Tak' WHEN installment1_paid = 0 THEN 'Nie' END AS 'Opłacona?',
                installment2_amount AS 'Rata 2',
                CASE WHEN installment2_paid = 1 THEN 'Tak' WHEN installment2_paid = 0 THEN 'Nie' END AS 'Opłacona?',
                installment3_amount AS 'Rata 3',
                CASE WHEN installment3_paid = 1 THEN 'Tak' WHEN installment3_paid = 0 THEN 'Nie' END AS 'Opłacona?',
                final_installment_amount AS 'Rata ostatnia',
                CASE WHEN final_installment_paid = 1 THEN 'Tak' WHEN final_installment_paid = 0 THEN 'Nie' END AS 'Opłacona?',
                kuba_installment1_amount AS 'Rata 1 - Kuba',
                kuba_invoice_number AS 'Nr faktury',
                kuba_installment2_amount AS 'Rata 2 - Kuba',
                kuba_installment3_amount AS 'Rata 3 - Kuba',
                kuba_final_installment_amount AS 'Rata ostatnia - Kuba',
                agent1_installment1_amount AS 'Rata 1 - Agent 1',
                agent1_installment2_amount AS 'Rata 2 - Agent 1',
                agent1_installment3_amount AS 'Rata 3 - Agent 1',
                agent1_final_installment_amount AS 'Rata ostatnia - Agent 1',
                agent2_installment1_amount AS 'Rata 1 - Agent 2',
                agent2_installment2_amount AS 'Rata 2 - Agent 2',
                agent2_installment3_amount AS 'Rata 3 - Agent 2',
                agent2_final_installment_amount AS 'Rata ostatnia - Agent 2',
                agent3_installment1_amount AS 'Rata 1 - Agent 3',
                agent3_installment2_amount AS 'Rata 2 - Agent 3',
                agent3_installment3_amount AS 'Rata 3 - Agent 3',
                agent3_final_installment_amount AS 'Rata ostatnia - Agent 3'
            FROM test2 ORDER BY id DESC";

            $stmt = $this->pdo->query($query);

            if ($stmt->rowCount() > 0) {
                // Najpierw pobieramy wszystkie dane, aby sprawdzić, które kolumny zawierają dane
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($rows)) {
                    echo '<p style="text-align:center;">Brak danych.</p>';
                    return;
                }

                // Sprawdzamy, które kolumny zawierają jakiekolwiek niepuste dane
                $nonEmptyColumns = [];
                foreach ($rows as $row) {
                    foreach ($row as $column => $value) {
                        if ($value !== null && $value !== '') {
                            $nonEmptyColumns[$column] = true;
                        }
                    }
                }

                // Wyświetlamy tylko te kolumny, które mają jakiekolwiek dane
                echo '<table class="data-table"><thead><tr>';
                foreach (array_keys($nonEmptyColumns) as $col) {
                    echo '<th class="sortable" data-column="' . htmlspecialchars($col, ENT_QUOTES) . '">' .
                        htmlspecialchars($col, ENT_QUOTES) . '</th>';
                }
                echo '</tr></thead><tbody>';

                // Wyświetlamy dane tylko dla niepustych kolumn
                foreach ($rows as $row) {
                    echo '<tr>';
                    foreach ($row as $column => $value) {
                        if (isset($nonEmptyColumns[$column])) {
                            // Formatowanie kwot pieniężnych
                            if (
                                is_numeric($value) &&
                                (strpos($column, 'kwota') !== false ||
                                    strpos($column, 'fee') !== false ||
                                    strpos($column, 'amount') !== false ||
                                    strpos($column, 'payout') !== false ||
                                    strpos($column, 'commission') !== false ||
                                    strpos($column, 'Rata') !== false)
                            ) {
                                echo '<td>' . number_format((float)$value, 2, ',', ' ') . ' zł</td>';
                            }
                            // Formatowanie procentów
                            elseif (is_numeric($value) && strpos($column, '%') !== false) {
                                echo '<td>' . number_format((float)$value, 2, ',', ' ') . '%</td>';
                            } else {
                                echo '<td>' . htmlspecialchars($value !== null ? $value : '', ENT_QUOTES) . '</td>';
                            }
                        }
                    }
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p style="text-align:center;">Brak danych.</p>';
            }
        } catch (PDOException $e) {
            echo '<p style="color:red; text-align:center;">Błąd: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>';
        }
    }
}
