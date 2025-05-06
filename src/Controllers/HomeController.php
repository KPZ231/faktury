<?php
namespace Dell\Faktury\Controllers;

use PDO;
use PDOException;

class HomeController {
    /** Wyświetla stronę z tabelą i formularzem importu */
    public function index(): void {
        error_log("HomeController::index - Start");
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        error_log("HomeController::index - Database connection established");
        include __DIR__ . '/../Views/home.php';
        error_log("HomeController::index - View rendered");
    }

    /** Obsługuje import CSV – wczytuje plik, pomija nagłówek i wrzuca rekordy do `test` */
    public function importCsv(): void {
        error_log("HomeController::importCsv - Start");
        header('Content-Type: application/json; charset=UTF-8');
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            error_log("HomeController::importCsv - No file uploaded or upload error: " . 
                      (isset($_FILES['file']) ? $_FILES['file']['error'] : 'No file'));
            echo json_encode(['message' => 'Proszę przesłać poprawny plik CSV.']);
            return;
        }

        error_log("HomeController::importCsv - File uploaded: " . $_FILES['file']['name']);
        $rows = file($_FILES['file']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$rows) {
            error_log("HomeController::importCsv - Empty or invalid file");
            echo json_encode(['message' => 'Plik jest pusty lub błędny.']);
            return;
        }

        error_log("HomeController::importCsv - File has " . count($rows) . " rows");
        // Pierwszy wiersz to nagłówek
        $header = str_getcsv(array_shift($rows));
        $columns = $header;
        error_log("HomeController::importCsv - Header columns: " . implode(", ", $columns));

        // Pobierz faktyczne kolumny z bazy danych
        $tableColumns = [];
        $colStmt = $pdo->query("DESCRIBE `test`");
        while ($row = $colStmt->fetch(PDO::FETCH_ASSOC)) {
            $tableColumns[] = $row['Field'];
        }
        error_log("HomeController::importCsv - Database columns: " . implode(", ", $tableColumns));

        // Filtruj kolumny CSV, pozostawiając tylko te, które istnieją w bazie danych
        $validColumnIndexes = [];
        $validColumns = [];
        $processedColumns = []; // Track already processed columns to avoid duplicates
        foreach ($columns as $i => $column) {
            // Skip if this column name has already been processed
            if (in_array($column, $processedColumns)) {
                error_log("HomeController::importCsv - Skipping duplicate column: " . $column);
                continue;
            }
            
            if (in_array($column, $tableColumns)) {
                $validColumnIndexes[] = $i;
                $validColumns[] = $column;
                $processedColumns[] = $column; // Mark this column as processed
            }
        }
        error_log("HomeController::importCsv - Valid columns: " . implode(", ", $validColumns));

        // Znajdź indeks kolumny z numerem faktury
        $invoiceNumberIndex = array_search('numer', $columns);
        if ($invoiceNumberIndex === false) {
            error_log("HomeController::importCsv - Column 'numer' not found in header");
            echo json_encode(['message' => 'Nie znaleziono kolumny "numer" w pliku.']);
            return;
        }
        error_log("HomeController::importCsv - Found 'numer' column at index: " . $invoiceNumberIndex);

        // Przygotuj zapytania używając tylko istniejących kolumn
        $colList = implode(',', array_map(fn($c) => "`".str_replace('`','``',$c)."`", $validColumns));
        $placeholders = implode(',', array_fill(0, count($validColumns), '?'));
        
        error_log("HomeController::importCsv - Preparing database statements");
        // Zapytanie do sprawdzenia czy faktura istnieje
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM `test` WHERE `numer` = ?");
        
        // Zapytanie do wstawienia nowej faktury
        $insertStmt = $pdo->prepare("INSERT INTO `test` ($colList) VALUES ($placeholders)");
        
        // Zapytanie do aktualizacji istniejącej faktury
        $updateCols = implode(',', array_map(fn($c) => "`".str_replace('`','``',$c)."` = ?", $validColumns));
        $updateStmt = $pdo->prepare("UPDATE `test` SET $updateCols WHERE `numer` = ?");

        error_log("HomeController::importCsv - Starting database transaction");
        $pdo->beginTransaction();
        $imported = 0;
        $updated = 0;

        try {
            foreach ($rows as $lineIndex => $line) {
                $fields = str_getcsv($line);
                
                // Wyodrębnij numer faktury
                $invoiceNumber = $fields[$invoiceNumberIndex];
                error_log("HomeController::importCsv - Processing invoice number: " . $invoiceNumber);
                
                // Wyodrębnij tylko kolumny, które istnieją w bazie danych
                $validFields = [];
                foreach ($validColumnIndexes as $idx) {
                    $validFields[] = isset($fields[$idx]) ? $fields[$idx] : null;
                }
                
                // Sprawdź czy faktura już istnieje
                $checkStmt->execute([$invoiceNumber]);
                $exists = $checkStmt->fetchColumn() > 0;
                error_log("HomeController::importCsv - Invoice exists: " . ($exists ? 'Yes' : 'No'));

                if ($exists) {
                    // Aktualizuj istniejącą fakturę
                    error_log("HomeController::importCsv - Updating existing invoice: " . $invoiceNumber);
                    $updateFields = array_merge($validFields, [$invoiceNumber]);
                    $updateStmt->execute($updateFields);
                    $updated++;
                } else {
                    // Wstaw nową fakturę
                    error_log("HomeController::importCsv - Inserting new invoice: " . $invoiceNumber);
                    $insertStmt->execute($validFields);
                    $imported++;
                }
            }

            error_log("HomeController::importCsv - Committing transaction. Imported: $imported, Updated: $updated");
            $pdo->commit();
            echo json_encode([
                'message' => "Zaimportowano $imported nowych rekordów i zaktualizowano $updated istniejących rekordów."
            ]);
        } catch (PDOException $e) {
            error_log("HomeController::importCsv - ERROR: " . $e->getMessage());
            error_log("HomeController::importCsv - Stack trace: " . $e->getTraceAsString());
            $pdo->rollBack();
            echo json_encode(['message' => 'Błąd podczas importu: ' . $e->getMessage()]);
        }
        error_log("HomeController::importCsv - Complete");
    }
}
