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
        
        // Ensure commission invoice columns exist
        $this->ensureCommissionColumnsExist();
        
        // Check if payment statuses should be synchronized automatically
        // Do this only once per hour to avoid excessive database operations
        $lastSync = isset($_SESSION['last_payment_sync']) ? $_SESSION['last_payment_sync'] : 0;
        $currentTime = time();
        $oneHourInSeconds = 3600;
        
        if ($currentTime - $lastSync > $oneHourInSeconds) {
            error_log("TableController::index - Auto-syncing payment statuses (last sync was " . 
                     ($lastSync > 0 ? date('Y-m-d H:i:s', $lastSync) : 'never') . ")");
            $this->syncPaymentStatusesInternal();
        } else {
            error_log("TableController::index - Skipping auto-sync, last sync was less than an hour ago");
        }

        // Pobierz listę agentów
        error_log("TableController::index - Pobieranie listy agentów");
        $agents = $this->getAgents();
        error_log("TableController::index - Znaleziono " . count($agents) . " agentów");

        // Jeśli wybrano agenta, wyświetl sprawy tego agenta
        $selectedAgentId = isset($_GET['agent_id']) ? $_GET['agent_id'] : null;
        $selectedAgent = null;
        error_log("TableController::index - Wybrany agent ID: " . ($selectedAgentId ?: 'brak'));

        // Handle the special Jakub case
        if ($selectedAgentId === 'jakub' || $selectedAgentId === 'Jakub') {
            $selectedAgent = [
                'imie' => 'Jakub',
                'nazwisko' => 'Kowalski',
                'agent_id' => 'jakub'
            ];
            error_log("TableController::index - Wybrano specjalnego agenta Jakub");
        } elseif ($selectedAgentId) {
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
                error_log("--- Przeliczanie sprawy ID: {$id}, nazwa: " . ($case['case_name'] ?? 'brak') . " ---");

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
            
            // After calculating commissions, also sync payment statuses
            $this->syncPaymentStatusesInternal();
            
        } catch (PDOException $e) {
            error_log("BŁĄD KRYTYCZNY calculating commissions: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
        }
    }
    
    /**
     * Internal method to sync payment statuses without UI feedback
     * Used when calculating commissions
     */
    private function syncPaymentStatusesInternal(): void
    {
        error_log("TableController::syncPaymentStatusesInternal - Start");
        
        try {
            // Get all cases from test2 table
            $casesQuery = "SELECT id, case_name FROM test2";
            $casesStmt = $this->pdo->query($casesQuery);
            $cases = $casesStmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("TableController::syncPaymentStatusesInternal - Found " . count($cases) . " cases");

            // Process each case
            foreach ($cases as $case) {
                $caseId = $case['id'];
                $caseName = $case['case_name'];
                
                if (empty($caseName)) {
                    error_log("TableController::syncPaymentStatusesInternal - Case ID {$caseId} has no name, skipping");
                    continue;
                }
                
                error_log("TableController::syncPaymentStatusesInternal - Processing case ID {$caseId}: {$caseName}");
                
                // Find paid invoices in test table that match the case name
                // Use LIKE for more flexible matching and ensure proper ordering
                $invoicesQuery = "
                    SELECT *, STR_TO_DATE(`Data płatności`, '%Y-%m-%d') as paid_date 
                    FROM test 
                    WHERE (Nabywca = :case_name OR Nabywca LIKE :case_name_partial OR :case_name LIKE CONCAT('%', Nabywca, '%')) 
                        AND Status = 'Opłacona'
                    ORDER BY paid_date ASC, numer ASC";
                $invoicesStmt = $this->pdo->prepare($invoicesQuery);
                $invoicesStmt->execute([
                    ':case_name' => $caseName,
                    ':case_name_partial' => "%{$caseName}%"
                ]);
                $invoices = $invoicesStmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("TableController::syncPaymentStatusesInternal - Found " . count($invoices) . " matching paid invoices for case {$caseName}");
                
                // Reset payment status for this case (to avoid duplicate markings)
                $resetQuery = "
                    UPDATE test2 
                    SET installment1_paid = 0, installment2_paid = 0, installment3_paid = 0, final_installment_paid = 0,
                        installment1_paid_invoice = NULL, installment2_paid_invoice = NULL, 
                        installment3_paid_invoice = NULL, final_installment_paid_invoice = NULL
                    WHERE id = :case_id";
                $resetStmt = $this->pdo->prepare($resetQuery);
                $resetStmt->execute([':case_id' => $caseId]);
                
                // Get installment amounts for this case
                $installmentsQuery = "
                    SELECT 
                        installment1_amount, 
                        installment2_amount, 
                        installment3_amount, 
                        final_installment_amount 
                    FROM test2 
                    WHERE id = :case_id";
                $installmentsStmt = $this->pdo->prepare($installmentsQuery);
                $installmentsStmt->execute([':case_id' => $caseId]);
                $installments = $installmentsStmt->fetch(PDO::FETCH_ASSOC);
                
                // Store installment amounts for direct comparisons
                $installmentAmounts = [
                    'installment1_paid' => floatval($installments['installment1_amount'] ?? 0),
                    'installment2_paid' => floatval($installments['installment2_amount'] ?? 0),
                    'installment3_paid' => floatval($installments['installment3_amount'] ?? 0),
                    'final_installment_paid' => floatval($installments['final_installment_amount'] ?? 0)
                ];
                
                // First attempt - match invoices with installments by amount
                $alreadyMatchedInvoices = [];
                $installmentMatched = [
                    'installment1_paid' => false,
                    'installment2_paid' => false,
                    'installment3_paid' => false,
                    'final_installment_paid' => false
                ];
                
                // Try to match invoices with installments by exact amount
                foreach ($invoices as $invoiceIndex => $invoice) {
                    $invoiceAmount = floatval($invoice['Kwota opłacona']);
                    
                    // Skip already matched invoices
                    if (in_array($invoiceIndex, $alreadyMatchedInvoices)) {
                        continue;
                    }
                    
                    // Try to find exact amount match
                    foreach ($installmentAmounts as $field => $amount) {
                        if ($amount > 0 && abs($invoiceAmount - $amount) < 0.01 && !$installmentMatched[$field]) {
                            // We found an exact match
                            error_log("TableController::syncPaymentStatusesInternal - Found exact amount match for {$field}: invoice amount {$invoiceAmount}, installment amount {$amount}");
                            
                            // Update the payment status
                            $updateQuery = "UPDATE test2 SET {$field} = 1, {$field}_invoice = :invoice_number WHERE id = :case_id";
                            $updateStmt = $this->pdo->prepare($updateQuery);
                            $updateStmt->execute([
                                ':case_id' => $caseId,
                                ':invoice_number' => $invoice['numer']
                            ]);
                            
                            error_log("TableController::syncPaymentStatusesInternal - Found exact amount match for {$field}: invoice amount {$invoiceAmount}, installment amount {$amount}, invoice: " . $invoice['numer']);
                            
                            $installmentMatched[$field] = true;
                            $alreadyMatchedInvoices[] = $invoiceIndex;
                            break;
                        }
                    }
                }
                
                // Second attempt - for invoices that weren't matched by amount, match them in chronological order
                $installmentFields = ['installment1_paid', 'installment2_paid', 'installment3_paid', 'final_installment_paid'];
                $nextUnmatchedInstallment = 0;
                
                // Find next unmatched installment
                while ($nextUnmatchedInstallment < count($installmentFields) && 
                       $installmentMatched[$installmentFields[$nextUnmatchedInstallment]]) {
                    $nextUnmatchedInstallment++;
                }
                
                // Process remaining invoices in chronological order
                foreach ($invoices as $invoiceIndex => $invoice) {
                    // Skip already matched invoices
                    if (in_array($invoiceIndex, $alreadyMatchedInvoices)) {
                        continue;
                    }
                    
                    $invoiceNumber = $invoice['numer'];
                    $invoiceDate = $invoice['Data płatności'];
                    $invoiceAmount = $invoice['Kwota opłacona'];
                    
                    error_log("TableController::syncPaymentStatusesInternal - Processing unmatched invoice {$invoiceNumber} from {$invoiceDate} for amount {$invoiceAmount}");
                    
                    // If we still have unmatched installments, mark the next one as paid
                    if ($nextUnmatchedInstallment < count($installmentFields)) {
                        $installmentField = $installmentFields[$nextUnmatchedInstallment];
                        
                        // Update the payment status
                        $updateQuery = "UPDATE test2 SET {$installmentField} = 1, {$installmentField}_invoice = :invoice_number WHERE id = :case_id";
                        $updateStmt = $this->pdo->prepare($updateQuery);
                        $updateStmt->execute([
                            ':case_id' => $caseId,
                            ':invoice_number' => $invoice['numer']
                        ]);
                        
                        error_log("TableController::syncPaymentStatusesInternal - Marked {$installmentField} as paid for case ID {$caseId} (chronological order), invoice: " . $invoice['numer']);
                        
                        $installmentMatched[$installmentField] = true;
                        $nextUnmatchedInstallment++;
                        
                        // Find next unmatched installment
                        while ($nextUnmatchedInstallment < count($installmentFields) && 
                              $installmentMatched[$installmentFields[$nextUnmatchedInstallment]]) {
                            $nextUnmatchedInstallment++;
                        }
                    } else {
                        error_log("TableController::syncPaymentStatusesInternal - No more unmatched installments available for case {$caseName}");
                        break;
                    }
                }
            }
            
            error_log("TableController::syncPaymentStatusesInternal - Payment statuses synchronized successfully");
            
            // Store the last synchronization timestamp in session
            $_SESSION['last_payment_sync'] = time();
        } catch (PDOException $e) {
            error_log("TableController::syncPaymentStatusesInternal - ERROR: " . $e->getMessage());
            error_log("TableController::syncPaymentStatusesInternal - Stack trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Pobierz sprawy przypisane do danego agenta
     */
    public function getAgentCases($agentId): array
    {
        try {
            // For the special Jakub agent, return all cases
            if ($agentId === 'jakub' || $agentId === 'Jakub') {
                $query = "SELECT t.id, t.case_name, t.is_completed, t.amount_won, t.upfront_fee, 
                    t.success_fee_percentage, t.total_commission, t.kuba_percentage, t.kuba_payout, 
                    t.kuba_installment1_amount, t.kuba_installment2_amount, t.kuba_installment3_amount, 
                    t.kuba_final_installment_amount, t.kuba_invoice_number, 
                    t.agent1_installment1_amount, t.agent1_installment2_amount, t.agent1_installment3_amount, 
                    t.agent1_final_installment_amount, t.agent2_installment1_amount, t.agent2_installment2_amount, 
                    t.agent2_installment3_amount, t.agent2_final_installment_amount, t.agent3_installment1_amount, 
                    t.agent3_installment2_amount, t.agent3_installment3_amount, t.agent3_final_installment_amount, 
                    t.installment1_amount, t.installment1_paid, t.installment1_paid_invoice, 
                    t.installment2_amount, t.installment2_paid, t.installment2_paid_invoice, 
                    t.installment3_amount, t.installment3_paid, t.installment3_paid_invoice, 
                    t.final_installment_amount, t.final_installment_paid, t.final_installment_paid_invoice,
                    t.installment1_commission_paid, t.installment2_commission_paid, 
                    t.installment3_commission_paid, t.final_installment_commission_paid,
                    t.installment1_commission_invoice, t.installment2_commission_invoice,
                    t.installment3_commission_invoice, t.final_installment_commission_invoice,
                    NULL as rola, NULL as percentage 
                    FROM test2 t 
                    ORDER BY t.id DESC";
                $stmt = $this->pdo->query($query);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Convert to integer for normal agent IDs
            $agentId = (int)$agentId;
            
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
    
            // Add special static Jakub button at the top
            echo '<a href="/table?agent_id=jakub" class="agent-button jakub-agent-button">' .
                 'Jakub Kowalski (Administrator)</a>';
    
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
    
    // Update the method to use CSS classes for agent highlighting
    private function getAgentHighlightClass($agentId) {
        // Get the last digit of the agent ID to determine which class to use
        $digit = $agentId % 10;
        return "agent-highlight-{$digit}";
    }

    // Modify renderTable method to use the CSS classes for agent highlighting in Jakub's view
    public function renderTable($agentId = null): void
    {
        try {
            // Check if we're viewing special Jakub view
            $isJakubView = ($agentId === 'jakub' || $agentId === 'Jakub');
            
            // Przelicz prowizje
            $this->calculateCommissions();

            // Jeśli podano ID agenta, pobierz tylko jego sprawy
            if ($agentId && !$isJakubView) {
                // Regular agent view - no highlighting for Kuba's data
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
                    
                    // First case - when an agent is selected
                    // Buduj wiersz danych z informacjami o sprawie i agentach
                    $row = [
                        'ID' => $case['id'], // Dodajemy ID do wiersza dla przycisku edycji
                        'Akcje' => $this->renderActionButtons($case['id']), // Dodajemy przycisk edycji
                        'Sprawa' => $case['case_name'],
                        'Zakończona?' => $case['is_completed'] ? 'Tak' : 'Nie',
                        'Wywalczona kwota' => $case['amount_won'],
                        'Opłata wstępna' => $case['upfront_fee'],
                        'Success fee %' => $case['success_fee_percentage'],
                        'Całość prowizji' => $case['total_commission'],
                    ];
                    
                    error_log("Dane sprawy ID {$case['id']}: Kuba%={$case['kuba_percentage']}, DoWypłaty={$case['kuba_payout']}");
                    error_log("Raty Kuby: " . 
                             "Rata 1=" . ($case['kuba_installment1_amount'] ?? 'brak') . ", " .
                             "Rata 2=" . ($case['kuba_installment2_amount'] ?? 'brak') . ", " .
                             "Rata 3=" . ($case['kuba_installment3_amount'] ?? 'brak') . ", " .
                             "Rata 4=" . ($case['kuba_final_installment_amount'] ?? 'brak'));
                    
                    // 1. Dodaj dane Kuby - połączone w jedną sekcję "Kuba" w widoku
                    $row['Kuba'] = [
                        'Prowizja %' => $case['kuba_percentage'] . '%',
                        'Do wypłaty' => $case['kuba_payout'] . '%',
                        'Rata 1' => $case['kuba_installment1_amount'],
                        'Rata 2' => $case['kuba_installment2_amount'],
                        'Rata 3' => $case['kuba_installment3_amount'],
                        'Rata 4' => $case['kuba_final_installment_amount'],
                        'Nr faktury' => $case['kuba_invoice_number']
                    ];
                    
                    // 2. Dodaj dane agentów do wiersza - każdy agent to osobna sekcja
                    for ($i = 1; $i <= 3; $i++) {
                        $agentRole = "agent_{$i}";
                        if (isset($agentsData[$agentRole])) {
                            // Sprawdź, czy to jest wybrany agent i dodaj odpowiednią klasę
                            $isSelected = ($agentsData[$agentRole]['agent_id'] == $agentId);
                            $cssClass = $isSelected ? 'agent-name-highlight selected-agent' : 'agent-name-highlight';
                            
                            // Utwórz nazwę agenta
                            $agentName = sprintf(
                                '<span class="%s">%s</span>', 
                                $cssClass,
                                htmlspecialchars($agentsData[$agentRole]['imie_nazwisko'])
                            );
                            
                            // Dodaj procent prowizji z podświetleniem
                            $percentValue = $agentsData[$agentRole]['percentage'];
                            $percentDisplay = $isSelected ? 
                                sprintf('<span class="selected-agent">%s%%</span>', $percentValue) : 
                                $percentValue . '%';
                                    
                            // Przygotuj dane rat dla agenta
                            $installment1 = $case["agent{$i}_installment1_amount"];
                            $installment2 = $case["agent{$i}_installment2_amount"];
                            $installment3 = $case["agent{$i}_installment3_amount"];
                            $installment4 = $case["agent{$i}_final_installment_amount"];
                            
                            // Jeśli to wybrany agent, dodaj podświetlenie dla rat
                            if ($isSelected) {
                                $installment1 = sprintf('<span class="selected-agent">%s zł</span>', number_format((float)$installment1, 2, ',', ' '));
                                $installment2 = sprintf('<span class="selected-agent">%s zł</span>', number_format((float)$installment2, 2, ',', ' '));
                                $installment3 = sprintf('<span class="selected-agent">%s zł</span>', number_format((float)$installment3, 2, ',', ' '));
                                $installment4 = sprintf('<span class="selected-agent">%s zł</span>', number_format((float)$installment4, 2, ',', ' '));
                            }
                            
                            // Dodaj sekcję agenta
                            $row["Agent {$i}"] = [
                                'Nazwa' => $agentName,
                                'Prowizja %' => $percentDisplay,
                                'Rata 1' => $installment1,
                                'Rata 2' => $installment2,
                                'Rata 3' => $installment3,
                                'Rata 4' => $installment4
                            ];
                        } else {
                            $row["Agent {$i}"] = [
                                'Nazwa' => '',
                                'Prowizja %' => '',
                                'Rata 1' => '',
                                'Rata 2' => '',
                                'Rata 3' => '',
                                'Rata 4' => ''
                            ];
                        }
                    }
                    
                    // 3. Dodaj ogólne raty na końcu
                    $row = array_merge($row, [
                        'Rata 1' => $case['installment1_amount'],
                        'Opłacona 1' => $case['installment1_paid'] ? 'Tak' : 'Nie',
                        'Rata 2' => $case['installment2_amount'],
                        'Opłacona 2' => $case['installment2_paid'] ? 'Tak' : 'Nie',
                        'Rata 3' => $case['installment3_amount'],
                        'Opłacona 3' => $case['installment3_paid'] ? 'Tak' : 'Nie',
                        'Rata 4' => $case['final_installment_amount'],
                        'Opłacona 4' => $case['final_installment_paid'] ? 'Tak' : 'Nie',
                    ]);
                    
                    // Store invoice data in the row without creating visible columns
                    $row['installment1_paid_invoice'] = $case['installment1_paid_invoice'] ?? '';
                    $row['installment2_paid_invoice'] = $case['installment2_paid_invoice'] ?? '';
                    $row['installment3_paid_invoice'] = $case['installment3_paid_invoice'] ?? '';
                    $row['final_installment_paid_invoice'] = $case['final_installment_paid_invoice'] ?? '';
                    
                    // Add commission payment status
                    $row['installment1_commission_paid'] = $case['installment1_commission_paid'] ?? 0;
                    $row['installment2_commission_paid'] = $case['installment2_commission_paid'] ?? 0;
                    $row['installment3_commission_paid'] = $case['installment3_commission_paid'] ?? 0;
                    $row['final_installment_commission_paid'] = $case['final_installment_commission_paid'] ?? 0;
                    
                    // Add commission invoice numbers
                    $row['installment1_commission_invoice'] = $case['installment1_commission_invoice'] ?? '';
                    $row['installment2_commission_invoice'] = $case['installment2_commission_invoice'] ?? '';
                    $row['installment3_commission_invoice'] = $case['installment3_commission_invoice'] ?? '';
                    $row['final_installment_commission_invoice'] = $case['final_installment_commission_invoice'] ?? '';
                    
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
                        t.kuba_installment1_amount AS 'Rata 1 – Kuba',
                        t.kuba_installment2_amount AS 'Rata 2 – Kuba',
                        t.kuba_installment3_amount AS 'Rata 3 – Kuba',
                        t.kuba_final_installment_amount AS 'Rata 4 – Kuba',
                        t.kuba_invoice_number AS 'Nr faktury',
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
                        t.agent3_final_installment_amount AS 'Rata 4 – Agent 3',
                        t.installment1_amount AS 'Rata 1',
                        CASE WHEN t.installment1_paid = 1 THEN 'Tak' WHEN t.installment1_paid = 0 THEN 'Nie' ELSE '' END AS 'Opłacona 1',
                        t.installment1_paid_invoice,
                        t.installment2_amount AS 'Rata 2',
                        CASE WHEN t.installment2_paid = 1 THEN 'Tak' WHEN t.installment2_paid = 0 THEN 'Nie' ELSE '' END AS 'Opłacona 2',
                        t.installment2_paid_invoice,
                        t.installment3_amount AS 'Rata 3',
                        CASE WHEN t.installment3_paid = 1 THEN 'Tak' WHEN t.installment3_paid = 0 THEN 'Nie' ELSE '' END AS 'Opłacona 3',
                        t.installment3_paid_invoice,
                        t.final_installment_amount AS 'Rata 4',
                        CASE WHEN t.final_installment_paid = 1 THEN 'Tak' WHEN t.final_installment_paid = 0 THEN 'Nie' ELSE '' END AS 'Opłacona 4',
                        t.final_installment_paid_invoice,
                        t.installment1_commission_paid,
                        t.installment2_commission_paid,
                        t.installment3_commission_paid,
                        t.final_installment_commission_paid,
                        t.installment1_commission_invoice,
                        t.installment2_commission_invoice,
                        t.installment3_commission_invoice,
                        t.final_installment_commission_invoice
                    FROM test2 t
                    ORDER BY t.id DESC";

                $stmt = $this->pdo->query($query);
                $casesTemp = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $rows = [];
                foreach ($casesTemp as $case) {
                    $caseId = $case['id'];
                    
                    // Dodajemy kolumnę akcji z przyciskiem edycji
                    $actionButtons = $this->renderActionButtons($caseId);
                    $newCase = [
                        'ID' => $caseId,
                        'Akcje' => $actionButtons,
                        'Sprawa' => $case['Sprawa'],
                        'Zakończona?' => $case['Zakończona?'],
                        'Wywalczona kwota' => $case['Wywalczona kwota'],
                        'Opłata wstępna' => $case['Opłata wstępna'],
                        'Success fee %' => $case['Success fee %'],
                        'Całość prowizji' => $case['Całość prowizji']
                    ];
                    
                    // Debug log to see all available keys in the case row
                    error_log("Keys in case row for case ID {$caseId}: " . implode(", ", array_keys($case)));
                    
                    // 1. Dodaj dane Kuby - połączone w jedną sekcję "Kuba" w widoku
                    $kubaData = [
                        'Prowizja %' => $case['Prowizja % Kuba'],
                        'Do wypłaty' => $case['Do wypłaty Kuba'],
                        'Rata 1' => $case['Rata 1 – Kuba'],
                        'Rata 2' => $case['Rata 2 – Kuba'],
                        'Rata 3' => $case['Rata 3 – Kuba'],
                        'Rata 4' => $case['Rata 4 – Kuba'],
                        'Nr faktury' => $case['Nr faktury']
                    ];
                    
                    // Dla widoku Jakuba, dodaj podświetlenie dla danych Kuby
                    if ($isJakubView) {
                        foreach ($kubaData as $key => $value) {
                            if ($key !== 'Nr faktury') { // Nie formatuj numeru faktury
                                if ($key === 'Prowizja %' || $key === 'Do wypłaty') {
                                    $kubaData[$key] = '<span class="kuba-highlight">' . $value . '%</span>';
                                } else {
                                    $kubaData[$key] = '<span class="kuba-highlight">' . number_format((float)$value, 2, ',', ' ') . ' zł</span>';
                                }
                            }
                        }
                    } else {
                        // Formatuj bez podświetlenia
                        foreach ($kubaData as $key => $value) {
                            if ($key !== 'Nr faktury' && is_numeric($value)) { // Nie formatuj numeru faktury
                                if ($key === 'Prowizja %' || $key === 'Do wypłaty') {
                                    $kubaData[$key] = $value . '%';
                                } else {
                                    $kubaData[$key] = number_format((float)$value, 2, ',', ' ') . ' zł';
                                }
                            }
                        }
                    }
                    
                    $newCase['Kuba'] = $kubaData;
                    
                    // Pobierz informacje o agentach dla tej sprawy
                    $agentsQuery = "
                        SELECT a.imie, a.nazwisko, sa.rola, sa.percentage, sa.agent_id
                        FROM sprawa_agent sa
                        JOIN agenci a ON sa.agent_id = a.agent_id
                        WHERE sa.sprawa_id = :sprawa_id
                        ORDER BY sa.rola";
                    
                    $agentsStmt = $this->pdo->prepare($agentsQuery);
                    $agentsStmt->execute([':sprawa_id' => $caseId]);
                    $agents = $agentsStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // 2. Przygotuj dane dla agentów 1-3
                    for ($i = 1; $i <= 3; $i++) {
                        $agentData = [
                            'Nazwa' => '',
                            'Prowizja %' => '',
                            'Rata 1' => '',
                            'Rata 2' => '',
                            'Rata 3' => '',
                            'Rata 4' => ''
                        ];
                        
                        // Znajdź dane agenta o tej roli
                        $agentFound = false;
                        foreach ($agents as $agent) {
                            if (preg_match('/agent_' . $i . '/', $agent['rola'])) {
                                $agentFound = true;
                                
                                if ($isJakubView) {
                                    // Dla Jakuba - zastosuj klasy podświetlenia agenta
                                    $highlightClass = $this->getAgentHighlightClass($agent['agent_id']);
                                    
                                    // Nazwa agenta z podświetleniem
                                    $agentData['Nazwa'] = sprintf(
                                        '<span class="%s">%s</span>', 
                                        $highlightClass,
                                        htmlspecialchars($agent['imie'] . ' ' . $agent['nazwisko'])
                                    );
                                    
                                    // Procent prowizji z podświetleniem
                                    $agentData['Prowizja %'] = sprintf(
                                        '<span class="%s">%s%%</span>',
                                        $highlightClass,
                                        $agent['percentage']
                                    );
                                    
                                    // Raty z podświetleniem
                                    $rata1 = $case["Rata 1 – Agent {$i}"];
                                    $rata2 = $case["Rata 2 – Agent {$i}"];
                                    $rata3 = $case["Rata 3 – Agent {$i}"];
                                    $rata4 = $case["Rata 4 – Agent {$i}"];
                                    
                                    $agentData['Rata 1'] = sprintf(
                                        '<span class="%s">%s zł</span>',
                                        $highlightClass,
                                        number_format((float)$rata1, 2, ',', ' ')
                                    );
                                    
                                    $agentData['Rata 2'] = sprintf(
                                        '<span class="%s">%s zł</span>',
                                        $highlightClass,
                                        number_format((float)$rata2, 2, ',', ' ')
                                    );
                                    
                                    $agentData['Rata 3'] = sprintf(
                                        '<span class="%s">%s zł</span>',
                                        $highlightClass,
                                        number_format((float)$rata3, 2, ',', ' ')
                                    );
                                    
                                    $agentData['Rata 4'] = sprintf(
                                        '<span class="%s">%s zł</span>',
                                        $highlightClass,
                                        number_format((float)$rata4, 2, ',', ' ')
                                    );
                                } else {
                                    // Dla normalnego widoku - podstawowe formatowanie
                                    $agentData['Nazwa'] = sprintf(
                                        '<span class="agent-name-highlight">%s</span>', 
                                        htmlspecialchars($agent['imie'] . ' ' . $agent['nazwisko'])
                                    );
                                    
                                    $agentData['Prowizja %'] = $agent['percentage'] . '%';
                                    
                                    // Formatuj raty
                                    $rata1 = $case["Rata 1 – Agent {$i}"];
                                    $rata2 = $case["Rata 2 – Agent {$i}"];
                                    $rata3 = $case["Rata 3 – Agent {$i}"];
                                    $rata4 = $case["Rata 4 – Agent {$i}"];
                                    
                                    $agentData['Rata 1'] = number_format((float)$rata1, 2, ',', ' ') . ' zł';
                                    $agentData['Rata 2'] = number_format((float)$rata2, 2, ',', ' ') . ' zł';
                                    $agentData['Rata 3'] = number_format((float)$rata3, 2, ',', ' ') . ' zł';
                                    $agentData['Rata 4'] = number_format((float)$rata4, 2, ',', ' ') . ' zł';
                                }
                                
                                break;
                            }
                        }
                        
                        $newCase["Agent {$i}"] = $agentData;
                    }
                    
                    // 3. Dodaj ogólne raty na końcu
                    $newCase = array_merge($newCase, [
                        'Rata 1' => $case['Rata 1'],
                        'Opłacona 1' => $case['Opłacona 1'],
                        'Rata 2' => $case['Rata 2'],
                        'Opłacona 2' => $case['Opłacona 2'],
                        'Rata 3' => $case['Rata 3'],
                        'Opłacona 3' => $case['Opłacona 3'],
                        'Rata 4' => $case['Rata 4'],
                        'Opłacona 4' => $case['Opłacona 4']
                    ]);
                    
                    // Store invoice data in the row without creating visible columns
                    $newCase['installment1_paid_invoice'] = $case['installment1_paid_invoice'] ?? '';
                    $newCase['installment2_paid_invoice'] = $case['installment2_paid_invoice'] ?? '';
                    $newCase['installment3_paid_invoice'] = $case['installment3_paid_invoice'] ?? '';
                    $newCase['final_installment_paid_invoice'] = $case['final_installment_paid_invoice'] ?? '';
                    
                    // Add commission payment status
                    $newCase['installment1_commission_paid'] = $case['installment1_commission_paid'] ?? 0;
                    $newCase['installment2_commission_paid'] = $case['installment2_commission_paid'] ?? 0;
                    $newCase['installment3_commission_paid'] = $case['installment3_commission_paid'] ?? 0;
                    $newCase['final_installment_commission_paid'] = $case['final_installment_commission_paid'] ?? 0;
                    
                    // Add commission invoice numbers
                    $newCase['installment1_commission_invoice'] = $case['installment1_commission_invoice'] ?? '';
                    $newCase['installment2_commission_invoice'] = $case['installment2_commission_invoice'] ?? '';
                    $newCase['installment3_commission_invoice'] = $case['installment3_commission_invoice'] ?? '';
                    $newCase['final_installment_commission_invoice'] = $case['final_installment_commission_invoice'] ?? '';
                    
                    $rows[] = $newCase;
                }
            }

            if (empty($rows)) {
                echo '<p style="text-align:center;">Brak danych.</p>';
                return;
            }

            // Define all headers before filtering
            $allHeaders = array_keys(reset($rows));

            // Filter out the invoice fields from the headers
            $invoiceFields = ['installment1_paid_invoice', 'installment2_paid_invoice', 'installment3_paid_invoice', 'final_installment_paid_invoice'];
            $allHeaders = array_filter($allHeaders, function($header) use ($invoiceFields) {
                return !in_array($header, $invoiceFields);
            });

            // Filter out commission paid columns
            $commissionFields = ['installment1_commission_paid', 'installment2_commission_paid', 'installment3_commission_paid', 'final_installment_commission_paid'];
            $allHeaders = array_filter($allHeaders, function($header) use ($commissionFields) {
                return !in_array($header, $commissionFields);
            });
            
            // FILTR: zostawiamy tylko te nagłówki, które mają choć jeden nie-pusty rekord
            $visibleHeaders = array_filter($allHeaders, function ($title) use ($rows) {
                // For non-rate columns or Kuba's rates, keep the original logic
                if (!(strpos($title, 'Rata') !== false && strpos($title, 'Agent') !== false)) {
                    // Check if column has any non-empty values (original logic)
                    foreach ($rows as $row) {
                        if (isset($row[$title]) && $row[$title] !== '' && $row[$title] !== null) {
                            // Handle array data (nested headers)
                            if (is_array($row[$title])) {
                                // At least one sub-value needs to be non-empty
                                foreach ($row[$title] as $subValue) {
                                    if ($subValue !== '' && $subValue !== null) {
                                        return true;
                                    }
                                }
                                return false;
                            }
                            return true;
                        }
                    }
                    return false;
                }
                
                // For agent rate columns, check if they have any non-zero values
                $hasNonZeroValue = false;
                
                foreach ($rows as $row) {
                    if (!isset($row[$title])) continue;
                    
                    $value = $row[$title];
                    
                    // Check if it's nested data
                    if (is_array($value)) {
                        foreach ($value as $subValue) {
                            if (is_string($subValue) && !empty($subValue)) {
                                return true;
                            }
                        }
                        continue;
                    }
                    
                    $numericValue = 0;
                    
                    // Extract numeric value from possibly formatted or HTML content
                    if (is_string($value)) {
                        // Remove HTML tags if present
                        $plainValue = strip_tags($value);
                        
                        // Extract first number from the string
                        if (preg_match('/[0-9,.]+/', $plainValue, $matches)) {
                            // Clean up and convert to float
                            $cleanedValue = str_replace([',', ' ', 'zł'], ['', '', ''], $matches[0]);
                            $numericValue = floatval(str_replace(',', '.', $cleanedValue));
                        }
                    } elseif (is_numeric($value)) {
                        $numericValue = floatval($value);
                    }
                    
                    // If column has a non-zero value, include it
                    if ($numericValue > 0) {
                        $hasNonZeroValue = true;
                        break;
                    }
                }
                
                return $hasNonZeroValue;
            });

            error_log("Filtered out empty rate columns. Before: " . count($allHeaders) . ", After: " . count($visibleHeaders));

            echo '<table class="data-table"><thead><tr>';
            foreach ($visibleHeaders as $title) {
                // Sprawdź czy to kolumna z zagnieżdżonymi danymi
                $isCollapsible = false;
                foreach ($rows as $row) {
                    if (isset($row[$title]) && is_array($row[$title])) {
                        $isCollapsible = true;
                        break;
                    }
                }
                
                $collapseClass = $isCollapsible ? 'collapsible' : 'sortable';
                $collapseIcon = $isCollapsible ? '<i class="fa-solid fa-chevron-right collapse-icon"></i>' : '';
                
                echo '<th class="' . $collapseClass . '" data-column="' . htmlspecialchars($title, ENT_QUOTES) . '">'
                    . htmlspecialchars($title, ENT_QUOTES) . '</th>';
            }
            echo '</tr></thead><tbody>';

            // Display rows using only the visible headers
            foreach ($rows as $row) {
                $dataAttributes = ' data-id="' . $row['ID'] . '"';
                
                // Add commission invoice attributes
                if (isset($row['installment1_commission_invoice'])) {
                    $dataAttributes .= ' data-installment1_commission_invoice="' . htmlspecialchars($row['installment1_commission_invoice'], ENT_QUOTES) . '"';
                }
                if (isset($row['installment2_commission_invoice'])) {
                    $dataAttributes .= ' data-installment2_commission_invoice="' . htmlspecialchars($row['installment2_commission_invoice'], ENT_QUOTES) . '"';
                }
                if (isset($row['installment3_commission_invoice'])) {
                    $dataAttributes .= ' data-installment3_commission_invoice="' . htmlspecialchars($row['installment3_commission_invoice'], ENT_QUOTES) . '"';
                }
                if (isset($row['final_installment_commission_invoice'])) {
                    $dataAttributes .= ' data-final_installment_commission_invoice="' . htmlspecialchars($row['final_installment_commission_invoice'], ENT_QUOTES) . '"';
                }
                
                echo '<tr' . $dataAttributes . '>';
                foreach ($visibleHeaders as $title) {
                    $value = $row[$title] ?? '';

                    if ($title === 'Akcje') {
                        // Dla kolumny akcji, po prostu wyświetl wartość HTML
                        echo '<td class="action-buttons">' . $value . '</td>';
                    } elseif ($title === 'ID') {
                        // Dla kolumny ID, po prostu wyświetl wartość
                        echo '<td>' . $value . '</td>';
                    } elseif (is_array($value)) {
                        // Kolumna z danymi agenta - używamy nowego formatu dla rozwijania w prawo
                        if ($title === 'Kuba' || strpos($title, 'Agent') === 0) {
                            // Wyciągnij dane agenta z tablicy
                            $name = '';
                            $percent = '';
                            $wypłata = '';
                            $rata1 = '';
                            $rata2 = '';
                            $rata3 = '';
                            $rata4 = '';
                            $invoice = '';
                            
                            foreach ($value as $key => $val) {
                                if ($key === 'Nazwa') {
                                    $name = $val;
                                } elseif ($key === 'Prowizja %') {
                                    $percent = $val;
                                } elseif ($key === 'Do wypłaty') {
                                    $wypłata = $val;
                                } elseif ($key === 'Rata 1') {
                                    $rata1 = $val;
                                } elseif ($key === 'Rata 2') {
                                    $rata2 = $val;
                                } elseif ($key === 'Rata 3') {
                                    $rata3 = $val;
                                } elseif ($key === 'Rata 4') {
                                    $rata4 = $val;
                                } elseif ($key === 'Nr faktury') {
                                    $invoice = $val;
                                }
                            }
                            
                            // Specjalne wyświetlanie dla Kuby
                            if ($title === 'Kuba') {
                                $displayName = 'Kuba';
                                
                                echo '<td class="agent-column" ' .
                                     'data-name="' . htmlspecialchars($displayName, ENT_QUOTES) . '" ' .
                                     'data-percent="' . strip_tags($percent) . '" ' .
                                     'data-wypłata="' . strip_tags($wypłata) . '" ' .
                                     'data-rata1="' . strip_tags($rata1) . '" ' .
                                     'data-rata2="' . strip_tags($rata2) . '" ' .
                                     'data-rata3="' . strip_tags($rata3) . '" ' .
                                     'data-rata4="' . strip_tags($rata4) . '" ' .
                                     'data-invoice="' . htmlspecialchars($invoice, ENT_QUOTES) . '">' .
                                     $displayName .
                                     '</td>';
                            } else {
                                // Standardowy przypadek dla Agentów 1-3
                                // Pobierz nazwę agenta z wartości name
                                $agentDisplayName = '';
                                if (!empty($name)) {
                                    $agentDisplayName = strip_tags($name);
                                } else {
                                    $agentDisplayName = $title; // Użyj tytułu kolumny jako nazwy
                                }
                                
                                echo '<td class="agent-column" ' .
                                     'data-name="' . htmlspecialchars($agentDisplayName, ENT_QUOTES) . '" ' .
                                     'data-percent="' . strip_tags($percent) . '" ' .
                                     'data-rata1="' . strip_tags($rata1) . '" ' .
                                     'data-rata2="' . strip_tags($rata2) . '" ' .
                                     'data-rata3="' . strip_tags($rata3) . '" ' .
                                     'data-rata4="' . strip_tags($rata4) . '">' .
                                     $name .
                                     '</td>';
                            }
                        } else {
                            // Obsługa innych kolumn z zagnieżdżonymi danymi (jeśli istnieją)
                            echo '<td>' . print_r($value, true) . '</td>';
                        }
                    } elseif (is_numeric($value)) {
                        if (strpos($title, 'Do wypłaty') !== false || 
                            strpos($title, 'Prowizja %') !== false || 
                            strpos($title, 'Success fee %') !== false) { 
                            echo '<td class="currency">' . number_format((float)$value, 2, ',', ' ') . '%</td>';
                        } elseif (strpos($title, 'Wywalczona kwota') !== false ||
                                  strpos($title, 'Opłata wstępna') !== false ||
                                  strpos($title, 'Całość prowizji') !== false ||
                                  strpos($title, 'Rata') !== false) {
                            // Dodaj znak złotówki do wartości pieniężnych
                            // Sprawdź czy to jest rata z przypisaną fakturą
                            $invoiceInfo = '';
                            $isPaid = false;
                            $cssClass = 'currency';
                            
                            if (strpos($title, 'Rata') !== false) {
                                $installmentNum = substr($title, -1); // Get the last character (the number)
                                $paidKey = 'Opłacona ' . $installmentNum;
                                
                                // Check if installment is paid
                                if (isset($row[$paidKey]) && $row[$paidKey] === 'Tak') {
                                    // Mark this cell as a paid installment
                                    $isPaid = true;
                                    $cssClass .= ' rate-paid';
                                    
                                    // Get commission paid status
                                    $commissionPaidField = '';
                                    $commissionPaid = false;
                                    $commissionInvoiceField = '';
                                    $commissionInvoice = '';
                                    
                                    if ($installmentNum == '1') {
                                        $commissionPaidField = 'installment1_commission_paid';
                                        $commissionInvoiceField = 'installment1_commission_invoice';
                                    } else if ($installmentNum == '2') {
                                        $commissionPaidField = 'installment2_commission_paid';
                                        $commissionInvoiceField = 'installment2_commission_invoice';
                                    } else if ($installmentNum == '3') {
                                        $commissionPaidField = 'installment3_commission_paid';
                                        $commissionInvoiceField = 'installment3_commission_invoice';
                                    } else if ($installmentNum == '4') {
                                        $commissionPaidField = 'final_installment_commission_paid';
                                        $commissionInvoiceField = 'final_installment_commission_invoice';
                                    }
                                    
                                    if (isset($row[$commissionPaidField]) && $row[$commissionPaidField] == 1) {
                                        $commissionPaid = true;
                                    }
                                    
                                    // Check if we have commission invoice data
                                    if (isset($row[$commissionInvoiceField]) && !empty($row[$commissionInvoiceField])) {
                                        $commissionInvoice = htmlspecialchars($row[$commissionInvoiceField], ENT_QUOTES);
                                    }
                                    
                                    // Determine invoice field name based on installment number
                                    $invoiceField = '';
                                    if ($installmentNum == '1') {
                                        $invoiceField = 'installment1_paid_invoice';
                                    } else if ($installmentNum == '2') {
                                        $invoiceField = 'installment2_paid_invoice';
                                    } else if ($installmentNum == '3') {
                                        $invoiceField = 'installment3_paid_invoice';
                                    } else if ($installmentNum == '4') {
                                        $invoiceField = 'final_installment_paid_invoice';
                                    }
                                    
                                    // If we have invoice data for this installment, display it
                                    if (isset($row[$invoiceField]) && !empty($row[$invoiceField])) {
                                        $invoiceNumber = htmlspecialchars($row[$invoiceField], ENT_QUOTES);
                                        $invoiceInfo = '<div class="invoice-number">' . $invoiceNumber . '</div>';
                                    }
                                    
                                    // Add commission paid checkbox
                                    $checkedAttr = $commissionPaid ? 'checked' : '';
                                    $installmentIdAttr = 'commission-' . $row['ID'] . '-' . $installmentNum;
                                    
                                    $invoiceInfo .= '
                                    <div class="commission-checkbox-container">
                                        <label class="commission-checkbox-label" title="Oznacz prowizję jako wypłaconą">
                                            <input type="checkbox" ' . $checkedAttr . ' class="commission-checkbox" 
                                            data-case-id="' . $row['ID'] . '" 
                                            data-installment="' . $installmentNum . '" 
                                            id="' . $installmentIdAttr . '">
                                            <span>Prowizja wypłacona</span>
                                        </label>';
                                        
                                    // Add invoice info icon if commission is paid and has invoice
                                    if ($commissionPaid && !empty($commissionInvoice)) {
                                        $invoiceInfo .= '
                                            <div class="commission-invoice-info">
                                                <i class="fa-solid fa-receipt"></i>
                                                <div class="commission-invoice-tooltip">
                                                    Prowizja wypłacona. Faktura: ' . $commissionInvoice . '
                                                </div>
                                            </div>';
                                    }
                                    
                                    $invoiceInfo .= '</div>';
                                }
                            }
                            
                            echo '<td class="currency">' . number_format((float)$value, 2, ',', ' ') . ' zł' . $invoiceInfo . '</td>';
                        } else {
                            echo '<td class="currency">' . number_format((float)$value, 2, ',', ' ') . '</td>';
                        }
                    } elseif ($value === 'Tak' || $value === 'Nie') {
                        // Obsługa wartości boolean
                        $statusClass = ($value === 'Tak') ? 'status-yes' : 'status-no';
                        echo '<td><span class="status ' . $statusClass . '">' . htmlspecialchars($value, ENT_QUOTES) . '</span></td>';
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
     * Generuje przyciski akcji dla danego rekordu
     */
    private function renderActionButtons($caseId): string 
    {
        $buttons = [];
        
        // Przycisk edycji z ikoną długopisu
        $editButton = sprintf(
            '<a href="/case/edit/%d" class="edit-button" title="Edytuj rekord"><i class="fa-solid fa-pen-to-square"></i></a>',
            $caseId
        );
        $buttons[] = $editButton;
        
        // Możesz dodać więcej przycisków w przyszłości
        
        return implode(' ', $buttons);
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

    /**
     * Wyświetl formularz edycji sprawy
     */
    public function edit($id): void
    {
        error_log("TableController::edit - Start");
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        $this->pdo = $pdo;

        // Pobierz ID sprawy z parametru metody
        $id = intval($id);
        
        if (!$id) {
            header('Location: /table?error=no_id');
            exit;
        }
        
        error_log("TableController::edit - Edycja sprawy o ID: {$id}");
        
        // Pobierz dane sprawy
        try {
            $query = "SELECT * FROM test2 WHERE id = :id";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':id' => $id]);
            $case = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$case) {
                error_log("TableController::edit - Nie znaleziono sprawy o ID: {$id}");
                header('Location: /table?error=not_found');
                exit;
            }
            
            // Pobierz agentów przypisanych do tej sprawy
            $agentsQuery = "
                SELECT sa.agent_id, sa.rola, sa.percentage, a.imie, a.nazwisko
                FROM sprawa_agent sa
                JOIN agenci a ON sa.agent_id = a.agent_id
                WHERE sa.sprawa_id = :sprawa_id
                ORDER BY sa.rola";
            
            $agentsStmt = $this->pdo->prepare($agentsQuery);
            $agentsStmt->execute([':sprawa_id' => $id]);
            $caseAgents = $agentsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Przypisz agentów do tablicy według ich ról dla łatwiejszego dostępu w widoku
            $assignedAgents = [];
            foreach ($caseAgents as $agent) {
                $role = $agent['rola'];
                $agentNum = 0;
                
                if (preg_match('/agent_(\d+)/', $role, $matches)) {
                    $agentNum = (int)$matches[1];
                    $assignedAgents[$agentNum] = $agent;
                }
            }
            
            // Pobierz wszystkich dostępnych agentów do wyboru
            $agents = $this->getAgents();
            
            error_log("TableController::edit - Renderowanie formularza edycji");
            include __DIR__ . '/../Views/edit_case.php';
            
        } catch (PDOException $e) {
            error_log("TableController::edit - BŁĄD: " . $e->getMessage());
            header('Location: /table?error=db_error');
            exit;
        }
    }
    
    /**
     * Aktualizuje dane sprawy po edycji
     */
    public function update($id): void
    {
        error_log("TableController::update - Start");
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        $this->pdo = $pdo;
        
        // Pobierz ID sprawy z parametru metody
        $id = intval($id);
        
        if (!$id) {
            header('Location: /table?error=no_id');
            exit;
        }
        
        $data = $_POST;
        error_log("TableController::update - Aktualizacja sprawy ID: {$id}");
        error_log("TableController::update - Otrzymane dane: " . print_r($data, true));
        
        try {
            // Walidacja danych przed aktualizacją
            $errors = [];
            
            // 1. Walidacja prowizji Kuby
            if (isset($data['kuba_percentage']) && $data['kuba_percentage'] !== '') {
                $kubaPercentage = floatval($data['kuba_percentage']);
                error_log("TableController::update - Prowizja Kuby: {$kubaPercentage}");
                if ($kubaPercentage < 0 || $kubaPercentage > 100) {
                    error_log("TableController::update - BŁĄD: Prowizja Kuby poza zakresem 0-100");
                    $errors[] = "Prowizja Kuby musi być wartością z przedziału 0-100.";
                }
            } else {
                error_log("TableController::update - BŁĄD: Brak prowizji Kuby");
                $errors[] = "Prowizja Kuby jest wymagana.";
                $kubaPercentage = 0;
            }
            
            // 2. Walidacja prowizji agentów
            $agentPercents = [];
            for ($i = 1; $i <= 3; $i++) {
                if (isset($data["agent{$i}_percentage"]) && $data["agent{$i}_percentage"] !== '') {
                    $agentValue = floatval($data["agent{$i}_percentage"]);
                    error_log("TableController::update - Prowizja Agenta {$i}: {$agentValue}");
                    if ($agentValue < 0 || $agentValue > 100) {
                        error_log("TableController::update - BŁĄD: Prowizja Agenta {$i} poza zakresem 0-100");
                        $errors[] = "Prowizja agenta {$i} musi być wartością z przedziału 0-100.";
                    }
                    if ($agentValue > $kubaPercentage) {
                        error_log("TableController::update - BŁĄD: Prowizja Agenta {$i} > prowizja Kuby");
                        $errors[] = "Prowizja agenta {$i} nie może być większa niż prowizja Kuby ({$kubaPercentage}%).";
                    }
                    $agentPercents[] = $agentValue;
                } else {
                    error_log("TableController::update - Agent {$i} nie ma określonej prowizji");
                }
            }
            if (array_sum($agentPercents) > $kubaPercentage) {
                error_log("TableController::update - BŁĄD: Suma prowizji agentów > prowizja Kuby");
                $errors[] = "Suma prowizji agentów (" . array_sum($agentPercents) . "%) nie może być większa niż prowizja Kuby ({$kubaPercentage}%).";
            }
            
            // 3. Walidacja innych pól numerycznych
            $fieldsToValidate = ['amount_won', 'upfront_fee', 'success_fee_percentage'];
            foreach ($fieldsToValidate as $field) {
                if (isset($data[$field]) && $data[$field] !== '') {
                    error_log("TableController::update - Walidacja pola {$field}: " . $data[$field]);
                    if (!is_numeric($data[$field]) || floatval($data[$field]) < 0) {
                        error_log("TableController::update - BŁĄD: Pole {$field} ma nieprawidłową wartość");
                        $errors[] = "Pole '{$field}' musi być liczbą nieujemną.";
                    }
                } else {
                    error_log("TableController::update - Pole {$field} nie jest ustawione");
                }
            }
            
            // 4. Walidacja pól rat (jeśli są podane)
            $totalInstallments = 0;
            for ($i = 1; $i <= 3; $i++) {
                $installmentField = "installment{$i}_amount";
                if (isset($data[$installmentField]) && $data[$installmentField] !== '') {
                    error_log("TableController::update - Walidacja raty {$i}: " . $data[$installmentField]);
                    if (!is_numeric($data[$installmentField]) || floatval($data[$installmentField]) < 0) {
                        error_log("TableController::update - BŁĄD: Rata {$i} ma nieprawidłową wartość");
                        $errors[] = "Rata {$i} musi być liczbą nieujemną.";
                    } else {
                        $totalInstallments += floatval($data[$installmentField]);
                    }
                } else {
                    error_log("TableController::update - Rata {$i} nie jest ustawiona");
                }
            }
            
            // 5. Sprawdzenie czy suma rat jest równa opłacie wstępnej
            if (isset($data['upfront_fee']) && $data['upfront_fee'] !== '') {
                $upfrontFee = floatval($data['upfront_fee']);
                if ($totalInstallments != $upfrontFee) {
                    error_log("TableController::update - BŁĄD: Suma rat ($totalInstallments) nie jest równa opłacie wstępnej ($upfrontFee)");
                    $errors[] = "Suma rat ({$totalInstallments} zł) musi być równa opłacie wstępnej ({$upfrontFee} zł).";
                }
            }
            
            // Jeśli wykryto błąd walidacji, wypisz je i zatrzymaj działanie
            if (count($errors) > 0) {
                error_log("TableController::update - Znaleziono " . count($errors) . " błędów walidacji");
                echo "<div style='color:red;'><h3>Błędy walidacji:</h3><ul>";
                foreach ($errors as $error) {
                    echo "<li>" . htmlspecialchars($error, ENT_QUOTES) . "</li>";
                }
                echo "</ul></div>";
                exit;
            }
            
            // Przygotuj dane do aktualizacji
            $updates = [];
            $params = [':id' => $id];
            
            // Pola, które można aktualizować w tabeli test2
            $editableFields = [
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
                'final_installment_paid',
                'kuba_invoice_number',
                'installment1_commission_paid',
                'installment2_commission_paid',
                'installment3_commission_paid',
                'final_installment_commission_paid'
            ];
            
            // Przetwórz checkbox na wartości 0/1
            $checkboxFields = [
                'is_completed',
                'installment1_paid',
                'installment2_paid',
                'installment3_paid',
                'final_installment_paid',
                'installment1_commission_paid',
                'installment2_commission_paid',
                'installment3_commission_paid',
                'final_installment_commission_paid'
            ];
            
            foreach ($editableFields as $field) {
                if (array_key_exists($field, $data)) {
                    if (in_array($field, $checkboxFields)) {
                        // Checkbox - sprawdź czy zaznaczony
                        $value = isset($data[$field]) ? 1 : 0;
                        $updates[] = "{$field} = :{$field}";
                        $params[":{$field}"] = $value;
                    } else {
                        // Pozostałe pola
                        $updates[] = "{$field} = :{$field}";
                        $params[":{$field}"] = $data[$field] !== '' ? $data[$field] : null;
                    }
                }
            }
            
            // Aktualizuj dane sprawy
            if (!empty($updates)) {
                $updateQuery = "UPDATE test2 SET " . implode(", ", $updates) . " WHERE id = :id";
                $updateStmt = $this->pdo->prepare($updateQuery);
                $updateStmt->execute($params);
                error_log("TableController::update - Zaktualizowano dane sprawy ID: {$id}");
            }
            
            // Aktualizuj powiązania agentów
            // Najpierw usuń istniejące powiązania
            $deleteAgentsQuery = "DELETE FROM sprawa_agent WHERE sprawa_id = :sprawa_id";
            $deleteAgentsStmt = $this->pdo->prepare($deleteAgentsQuery);
            $deleteAgentsStmt->execute([':sprawa_id' => $id]);
            error_log("TableController::update - Usunięto istniejące powiązania agentów");
            
            // Dodaj nowe powiązania dla agentów 1-3
            $insertAgentQuery = "
                INSERT INTO sprawa_agent (sprawa_id, agent_id, rola, percentage) 
                VALUES (:sprawa_id, :agent_id, :rola, :percentage)";
            $insertAgentStmt = $this->pdo->prepare($insertAgentQuery);
            
            for ($i = 1; $i <= 3; $i++) {
                $agentIdKey = "agent{$i}_id";
                $percentageKey = "agent{$i}_percentage";
                
                if (isset($data[$agentIdKey]) && $data[$agentIdKey] && isset($data[$percentageKey])) {
                    $agentId = $data[$agentIdKey];
                    $percentage = $data[$percentageKey];
                    
                    $insertAgentStmt->execute([
                        ':sprawa_id' => $id,
                        ':agent_id' => $agentId,
                        ':rola' => "agent_{$i}",
                        ':percentage' => $percentage
                    ]);
                    
                    error_log("TableController::update - Dodano powiązanie dla agenta {$agentId} z rolą agent_{$i}");
                }
            }
            
            // Przelicz prowizje po aktualizacji
            $this->recalculateCase($id);
            
            // Synchronize payment statuses after update
            $this->syncPaymentStatusesInternal();
            
            // Przekieruj z powrotem do tabeli z komunikatem sukcesu
            header('Location: /table?success=1');
            exit;
            
        } catch (PDOException $e) {
            error_log("TableController::update - BŁĄD: " . $e->getMessage());
            error_log("TableController::update - Stack trace: " . $e->getTraceAsString());
            header('Location: /table?error=update_failed');
            exit;
        }
    }

    /**
     * Synchronize payment statuses between test and test2 tables
     * This method checks if invoices in the test table match cases in test2
     * and marks installments as paid in chronological order
     */
    public function syncPaymentStatuses(): void
    {
        error_log("TableController::syncPaymentStatuses - Start");
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        $this->pdo = $pdo;
        
        try {
            // Call the internal method that does the actual work
            $this->syncPaymentStatusesInternal();
            
            // Display success message and redirect
            error_log("TableController::syncPaymentStatuses - Payment statuses synchronized successfully");
            echo '<div class="notification info show">
                    <i class="fa-solid fa-check-circle"></i>
                    Statusy płatności zostały zsynchronizowane pomyślnie!
                  </div>';
            
            // Redirect back to table
            header("Refresh: 2; URL=/table");
        } catch (PDOException $e) {
            error_log("TableController::syncPaymentStatuses - ERROR: " . $e->getMessage());
            error_log("TableController::syncPaymentStatuses - Stack trace: " . $e->getTraceAsString());
            echo '<div class="notification error show">
                    <i class="fa-solid fa-exclamation-circle"></i>
                    Błąd podczas synchronizacji statusów płatności: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '
                  </div>';
        }
    }
    
    /**
     * AJAX endpoint for payment status synchronization
     * Returns JSON response instead of redirecting
     */
    public function syncPaymentsAjax(): void
    {
        error_log("TableController::syncPaymentsAjax - Start");
        header('Content-Type: application/json');
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        $this->pdo = $pdo;
        
        try {
            // Call the internal method that does the actual work
            $this->syncPaymentStatusesInternal();
            
            // Return success message
            error_log("TableController::syncPaymentsAjax - Payment statuses synchronized successfully");
            $response = [
                'success' => true,
                'message' => 'Statusy płatności zostały zsynchronizowane pomyślnie!'
            ];
            
            // Include last sync time if available
            if (isset($_SESSION['last_payment_sync'])) {
                $response['last_sync_time'] = date('d.m.Y H:i:s', $_SESSION['last_payment_sync']);
            }
            
            echo json_encode($response);
        } catch (PDOException $e) {
            error_log("TableController::syncPaymentsAjax - ERROR: " . $e->getMessage());
            error_log("TableController::syncPaymentsAjax - Stack trace: " . $e->getTraceAsString());
            
            echo json_encode([
                'success' => false,
                'message' => 'Błąd podczas synchronizacji statusów płatności: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * AJAX endpoint for updating commission payment status
     * Accepts POST request with case_id, installment_number, and status parameters
     * Returns JSON response
     */
    public function updateCommissionStatusAjax(): void
    {
        error_log("TableController::updateCommissionStatusAjax - Start");
        header('Content-Type: application/json');
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        $this->pdo = $pdo;
        
        try {
            // Get request body
            $input_raw = file_get_contents('php://input');
            error_log("TableController::updateCommissionStatusAjax - Raw input: " . $input_raw);
            
            // Validate input
            $input = json_decode($input_raw, true);
            error_log("TableController::updateCommissionStatusAjax - Decoded input: " . print_r($input, true));
            
            if (!isset($input['case_id']) || !isset($input['installment_number']) || !isset($input['status'])) {
                throw new \Exception("Missing required parameters: case_id, installment_number, status");
            }
            
            $caseId = (int)$input['case_id'];
            $installmentNumber = $input['installment_number'];
            $status = (int)$input['status'];
            $invoiceNumber = isset($input['invoice_number']) ? $input['invoice_number'] : '';
            $agentId = isset($input['agent_id']) ? $input['agent_id'] : null;
            
            error_log("TableController::updateCommissionStatusAjax - Parameters: case_id=$caseId, installment_number=$installmentNumber, status=$status, invoice_number=$invoiceNumber, agent_id=" . ($agentId ? $agentId : 'null'));
            
            // Validate installment number
            if (!in_array($installmentNumber, [1, 2, 3, 4])) {
                throw new \Exception("Invalid installment number. Must be 1, 2, 3, or 4.");
            }
            
            // Map installment number to database columns
            $columnMap = [
                1 => 'installment1_commission_paid',
                2 => 'installment2_commission_paid',
                3 => 'installment3_commission_paid',
                4 => 'final_installment_commission_paid'
            ];
            
            // Map installment number to invoice column
            $invoiceColumnMap = [
                1 => 'installment1_commission_invoice',
                2 => 'installment2_commission_invoice',
                3 => 'installment3_commission_invoice',
                4 => 'final_installment_commission_invoice'
            ];
            
            // Map installment number to agent column
            $agentColumnMap = [
                1 => 'installment1_commission_agent_id',
                2 => 'installment2_commission_agent_id',
                3 => 'installment3_commission_agent_id',
                4 => 'final_installment_commission_agent_id'
            ];
            
            $column = $columnMap[$installmentNumber];
            $invoiceColumn = $invoiceColumnMap[$installmentNumber];
            $agentColumn = $agentColumnMap[$installmentNumber];
            
            error_log("TableController::updateCommissionStatusAjax - DB column to update: " . $column);
            error_log("TableController::updateCommissionStatusAjax - Invoice column to update: " . $invoiceColumn);
            error_log("TableController::updateCommissionStatusAjax - Agent column to update: " . $agentColumn);
            
            // Check if columns exist, if not create them
            $this->ensureCommissionColumnsExist();
            
            // Start a transaction to ensure data consistency
            $this->pdo->beginTransaction();
            
            try {
                // 1. First update the test2 table as before
                $query = "UPDATE test2 SET {$column} = :status";
                
                // Add invoice number to query if provided
                if (!empty($invoiceNumber)) {
                    $query .= ", {$invoiceColumn} = :invoice_number";
                }
                
                // Add agent ID to query if provided
                if ($agentId !== null) {
                    $query .= ", {$agentColumn} = :agent_id";
                }
                
                $query .= " WHERE id = :case_id";
                
                error_log("TableController::updateCommissionStatusAjax - SQL query for test2: " . $query);
                
                $stmt = $this->pdo->prepare($query);
                $params = [
                    ':status' => $status,
                    ':case_id' => $caseId
                ];
                
                // Add invoice parameter if provided
                if (!empty($invoiceNumber)) {
                    $params[':invoice_number'] = $invoiceNumber;
                }
                
                // Add agent ID parameter if provided
                if ($agentId !== null) {
                    $params[':agent_id'] = $agentId;
                }
                
                $test2UpdateSuccess = $stmt->execute($params);
                
                if (!$test2UpdateSuccess) {
                    error_log("TableController::updateCommissionStatusAjax - SQL error updating test2: " . print_r($stmt->errorInfo(), true));
                    throw new \Exception("Database error updating test2: " . implode(", ", $stmt->errorInfo()));
                }
                
                $test2RowCount = $stmt->rowCount();
                error_log("TableController::updateCommissionStatusAjax - test2 rows affected: " . $test2RowCount);
                
                // 2. Handle the commission_payments table
                // If status=0, delete any existing records
                if ($status == 0) {
                    $deleteQuery = "DELETE FROM commission_payments 
                                  WHERE case_id = :case_id 
                                  AND installment_number = :installment_number";
                    
                    $deleteStmt = $this->pdo->prepare($deleteQuery);
                    $deleteParams = [
                        ':case_id' => $caseId,
                        ':installment_number' => $installmentNumber
                    ];
                    
                    // If agent ID is provided, only delete for that agent
                    if ($agentId !== null) {
                        $deleteQuery .= " AND agent_id = :agent_id";
                        $deleteParams[':agent_id'] = $agentId;
                    }
                    
                    $deleteStmt = $this->pdo->prepare($deleteQuery);
                    $deleteSuccess = $deleteStmt->execute($deleteParams);
                    
                    error_log("TableController::updateCommissionStatusAjax - Deleted from commission_payments: " . 
                             ($deleteSuccess ? "success" : "failed") . ", rows: " . $deleteStmt->rowCount());
                }
                // If status=1, insert or update record in commission_payments
                else if ($status == 1 && $agentId !== null) {
                    // First check if a record already exists
                    $checkQuery = "SELECT id FROM commission_payments 
                                 WHERE case_id = :case_id 
                                 AND installment_number = :installment_number
                                 AND agent_id = :agent_id";
                    
                    $checkStmt = $this->pdo->prepare($checkQuery);
                    $checkParams = [
                        ':case_id' => $caseId,
                        ':installment_number' => $installmentNumber,
                        ':agent_id' => $agentId
                    ];
                    
                    $checkStmt->execute($checkParams);
                    $existingRecord = $checkStmt->fetch(\PDO::FETCH_ASSOC);
                    
                    if ($existingRecord) {
                        // Update existing record
                        $updateQuery = "UPDATE commission_payments 
                                      SET invoice_number = :invoice_number, 
                                          status = :status, 
                                          updated_at = NOW() 
                                      WHERE id = :id";
                        
                        $updateStmt = $this->pdo->prepare($updateQuery);
                        $updateParams = [
                            ':invoice_number' => $invoiceNumber,
                            ':status' => $status,
                            ':id' => $existingRecord['id']
                        ];
                        
                        $updateSuccess = $updateStmt->execute($updateParams);
                        
                        error_log("TableController::updateCommissionStatusAjax - Updated commission_payments: " . 
                                 ($updateSuccess ? "success" : "failed") . ", rows: " . $updateStmt->rowCount());
                    } else {
                        // Insert new record
                        $insertQuery = "INSERT INTO commission_payments 
                                      (case_id, installment_number, agent_id, invoice_number, status, created_at) 
                                      VALUES (:case_id, :installment_number, :agent_id, :invoice_number, :status, NOW())";
                        
                        $insertStmt = $this->pdo->prepare($insertQuery);
                        $insertParams = [
                            ':case_id' => $caseId,
                            ':installment_number' => $installmentNumber,
                            ':agent_id' => $agentId,
                            ':invoice_number' => $invoiceNumber,
                            ':status' => $status
                        ];
                        
                        $insertSuccess = $insertStmt->execute($insertParams);
                        $lastInsertId = $this->pdo->lastInsertId();
                        
                        error_log("TableController::updateCommissionStatusAjax - Inserted into commission_payments: " . 
                                 ($insertSuccess ? "success, ID: " . $lastInsertId : "failed"));
                    }
                } else {
                    error_log("TableController::updateCommissionStatusAjax - Skipping commission_payments update: status=$status, agent_id=" . ($agentId ?? 'null'));
                }
                
                // Commit the transaction
                $this->pdo->commit();
                
                // Return success response
                $response = [
                    'success' => true,
                    'message' => "Commission payment status for installment {$installmentNumber} updated successfully",
                    'case_id' => $caseId,
                    'installment_number' => $installmentNumber,
                    'status' => $status,
                    'invoice_number' => $invoiceNumber,
                    'agent_id' => $agentId,
                    'rows_affected' => $test2RowCount
                ];
                error_log("TableController::updateCommissionStatusAjax - Success response: " . json_encode($response));
                echo json_encode($response);
                
            } catch (\Exception $e) {
                // Rollback transaction on error
                $this->pdo->rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            error_log("TableController::updateCommissionStatusAjax - ERROR: " . $e->getMessage());
            error_log("TableController::updateCommissionStatusAjax - Stack trace: " . $e->getTraceAsString());
            
            // Return error response
            http_response_code(400);
            $response = [
                'success' => false,
                'message' => $e->getMessage()
            ];
            error_log("TableController::updateCommissionStatusAjax - Error response: " . json_encode($response));
            echo json_encode($response);
        }
    }
    
    /**
     * Ensure that all commission-related columns exist in the database
     */
    private function ensureCommissionColumnsExist(): void
    {
        try {
            // Check if the invoice columns exist
            $columnsQuery = "SHOW COLUMNS FROM test2 LIKE 'installment1_commission_invoice'";
            $stmt = $this->pdo->query($columnsQuery);
            $invoiceColumnsExist = ($stmt->rowCount() > 0);
            
            // Check if the agent ID columns exist
            $columnsQuery = "SHOW COLUMNS FROM test2 LIKE 'installment1_commission_agent_id'";
            $stmt = $this->pdo->query($columnsQuery);
            $agentColumnsExist = ($stmt->rowCount() > 0);
            
            // If the invoice columns don't exist, create them
            if (!$invoiceColumnsExist) {
                error_log("TableController::ensureCommissionColumnsExist - Creating commission invoice columns");
                
                $alterQuery = "ALTER TABLE test2 
                    ADD COLUMN installment1_commission_invoice VARCHAR(255) NULL,
                    ADD COLUMN installment2_commission_invoice VARCHAR(255) NULL,
                    ADD COLUMN installment3_commission_invoice VARCHAR(255) NULL,
                    ADD COLUMN final_installment_commission_invoice VARCHAR(255) NULL";
                
                $result = $this->pdo->exec($alterQuery);
                
                if ($result !== false) {
                    error_log("TableController::ensureCommissionColumnsExist - Commission invoice columns created successfully");
                } else {
                    error_log("TableController::ensureCommissionColumnsExist - Error creating invoice columns: " . print_r($this->pdo->errorInfo(), true));
                }
            } else {
                error_log("TableController::ensureCommissionColumnsExist - Commission invoice columns already exist");
            }
            
            // If the agent ID columns don't exist, create them
            if (!$agentColumnsExist) {
                error_log("TableController::ensureCommissionColumnsExist - Creating commission agent ID columns");
                
                $alterQuery = "ALTER TABLE test2 
                    ADD COLUMN installment1_commission_agent_id INT NULL,
                    ADD COLUMN installment2_commission_agent_id INT NULL,
                    ADD COLUMN installment3_commission_agent_id INT NULL,
                    ADD COLUMN final_installment_commission_agent_id INT NULL";
                
                $result = $this->pdo->exec($alterQuery);
                
                if ($result !== false) {
                    error_log("TableController::ensureCommissionColumnsExist - Commission agent ID columns created successfully");
                } else {
                    error_log("TableController::ensureCommissionColumnsExist - Error creating agent ID columns: " . print_r($this->pdo->errorInfo(), true));
                }
            } else {
                error_log("TableController::ensureCommissionColumnsExist - Commission agent ID columns already exist");
            }
            
            // Verify all required columns exist
            $requiredColumns = [
                'installment1_commission_invoice',
                'installment2_commission_invoice',
                'installment3_commission_invoice',
                'final_installment_commission_invoice',
                'installment1_commission_agent_id',
                'installment2_commission_agent_id',
                'installment3_commission_agent_id',
                'final_installment_commission_agent_id'
            ];
            
            $stmt = $this->pdo->query("DESCRIBE test2");
            $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $allColumnsExist = true;
            foreach ($requiredColumns as $column) {
                if (!in_array($column, $existingColumns)) {
                    error_log("TableController::ensureCommissionColumnsExist - Missing column: {$column}");
                    $allColumnsExist = false;
                }
            }
            
            if ($allColumnsExist) {
                error_log("TableController::ensureCommissionColumnsExist - All commission columns verified");
            }
        } catch (\PDOException $e) {
            error_log("TableController::ensureCommissionColumnsExist - ERROR: " . $e->getMessage());
            // Don't throw an exception, just log the error
        }
    }

    /**
     * AJAX endpoint to get agents assigned to a specific case
     * Returns JSON response with agents data
     */
    public function getCaseAgentsAjax(): void
    {
        error_log("TableController::getCaseAgentsAjax - Start");
        header('Content-Type: application/json');
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        $this->pdo = $pdo;
        
        try {
            // Get case ID from query parameters
            $caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : null;
            
            if (!$caseId) {
                throw new \Exception("Missing required parameter: case_id");
            }
            
            error_log("TableController::getCaseAgentsAjax - Fetching agents for case ID: $caseId");
            
            // Get agents assigned to this case
            $query = "
                SELECT a.agent_id, a.imie, a.nazwisko, sa.rola, sa.percentage
                FROM sprawa_agent sa
                JOIN agenci a ON sa.agent_id = a.agent_id
                WHERE sa.sprawa_id = :sprawa_id
                ORDER BY sa.rola
            ";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':sprawa_id' => $caseId]);
            $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("TableController::getCaseAgentsAjax - Found " . count($agents) . " agents");
            
            // Always add Kuba/Jakub as an option
            $kubaExists = false;
            foreach ($agents as $agent) {
                if (strtolower($agent['imie']) === 'kuba' || strtolower($agent['imie']) === 'jakub') {
                    $kubaExists = true;
                    break;
                }
            }
            
            if (!$kubaExists) {
                // Add a virtual Kuba entry
                $kuba = [
                    'agent_id' => 'jakub',
                    'imie' => 'Jakub',
                    'nazwisko' => 'Kowalski',
                    'rola' => 'kuba',
                    'percentage' => null
                ];
                array_unshift($agents, $kuba);
            }
            
            // Return success response
            $response = [
                'success' => true,
                'message' => "Successfully retrieved agents for case ID: $caseId",
                'agents' => $agents,
                'case_id' => $caseId
            ];
            
            echo json_encode($response);
            
        } catch (\Exception $e) {
            error_log("TableController::getCaseAgentsAjax - ERROR: " . $e->getMessage());
            
            // Return error response
            $response = [
                'success' => false,
                'message' => $e->getMessage()
            ];
            
            echo json_encode($response);
        }
    }
}
