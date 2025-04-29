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
        require_once __DIR__ . '/../../config/configuration.php'; // dające $pdo

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
        $header  = str_getcsv(array_shift($rows));
        $columns = $header;
        // Przygotuj INSERT
        $colList      = implode(',', array_map(fn($c) => "`".str_replace('`','``',$c)."`", $columns));
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $stmt         = $pdo->prepare("INSERT INTO `test` ({$colList}) VALUES ({$placeholders})");

        $pdo->beginTransaction();
        $count = 0;
        foreach ($rows as $line) {
            $fields = str_getcsv($line);
            // Dopasuj liczbę wartości do kolumn
            $fields = count($fields) < count($columns)
                    ? array_pad($fields, count($columns), null)
                    : array_slice($fields, 0, count($columns));
            $stmt->execute($fields);
            $count++;
        }
        $pdo->commit();

        echo json_encode(['message' => "Zaimportowano {$count} rekordów."]);
    }
}
