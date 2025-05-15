<?php

namespace Lenovo\Faktury\Controllers;

class HomeController {
    /**
     * Wyświetla stronę główną systemu
     */
    public function index(): void {
        // Załadowanie widoku
        include __DIR__ . '/../Views/home.php';
    }

    /**
     * Wyświetla stronę importu faktur
     */
    public function import(): void {
        include __DIR__ . '/../Views/import.php';
    }

    /**
     * Obsługuje zarządzanie agentami
     */
    public function agents(): void {
        // Inicjalizacja zmiennych
        $messages = [];
        $errors = [];
        $old_input = [];

        // Obsługa formularza POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nazwa_agenta = trim($_POST['nazwa_agenta'] ?? '');

            // Walidacja
            if (empty($nazwa_agenta)) {
                $errors[] = "Imię i nazwisko agenta jest wymagane.";
            }

            // Sprawdzenie unikalności
            if (empty($errors)) {
                try {
                    $pdo = new \PDO(
                        "mysql:host=localhost;dbname=projektimport;charset=utf8mb4",
                        "root",
                        "",
                        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
                    );
                    
                    $sqlCheck = "SELECT COUNT(*) FROM Agenci WHERE nazwa_agenta = :nazwa";
                    $stmtCheck = $pdo->prepare($sqlCheck);
                    $stmtCheck->execute([':nazwa' => $nazwa_agenta]);
                    
                    if ($stmtCheck->fetchColumn() > 0) {
                        $errors[] = "Agent o takim imieniu i nazwisku już istnieje.";
                    } else {
                        // Dodaj nowego agenta
                        $sql = "INSERT INTO Agenci (nazwa_agenta) VALUES (:nazwa)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([':nazwa' => $nazwa_agenta]);

                        $messages['success'] = "Nowy agent '{$nazwa_agenta}' został pomyślnie dodany.";
                    }
                } catch (\PDOException $e) {
                    error_log("DB Error in agents(): " . $e->getMessage());
                    $errors[] = "Wystąpił błąd podczas operacji na bazie danych.";
                    $old_input = $_POST;
                }
            } else {
                $old_input = $_POST;
            }
        }

        // Przekaż zmienne do widoku
        $GLOBALS['messages'] = $messages;
        $GLOBALS['errors'] = $errors;
        $GLOBALS['old_input'] = $old_input;

        // Załaduj widok
        include __DIR__ . '/../Views/agents.php';
    }
    
    /**
     * Obsługuje import faktur
     */
    public function handleImportPost(): void {
        header('Content-Type: application/json; charset=UTF-8');
        
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['error' => 'Proszę przesłać poprawny plik.']);
            return;
        }
        
        $file = $_FILES['file'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        try {
            if ($extension !== 'csv') {
                throw new \Exception('Tylko pliki CSV są obsługiwane.');
            }
            
            $rows = file($file['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!$rows) {
                throw new \Exception('Plik jest pusty lub błędny.');
            }
            
            // Pomijamy nagłówek
            $header = array_shift($rows);
            $headerColumns = str_getcsv($header);
            $headerColumns = array_map('trim', $headerColumns);
            
            // Połączenie z bazą danych
            $pdo = new \PDO(
                "mysql:host=localhost;dbname=projektimport;charset=utf8mb4",
                "root",
                "",
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            
            // Pobierz nazwy kolumn z tabeli faktury
            $stmtColumns = $pdo->query("DESCRIBE faktury");
            $tableColumns = [];
            while ($column = $stmtColumns->fetch(\PDO::FETCH_ASSOC)) {
                $tableColumns[] = $column['Field'];
            }
            
            // Mapuj kolumny CSV do kolumn bazy danych
            $columnMapping = [];
            foreach ($headerColumns as $index => $columnName) {
                if (in_array($columnName, $tableColumns)) {
                    $columnMapping[$index] = $columnName;
                }
            }
            
            if (empty($columnMapping)) {
                throw new \Exception('Brak pasujących kolumn w pliku CSV i tabeli faktury.');
            }
            
            // Przygotuj zapytanie SQL
            $columns = implode(', ', array_map(function($col) {
                return "`" . str_replace('`', '``', $col) . "`";
            }, array_values($columnMapping)));
            
            $placeholders = implode(', ', array_fill(0, count($columnMapping), '?'));
            
            $stmt = $pdo->prepare("INSERT INTO faktury ($columns) VALUES ($placeholders)");
            
            // Rozpocznij transakcję
            $pdo->beginTransaction();
            $imported = 0;
            
            foreach ($rows as $row) {
                $data = str_getcsv($row);
                $values = [];
                
                foreach ($columnMapping as $csvIndex => $dbColumn) {
                    $values[] = isset($data[$csvIndex]) ? $data[$csvIndex] : null;
                }
                
                $stmt->execute($values);
                $imported++;
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Zaimportowano $imported rekordów."]);
            
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['error' => $e->getMessage()]);
        }
        
        include __DIR__ . '/../Views/import.php';
    }

    /**
     * Aktualizuje informacje o płatności agenta
     */
    public function updatePayment(): void {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id_sprawy'], $data['id_agenta'], $data['opis_raty'], $data['kwota'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            return;
        }

        try {
            $pdo = new \PDO(
                "mysql:host=localhost;dbname=projektimport;charset=utf8mb4",
                "root",
                "",
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            // Sprawdź czy wpis już istnieje
            $stmt = $pdo->prepare("SELECT id_wyplaty FROM agenci_wyplaty WHERE id_sprawy = :id_sprawy AND id_agenta = :id_agenta AND opis_raty = :opis_raty");
            $stmt->execute([
                ':id_sprawy' => $data['id_sprawy'],
                ':id_agenta' => $data['id_agenta'],
                ':opis_raty' => $data['opis_raty']
            ]);
            
            $existing = $stmt->fetch();

            if ($existing) {
                // Aktualizuj istniejący wpis
                $stmt = $pdo->prepare("UPDATE agenci_wyplaty SET 
                    czy_oplacone = :czy_oplacone,
                    numer_faktury = :numer_faktury,
                    data_platnosci = :data_platnosci
                    WHERE id_wyplaty = :id_wyplaty");
                
                $stmt->execute([
                    ':czy_oplacone' => $data['czy_oplacone'] ?? false,
                    ':numer_faktury' => $data['numer_faktury'] ?? null,
                    ':data_platnosci' => $data['data_platnosci'] ?? null,
                    ':id_wyplaty' => $existing['id_wyplaty']
                ]);
            } else {
                // Dodaj nowy wpis
                $stmt = $pdo->prepare("INSERT INTO agenci_wyplaty 
                    (id_sprawy, id_agenta, opis_raty, kwota, czy_oplacone, numer_faktury, data_platnosci) 
                    VALUES 
                    (:id_sprawy, :id_agenta, :opis_raty, :kwota, :czy_oplacone, :numer_faktury, :data_platnosci)");
                
                $stmt->execute([
                    ':id_sprawy' => $data['id_sprawy'],
                    ':id_agenta' => $data['id_agenta'],
                    ':opis_raty' => $data['opis_raty'],
                    ':kwota' => $data['kwota'],
                    ':czy_oplacone' => $data['czy_oplacone'] ?? false,
                    ':numer_faktury' => $data['numer_faktury'] ?? null,
                    ':data_platnosci' => $data['data_platnosci'] ?? null
                ]);
            }

            echo json_encode(['success' => true]);
        } catch (\PDOException $e) {
            error_log("DB Error in updatePayment(): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
        }
    }

    /**
     * Pobiera status płatności
     */
    public function getPaymentStatus(): void {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $id_sprawy = $_GET['id_sprawy'] ?? null;
        $id_agenta = $_GET['id_agenta'] ?? null;
        $opis_raty = $_GET['opis_raty'] ?? null;

        if (!$id_sprawy || !$id_agenta || !$opis_raty) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required parameters']);
            return;
        }

        try {
            $pdo = new \PDO(
                "mysql:host=localhost;dbname=projektimport;charset=utf8mb4",
                "root",
                "",
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            $stmt = $pdo->prepare("SELECT czy_oplacone, numer_faktury, data_platnosci 
                                 FROM agenci_wyplaty 
                                 WHERE id_sprawy = :id_sprawy 
                                 AND id_agenta = :id_agenta 
                                 AND opis_raty = :opis_raty");
            
            $stmt->execute([
                ':id_sprawy' => $id_sprawy,
                ':id_agenta' => $id_agenta,
                ':opis_raty' => $opis_raty
            ]);

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            echo json_encode($result ?: ['czy_oplacone' => false, 'numer_faktury' => null, 'data_platnosci' => null]);
        } catch (\PDOException $e) {
            error_log("DB Error in getPaymentStatus(): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
        }
    }
}