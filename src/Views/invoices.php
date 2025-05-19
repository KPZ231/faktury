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
                <a href="/wizard" class="cleannav__link" data-tooltip="Kreator rekordu">
                    <i class="fa-solid fa-wand-magic-sparkles cleannav__icon"></i>
                </a>
            </li>
            <li class="cleannav__item">
                <a href="/podsumowanie-spraw" class="cleannav__link" data-tooltip="Podsumowanie spraw">
                    <i class="fa-solid fa-clipboard-list cleannav__icon"></i>
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
        <form id="uploadForm" method="POST" enctype="multipart/form-data" accept-charset="UTF-8">
            <label for="file">Wybierz plik (CSV, Excel, OpenDocument):</label>
            <input type="file" id="file" name="file" accept=".csv,.xlsx,.xls,.ods" required>
            <div class="file-tips">
                <p><i class="fa-solid fa-lightbulb"></i> Preferowany format: <strong>CSV</strong> - najbardziej niezawodny</p>
                <p><i class="fa-solid fa-info-circle"></i> Pliki Excel (.xlsx, .xls) wymagają rozszerzenia PHP ZIP na serwerze</p>
                <p><i class="fa-solid fa-shield-alt"></i> Pliki są sprawdzane pod kątem bezpieczeństwa <span class="security-badge">CHRONIONE</span></p>
                <p><i class="fa-solid fa-exclamation-triangle"></i> <strong>Ważne:</strong> Upewnij się, że nazwy kolumn zawierają polskie znaki (ą, ć, ę, itp.)</p>
            </div>
            <button type="submit">
                <i class="fa-solid fa-upload"></i> 
                Importuj do bazy
            </button>
        </form>
    </div>

    <div class="sort-controls">
        <div class="filter-actions">
            <button id="resetFilters" class="btn btn-secondary" title="Wyczyść wszystkie filtry i sortowanie">
                <i class="fas fa-undo"></i> Wyczyść filtry
            </button>
        </div>
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
            $sortColumn = $_GET['sort'] ?? 'Data wystawienia';
            $sortDirection = $_GET['dir'] ?? 'DESC';
            
            $whereClause = '';
            $params = [];
            
            if (!empty($year)) {
                $whereClause .= "YEAR(`Data wystawienia`) = ?";
                $params[] = $year;
            }
            
            if (!empty($month)) {
                if (!empty($whereClause)) {
                    $whereClause .= " AND ";
                }
                $whereClause .= "MONTH(`Data wystawienia`) = ?";
                $params[] = $month;
            }
            
            if (!empty($whereClause)) {
                $whereClause = "WHERE " . $whereClause;
            }
            
            // Przygotuj kolumnę sortowania (escapowanie dla bezpieczeństwa)
            $safeColumn = "`" . str_replace("`", "``", $sortColumn) . "`";
            $safeDirection = ($sortDirection === 'ASC') ? 'ASC' : 'DESC';
            
            $sql = "SELECT $colsEsc FROM `faktury` $whereClause ORDER BY $safeColumn $safeDirection LIMIT 1000";
            
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
                                <?php $columnClass = preg_replace('/[^a-zA-Z0-9]/', '-', $column); ?>
                                <th data-column="<?php echo htmlspecialchars($column); ?>" data-column-class="<?php echo htmlspecialchars($columnClass); ?>" class="column-<?php echo htmlspecialchars($columnClass); ?> sortable">
                                    <div class="column-header">
                                        <span class="column-title"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $column))); ?></span>
                                        <span class="sort-icon <?php echo ($sortColumn === $column) ? ($sortDirection === 'ASC' ? 'sort-asc' : 'sort-desc') : ''; ?>">
                                            <i class="fa-solid fa-sort"></i>
                                        </span>
                                    </div>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <?php foreach ($columns as $column): ?>
                                <?php $columnClass = preg_replace('/[^a-zA-Z0-9]/', '-', $column); ?>
                                <td class="column-<?php echo htmlspecialchars($columnClass); ?>">
                                    <?php 
                                    // Formatowanie specjalne dla dat i kwot
                                    if (strpos(strtolower($column), 'data') === 0 && !empty($invoice[$column])) {
                                        echo date('Y-m-d', strtotime($invoice[$column]));
                                    } 
                                    else if (strpos(strtolower($column), 'kwota') === 0 || 
                                            strpos(strtolower($column), 'wartosc') === 0 || 
                                            strpos(strtolower($column), 'wartość') === 0) {
                                        echo number_format((float)$invoice[$column], 2, ',', ' ') . ' zł';
                                    }
                                    else if (strtolower($column) === 'status') {
                                        $statusClass = strtolower($invoice[$column]) == 'opłacona' ? 'status-yes' : 'status-no';
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

    <style>
        /* Style dla kolumn z możliwością sortowania */
        .sortable {
            cursor: pointer;
        }
        
        .column-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .sort-icon {
            display: inline-flex;
            margin-left: 5px;
            color: #aaa;
            transition: transform 0.2s;
        }
        
        .sort-asc .fa-sort:before {
            content: "\f0de"; /* fa-sort-up */
            color: var(--primary);
        }
        
        .sort-desc .fa-sort:before {
            content: "\f0dd"; /* fa-sort-down */
            color: var(--primary);
        }
        
        /* Style dla ukrywania kolumn */
        .hidden-column {
            display: none !important;
        }
        
        /* Style dla selektora kolumn */
        .column-selector {
            display: none;
            position: absolute;
            right: 20px;
            top: 180px;
            background-color: white;
            border: 1px solid var(--border);
            border-radius: 6px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            width: 250px;
            max-height: 80vh;
            overflow-y: auto;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Column visibility toggle
            const columnSelector = document.getElementById('columnSelector');
            const toggleColumnSelectorBtn = document.getElementById('toggleColumnSelector');
            const closeColumnSelectorBtn = document.getElementById('closeColumnSelector');
            const selectAllColumnsBtn = document.getElementById('selectAllColumns');
            const deselectAllColumnsBtn = document.getElementById('deselectAllColumns');
            const columnList = document.getElementById('columnList');
            
            // Create counter for visible columns
            const columnCounter = document.createElement('span');
            columnCounter.className = 'column-counter';
            columnCounter.style.marginLeft = '8px';
            columnCounter.style.fontSize = '0.85rem';
            columnCounter.style.color = 'var(--text-secondary)';
            toggleColumnSelectorBtn.appendChild(columnCounter);
            
            // Get all column headers
            const columns = Array.from(document.querySelectorAll('th[data-column]'));
            
            // Essential columns that should not be hidden
            const essentialColumns = ['numer', 'Data wystawienia', 'Wartość brutto', 'Nabywca'];
            
            // Load saved column visibility preferences
            let columnVisibility = {};
            try {
                const savedVisibility = localStorage.getItem('invoiceColumnVisibility');
                if (savedVisibility) {
                    columnVisibility = JSON.parse(savedVisibility);
                }
            } catch (e) {
                console.error('Error loading saved column visibility:', e);
                columnVisibility = {};
            }
            
            // Generate checkboxes for each column
            columns.forEach(column => {
                const columnName = column.getAttribute('data-column');
                const columnClass = column.getAttribute('data-column-class') || columnName.replace(/[^a-zA-Z0-9]/g, '-');
                const isEssential = essentialColumns.includes(columnName);
                
                const label = document.createElement('label');
                label.className = 'column-checkbox-label';
                
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'column-checkbox';
                checkbox.value = columnName;
                checkbox.setAttribute('data-column-class', columnClass);
                
                // Set initial checkbox state based on saved preferences or defaults
                let isChecked = true; // Default visible
                
                // Log the essential columns for debugging
                if (essentialColumns.includes(columnName)) {
                    console.log(`Column ${columnName} is essential`);
                }
                
                if (isEssential) {
                    isChecked = true; // Essential columns always visible
                    checkbox.disabled = true;
                    label.className += ' essential';
                } else if (columnVisibility.hasOwnProperty(columnName)) {
                    isChecked = columnVisibility[columnName];
                    console.log(`Loading saved visibility for ${columnName}: ${isChecked}`);
                }
                
                checkbox.checked = isChecked;
                
                // Apply initial visibility
                if (!isChecked) {
                    // Apply hidden class immediately
                    document.querySelectorAll(`.column-${columnClass}`).forEach(cell => {
                        cell.classList.add('hidden-column');
                    });
                }
                
                checkbox.addEventListener('change', function() {
                    toggleColumnVisibility(columnName, columnClass, this.checked);
                    saveColumnVisibility();
                    updateColumnCounter();
                });
                
                label.appendChild(checkbox);
                // Pobierz tylko tekst tytułu kolumny, ignorując ikonę sortowania
                const columnTitle = column.querySelector('.column-title');
                label.appendChild(document.createTextNode(' ' + (columnTitle ? columnTitle.textContent.trim() : column.textContent.trim())));
                
                columnList.appendChild(label);
            });
            
            // Toggle column visibility function
            function toggleColumnVisibility(columnName, columnClass, isVisible) {
                // Select both header and data cells with this column class
                const columnCells = document.querySelectorAll(`.column-${columnClass}`);
                console.log(`Toggling visibility for column ${columnName} (class: column-${columnClass}): ${isVisible ? 'show' : 'hide'}, found ${columnCells.length} cells`);
                
                if (columnCells.length === 0) {
                    console.warn(`No elements found with class .column-${columnName}`);
                    return;
                }
                
                columnCells.forEach(cell => {
                    if (isVisible) {
                        cell.classList.remove('hidden-column');
                    } else {
                        cell.classList.add('hidden-column');
                    }
                });
            }
            
            // Save column visibility preferences to localStorage
            function saveColumnVisibility() {
                const prefs = {};
                document.querySelectorAll('.column-checkbox:not(:disabled)').forEach(checkbox => {
                    prefs[checkbox.value] = checkbox.checked;
                });
                try {
                    localStorage.setItem('invoiceColumnVisibility', JSON.stringify(prefs));
                } catch (e) {
                    console.error('Failed to save column visibility:', e);
                }
            }
            
            // Update column counter
            function updateColumnCounter() {
                const totalColumns = columns.length;
                // Count visible columns by using the checkbox state instead of DOM elements
                const visibleColumns = Array.from(document.querySelectorAll('.column-checkbox'))
                    .filter(checkbox => checkbox.checked).length;
                columnCounter.textContent = `(${visibleColumns}/${totalColumns})`;
            }
            
            // Do an initial count after setup
            setTimeout(updateColumnCounter, 100);
            
            // Add a debug function to check for issues
            function debugColumnVisibility() {
                console.group('Column Visibility Debug');
                const allColumns = Array.from(document.querySelectorAll('.column-checkbox'));
                console.log(`Total columns: ${allColumns.length}`);
                
                // Check which columns are checked but might have hidden elements
                const checkedButHidden = allColumns
                    .filter(checkbox => checkbox.checked)
                    .map(checkbox => {
                        const name = checkbox.value;
                        const cells = document.querySelectorAll(`.column-${name}.hidden-column`);
                        return { name, hiddenCount: cells.length };
                    })
                    .filter(info => info.hiddenCount > 0);
                
                if (checkedButHidden.length > 0) {
                    console.warn('Columns checked but with hidden elements:', checkedButHidden);
                }
                
                // Check which columns are unchecked but might have visible elements
                const uncheckedButVisible = allColumns
                    .filter(checkbox => !checkbox.checked && !checkbox.disabled)
                    .map(checkbox => {
                        const name = checkbox.value;
                        const cells = document.querySelectorAll(`.column-${name}:not(.hidden-column)`);
                        return { name, visibleCount: cells.length };
                    })
                    .filter(info => info.visibleCount > 0);
                
                if (uncheckedButVisible.length > 0) {
                    console.warn('Columns unchecked but with visible elements:', uncheckedButVisible);
                }
                
                console.groupEnd();
            }
            
            // Reset all column visibility first to ensure clean state
            function resetColumnVisibility() {
                console.log('Resetting column visibility');
                // First show all columns
                document.querySelectorAll('th, td').forEach(cell => {
                    cell.classList.remove('hidden-column');
                });
                
                // Jeśli używamy lokalnego przechowywania, to sprawdzamy stan checkboxów
                document.querySelectorAll('.column-checkbox').forEach(checkbox => {
                    const columnName = checkbox.value;
                    const isChecked = checkbox.checked;
                    
                    console.log(`Column ${columnName}: ${isChecked ? 'visible' : 'hidden'}`);
                    
                    // Jeśli checkbox nie jest zaznaczony, ukryj odpowiednie komórki
                    if (!isChecked) {
                        const columnClass = checkbox.getAttribute('data-column-class') || columnName.replace(/[^a-zA-Z0-9]/g, '-');
                        document.querySelectorAll(`.column-${columnClass}`).forEach(cell => {
                            cell.classList.add('hidden-column');
                        });
                    }
                });
                
                updateColumnCounter();
            }
            
            // Call reset after initialization to ensure consistent state
            setTimeout(resetColumnVisibility, 200);
            
            // Add special case for column selector toggle to reset state
            toggleColumnSelectorBtn.addEventListener('click', function() {
                if (columnSelector.style.display === 'block') {
                    columnSelector.style.display = 'none';
                } else {
                    // Reset column visibility when opening the selector
                    resetColumnVisibility();
                    columnSelector.style.display = 'block';
                    // Position the popup based on viewport size
                    if (window.innerWidth < 768) {
                        columnSelector.style.right = '0';
                    }
                }
            });
            
            // Close column selector
            closeColumnSelectorBtn.addEventListener('click', function() {
                columnSelector.style.display = 'none';
            });
            
            // Close popup when clicking outside
            document.addEventListener('click', function(event) {
                if (!columnSelector.contains(event.target) && 
                    event.target !== toggleColumnSelectorBtn && 
                    !toggleColumnSelectorBtn.contains(event.target) &&
                    columnSelector.style.display === 'block') {
                    columnSelector.style.display = 'none';
                }
            });
            
            // Select all columns
            selectAllColumnsBtn.addEventListener('click', function() {
                document.querySelectorAll('.column-checkbox:not(:disabled)').forEach(checkbox => {
                        checkbox.checked = true;
                });
                saveColumnVisibility();
                resetColumnVisibility();
            });
            
            // Deselect all non-essential columns
            deselectAllColumnsBtn.addEventListener('click', function() {
                document.querySelectorAll('.column-checkbox:not(:disabled)').forEach(checkbox => {
                        checkbox.checked = false;
                });
                saveColumnVisibility();
                resetColumnVisibility();
            });
            
            // Date filtering
            const applyFilterBtn = document.getElementById('applyDateFilter');
            const yearSelect = document.getElementById('yearSelect');
            const monthSelect = document.getElementById('monthSelect');
            const searchInput = document.getElementById('searchInput');
            const resetFiltersBtn = document.getElementById('resetFilters');

            // Reset all filters and sorting
            function resetAllFilters() {
                // Reset year and month selects
                if (yearSelect) yearSelect.value = '';
                if (monthSelect) monthSelect.value = '';
                
                // Reset search input
                if (searchInput) searchInput.value = '';
                
                // Reset URL parameters
                const url = new URL(window.location.href);
                const params = new URLSearchParams(url.search);
                
                // Remove all filter and sort parameters
                ['year', 'month', 'search', 'sort', 'dir'].forEach(param => {
                    params.delete(param);
                });
                
                // Redirect to clean URL
                window.location.href = url.pathname + (params.toString() ? '?' + params.toString() : '');
            }
            
            // Add click event for reset button
            if (resetFiltersBtn) {
                resetFiltersBtn.addEventListener('click', resetAllFilters);
            }
            
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
            const sortParam = urlParams.get('sort');
            const dirParam = urlParams.get('dir');
            
            // Initialize sorting functionality
            initSorting(sortParam, dirParam);
            
            // Table column sorting functionality
            function initSorting(currentSortColumn, currentSortDirection) {
                const sortableHeaders = document.querySelectorAll('th.sortable');
                
                sortableHeaders.forEach(header => {
                    header.addEventListener('click', function() {
                        const column = this.getAttribute('data-column');
                        let direction = 'ASC';
                        
                        // If already sorting by this column, toggle direction
                        if (column === currentSortColumn) {
                            direction = currentSortDirection === 'ASC' ? 'DESC' : 'ASC';
                        }
                        
                        // Create URL with sort parameters
                        let url = new URL(window.location);
                        url.searchParams.set('sort', column);
                        url.searchParams.set('dir', direction);
                        
                        // Preserve any existing filter parameters
                        window.location.href = url.toString();
                    });
                });
            }
            
            if (yearParam) {
                yearSelect.value = yearParam;
            }
            
            if (monthParam) {
                monthSelect.value = monthParam;
            }
            
            // File upload form handling
            const uploadForm = document.getElementById('uploadForm');
            const resultContainer = document.createElement('div');
            resultContainer.className = 'import-result';
            resultContainer.style.display = 'none';
            uploadForm.parentNode.appendChild(resultContainer);
            
            uploadForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const submitButton = this.querySelector('button[type="submit"]');
                
                // Clear previous results
                resultContainer.innerHTML = '';
                resultContainer.style.display = 'none';
                
                // Disable button and change text
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Przetwarzanie...';
                
                fetch('/invoices', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Re-enable button and restore text
                    submitButton.disabled = false;
                    submitButton.innerHTML = '<i class="fa-solid fa-upload"></i> Importuj do bazy';
                    
                    // Display result with proper formatting
                    if (data.message && data.message.includes('Błąd')) {
                        const errorMessages = data.message.split('\n').map(line => 
                            line ? `<p>${line}</p>` : '<br>'
                        ).join('');
                        
                        resultContainer.innerHTML = `
                            <div class="import-error">
                                <i class="fa-solid fa-exclamation-triangle"></i>
                                <div>${errorMessages}</div>
                            </div>
                        `;
                    } else {
                        resultContainer.innerHTML = `
                            <div class="import-success">
                                <i class="fa-solid fa-check-circle"></i>
                                <p>${data.message}</p>
                            </div>
                        `;
                        
                        // Refresh page to show new data after successful import
                        setTimeout(() => {
                        window.location.reload();
                        }, 2000);
                    }
                    
                    resultContainer.style.display = 'block';
                    // Scroll to results
                    resultContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Re-enable button and restore text
                    submitButton.disabled = false;
                    submitButton.innerHTML = '<i class="fa-solid fa-upload"></i> Importuj do bazy';
                    
                    resultContainer.innerHTML = `
                        <div class="import-error">
                            <i class="fa-solid fa-exclamation-triangle"></i>
                            <p>Wystąpił błąd podczas przetwarzania pliku: ${error.message}</p>
                        </div>
                    `;
                    resultContainer.style.display = 'block';
                    resultContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                });
            });
        });
    </script>
</body>
</html>