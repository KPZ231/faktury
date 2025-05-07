<?php
namespace Dell\Faktury\Controllers;

use PDO;
use PDOException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

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

    /**
     * Czyści i escapuje nazwę kolumny dla bezpiecznego użycia w zapytaniach SQL
     * 
     * @param string $columnName Nazwa kolumny do oczyszczenia
     * @return string Bezpieczna nazwa kolumny
     */
    private function safeColumnName(string $columnName): string {
        // Usuń wszystkie znaki oprócz liter, cyfr i podkreślników
        $columnName = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
        
        // Ustaw jako safe string dla SQL (escape dla backticks)
        return str_replace('`', '``', $columnName);
    }

    /** Obsługuje import plików kalkulacyjnych – konwertuje do CSV i wrzuca rekordy do `test` */
    public function importCsv(): void {
        error_log("HomeController::importCsv - Start");
        header('Content-Type: application/json; charset=UTF-8');
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            error_log("HomeController::importCsv - No file uploaded or upload error: " . 
                      (isset($_FILES['file']) ? $_FILES['file']['error'] : 'No file'));
            echo json_encode(['message' => 'Proszę przesłać poprawny plik.']);
            return;
        }

        $file = $_FILES['file'];
        
        // Walidacja pliku
        if (!is_uploaded_file($file['tmp_name'])) {
            error_log("HomeController::importCsv - Security violation: attempted upload forgery");
            echo json_encode(['message' => 'Błąd bezpieczeństwa podczas przesyłania pliku.']);
            return;
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $tempFile = $file['tmp_name'];
        $csvTempFile = null;
        
        error_log("HomeController::importCsv - File uploaded: " . $file['name'] . " (type: $extension)");
        
        try {
            // Sprawdź obsługiwane formaty plików
            $supportedExtensions = ['csv', 'xlsx', 'xls', 'ods'];
            if (!in_array($extension, $supportedExtensions)) {
                throw new \Exception("Nieobsługiwany format pliku. Akceptowane formaty: " . implode(', ', $supportedExtensions));
            }
            
            $rows = [];
            
            // Jeśli to nie jest CSV, skonwertuj plik na format CSV
            if ($extension !== 'csv') {
                error_log("HomeController::importCsv - Converting $extension file to CSV");
                
                // Sprawdź czy rozszerzenie zip jest dostępne (wymagane dla plików Excel)
                if (!class_exists('ZipArchive')) {
                    throw new \Exception("Nie można przetworzyć pliku Excel - brakuje rozszerzenia PHP 'zip'. Proszę użyć pliku CSV lub skontaktować się z administratorem.");
                }
                
                // Wczytaj plik za pomocą PhpSpreadsheet
                $spreadsheet = IOFactory::load($tempFile);
                $worksheet = $spreadsheet->getActiveSheet();
                
                // Stwórz tymczasowy plik CSV
                $csvTempFile = tempnam(sys_get_temp_dir(), 'csv_');
                $writer = new Csv($spreadsheet);
                $writer->setDelimiter(',');
                $writer->setEnclosure('"');
                $writer->setLineEnding("\r\n");
                $writer->setSheetIndex(0);
                $writer->save($csvTempFile);
                
                // Używamy teraz tymczasowego pliku CSV
                $rows = file($csvTempFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                error_log("HomeController::importCsv - File converted to CSV with " . count($rows) . " rows");
            } else {
                // Bezpośrednio wczytaj plik CSV
                $rows = file($tempFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                error_log("HomeController::importCsv - File has " . count($rows) . " rows");
            }
            
            if (!$rows || count($rows) <= 1) { // Przynajmniej nagłówek i jeden wiersz danych
                throw new \Exception('Plik jest pusty lub zawiera tylko nagłówek.');
            }
            
            // Pierwszy wiersz to nagłówek
            $headerRow = array_shift($rows);
            error_log("HomeController::importCsv - Raw header row: " . $headerRow);
            
            $header = str_getcsv($headerRow);
            $columns = array_map('trim', $header); // Usuwamy dodatkowe spacje
            error_log("HomeController::importCsv - Parsed header columns: " . implode(", ", $columns));
            
            // Sprawdź pierwszy wiersz danych, aby zobrazować jak dane są interpretowane
            if (!empty($rows)) {
                $firstDataRow = str_getcsv($rows[0]);
                $dataValues = implode(", ", array_map(function($idx, $val) use ($columns) {
                    return $columns[$idx] . "=" . $val;
                }, array_keys($firstDataRow), $firstDataRow));
                error_log("HomeController::importCsv - First data row: " . $dataValues);
            }
            
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

            // Bardziej elastyczne wyszukiwanie kolumny z numerem faktury
            $invoiceNumberIndex = false;
            $possibleInvoiceColumns = ['numer', 'nr faktury', 'numer faktury', 'nr_faktury', 'numer_faktury'];
            
            // Szukaj dokładnego dopasowania
            foreach ($columns as $i => $column) {
                $columnLower = strtolower(trim($column));
                if (in_array($columnLower, $possibleInvoiceColumns)) {
                    $invoiceNumberIndex = $i;
                    error_log("HomeController::importCsv - Found invoice number column: '$column' at index: $i");
                    break;
                }
            }
            
            // Jeśli nie znaleziono dokładnego dopasowania, szukaj kolumny zawierającej słowo "numer" lub "nr"
            if ($invoiceNumberIndex === false) {
                foreach ($columns as $i => $column) {
                    $columnLower = strtolower(trim($column));
                    if (strpos($columnLower, 'numer') !== false || 
                       (strpos($columnLower, 'nr') !== false && strpos($columnLower, 'fakt') !== false)) {
                        $invoiceNumberIndex = $i;
                        error_log("HomeController::importCsv - Found partial match for invoice number column: '$column' at index: $i");
                        break;
                    }
                }
            }
            
            // Jeśli nadal nie znaleziono, spróbuj z pierwszą kolumną (zazwyczaj może to być LP lub ID)
            if ($invoiceNumberIndex === false && count($columns) > 1) {
                $invoiceNumberIndex = 1; // Często kolumna z numerem faktury jest drugą kolumną (po LP)
                error_log("HomeController::importCsv - No invoice number column found, defaulting to second column: '{$columns[$invoiceNumberIndex]}'");
            }
            
            if ($invoiceNumberIndex === false) {
                error_log("HomeController::importCsv - Column 'numer' or equivalent not found in header");
                $columnList = implode(", ", array_map(function($col) { return "'$col'"; }, $columns));
                echo json_encode([
                    'message' => 'Nie znaleziono kolumny z numerem faktury w pliku. Sprawdź czy plik zawiera kolumnę "numer" lub podobną.',
                    'details' => "Znalezione kolumny: $columnList"
                ]);
                return;
            }

            // Bezpieczne przygotowanie nazw kolumn dla SQL (zabezpieczenie przed SQL Injection)
            $safeValidColumns = array_map([$this, 'safeColumnName'], $validColumns);
            
            // Przygotuj zapytania używając bezpiecznych nazw kolumn
            $colList = implode(',', array_map(fn($c) => "`$c`", $safeValidColumns));
            $placeholders = implode(',', array_fill(0, count($validColumns), '?'));
            
            error_log("HomeController::importCsv - Preparing database statements");
            
            // Zapytanie do sprawdzenia czy faktura istnieje - używa prepared statement
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM `test` WHERE `numer` = ?");
            
            // Zapytanie do wstawienia nowej faktury - używa prepared statement
            $insertStmt = $pdo->prepare("INSERT INTO `test` ($colList) VALUES ($placeholders)");
            
            // Zapytanie do aktualizacji istniejącej faktury - używa prepared statement
            $updateCols = implode(',', array_map(fn($c) => "`$c` = ?", $safeValidColumns));
            $updateStmt = $pdo->prepare("UPDATE `test` SET $updateCols WHERE `numer` = ?");

            error_log("HomeController::importCsv - Starting database transaction");
            $pdo->beginTransaction();
            $imported = 0;
            $updated = 0;

            foreach ($rows as $lineIndex => $line) {
                $fields = str_getcsv($line);
                
                // Wyodrębnij numer faktury
                if (!isset($fields[$invoiceNumberIndex])) {
                    error_log("HomeController::importCsv - Missing invoice number in row: " . ($lineIndex + 2));
                    continue; // Pomijamy ten wiersz
                }
                
                $invoiceNumber = $fields[$invoiceNumberIndex];
                
                // Walidacja numeru faktury
                if (empty(trim($invoiceNumber))) {
                    error_log("HomeController::importCsv - Empty invoice number in row: " . ($lineIndex + 2));
                    continue; // Pomijamy ten wiersz z pustym numerem faktury
                }
                
                // Sanityzacja danych wejściowych
                $invoiceNumber = htmlspecialchars($invoiceNumber, ENT_QUOTES, 'UTF-8');
                error_log("HomeController::importCsv - Processing invoice number: " . $invoiceNumber);
                
                // Wyodrębnij tylko kolumny, które istnieją w bazie danych
                $validFields = [];
                foreach ($validColumnIndexes as $idx) {
                    // Sanityzacja danych wejściowych
                    $value = isset($fields[$idx]) ? $fields[$idx] : null;
                    // Dla wartości null nie wykonujemy sanityzacji
                    if ($value !== null) {
                        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    }
                    $validFields[] = $value;
                }
                
                // Sprawdź czy faktura już istnieje używając prepared statement
                $checkStmt->execute([$invoiceNumber]);
                $exists = $checkStmt->fetchColumn() > 0;
                error_log("HomeController::importCsv - Invoice exists: " . ($exists ? 'Yes' : 'No'));

                if ($exists) {
                    // Aktualizuj istniejącą fakturę używając prepared statement
                    error_log("HomeController::importCsv - Updating existing invoice: " . $invoiceNumber);
                    $updateFields = array_merge($validFields, [$invoiceNumber]);
                    $updateStmt->execute($updateFields);
                    $updated++;
                } else {
                    // Wstaw nową fakturę używając prepared statement
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
        } catch (\Exception $e) {
            error_log("HomeController::importCsv - ERROR: " . $e->getMessage());
            error_log("HomeController::importCsv - Stack trace: " . $e->getTraceAsString());
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['message' => 'Błąd podczas importu: ' . $e->getMessage()]);
        } finally {
            // Usuń tymczasowy plik CSV jeśli istnieje
            if ($csvTempFile && file_exists($csvTempFile)) {
                unlink($csvTempFile);
            }
        }
        error_log("HomeController::importCsv - Complete");
    }
}
