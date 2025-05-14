/**
 * Simple Commission Management JS
 * Handles basic functionality for commission payments
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Commission management module loaded');
    
    // Initialize commission payment handlers
    addPayCommissionHandlers();
    
    // Refresh any existing invoice information in the UI
    refreshInvoiceDisplay();
    
    // Initialize modal close buttons
    initModalCloseButtons();
});

/**
 * Updates the display of invoice numbers and agent names in the UI
 */
function refreshInvoiceDisplay() {
    console.log("Refreshing invoice displays");
    
    // Find all commission checkboxes
    document.querySelectorAll('.commission-checkbox').forEach(checkbox => {
        if (checkbox.checked) {
            // For checked checkboxes, find the parent element
            const container = checkbox.closest('.commission-checkbox-container');
            if (!container) return;
            
            // Look for existing invoice display
            let displayEl = container.querySelector('.commission-invoice-display');
            if (!displayEl) {
                // Create a new display element if it doesn't exist
                displayEl = document.createElement('div');
                displayEl.className = 'commission-invoice-display';
                container.appendChild(displayEl);
            }
            
            // Get case ID and installment number
            const caseId = checkbox.dataset.caseId;
            const installment = checkbox.dataset.installment;
            
            // Load invoice information for this case and installment
            loadInvoiceInfo(caseId, installment, displayEl);
        }
    });
}

/**
 * Loads invoice information for a case and installment
 */
function loadInvoiceInfo(caseId, installment, displayElement) {
    // First clear the display element
    displayElement.innerHTML = '<div style="text-align:center;"><i class="fa fa-spinner fa-spin"></i> Ładowanie...</div>';
    
    console.log(`Loading invoice info for case ${caseId}, installment ${installment}`);
    
    // Get the row for this case
    const row = document.querySelector(`tr[data-id="${caseId}"]`);
    if (!row) {
        console.error(`Row for case ${caseId} not found`);
        displayElement.innerHTML = '<div class="error">Błąd: Nie znaleziono wiersza dla tej sprawy</div>';
        return;
    }
    
    // Fetch commission payments data from API
    fetch(`/get-commission-payments?case_id=${caseId}&installment_number=${installment}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server returned ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log("Got commission payments data:", data);
            
            // Add styles if not already added
            addInvoiceDisplayStyles();
            
            if (data.success && data.payments && data.payments.length > 0) {
                // Create display HTML
                let html = '<div class="invoice-info-header">Faktury prowizji:</div>';
                
                // Format each payment
                data.payments.forEach(payment => {
                    // Ensure we have agent name and invoice number
                    const agentName = payment.agent_name || payment.imie + ' ' + payment.nazwisko || 'Agent ' + payment.agent_id || 'Nieznany';
                    const invoiceNum = payment.invoice_number || '---';
                    
                    html += `
                        <div class="invoice-agent-row">
                            <span class="invoice-agent-name">${agentName}:</span>
                            <span class="invoice-number">${invoiceNum}</span>
                        </div>
                    `;
                });
                
                // Update the display element
                displayElement.innerHTML = html;
                
                // Add a CSS class to indicate there are multiple invoices if needed
                if (data.payments.length > 1) {
                    displayElement.classList.add('multiple-invoices');
                }
            } else {
                // If API doesn't return payments, fallback to data attributes
                fallbackToDataAttributes(caseId, installment, displayElement, row);
            }
        })
        .catch(error => {
            console.error('Error fetching commission payments:', error);
            
            // Fallback to data attributes method
            fallbackToDataAttributes(caseId, installment, displayElement, row);
        });
}

/**
 * Fallback method to get invoice info from data attributes
 */
function fallbackToDataAttributes(caseId, installment, displayElement, row) {
    console.log(`Using fallback for case ${caseId}, installment ${installment}`);
    
    // Get the invoice attribute based on installment number
    const invoiceAttr = `data-installment${installment}_commission_invoice`;
    const invoiceNumber = row.getAttribute(invoiceAttr);
    
    if (invoiceNumber) {
        // Create a simple display with the invoice number
        let html = '<div class="invoice-info-header">Faktura prowizji:</div>';
        html += `
            <div class="invoice-agent-row">
                <span class="invoice-agent-name">Faktura:</span>
                <span class="invoice-number">${invoiceNumber}</span>
            </div>
        `;
        
        // Update the display element
        displayElement.innerHTML = html;
    } else {
        displayElement.innerHTML = '<div class="invoice-info-header">Brak danych o fakturach</div>';
    }
}

/**
 * Adds styles for invoice display if not already added
 */
function addInvoiceDisplayStyles() {
    if (document.getElementById('invoice-display-styles')) return;
    
    const style = document.createElement('style');
    style.id = 'invoice-display-styles';
    style.textContent = `
        .commission-invoice-display {
            margin-top: 10px;
            background-color: #f5f5f5;
            border-radius: 4px;
            padding: 8px 10px;
            font-size: 13px;
            border-left: 3px solid #4CAF50;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            max-width: 100%;
            overflow: hidden;
        }
        
        .commission-invoice-display.multiple-invoices {
            border-left-color: #2196F3;
            background-color: #f0f7ff;
        }
        
        .invoice-info-header {
            font-weight: bold;
            margin-bottom: 6px;
            color: #333;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .multiple-invoices .invoice-info-header {
            color: #1565C0;
        }
        
        .invoice-agent-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            padding: 3px 0;
            border-bottom: 1px dotted #ddd;
            align-items: center;
        }
        
        .invoice-agent-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .invoice-agent-name {
            font-weight: 500;
            color: #444;
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-right: 5px;
        }
        
        .invoice-number {
            color: #4CAF50;
            font-family: monospace;
            background: #eef7ee;
            padding: 2px 5px;
            border-radius: 3px;
            border: 1px solid #d4e8d4;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .multiple-invoices .invoice-number {
            color: #1976D2;
            background: #e3f2fd;
            border-color: #bbdefb;
        }
        
        /* Hide field names in blue headers */
        .modal-content h3,
        .installment-heading,
        .blue-header {
            text-transform: capitalize;
            overflow: hidden;
        }
        
        /* Hide technical field names like INSTALLMENT1_COMMISSION_INVOICE */
        [class*="INSTALLMENT"],
        [id*="INSTALLMENT"] {
            display: none !important;
        }
    `;
    
    document.head.appendChild(style);
}

/**
 * Function to format technical field names into user-friendly labels
 * @param {string} fieldName - The technical field name to format
 * @return {string} - User-friendly formatted name
 */
function formatFieldName(fieldName) {
    // Don't show technical names like INSTALLMENT1_COMMISSION_INVOICE
    if (!fieldName || typeof fieldName !== 'string') return '';
    
    // Replace technical names with user-friendly ones
    if (fieldName.includes('INSTALLMENT1_COMMISSION_INVOICE')) return 'Faktura prowizji (rata 1)';
    if (fieldName.includes('INSTALLMENT2_COMMISSION_INVOICE')) return 'Faktura prowizji (rata 2)';
    if (fieldName.includes('INSTALLMENT3_COMMISSION_INVOICE')) return 'Faktura prowizji (rata 3)';
    if (fieldName.includes('INSTALLMENT4_COMMISSION_INVOICE')) return 'Faktura prowizji (rata 4)';
    if (fieldName.includes('FINAL_INSTALLMENT_COMMISSION_INVOICE')) return 'Faktura prowizji (rata końcowa)';
    
    // Default case - just return the field name with first letter capitalized
    return fieldName.charAt(0).toUpperCase() + fieldName.slice(1).toLowerCase();
}

/**
 * Adds click handlers to all commission payment buttons
 */
function addPayCommissionHandlers() {
    const checkboxes = document.querySelectorAll('.commission-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const caseId = this.dataset.caseId;
            const installment = this.dataset.installment;
            const isChecked = this.checked;
            
            if (isChecked) {
                // When checked, open modal for payment
                openSimpleCommissionModal(caseId, installment);
            } else {
                // When unchecked, simply update status to not paid
                updateCommissionStatus(caseId, installment, 0, null);
            }
        });
    });
}

/**
 * Opens the commission modal for payment
 */
function openSimpleCommissionModal(caseId, installmentNumber) {
    // Get modal element
    const modal = document.getElementById('commission-payment-modal');
    
    // Clear previous content
    const modalBody = modal.querySelector('.modal-body');
    modalBody.querySelector('.agent-forms-container').innerHTML = '';
    
    // Update modal title with case name and installment number
    const modalTitle = modal.querySelector('.modal-title');
    modalTitle.textContent = `Wypłata Prowizji - Rata ${installmentNumber}`;
    
    // Store case ID and installment number on the modal for later use
    modal.dataset.caseId = caseId;
    modal.dataset.installmentNumber = installmentNumber;
    
    // Load agents for this case
    fetch(`/get-case-agents?case_id=${caseId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.agents && data.agents.length > 0) {
                // Create a form field for each agent
                data.agents.forEach(agent => createAgentField(agent, installmentNumber));
            } else {
                showMessage("Brak przypisanych agentów do tej sprawy.");
            }
            
            // Show the modal
            modal.style.display = 'block';
        })
        .catch(error => {
            console.error('Error loading agents:', error);
            showMessage("Błąd podczas ładowania agentów. Spróbuj ponownie.");
        });
}

/**
 * Creates a form field for an agent
 */
function createAgentField(agent, installmentNumber) {
    const container = document.querySelector('.agent-forms-container');
    
    // Create agent form element
    const form = document.createElement('div');
    form.className = 'agent-form';
    form.dataset.agentId = agent.agent_id;
    
    // Get agent name
    const agentName = `${agent.imie} ${agent.nazwisko}`;
    
    // Get amount for this agent and installment from dataset attributes
    let rateAmount = getAgentRateAmount(agent, installmentNumber);
    
    // Create form HTML
    form.innerHTML = `
        <div class="agent-info">
            <div class="agent-name">${agentName}</div>
            <div class="agent-rate">${rateAmount} zł</div>
        </div>
        <div class="form-group">
            <label for="invoice-${agent.agent_id}">Numer faktury:</label>
            <input type="text" id="invoice-${agent.agent_id}" class="form-control invoice-number-input" 
                placeholder="Wpisz numer faktury" required>
        </div>
    `;
    
    // Add the form to the container
    container.appendChild(form);
}

/**
 * Gets the payment amount for a specific agent and installment
 */
function getAgentRateAmount(agent, installmentNumber) {
    // Default to 0 if not found
    let amount = 0;
    
    // Try to get the amount from the row's data attributes
    try {
        const caseRow = document.querySelector(`tr[data-id="${document.getElementById('commission-payment-modal').dataset.caseId}"]`);
        
        if (caseRow) {
            // First try to get from agent columns
            const agentCells = caseRow.querySelectorAll('.agent-column');
            
            // Check agent columns
            for (const cell of agentCells) {
                const name = cell.dataset.name;
                
                // Check if this cell contains our agent
                if (name && name.includes(agent.imie) && name.includes(agent.nazwisko)) {
                    // Get the rate amount for this installment
                    amount = cell.dataset[`rata${installmentNumber}`];
                    break;
                }
                
                // Special case for Kuba/Jakub
                if ((agent.imie === 'Kuba' || agent.imie === 'Jakub') && 
                    (name === 'Kuba' || name === 'Jakub' || name === 'Jakub Kowalski')) {
                    amount = cell.dataset[`rata${installmentNumber}`];
                    break;
                }
            }
            
            // If amount found, clean it up (remove formatting)
            if (amount) {
                // Remove currency symbol, spaces, and commas
                amount = amount.replace(/[^\d.,]/g, '').replace(',', '.');
            }
        }
    } catch (e) {
        console.error('Error getting rate amount:', e);
    }
    
    return parseFloat(amount) ? parseFloat(amount).toFixed(2) : '0.00';
}

/**
 * Shows a message in the modal
 */
function showMessage(message) {
    const container = document.querySelector('.agent-forms-container');
    container.innerHTML = `<div class="alert alert-info">${message}</div>`;
}

/**
 * Saves all commission payments (called when "Save" button is clicked)
 */
function saveAllCommissionPayments() {
    console.log("saveAllCommissionPayments called");
    
    const modal = document.getElementById('commission-payment-modal');
    const caseId = modal.dataset.caseId;
    const installmentNumber = modal.dataset.installmentNumber;
    
    console.log(`Processing payments for case ${caseId}, installment ${installmentNumber}`);
    
    // Get all agent forms
    const agentForms = modal.querySelectorAll('.agent-form');
    
    // Check if there are any forms
    if (agentForms.length === 0) {
        showNotification('Brak agentów do zapisania.', 'error');
        return;
    }
    
    // Validate all forms
    let isValid = true;
    let allPromises = [];
    
    // Show loading state
    const saveButton = document.getElementById('save-commission-btn');
    if (saveButton) {
        saveButton.disabled = true;
        saveButton.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Zapisywanie...';
    }
    
    agentForms.forEach(form => {
        const agentId = form.dataset.agentId;
        const invoiceInput = form.querySelector('.invoice-number-input');
        const invoiceNumber = invoiceInput.value.trim();
        
        console.log(`Validating form for agent ${agentId}, invoice: ${invoiceNumber}`);
        
        // Check if invoice number is provided
        if (!invoiceNumber) {
            invoiceInput.classList.add('error');
            isValid = false;
        } else {
            invoiceInput.classList.remove('error');
            
            // Create promise for this payment
            const promise = saveCommissionPayment(caseId, installmentNumber, agentId, invoiceNumber);
            allPromises.push(promise);
        }
    });
    
    // If any form is invalid, show error
    if (!isValid) {
        showNotification('Wypełnij wszystkie pola faktury.', 'error');
        
        // Reset save button
        if (saveButton) {
            saveButton.disabled = false;
            saveButton.innerHTML = 'Zapisz wszystkie';
        }
        
        return;
    }
    
    // Wait for all payments to be saved
    Promise.all(allPromises)
        .then(results => {
            console.log("All commission payments saved successfully:", results);
            
            // Check if all results were successful
            const allSuccessful = results.every(result => result.success === true);
            
            if (allSuccessful) {
            showNotification('Wszystkie płatności prowizji zostały zapisane.', 'success');
            
            // Update UI to show payment is done
            const checkbox = document.querySelector(`#commission-${caseId}-${installmentNumber}`);
            if (checkbox) {
                checkbox.checked = true;
                
                // Refresh the invoice display
                setTimeout(() => refreshInvoiceDisplay(), 500);
            }
            
            // Close the modal
            closeCommissionModal();
            } else {
                // Show errors for failed payments
                const failedResults = results.filter(result => result.success !== true);
                console.error("Some commission payments failed:", failedResults);
                
                if (failedResults.length > 0) {
                    showNotification(`Błąd podczas zapisywania ${failedResults.length} płatności prowizji.`, 'error');
                }
                
                // Reset save button
                if (saveButton) {
                    saveButton.disabled = false;
                    saveButton.innerHTML = 'Zapisz wszystkie';
                }
            }
        })
        .catch(error => {
            console.error('Error saving payments:', error);
            showNotification('Wystąpił błąd podczas zapisywania płatności: ' + error.message, 'error');
            
            // Reset save button
            if (saveButton) {
                saveButton.disabled = false;
                saveButton.innerHTML = 'Zapisz wszystkie';
            }
        });
}

/**
 * Saves a single commission payment
 */
function saveCommissionPayment(caseId, installmentNumber, agentId, invoiceNumber) {
    console.log(`Saving commission payment: case=${caseId}, installment=${installmentNumber}, agent=${agentId}, invoice=${invoiceNumber}`);
    
    const data = {
        case_id: caseId,
        installment_number: installmentNumber,
        status: 1,
        invoice_number: invoiceNumber,
        agent_id: agentId
    };
    
    console.log("Commission payment data:", data);
    
    return fetch('/update-commission-status', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Server returned ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log("Commission payment response:", data);
        return data;
    })
    .catch(error => {
        console.error("Error saving commission payment:", error);
        throw error;
    });
}

/**
 * Updates commission status without invoice number (for unchecking)
 */
function updateCommissionStatus(caseId, installmentNumber, status, invoiceNumber = null) {
    console.log(`Updating commission status: case=${caseId}, installment=${installmentNumber}, status=${status}, invoice=${invoiceNumber}`);
    
    const data = {
        case_id: caseId,
        installment_number: installmentNumber,
        status: status,
        invoice_number: invoiceNumber
    };
    
    console.log("Commission status update data:", data);
    
    fetch('/update-commission-status', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Server returned ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log("Commission status update response:", data);
        
        if (data.success) {
            showNotification(status ? 'Prowizja oznaczona jako wypłacona.' : 'Prowizja oznaczona jako niewypłacona.', 'info');
            
            // Refresh the invoice display
            setTimeout(() => refreshInvoiceDisplay(), 500);
        } else {
            showNotification('Błąd aktualizacji statusu prowizji: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error updating commission status:', error);
        showNotification('Wystąpił błąd podczas aktualizacji statusu: ' + error.message, 'error');
    });
}

/**
 * Closes the commission modal
 */
function closeCommissionModal() {
    const modal = document.getElementById('commission-payment-modal');
    modal.style.display = 'none';
}

/**
 * Shows a notification message
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type} show`;
    
    notification.innerHTML = `
        <i class="fa-solid fa-${type === 'error' ? 'exclamation' : type === 'success' ? 'check' : 'info'}-circle"></i>
        ${message}
    `;
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

/**
 * Initializes modal close buttons
 */
function initModalCloseButtons() {
    const closeButtons = document.querySelectorAll('.close');
    
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            closeCommissionModal();
        });
    });
    
    // Also add event listener to the cancel button in the commission modal
    const cancelBtn = document.getElementById('cancel-commission-btn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            closeCommissionModal();
        });
    }
} 