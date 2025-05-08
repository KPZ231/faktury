<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Tabela</title>
    <script>
        // Uruchom po załadowaniu DOM
        document.addEventListener('DOMContentLoaded', function() {
            console.log("DOM loaded - initializing table functions");
            
            // Inicjalizuj funkcje sortowania
            initSorting();
            
            // Inicjalizuj rozwijanie kolumn
            initColumnExpansion();
            
            // Dodaj bezpośrednie kliknięcie na komórkę agenta
            initAgentCellClicks();
            
            // Napraw wyświetlanie wartości
            fixHighlightedValues();
            
            // Initialize invoice tooltips
            initInvoiceTooltips();
            
            // Function to handle sync button click
            function handleSyncClick(btn) {
                // Show loading state
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Synchronizacja...';
                
                // Make AJAX request
                fetch('/sync-payments-ajax', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    // Show notification
                    const notification = document.createElement('div');
                    notification.className = 'notification info show';
                    notification.innerHTML = `<i class="fa-solid fa-${data.success ? 'check-circle' : 'exclamation-circle'}"></i> ${data.message}`;
                    document.body.appendChild(notification);
                    
                    // Update last sync time if provided
                    if (data.last_sync_time) {
                        const lastSyncSpan = document.querySelector('.last-sync-time');
                        if (lastSyncSpan) {
                            lastSyncSpan.textContent = 'Ostatnia synchronizacja: ' + data.last_sync_time;
                        } else {
                            const newSyncSpan = document.createElement('span');
                            newSyncSpan.className = 'last-sync-time';
                            newSyncSpan.textContent = 'Ostatnia synchronizacja: ' + data.last_sync_time;
                            document.querySelector('.sync-payment-container').appendChild(newSyncSpan);
                        }
                    }
                    
                    // Remove notification after delay
                    setTimeout(() => {
                        notification.classList.remove('show');
                        setTimeout(() => notification.remove(), 500);
                        
                        // Refresh the table content
                        location.reload();
                    }, 3000);
                })
                .catch(error => {
                    console.error('Error syncing payments:', error);
                    
                    // Show error notification
                    const notification = document.createElement('div');
                    notification.className = 'notification error show';
                    notification.innerHTML = '<i class="fa-solid fa-exclamation-circle"></i> Błąd podczas synchronizacji statusów płatności';
                    document.body.appendChild(notification);
                    
                    // Remove notification after delay
                    setTimeout(() => {
                        notification.classList.remove('show');
                        setTimeout(() => notification.remove(), 500);
                    }, 5000);
                })
                .finally(() => {
                    // Reset button state
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-sync"></i> Synchronizuj statusy płatności';
                });
            }
            
            // Attach event listener to first sync button
            const syncBtn = document.getElementById('syncPaymentsBtn');
            if (syncBtn) {
                syncBtn.addEventListener('click', function() {
                    handleSyncClick(this);
                });
            }
            
            // Attach event listener to second sync button
            const syncBtn2 = document.getElementById('syncPaymentsBtn2');
            if (syncBtn2) {
                syncBtn2.addEventListener('click', function() {
                    handleSyncClick(this);
                });
            }
        });
        
        // Inicjalizacja klikania bezpośrednio na komórki agentów
        function initAgentCellClicks() {
            console.log("Initializing agent cell clicks");
            
            document.querySelectorAll('.agent-column').forEach(cell => {
                console.log("Found agent cell:", cell.textContent.trim());
                
                cell.addEventListener('click', function(e) {
                    e.stopPropagation();
                    console.log("Agent cell clicked:", this.textContent.trim());
                    
                    // Przełącz stan rozwinięcia komórki
                    if (this.classList.contains('expanded-cell')) {
                        hideAgentDetails(this);
                    } else {
                        const agentName = this.textContent.trim(); 
                        showAgentDetails(this, agentName);
                    }
                });
            });
        }
        
        // Inicjalizacja sortowania tabeli
        function initSorting() {
            console.log("Initializing sorting");
            const sortables = document.querySelectorAll('.sortable');
            
            if (sortables.length > 0) {
                console.log("Found " + sortables.length + " sortable headers");
                sortables[0].classList.add('asc');
            } else {
                console.log("No sortable headers found");
            }
            
            sortables.forEach(th => {
                th.addEventListener('click', function() {
                    const column = this.dataset.column;
                    const table = this.closest('table');
                    const tbody = table.querySelector('tbody');
                    const headerRow = table.querySelector('thead tr');
                    
                    // Resetujemy klasy sortowania dla wszystkich nagłówków
                    headerRow.querySelectorAll('th').forEach(header => {
                        if (header !== this) {
                            header.classList.remove('asc', 'desc');
                        }
                    });
                    
                    // Ustalamy kierunek sortowania
                    let sortDirection = 'asc';
                    if (this.classList.contains('asc')) {
                        this.classList.remove('asc');
                        this.classList.add('desc');
                        sortDirection = 'desc';
                    } else {
                        this.classList.remove('desc');
                        this.classList.add('asc');
                    }
                    
                    // Sortujemy wiersze
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    const sortedRows = rows.sort((a, b) => {
                        const aVal = a.children[Array.from(headerRow.children).indexOf(this)].innerText.trim();
                        const bVal = b.children[Array.from(headerRow.children).indexOf(this)].innerText.trim();
                        
                        // Obsługa różnych typów danych
                        if (!isNaN(parseFloat(aVal)) && !isNaN(parseFloat(bVal))) {
                            // Liczby
                            const aNum = parseFloat(aVal.replace(/[^\d.,]/g, '').replace(',', '.'));
                            const bNum = parseFloat(bVal.replace(/[^\d.,]/g, '').replace(',', '.'));
                            return sortDirection === 'asc' ? aNum - bNum : bNum - aNum;
                        } else if (aVal === 'Tak' || aVal === 'Nie' || bVal === 'Tak' || bVal === 'Nie') {
                            // Wartości logiczne
                            const aVal2 = aVal === 'Tak' ? 1 : 0;
                            const bVal2 = bVal === 'Tak' ? 1 : 0;
                            return sortDirection === 'asc' ? aVal2 - bVal2 : bVal2 - aVal2;
                        } else {
                            // Tekst
                            return sortDirection === 'asc' 
                                ? aVal.localeCompare(bVal, 'pl', {sensitivity: 'base'}) 
                                : bVal.localeCompare(aVal, 'pl', {sensitivity: 'base'});
                        }
                    });
                    
                    // Dodajemy posortowane wiersze z powrotem do tabeli
                    rows.forEach(row => row.remove());
                    sortedRows.forEach(row => tbody.appendChild(row));
                    
                    console.log('Sortowanie według kolumny:', column, 'kierunek:', sortDirection);
                });
            });
        }

        // Inicjalizacja rozwijania kolumn
        function initColumnExpansion() {
            console.log("Initializing column expansion");
            
            // Przetwórz wszystkie nagłówki agentów (Kuba, Agent 1, Agent 2, Agent 3)
            const collapsibleHeaders = document.querySelectorAll('th.collapsible');
            console.log("Found " + collapsibleHeaders.length + " collapsible headers");
            
            collapsibleHeaders.forEach(th => {
                console.log("Processing header:", th.textContent);
                
                // Dodaj przycisk rozwijania
                if (!th.querySelector('.column-expand-icon')) {
                    const expandButton = document.createElement('span');
                    expandButton.className = 'column-expand-icon';
                    expandButton.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
                    th.insertBefore(expandButton, th.firstChild);
                }
                
                // Obsługa kliknięcia w nagłówek
                th.addEventListener('click', function(e) {
                    console.log("Header clicked:", this.textContent);
                    e.stopPropagation(); // Zatrzymaj propagację, aby nie wywoływać sortowania
                    
                    // Pobierz nazwę kolumny (Kuba, Agent 1, Agent 2, Agent 3)
                    const columnName = this.dataset.column;
                    console.log("Column name:", columnName);
                    
                    // Przełącz stan ikony rozwijania
                    const icon = this.querySelector('.column-expand-icon i');
                    icon.classList.toggle('expanded');
                    
                    // Przełącz klasę nagłówka
                    this.classList.toggle('expanded-header');
                    
                    // Sprawdź, czy komórki mają już dane
                    const table = this.closest('table');
                    const rows = table.querySelectorAll('tbody tr');
                    const colIndex = Array.from(this.parentNode.children).indexOf(this);
                    
                    console.log("Column index:", colIndex);
                    
                    // Rozwiń zawartość dla wszystkich komórek w tej kolumnie
                    rows.forEach(row => {
                        const cell = row.children[colIndex];
                        if (cell) {
                            console.log("Processing cell:", cell);
                            
                            // Rozwiń komórkę
                            if (this.classList.contains('expanded-header')) {
                                showAgentDetails(cell, columnName);
                            } else {
                                hideAgentDetails(cell, columnName);
                            }
                        }
                    });
                });
            });
            
            // Napraw symbole po zakończeniu animacji
            document.addEventListener('transitionend', function(event) {
                if (event.target.classList.contains('agent-details-row') || 
                    event.target.classList.contains('agent-details')) {
                    fixHighlightedValues();
                }
            });
        }
        
        // Pokaż szczegóły agenta
        function showAgentDetails(cell, agentName) {
            console.log("Showing details for:", agentName, "in cell:", cell);
            
            // Sprawdź, czy komórka ma klasę agent-column
            if (!cell.classList.contains('agent-column')) {
                console.log("Not an agent column cell, skipping");
                return;
            }
            
            // Sprawdź, czy szczegóły już istnieją
            if (!cell.querySelector('.agent-details')) {
                // Dodaj kontener na szczegóły
                const details = document.createElement('div');
                details.className = 'agent-details';
                
                // Pobierz dane z atrybutów data-*
                const name = cell.dataset.name || '';
                const percent = cell.dataset.percent || '';
                const wypłata = cell.dataset.wypłata || '';
                const rata1 = cell.dataset.rata1 || '';
                const rata2 = cell.dataset.rata2 || '';
                const rata3 = cell.dataset.rata3 || '';
                const rata4 = cell.dataset.rata4 || '';
                const invoice = cell.dataset.invoice || '';
                
                console.log("Agent data:", { name, percent, wypłata, rata1, rata2, rata3, rata4, invoice });
                
                // Dodaj wiersze z danymi
                const rows = [];
                if (percent) {
                    rows.push(`<div class="agent-details-row"><span>Prowizja:</span> <span>${percent}</span></div>`);
                }
                
                // Dodaj wiersz "Do wypłaty" tylko dla Kuby
                if (agentName === 'Kuba' && wypłata) {
                    rows.push(`<div class="agent-details-row"><span>Do wypłaty:</span> <span>${wypłata}</span></div>`);
                }
                
                // Dodaj raty
                if (rata1) rows.push(`<div class="agent-details-row"><span>Rata 1:</span> <span>${rata1}</span></div>`);
                if (rata2) rows.push(`<div class="agent-details-row"><span>Rata 2:</span> <span>${rata2}</span></div>`);
                if (rata3) rows.push(`<div class="agent-details-row"><span>Rata 3:</span> <span>${rata3}</span></div>`);
                if (rata4) rows.push(`<div class="agent-details-row"><span>Rata 4:</span> <span>${rata4}</span></div>`);
                
                // Dodaj numer faktury tylko dla Kuby
                if (agentName === 'Kuba' && invoice) {
                    rows.push(`<div class="agent-details-row"><span></span> <span>${invoice}</span></div>`);
                }
                
                // Ustaw zawartość HTML
                details.innerHTML = rows.join('');
                cell.appendChild(details);
                
                // Dodaj klasę do komórki
                cell.classList.add('expanded-cell');
            } else {
                // Pokaż istniejące szczegóły
                cell.querySelector('.agent-details').style.display = 'block';
                cell.classList.add('expanded-cell');
            }
            
            // Napraw formatowanie wartości
            fixHighlightedValues();
        }
        
        // Ukryj szczegóły agenta
        function hideAgentDetails(cell, agentName) {
            console.log("Hiding details for agent in cell:", cell);
            
            if (!cell.classList.contains('agent-column')) {
                console.log("Not an agent column cell, skipping");
                return;
            }
            
            const details = cell.querySelector('.agent-details');
            if (details) {
                details.style.display = 'none';
            }
            cell.classList.remove('expanded-cell');
        }

        // Funkcja do naprawy wyświetlania podświetlonych wartości z symbolami waluty i procentami
        function fixHighlightedValues() {
            console.log("Fixing highlighted values");
            
            // 1. Fixuj symbole % i zł w bezpośrednio podświetlonych komórkach
            document.querySelectorAll('.selected-agent').forEach(span => {
                const text = span.innerText;
                // Sprawdź, czy tekst zawiera tylko cyfry, spacje i separatory
                if (text.match(/^[\d\s,.]+$/)) {
                    // To jest tylko liczba, sprawdźmy czy to procent czy kwota
                    const parentCell = span.closest('td');
                    if (parentCell) {
                        const column = getColumnForCell(parentCell);
                        if (column && (column.includes('%') || column.includes('prowizji') || column.includes('Prowizja'))) {
                            // To jest wartość procentowa
                            span.innerText = text + '%';
                        } else if (column && (column.includes('kwota') || column.includes('Rata') || 
                                  column.includes('rata') || column.includes('opłata') || column.includes('Opłata') ||
                                  column.includes('prowizji') || column.includes('commission'))) {
                            // To jest wartość pieniężna
                            span.innerText = text + ' zł';
                        }
                    }
                }
            });

            // 2. Popraw formatowanie w komórkach szczegółów agenta
            document.querySelectorAll('.agent-details-row span:nth-child(2)').forEach(span => {
                const label = span.previousElementSibling?.textContent || '';
                const text = span.innerText.trim();
                
                // Sprawdź, czy tekst zawiera tylko cyfry, spacje i separatory (i nie zawiera % lub zł)
                if (text.match(/^[\d\s,.]+$/) && !text.includes('%') && !text.includes('zł')) {
                    // Sprawdź typ wartości na podstawie etykiety
                    if (label.includes('Prowizja') || label.includes('wypłaty')) {
                        // To jest wartość procentowa
                        span.innerText = text + '%';
                    } else if (label.includes('Rata')) {
                        // To jest wartość pieniężna
                        span.innerText = text + ' zł';
                    }
                }
            });
        }

        // Pomocnicza funkcja do określenia kolumny dla komórki
        function getColumnForCell(cell) {
            const table = cell.closest('table');
            if (!table) return null;
            
            const rowIndex = Array.from(cell.parentNode.parentNode.children).indexOf(cell.parentNode);
            const columnIndex = Array.from(cell.parentNode.children).indexOf(cell);
            
            if (columnIndex >= 0) {
                const headers = table.querySelectorAll('th');
                if (headers[columnIndex]) {
                    return headers[columnIndex].innerText;
                }
            }
            
            return null;
        }

        // Initialize tooltips for invoice numbers
        function initInvoiceTooltips() {
            console.log("Initializing invoice tooltips");
            
            document.querySelectorAll('.invoice-number').forEach(element => {
                // Add title attribute to show full text on hover if truncated
                if (element.offsetWidth < element.scrollWidth) {
                    element.title = element.textContent;
                }
            });
        }
    </script>

    <style>
        @keyframes pulse {
            0% { opacity: 0.7; }
            50% { opacity: 1; }
            100% { opacity: 0.7; }
        }
        
        /* Style for paid installments with invoice numbers */
        .paid-with-invoice {
            position: relative;
            cursor: pointer;
            border-bottom: 1px dashed #4CAF50;
        }
        
        /* Custom tooltip styling */
        .status.status-yes[data-invoice]:hover::after {
            content: attr(title);
            position: absolute;
            bottom: 150%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 100;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .status.status-yes[data-invoice]:hover::before {
            content: "";
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
            z-index: 100;
        }
        
        .calculation-tooltip {
            animation: pulse 1.5s infinite;
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #333;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            z-index: 100;
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        .calculation-tooltip::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }
        
        /* Poprawa stylu dla komórek z różniącą się obliczoną wartością */
        td[data-calculated], .nested-value[data-calculated] {
            position: relative;
            background-color: rgba(255, 235, 59, 0.2) !important;
            border-bottom: 1px dashed #FFB300 !important;
            cursor: help;
            transition: all 0.3s ease;
        }
        
        td[data-calculated]:hover, .nested-value[data-calculated]:hover {
            background-color: rgba(255, 235, 59, 0.3) !important;
        }
        
        /* Stylizacja kolumn agentów */
        th.collapsible {
            cursor: pointer;
            position: relative;
            padding-left: 28px !important;
            background-color: #64b5f6;
            transition: all 0.3s ease;
        }
        
        th.collapsible:hover {
            background-color: #42a5f5;
        }
        
        .column-expand-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            transition: transform 0.3s ease;
            display: inline-block;
            margin-right: 6px;
        }
        
        .column-expand-icon i {
            transition: transform 0.3s ease;
        }
        
        .column-expand-icon i.expanded {
            transform: rotate(90deg);
        }
        
        .expanded-header {
            background-color: #1976D2 !important;
            color: white !important;
        }
        
        /* Styl dla komórek agentów */
        .agent-column {
            position: relative;
            white-space: nowrap;
            transition: all 0.3s ease;
            background-color: #fff;
            vertical-align: top; /* Bardzo ważne dla układu */
            padding: 1.2rem 1.5rem !important;
            cursor: pointer;
        }
        
        .agent-column.expanded-cell {
            background-color: rgba(232, 245, 255, 0.5) !important;
            min-width: 250px;
            padding-bottom: 0 !important;
        }
        
        /* Styl dla szczegółów agenta */
        .agent-details {
            display: block;
            width: 100%;
            background-color: rgba(232, 245, 255, 0.5);
            margin-top: 10px;
            padding: 10px 0;
            border-radius: 4px;
            border: 1px solid rgba(25, 118, 210, 0.2);
            margin-bottom: 15px;
        }
        
        .agent-details-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 10px;
            border-bottom: 1px dashed rgba(25, 118, 210, 0.1);
        }
        
        .agent-details-row:last-child {
            border-bottom: none;
        }
        
        .agent-details-row span:first-child {
            font-weight: 500;
            color: #1976D2;
        }
        
        .agent-details-row span:last-child {
            font-weight: 600;
        }

        /* Zwiększenie szerokości tabeli kiedy rozwinięta */
        .data-table th.expanded-header + th {
            padding-left: 25px !important;
        }

        /* Additional styles for the sync payments button */
        .sync-payment-container {
            margin: 15px 0;
            position: relative;
            display: inline-block;
        }
        
        .sync-payment-button {
            display: inline-flex;
            align-items: center;
            background-color: #4CAF50;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .sync-payment-button i {
            margin-right: 8px;
        }
        
        .sync-payment-button:hover {
            background-color: #388E3C;
        }
        
        .sync-tooltip {
            visibility: hidden;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 4px;
            padding: 5px 10px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            white-space: nowrap;
            font-size: 12px;
        }
        
        .sync-payment-container:hover .sync-tooltip {
            visibility: visible;
            opacity: 1;
        }
        
        .last-sync-time {
            display: block;
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }
        
        /* Style for invoice numbers displayed under installment amounts */
        .invoice-number {
            display: block;
            font-size: 11px;
            color: #4CAF50;
            margin-top: 2px;
            font-style: italic;
            font-weight: normal;
        }
        
        /* Adjust table cells containing invoice numbers */
        td.currency {
            vertical-align: top;
            line-height: 1.3;
            padding-top: 8px;
            padding-bottom: 8px;
            min-width: 100px;  /* Ensure cell is wide enough for invoice number */
        }
        
        td.currency .invoice-number {
            border-top: 1px dotted #e0e0e0;
            padding-top: 2px;
            max-width: 120px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Make invoice numbers more visible on hover */
        td.currency:hover .invoice-number {
            color: #2E7D32;
            font-weight: 500;
        }
        
        /* Make rate columns with consistent width */
        th[data-column^="Rata"] {
            min-width: 110px;
        }
    </style>

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
        <h1>Podejrzyj Tabele</h1>
    </header>

    <?php if (isset($_GET['success'])): ?>
    <div class="notification info show">
        <i class="fa-solid fa-check-circle"></i>
        Rekord został pomyślnie zaktualizowany!
    </div>
    <script>
        // Auto-hide notification after 3 seconds
        setTimeout(function() {
            document.querySelector('.notification').classList.remove('show');
        }, 3000);
    </script>
    <?php endif; ?>

    <section id="dataTable">
        <?php if (isset($selectedAgent)): ?>
            <!-- Wyświetl dane dla wybranego agenta -->
            <h2>Sprawy agenta: <span class="selected-agent-name"><?= htmlspecialchars($selectedAgent['imie'] . ' ' . $selectedAgent['nazwisko'], ENT_QUOTES) ?></span></h2>
            <p class="agent-info">Wszystkie dane związane z agentem <strong><?= htmlspecialchars($selectedAgent['imie'], ENT_QUOTES) ?></strong> są podświetlone na niebiesko.</p>
            <a href="/table" class="back-link">⬅️ Powrót do wyboru agenta</a>
            
            <!-- Add Synchronize Payments button -->
            <div class="sync-payment-container">
                <button id="syncPaymentsBtn" class="sync-payment-button">
                    <i class="fa-solid fa-sync"></i> Synchronizuj statusy płatności
                </button>
                <span class="sync-tooltip">Aktualizuje status opłaconych rat na podstawie faktur w systemie</span>
                <?php if (isset($_SESSION['last_payment_sync'])): ?>
                    <span class="last-sync-time">
                        Ostatnia synchronizacja: <?= date('d.m.Y H:i:s', $_SESSION['last_payment_sync']) ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <?php $this->renderTable($selectedAgentId); ?>
        <?php else: ?>
            <!-- Wyświetl listę agentów do wyboru -->
            <h2>Wybierz agenta do wyświetlenia spraw</h2>
            
            <!-- Add Synchronize Payments button for main view too -->
            <div class="sync-payment-container">
                <button id="syncPaymentsBtn2" class="sync-payment-button">
                    <i class="fa-solid fa-sync"></i> Synchronizuj statusy płatności
                </button>
                <span class="sync-tooltip">Aktualizuje status opłaconych rat na podstawie faktur w systemie</span>
            </div>
            
            <?php $this->renderAgentSelection(); ?>
        <?php endif; ?>
    </section>

</body>

</html>