<?php
require_once __DIR__ . '/../../config/database.php';

// Obsługa importu CSV i wstawianie rekordów do tabeli `test`
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $tmp = $_FILES['file']['tmp_name'];
    $rows = file($tmp, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$rows) {
        echo json_encode(['message' => 'Plik jest pusty lub błędny.']);
        exit;
    }
    // Pomiń nagłówek
    $header = str_getcsv(array_shift($rows));
    // Lista kolumn w tej samej kolejności co nagłówek
    $columns = $header;
    // Przygotowanie zapytania
    $colsEscaped = implode(',', array_map(fn($c) => "`" . str_replace('`', '``', $c) . "`", $columns));
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $stmt = $pdo->prepare("INSERT INTO `test` ($colsEscaped) VALUES ($placeholders)");
    $imported = 0;
    $pdo->beginTransaction();
    foreach ($rows as $line) {
        $fields = str_getcsv($line);
        $countFields = count($fields);
        $countCols = count($columns);
        if ($countFields < $countCols) {
            $fields = array_pad($fields, $countCols, null);
        } elseif ($countFields > $countCols) {
            $fields = array_slice($fields, 0, $countCols);
        }
        $stmt->execute($fields);
        $imported++;
    }
    $pdo->commit();
    echo json_encode(['message' => "Zaimportowano $imported rekordów."]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <!-- <base href="/zestawienie11/"> -->
    <title>Podejrzyj Faktury</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .notification { position: fixed; top: 20px; right: -100%; padding: 20px; background: #323232; color: #fff; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); max-width: 300px; font-family: sans-serif; transition: right 0.5s ease-out; z-index:1000; }
        .notification.show { right: 20px; }
        table { width: 95%; margin: 30px auto; border-collapse: collapse; }
        table, th, td { border: 1px solid #ccc; }
        th, td { padding: 8px; text-align: center; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <header><h1>Podejrzyj Faktury</h1></header>

    <div id="uploadFile">
        <form id="uploadForm" method="POST" enctype="multipart/form-data">
            <label for="file">Wybierz plik CSV:</label><br>
            <input type="file" id="file" name="file" accept=".csv" required><br><br>
            <button type="submit">Importuj do bazy</button>
        </form>
    </div>

    <section id="dataTable">
        <?php
        try {
            // Pobierz wszystkie kolumny dynamicznie
            $stmtCols = $pdo->query('DESCRIBE `test`');
            $columns = array_column($stmtCols->fetchAll(PDO::FETCH_ASSOC), 'Field');
            $colsEsc = implode(',', array_map(fn($c) => "`$c`", $columns));
            $stmt = $pdo->query("SELECT $colsEsc FROM `test` ORDER BY `LP` DESC");

            if ($stmt->rowCount() > 0) {
                echo '<table><thead><tr>';
                foreach ($columns as $col) echo '<th>' . htmlspecialchars($col, ENT_QUOTES) . '</th>';
                echo '</tr></thead><tbody>';
                while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                    echo '<tr>';
                    foreach ($row as $cell) echo '<td>' . htmlspecialchars($cell, ENT_QUOTES) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p style="text-align:center;">Brak danych.</p>';
            }
        } catch (PDOException $e) {
            echo '<p style="color:red; text-align:center;">Błąd: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>';
        }
        ?>
    </section>

    <div id="notificationContainer"></div>
    <script>
        document.getElementById('uploadForm').addEventListener('submit', async e => {
            e.preventDefault();
            const data = new FormData(e.currentTarget);
            try {
                const res = await fetch('', { method:'POST', body:data });
                const json = await res.json();
                showNotification(json.message);
                // odśwież tabelę po imporcie
                setTimeout(() => location.reload(), 1000);
            } catch {
                showNotification('Błąd podczas importu.');
            }
        });
        function showNotification(msg) {
            const cont = document.getElementById('notificationContainer');
            const n = document.createElement('div');n.className='notification';n.textContent=msg;cont.appendChild(n);
            setTimeout(() => n.classList.add('show'),50);
            setTimeout(() =>{n.classList.remove('show');setTimeout(()=>cont.removeChild(n),500);},5050);
        }
    </script>
</body>
</html>
