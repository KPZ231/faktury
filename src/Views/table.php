<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Tabela</title>
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
            <?php $this->renderTable($selectedAgentId); ?>
        <?php else: ?>
            <!-- Wyświetl listę agentów do wyboru -->
            <h2>Wybierz agenta do wyświetlenia spraw</h2>
            <?php $this->renderAgentSelection(); ?>
        <?php endif; ?>
    </section>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sortables = document.querySelectorAll('.sortable');
            
            // Obsługa sortowania
            sortables.forEach(th => {
                th.addEventListener('click', function() {
                    const column = this.dataset.column;
                    const table = document.querySelector('.data-table');
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
                    
                    // Pobieramy indeks kolumny
                    const columnIdx = Array.from(headerRow.children).indexOf(this);
                    
                    // Sortujemy wiersze
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    const sortedRows = rows.sort((a, b) => {
                        const aVal = a.children[columnIdx].innerText.trim();
                        const bVal = b.children[columnIdx].innerText.trim();
                        
                        // Obsługa różnych typów danych
                        if (!isNaN(parseFloat(aVal)) && !isNaN(parseFloat(bVal))) {
                            // Liczby - wyciągamy wartość liczbową z tekstu
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
                    
                    // Dodajemy efekt sortowania z animacją
                    sortedRows.forEach(row => {
                        row.style.opacity = '0';
                        row.style.transform = 'translateY(10px)';
                        setTimeout(() => {
                            tbody.appendChild(row);
                            // Animacja wejścia
                            setTimeout(() => {
                                row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                                row.style.opacity = '1';
                                row.style.transform = 'translateY(0)';
                            }, 10);
                        }, 50);
                    });
                    
                    console.log('Sortowanie według kolumny:', column, 'kierunek:', sortDirection);
                });
            });

            // Implementacja modelu obliczeniowego dla tabeli
            recalculateValues();
            
            // Dodanie klasy aktywnej dla pierwszej kolumny sortowania
            if (sortables.length > 0) {
                sortables[0].classList.add('asc');
            }
        });

        // Funkcja do przeliczania wartości w tabeli
        function recalculateValues() {
            const table = document.querySelector('.data-table');
            if (!table) return;

            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                // Pobierz komórki z wartościami
                const cells = row.querySelectorAll('td');
                if (cells.length < 10) return; // Pomijamy, jeśli wiersz nie ma wystarczającej liczby komórek

                // Znajdź indeksy kolumn potrzebnych do obliczeń
                let columnIndices = {};
                table.querySelectorAll('th').forEach((th, index) => {
                    const columnName = th.textContent.trim();
                    columnIndices[columnName] = index;
                });

                // 1. Całość prowizji (F = D + (C * E))
                const amountWonCell = cells[columnIndices['Wywalczona kwota']];
                const upfrontFeeCell = cells[columnIndices['Opłata wstępna']];
                const successFeePercentCell = cells[columnIndices['Success fee %']];
                const totalCommissionCell = cells[columnIndices['Całość prowizji']];

                if (amountWonCell && upfrontFeeCell && successFeePercentCell && totalCommissionCell) {
                    const amountWon = parseFloat(amountWonCell.textContent.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
                    const upfrontFee = parseFloat(upfrontFeeCell.textContent.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
                    const successFeePercent = parseFloat(successFeePercentCell.textContent.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
                    
                    const totalCommission = upfrontFee + (amountWon * (successFeePercent/100));
                    
                    // Podświetl wyniki obliczeń dla lepszej widoczności
                    highlightCalculatedValue(totalCommissionCell, totalCommission);
                }

                // 2. Do wypłaty Kuba (H = G - SUM(I:K))
                const kubaPercentCell = cells[columnIndices['Prowizja % Kuba']];
                const kubaPayoutCell = cells[columnIndices['Do wypłaty Kuba']];
                
                // Znajdź komórki z procentami agentów
                let agentPercentages = [];
                for (let i = 1; i <= 3; i++) {
                    const cellIndex = columnIndices[`Prowizja % Agent ${i}`];
                    if (cellIndex !== undefined && cells[cellIndex]) {
                        const percentText = cells[cellIndex].textContent;
                        const percent = parseFloat(percentText.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
                        agentPercentages.push(percent);
                    }
                }
                
                if (kubaPercentCell && kubaPayoutCell) {
                    const kubaPercent = parseFloat(kubaPercentCell.textContent.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
                    const agentSum = agentPercentages.reduce((sum, percent) => sum + percent, 0);
                    const kubaPayout = Math.max(0, Math.min(100, kubaPercent - agentSum));
                    
                    highlightCalculatedValue(kubaPayoutCell, kubaPayout + '%');
                }

                // 3. Ostatnia rata (T = F - SUM(N:R))
                if (totalCommissionCell && columnIndices['Rata 1'] !== undefined && 
                    columnIndices['Rata 2'] !== undefined && columnIndices['Rata 3'] !== undefined && 
                    columnIndices['Rata 4'] !== undefined) {
                    
                    const installment1Cell = cells[columnIndices['Rata 1']];
                    const installment2Cell = cells[columnIndices['Rata 2']];
                    const installment3Cell = cells[columnIndices['Rata 3']];
                    const finalInstallmentCell = cells[columnIndices['Rata 4']];
                    
                    if (installment1Cell && installment2Cell && installment3Cell && finalInstallmentCell) {
                        const totalCommission = parseFloat(totalCommissionCell.textContent.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
                        const installment1 = parseFloat(installment1Cell.textContent.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
                        const installment2 = parseFloat(installment2Cell.textContent.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
                        const installment3 = parseFloat(installment3Cell.textContent.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
                        
                        const finalInstallment = Math.max(0, totalCommission - (installment1 + installment2 + installment3));
                        
                        highlightCalculatedValue(finalInstallmentCell, finalInstallment);
                    }
                }

                // 4. Podział rat dla Kuby (V, X, Z, AB)
                if (kubaPayoutCell && 
                    columnIndices['Rata 1'] !== undefined && 
                    columnIndices['Rata 2'] !== undefined && 
                    columnIndices['Rata 3'] !== undefined && 
                    columnIndices['Rata 4'] !== undefined &&
                    columnIndices['Rata 1 – Kuba'] !== undefined &&
                    columnIndices['Rata 2 – Kuba'] !== undefined &&
                    columnIndices['Rata 3 – Kuba'] !== undefined &&
                    columnIndices['Rata 4 – Kuba'] !== undefined) {
                    
                    const kubaPayout = parseFloat(kubaPayoutCell.textContent.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
                    const kubaFraction = kubaPayout / 100;
                    
                    const installment1 = parseFloat(cells[columnIndices['Rata 1']].textContent.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
                    const installment2 = parseFloat(cells[columnIndices['Rata 2']].textContent.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
                    const installment3 = parseFloat(cells[columnIndices['Rata 3']].textContent.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
                    const finalInstallment = parseFloat(cells[columnIndices['Rata 4']].textContent.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
                    
                    const kubaInstallment1Cell = cells[columnIndices['Rata 1 – Kuba']];
                    const kubaInstallment2Cell = cells[columnIndices['Rata 2 – Kuba']];
                    const kubaInstallment3Cell = cells[columnIndices['Rata 3 – Kuba']];
                    const kubaFinalInstallmentCell = cells[columnIndices['Rata 4 – Kuba']];
                    
                    if (kubaInstallment1Cell && kubaInstallment2Cell && kubaInstallment3Cell && kubaFinalInstallmentCell) {
                        highlightCalculatedValue(kubaInstallment1Cell, installment1 * kubaFraction);
                        highlightCalculatedValue(kubaInstallment2Cell, installment2 * kubaFraction);
                        highlightCalculatedValue(kubaInstallment3Cell, installment3 * kubaFraction);
                        highlightCalculatedValue(kubaFinalInstallmentCell, finalInstallment * kubaFraction);
                    }
                }

                // 5. Podział rat dla Agentów 1–3
                for (let agentNum = 1; agentNum <= 3; agentNum++) {
                    const agentPercentTitle = `Prowizja % Agent ${agentNum}`;
                    
                    if (columnIndices[agentPercentTitle] !== undefined) {
                        const agentPercent = parseFloat(cells[columnIndices[agentPercentTitle]]?.textContent.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
                        const agentFraction = agentPercent / 100;
                        
                        for (let rataNum = 1; rataNum <= 4; rataNum++) {
                            const rataTitle = rataNum === 4 ? 'Rata 4' : `Rata ${rataNum}`;
                            const agentRataTitle = `Rata ${rataNum} – Agent ${agentNum}`;
                            
                            if (columnIndices[rataTitle] !== undefined && columnIndices[agentRataTitle] !== undefined) {
                                const rataValue = parseFloat(cells[columnIndices[rataTitle]]?.textContent.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
                                const agentRataCell = cells[columnIndices[agentRataTitle]];
                                
                                if (agentRataCell) {
                                    highlightCalculatedValue(agentRataCell, rataValue * agentFraction);
                                }
                            }
                        }
                    }
                }
            });
        }

        // Funkcja podświetlająca obliczone wartości
        function highlightCalculatedValue(cell, value) {
            if (!cell) return;
            
            // Sprawdź czy obecna wartość komórki jest już poprawna
            const currentValue = parseFloat(cell.textContent.replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
            const calculated = typeof value === 'number' ? value : parseFloat(value);
            
            // Jeśli wartości są różne, podświetl komórkę
            if (Math.abs(currentValue - calculated) > 0.01) {
                cell.setAttribute('data-calculated', calculated.toFixed(2));
                cell.style.backgroundColor = 'rgba(255, 235, 59, 0.3)';
                cell.style.position = 'relative';
                
                // Sprawdź, czy wartość powinna być procentem czy kwotą
                const isPercent = typeof value === 'string' && value.includes('%') || 
                                  cell.textContent.includes('%') || 
                                  cell.parentElement.querySelector('th')?.textContent.includes('%');
                
                // Formatuj wartość z separatorami tysięcznym i odpowiednią jednostką
                const formattedValue = isPercent ? 
                    formatPercent(calculated) : 
                    formatCurrency(calculated);
                
                // Dodaj pulsującą podpowiedź
                const tooltip = document.createElement('span');
                tooltip.className = 'calculation-tooltip';
                tooltip.textContent = 'Obliczona wartość: ' + formattedValue;
                tooltip.style.position = 'absolute';
                tooltip.style.bottom = '100%';
                tooltip.style.left = '0';
                tooltip.style.backgroundColor = '#333';
                tooltip.style.color = 'white';
                tooltip.style.padding = '3px 8px';
                tooltip.style.borderRadius = '4px';
                tooltip.style.zIndex = '10';
                tooltip.style.whiteSpace = 'nowrap';
                tooltip.style.opacity = '0';
                tooltip.style.transition = 'opacity 0.3s';
                
                // Dodaj strzałkę
                const arrow = document.createElement('span');
                arrow.style.position = 'absolute';
                arrow.style.top = '100%';
                arrow.style.left = '10px';
                arrow.style.borderWidth = '5px';
                arrow.style.borderStyle = 'solid';
                arrow.style.borderColor = '#333 transparent transparent transparent';
                tooltip.appendChild(arrow);
                
                cell.appendChild(tooltip);
                
                // Pokaż podpowiedź po najechaniu
                cell.addEventListener('mouseenter', function() {
                    tooltip.style.opacity = '1';
                });
                
                cell.addEventListener('mouseleave', function() {
                    tooltip.style.opacity = '0';
                });
            }
        }
        
        // Funkcje pomocnicze do formatowania wartości
        function formatCurrency(value) {
            return value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " ") + ' zł';
        }
        
        function formatPercent(value) {
            return value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, " ") + '%';
        }
    </script>

    <style>
        @keyframes pulse {
            0% { opacity: 0.7; }
            50% { opacity: 1; }
            100% { opacity: 0.7; }
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
        td[data-calculated] {
            position: relative;
            background-color: rgba(255, 235, 59, 0.2) !important;
            border-bottom: 1px dashed #FFB300 !important;
            cursor: help;
            transition: all 0.3s ease;
        }
        
        td[data-calculated]:hover {
            background-color: rgba(255, 235, 59, 0.3) !important;
        }
        
        /* Dodatkowe styles dla wyróżnienia agentów */
        .agent-name-highlight {
            padding: 3px 8px;
            border-radius: 4px;
            background-color: rgba(224, 224, 224, 0.3);
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .agent-name-highlight:hover {
            background-color: rgba(224, 224, 224, 0.6);
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        /* Style dla przycisku edycji */
        .edit-button {
            display: inline-block;
            padding: 5px 10px;
            background-color: #2196F3;
            color: white;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            margin-right: 5px;
            transition: all 0.3s ease;
        }
        
        .edit-button:hover {
            background-color: #0b7dda;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        /* Kontener przycisków akcji */
        .action-buttons {
            white-space: nowrap;
            text-align: center;
        }
    </style>

</body>

</html>