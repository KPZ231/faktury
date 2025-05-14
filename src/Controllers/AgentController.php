<?php

namespace Dell\Faktury\Controllers;

use PDO;
use PDOException;

class AgentController
{
    private PDO $db;

    public function __construct()
    {
        error_log("AgentController::__construct - Inicjalizacja kontrolera agentów");
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        $this->db = $pdo;
        error_log("AgentController::__construct - Połączenie z bazą danych zainicjalizowane");
    }

    /**
     * Wyświetla listę agentów i formularz dodawania
     */
    public function index(): void
    {
        error_log("AgentController::index - Start");
        
        $success = isset($_GET['success']) ? filter_var($_GET['success'], FILTER_VALIDATE_BOOLEAN) : false;
        $error = isset($_GET['error']) ? $_GET['error'] : null;
        
        error_log("AgentController::index - Status: success=" . ($success ? 'true' : 'false') . ", error=" . ($error ?: 'brak'));
        
        try {
            error_log("AgentController::index - Pobieranie wszystkich agentów");
            $stmt = $this->db->query("SELECT * FROM agenci ORDER BY nazwa_agenta");
            $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("AgentController::index - Pobrano " . count($agents) . " agentów");
            
            // Przygotuj dane dla widoku
            foreach ($agents as &$agent) {
                // Pobierz sprawy agenta
                $agent['sprawy'] = $this->getAgentCases($agent['id_agenta']);
            }
            
            // Udostępnij połączenie z bazą danych dla widoku
            $pdo = $this->db;
            
            // Przekaż dane do widoku
            include __DIR__ . '/../Views/agents.php';
            error_log("AgentController::index - Zakończono");
        } catch (PDOException $e) {
            error_log("AgentController::index - BŁĄD: " . $e->getMessage());
            error_log("AgentController::index - Stack trace: " . $e->getTraceAsString());
            $agents = [];
            $error = "Błąd bazy danych: " . $e->getMessage();
            
            // Udostępnij połączenie z bazą danych dla widoku
            $pdo = $this->db;
            
            include __DIR__ . '/../Views/agents.php';
        }
    }

    /**
     * Pobiera sprawy przypisane do agenta
     */
    private function getAgentCases($agentId): array
    {
        try {
            // Pobierz sprawy przypisane do agenta
            $query = "
                SELECT 
                    s.id_sprawy,
                    s.identyfikator_sprawy,
                    pas.udzial_prowizji_proc as percentage
                FROM prowizje_agentow_spraw pas
                JOIN sprawy s ON pas.id_sprawy = s.id_sprawy
                WHERE pas.id_agenta = ?
                ORDER BY s.id_sprawy DESC
            ";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$agentId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching agent cases: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obsługa dodawania nowego agenta
     */
    public function addAgent(): void
    {
        error_log("AgentController::addAgent - Start");
        // Pobierz dane z formularza
        $nazwaAgenta = $_POST['nazwa_agenta'] ?? '';
        
        error_log("AgentController::addAgent - Dane agenta: nazwa=" . $nazwaAgenta);
        
        if (empty($nazwaAgenta)) {
            error_log("AgentController::addAgent - Brak wymaganych danych");
            header('Location: /agents?error=missing_data');
            exit;
        }
        
        try {
            error_log("AgentController::addAgent - Przygotowanie zapytania INSERT");
            $stmt = $this->db->prepare(
                "INSERT INTO agenci (nazwa_agenta) 
                 VALUES (?)"
            );
            
            error_log("AgentController::addAgent - Wykonanie zapytania INSERT");
            $result = $stmt->execute([$nazwaAgenta]);
            
            if ($result) {
                error_log("AgentController::addAgent - Pomyślnie dodano agenta. Ostatnie ID: " . $this->db->lastInsertId());
                header('Location: /agents?success=1');
            } else {
                error_log("AgentController::addAgent - Błąd dodawania agenta");
                header('Location: /agents?error=db_error');
            }
        } catch (PDOException $e) {
            error_log("AgentController::addAgent - BŁĄD: " . $e->getMessage());
            error_log("AgentController::addAgent - Stack trace: " . $e->getTraceAsString());
            header('Location: /agents?error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    /**
     * Wyświetla szczegóły pojedynczej sprawy agenta
     * @param int $agentId
     * @param int $caseId
     */
    public function showCase(int $agentId, int $caseId): void
    {
        // Pobierz dane agenta
        $stmt = $this->db->prepare("SELECT nazwa_agenta FROM agenci WHERE id_agenta = :id");
        $stmt->execute([':id' => $agentId]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$agent) {
            echo "Nie znaleziono agenta.";
            return;
        }

        // Pobierz szczegóły sprawy
        $stmt = $this->db->prepare("
            SELECT pas.udzial_prowizji_proc, s.* 
            FROM prowizje_agentow_spraw pas
            JOIN sprawy s ON pas.id_sprawy = s.id_sprawy
            WHERE pas.id_agenta = :agent_id AND pas.id_sprawy = :case_id
        ");
        $stmt->execute([':agent_id' => $agentId, ':case_id' => $caseId]);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$case) {
            echo "Brak takiej sprawy dla tego agenta.";
            return;
        }

        // Pobierz wszystkich agentów przypisanych do tej sprawy
        $stmt = $this->db->prepare("
            SELECT a.nazwa_agenta, pas.udzial_prowizji_proc
            FROM prowizje_agentow_spraw pas
            JOIN agenci a ON pas.id_agenta = a.id_agenta
            WHERE pas.id_sprawy = :case_id
            ORDER BY pas.udzial_prowizji_proc DESC
        ");
        $stmt->execute([':case_id' => $caseId]);
        $caseAgents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Tutaj możesz wyświetlić dane sprawy i powiązanych agentów
        echo "Agent: {$agent['nazwa_agenta']}, Udział: {$case['udzial_prowizji_proc']}, Sprawa: {$case['identyfikator_sprawy']}";
    }
    
    /**
     * Pobiera informacje o prowizji agenta i zwraca jako JSON
     */
    public function getAgentCommission(): void
    {
        error_log("AgentController::getAgentCommission - Start");
        
        // Sprawdź, czy przekazano ID agenta
        if (!isset($_GET['agent_id']) || empty($_GET['agent_id'])) {
            error_log("AgentController::getAgentCommission - Brak wymaganego parametru agent_id");
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Missing agent_id parameter',
                'commission_percentage' => null
            ]);
            return;
        }
        
        $agentId = intval($_GET['agent_id']);
        error_log("AgentController::getAgentCommission - Pobieranie danych dla agenta ID: " . $agentId);
        
        try {
            // Pobierz podstawowe dane agenta
            $query = "SELECT id_agenta, nazwa_agenta FROM agenci WHERE id_agenta = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$agentId]);
            $agent = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$agent) {
                throw new \Exception("Agent o ID {$agentId} nie istnieje");
            }
            
            // Jeśli przekazano również ID sprawy, pobierz konkretną prowizję
            if (isset($_GET['case_id']) && !empty($_GET['case_id'])) {
                $caseId = intval($_GET['case_id']);
                $query = "
                    SELECT udzial_prowizji_proc
                    FROM prowizje_agentow_spraw
                    WHERE id_agenta = ? AND id_sprawy = ?
                ";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$agentId, $caseId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    $commissionPercentage = floatval($result['udzial_prowizji_proc']) * 100; // Convert from decimal to percentage
                } else {
                    $commissionPercentage = 0; // Brak powiązanej prowizji
                }
            } else {
                // Jeśli nie ma ID sprawy, zwróć domyślną wartość
                $commissionPercentage = 0;
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                'agent' => $agent,
                'commission_percentage' => $commissionPercentage
            ]);
            
        } catch (\Exception $e) {
            error_log("AgentController::getAgentCommission - ERROR: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'error' => $e->getMessage(),
                'commission_percentage' => null
            ]);
        }
    }
}