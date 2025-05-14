<?php

namespace Lenovo\Faktury\Controllers;

use Lenovo\Faktury\Models\User;

class HomeController {
    public function index(): void {
        // Pobranie listy użytkowników
        $users = User::all();

        // Załadowanie widoku
        include __DIR__ . '/../Views/home.php';
    }
    public function import(): void {
        $users = User::all();

        include __DIR__ . '/../Views/import.php';
    }

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
    
    public function handleImportPost(): void {
        include __DIR__ . '/../Views/import.php';
    }

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