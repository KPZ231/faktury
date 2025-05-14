<!-- Simple Commission Payment Modal -->
<div id="commission-payment-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="close" id="close-commission-modal">&times;</span>
            <h2 class="modal-title">Wypłata Prowizji</h2>
        </div>
        <div class="modal-body">
            <div class="commission-info">
                <p>Wprowadź numery faktur dla każdego agenta:</p>
            </div>
            
            <div class="agent-forms-container">
                <!-- Agent forms will be dynamically added here -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="cancel-commission-btn">Anuluj</button>
            <button type="button" class="btn btn-primary" id="save-commission-btn" onclick="saveAllCommissionPayments()">Zapisz wszystkie</button>
        </div>
    </div>
</div>

<style>
    /* Basic modal styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.5);
    }
    
    .modal-content {
        background-color: #ffffff;
        margin: 5% auto;
        padding: 25px;
        border-radius: 8px;
        width: 85%;
        max-width: 650px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        animation: modalFadeIn 0.3s ease-out;
    }
    
    @keyframes modalFadeIn {
        from {opacity: 0; transform: translateY(-20px);}
        to {opacity: 1; transform: translateY(0);}
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
        margin-bottom: 20px;
    }
    
    .modal-header h2 {
        margin: 0;
        font-size: 22px;
        color: #333;
        font-weight: 600;
    }
    
    .close {
        color: #888;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        transition: color 0.2s;
    }
    
    .close:hover {
        color: #333;
    }
    
    .modal-body {
        padding: 10px 0;
    }
    
    .commission-info {
        margin-bottom: 20px;
        padding: 12px 15px;
        background-color: #f8f9fa;
        border-left: 4px solid #4CAF50;
        border-radius: 4px;
    }
    
    .commission-info p {
        margin: 0;
        font-size: 15px;
        color: #555;
    }
    
    .modal-footer {
        padding-top: 20px;
        border-top: 2px solid #f0f0f0;
        display: flex;
        justify-content: flex-end;
        margin-top: 15px;
    }
    
    /* Agent form styles */
    .agent-forms-container {
        max-height: 400px;
        overflow-y: auto;
        padding-right: 5px;
    }
    
    .agent-form {
        background-color: #f9f9f9;
        border-radius: 6px;
        padding: 18px;
        margin-bottom: 18px;
        border: 1px solid #e9e9e9;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        transition: all 0.2s;
    }
    
    .agent-form:hover {
        box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    
    .agent-info {
        margin-bottom: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .agent-name {
        font-weight: bold;
        font-size: 17px;
        margin-bottom: 5px;
        color: #333;
        text-shadow: 0 1px 0 rgba(255,255,255,0.7);
    }
    
    .agent-rate {
        color: #555;
        font-size: 15px;
        background-color: #eef7ee;
        padding: 5px 10px;
        border-radius: 4px;
        border: 1px solid #cbe7cb;
    }
    
    .form-group {
        margin-bottom: 0;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #444;
        font-size: 14px;
    }
    
    .form-control {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 15px;
        transition: all 0.2s;
    }
    
    .form-control:focus {
        border-color: #4CAF50;
        box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        outline: none;
    }
    
    .form-control.error {
        border-color: #dc3545;
        background-color: #fff8f8;
    }
    
    /* Button styles */
    .btn {
        padding: 10px 18px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        transition: all 0.2s;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    .btn-primary {
        background-color: #4CAF50;
        color: white;
        margin-left: 12px;
        box-shadow: 0 2px 4px rgba(76, 175, 80, 0.3);
    }
    
    .btn-primary:hover {
        background-color: #45a049;
        transform: translateY(-1px);
        box-shadow: 0 3px 6px rgba(76, 175, 80, 0.4);
    }
    
    .btn-primary:active {
        transform: translateY(0);
        box-shadow: 0 1px 2px rgba(76, 175, 80, 0.3);
    }
    
    .btn-secondary {
        background-color: #f44336;
        color: white;
        box-shadow: 0 2px 4px rgba(244, 67, 54, 0.3);
    }
    
    .btn-secondary:hover {
        background-color: #d32f2f;
        transform: translateY(-1px);
        box-shadow: 0 3px 6px rgba(244, 67, 54, 0.4);
    }
    
    .btn-secondary:active {
        transform: translateY(0);
        box-shadow: 0 1px 2px rgba(244, 67, 54, 0.3);
    }
    
    /* Notification styles */
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px;
        border-radius: 6px;
        color: white;
        font-weight: bold;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 2000;
        transform: translateY(-20px);
        opacity: 0;
        transition: all 0.3s;
        max-width: 350px;
    }
    
    .notification.show {
        transform: translateY(0);
        opacity: 1;
    }
    
    .notification.info {
        background-color: #2196F3;
    }
    
    .notification.success {
        background-color: #4CAF50;
    }
    
    .notification.error {
        background-color: #F44336;
    }
    
    .notification i {
        margin-right: 10px;
    }
    
    /* Alert styles */
    .alert {
        padding: 16px;
        border-radius: 6px;
        margin-bottom: 15px;
        text-align: center;
    }
    
    .alert-info {
        background-color: #e3f2fd;
        border: 1px solid #bbdefb;
        color: #0d47a1;
    }
    
    /* Hide technical field names */
    [class*="INSTALLMENT"],
    [id*="INSTALLMENT"] {
        display: none !important;
    }
    
    /* Format the modal titles properly */
    .modal-content h3 {
        text-transform: capitalize;
    }
    
    /* Create more user-friendly display for installment numbers */
    .installment-info {
        margin: 15px 0;
        padding: 8px 12px;
        background-color: #f2f7ff;
        border-radius: 4px;
        border-left: 3px solid #4285f4;
    }
    
    .installment-label {
        font-weight: bold;
        color: #333;
        margin-right: 5px;
    }
    
    .installment-value {
        color: #4285f4;
        font-weight: 500;
    }
    
    /* User-friendly title styling */
    .user-friendly-title {
        color: #333;
        border-bottom: 2px solid #4285f4;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }
    
    /* Custom styling for agent forms */
    .agent-form {
        background-color: #f9f9f9;
        border: 1px solid #eee;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .agent-form h4 {
        margin-top: 0;
        color: #4285f4;
    }
</style>

<script>
    // Initialize the modal close button
    document.addEventListener('DOMContentLoaded', function() {
        const closeBtn = document.getElementById('close-commission-modal');
        const cancelBtn = document.getElementById('cancel-commission-btn');
        const modal = document.getElementById('commission-payment-modal');
        
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        }
        
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        }
        
        // Close when clicking outside the modal
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });
</script> 