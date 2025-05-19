<?php
namespace Dell\Faktury\Controllers;

use PDO;
use PDOException;

class DatabaseManageController {
    /**
     * Wyświetla stronę zarządzania bazą danych
     */
    public function index(): void {
        error_log("DatabaseManageController::index - Start");
        
        // Sprawdź uprawnienia użytkownika - tylko superadmin ma dostęp
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
            error_log("DatabaseManageController::index - Access denied for user: " . ($_SESSION['user'] ?? 'unknown'));
            header('Location: /?access_denied=1');
            exit;
        }
        
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        
        // Pobierz informacje o tabelach w bazie danych
        $tables = [];
        try {
            $stmt = $pdo->query("SHOW TABLES");
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $tableName = $row[0];
                
                // Pobierz liczbę rekordów w tabeli
                $countStmt = $pdo->query("SELECT COUNT(*) FROM `$tableName`");
                $recordCount = $countStmt->fetchColumn();
                
                // Pobierz informacje o kolumnach
                $columnsStmt = $pdo->query("DESCRIBE `$tableName`");
                $columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $tables[] = [
                    'name' => $tableName,
                    'records' => $recordCount,
                    'columns' => $columns
                ];
            }
        } catch (PDOException $e) {
            error_log("DatabaseManageController::index - Error: " . $e->getMessage());
        }
        
        // Przekaż dane do widoku
        $databaseInfo = [
            'tables' => $tables
        ];
        
        error_log("DatabaseManageController::index - Rendering view");
        include __DIR__ . '/../Views/databasemanage.php';
        error_log("DatabaseManageController::index - View rendered");
    }
    
    /**
     * Testuje połączenie z bazą danych z podanymi danymi
     */
    public function testConnection(): void {
        error_log("DatabaseManageController::testConnection - Start");
        header('Content-Type: application/json; charset=UTF-8');
        
        // Sprawdź czy mamy wszystkie wymagane dane
        if (!isset($_POST['host']) || !isset($_POST['dbname']) || !isset($_POST['username'])) {
            echo json_encode([
                'success' => false, 
                'message' => 'Brakuje wymaganych danych do połączenia z bazą'
            ]);
            return;
        }
        
        $host = $_POST['host'];
        $dbname = $_POST['dbname'];
        $username = $_POST['username'];
        $password = $_POST['password'] ?? '';
        
        error_log("DatabaseManageController::testConnection - Attempting to connect to database $dbname on $host as $username");
        
        try {
            // Próba połączenia z podanymi danymi
            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, $username, $password, $options);
            
            // Zapisz dane połączenia do sesji, aby móc ich użyć później
            $_SESSION['db_connection'] = [
                'host' => $host,
                'dbname' => $dbname,
                'username' => $username,
                'password' => $password
            ];
            
            // Test uprawnień - sprawdź uprawnienia do TRUNCATE
            $truncateAllowed = false;
            try {
                $stmt = $pdo->query("SHOW GRANTS FOR CURRENT_USER()");
                $grants = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                $hasTruncatePrivilege = false;
                foreach ($grants as $grant) {
                    // Sprawdź czy użytkownik ma uprawnienia ALL PRIVILEGES lub specyficznie DELETE lub TRUNCATE
                    if (
                        strpos($grant, 'ALL PRIVILEGES') !== false ||
                        strpos($grant, 'DELETE') !== false
                    ) {
                        $hasTruncatePrivilege = true;
                        break;
                    }
                }
                
                error_log("DatabaseManageController::testConnection - Truncate privilege check: " . ($hasTruncatePrivilege ? 'YES' : 'NO'));
                $truncateAllowed = $hasTruncatePrivilege;
            } catch (PDOException $e) {
                error_log("DatabaseManageController::testConnection - Error checking privileges: " . $e->getMessage());
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Połączenie z bazą danych nawiązane pomyślnie',
                'privileges' => [
                    'truncate' => $truncateAllowed
                ]
            ]);
        } catch (PDOException $e) {
            error_log("DatabaseManageController::testConnection - Connection error: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Błąd połączenia z bazą danych: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Pobiera PDO z sesji albo domyślne
     */
    private function getDbConnection(): PDO {
        if (isset($_SESSION['db_connection'])) {
            try {
                $conn = $_SESSION['db_connection'];
                $dsn = "mysql:host={$conn['host']};dbname={$conn['dbname']};charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                
                return new PDO($dsn, $conn['username'], $conn['password'], $options);
            } catch (PDOException $e) {
                error_log("Error using custom DB connection: " . $e->getMessage());
                // Fall back to default connection if custom fails
            }
        }
        
        // Use default connection
        require_once __DIR__ . '/../../config/database.php';
        global $pdo;
        return $pdo;
    }
    
    /**
     * Tworzy kopię zapasową tabeli
     */
    public function backupTable(): void {
        error_log("DatabaseManageController::backupTable - Start");
        header('Content-Type: application/json; charset=UTF-8');
        
        // Sprawdź uprawnienia użytkownika - tylko superadmin ma dostęp
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
            error_log("DatabaseManageController::backupTable - Access denied for user: " . ($_SESSION['user'] ?? 'unknown'));
            echo json_encode(['success' => false, 'message' => 'Brak uprawnień do wykonania tej operacji']);
            return;
        }
        
        if (!isset($_POST['table']) || empty($_POST['table'])) {
            echo json_encode(['success' => false, 'message' => 'Nie podano nazwy tabeli']);
            return;
        }
        
        $tableName = $_POST['table'];
        $backupTableName = $tableName . '_backup_' . date('Ymd_His');
        
        try {
            $pdo = $this->getDbConnection();
            
            // Utwórz kopię tabeli
            $pdo->exec("CREATE TABLE `$backupTableName` LIKE `$tableName`");
            $pdo->exec("INSERT INTO `$backupTableName` SELECT * FROM `$tableName`");
            
            echo json_encode([
                'success' => true, 
                'message' => "Utworzono kopię zapasową tabeli jako $backupTableName"
            ]);
        } catch (PDOException $e) {
            error_log("DatabaseManageController::backupTable - Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Błąd: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Czyści wszystkie rekordy z tabeli
     */
    public function truncateTable(): void {
        error_log("DatabaseManageController::truncateTable - Start");
        header('Content-Type: application/json; charset=UTF-8');
        
        // Sprawdź uprawnienia użytkownika - tylko superadmin ma dostęp
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
            error_log("DatabaseManageController::truncateTable - Access denied for user: " . ($_SESSION['user'] ?? 'unknown'));
            echo json_encode(['success' => false, 'message' => 'Brak uprawnień do wykonania tej operacji']);
            return;
        }
        
        if (!isset($_POST['table']) || empty($_POST['table'])) {
            echo json_encode(['success' => false, 'message' => 'Nie podano nazwy tabeli']);
            return;
        }
        
        $tableName = $_POST['table'];
        error_log("DatabaseManageController::truncateTable - Attempting to truncate table: $tableName");
        
        try {
            $pdo = $this->getDbConnection();
            
            // Sprawdź, czy tabela jest tabelą agentów, która zawiera rekordy, które powinniśmy zachować
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `$tableName` LIKE 'id'");
            $stmt->execute();
            $hasIdColumn = $stmt->rowCount() > 0;
            
            // Sprawdź czy mamy kolumnę id_agenta zamiast id
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `$tableName` LIKE 'id_agenta'");
            $stmt->execute();
            $hasIdAgentaColumn = $stmt->rowCount() > 0;
            
            $idColumnName = $hasIdAgentaColumn ? 'id_agenta' : 'id';
            
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `$tableName` LIKE 'name'");
            $stmt->execute();
            $hasNameColumn = $stmt->rowCount() > 0;
            
            // Znajdź agenta do zachowania, jeśli istnieje
            $agentToPreserve = null;
            $isAgentTable = ($hasIdAgentaColumn || $hasIdColumn) && $hasNameColumn;
            
            // Jeśli to tabela agentów, sprawdź czy istnieje agent o id=1 i name='Kuba'
            if ($isAgentTable) {
                $stmt = $pdo->prepare("SELECT * FROM `$tableName` WHERE $idColumnName = 1 AND name = 'Kuba'");
                $stmt->execute();
                $agentToPreserve = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($agentToPreserve) {
                    error_log("DatabaseManageController::truncateTable - Found agent with $idColumnName=1 and name='Kuba' to preserve");
                }
            }
            
            // Wyłącz sprawdzanie kluczy obcych tymczasowo
            error_log("DatabaseManageController::truncateTable - Disabling foreign key checks");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Najpierw spróbuj użyć DELETE
            try {
                error_log("DatabaseManageController::truncateTable - Trying DELETE FROM approach with foreign key checks disabled");
                
                // Jeśli to tabela agentów, użyj DELETE z warunkiem WHERE
                if ($isAgentTable) {
                    error_log("DatabaseManageController::truncateTable - Using DELETE with condition to preserve agent with $idColumnName=1");
                    $pdo->exec("DELETE FROM `$tableName` WHERE $idColumnName <> 1");
                } else {
                    // Dla innych tabel użyj standardowego DELETE
                    $pdo->exec("DELETE FROM `$tableName`");
                }
                
                // Ponownie włącz sprawdzanie kluczy obcych
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                
                // Komunikat o pomyślnym usunięciu rekordów z zachowaniem agenta (jeśli dotyczy)
                if ($isAgentTable) {
                    echo json_encode([
                        'success' => true, 
                        'message' => "Usunięto wszystkie rekordy z tabeli $tableName (z zachowaniem agenta o $idColumnName=1)"
                    ]);
                } else {
                    echo json_encode([
                        'success' => true, 
                        'message' => "Usunięto wszystkie rekordy z tabeli $tableName"
                    ]);
                }
                return;
            } catch (PDOException $deleteError) {
                error_log("DatabaseManageController::truncateTable - DELETE failed: " . $deleteError->getMessage());
                // Jeśli DELETE nie zadziała, spróbuj TRUNCATE (z wyłączonymi już kluczami obcymi)
            }
            
            // Spróbuj TRUNCATE z wyłączonymi kluczami obcymi
            error_log("DatabaseManageController::truncateTable - Trying TRUNCATE TABLE approach with foreign key checks disabled");
            
            // Jeśli to tabela agentów, najpierw zabezpiecz agenta o id=1
            if ($isAgentTable) {
                // Pobierz dane agenta do zachowania, nawet jeśli go nie znaleźliśmy wcześniej
                if (!$agentToPreserve) {
                    $stmt = $pdo->prepare("SELECT * FROM `$tableName` WHERE $idColumnName = 1");
                    $stmt->execute();
                    $agentToPreserve = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($agentToPreserve) {
                        error_log("DatabaseManageController::truncateTable - Found agent with $idColumnName=1 to preserve for TRUNCATE");
                    }
                }
            }
            
            // Wykonaj TRUNCATE
            $pdo->exec("TRUNCATE TABLE `$tableName`");
            
            // Ponownie włącz sprawdzanie kluczy obcych
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            // Jeśli agent istniał, przywróć go
            if ($agentToPreserve) {
                $columns = array_keys($agentToPreserve);
                $placeholders = array_fill(0, count($columns), '?');
                
                $sql = "INSERT INTO `$tableName` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($agentToPreserve));
                
                echo json_encode([
                    'success' => true, 
                    'message' => "Usunięto wszystkie rekordy z tabeli $tableName (z zachowaniem agenta o $idColumnName=1)"
                ]);
                return;
            }
            
            echo json_encode([
                'success' => true, 
                'message' => "Usunięto wszystkie rekordy z tabeli $tableName"
            ]);
        } catch (PDOException $e) {
            // W przypadku błędu upewnij się, że klucze obce są ponownie włączone
            try {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            } catch (PDOException $e2) {
                error_log("DatabaseManageController::truncateTable - Error re-enabling foreign key checks: " . $e2->getMessage());
            }
            
            error_log("DatabaseManageController::truncateTable - Error: " . $e->getMessage());
            // Sprawdź czy to error dostępu
            $errorCode = $e->getCode();
            error_log("DatabaseManageController::truncateTable - Error code: " . $errorCode);
            
            if ($errorCode == 1142) { // Access denied for this command
                echo json_encode([
                    'success' => false, 
                    'message' => "Błąd uprawnień: Brak wystarczających uprawnień do czyszczenia tabeli. Komunikat: " . $e->getMessage(),
                    'errorDetails' => [
                        'code' => $errorCode,
                        'message' => $e->getMessage()
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Błąd: ' . $e->getMessage(),
                    'errorDetails' => [
                        'code' => $errorCode,
                        'message' => $e->getMessage()
                    ]
                ]);
            }
        }
    }
    
    /**
     * Usuwa tabelę z bazy danych
     */
    public function dropTable(): void {
        error_log("DatabaseManageController::dropTable - Start");
        header('Content-Type: application/json; charset=UTF-8');
        
        // Sprawdź uprawnienia użytkownika - tylko superadmin ma dostęp
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
            error_log("DatabaseManageController::dropTable - Access denied for user: " . ($_SESSION['user'] ?? 'unknown'));
            echo json_encode(['success' => false, 'message' => 'Brak uprawnień do wykonania tej operacji']);
            return;
        }
        
        if (!isset($_POST['table']) || empty($_POST['table'])) {
            echo json_encode(['success' => false, 'message' => 'Nie podano nazwy tabeli']);
            return;
        }
        
        $tableName = $_POST['table'];
        
        try {
            $pdo = $this->getDbConnection();
            
            // Usuń tabelę
            $pdo->exec("DROP TABLE `$tableName`");
            
            echo json_encode([
                'success' => true, 
                'message' => "Usunięto tabelę $tableName"
            ]);
        } catch (PDOException $e) {
            error_log("DatabaseManageController::dropTable - Error: " . $e->getMessage());
            echo json_encode([
                'success' => false, 
                'message' => 'Błąd: ' . $e->getMessage(),
                'errorDetails' => [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ]
            ]);
        }
    }
} 