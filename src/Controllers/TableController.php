<?php

namespace Dell\Faktury\Controllers;

use PDO;
use PDOException;

class TableController
{
    private $pdo;

    public function __construct()
    {
        error_log("TableController::__construct - Inicjalizacja kontrolera tabeli");
        // Konstruktor bez parametrów - połączenie z bazą będzie inicjalizowane w index()
    }

    public function index(): void
    {
        error_log("TableController::index - Start");
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        $this->pdo = $pdo;
        error_log("TableController::index - Połączenie z bazą danych zainicjalizowane");

        // Pobierz listę agentów
        error_log("TableController::index - Pobieranie listy agentów");
        $agents = $this->getAgents();
        error_log("TableController::index - Znaleziono " . count($agents) . " agentów");

        // Jeśli wybrano agenta, wyświetl sprawy tego agenta
        $selectedAgentId = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : null;
        $selectedAgent = null;
        error_log("TableController::index - Wybrany agent ID: " . ($selectedAgentId ?: 'brak'));

        if ($selectedAgentId) {
            foreach ($agents as $agent) {
                if ($agent['agent_id'] == $selectedAgentId) {
                    $selectedAgent = $agent;
                    error_log("TableController::index - Znaleziono wybranego agenta: " . $agent['imie'] . " " . $agent['nazwisko']);
                    break;
                }
            }
        }

        error_log("TableController::index - Renderowanie widoku tabeli");
        include __DIR__ . '/../Views/table.php';
        error_log("TableController::index - Zakończono");
    }

    /**
     * Pobiera listę wszystkich agentów
     */
    public function getAgents(): array
    {
        error_log("TableController::getAgents - Start");
        try {
            $query = "SELECT agent_id, imie, nazwisko FROM agenci ORDER BY nazwisko, imie";
            $stmt = $this->pdo->query($query);
            $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("TableController::getAgents - Pobrano " . count($agents) . " agentów");
            return $agents;
        } catch (PDOException $e) {
            error_log("TableController::getAgents - BŁĄD: " . $e->getMessage());
            error_log("TableController::getAgents - Stack trace: " . $e->getTraceAsString());
            return [];
        }
    }

    /**
     * Calculate commission fields before displaying the table
     */
    public function calculateCommissions(): void
    {
        try {
            // Włącz wyświetlanie logów w przeglądarce 
            ini_set('display_errors', 1);
            error_log("=== ROZPOCZĘCIE OBLICZEŃ PROWIZJI ===");
            
            // Pobierz wszystkie rekordy z tabeli test2
            $query = "SELECT * FROM test2";
            $stmt = $this->pdo->query($query);
            $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Znaleziono " . count($cases) . " spraw do przeliczenia");

            foreach ($cases as $case) {
                $updates = [];
                $params = [];
                $id = $case['id'];
                error_log("--- Przeliczanie sprawy ID: {$id}, nazwa: " . ($case['case_name'] ?? 'brak nazwy') . " ---");

                // 1. Całość prowizji (F = D + (C * E))
                $upfrontFee = !empty($case['upfront_fee']) ? floatval($case['upfront_fee']) : 0;
                $amountWon = !empty($case['amount_won']) ? floatval($case['amount_won']) : 0;
                $successFeePercentage = !empty($case['success_fee_percentage']) ? floatval($case['success_fee_percentage']) : 0;
                $totalCommission = $upfrontFee + ($amountWon * ($successFeePercentage / 100));
                $updates[] = "total_commission = :total_commission";
                $params[':total_commission'] = $totalCommission;
                error_log("1. Całość prowizji: upfrontFee={$upfrontFee}, amountWon={$amountWon}, successFeePercentage={$successFeePercentage}, totalCommission={$totalCommission}");

                // Pobierz wartości procentowe z tabeli sprawa_agent
                // UWAGA: kuba_percentage jest statyczną wartością z tabeli test2
                $kubaPercentage = !empty($case['kuba_percentage']) ? floatval($case['kuba_percentage']) : 0;
                $agent1Percentage = 0;
                $agent2Percentage = 0;
                $agent3Percentage = 0;
                error_log("Używam statycznej wartości kuba_percentage={$kubaPercentage} bezpośrednio z tabeli test2");

                // Pobierz dane agentów (tylko dla agentów 1-3, bez Kuby)
                $agentsQuery = "
                    SELECT agent_id, rola, percentage
                    FROM sprawa_agent
                    WHERE sprawa_id = :sprawa_id
                       AND rola IN ('agent_1', 'agent_2', 'agent_3')
                ";
                $agentsStmt = $this->pdo->prepare($agentsQuery);
                $agentsStmt->execute([':sprawa_id' => $id]);
                $agents = $agentsStmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Znaleziono " . count($agents) . " agentów dla sprawy {$id} (bez Kuby)");
                
                // Debug: wypisz wszystkich znalezionych agentów
                foreach ($agents as $agent) {
                    error_log("Agent: ID=" . $agent['agent_id'] . ", rola=" . $agent['rola'] . ", procent=" . $agent['percentage']);
                }

                // Przypisz procentowe wartości agentów na podstawie ról
                foreach ($agents as $agent) {
                    $role = strtolower($agent['rola']);
                    if ($role === 'agent_1') {
                        $agent1Percentage = floatval($agent['percentage']);
                        error_log("Znaleziono Agenta 1, procent: {$agent1Percentage}");
                    } else if ($role === 'agent_2') {
                        $agent2Percentage = floatval($agent['percentage']);
                        error_log("Znaleziono Agenta 2, procent: {$agent2Percentage}");
                    } else if ($role === 'agent_3') {
                        $agent3Percentage = floatval($agent['percentage']);
                        error_log("Znaleziono Agenta 3, procent: {$agent3Percentage}");
                    } else {
                        error_log("Nieznana rola agenta: {$agent['rola']}");
                    }
                }
                
                // Aktualizacja wartości procentowych w rekordzie dla kompatybilności
                $updates[] = "kuba_percentage = :kuba_percentage";
                $updates[] = "agent1_percentage = :agent1_percentage";
                $updates[] = "agent2_percentage = :agent2_percentage";
                $updates[] = "agent3_percentage = :agent3_percentage";
                $params[':kuba_percentage'] = $kubaPercentage;
                $params[':agent1_percentage'] = $agent1Percentage;
                $params[':agent2_percentage'] = $agent2Percentage;
                $params[':agent3_percentage'] = $agent3Percentage;
                error_log("Wartości procentów zapisywane do bazy: kuba={$kubaPercentage}, agent1={$agent1Percentage}, agent2={$agent2Percentage}, agent3={$agent3Percentage}");

                // 2. Do wypłaty Kuba (H = G - SUM(I:K))
                $kubaPayout = $kubaPercentage - ($agent1Percentage + $agent2Percentage + $agent3Percentage);
                error_log("Obliczenie do wypłaty Kuba: {$kubaPercentage} - ({$agent1Percentage} + {$agent2Percentage} + {$agent3Percentage}) = {$kubaPayout}");
                
                // Ograniczenie wyniku do zakresu 0–100
                $kubaPayout = max(0, min(100, $kubaPayout));
                $updates[] = "kuba_payout = :kuba_payout";
                $params[':kuba_payout'] = $kubaPayout;
                error_log("2. Do wypłaty Kuba (po ograniczeniu): {$kubaPayout}");

                // 3. Ostatnia rata (T = F - SUM(N, P, R))
                $installment1 = !empty($case['installment1_amount']) ? floatval($case['installment1_amount']) : 0;
                $installment2 = !empty($case['installment2_amount']) ? floatval($case['installment2_amount']) : 0;
                $installment3 = !empty($case['installment3_amount']) ? floatval($case['installment3_amount']) : 0;
                $finalInstallment = $totalCommission - ($installment1 + $installment2 + $installment3);
                error_log("Obliczenie ostatniej raty: {$totalCommission} - ({$installment1} + {$installment2} + {$installment3}) = {$finalInstallment}");
                
                $finalInstallment = ($finalInstallment > 0) ? $finalInstallment : 0;
                $updates[] = "final_installment_amount = :final_installment_amount";
                $params[':final_installment_amount'] = $finalInstallment;
                error_log("3. Ostatnia rata (po ograniczeniu): {$finalInstallment}");

                // 4. Podział rat dla Kuby (V, X, Z, AB)
                $updates[] = "kuba_installment1_amount = :kuba_installment1_amount";
                $updates[] = "kuba_installment2_amount = :kuba_installment2_amount";
                $updates[] = "kuba_installment3_amount = :kuba_installment3_amount";
                $updates[] = "kuba_final_installment_amount = :kuba_final_installment_amount";
                
                // Przeliczamy procent z zakresu 0–100 na ułamek
                $kubaFraction = $kubaPayout / 100;
                
                // Sprawdź, czy mamy poprawne wartości
                if (is_nan($kubaFraction) || $kubaFraction === INF || $kubaFraction === -INF) {
                    error_log("UWAGA: Niepoprawna wartość kubaFraction: {$kubaFraction}. Używam 0.");
                    $kubaFraction = 0;
                }
                
                $kubaInstallment1 = $installment1 * $kubaFraction;
                $kubaInstallment2 = $installment2 * $kubaFraction;
                $kubaInstallment3 = $installment3 * $kubaFraction;
                $kubaFinalInstallment = $finalInstallment * $kubaFraction;
                
                $params[':kuba_installment1_amount'] = $kubaInstallment1;
                $params[':kuba_installment2_amount'] = $kubaInstallment2;
                $params[':kuba_installment3_amount'] = $kubaInstallment3;
                $params[':kuba_final_installment_amount'] = $kubaFinalInstallment;
                error_log("4. Raty Kuby: kubaFraction={$kubaFraction}, rata1={$kubaInstallment1}, rata2={$kubaInstallment2}, rata3={$kubaInstallment3}, rata4={$kubaFinalInstallment}");

                // 5. Podział rat dla Agentów 1–3
                // Agent 1
                $agent1Fraction = $agent1Percentage / 100;
                $agent1Installment1 = $installment1 * $agent1Fraction;
                $agent1Installment2 = $installment2 * $agent1Fraction;
                $agent1Installment3 = $installment3 * $agent1Fraction;
                $agent1FinalInstallment = $finalInstallment * $agent1Fraction;
                
                $updates[] = "agent1_installment1_amount = :agent1_installment1_amount";
                $updates[] = "agent1_installment2_amount = :agent1_installment2_amount";
                $updates[] = "agent1_installment3_amount = :agent1_installment3_amount";
                $updates[] = "agent1_final_installment_amount = :agent1_final_installment_amount";
                $params[':agent1_installment1_amount'] = $agent1Installment1;
                $params[':agent1_installment2_amount'] = $agent1Installment2;
                $params[':agent1_installment3_amount'] = $agent1Installment3;
                $params[':agent1_final_installment_amount'] = $agent1FinalInstallment;
                error_log("5a. Raty Agenta 1: fraction={$agent1Fraction}, rata1={$agent1Installment1}, rata2={$agent1Installment2}, rata3={$agent1Installment3}, rata4={$agent1FinalInstallment}");

                // Agent 2
                $agent2Fraction = $agent2Percentage / 100;
                $agent2Installment1 = $installment1 * $agent2Fraction;
                $agent2Installment2 = $installment2 * $agent2Fraction;
                $agent2Installment3 = $installment3 * $agent2Fraction;
                $agent2FinalInstallment = $finalInstallment * $agent2Fraction;
                
                $updates[] = "agent2_installment1_amount = :agent2_installment1_amount";
                $updates[] = "agent2_installment2_amount = :agent2_installment2_amount";
                $updates[] = "agent2_installment3_amount = :agent2_installment3_amount";
                $updates[] = "agent2_final_installment_amount = :agent2_final_installment_amount";
                $params[':agent2_installment1_amount'] = $agent2Installment1;
                $params[':agent2_installment2_amount'] = $agent2Installment2;
                $params[':agent2_installment3_amount'] = $agent2Installment3;
                $params[':agent2_final_installment_amount'] = $agent2FinalInstallment;
                error_log("5b. Raty Agenta 2: fraction={$agent2Fraction}, rata1={$agent2Installment1}, rata2={$agent2Installment2}, rata3={$agent2Installment3}, rata4={$agent2FinalInstallment}");

                // Agent 3
                $agent3Fraction = $agent3Percentage / 100;
                $agent3Installment1 = $installment1 * $agent3Fraction;
                $agent3Installment2 = $installment2 * $agent3Fraction;
                $agent3Installment3 = $installment3 * $agent3Fraction;
                $agent3FinalInstallment = $finalInstallment * $agent3Fraction;
                
                $updates[] = "agent3_installment1_amount = :agent3_installment1_amount";
                $updates[] = "agent3_installment2_amount = :agent3_installment2_amount";
                $updates[] = "agent3_installment3_amount = :agent3_installment3_amount";
                $updates[] = "agent3_final_installment_amount = :agent3_final_installment_amount";
                $params[':agent3_installment1_amount'] = $agent3Installment1;
                $params[':agent3_installment2_amount'] = $agent3Installment2;
                $params[':agent3_installment3_amount'] = $agent3Installment3;
                $params[':agent3_final_installment_amount'] = $agent3FinalInstallment;
                error_log("5c. Raty Agenta 3: fraction={$agent3Fraction}, rata1={$agent3Installment1}, rata2={$agent3Installment2}, rata3={$agent3Installment3}, rata4={$agent3FinalInstallment}");

                // Zapisz zmiany w bazie
                if (!empty($updates)) {
                    $updateQuery = "UPDATE test2 SET " . implode(", ", $updates) . " WHERE id = :id";
                    $params[':id'] = $id;
                    $updateStmt = $this->pdo->prepare($updateQuery);
                    
                    try {
                        $updateStmt->execute($params);
                        error_log("Pomyślnie zapisano dane dla sprawy ID: {$id}");
                    } catch (PDOException $e) {
                        error_log("BŁĄD podczas zapisywania sprawy ID: {$id}: " . $e->getMessage());
                        // Wyświetl parametry i zapytanie dla debugowania
                        error_log("Zapytanie: {$updateQuery}");
                        error_log("Parametry: " . print_r($params, true));
                    }
                }
                
                error_log("Zakończono przeliczanie sprawy ID: {$id}");
            }
            
            error_log("=== ZAKOŃCZENIE OBLICZEŃ PROWIZJI ===");
        } catch (PDOException $e) {
            error_log("BŁĄD KRYTYCZNY calculating commissions: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
        }
    }
    

    /**
     * Pobierz sprawy przypisane do danego agenta
     */
    public function getAgentCases($agentId): array
    {
        try {
            // Pobierz wszystkie sprawy przypisane do tego agenta z tabeli sprawa_agent
            $query = "
                SELECT t.*, sa.rola, sa.percentage 
                FROM test2 t
                JOIN sprawa_agent sa ON t.id = sa.sprawa_id
                WHERE sa.agent_id = :agent_id
                ORDER BY t.id DESC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':agent_id' => $agentId]);
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
                if (strtolower($agent['imie']) === 'jakub' || strtolower($agent['imie']) === 'kuba') {
                    $style = ' style="background-color: green;"';
                }
                echo '<a href="' . $url . '" class="agent-button"' . $style . '>' .
                     htmlspecialchars($agent['imie'] . ' ' . $agent['nazwisko'], ENT_QUOTES) . '</a>';
            }
    
            echo '</div></div>';
        } catch (PDOException $e) {
            echo '<p style="color:red; text-align:center;">Błąd: '
                . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>';
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
                
                // Pobierz dane wybranego agenta
                $selectedAgentQuery = "SELECT imie, nazwisko FROM agenci WHERE agent_id = :agent_id";
                $selectedAgentStmt = $this->pdo->prepare($selectedAgentQuery);
                $selectedAgentStmt->execute([':agent_id' => $agentId]);
                $selectedAgent = $selectedAgentStmt->fetch(PDO::FETCH_ASSOC);
                $selectedAgentName = $selectedAgent ? ($selectedAgent['imie'] . ' ' . $selectedAgent['nazwisko']) : '';

                if (empty($cases)) {
                    echo '<p style="text-align:center;">Brak spraw dla wybranego agenta.</p>';
                    return;
                }

                // Przygotuj dane do wyświetlenia
                $rows = [];
                foreach ($cases as $case) {
                    // Pobierz rolę wybranego agenta w tej sprawie
                    $roleQuery = "SELECT rola FROM sprawa_agent WHERE sprawa_id = :sprawa_id AND agent_id = :agent_id";
                    $roleStmt = $this->pdo->prepare($roleQuery);
                    $roleStmt->execute([':sprawa_id' => $case['id'], ':agent_id' => $agentId]);
                    $roleData = $roleStmt->fetch(PDO::FETCH_ASSOC);
                    $selectedRole = $roleData ? $roleData['rola'] : '';
                    
                    // Pobierz informacje o wszystkich agentach przypisanych do tej sprawy
                    $agentsQuery = "
                        SELECT a.imie, a.nazwisko, sa.rola, sa.percentage, sa.agent_id
                        FROM sprawa_agent sa
                        JOIN agenci a ON sa.agent_id = a.agent_id
                        WHERE sa.sprawa_id = :sprawa_id
                        ORDER BY sa.rola";
                    
                    $agentsStmt = $this->pdo->prepare($agentsQuery);
                    $agentsStmt->execute([':sprawa_id' => $case['id']]);
                    $agents = $agentsStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Przygotuj dane agentów do wyświetlenia
                    $agentsData = [];
                    foreach ($agents as $agent) {
                        $agentsData[$agent['rola']] = [
                            'imie_nazwisko' => $agent['imie'] . ' ' . $agent['nazwisko'],
                            'percentage' => $agent['percentage'],
                            'agent_id' => $agent['agent_id']
                        ];
                    }
                    
                    // Buduj wiersz danych z informacjami o sprawie i agentach
                    $row = [
                        'Sprawa' => $case['case_name'],
                        'Zakończona?' => $case['is_completed'] ? 'Tak' : 'Nie',
                        'Wywalczona kwota' => $case['amount_won'],
                        'Opłata wstępna' => $case['upfront_fee'],
                        'Success fee %' => $case['success_fee_percentage'],
                        'Całość prowizji' => $case['total_commission'],
                        'Prowizja % Kuba' => $case['kuba_percentage'],
                        'Do wypłaty Kuba' => $case['kuba_payout']
                    ];
                    
                    error_log("Dane sprawy ID {$case['id']}: Kuba%={$case['kuba_percentage']}, DoWypłaty={$case['kuba_payout']}");
                    error_log("Raty Kuby: " . 
                             "Rata 1=" . ($case['kuba_installment1_amount'] ?? 'brak') . ", " .
                             "Rata 2=" . ($case['kuba_installment2_amount'] ?? 'brak') . ", " .
                             "Rata 3=" . ($case['kuba_installment3_amount'] ?? 'brak') . ", " .
                             "Rata 4=" . ($case['kuba_final_installment_amount'] ?? 'brak'));
                    
                    // Dodaj dane agentów do wiersza
                    for ($i = 1; $i <= 3; $i++) {
                        $agentRole = "agent_{$i}";
                        if (isset($agentsData[$agentRole])) {
                            // Sprawdź, czy to jest wybrany agent i dodaj odpowiednią klasę
                            $isSelected = ($agentsData[$agentRole]['agent_id'] == $agentId);
                            $cssClass = $isSelected ? 'agent-name-highlight selected-agent' : 'agent-name-highlight';
                            
                            $row["Agent {$i}"] = sprintf(
                                '<span class="%s">%s</span>', 
                                $cssClass,
                                htmlspecialchars($agentsData[$agentRole]['imie_nazwisko'])
                            );
                            
                            // Dodaj klasę również do komórki z procentem prowizji
                            $percentValue = $agentsData[$agentRole]['percentage'];
                            $row["Prowizja % Agent {$i}"] = $isSelected ? 
                                sprintf('<span class="selected-agent">%s</span>', $percentValue) : 
                                $percentValue;
                        } else {
                            $row["Agent {$i}"] = '';
                            $row["Prowizja % Agent {$i}"] = '';
                        }
                    }
                    
                    // Dodaj pozostałe dane
                    $row = array_merge($row, [
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
                        'Rata 4 – Kuba' => $case['kuba_final_installment_amount']
                    ]);
                    
                    // Dodaj raty dla agentów z odpowiednim podświetleniem
                    for ($i = 1; $i <= 3; $i++) {
                        $agentRole = "agent_{$i}";
                        if (isset($agentsData[$agentRole])) {
                            $isSelected = ($agentsData[$agentRole]['agent_id'] == $agentId);
                            
                            // Format dla wszystkich rat, z dodatkowym podświetleniem jeśli to wybrany agent
                            foreach (['1', '2', '3', '4'] as $rataNum) {
                                $keyName = "Rata {$rataNum} – Agent {$i}";
                                $valueKey = "agent{$i}_installment{$rataNum}_amount";
                                if ($rataNum == '4') $valueKey = "agent{$i}_final_installment_amount";
                                
                                $rataValue = $case[$valueKey];
                                $row[$keyName] = $isSelected ? 
                                    sprintf('<span class="selected-agent">%s</span>', $rataValue) : 
                                    $rataValue;
                            }
                        }
                    }
                    
                    $rows[] = $row;
                }
            } else {
                // Pobierz wszystkie sprawy z dołączonymi informacjami o agentach
                $query = "
                    SELECT 
                        t.id,
                        t.case_name AS 'Sprawa',
                        CASE WHEN t.is_completed = 1 THEN 'Tak' WHEN t.is_completed = 0 THEN 'Nie' ELSE '' END AS 'Zakończona?',
                        t.amount_won AS 'Wywalczona kwota',
                        t.upfront_fee AS 'Opłata wstępna',
                        t.success_fee_percentage AS 'Success fee %',
                        t.total_commission AS 'Całość prowizji',
                        t.kuba_percentage AS 'Prowizja % Kuba',
                        t.kuba_payout AS 'Do wypłaty Kuba',
                        t.installment1_amount AS 'Rata 1',
                        CASE WHEN t.installment1_paid = 1 THEN 'Tak' WHEN t.installment1_paid = 0 THEN 'Nie' ELSE '' END AS 'Opłacona 1',
                        t.installment2_amount AS 'Rata 2',
                        CASE WHEN t.installment2_paid = 1 THEN 'Tak' WHEN t.installment2_paid = 0 THEN 'Nie' ELSE '' END AS 'Opłacona 2',
                        t.installment3_amount AS 'Rata 3',
                        CASE WHEN t.installment3_paid = 1 THEN 'Tak' WHEN t.installment3_paid = 0 THEN 'Nie' ELSE '' END AS 'Opłacona 3',
                        t.final_installment_amount AS 'Rata 4',
                        CASE WHEN t.final_installment_paid = 1 THEN 'Tak' WHEN t.final_installment_paid = 0 THEN 'Nie' ELSE '' END AS 'Opłacona 4',
                        t.kuba_installment1_amount AS 'Rata 1 – Kuba',
                        t.kuba_invoice_number AS 'Nr faktury',
                        t.kuba_installment2_amount AS 'Rata 2 – Kuba',
                        t.kuba_installment3_amount AS 'Rata 3 – Kuba',
                        t.kuba_final_installment_amount AS 'Rata 4 – Kuba',
                        t.agent1_installment1_amount AS 'Rata 1 – Agent 1',
                        t.agent1_installment2_amount AS 'Rata 2 – Agent 1',
                        t.agent1_installment3_amount AS 'Rata 3 – Agent 1',
                        t.agent1_final_installment_amount AS 'Rata 4 – Agent 1',
                        t.agent2_installment1_amount AS 'Rata 1 – Agent 2',
                        t.agent2_installment2_amount AS 'Rata 2 – Agent 2',
                        t.agent2_installment3_amount AS 'Rata 3 – Agent 2',
                        t.agent2_final_installment_amount AS 'Rata 4 – Agent 2',
                        t.agent3_installment1_amount AS 'Rata 1 – Agent 3',
                        t.agent3_installment2_amount AS 'Rata 2 – Agent 3',
                        t.agent3_installment3_amount AS 'Rata 3 – Agent 3',
                        t.agent3_final_installment_amount AS 'Rata 4 – Agent 3'
                    FROM test2 t
                    ORDER BY t.id DESC";

                $stmt = $this->pdo->query($query);
                $casesTemp = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $rows = [];
                foreach ($casesTemp as $case) {
                    $caseId = $case['id'];
                    unset($case['id']); // Usuń ID z danych do wyświetlenia
                    
                    // Pobierz informacje o agentach dla tej sprawy
                    $agentsQuery = "
                        SELECT a.imie, a.nazwisko, sa.rola, sa.percentage
                        FROM sprawa_agent sa
                        JOIN agenci a ON sa.agent_id = a.agent_id
                        WHERE sa.sprawa_id = :sprawa_id
                        ORDER BY sa.rola";
                    
                    $agentsStmt = $this->pdo->prepare($agentsQuery);
                    $agentsStmt->execute([':sprawa_id' => $caseId]);
                    $agents = $agentsStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Dodaj informacje o agentach do wiersza
                    foreach ($agents as $agent) {
                        if (preg_match('/agent_(\d+)/', $agent['rola'], $matches)) {
                            $agentNum = $matches[1];
                            $case["Agent {$agentNum}"] = sprintf(
                                '<span class="agent-name-highlight">%s</span>', 
                                htmlspecialchars($agent['imie'] . ' ' . $agent['nazwisko'])
                            );
                            $case["Prowizja % Agent {$agentNum}"] = $agent['percentage'];
                        }
                    }
                    
                    $rows[] = $case;
                }
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
                        // Zezwól na HTML w komórkach (np. dla nazw agentów)
                        echo '<td>' . $value . '</td>';
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

    /**
     * Recalculate commissions for a specific case (debug method)
     */
    public function recalculateCase($caseId = null): void 
    {
        if (!$caseId) {
            // Jeśli ID nie podano, użyj parametru z GET
            $caseId = isset($_GET['case_id']) ? intval($_GET['case_id']) : null;
        }
        
        if (!$caseId) {
            echo "Błąd: Nie podano ID sprawy do przeliczenia.";
            return;
        }
        
        try {
            require_once __DIR__ . '/../../config/database.php';
            global $pdo;
            $this->pdo = $pdo;
            
            // Pobierz dane sprawy
            $query = "SELECT * FROM test2 WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':id' => $caseId]);
            $case = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$case) {
                echo "Błąd: Sprawa o ID {$caseId} nie istnieje.";
                return;
            }
            
            echo "<h2>Przeliczanie sprawy ID: {$caseId}</h2>";
            echo "<pre>";
            
            // Pobierz dane agentów dla tej sprawy
            $agentsQuery = "
                SELECT sa.agent_id, sa.rola, sa.percentage, a.imie, a.nazwisko 
                FROM sprawa_agent sa
                JOIN agenci a ON sa.agent_id = a.agent_id
                WHERE sa.sprawa_id = :sprawa_id
            ";
            $agentsStmt = $this->pdo->prepare($agentsQuery);
            $agentsStmt->execute([':sprawa_id' => $caseId]);
            $agents = $agentsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "Znaleziono " . count($agents) . " agentów:\n";
            foreach ($agents as $agent) {
                echo "- {$agent['imie']} {$agent['nazwisko']} (rola: {$agent['rola']}, procent: {$agent['percentage']}%)\n";
            }
            echo "\n";
            
            // Obliczenia
            $updates = [];
            $params = [];
            
            // 1. Całość prowizji (F = D + (C * E))
            $upfrontFee = !empty($case['upfront_fee']) ? floatval($case['upfront_fee']) : 0;
            $amountWon = !empty($case['amount_won']) ? floatval($case['amount_won']) : 0;
            $successFeePercentage = !empty($case['success_fee_percentage']) ? floatval($case['success_fee_percentage']) : 0;
            $totalCommission = $upfrontFee + ($amountWon * ($successFeePercentage / 100));
            $updates[] = "total_commission = :total_commission";
            $params[':total_commission'] = $totalCommission;
            
            echo "1. Całość prowizji:\n";
            echo "   Opłata wstępna: {$upfrontFee} zł\n";
            echo "   Wywalczona kwota: {$amountWon} zł\n";
            echo "   Success fee %: {$successFeePercentage}%\n";
            echo "   Całość prowizji = {$upfrontFee} + ({$amountWon} * {$successFeePercentage}/100) = {$totalCommission} zł\n\n";
            
            // Zbierz wartości procentowe
            // Kuba - używamy bezpośrednio wartości z tabeli test2
            $kubaPercentage = !empty($case['kuba_percentage']) ? floatval($case['kuba_percentage']) : 0;
            echo "   Kuba (wartość stała z tabeli test2): {$kubaPercentage}%\n";
            
            $agent1Percentage = 0;
            $agent2Percentage = 0;
            $agent3Percentage = 0;
            
            // Wyszukaj tylko pozostałych agentów
            foreach ($agents as $agent) {
                $role = strtolower($agent['rola']);
                if ($role === 'agent_1') {
                    $agent1Percentage = floatval($agent['percentage']);
                    echo "   Znaleziono Agenta 1: {$agent['imie']} {$agent['nazwisko']}, procent: {$agent1Percentage}%\n";
                } else if ($role === 'agent_2') {
                    $agent2Percentage = floatval($agent['percentage']);
                    echo "   Znaleziono Agenta 2: {$agent['imie']} {$agent['nazwisko']}, procent: {$agent2Percentage}%\n";
                } else if ($role === 'agent_3') {
                    $agent3Percentage = floatval($agent['percentage']);
                    echo "   Znaleziono Agenta 3: {$agent['imie']} {$agent['nazwisko']}, procent: {$agent3Percentage}%\n";
                }
            }
            
            echo "\n";
            
            // 2. Do wypłaty Kuba (H = G - SUM(I:K))
            $kubaPayout = $kubaPercentage - ($agent1Percentage + $agent2Percentage + $agent3Percentage);
            $kubaPayout = max(0, min(100, $kubaPayout));
            $updates[] = "kuba_payout = :kuba_payout";
            $params[':kuba_payout'] = $kubaPayout;
            
            echo "2. Do wypłaty Kuba:\n";
            echo "   Wzór: Kuba% - (Agent1% + Agent2% + Agent3%)\n";
            echo "   Do wypłaty Kuba = {$kubaPercentage}% - ({$agent1Percentage}% + {$agent2Percentage}% + {$agent3Percentage}%) = {$kubaPayout}%\n\n";
            
            // 3. Ostatnia rata (T = F - SUM(N, P, R))
            $installment1 = !empty($case['installment1_amount']) ? floatval($case['installment1_amount']) : 0;
            $installment2 = !empty($case['installment2_amount']) ? floatval($case['installment2_amount']) : 0;
            $installment3 = !empty($case['installment3_amount']) ? floatval($case['installment3_amount']) : 0;
            $finalInstallment = $totalCommission - ($installment1 + $installment2 + $installment3);
            $finalInstallment = max(0, $finalInstallment);
            $updates[] = "final_installment_amount = :final_installment_amount";
            $params[':final_installment_amount'] = $finalInstallment;
            
            echo "3. Ostatnia rata:\n";
            echo "   Wzór: Całość prowizji - (Rata1 + Rata2 + Rata3)\n";
            echo "   Ostatnia rata = {$totalCommission} - ({$installment1} + {$installment2} + {$installment3}) = {$finalInstallment} zł\n\n";
            
            // 4. Podział rat dla Kuby (V, X, Z, AB)
            $kubaFraction = $kubaPayout / 100;
            
            $kubaInstallment1 = $installment1 * $kubaFraction;
            $kubaInstallment2 = $installment2 * $kubaFraction;
            $kubaInstallment3 = $installment3 * $kubaFraction;
            $kubaFinalInstallment = $finalInstallment * $kubaFraction;
            
            $updates[] = "kuba_installment1_amount = :kuba_installment1_amount";
            $updates[] = "kuba_installment2_amount = :kuba_installment2_amount";
            $updates[] = "kuba_installment3_amount = :kuba_installment3_amount";
            $updates[] = "kuba_final_installment_amount = :kuba_final_installment_amount";
            $params[':kuba_installment1_amount'] = $kubaInstallment1;
            $params[':kuba_installment2_amount'] = $kubaInstallment2;
            $params[':kuba_installment3_amount'] = $kubaInstallment3;
            $params[':kuba_final_installment_amount'] = $kubaFinalInstallment;
            
            echo "4. Podział rat dla Kuby:\n";
            echo "   Wzór: Rata * (Do wypłaty Kuba / 100)\n";
            echo "   Współczynnik Kuby: {$kubaPayout}% / 100 = {$kubaFraction}\n";
            echo "   Rata 1 Kuby = {$installment1} * {$kubaFraction} = {$kubaInstallment1} zł\n";
            echo "   Rata 2 Kuby = {$installment2} * {$kubaFraction} = {$kubaInstallment2} zł\n";
            echo "   Rata 3 Kuby = {$installment3} * {$kubaFraction} = {$kubaInstallment3} zł\n";
            echo "   Rata 4 Kuby = {$finalInstallment} * {$kubaFraction} = {$kubaFinalInstallment} zł\n\n";
            
            // Zapisz zmiany w bazie
            $updateQuery = "UPDATE test2 SET " . implode(", ", $updates) . " WHERE id = :id";
            $params[':id'] = $caseId;
            $updateStmt = $this->pdo->prepare($updateQuery);
            $result = $updateStmt->execute($params);
            
            echo $result ? "Zapisano zmiany w bazie danych." : "Błąd podczas zapisywania zmian!";
            echo "</pre>";
            
            echo "<p><a href='/table'>Powrót do listy spraw</a></p>";
            
        } catch (PDOException $e) {
            echo "<h2>Błąd!</h2>";
            echo "<pre>";
            echo "Wystąpił błąd podczas przeliczania prowizji: " . $e->getMessage();
            echo "</pre>";
        }
    }
}
