<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/table.css">
    <link rel="shortcut icon" href="/assets/images/favicon.png" type="image/x-icon">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Tabela</title>
    <script src="/assets/js/responsive.js" defer></script>
    
    <!-- Additional responsive styles for tables -->
    <style>
        @media (max-width: 768px) {
            .data-table {
                min-width: 800px;
            }
            
            .tapped {
                background-color: rgba(33, 150, 243, 0.1);
            }
            
            .expanded {
                white-space: normal;
                word-break: break-word;
            }
            
            .swipe-indicator {
                display: block;
                text-align: center;
                padding: 10px;
                color: #6c757d;
                font-size: 0.8rem;
                animation: fadeInOut 2s ease-in-out infinite;
            }
            
            @keyframes fadeInOut {
                0% { opacity: 0.5; }
                50% { opacity: 1; }
                100% { opacity: 0.5; }
            }
        }
    </style>
    
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
            
            // Initialize commission modal
            setupCommissionModal();
            
            // Initialize row selection functionality
            initRowSelection();
            
            // Initialize row hover tooltips
            initRowHoverTooltips();
            
            // Add class to Tak/Nie cells that don't have styling
            document.querySelectorAll('.data-table td').forEach(cell => {
                if (cell.textContent.trim() === 'Tak' && !cell.querySelector('.status') && !cell.classList.contains('tak')) {
                    cell.innerHTML = '<span class="tak">Tak</span>';
                }
                if (cell.textContent.trim() === 'Nie' && !cell.querySelector('.status') && !cell.classList.contains('nie')) {
                    cell.innerHTML = '<span class="nie">Nie</span>';
                }
            });
            
            // Ensure tooltips work on has-payment-info elements
            document.querySelectorAll('.has-payment-info').forEach(element => {
                // Show tooltip on mouse enter
                element.addEventListener('mouseenter', function() {
                    const tooltip = this.querySelector('.agent-payment-tooltip');
                    if (tooltip) {
                        tooltip.style.display = 'block';
                    }
                });
                
                // Hide tooltip on mouse leave
                element.addEventListener('mouseleave', function() {
                    const tooltip = this.querySelector('.agent-payment-tooltip');
                    if (tooltip) {
                        tooltip.style.display = 'none';
                    }
                });
            });
            
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
            
            // Function to initialize row selection
            function initRowSelection() {
                console.log("Initializing row selection");
                
                // Add selection column to the table header if it doesn't exist
                const table = document.querySelector('.data-table');
                if (!table) return;
                
                const headerRow = table.querySelector('thead tr');
                if (!headerRow) return;
                
                // Insert selection column header if it doesn't exist
                if (!headerRow.querySelector('.select-checkbox')) {
                    const selectHeader = document.createElement('th');
                    selectHeader.className = 'select-checkbox';
                    selectHeader.innerHTML = '<i class="fa-solid fa-check-square"></i>';
                    headerRow.insertBefore(selectHeader, headerRow.firstChild);
                }
                
                // Add selection checkbox to each row
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    // Skip if the row already has a checkbox
                    if (row.querySelector('.select-checkbox')) return;
                    
                    // Create a cell with a checkbox
                    const selectCell = document.createElement('td');
                    selectCell.className = 'select-checkbox';
                    selectCell.innerHTML = '<input type="radio" name="selected-row" class="row-select-checkbox">';
                    
                    // Insert at the beginning of the row
                    row.insertBefore(selectCell, row.firstChild);
                    
                    // Get case ID from the row
                    const caseId = row.getAttribute('data-id');
                    if (caseId) {
                        selectCell.querySelector('input').dataset.caseId = caseId;
                    }
                    
                    // Add click event for the entire row
                    row.addEventListener('click', function(e) {
                        // Don't select row if clicking on specific elements
                        if (e.target.matches('.commission-checkbox, .edit-button, a, button, .agent-column, .agent-details, .agent-details-row')) {
                            return;
                        }
                        
                        // Check the radio button in this row
                        const radio = this.querySelector('.row-select-checkbox');
                        if (radio) {
                            radio.checked = true;
                            
                            // Dispatch a change event to trigger handlers
                            radio.dispatchEvent(new Event('change'));
                        }
                    });
                    
                    // Add click event for the checkbox itself
                    selectCell.querySelector('input').addEventListener('change', function() {
                        // Unselect all rows
                        rows.forEach(r => r.classList.remove('selected-row'));
                        
                        // Select this row
                        if (this.checked) {
                            row.classList.add('selected-row');
                            
                            // Get case ID and store it globally for other functions
                            const caseId = this.dataset.caseId;
                            if (caseId) {
                                window.selectedCaseId = caseId;
                                console.log("Selected case ID:", caseId);
                                
                                // Enable commission checkboxes only for this case
                                toggleCommissionCheckboxes(caseId);
                                
                                // Fetch and display agents for this case
                                fetchAgentsForCase(caseId);
                            }
                        }
                    });
                });
                
                // Initially disable all commission checkboxes
                toggleCommissionCheckboxes(null);
            }
            
            // Function to toggle commission checkboxes based on selected case
            function toggleCommissionCheckboxes(selectedCaseId) {
                console.log("Toggling commission checkboxes for case ID:", selectedCaseId);
                
                document.querySelectorAll('.commission-checkbox').forEach(checkbox => {
                    const caseId = checkbox.dataset.caseId;
                    
                    // Enable checkbox only if it belongs to the selected case
                    if (selectedCaseId && caseId === selectedCaseId) {
                        checkbox.disabled = false;
                        checkbox.closest('label').title = "Oznacz prowizję jako wypłaconą";
                    } else {
                        checkbox.disabled = true;
                        checkbox.closest('label').title = "Zaznacz wiersz, aby móc edytować status prowizji";
                    }
                });
            }
            
            // Function to fetch agents for a specific case
            function fetchAgentsForCase(caseId) {
                console.log("Fetching agents for case ID:", caseId);
                
                // Make AJAX request to get agents assigned to this case
                fetch(`/get-case-agents?case_id=${caseId}`)
                    .then(response => response.json())
                    .then(data => {
                        console.log("Agents for case:", data);
                        
                        // Update agent select in the commission modal
                        updateAgentOptions(data.agents);
                    })
                    .catch(error => {
                        console.error("Error fetching case agents:", error);
                    });
            }
            
            // Function to update agent options in the commission modal
            function updateAgentOptions(agents) {
                const agentSelect = document.getElementById('agentSelect');
                if (!agentSelect) return;
                
                // Keep default option
                const defaultOption = agentSelect.querySelector('option[value=""]');
                
                // Clear existing options except default
                agentSelect.innerHTML = '';
                
                // Add default option back
                if (defaultOption) {
                    agentSelect.appendChild(defaultOption);
                } else {
                    const newDefaultOption = document.createElement('option');
                    newDefaultOption.value = '';
                    newDefaultOption.textContent = '-- Wybierz agenta --';
                    agentSelect.appendChild(newDefaultOption);
                }
                
                // Add agents for this case
                if (agents && agents.length > 0) {
                    agents.forEach(agent => {
                        const option = document.createElement('option');
                        option.value = agent.agent_id;
                        option.textContent = `${agent.imie} ${agent.nazwisko}`;
                        option.dataset.name = `${agent.imie} ${agent.nazwisko}`;
                        option.dataset.role = agent.rola || '';
                        option.dataset.percentage = agent.percentage || '';
                        agentSelect.appendChild(option);
                    });
                } else {
                    // Add message if no agents
                    const option = document.createElement('option');
                    option.disabled = true;
                    option.textContent = 'Brak przypisanych agentów';
                    agentSelect.appendChild(option);
                }
            }
            
            // Function to initialize agent payment notifications
            function initAgentPaymentNotifications() {
                console.log("Initializing agent payment notifications");
                
                // Look for all rate cells in the table
                document.querySelectorAll('.data-table tbody td').forEach(cell => {
                    // Check if this is a rate cell
                    const columnHeader = getColumnForCell(cell);
                    if (!columnHeader || !columnHeader.match(/^Rata \d+$/)) return;
                    
                    // Get the rate number
                    const rateNumber = columnHeader.match(/\d+/)[0];
                    if (!rateNumber) return;
                    
                    // Get the row and case ID
                    const row = cell.closest('tr');
                    if (!row) return;
                    
                    const caseId = row.getAttribute('data-id');
                    if (!caseId) return;
                    
                    // Get all agent cells in this row
                    const agentCells = Array.from(row.querySelectorAll('.agent-column'));
                    if (agentCells.length === 0) return;
                    
                    // Check if installment is paid
                    const paidColumn = `Opłacona ${rateNumber}`;
                    const paidCell = Array.from(row.querySelectorAll('td')).find(td => {
                        const header = getColumnForCell(td);
                        return header === paidColumn;
                    });
                    
                    const isPaid = paidCell && paidCell.textContent.includes('Tak');
                    
                    // If paid, add special class
                    if (isPaid) {
                        cell.classList.add('rate-paid');
                        row.classList.add('has-paid-installment');
                    }
                    
                    // Create tooltip content
                    let tooltipContent = '';
                    
                    // Get invoice number for this installment if available
                    let invoiceField;
                    switch(rateNumber) {
                        case '1': invoiceField = 'installment1_paid_invoice'; break;
                        case '2': invoiceField = 'installment2_paid_invoice'; break;
                        case '3': invoiceField = 'installment3_paid_invoice'; break;
                        case '4': invoiceField = 'final_installment_paid_invoice'; break;
                    }
                    
                    // If we have invoice data for this installment, add to tooltip
                    if (row.hasAttribute(invoiceField) && row[invoiceField]) {
                        const invoiceNumber = row[invoiceField];
                        tooltipContent += `<div class="tooltip-section"><b>Faktura klienta:</b> ${invoiceNumber}</div>`;
                        tooltipContent += '<div class="tooltip-separator"></div>';
                    }
                    
                    // Add commission payment info
                    let commissionField;
                    switch(rateNumber) {
                        case '1': commissionField = 'installment1_commission_paid'; break;
                        case '2': commissionField = 'installment2_commission_paid'; break;
                        case '3': commissionField = 'installment3_commission_paid'; break;
                        case '4': commissionField = 'final_installment_commission_paid'; break;
                    }
                    
                    // Check if commission is paid
                    const commissionPaid = row.hasAttribute(commissionField) && row[commissionField] == 1;
                    
                    // Add commission invoice if available
                    let commissionInvoiceField;
                    switch(rateNumber) {
                        case '1': commissionInvoiceField = 'installment1_commission_invoice'; break;
                        case '2': commissionInvoiceField = 'installment2_commission_invoice'; break;
                        case '3': commissionInvoiceField = 'installment3_commission_invoice'; break;
                        case '4': commissionInvoiceField = 'final_installment_commission_invoice'; break;
                    }
                    
                    if (commissionPaid && row.hasAttribute(commissionInvoiceField) && row[commissionInvoiceField]) {
                        const commissionInvoice = row[commissionInvoiceField];
                        tooltipContent += `<div class="tooltip-section"><b>Faktura prowizji:</b> ${commissionInvoice}</div>`;
                        tooltipContent += '<div class="tooltip-separator"></div>';
                    }
                    
                    // Get payment amounts for each agent
                    let agentPayments = '';
                    
                    agentCells.forEach(agentCell => {
                        // Get agent name
                        const agentName = agentCell.dataset.name || agentCell.textContent.trim();
                        if (!agentName) return;
                        
                        // Get agent rate amount
                        const rateKey = `rata${rateNumber}`;
                        const rateValue = agentCell.dataset[rateKey];
                        if (!rateValue || rateValue === '0' || rateValue === '0 zł') return;
                        
                        // Check if the value is actually a number > 0
                        const numericValue = parseFloat(rateValue.replace(/[^\d,.]/g, '').replace(',', '.'));
                        if (isNaN(numericValue) || numericValue <= 0) return;
                        
                        // Properly format the amount
                        let formattedAmount = rateValue;
                        if (!formattedAmount.includes('zł')) {
                            formattedAmount += ' zł';
                        }
                        
                        agentPayments += `<div><b>${agentName}:</b> ${formattedAmount}</div>`;
                    });
                    
                    // Add agent payments to tooltip
                    if (agentPayments) {
                        tooltipContent += `<div class="tooltip-section"><b>Płatności dla agentów:</b></div>`;
                        tooltipContent += agentPayments;
                    }
                    
                    // If we have tooltip content, add it
                    if (tooltipContent) {
                        // Create tooltip container
                        const tooltip = document.createElement('div');
                        tooltip.className = 'agent-payment-tooltip';
                        tooltip.innerHTML = tooltipContent;
                        tooltip.style.display = 'none';
                        
                        // Add tooltip to cell
                        cell.appendChild(tooltip);
                        
                        // Mark cell as having tooltip
                        cell.classList.add('has-payment-info');
                        
                        // Show/hide tooltip on hover
                        cell.addEventListener('mouseenter', () => {
                            tooltip.style.display = 'block';
                        });
                        
                        cell.addEventListener('mouseleave', () => {
                            tooltip.style.display = 'none';
                        });
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
            
            // Find all commission checkboxes
            document.querySelectorAll('.commission-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function(e) {
                    const caseId = this.dataset.caseId;
                    const installment = this.dataset.installment;
                    const isChecked = this.checked;
                    
                    if (isChecked) {
                        // If checked, open commission payment modal
                        e.preventDefault(); // Prevent default checkbox behavior
                        this.checked = false; // Uncheck until payment confirmed
                        
                        // Show payment modal for all agents for this case
                        openSimpleCommissionModal(caseId, installment);
                    }
                });
            });
        }
    </script>

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
                <a href="/podsumowanie-spraw" class="cleannav__link" data-tooltip="Podsumowanie Faktur">
                    <i class="fa-solid fa-file-invoice-dollar cleannav__icon"></i>
                </a>
            </li>
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin'): ?>
            <li class="cleannav__item">
                <a href="/zarzadzanie-uzytkownikami" class="cleannav__manage-btn" data-tooltip="Zarządzanie Użytkownikami">
                    <i class="fa-solid fa-users-cog cleannav__icon"></i>
                </a>
            </li>
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

    <!-- Include the commission modal -->
    <?php include_once __DIR__ . '/components/commission_modal.php'; ?>
    
    <!-- Include the commission management JS -->
    <script src="/assets/js/commission-management.js"></script>

    <!-- Add this script at the end of the file before the closing body tag -->
    <script>
        // Mobile-specific enhancements for tables
        document.addEventListener('DOMContentLoaded', function() {
            if (window.innerWidth <= 768) {
                // Add swipe indicators to let users know they can scroll horizontally
                const tableContainers = document.querySelectorAll('.table-container');
                tableContainers.forEach(container => {
                    const swipeIndicator = document.createElement('div');
                    swipeIndicator.className = 'swipe-indicator';
                    swipeIndicator.innerHTML = '<i class="fas fa-arrows-left-right"></i> Przesuń palcem w poziomie, aby zobaczyć więcej';
                    
                    // Insert before the table container
                    container.parentNode.insertBefore(swipeIndicator, container);
                    
                    // Hide indicator after first scroll
                    container.addEventListener('scroll', function() {
                        swipeIndicator.style.display = 'none';
                    }, { once: true });
                });
                
                // Optimize cell content for small screens
                const tableCells = document.querySelectorAll('td');
                tableCells.forEach(cell => {
                    // If cell content is very long, truncate it and add ellipsis
                    if (cell.textContent.length > 20 && 
                        !cell.classList.contains('currency') && 
                        !cell.classList.contains('percentage') &&
                        !cell.classList.contains('action-cell') &&
                        !cell.classList.contains('select-checkbox')) {
                        
                        cell.setAttribute('data-full-content', cell.textContent);
                        cell.textContent = cell.textContent.substring(0, 20) + '...';
                        
                        // Show full content on tap
                        cell.addEventListener('click', function(e) {
                            // Don't trigger if clicking on a link, button, etc.
                            if (e.target.tagName.toLowerCase() === 'a' || 
                                e.target.tagName.toLowerCase() === 'button' ||
                                e.target.tagName.toLowerCase() === 'input') {
                                return;
                            }
                            
                            const fullContent = this.getAttribute('data-full-content');
                            const isExpanded = this.classList.contains('expanded');
                            
                            if (isExpanded) {
                                this.textContent = fullContent.substring(0, 20) + '...';
                                this.classList.remove('expanded');
                            } else {
                                this.textContent = fullContent;
                                this.classList.add('expanded');
                            }
                        });
                    }
                });
                
                // Make agent cells more visible on mobile
                const agentCells = document.querySelectorAll('td[class*="agent"], .agent-name, .agent-highlight');
                agentCells.forEach(cell => {
                    cell.style.fontWeight = 'bold';
                    cell.style.color = '#0277bd';
                });
            }
        });
    </script>
</body>

</html>