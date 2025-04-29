<?php
namespace Dell\Faktury\Controllers;

use PDO;
use PDOException;

class HomeController {
    /** Wyświetla stronę z tabelą i formularzem importu */
    public function index(): void {
        include __DIR__ . '/../Views/home.php';
    }

    /** Obsługuje import CSV – wczytuje plik, pomija nagłówek i wrzuca rekordy do `test` */
    public function importCsv(): void {
        header('Content-Type: application/json; charset=UTF-8');
        require_once __DIR__ . '/../../config/database.php';

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['message' => 'Proszę przesłać poprawny plik CSV.']);
            return;
        }

        $rows = file($_FILES['file']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$rows) {
            echo json_encode(['message' => 'Plik jest pusty lub błędny.']);
            return;
        }

        // Pierwszy wiersz to nagłówek
        $header = str_getcsv(array_shift($rows));
        $columns = $header;

        // Znajdź indeks kolumny z numerem faktury
        $invoiceNumberIndex = array_search('numer', $columns);
        if ($invoiceNumberIndex === false) {
            echo json_encode(['message' => 'Nie znaleziono kolumny "numer" w pliku.']);
            return;
        }

        // Przygotuj zapytania
        $colList = implode(',', array_map(fn($c) => "`".str_replace('`','``',$c)."`", $columns));
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        
        // Zapytanie do sprawdzenia czy faktura istnieje
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM `test` WHERE `numer` = ?");
        
        // Zapytanie do wstawienia nowej faktury
        $insertStmt = $pdo->prepare("INSERT INTO `test` ($colList) VALUES ($placeholders)");
        
        // Zapytanie do aktualizacji istniejącej faktury
        $updateCols = implode(',', array_map(fn($c) => "`".str_replace('`','``',$c)."` = ?", $columns));
        $updateStmt = $pdo->prepare("UPDATE `test` SET $updateCols WHERE `numer` = ?");

        $pdo->beginTransaction();
        $imported = 0;
        $updated = 0;

        try {
            foreach ($rows as $line) {
                $fields = str_getcsv($line);
                
                // Dopasuj liczbę wartości do kolumn
                if (count($fields) < count($columns)) {
                    $fields = array_pad($fields, count($columns), null);
                } elseif (count($fields) > count($columns)) {
                    $fields = array_slice($fields, 0, count($columns));
                }

                $invoiceNumber = $fields[$invoiceNumberIndex];
                
                // Sprawdź czy faktura już istnieje
                $checkStmt->execute([$invoiceNumber]);
                $exists = $checkStmt->fetchColumn() > 0;

                if ($exists) {
                    // Aktualizuj istniejącą fakturę
                    $updateFields = array_merge($fields, [$invoiceNumber]);
                    $updateStmt->execute($updateFields);
                    $updated++;
                } else {
                    // Wstaw nową fakturę
                    $insertStmt->execute($fields);
                    $imported++;
                }
            }

            $pdo->commit();
            echo json_encode([
                'message' => "Zaimportowano $imported nowych rekordów i zaktualizowano $updated istniejących rekordów."
            ]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['message' => 'Błąd podczas importu: ' . $e->getMessage()]);
        }
    }
}
