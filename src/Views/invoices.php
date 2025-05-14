<?php
// Obsługa importu CSV i wstawianie rekordów do tabeli `faktury`
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
        $stmtCols = $pdo->query('DESCRIBE `faktury`');
        $columns = array_column($stmtCols->fetchAll(PDO::FETCH_ASSOC), 'Field');

        // Przygotowanie zapytania
        $colsEscaped = implode(',', array_map(fn($c) => "`" . str_replace('`', '``', $c) . "`", $columns));
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $stmt = $pdo->prepare("INSERT INTO `faktury` ($colsEscaped) VALUES ($placeholders)");

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
    <link rel="shortcut icon" href="../../assets/images/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <?php include_once __DIR__ . '/components/user_info.php'; ?>
    <nav class="cleannav">
        <ul class="cleannav__list">
            <li class="cleannav__item">
                <a href="/" class="cleannav__link" data-tooltip="Strona główna">
                    <i class="fa-solid fa-house cleannav__icon"></i>
                </a>
            </li>
            <li class="cleannav__item">
                <a href="/invoices" class="cleannav__link" data-tooltip="Faktury">
                    <i class="fa-solid fa-file-invoice cleannav__icon"></i>
                </a>
            </li>
            <li class="cleannav__item">
                <a href="/agents" class="cleannav__link" data-tooltip="Dodaj agenta">
                    <i class="fa-solid fa-user-plus cleannav__icon"></i>
                </a>
            </li>
            <li class="cleannav__item">
                <a href="/table" class="cleannav__link" data-tooltip="Tabela z danymi">
                    <i class="fa-solid fa-table cleannav__icon"></i>
                </a>
            </li>
            <li class="cleannav__item">
                <a href="/wizard" class="cleannav__link" data-tooltip="Kreator rekordu">
                    <i class="fa-solid fa-wand-magic-sparkles cleannav__icon"></i>
                </a>
            </li>
            <li class="cleannav__item">
                <a href="/test" class="cleannav__link" data-tooltip="Test">
                    <i class="fa-solid fa-vial cleannav__icon"></i>
                </a>
            </li>
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin'): ?>
            <li class="cleannav__item">
                <a href="/database" class="cleannav__manage-btn" data-tooltip="Zarządzaj bazą">
                    <i class="fa-solid fa-database cleannav__icon"></i>
                </a>
            </li>
            <?php endif; ?>
            <li class="cleannav__item">
                <a href="/logout" class="cleannav__link" data-tooltip="Wyloguj">
                    <i class="fa-solid fa-sign-out-alt cleannav__icon"></i>
                </a>
            </li>
        </ul>
    </nav>
    <header>
        <h1>Podejrzyj Faktury</h1>
    </header>

    <div id="uploadFile">
        <form id="uploadForm" method="POST" enctype="multipart/form-data">
            <label for="file">Wybierz plik (CSV, Excel, OpenDocument):</label>
            <input type="file" id="file" name="file" accept=".csv,.xlsx,.xls,.ods" required>
            <div class="file-tips">
                <p><i class="fa-solid fa-lightbulb"></i> Preferowany format: <strong>CSV</strong> - najbardziej niezawodny</p>
                <p><i class="fa-solid fa-info-circle"></i> Pliki Excel (.xlsx, .xls) wymagają rozszerzenia PHP ZIP na serwerze</p>
                <p><i class="fa-solid fa-shield-alt"></i> Pliki są sprawdzane pod kątem bezpieczeństwa <span class="security-badge">CHRONIONE</span></p>
            </div>
            <button type="submit">
                <i class="fa-solid fa-upload"></i> 
                Importuj do bazy
            </button>
        </form>
    </div>

    <div class="sort-controls">
        <div class="date-filter">
            <div class="filter-group">
                <span class="filter-label">Rok</span>
                <select id="yearSelect">
                    <option value="">Wszystko</option>
                    <option value="2025"selected>2025</option>
                    <option value="2024">2024</option>
                    <option value="2023">2023</option>
                    <option value="2022">2022</option>
                    <option value="2021">2021</option>
                </select>
            </div>
            <div class="filter-group">
                <span class="filter-label">Miesiąc</span>
                <select id="monthSelect">
                    <option value="">Wszystko</option>
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
            </div>
            <button id="applyDateFilter">
                <i class="fa-solid fa-filter"></i>
                Filtruj dane
            </button>
        </div>
        
        <div class="column-visibility">
            <button id="toggleColumnSelector" class="toggle-columns-btn">
                <i class="fa-solid fa-table-columns"></i>
                Pokaż/ukryj kolumny
            </button>
            <div id="columnSelector" class="column-selector">
                <div class="column-selector-header">
                    <span>Widoczność kolumn</span>
                    <button id="closeColumnSelector" class="close-btn"><i class="fa-solid fa-times"></i></button>
                </div>
                <div class="column-selector-body" id="columnList">
                    <!-- Kolumny zostaną dodane dynamicznie przez JavaScript -->
                </div>
                <div class="column-selector-footer">
                    <button id="selectAllColumns" class="btn-small">Zaznacz wszystkie</button>
                    <button id="deselectAllColumns" class="btn-small">Odznacz wszystkie</button>
                </div>
            </div>
        </div>
    </div>

    <section id="dataTable">
        <?php
        try {
            // Pobierz wszystkie kolumny dynamicznie
            $stmtCols = $pdo->prepare('DESCRIBE `faktury`');
            $stmtCols->execute();
            $columns = array_column($stmtCols->fetchAll(PDO::FETCH_ASSOC), 'Field');
            $colsEsc = implode(',', array_map(fn($c) => "`$c`", $columns));
            
            // Ustalamy domyślne sortowanie i pobieranie danych
            $year = $_GET['year'] ?? date('Y');
            $month = $_GET['month'] ?? '';
            
            $whereClause = '';
            $params = [];
            
            if (!empty($year)) {
                $whereClause .= "YEAR(data_wystawienia) = ?";
                $params[] = $year;
            }
            
            if (!empty($month)) {
                if (!empty($whereClause)) {
                    $whereClause .= " AND ";
                }
                $whereClause .= "MONTH(data_wystawienia) = ?";
                $params[] = $month;
            }
            
            if (!empty($whereClause)) {
                $whereClause = "WHERE " . $whereClause;
            }
            
            $sql = "SELECT $colsEsc FROM `faktury` $whereClause ORDER BY data_wystawienia DESC LIMIT 1000";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalInvoices = count($invoices);
        ?>
            <h2>Faktury (<?php echo $totalInvoices; ?>)</h2>
            
            <?php if ($totalInvoices > 0): ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <?php foreach ($columns as $column): ?>
                                <th data-column="<?php echo htmlspecialchars($column); ?>" class="column-<?php echo htmlspecialchars($column); ?>">
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $column))); ?>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <?php foreach ($columns as $column): ?>
                                <td class="column-<?php echo htmlspecialchars($column); ?>">
                                    <?php 
                                    // Formatowanie specjalne dla dat i kwot
                                    if (strpos($column, 'data_') === 0 && !empty($invoice[$column])) {
                                        echo date('Y-m-d', strtotime($invoice[$column]));
                                    } 
                                    else if (strpos($column, 'kwota') === 0 || strpos($column, 'wartosc') === 0) {
                                        echo number_format((float)$invoice[$column], 2, ',', ' ') . ' zł';
                                    }
                                    else if ($column === 'status') {
                                        $statusClass = ($invoice[$column] == 'Opłacona') ? 'status-yes' : 'status-no';
                                        echo '<span class="' . $statusClass . '">' . htmlspecialchars($invoice[$column]) . '</span>';
                                    }
                                    else {
                                        echo htmlspecialchars($invoice[$column] ?? '');
                                    }
                                    ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Brak faktur do wyświetlenia.</p>
            <?php endif; ?>
        <?php
        } catch (PDOException $e) {
            echo '<div class="error-message">Błąd podczas pobierania danych: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Column visibility toggle
            const columnSelector = document.getElementById('columnSelector');
            const toggleColumnSelectorBtn = document.getElementById('toggleColumnSelector');
            const closeColumnSelectorBtn = document.getElementById('closeColumnSelector');
            const selectAllColumnsBtn = document.getElementById('selectAllColumns');
            const deselectAllColumnsBtn = document.getElementById('deselectAllColumns');
            const columnList = document.getElementById('columnList');
            
            // Get all column headers
            const columns = Array.from(document.querySelectorAll('th[data-column]'));
            
            // Essential columns that should not be hidden
            const essentialColumns = ['id_faktury', 'numer_faktury', 'status', 'data_wystawienia', 'wartosc_brutto'];
            
            // Generate checkboxes for each column
            columns.forEach(column => {
                const columnName = column.getAttribute('data-column');
                const isEssential = essentialColumns.includes(columnName);
                
                const label = document.createElement('label');
                label.className = 'column-checkbox-label';
                
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'column-checkbox';
                checkbox.value = columnName;
                checkbox.checked = true; // Default checked
                
                // If column is essential, disable checkbox
                if (isEssential) {
                    checkbox.disabled = true;
                    label.className += ' essential';
                }
                
                checkbox.addEventListener('change', function() {
                    toggleColumnVisibility(columnName, this.checked);
                });
                
                label.appendChild(checkbox);
                label.appendChild(document.createTextNode(' ' + column.textContent.trim()));
                
                columnList.appendChild(label);
            });
            
            // Toggle column visibility function
            function toggleColumnVisibility(columnName, isVisible) {
                const columnCells = document.querySelectorAll(`.column-${columnName}`);
                columnCells.forEach(cell => {
                    cell.style.display = isVisible ? '' : 'none';
                });
            }
            
            // Toggle column selector
            toggleColumnSelectorBtn.addEventListener('click', function() {
                columnSelector.style.display = columnSelector.style.display === 'block' ? 'none' : 'block';
            });
            
            // Close column selector
            closeColumnSelectorBtn.addEventListener('click', function() {
                columnSelector.style.display = 'none';
            });
            
            // Select all columns
            selectAllColumnsBtn.addEventListener('click', function() {
                document.querySelectorAll('.column-checkbox:not(:disabled)').forEach(checkbox => {
                        checkbox.checked = true;
                    toggleColumnVisibility(checkbox.value, true);
                });
            });
            
            // Deselect all non-essential columns
            deselectAllColumnsBtn.addEventListener('click', function() {
                document.querySelectorAll('.column-checkbox:not(:disabled)').forEach(checkbox => {
                        checkbox.checked = false;
                    toggleColumnVisibility(checkbox.value, false);
                });
            });
            
            // Date filtering
            const applyFilterBtn = document.getElementById('applyDateFilter');
            const yearSelect = document.getElementById('yearSelect');
            const monthSelect = document.getElementById('monthSelect');
            
            applyFilterBtn.addEventListener('click', function() {
                const year = yearSelect.value;
                const month = monthSelect.value;
                
                let url = new URL(window.location);
                
                if (year) {
                    url.searchParams.set('year', year);
                } else {
                    url.searchParams.delete('year');
                }
                
                if (month) {
                    url.searchParams.set('month', month);
                } else {
                    url.searchParams.delete('month');
                }
                
                window.location.href = url.toString();
            });
            
            // Initialize values from URL params
            const urlParams = new URLSearchParams(window.location.search);
            const yearParam = urlParams.get('year');
            const monthParam = urlParams.get('month');
            
            if (yearParam) {
                yearSelect.value = yearParam;
            }
            
            if (monthParam) {
                monthSelect.value = monthParam;
            }
            
            // File upload form handling
            const uploadForm = document.getElementById('uploadForm');
            
            uploadForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const submitButton = this.querySelector('button[type="submit"]');
                
                // Disable button and change text
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Przetwarzanie...';
                
                fetch('/invoices', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    // Re-enable button and restore text
                    submitButton.disabled = false;
                    submitButton.innerHTML = '<i class="fa-solid fa-upload"></i> Importuj do bazy';
                    
                    // Refresh page to show new data
                    if (!data.message.includes('Błąd')) {
                        window.location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Wystąpił błąd podczas przetwarzania pliku.');
                    // Re-enable button and restore text
                    submitButton.disabled = false;
                    submitButton.innerHTML = '<i class="fa-solid fa-upload"></i> Importuj do bazy';
                });
            });
        });
    </script>
</body>
</html>