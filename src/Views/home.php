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
            $stmtCols = $pdo->prepare('DESCRIBE `test`');
            $stmtCols->execute();
            $columns = array_column($stmtCols->fetchAll(PDO::FETCH_ASSOC), 'Field');
            $colsEsc = implode(',', array_map(fn($c) => "`$c`", $columns));
            $stmt = $pdo->query("SELECT $colsEsc FROM `test` ORDER BY `LP` DESC");

            if ($stmt->rowCount() > 0) {
                echo '<table class="data-table"><thead><tr>';
                foreach ($columns as $col) {
                    echo '<th class="sortable" data-column="' . htmlspecialchars($col, ENT_QUOTES) . '">' .
                        htmlspecialchars($col, ENT_QUOTES) . '</th>';
                }
                echo '</tr></thead><tbody>';
                while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                    echo '<tr>';
                    foreach ($row as $index => $cell) {
                        // Sprawdź czy to kolumna kwota i zastosuj odpowiednią klasę
                        $className = '';
                        if (strtolower($columns[$index]) === 'kwota' || 
                            strpos(strtolower($columns[$index]), 'kwot') !== false) {
                            $className = 'currency';
                        }
                        
                        echo '<td' . ($className ? ' class="'.$className.'"' : '') . '>' . 
                             htmlspecialchars($cell, ENT_QUOTES) . '</td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<div class="no-data">
                        <i class="fa-solid fa-database fa-3x"></i>
                        <p>Brak danych. Zaimportuj plik, aby wyświetlić dane.</p>
                      </div>';
            }
        } catch (PDOException $e) {
            echo '<div class="error-message">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <p>Błąd: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>
                  </div>';
        }
        ?>
    </section>

    <div id="notificationContainer"></div>
    <script>
        <?php if (isset($_GET['access_denied'])): ?>
        // Show access denied notification when redirected from a restricted page
        document.addEventListener('DOMContentLoaded', function() {
            showNotification('Brak dostępu do żądanej strony. Ta funkcja wymaga uprawnień superadmin.', 'error');
        });
        <?php endif; ?>
        
        document.getElementById('uploadForm').addEventListener('submit', async e => {
            e.preventDefault();
            
            // Walidacja pliku po stronie klienta
            const fileInput = document.getElementById('file');
            if (!fileInput.files || !fileInput.files[0]) {
                showNotification('Proszę wybrać plik do zaimportowania.', 'error');
                return;
            }
            
            const file = fileInput.files[0];
            const fileName = file.name || '';
            const fileExt = fileName.split('.').pop().toLowerCase();
            const allowedExtensions = ['csv', 'xlsx', 'xls', 'ods'];
            
            if (!allowedExtensions.includes(fileExt)) {
                showExtendedError(`Nieobsługiwany format pliku: ${fileExt}`, 
                                  `Dozwolone formaty: ${allowedExtensions.join(', ')}`);
                return;
            }
            
            // Sprawdź rozmiar pliku (max 10MB)
            const maxFileSize = 10 * 1024 * 1024; // 10MB
            if (file.size > maxFileSize) {
                showExtendedError('Plik jest zbyt duży', 
                                 `Maksymalny rozmiar pliku: 10MB. Wybrany plik ma ${(file.size / (1024 * 1024)).toFixed(2)}MB`);
                return;
            }
            
            const data = new FormData(e.currentTarget);
            
            try {
                showNotification('Trwa przetwarzanie pliku...' + (fileExt !== 'csv' ? ' Konwersja do formatu CSV.' : ''));
                
                const res = await fetch('', {
                    method: 'POST',
                    body: data
                });
                
                if (!res.ok) {
                    throw new Error(`Błąd serwera: ${res.status} ${res.statusText}`);
                }
                
                const json = await res.json();
                
                // Sprawdź czy to błąd związany z ZipArchive
                if (json.message && json.message.includes('brakuje rozszerzenia PHP')) {
                    showExtendedError(sanitizeHTML(json.message));
                } 
                // Sprawdź czy to błąd związany z brakiem kolumny "numer"
                else if (json.message && json.message.includes('Nie znaleziono kolumny z numerem faktury')) {
                    showExtendedError(sanitizeHTML(json.message), sanitizeHTML(json.details));
                }
                else {
                    showNotification(sanitizeHTML(json.message));
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (error) {
                console.error('Import error:', error);
                showNotification('Błąd podczas importu: ' + sanitizeHTML(error.message), 'error');
            }
        });

        // Sanityzacja HTML do ochrony przed XSS
        function sanitizeHTML(text) {
            if (!text) return '';
            const element = document.createElement('div');
            element.textContent = text;
            return element.innerHTML;
        }

        function showNotification(msg, type = 'info') {
            const cont = document.getElementById('notificationContainer');
            const n = document.createElement('div');
            n.className = `notification ${type}`;
            
            const icon = type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
            n.innerHTML = `<i class="fa-solid ${icon}"></i> ${msg}`;
            
            cont.appendChild(n);
            setTimeout(() => n.classList.add('show'), 50);
            setTimeout(() => {
                n.classList.remove('show');
                setTimeout(() => n.remove(), 500);
            }, type === 'error' ? 7000 : 5050); // Dłuższy czas dla błędów
        }
        
        // Funkcja do wyświetlania rozszerzonych błędów
        function showExtendedError(msg, details) {
            // Usuń istniejący komunikat o błędzie, jeśli istnieje
            const existing = document.getElementById('extendedErrorMessage');
            if (existing) {
                existing.remove();
            }
            
            // Utwórz nowy element błędu
            const errorDiv = document.createElement('div');
            errorDiv.id = 'extendedErrorMessage';
            errorDiv.className = 'extended-error';
            
            let detailsHtml = '';
            if (details) {
                detailsHtml = `
                    <div class="error-details">
                        <p><strong>Szczegóły:</strong></p>
                        <div class="details-content">${details}</div>
                    </div>
                `;
            }
            
            errorDiv.innerHTML = `
                <div class="error-header">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                    <h3>Błąd przetwarzania pliku</h3>
                </div>
                <p>${msg}</p>
                ${detailsHtml}
                <div class="error-instructions">
                    <p><strong>Jak rozwiązać problem:</strong></p>
                    <ol>
                        <li>Upewnij się, że plik zawiera kolumnę z numerem faktury (może być nazwana "Numer", "Nr faktury", itp.)</li>
                        <li>Sprawdź, czy pierwszy wiersz zawiera prawidłowe nagłówki kolumn</li>
                        <li>Zapisz plik w formacie CSV i spróbuj ponownie</li>
                    </ol>
                </div>
                <button onclick="this.parentNode.remove();" class="btn-close">
                    <i class="fa-solid fa-times"></i> Zamknij
                </button>
            `;
            
            // Umieść element na stronie
            const uploadForm = document.getElementById('uploadFile');
            uploadForm.parentNode.insertBefore(errorDiv, uploadForm.nextSibling);
            
            // Pokaż powiadomienie
            showNotification('Wykryto problem z formatem pliku', 'error');
        }

        // Sortowanie tabeli
        const table = document.querySelector('table');
        if (table) {
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
                    
                    // Pokaż informację o sortowaniu
                    showNotification(`Posortowano według ${column} ${direction === 'asc' ? 'rosnąco' : 'malejąco'}`);
                });
            });

            function sortTable(column, direction) {
                const rows = Array.from(tbody.querySelectorAll('tr'));
                const index = Array.from(headers).findIndex(h => h.dataset.column === column);

                rows.sort((a, b) => {
                    const aValue = a.cells[index].textContent;
                    const bValue = b.cells[index].textContent;

                    // Sprawdź, czy wartości mogą być liczbami
                    const aNum = parseFloat(aValue.replace(/[^0-9.-]+/g, ''));
                    const bNum = parseFloat(bValue.replace(/[^0-9.-]+/g, ''));

                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return direction === 'asc' ? aNum - bNum : bNum - aNum;
                    } else {
                        if (direction === 'asc') {
                            return aValue.localeCompare(bValue, 'pl', { numeric: true });
                        } else {
                            return bValue.localeCompare(aValue, 'pl', { numeric: true });
                        }
                    }
                });

                tbody.innerHTML = '';
                rows.forEach(row => tbody.appendChild(row));
            }
        }

        // Po załadowaniu strony zapisz oryginalne dane tabeli
        let originalTableRows = [];
        document.addEventListener('DOMContentLoaded', () => {
            const table = document.querySelector('table');
            if (table && table.querySelector('tbody')) {
                originalTableRows = Array.from(table.querySelectorAll('tbody tr'));
                
                // Inicjalizacja przełącznika widoczności kolumn
                initColumnVisibility(table);
            }
        });
        
        // Funkcja inicjalizująca system pokazywania/ukrywania kolumn
        function initColumnVisibility(table) {
            if (!table) return;
            
            const headers = Array.from(table.querySelectorAll('th'));
            const columnList = document.getElementById('columnList');
            const columnSelector = document.getElementById('columnSelector');
            const toggleButton = document.getElementById('toggleColumnSelector');
            const closeButton = document.getElementById('closeColumnSelector');
            const selectAllBtn = document.getElementById('selectAllColumns');
            const deselectAllBtn = document.getElementById('deselectAllColumns');
            
            // Sprawdź, czy istnieje zapis widoczności kolumn w localStorage
            let columnVisibility = {};
            const savedVisibility = localStorage.getItem('columnVisibility');
            
            if (savedVisibility) {
                try {
                    columnVisibility = JSON.parse(savedVisibility);
                } catch (e) {
                    console.error('Błąd odczytu zapisanych ustawień kolumn:', e);
                    columnVisibility = {};
                }
            }
            
            // Utwórz listę kolumn z checkboxami
            headers.forEach((header, index) => {
                const columnName = header.textContent.trim();
                const columnId = `column-${index}`;
                
                // Utwórz element z checkboxem
                const item = document.createElement('div');
                item.className = 'column-checkbox';
                item.dataset.columnIndex = index;
                
                // Sprawdź, czy kolumna powinna być widoczna
                const isVisible = columnVisibility[columnId] !== false; // domyślnie wszystkie są widoczne
                
                // Jeśli kolumna jest ukryta, dodaj odpowiednią klasę do elementów tabeli
                if (!isVisible) {
                    applyColumnVisibility(table, index, false);
                    item.classList.add('hidden-column');
                }
                
                item.innerHTML = `
                    <input type="checkbox" id="${columnId}" 
                           data-column-index="${index}" 
                           ${isVisible ? 'checked' : ''}>
                    <label for="${columnId}">${columnName}</label>
                `;
                
                // Obsługa kliknięcia w checkbox
                const checkbox = item.querySelector('input');
                checkbox.addEventListener('change', function() {
                    const isChecked = this.checked;
                    const columnIndex = parseInt(this.dataset.columnIndex);
                    
                    // Zapisz stan widoczności
                    columnVisibility[columnId] = isChecked;
                    localStorage.setItem('columnVisibility', JSON.stringify(columnVisibility));
                    
                    // Zaktualizuj wygląd elementu w selectorze
                    if (isChecked) {
                        item.classList.remove('hidden-column');
                    } else {
                        item.classList.add('hidden-column');
                    }
                    
                    // Aktualizuj widoczność kolumn w tabeli
                    applyColumnVisibility(table, columnIndex, isChecked);
                    
                    // Pokaż powiadomienie
                    showNotification(`Kolumna "${columnName}" została ${isChecked ? 'pokazana' : 'ukryta'}`);
                });
                
                // Obsługa kliknięcia w cały obszar (dla wygody)
                item.addEventListener('click', function(e) {
                    if (e.target !== checkbox) {
                        checkbox.checked = !checkbox.checked;
                        checkbox.dispatchEvent(new Event('change'));
                    }
                });
                
                columnList.appendChild(item);
            });
            
            // Obsługa przycisku "Pokaż/ukryj kolumny"
            toggleButton.addEventListener('click', function() {
                columnSelector.classList.toggle('show');
            });
            
            // Obsługa przycisku zamknięcia
            closeButton.addEventListener('click', function() {
                columnSelector.classList.remove('show');
            });
            
            // Zamknij selektor po kliknięciu poza nim
            document.addEventListener('click', function(e) {
                if (!columnSelector.contains(e.target) && e.target !== toggleButton) {
                    columnSelector.classList.remove('show');
                }
            });
            
            // Obsługa przycisków "Zaznacz wszystkie" i "Odznacz wszystkie"
            selectAllBtn.addEventListener('click', function() {
                const checkboxes = columnList.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(checkbox => {
                    if (!checkbox.checked) {
                        checkbox.checked = true;
                        checkbox.dispatchEvent(new Event('change'));
                    }
                });
            });
            
            deselectAllBtn.addEventListener('click', function() {
                const checkboxes = columnList.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        checkbox.checked = false;
                        checkbox.dispatchEvent(new Event('change'));
                    }
                });
            });
        }
        
        // Funkcja aktualizująca widoczność kolumny w tabeli
        function applyColumnVisibility(table, columnIndex, isVisible) {
            if (!table) return;
            
            // Ukryj/pokaż nagłówek
            const header = table.querySelector(`th:nth-child(${columnIndex + 1})`);
            if (header) {
                if (isVisible) {
                    header.classList.remove('hidden-column');
                } else {
                    header.classList.add('hidden-column');
                }
            }
            
            // Ukryj/pokaż komórki danych
            const cells = table.querySelectorAll(`td:nth-child(${columnIndex + 1})`);
            cells.forEach(cell => {
                if (isVisible) {
                    cell.classList.remove('hidden-column');
                } else {
                    cell.classList.add('hidden-column');
                }
            });
        }

        // Filtrowanie po dacie
        document.getElementById('applyDateFilter').addEventListener('click', () => {
            const table = document.querySelector('table');
            const tbody = table?.querySelector('tbody');
            if (!table || !tbody) {
                showNotification('Nie znaleziono tabeli z danymi.', 'error');
                return;
            }

            // Jeśli nie zapisano oryginalnych danych, zrób to teraz
            if (originalTableRows.length === 0) {
                originalTableRows = Array.from(tbody.querySelectorAll('tr'));
            }

            const headers = Array.from(table.querySelectorAll('th'));
            const year = document.getElementById('yearSelect').value;
            const month = document.getElementById('monthSelect').value;

            // Jeśli nie wybrano filtrów, przywróć wszystkie dane
            if (!year && !month) {
                tbody.innerHTML = '';
                originalTableRows.forEach(row => tbody.appendChild(row.cloneNode(true)));
                showNotification('Wyświetlono wszystkie dane');
                return;
            }

            // Znajdź indeks kolumny z datą - szukaj różnych wariantów nazwy
            let dateIndex = -1;
            for (let i = 0; i < headers.length; i++) {
                const colName = headers[i].textContent.toLowerCase().trim();
                if (colName.includes('data') || colName === 'data-wystawienia' || 
                    colName === 'datawystawienia' || colName.includes('date')) {
                    dateIndex = i;
                    console.log("Znaleziona kolumna daty:", colName, "index:", i);
                    break;
                }
            }

            if (dateIndex === -1) {
                showNotification('Nie znaleziono kolumny z datą. Sprawdź nazwę kolumny z datą.', 'error');
                return;
            }

            // Filtruj wiersze z oryginalnych danych
            const filteredRows = originalTableRows.filter(row => {
                const dateCell = row.cells[dateIndex];
                if (!dateCell) return false;
                
                const dateText = dateCell.textContent.trim();
                
                // Dopasuj format YYYY-MM-DD
                if (dateText.match(/^\d{4}-\d{2}-\d{2}/)) {
                    const parts = dateText.split('-');
                    const rowYear = parts[0];
                    const rowMonth = parts[1];
                    
                    const yearMatches = !year || rowYear === year;
                    const monthMatches = !month || rowMonth === month;
                    
                    return yearMatches && monthMatches;
                }
                
                return false;
            });

            // Wyczyść tabelę i dodaj tylko odfiltrowane wiersze
            tbody.innerHTML = '';
            if (filteredRows.length > 0) {
                filteredRows.forEach(row => tbody.appendChild(row.cloneNode(true)));
                
                // Pokaż informację o filtrowaniu
                let message = 'Wyświetlono dane';
                if (year) message += ` z roku ${year}`;
                if (month) {
                    const monthName = document.querySelector(`#monthSelect option[value="${month}"]`).textContent;
                    message += year ? ` i miesiąca ${monthName}` : ` z miesiąca ${monthName}`;
                }
                showNotification(message);
            } else {
                showNotification('Brak danych dla wybranych kryteriów', 'info');
                
                // Dodaj wiersz informujący o braku danych
                const emptyRow = document.createElement('tr');
                const cell = document.createElement('td');
                cell.colSpan = headers.length;
                cell.textContent = 'Brak danych dla wybranych kryteriów';
                cell.style.textAlign = 'center';
                cell.style.padding = '20px';
                cell.style.color = 'var(--text-secondary)';
                emptyRow.appendChild(cell);
                tbody.appendChild(emptyRow);
            }
        });
    </script>
    
    <style>
        .no-data, .error-message {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: var(--shadow-light);
            margin: 20px auto;
            color: var(--text-secondary);
        }
        
        .no-data i, .error-message i {
            color: var(--primary-color);
            margin-bottom: 15px;
            opacity: 0.7;
        }
        
        .error-message {
            border-left: 4px solid var(--error-color);
            color: var(--error-color);
        }
        
        .error-message i {
            color: var(--error-color);
        }
        
        /* Style dla rozszerzonego komunikatu o błędzie */
        .extended-error {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(239, 83, 80, 0.15);
            margin: 20px auto;
            max-width: 600px;
            padding: 25px;
            position: relative;
            border-left: 5px solid var(--error-color);
        }
        
        .error-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            color: var(--error-color);
        }
        
        .error-header i {
            font-size: 24px;
            margin-right: 10px;
        }
        
        .error-header h3 {
            margin: 0;
            font-size: 18px;
        }
        
        .error-instructions {
            background-color: #f9f9f9;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        
        .error-instructions ol {
            margin: 10px 0 0 20px;
            padding: 0;
        }
        
        .error-instructions li {
            margin-bottom: 8px;
        }
        
        .btn-close {
            background-color: #f5f5f5;
            border: none;
            border-radius: 4px;
            color: #555;
            cursor: pointer;
            font-size: 14px;
            padding: 8px 15px;
            transition: all 0.3s ease;
        }
        
        .btn-close:hover {
            background-color: #e0e0e0;
        }
        
        /* Style dla wskazówek odnośnie plików */
        .file-tips {
            background-color: #f8f9fa;
            border-radius: 6px;
            font-size: 13px;
            margin: 10px 0 15px;
            padding: 10px 15px;
            color: var(--text-secondary);
            border-left: 3px solid var(--primary-light);
        }
        
        .file-tips p {
            margin: 5px 0;
            display: flex;
            align-items: center;
        }
        
        .file-tips i {
            color: var(--primary-color);
            margin-right: 8px;
            font-size: 14px;
        }
        
        .file-tips strong {
            color: var(--primary-dark);
        }
        
        /* Style dla szczegółów błędu */
        .error-details {
            background-color: #f5f5f5;
            border-radius: 5px;
            margin: 10px 0;
            padding: 10px;
        }
        
        .details-content {
            font-family: monospace;
            color: #555;
            margin-top: 5px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 4px;
            border-left: 3px solid #ddd;
            overflow-x: auto;
        }
        
        /* Dodatkowe styles bezpieczeństwa */
        .security-badge {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 1px 6px;
            border-radius: 4px;
            font-size: 11px;
            margin-left: 6px;
            vertical-align: middle;
        }
    </style>
</body>

</html>