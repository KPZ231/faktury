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
        </ul>
    </nav>

    <header>
        <h1>Podejrzyj Tabele</h1>
    </header>

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
            sortables.forEach(th => {
                th.addEventListener('click', function() {
                    const column = this.dataset.column;
                    // Implementacja sortowania - można rozwinąć w przyszłości
                    console.log('Sortowanie według kolumny:', column);
                });
            });

            // Implementacja modelu obliczeniowego dla tabeli
            recalculateValues();
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
                tooltip.style.fontSize = '12px';
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
        }
    </style>

</body>

</html>