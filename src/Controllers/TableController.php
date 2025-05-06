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

        // Pobierz listę agentów
        $agents = $this->getAgents();

        // Jeśli wybrano agenta, wyświetl sprawy tego agenta
        $selectedAgentId = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : null;
        $selectedAgent = null;

        if ($selectedAgentId) {
            foreach ($agents as $agent) {
                if ($agent['agent_id'] == $selectedAgentId) {
                    $selectedAgent = $agent;
                    break;
                }
            }
        }

        include __DIR__ . '/../Views/table.php';
    }

    /**
     * Pobiera listę wszystkich agentów
     */
    public function getAgents(): array
    {
        try {
            $query = "SELECT agent_id, imie, nazwisko FROM agenci ORDER BY nazwisko, imie";
            $stmt = $this->pdo->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching agents: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate commission fields before displaying the table
     */
    public function calculateCommissions(): void
    {
        try {
            // Pobierz wszystkie rekordy z tabeli test2
            $query = "SELECT * FROM test2";
            $stmt = $this->pdo->query($query);
            $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            foreach ($cases as $case) {
                $updates = [];
                $params = [];
                $id = $case['id'];
    
                // 1. Całość prowizji (F = D + (C * E))
                $upfrontFee = !empty($case['upfront_fee']) ? floatval($case['upfront_fee']) : 0;
                $amountWon = !empty($case['amount_won']) ? floatval($case['amount_won']) : 0;
                $successFeePercentage = !empty($case['success_fee_percentage']) ? floatval($case['success_fee_percentage']) : 0;
                $totalCommission = $upfrontFee + ($amountWon * ($successFeePercentage / 100));
                $updates[] = "total_commission = :total_commission";
                $params[':total_commission'] = $totalCommission;
    
                // 2. Do wypłaty Kuba (H = G - SUM(I:K))
                $kubaPercentage = !empty($case['kuba_percentage']) ? floatval($case['kuba_percentage']) : 0;
                $agent1Percentage = !empty($case['agent1_percentage']) ? floatval($case['agent1_percentage']) : 0;
                $agent2Percentage = !empty($case['agent2_percentage']) ? floatval($case['agent2_percentage']) : 0;
                $agent3Percentage = !empty($case['agent3_percentage']) ? floatval($case['agent3_percentage']) : 0;
                $kubaPayout = $kubaPercentage - ($agent1Percentage + $agent2Percentage + $agent3Percentage);
                // Ograniczamy wynik do przedziału 0–100
                $kubaPayout = max(0, min(100, $kubaPayout));
                $updates[] = "kuba_payout = :kuba_payout";
                $params[':kuba_payout'] = $kubaPayout;
    
                // 3. Ostatnia rata (T = F - SUM(N, P, R))
                $installment1 = !empty($case['installment1_amount']) ? floatval($case['installment1_amount']) : 0;
                $installment2 = !empty($case['installment2_amount']) ? floatval($case['installment2_amount']) : 0;
                $installment3 = !empty($case['installment3_amount']) ? floatval($case['installment3_amount']) : 0;
                $finalInstallment = $totalCommission - ($installment1 + $installment2 + $installment3);
                $finalInstallment = ($finalInstallment > 0) ? $finalInstallment : 0;
                $updates[] = "final_installment_amount = :final_installment_amount";
                $params[':final_installment_amount'] = $finalInstallment;
    
                // 4. Podział rat dla Kuby (V, X, Z, AB)
                $updates[] = "kuba_installment1_amount = :kuba_installment1_amount";
                $updates[] = "kuba_installment2_amount = :kuba_installment2_amount";
                $updates[] = "kuba_installment3_amount = :kuba_installment3_amount";
                $updates[] = "kuba_final_installment_amount = :kuba_final_installment_amount";
                // Przeliczamy procent na ułamek (np. 15% -> 0.15)
                $kubaFraction = $kubaPayout / 100;
                $params[':kuba_installment1_amount'] = $installment1 * $kubaFraction;
                $params[':kuba_installment2_amount'] = $installment2 * $kubaFraction;
                $params[':kuba_installment3_amount'] = $installment3 * $kubaFraction;
                $params[':kuba_final_installment_amount'] = $finalInstallment * $kubaFraction;
    
                // 5. Podział rat dla Agentów 1–3
                // Agent 1
                $agent1Fraction = $agent1Percentage / 100;
                $updates[] = "agent1_installment1_amount = :agent1_installment1_amount";
                $updates[] = "agent1_installment2_amount = :agent1_installment2_amount";
                $updates[] = "agent1_installment3_amount = :agent1_installment3_amount";
                $updates[] = "agent1_final_installment_amount = :agent1_final_installment_amount";
                $params[':agent1_installment1_amount'] = $installment1 * $agent1Fraction;
                $params[':agent1_installment2_amount'] = $installment2 * $agent1Fraction;
                $params[':agent1_installment3_amount'] = $installment3 * $agent1Fraction;
                $params[':agent1_final_installment_amount'] = $finalInstallment * $agent1Fraction;
    
                // Agent 2
                $agent2Fraction = $agent2Percentage / 100;
                $updates[] = "agent2_installment1_amount = :agent2_installment1_amount";
                $updates[] = "agent2_installment2_amount = :agent2_installment2_amount";
                $updates[] = "agent2_installment3_amount = :agent2_installment3_amount";
                $updates[] = "agent2_final_installment_amount = :agent2_final_installment_amount";
                $params[':agent2_installment1_amount'] = $installment1 * $agent2Fraction;
                $params[':agent2_installment2_amount'] = $installment2 * $agent2Fraction;
                $params[':agent2_installment3_amount'] = $installment3 * $agent2Fraction;
                $params[':agent2_final_installment_amount'] = $finalInstallment * $agent2Fraction;
    
                // Agent 3
                $agent3Fraction = $agent3Percentage / 100;
                $updates[] = "agent3_installment1_amount = :agent3_installment1_amount";
                $updates[] = "agent3_installment2_amount = :agent3_installment2_amount";
                $updates[] = "agent3_installment3_amount = :agent3_installment3_amount";
                $updates[] = "agent3_final_installment_amount = :agent3_final_installment_amount";
                $params[':agent3_installment1_amount'] = $installment1 * $agent3Fraction;
                $params[':agent3_installment2_amount'] = $installment2 * $agent3Fraction;
                $params[':agent3_installment3_amount'] = $installment3 * $agent3Fraction;
                $params[':agent3_final_installment_amount'] = $finalInstallment * $agent3Fraction;
    
                // Zapisz zmiany w bazie
                if (!empty($updates)) {
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
    
    

    /**
     * Pobierz sprawy przypisane do danego agenta
     */
    public function getAgentCases($agentId): array
    {
        try {
            // Pobierz kolumnę 'sprawy' dla wybranego agenta
            $query = "SELECT sprawy FROM agenci WHERE agent_id = :agent_id";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':agent_id' => $agentId]);
            $agent = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$agent) {
                return [];
            }

            // Dekoduj zapisany JSON do tablicy ID spraw
            $caseIds = json_decode($agent['sprawy'], true);
            if (!is_array($caseIds) || count($caseIds) === 0) {
                return [];
            }

            // Przygotuj zapytanie z warunkiem IN dla wszystkich ID
            $placeholders = implode(',', array_fill(0, count($caseIds), '?'));
            $query = "SELECT * FROM test2 WHERE id IN ($placeholders)";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($caseIds);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log('Error fetching agent cases: ' . $e->getMessage());
            return [];
        }
    }


    public function renderAgentSelection(): void
    {
        try {
            $agents = $this->getAgents();
    
            if (empty($agents)) {
                echo '<p style="text-align:center;">Brak agentów w bazie danych.</p>';
                return;
            }
    
            echo '<div class="agent-selection">';
            echo '<h3>Wybierz agenta:</h3>';
            echo '<div class="agent-list">';
    
            // Używamy absolutnej ścieżki (/table), aby zapewnić właściwe przekierowanie
            foreach ($agents as $agent) {
                $url = '/table?agent_id=' . $agent['agent_id'];
                // Jeśli imię agenta to "jakub" (porównanie ignorujące wielkość liter), ustaw tło na zielono
                $style = '';
                if (strtolower($agent['imie']) === 'jakub') {
                    $style = ' style="background-color: green;"';
                }
                echo '<a href="' . $url . '" class="agent-button"' . $style . '>' .
                     htmlspecialchars($agent['imie'] . ' ' . $agent['nazwisko'], ENT_QUOTES) . '</a>';
            }
    
            echo '</div></div>';
        } catch (PDOException $e) {
            echo '<p style="color:red; text-align:center;">Błąd: ' .
                htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>';
        }
    }
    


    public function renderTable($agentId = null): void
    {
        try {
            // Przelicz prowizje
            $this->calculateCommissions();

            // Jeśli podano ID agenta, pobierz tylko jego sprawy
            if ($agentId) {
                $cases = $this->getAgentCases($agentId);

                if (empty($cases)) {
                    echo '<p style="text-align:center;">Brak spraw dla wybranego agenta.</p>';
                    return;
                }

                // Przygotuj dane do wyświetlenia
                $rows = [];
                foreach ($cases as $case) {
                    $row = [
                        'Sprawa' => $case['case_name'],
                        'Zakończona?' => $case['is_completed'] ? 'Tak' : 'Nie',
                        'Wywalczona kwota' => $case['amount_won'],
                        'Opłata wstępna' => $case['upfront_fee'],
                        'Success fee %' => $case['success_fee_percentage'],
                        'Całość prowizji' => $case['total_commission'],
                        'Prowizja % Kuba' => $case['kuba_percentage'],
                        'Do wypłaty Kuba' => $case['kuba_payout'],
                        'Prowizja % Agent 1' => $case['agent1_percentage'],
                        'Prowizja % Agent 2' => $case['agent2_percentage'],
                        'Prowizja % Agent 3' => $case['agent3_percentage'],
                        'Prowizja % Agent 4' => $case['agent4_percentage'],
                        'Prowizja % Agent 5' => $case['agent5_percentage'],
                        'Rata 1' => $case['installment1_amount'],
                        'Opłacona 1' => $case['installment1_paid'] ? 'Tak' : 'Nie',
                        'Rata 2' => $case['installment2_amount'],
                        'Opłacona 2' => $case['installment2_paid'] ? 'Tak' : 'Nie',
                        'Rata 3' => $case['installment3_amount'],
                        'Opłacona 3' => $case['installment3_paid'] ? 'Tak' : 'Nie',
                        'Rata 4' => $case['final_installment_amount'],
                        'Opłacona 4' => $case['final_installment_paid'] ? 'Tak' : 'Nie',
                        'Rata 1 – Kuba' => $case['kuba_installment1_amount'],
                        'Nr faktury' => $case['kuba_invoice_number'],
                        'Rata 2 – Kuba' => $case['kuba_installment2_amount'],
                        'Rata 3 – Kuba' => $case['kuba_installment3_amount'],
                        'Rata 4 – Kuba' => $case['kuba_final_installment_amount'],
                        'Rata 1 – Agent 1' => $case['agent1_installment1_amount'],
                        'Rata 2 – Agent 1' => $case['agent1_installment2_amount'],
                        'Rata 3 – Agent 1' => $case['agent1_installment3_amount'],
                        'Rata 4 – Agent 1' => $case['agent1_final_installment_amount'],
                        'Rata 1 – Agent 2' => $case['agent2_installment1_amount'],
                        'Rata 2 – Agent 2' => $case['agent2_installment2_amount'],
                        'Rata 3 – Agent 2' => $case['agent2_installment3_amount'],
                        'Rata 4 – Agent 2' => $case['agent2_final_installment_amount'],
                        'Rata 1 – Agent 3' => $case['agent3_installment1_amount'],
                        'Rata 2 – Agent 3' => $case['agent3_installment2_amount'],
                        'Rata 3 – Agent 3' => $case['agent3_installment3_amount'],
                        'Rata 4 – Agent 3' => $case['agent3_final_installment_amount']
                    ];
                    $rows[] = $row;
                }
            } else {
                // Oryginalne SELECT z wszystkimi kolumnami + statusami opłacenia
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
                    CASE WHEN installment1_paid = 1 THEN 'Tak' WHEN installment1_paid = 0 THEN 'Nie' ELSE '' END AS 'Opłacona 1',
                    installment2_amount AS 'Rata 2',
                    CASE WHEN installment2_paid = 1 THEN 'Tak' WHEN installment2_paid = 0 THEN 'Nie' ELSE '' END AS 'Opłacona 2',
                    installment3_amount AS 'Rata 3',
                    CASE WHEN installment3_paid = 1 THEN 'Tak' WHEN installment3_paid = 0 THEN 'Nie' ELSE '' END AS 'Opłacona 3',
                    final_installment_amount AS 'Rata 4',
                    CASE WHEN final_installment_paid = 1 THEN 'Tak' WHEN final_installment_paid = 0 THEN 'Nie' ELSE '' END AS 'Opłacona 4',
        
                    kuba_installment1_amount AS 'Rata 1 – Kuba',
                    kuba_invoice_number AS 'Nr faktury',
                    kuba_installment2_amount AS 'Rata 2 – Kuba',
                    kuba_installment3_amount AS 'Rata 3 – Kuba',
                    kuba_final_installment_amount AS 'Rata 4 – Kuba',
        
                    agent1_installment1_amount AS 'Rata 1 – Agent 1',
                    agent1_installment2_amount AS 'Rata 2 – Agent 1',
                    agent1_installment3_amount AS 'Rata 3 – Agent 1',
                    agent1_final_installment_amount AS 'Rata 4 – Agent 1',
                    agent2_installment1_amount AS 'Rata 1 – Agent 2',
                    agent2_installment2_amount AS 'Rata 2 – Agent 2',
                    agent2_installment3_amount AS 'Rata 3 – Agent 2',
                    agent2_final_installment_amount AS 'Rata 4 – Agent 2',
                    agent3_installment1_amount AS 'Rata 1 – Agent 3',
                    agent3_installment2_amount AS 'Rata 2 – Agent 3',
                    agent3_installment3_amount AS 'Rata 3 – Agent 3',
                    agent3_final_installment_amount AS 'Rata 4 – Agent 3'
                FROM test2
                ORDER BY id DESC";

                $stmt = $this->pdo->query($query);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            if (empty($rows)) {
                echo '<p style="text-align:center;">Brak danych.</p>';
                return;
            }

            // Pełna lista nagłówków w kolejności SELECT
            $allHeaders = array_keys($rows[0]);

            // FILTR: zostawiamy tylko te nagłówki, które mają choć jeden nie-pusty rekord
            $visibleHeaders = array_filter($allHeaders, function ($title) use ($rows) {
                foreach ($rows as $row) {
                    if (isset($row[$title]) && $row[$title] !== '' && $row[$title] !== null) {
                        return true;
                    }
                }
                return false;
            });

            echo '<table class="data-table"><thead><tr>';
            foreach ($visibleHeaders as $title) {
                echo '<th class="sortable" data-column="' . htmlspecialchars($title, ENT_QUOTES) . '">'
                    . htmlspecialchars($title, ENT_QUOTES) . '</th>';
            }
            echo '</tr></thead><tbody>';

            // Wyświetlanie wierszy – tylko kolumny z $visibleHeaders
            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($visibleHeaders as $title) {
                    $value = $row[$title] ?? '';

                    if (is_numeric($value)) {
                        if ($title === 'Do wypłaty Kuba') { 
                            echo '<td>' . number_format((float)$value, 2, ',', ' ') . '%</td>';
                        } elseif (strpos($title, '%') !== false) {
                            echo '<td>' . number_format((float)$value, 2, ',', ' ') . '%</td>';
                        } elseif (strpos($title, 'Wywalczona kwota') !== false ||
                                  strpos($title, 'Opłata wstępna') !== false ||
                                  strpos($title, 'Całość prowizji') !== false ||
                                  strpos($title, 'Rata') !== false) {
                            // Dodaj znak złotówki do wartości pieniężnych
                            echo '<td>' . number_format((float)$value, 2, ',', ' ') . ' zł</td>';
                        } else {
                            echo '<td>' . number_format((float)$value, 2, ',', ' ') . '</td>';
                        }
                    } elseif ($value === 'Tak' || $value === 'Nie') {
                        // Obsługa wartości boolean
                        $color = ($value === 'Tak') ? 'green' : 'red';
                        echo '<td style="color:' . $color . ';">' . htmlspecialchars($value, ENT_QUOTES) . '</td>';
                    } else {
                        echo '<td>' . htmlspecialchars($value, ENT_QUOTES) . '</td>';
                    }
                    
                }
                echo '</tr>';
            }

            echo '</tbody></table>';
        } catch (PDOException $e) {
            echo '<p style="color:red; text-align:center;">Błąd: '
                . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>';
        }
    }
}
