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
            $stmt = $this->db->query("SELECT * FROM agenci ORDER BY nazwisko, imie");
            $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("AgentController::index - Pobrano " . count($agents) . " agentów");
            
            // Przygotuj dane dla widoku
            foreach ($agents as &$agent) {
                if (isset($agent['sprawy']) && !empty($agent['sprawy'])) {
                    // Próba dekodowania JSON jeśli sprawy są zapisane jako string
                    if (is_string($agent['sprawy'])) {
                        $sprawy = json_decode($agent['sprawy'], true);
                        if (!$sprawy) {
                            $sprawy = [];
                            error_log("AgentController::index - Błąd dekodowania JSON dla agenta ID: " . $agent['agent_id']);
                        }
                        $agent['sprawy'] = $sprawy;
                    }
                    error_log("AgentController::index - Agent ID: " . $agent['agent_id'] . ", liczba spraw: " . count($agent['sprawy']));
                } else {
                    $agent['sprawy'] = [];
                    error_log("AgentController::index - Agent ID: " . $agent['agent_id'] . " nie ma przypisanych spraw");
                }
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
     * Pobiera listę agentów wraz z ich sprawami
     */
    private function getAgentsWithCases(): array
    {
        try {
            // Pobierz podstawowe dane agentów
            $query = "SELECT agent_id, imie, nazwisko FROM agenci ORDER BY agent_id ASC";
            $stmt = $this->db->query($query);
            $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Dla każdego agenta pobierz jego sprawy z tabeli sprawa_agent
            foreach ($agents as &$agent) {
                $agent['sprawy'] = $this->getAgentCasesDetails($agent['agent_id']);
            }

            return $agents;
        } catch (PDOException $e) {
            error_log('Error fetching agents with cases: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Pobiera szczegółowe dane o sprawach agenta
     */
    private function getAgentCasesDetails(int $agentId): array
    {
        try {
            // Pobierz sprawy przypisane do agenta wraz z rolą i szczegółami sprawy
            $query = "
                SELECT 
                    t.id AS sprawa_id,
                    t.case_name,
                    sa.rola,
                    sa.percentage
                FROM sprawa_agent sa
                JOIN test2 t ON sa.sprawa_id = t.id
                WHERE sa.agent_id = ?
                ORDER BY t.id DESC
            ";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$agentId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching agent case details: ' . $e->getMessage());
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
        $imie = $_POST['imie'] ?? '';
        $nazwisko = $_POST['nazwisko'] ?? '';
        
        error_log("AgentController::addAgent - Dane agenta: imię=" . $imie . ", nazwisko=" . $nazwisko);
        
        if (empty($imie) || empty($nazwisko)) {
            error_log("AgentController::addAgent - Brak wymaganych danych");
            header('Location: /agents?error=missing_data');
            exit;
        }
        
        try {
            error_log("AgentController::addAgent - Przygotowanie zapytania INSERT");
            $stmt = $this->db->prepare(
                "INSERT INTO agenci (imie, nazwisko, sprawy) 
                 VALUES (?, ?, ?)"
            );
            
            // Inicjuj pustą tablicę spraw (jako JSON)
            $sprawy = json_encode([]);
            
            error_log("AgentController::addAgent - Wykonanie zapytania INSERT");
            $result = $stmt->execute([$imie, $nazwisko, $sprawy]);
            
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
        $this->db = $this->db;

        // Pobierz dane agenta
        $stmt = $this->db->prepare("SELECT imie, nazwisko FROM agenci WHERE agent_id = :id");
        $stmt->execute([':id' => $agentId]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$agent) {
            echo "Nie znaleziono agenta.";
            return;
        }

        // Pobierz szczegóły sprawy z relacji sprawa_agent
        $stmt = $this->db->prepare("
            SELECT sa.rola, t.* 
            FROM sprawa_agent sa
            JOIN test2 t ON sa.sprawa_id = t.id
            WHERE sa.agent_id = :agent_id AND sa.sprawa_id = :case_id
        ");
        $stmt->execute([':agent_id' => $agentId, ':case_id' => $caseId]);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$case) {
            echo "Brak takiej sprawy dla tego agenta.";
            return;
        }

        // Pobierz wszystkich agentów przypisanych do tej sprawy
        $stmt = $this->db->prepare("
            SELECT a.imie, a.nazwisko, sa.rola, sa.percentage
            FROM sprawa_agent sa
            JOIN agenci a ON sa.agent_id = a.agent_id
            WHERE sa.sprawa_id = :case_id
            ORDER BY sa.rola
        ");
        $stmt->execute([':case_id' => $caseId]);
        $caseAgents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Tutaj możesz wyświetlić dane sprawy i powiązanych agentów
        // Na razie nie implementujemy pełnego widoku, tylko zwracamy dane
        echo "Agent: {$agent['imie']} {$agent['nazwisko']}, Rola: {$case['rola']}, Sprawa: {$case['case_name']}";
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
            $query = "SELECT agent_id, imie, nazwisko FROM agenci WHERE agent_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$agentId]);
            $agent = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$agent) {
                error_log("AgentController::getAgentCommission - Nie znaleziono agenta o ID: " . $agentId);
                header('Content-Type: application/json');
                echo json_encode([
                    'error' => 'Agent not found',
                    'commission_percentage' => null
                ]);
                return;
            }
            
            // Pobierz średni procent prowizji z tabeli sprawa_agent (jeśli istnieje)
            $query = "SELECT AVG(percentage) AS avg_percentage FROM sprawa_agent WHERE agent_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$agentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $commissionPercentage = null;
            if ($result && $result['avg_percentage'] !== null) {
                $commissionPercentage = number_format((float)$result['avg_percentage'], 2);
                error_log("AgentController::getAgentCommission - Średni procent prowizji: " . $commissionPercentage);
            } else {
                error_log("AgentController::getAgentCommission - Brak danych o prowizji dla agenta");
            }
            
            // Zwróć dane jako JSON
            header('Content-Type: application/json');
            echo json_encode([
                'agent_id' => $agent['agent_id'],
                'agent_name' => $agent['imie'] . ' ' . $agent['nazwisko'],
                'commission_percentage' => $commissionPercentage
            ]);
            
            error_log("AgentController::getAgentCommission - Zwrócono dane JSON");
            
        } catch (PDOException $e) {
            error_log("AgentController::getAgentCommission - BŁĄD: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Database error: ' . $e->getMessage(),
                'commission_percentage' => null
            ]);
        }
    }
}