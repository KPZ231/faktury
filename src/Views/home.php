<?php
// Obsługa importu CSV i wstawianie rekordów do tabeli `test`
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $file = $_FILES['file'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    try {
        if ($extension !== 'csv') {
            throw new Exception('Tylko pliki CSV są obsługiwane.');
        }

        $rows = file($file['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$rows) {
            throw new Exception('Plik jest pusty lub błędny.');
        }

        // Pomiń nagłówek
        array_shift($rows);
        $data = array_map('str_getcsv', $rows);

        if (empty($data)) {
            throw new Exception('Brak danych do zaimportowania.');
        }

        // Pobierz nazwy kolumn z tabeli
        $stmtCols = $pdo->query('DESCRIBE `test`');
        $columns = array_column($stmtCols->fetchAll(PDO::FETCH_ASSOC), 'Field');

        // Przygotowanie zapytania
        $colsEscaped = implode(',', array_map(fn($c) => "`" . str_replace('`', '``', $c) . "`", $columns));
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $stmt = $pdo->prepare("INSERT INTO `test` ($colsEscaped) VALUES ($placeholders)");

        $imported = 0;
        $pdo->beginTransaction();

        foreach ($data as $row) {
            if (count($row) < count($columns)) {
                $row = array_pad($row, count($columns), null);
            } elseif (count($row) > count($columns)) {
                $row = array_slice($row, 0, count($columns));
            }

            $stmt->execute($row);
            $imported++;
        }

        $pdo->commit();
        echo json_encode(['message' => "Zaimportowano $imported rekordów."]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['message' => 'Błąd: ' . $e->getMessage()]);
    }
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
    
<!-- Dodaj w <head> strony, aby załadować Font Awesome: -->
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <nav class="cleannav">
        <ul class="cleannav__list">
            <li class="cleannav__item">
                <a href="/" class="cleannav__link">
                    <i class="fa-solid fa-house cleannav__icon"></i>
                    Home
                </a>
            </li>
            <li class="cleannav__item">
                <a href="/agents" class="cleannav__link">
                    <i class="fa-solid fa-plus cleannav__icon"></i>
                    Dodaj Agenta
                </a>
            </li>
            <li class="cleannav__item">
                <a href="/table" class="cleannav__link">
                    <i class="fa-solid fa-briefcase cleannav__icon"></i>
                    Tabela Z Danymi
                </a>
            </li>
            <li class="cleannav__item">
                <a href="/wizard" class="cleannav__link">
                    <i class="fa-solid fa-database cleannav__icon"></i>
                    Kreator Rekordu
                </a>
            </li>
            <li class="cleannav__item">
                <a href="/logout" class="cleannav__link">
                    <i class="fa-solid fa-sign-out-alt cleannav__icon"></i>
                    Wyloguj (<?= htmlspecialchars($_SESSION['user'] ?? 'Gość') ?>)
                </a>
            </li>
        </ul>
    </nav>
    <header>
        <h1>Podejrzyj Faktury</h1>
    </header>

    <div id="uploadFile">
        <form id="uploadForm" method="POST" enctype="multipart/form-data">
            <label for="file">Wybierz plik CSV:</label><br>
            <input type="file" id="file" name="file" accept=".csv" required><br><br>
            <button type="submit">Importuj do bazy</button>
        </form>
    </div>

    <div class="sort-controls">
        <select id="monthSelect">
            <option value="">Wszystkie miesiące</option>
            <option value="01">Styczeń</option>
            <option value="02">Luty</option>
            <option value="03">Marzec</option>
            <option value="04">Kwiecień</option>
            <option value="05">Maj</option>
            <option value="06">Czerwiec</option>
            <option value="07">Lipiec</option>
            <option value="08">Sierpień</option>
            <option value="09">Wrzesień</option>
            <option value="10">Październik</option>
            <option value="11">Listopad</option>
            <option value="12">Grudzień</option>
        </select>
        <button id="sortByAmount">Sortuj po kwocie</button>
    </div>

    <section id="dataTable">
        <?php
        try {
            // Pobierz wszystkie kolumny dynamicznie
            $stmtCols = $pdo->prepare('DESCRIBE `test`');
            $stmtCols->execute();
            $columns = array_column($stmtCols->fetchAll(PDO::FETCH_ASSOC), 'Field');
            $colsEsc = implode(',', array_map(fn($c) => "`$c`", $columns));
            $stmt = $pdo->query("SELECT $colsEsc FROM `test` ORDER BY `LP` DESC");

            if ($stmt->rowCount() > 0) {
                echo '<table><thead><tr>';
                foreach ($columns as $col) {
                    echo '<th class="sortable" data-column="' . htmlspecialchars($col, ENT_QUOTES) . '">' .
                        htmlspecialchars($col, ENT_QUOTES) . '</th>';
                }
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
                const res = await fetch('', {
                    method: 'POST',
                    body: data
                });
                const json = await res.json();
                showNotification(json.message);
                setTimeout(() => location.reload(), 1000);
            } catch {
                showNotification('Błąd podczas importu.');
            }
        });

        function showNotification(msg) {
            const cont = document.getElementById('notificationContainer');
            const n = document.createElement('div');
            n.className = 'notification';
            n.textContent = msg;
            cont.appendChild(n);
            setTimeout(() => n.classList.add('show'), 50);
            setTimeout(() => {
                n.classList.remove('show');
                setTimeout(() => cont.removeChild(n), 500);
            }, 5050);
        }

        // Sortowanie tabeli
        const table = document.querySelector('table');
        const tbody = table.querySelector('tbody');
        const headers = table.querySelectorAll('th.sortable');
        let currentSort = {
            column: null,
            direction: 'asc'
        };

        headers.forEach(header => {
            header.addEventListener('click', () => {
                const column = header.dataset.column;
                const direction = currentSort.column === column && currentSort.direction === 'asc' ? 'desc' : 'asc';

                // Usuń klasy sortowania ze wszystkich nagłówków
                headers.forEach(h => h.classList.remove('asc', 'desc'));

                // Dodaj klasę sortowania do aktualnego nagłówka
                header.classList.add(direction);

                // Sortuj tabelę
                sortTable(column, direction);

                // Zapisz aktualne sortowanie
                currentSort = {
                    column,
                    direction
                };
            });
        });

        function sortTable(column, direction) {
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const index = Array.from(headers).findIndex(h => h.dataset.column === column);

            rows.sort((a, b) => {
                const aValue = a.cells[index].textContent;
                const bValue = b.cells[index].textContent;

                if (direction === 'asc') {
                    return aValue.localeCompare(bValue, 'pl', {
                        numeric: true
                    });
                } else {
                    return bValue.localeCompare(aValue, 'pl', {
                        numeric: true
                    });
                }
            });

            tbody.innerHTML = '';
            rows.forEach(row => tbody.appendChild(row));
        }

        // Sortowanie po kwocie w wybranym miesiącu
        document.getElementById('sortByAmount').addEventListener('click', () => {
            const month = document.getElementById('monthSelect').value;
            const rows = Array.from(tbody.querySelectorAll('tr'));

            // Znajdź indeks kolumny z datą i kwotą
            const dateIndex = Array.from(headers).findIndex(h => h.dataset.column.toLowerCase().includes('data'));
            const amountIndex = Array.from(headers).findIndex(h => h.dataset.column.toLowerCase().includes('kwota'));

            if (dateIndex === -1 || amountIndex === -1) {
                showNotification('Nie znaleziono kolumn z datą lub kwotą.');
                return;
            }

            // Filtruj i sortuj wiersze
            const filteredRows = rows.filter(row => {
                if (!month) return true;
                const date = row.cells[dateIndex].textContent;
                return date.includes(`-${month}-`);
            });

            filteredRows.sort((a, b) => {
                const aAmount = parseFloat(a.cells[amountIndex].textContent.replace(/[^0-9.-]+/g, '')) || 0;
                const bAmount = parseFloat(b.cells[amountIndex].textContent.replace(/[^0-9.-]+/g, '')) || 0;
                return bAmount - aAmount; // Od największej do najmniejszej
            });

            tbody.innerHTML = '';
            filteredRows.forEach(row => tbody.appendChild(row));

            showNotification(`Posortowano po kwocie${month ? ` dla miesiąca ${month}` : ''}`);
        });
    </script>
</body>

</html>