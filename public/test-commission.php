<?php
// Initialize session
session_start();

// Set user session if not set for testing purposes
if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = 'test_user';
    $_SESSION['user_role'] = 'admin';
}

// Connect to database
require_once __DIR__ . '/../config/database.php';
global $pdo;

// Get some test data
$query = "SELECT id, client_first_name, client_last_name, 
          installment1_amount, installment1_paid, installment1_commission_paid,
          installment2_amount, installment2_paid, installment2_commission_paid,
          installment3_amount, installment3_paid, installment3_commission_paid,
          final_installment_amount, final_installment_paid, final_installment_commission_paid
          FROM test2 LIMIT 5";
$stmt = $pdo->query($query);
$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commission Test Page</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .commission-checkbox-container { margin-top: 5px; }
        .commission-paid { background-color: #e8f5e9 !important; }
        .commission-needed { background-color: #fff3e0 !important; }
        .status { padding: 2px 6px; border-radius: 3px; font-size: 12px; }
        .status-yes { background-color: #e8f5e9; color: #2e7d32; }
        .status-no { background-color: #ffebee; color: #c62828; }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: #f2f2f2;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            transform: translateX(120%);
            transition: transform 0.3s ease;
        }
        .notification.show {
            transform: translateX(0);
        }
        .notification.info { background-color: #e8f5e9; color: #2e7d32; }
        .notification.error { background-color: #ffebee; color: #c62828; }
    </style>
</head>
<body>
    <h1>Commission Status Test Page</h1>
    <p>This page tests the commission status update functionality.</p>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Client</th>
                <th>Installment 1</th>
                <th>Installment 2</th>
                <th>Installment 3</th>
                <th>Installment 4</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cases as $case): ?>
            <tr>
                <td><?= $case['id'] ?></td>
                <td><?= $case['client_first_name'] . ' ' . $case['client_last_name'] ?></td>
                
                <td class="<?= $case['installment1_paid'] ? ($case['installment1_commission_paid'] ? 'commission-paid' : 'commission-needed') : '' ?>">
                    <?= number_format((float)$case['installment1_amount'], 2, ',', ' ') ?> zł
                    <?php if ($case['installment1_paid']): ?>
                        <div class="commission-checkbox-container">
                            <label class="commission-checkbox-label" title="Oznacz prowizję jako wypłaconą">
                                <input type="checkbox" 
                                       <?= $case['installment1_commission_paid'] ? 'checked' : '' ?> 
                                       class="commission-checkbox" 
                                       data-case-id="<?= $case['id'] ?>" 
                                       data-installment="1">
                                <span>Prowizja wypłacona</span>
                            </label>
                        </div>
                    <?php endif; ?>
                </td>
                
                <td class="<?= $case['installment2_paid'] ? ($case['installment2_commission_paid'] ? 'commission-paid' : 'commission-needed') : '' ?>">
                    <?= number_format((float)$case['installment2_amount'], 2, ',', ' ') ?> zł
                    <?php if ($case['installment2_paid']): ?>
                        <div class="commission-checkbox-container">
                            <label class="commission-checkbox-label" title="Oznacz prowizję jako wypłaconą">
                                <input type="checkbox" 
                                       <?= $case['installment2_commission_paid'] ? 'checked' : '' ?> 
                                       class="commission-checkbox" 
                                       data-case-id="<?= $case['id'] ?>" 
                                       data-installment="2">
                                <span>Prowizja wypłacona</span>
                            </label>
                        </div>
                    <?php endif; ?>
                </td>
                
                <td class="<?= $case['installment3_paid'] ? ($case['installment3_commission_paid'] ? 'commission-paid' : 'commission-needed') : '' ?>">
                    <?= number_format((float)$case['installment3_amount'], 2, ',', ' ') ?> zł
                    <?php if ($case['installment3_paid']): ?>
                        <div class="commission-checkbox-container">
                            <label class="commission-checkbox-label" title="Oznacz prowizję jako wypłaconą">
                                <input type="checkbox" 
                                       <?= $case['installment3_commission_paid'] ? 'checked' : '' ?> 
                                       class="commission-checkbox" 
                                       data-case-id="<?= $case['id'] ?>" 
                                       data-installment="3">
                                <span>Prowizja wypłacona</span>
                            </label>
                        </div>
                    <?php endif; ?>
                </td>
                
                <td class="<?= $case['final_installment_paid'] ? ($case['final_installment_commission_paid'] ? 'commission-paid' : 'commission-needed') : '' ?>">
                    <?= number_format((float)$case['final_installment_amount'], 2, ',', ' ') ?> zł
                    <?php if ($case['final_installment_paid']): ?>
                        <div class="commission-checkbox-container">
                            <label class="commission-checkbox-label" title="Oznacz prowizję jako wypłaconą">
                                <input type="checkbox" 
                                       <?= $case['final_installment_commission_paid'] ? 'checked' : '' ?> 
                                       class="commission-checkbox" 
                                       data-case-id="<?= $case['id'] ?>" 
                                       data-installment="4">
                                <span>Prowizja wypłacona</span>
                            </label>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div id="debug" style="margin-top: 30px; padding: 10px; background: #f8f8f8; border: 1px solid #ddd;">
        <h3>Debug Info:</h3>
        <div id="debug-content"></div>
    </div>

    <script>
        // Initialize commission checkboxes
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
                }
                
                checkbox.addEventListener('change', function() {
                    console.log("Commission checkbox changed:", this.checked);
                    
                    // Handle commission checkbox interaction
                    handleCommissionCheckboxChange(this, label);
                });
            });
        }
        
        // Handle commission checkbox change
        function handleCommissionCheckboxChange(checkbox, label) {
            console.log("Handling commission checkbox change");
            
            const caseId = checkbox.dataset.caseId;
            const installmentNumber = checkbox.dataset.installment;
            const isChecked = checkbox.checked;
            
            console.log(`Case ID: ${caseId}, Installment: ${installmentNumber}, Checked: ${isChecked}`);
            debugLog(`Case ID: ${caseId}, Installment: ${installmentNumber}, Checked: ${isChecked}`);
            
            // Get the cell containing the checkbox
            const cell = checkbox.closest('td');
            
            // Update UI based on state
            if (isChecked) {
                cell.classList.add('commission-paid');
                cell.classList.remove('commission-needed');
            } else {
                cell.classList.remove('commission-paid');
                cell.classList.add('commission-needed');
            }
            
            // Show notification
            const message = isChecked 
                ? `Prowizja za ratę ${installmentNumber} została oznaczona jako wypłacona.` 
                : `Prowizja za ratę ${installmentNumber} została oznaczona jako niewypłacona.`;
            
            showNotification(message, isChecked ? 'info' : 'error');
            
            // Send update to server
            updateCommissionStatus(caseId, installmentNumber, isChecked ? 1 : 0);
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
        
        // Function to update commission status on server
        function updateCommissionStatus(caseId, installmentNumber, status) {
            console.log(`Updating commission status: Case ${caseId}, Installment ${installmentNumber}, Status ${status}`);
            debugLog(`Updating commission status: Case ${caseId}, Installment ${installmentNumber}, Status ${status}`);
            
            // Debug JSON payload
            const jsonPayload = JSON.stringify({
                case_id: caseId,
                installment_number: installmentNumber,
                status: status
            });
            console.log("JSON Payload:", jsonPayload);
            debugLog("JSON Payload: " + jsonPayload);
            
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
                debugLog("AJAX Response status: " + response.status);
                if (!response.ok) {
                    console.error("HTTP Error:", response.status, response.statusText);
                    debugLog("HTTP Error: " + response.status + " " + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                console.log('Commission status update response:', data);
                debugLog('Response: ' + JSON.stringify(data));
                
                if (data.success) {
                    console.log("Update successful, status saved in database for case", caseId, "installment", installmentNumber);
                    debugLog(`Success: Updated commission for case ${caseId}, installment ${installmentNumber}`);
                    
                    // Show notification
                    showNotification(`Prowizja za ratę ${installmentNumber} została zapisana w bazie danych.`, 'info');
                } else {
                    // If there was an error, revert the checkbox state
                    console.error("Error updating commission:", data.message);
                    debugLog("Error: " + data.message);
                    
                    const checkbox = document.querySelector(`.commission-checkbox[data-case-id="${caseId}"][data-installment="${installmentNumber}"]`);
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;
                        
                        // Also update the cell class
                        const cell = checkbox.closest('td');
                        if (cell) {
                            cell.classList.toggle('commission-paid');
                            cell.classList.toggle('commission-needed');
                        }
                        
                        // Show error notification
                        showNotification(`Błąd: ${data.message}`, 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Error updating commission status:', error);
                debugLog('Connection error: ' + error.message);
                
                // Revert the checkbox state on error
                const checkbox = document.querySelector(`.commission-checkbox[data-case-id="${caseId}"][data-installment="${installmentNumber}"]`);
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                    
                    // Also update the cell class
                    const cell = checkbox.closest('td');
                    if (cell) {
                        cell.classList.toggle('commission-paid');
                        cell.classList.toggle('commission-needed');
                    }
                    
                    // Show error notification
                    showNotification('Błąd połączenia podczas aktualizacji statusu prowizji.', 'error');
                }
            });
        }
        
        // Debug log function
        function debugLog(message) {
            const debugContent = document.getElementById('debug-content');
            const logLine = document.createElement('div');
            logLine.textContent = new Date().toLocaleTimeString() + ': ' + message;
            debugContent.appendChild(logLine);
        }
        
        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            console.log("DOM loaded - initializing commission test page");
            debugLog("Page initialized");
            
            // Initialize commission checkboxes
            initCommissionCheckboxes();
        });
    </script>
</body>
</html> 