<?php

namespace Dell\Faktury\Controllers;

use PDO;
use PDOException;

class AgentController
{
    private ?PDO $pdo = null;

    private function initPdo(): void
    {
        if ($this->pdo === null) {
            require __DIR__ . '/../../config/database.php';
            global $pdo;
            $this->pdo = $pdo;
        }
    }

    /**
     * Wyświetla listę agentów i formularz dodawania
     */
    public function index(): void
    {
        require __DIR__ . '/../../config/database.php';
        global $pdo;
        $this->pdo = $pdo;

        $this->initPdo();

        $stmt = $this->pdo->query("SELECT agent_id, imie, nazwisko, sprawy FROM agenci ORDER BY agent_id ASC");
        $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = count($agents);

        // Przekazujemy dane do widoku bezpośrednio, niech widok nie używa globalnego $pdo
        $agentsData = [
            'agents' => $agents,
            'count' => $count
        ];

        include __DIR__ . '/../Views/agents.php';
    }

    /**
     * Obsługa dodawania nowego agenta
     */
    public function addAgent(): void
    {
        $this->initPdo();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('HTTP/1.1 405 Method Not Allowed');
            echo "Metoda niedozwolona.";
            exit;
        }

        $imie = trim($_POST['imie'] ?? '');
        $nazwisko = trim($_POST['nazwisko'] ?? '');

        if ($imie === '' || $nazwisko === '') {
            $error = 'Proszę wypełnić wszystkie pola.';
            $this->index();
            return;
        }

        try {
            $sql = "INSERT INTO agenci (imie, nazwisko, sprawy) VALUES (:imie, :nazwisko, JSON_ARRAY())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':imie' => $imie,
                ':nazwisko' => $nazwisko
            ]);

            header('Location: /agents');
            exit;
        } catch (PDOException $e) {
            $error = 'Błąd bazy danych: ' . $e->getMessage();
            $this->index();
        }
    }

    /**
     * Wyświetla szczegóły pojedynczej sprawy agenta
     * @param int $agentId
     * @param string $caseName
     */
    public function showCase(int $agentId, string $caseName): void
    {
        $this->initPdo();

        $stmt = $this->pdo->prepare("SELECT imie, nazwisko, sprawy FROM agenci WHERE agent_id = :id");
        $stmt->execute([':id' => $agentId]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$agent) {
            echo "Nie znaleziono agenta.";
            return;
        }

        $cases = json_decode($agent['sprawy'], true) ?: [];
        if (!in_array($caseName, $cases, true)) {
            echo "Brak takiej sprawy dla tego agenta.";
            return;
        }
    }
}