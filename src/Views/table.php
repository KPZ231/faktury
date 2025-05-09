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
            
            // Initialize agent payment notifications
            initAgentPaymentNotifications();
            
            // Initialize commission checkboxes
            initCommissionCheckboxes();
            
            // Hide commission paid columns
            hideCommissionColumns();
            
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
                        setTimeout(() => notification.remove(), 5000);
                    }, 5000);
                })
                .finally(() => {
                    // Reset button state
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-sync"></i> Synchronizuj statusy płatności';
                });
            }
            
            // Function to initialize agent payment notifications
            function initAgentPaymentNotifications() {
                console.log("Initializing agent payment notifications");
                
                // Look for all installment cells in the table
                document.querySelectorAll('.data-table tbody tr').forEach(row => {
                    // Get all cells in the row
                    const cells = Array.from(row.querySelectorAll('td'));
                    
                    // Find all agent cells in the row (Agent 1, Agent 2, Agent 3)
                    const agentCells = {};
                    const agentRates = {};
                    
                    // Collect agent info
                    cells.forEach(cell => {
                        if (cell.classList.contains('agent-column')) {
                            // Extract agent info and installment rates
                            const agentName = cell.dataset.name || '';
                            
                            // Figure out which agent number this is
                            let agentNumber = 0;
                            const columnHeader = getColumnForCell(cell);
                            
                            if (columnHeader) {
                                if (columnHeader.includes('Agent 1')) agentNumber = 1;
                                else if (columnHeader.includes('Agent 2')) agentNumber = 2;
                                else if (columnHeader.includes('Agent 3')) agentNumber = 3;
                            }
                            
                            if (agentNumber > 0 && agentName) {
                                agentCells[agentNumber] = cell;
                                agentRates[agentNumber] = {
                                    name: agentName,
                                    rata1: cell.dataset.rata1 || '',
                                    rata2: cell.dataset.rata2 || '',
                                    rata3: cell.dataset.rata3 || '',
                                    rata4: cell.dataset.rata4 || ''
                                };
                            }
                        }
                    });
                    
                    // Also get Kuba's data
                    const kubaCell = cells.find(cell => 
                        cell.classList.contains('agent-column') && 
                        (cell.dataset.name === 'Kuba' || cell.textContent.trim() === 'Kuba')
                    );
                    
                    let kubaRates = null;
                    if (kubaCell) {
                        kubaRates = {
                            name: 'Kuba',
                            rata1: kubaCell.dataset.rata1 || '',
                            rata2: kubaCell.dataset.rata2 || '',
                            rata3: kubaCell.dataset.rata3 || '',
                            rata4: kubaCell.dataset.rata4 || ''
                        };
                    }
                    
                    // Process all installment cells (1-4)
                    for (let i = 1; i <= 4; i++) {
                        // Find rate cell by exact text matching
                        const rateCell = cells.find(cell => {
                            const header = getColumnForCell(cell);
                            return header === `Rata ${i}`;
                        });
                        
                        // If rate cell exists
                        if (rateCell) {
                            const isPaid = cells.some(cell => {
                                const header = getColumnForCell(cell);
                                return header === `Opłacona ${i}` && cell.textContent.trim() === 'Tak';
                            });
                            
                            // If paid, mark with red highlight (existing functionality)
                            if (isPaid) {
                                rateCell.classList.add('rate-paid');
                                row.classList.add('has-paid-installment');
                            }
                            
                            // Create tooltip with agent payment information
                            let tooltipContent = '';
                            
                            // Get invoice number for this installment if available
                            let invoiceField;
                            switch(i) {
                                case 1: invoiceField = 'installment1_paid_invoice'; break;
                                case 2: invoiceField = 'installment2_paid_invoice'; break;
                                case 3: invoiceField = 'installment3_paid_invoice'; break;
                                case 4: invoiceField = 'final_installment_paid_invoice'; break;
                            }
                            
                            // Get commission invoice number if available
                            let commissionInvoiceField;
                            switch(i) {
                                case 1: commissionInvoiceField = 'installment1_commission_invoice'; break;
                                case 2: commissionInvoiceField = 'installment2_commission_invoice'; break;
                                case 3: commissionInvoiceField = 'installment3_commission_invoice'; break;
                                case 4: commissionInvoiceField = 'final_installment_commission_invoice'; break;
                            }
                            
                            // If installment is paid, show invoice numbers
                            if (isPaid) {
                                // Add invoice info to tooltip if available
                                if (row.hasAttribute(invoiceField) && row[invoiceField]) {
                                    tooltipContent += `<div class="tooltip-section">Faktura klienta: <strong>${row[invoiceField]}</strong></div>`;
                                }
                                
                                // Add commission invoice info if available
                                if (row.hasAttribute(commissionInvoiceField) && row[commissionInvoiceField]) {
                                    tooltipContent += `<div class="tooltip-section">Faktura prowizji: <strong>${row[commissionInvoiceField]}</strong></div>`;
                                }
                                
                                // Add section separator if we have invoice info
                                if (tooltipContent) {
                                    tooltipContent += `<div class="tooltip-separator"></div>`;
                                }
                            }
                            
                            // Add info for each agent that has a positive amount
                            Object.keys(agentRates).forEach(agentNum => {
                                const agent = agentRates[agentNum];
                                const rateKey = `rata${i}`;
                                const rateValue = agent[rateKey];
                                
                                // Check if agent has a rate value
                                if (rateValue && rateValue !== '0' && rateValue !== '0 zł' && 
                                    rateValue !== '0,00' && rateValue !== '0,00 zł') {
                                    
                                    // Extract the numeric value (remove zł, spaces and formatting)
                                    let numericValue = rateValue;
                                    if (typeof numericValue === 'string') {
                                        numericValue = numericValue.replace(/[^\d,.]/g, '');
                                        numericValue = numericValue.replace(',', '.');
                                        numericValue = parseFloat(numericValue);
                                    }
                                    
                                    // If rate is a positive number
                                    if (numericValue > 0) {
                                        tooltipContent += `<strong>${agent.name}</strong>: ${rateValue}<br>`;
                                    }
                                }
                            });
                            
                            // Add Kuba's payment info if applicable
                            if (kubaRates) {
                                const rateKey = `rata${i}`;
                                const rateValue = kubaRates[rateKey];
                                
                                if (rateValue && rateValue !== '0' && rateValue !== '0 zł' && 
                                    rateValue !== '0,00' && rateValue !== '0,00 zł') {
                                    
                                    let numericValue = rateValue;
                                    if (typeof numericValue === 'string') {
                                        numericValue = numericValue.replace(/[^\d,.]/g, '');
                                        numericValue = numericValue.replace(',', '.');
                                        numericValue = parseFloat(numericValue);
                                    }
                                    
                                    if (numericValue > 0) {
                                        tooltipContent += `<strong>Kuba</strong>: ${rateValue}<br>`;
                                    }
                                }
                            }
                            
                            // Add tooltip if there's content
                            if (tooltipContent) {
                                // Check if a tooltip already exists
                                let tooltip = rateCell.querySelector('.agent-payment-tooltip');
                                if (!tooltip) {
                                    tooltip = document.createElement('div');
                                    tooltip.className = 'agent-payment-tooltip';
                                    rateCell.appendChild(tooltip);
                                    
                                    // Add hover behavior for showing tooltip
                                    rateCell.addEventListener('mouseenter', () => {
                                        tooltip.style.display = 'block';
                                    });
                                    
                                    rateCell.addEventListener('mouseleave', () => {
                                        tooltip.style.display = 'none';
                                    });
                                }
                                
                                // Update tooltip content
                                tooltip.innerHTML = tooltipContent;
                                
                                // Add a hover indicator to show there's tooltip info available
                                rateCell.classList.add('has-payment-info');
                            }
                        }
                    }
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

        // Function to initialize commission checkboxes
        function initCommissionCheckboxes() {
            console.log("Initializing commission checkboxes");
            
            document.querySelectorAll('.commission-checkbox-container').forEach(container => {
                const checkbox = container.querySelector('.commission-checkbox');
                const label = container.querySelector('.commission-checkbox-label');
                
                // Apply initial styles based on checkbox state
                const cell = checkbox.closest('td');
                if (!cell) return;
                
                if (checkbox.checked) {
                    // If checked, mark as paid
                    cell.classList.add('commission-paid');
                    cell.classList.remove('commission-needed');
                } else {
                    // If not checked, mark as needing attention
                    cell.classList.remove('commission-paid');
                    cell.classList.add('commission-needed');
                    
                    // Get information about which agents need payment
                    const caseId = checkbox.dataset.caseId;
                    const installmentNum = checkbox.dataset.installment;
                    
                    // Get agent information if available
                    const agentInfo = getAgentPaymentInfo(cell, installmentNum);
                    
                    if (agentInfo) {
                        // Add tooltip with agent info
                        const tooltip = document.createElement('div');
                        tooltip.className = 'agent-payment-tooltip';
                        tooltip.innerHTML = agentInfo;
                        cell.appendChild(tooltip);
                        
                        // Show tooltip on cell hover
                        cell.addEventListener('mouseenter', () => {
                            tooltip.style.display = 'block';
                        });
                        
                        cell.addEventListener('mouseleave', () => {
                            tooltip.style.display = 'none';
                        });
                    }
                }
                
                checkbox.addEventListener('change', function() {
                    console.log("Commission checkbox changed:", this.checked);
                    
                    // Handle commission checkbox interaction
                    handleCommissionCheckboxChange(this, label);
                });
            });
            
            // Set up modal event listeners
            setupCommissionModal();
        }
        
        // Global variable to track the current checkbox being processed
        let currentCommissionCheckbox = null;

        // Handle commission checkbox change
        function handleCommissionCheckboxChange(checkbox, label) {
            console.log("Handling commission checkbox change");
            
            const caseId = checkbox.dataset.caseId;
            const installmentNumber = checkbox.dataset.installment;
            const isChecked = checkbox.checked;
            
            console.log(`Case ID: ${caseId}, Installment: ${installmentNumber}, Checked: ${isChecked}`);
            
            // Store the current checkbox for use in modal
            currentCommissionCheckbox = checkbox;
            
            // If the checkbox is being checked (from unchecked to checked)
            if (isChecked) {
                // Show the invoice modal
                showCommissionInvoiceModal();
                
                // The actual update will happen when the user confirms in the modal
                return;
            } else {
                // For unchecking, just update status directly
                updateCommissionUI(checkbox, false);
                updateCommissionStatus(caseId, installmentNumber, 0, '');
            }
        }

        // Set up commission modal
        function setupCommissionModal() {
            const modal = document.getElementById('commissionInvoiceModal');
            const closeBtn = document.querySelector('.modal-close');
            const saveBtn = document.getElementById('saveInvoiceButton');
            const cancelBtn = document.getElementById('cancelInvoiceButton');
            const invoiceInput = document.getElementById('commissionInvoiceNumber');
            
            // Close button click
            closeBtn.addEventListener('click', () => {
                hideCommissionInvoiceModal();
                resetCheckbox();
            });
            
            // Cancel button click
            cancelBtn.addEventListener('click', () => {
                hideCommissionInvoiceModal();
                resetCheckbox();
            });
            
            // Save button click
            saveBtn.addEventListener('click', () => {
                const invoiceNumber = invoiceInput.value.trim();
                saveCommissionInvoice(invoiceNumber);
            });
            
            // Press Enter to save
            invoiceInput.addEventListener('keyup', (e) => {
                if (e.key === 'Enter') {
                    const invoiceNumber = invoiceInput.value.trim();
                    saveCommissionInvoice(invoiceNumber);
                }
            });
            
            // Close when clicking outside the modal
            window.addEventListener('click', (e) => {
                if (e.target === modal) {
                    hideCommissionInvoiceModal();
                    resetCheckbox();
                }
            });
        }

        // Show the commission invoice modal
        function showCommissionInvoiceModal() {
            const modal = document.getElementById('commissionInvoiceModal');
            const invoiceInput = document.getElementById('commissionInvoiceNumber');
            
            // Clear previous input
            invoiceInput.value = '';
            
            // Show the modal
            modal.style.display = 'block';
            
            // Focus on the input field
            setTimeout(() => {
                invoiceInput.focus();
            }, 100);
        }

        // Hide the commission invoice modal
        function hideCommissionInvoiceModal() {
            const modal = document.getElementById('commissionInvoiceModal');
            modal.style.display = 'none';
        }

        // Reset the checkbox to its previous state
        function resetCheckbox() {
            if (currentCommissionCheckbox) {
                currentCommissionCheckbox.checked = false;
            }
        }

        // Save the commission invoice number
        function saveCommissionInvoice(invoiceNumber) {
            if (!currentCommissionCheckbox) {
                console.error("No active checkbox found");
                hideCommissionInvoiceModal();
                return;
            }
            
            const caseId = currentCommissionCheckbox.dataset.caseId;
            const installmentNumber = currentCommissionCheckbox.dataset.installment;
            
            // Update UI
            updateCommissionUI(currentCommissionCheckbox, true, invoiceNumber);
            
            // Update server
            updateCommissionStatus(caseId, installmentNumber, 1, invoiceNumber);
            
            // Hide modal
            hideCommissionInvoiceModal();
        }

        // Update commission UI based on state
        function updateCommissionUI(checkbox, isChecked, invoiceNumber = '') {
            const cell = checkbox.closest('td');
            
            if (!cell) return;
            
            if (isChecked) {
                // Update style for checked state
                cell.classList.add('commission-paid');
                cell.classList.remove('commission-needed');
                
                // Remove any existing tooltip
                const existingTooltip = cell.querySelector('.agent-payment-tooltip');
                if (existingTooltip) existingTooltip.remove();
                
                // Add invoice info if provided
                if (invoiceNumber) {
                    const invoiceInfo = document.createElement('div');
                    invoiceInfo.className = 'commission-invoice-info';
                    invoiceInfo.innerHTML = '<i class="fa-solid fa-receipt"></i>';
                    
                    const tooltip = document.createElement('div');
                    tooltip.className = 'commission-invoice-tooltip';
                    tooltip.textContent = `Prowizja wypłacona. Faktura: ${invoiceNumber}`;
                    
                    invoiceInfo.appendChild(tooltip);
                    cell.querySelector('.commission-checkbox-container').appendChild(invoiceInfo);
                }
            } else {
                // Update style for unchecked state
                cell.classList.remove('commission-paid');
                cell.classList.add('commission-needed');
                
                // Remove invoice info if exists
                const existingInfo = cell.querySelector('.commission-invoice-info');
                if (existingInfo) existingInfo.remove();
                
                // Check if we need to re-add agent tooltip
                if (!cell.querySelector('.agent-payment-tooltip')) {
                    const agentInfo = getAgentPaymentInfo(cell, checkbox.dataset.installment);
                    
                    if (agentInfo) {
                        const tooltip = document.createElement('div');
                        tooltip.className = 'agent-payment-tooltip';
                        tooltip.innerHTML = agentInfo;
                        cell.appendChild(tooltip);
                        
                        cell.addEventListener('mouseenter', () => {
                            tooltip.style.display = 'block';
                        });
                        
                        cell.addEventListener('mouseleave', () => {
                            tooltip.style.display = 'none';
                        });
                    }
                }
            }
            
            // Show notification
            const message = isChecked 
                ? `Prowizja za ratę ${checkbox.dataset.installment} została oznaczona jako wypłacona.` 
                : `Prowizja za ratę ${checkbox.dataset.installment} została oznaczona jako niewypłacona.`;
            
            showNotification(message, isChecked ? 'info' : 'error');
        }

        // Function to update commission status on server
        function updateCommissionStatus(caseId, installmentNumber, status, invoiceNumber = '') {
            console.log(`Updating commission status: Case ${caseId}, Installment ${installmentNumber}, Status ${status}, Invoice: ${invoiceNumber}`);
            
            // Debug JSON payload
            const jsonPayload = JSON.stringify({
                case_id: caseId,
                installment_number: installmentNumber,
                status: status,
                invoice_number: invoiceNumber
            });
            console.log("JSON Payload:", jsonPayload);
            
            // Add debug info
            console.log("Sending AJAX request to /update-commission-status");
            
            // Send AJAX request to update status
            fetch('/update-commission-status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: jsonPayload
            })
            .then(response => {
                console.log("AJAX Response status:", response.status);
                if (!response.ok) {
                    console.error("HTTP Error:", response.status, response.statusText);
                }
                return response.json();
            })
            .then(data => {
                console.log('Commission status update response:', data);
                
                if (data.success) {
                    console.log("Update successful, status saved in database for case", caseId, "installment", installmentNumber);
                    // Pokazuję powiadomienie o sukcesie
                    showNotification(`Prowizja za ratę ${installmentNumber} została zapisana w bazie danych.`, 'info');
                } else {
                    // If there was an error, revert the checkbox state
                    console.error("Error updating commission:", data.message);
                    const checkbox = document.querySelector(`.commission-checkbox[data-case-id="${caseId}"][data-installment="${installmentNumber}"]`);
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;
                        
                        // Also update the cell class
                        updateCommissionUI(checkbox, checkbox.checked);
                        
                        // Show error notification
                        showNotification(`Błąd: ${data.message}`, 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Error updating commission status:', error);
                console.error('Error details:', error.stack);
                
                // Revert the checkbox state on error
                const checkbox = document.querySelector(`.commission-checkbox[data-case-id="${caseId}"][data-installment="${installmentNumber}"]`);
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                    
                    // Also update the cell class
                    updateCommissionUI(checkbox, checkbox.checked);
                    
                    // Show error notification
                    showNotification('Błąd połączenia podczas aktualizacji statusu prowizji.', 'error');
                }
            });
        }

        // Hide commission paid columns
        function hideCommissionColumns() {
            console.log("Hiding commission paid columns");
            
            // Build array of column patterns to hide
            const patternsToHide = [
                'installment1_commission_paid',
                'installment2_commission_paid',
                'installment3_commission_paid',
                'final_installment_commission_paid',
                'INSTALLMENT1_COMMISSION_PAID',
                'INSTALLMENT2_COMMISSION_PAID',
                'INSTALLMENT3_COMMISSION_PAID',
                'FINAL_INSTALLMENT_COMMISSION_PAID',
                // Add commission invoice patterns
                'installment1_commission_invoice',
                'installment2_commission_invoice',
                'installment3_commission_invoice',
                'final_installment_commission_invoice',
                'INSTALLMENT1_COMMISSION_INVOICE',
                'INSTALLMENT2_COMMISSION_INVOICE',
                'INSTALLMENT3_COMMISSION_INVOICE',
                'FINAL_INSTALLMENT_COMMISSION_INVOICE'
            ];
            
            // Function to check if column matches any of our patterns
            function shouldHideColumn(columnName) {
                return patternsToHide.some(pattern => 
                    columnName === pattern || 
                    columnName.includes(pattern) ||
                    // Handle case variations
                    columnName.toLowerCase() === pattern.toLowerCase() ||
                    columnName.toLowerCase().includes(pattern.toLowerCase())
                );
            }
            
            // Hide columns based on data-column attribute
            document.querySelectorAll('th[data-column], td').forEach(element => {
                const column = element.getAttribute('data-column');
                if (column && shouldHideColumn(column)) {
                    element.style.display = 'none';
                }
            });
            
            // Also check column text for header cells without data-column
            document.querySelectorAll('th').forEach(header => {
                const headerText = header.textContent.trim();
                if (shouldHideColumn(headerText)) {
                    const index = Array.from(header.parentNode.children).indexOf(header);
                    
                    // Hide this header
                    header.style.display = 'none';
                    
                    // Hide all cells in this column
                    document.querySelectorAll('tr').forEach(row => {
                        const cells = row.querySelectorAll('td');
                        if (cells.length > index) {
                            cells[index].style.display = 'none';
                        }
                    });
                }
            });
        }

        // Get information about agent payments for this installment
        function getAgentPaymentInfo(cell, installmentNum) {
            // Try to get information from parent row
            const row = cell.closest('tr');
            if (!row) return null;
            
            let agentInfo = '';
            
            // Check if we have a commission invoice number for this installment
            const caseId = row.getAttribute('data-id') || '';
            if (caseId) {
                // Get commission invoice field name based on installment number
                let commissionInvoiceField;
                switch(installmentNum) {
                    case '1': commissionInvoiceField = 'installment1_commission_invoice'; break;
                    case '2': commissionInvoiceField = 'installment2_commission_invoice'; break;
                    case '3': commissionInvoiceField = 'installment3_commission_invoice'; break;
                    case '4': commissionInvoiceField = 'final_installment_commission_invoice'; break;
                    default: commissionInvoiceField = '';
                }
                
                // Try to get the invoice number from dataset
                const invoiceNumber = row.getAttribute(`data-${commissionInvoiceField}`) || '';
                if (invoiceNumber) {
                    agentInfo += `<div class="tooltip-section">Faktura prowizji: <strong>${invoiceNumber}</strong></div>`;
                    agentInfo += '<div class="tooltip-separator"></div>';
                }
            }
            
            // Find all agent cells in the row
            const agentCells = row.querySelectorAll('.agent-column');
            agentCells.forEach(agentCell => {
                const agentName = agentCell.dataset.name || agentCell.textContent.trim();
                const rateKey = 'rata' + installmentNum;
                const rateValue = agentCell.dataset[rateKey];
                
                if (rateValue && rateValue !== '0' && rateValue !== '0 zł' && 
                    rateValue !== '0,00' && rateValue !== '0,00 zł') {
                    
                    // Extract numeric value
                    let numericValue = rateValue;
                    if (typeof numericValue === 'string') {
                        numericValue = numericValue.replace(/[^\d,.]/g, '');
                        numericValue = numericValue.replace(',', '.');
                        numericValue = parseFloat(numericValue);
                    }
                    
                    // If rate is positive, add to agent info
                    if (numericValue > 0) {
                        agentInfo += `<strong>${agentName}</strong>: ${rateValue}<br>`;
                    }
                }
            });
            
            return agentInfo;
        }

        // Function to show notification
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification ${type} show`;
            notification.innerHTML = `<i class="fa-solid fa-${type === 'info' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            document.body.appendChild(notification);
            
            // Remove notification after delay
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 500);
            }, 3000);
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
        
        /* Styles for paid installment rows and cells */
        .data-table {
            position: relative;
        }
        
        .data-table tbody tr.has-paid-installment {
            position: relative;
        }
        
        .data-table tbody tr.has-paid-installment::before {
            content: "!";
            position: absolute;
            left: -25px; 
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            background-color: #F44336;
            color: white;
            border-radius: 50%;
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            line-height: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            animation: pulse 1.5s infinite;
            z-index: 10;
        }
        
        /* Style for the red highlighted rate amount */
        .rate-paid {
            position: relative;
            background-color: #ffcdd2 !important;
            color: #d32f2f !important;
            font-weight: bold !important;
            border: 1px solid #ef5350 !important;
        }
        
        /* Style for rates that need commission payment */
        .commission-needed {
            position: relative;
            overflow: visible !important;
        }
        
        .commission-needed::after {
            content: "⚠️";
            position: absolute;
            top: -10px;
            right: -10px;
            font-size: 16px;
            animation: pulse 1.5s infinite;
            filter: drop-shadow(0 0 2px rgba(255,255,255,0.8));
            z-index: 15;
        }
        
        /* Zmiana koloru po zaznaczeniu prowizji */
        .commission-paid {
            background-color: #e8f5e9 !important;
            color: #2E7D32 !important;
            transition: background-color 0.5s ease;
            border: 1px solid #66BB6A !important;
        }
        
        .commission-paid::after {
            content: "✓";
            position: absolute;
            top: 5px;
            right: 5px;
            color: #2E7D32;
            font-size: 14px;
            font-weight: bold;
            z-index: 15;
        }
        
        /* Wyraziste oznaczenie dla nieprzeczytanych prowizji */
        .commission-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #FF5722;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            animation: pulse 1.5s infinite;
            z-index: 15;
        }
        
        /* Style for the tooltip */
        .agent-payment-tooltip {
            display: none;
            position: absolute;
            bottom: calc(100% + 5px);
            left: 50%;
            transform: translateX(-50%);
            background-color: #fff;
            color: #333;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 100;
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            border: 1px solid #ddd;
            min-width: 150px;
            font-weight: normal;
        }
        
        .agent-payment-tooltip::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #fff transparent transparent transparent;
        }
        
        .agent-payment-tooltip strong {
            color: #1976D2;
        }
        
        td:hover .agent-payment-tooltip {
            display: block;
        }
        
        /* Styles for invoice information in tooltip */
        .tooltip-section {
            padding: 4px 0;
        }
        
        .tooltip-separator {
            height: 1px;
            background-color: #e0e0e0;
            margin: 5px 0;
        }
        
        /* Styles for commission payment checkbox */
        .commission-checkbox-container {
            margin-top: 8px;
            padding-top: 5px;
            border-top: 1px dotted #e0e0e0;
            font-size: 12px;
        }
        
        .commission-checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            color: #666;
            font-weight: normal;
            transition: color 0.3s ease;
        }
        
        .commission-checkbox-label:hover {
            color: #333;
        }
        
        .commission-checkbox {
            margin-right: 6px;
            cursor: pointer;
        }
        
        /* Additional animation for paid commissions */
        .commission-paid {
            background-color: #e8f5e9 !important;
            transition: background-color 0.5s ease;
        }
        
        .commission-checkbox:checked + span {
            color: #2E7D32;
            font-weight: 500;
        }
        
        /* Additional padding for the table to accommodate the exclamation marks */
        #dataTable {
            padding-left: 30px;
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
        
        /* Hide commission paid columns */
        th[data-column^="installment"][data-column$="_commission_paid"],
        td[data-column^="installment"][data-column$="_commission_paid"],
        th[data-column^="INSTALLMENT"][data-column$="_COMMISSION_PAID"],
        td[data-column^="INSTALLMENT"][data-column$="_COMMISSION_PAID"],
        th[data-column="final_installment_commission_paid"],
        td[data-column="final_installment_commission_paid"],
        th[data-column="FINAL_INSTALLMENT_COMMISSION_PAID"],
        td[data-column="FINAL_INSTALLMENT_COMMISSION_PAID"] {
            display: none !important;
        }
        
        /* Stylowanie legendy prowizji */
        .commission-legend {
            margin: 30px auto;
            max-width: 800px;
            padding: 15px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .commission-legend h3 {
            color: #1976D2;
            text-align: center;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .legend-items {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .legend-symbol {
            width: 80px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            position: relative;
        }
        
        .legend-description {
            font-size: 14px;
            color: #555;
        }
        
        /* Nowa, elegancka legenda */
        .status-legend {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 25px;
            margin: 20px auto;
            max-width: 800px;
            padding: 8px 15px;
            background-color: #f8f9fa;
            border-radius: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .legend-icon {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .legend-text {
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }
        
        /* Ikony statusów */
        .paid-rate {
            background-color: #ef5350;
            border: 1px solid #d32f2f;
        }
        
        .commission-pending {
            background-color: #ffb74d;
            border: 1px solid #f57c00;
        }
        
        .commission-complete {
            background-color: #66bb6a;
            border: 1px solid #388e3c;
        }
        
        /* Style for commission indicators */
        .rate-paid {
            position: relative;
            background-color: rgba(239, 83, 80, 0.15) !important;
            color: #d32f2f !important;
            font-weight: 600 !important;
            border-left: 3px solid #ef5350 !important;
        }
        
        .commission-needed {
            position: relative;
            background-color: rgba(255, 183, 77, 0.15) !important;
            color: #f57c00 !important;
            font-weight: 600 !important;
            border-left: 3px solid #ffb74d !important;
        }
        
        .commission-needed::after {
            content: "●";
            position: absolute;
            top: 5px;
            right: 5px;
            color: #ffb74d;
            font-size: 10px;
        }
        
        .commission-paid {
            background-color: rgba(102, 187, 106, 0.15) !important;
            color: #388e3c !important;
            font-weight: 600 !important;
            border-left: 3px solid #66bb6a !important;
        }
        
        .commission-paid::after {
            content: "●";
            position: absolute;
            top: 5px;
            right: 5px;
            color: #66bb6a;
            font-size: 10px;
        }
        
        /* Wyraziste oznaczenie dla prowizji do wypłaty */
        .commission-badge {
            position: absolute;
            top: 5px;
            right: 20px;
            color: #f57c00;
            font-size: 10px;
            font-weight: bold;
        }

        /* Superadmin badge */
        .superadmin-badge {
            background-color: #ff9800;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
            font-size: 0.9rem;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(255, 152, 0, 0.3);
            max-width: fit-content;
        }

        /* Modal styles for commission invoice */
        .modal {
            display: none;
            position: fixed;
            z-index: 1500;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            animation: fadeIn 0.3s ease;
            backdrop-filter: blur(3px);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideDown {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 25px;
            width: 400px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            animation: slideDown 0.3s ease;
            position: relative;
        }

        .modal-close {
            position: absolute;
            top: 10px;
            right: 15px;
            color: #aaa;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: #333;
        }

        .modal h3 {
            margin-top: 0;
            color: var(--primary-dark);
            font-size: 1.4rem;
            margin-bottom: 15px;
        }

        .modal p {
            color: #666;
            margin-bottom: 20px;
        }

        .modal-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            margin-bottom: 20px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }

        .modal-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(100, 181, 246, 0.15);
            outline: none;
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .modal-button {
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .save-button {
            background-color: var(--primary-color);
            color: white;
        }

        .save-button:hover {
            background-color: var(--primary-dark);
        }

        .cancel-button {
            background-color: #f5f5f5;
            color: #666;
        }

        .cancel-button:hover {
            background-color: #e0e0e0;
        }
        
        /* Style for commission invoice information */
        .commission-invoice-info {
            display: inline-flex;
            align-items: center;
            margin-left: 8px;
            position: relative;
            cursor: pointer;
        }
        
        .commission-invoice-info i {
            color: #4CAF50;
            font-size: 1rem;
        }
        
        .commission-invoice-tooltip {
            display: none;
            position: absolute;
            bottom: calc(100% + 8px);
            left: 50%;
            transform: translateX(-50%);
            background-color: #fff;
            color: #333;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 100;
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            border: 1px solid #e0e0e0;
            min-width: 180px;
            font-weight: normal;
        }
        
        .commission-invoice-tooltip::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #fff transparent transparent transparent;
        }
        
        .commission-invoice-info:hover .commission-invoice-tooltip {
            display: block;
        }

        /* Style for rate cells with payment info */
        .has-payment-info {
            position: relative;
            cursor: pointer;
        }
        
        .has-payment-info:hover {
            background-color: rgba(0, 0, 0, 0.03);
        }
        
        .has-payment-info:after {
            content: 'ℹ️';
            position: absolute;
            top: 2px;
            right: 2px;
            font-size: 10px;
            opacity: 0.5;
        }
        
        .has-payment-info:hover:after {
            opacity: 1;
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

    <!-- Commission Invoice Modal -->
    <div id="commissionInvoiceModal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h3>Podaj numer faktury</h3>
            <p>Wprowadź numer faktury dla prowizji:</p>
            <input type="text" id="commissionInvoiceNumber" placeholder="Numer faktury" class="modal-input">
            <div class="modal-buttons">
                <button id="saveInvoiceButton" class="modal-button save-button">Zapisz</button>
                <button id="cancelInvoiceButton" class="modal-button cancel-button">Anuluj</button>
            </div>
        </div>
    </div>

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