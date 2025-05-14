<?php
// Include helper functions for formatting
require_once __DIR__ . '/../Lib/helpers.php';

// Initialize database connection
require_once __DIR__ . '/../../config/database.php';
global $pdo;

// Define constants and helper variables
$epsilon = 0.01; // For floating point comparisons
$kuba_id = 1; // Assuming Kuba's agent ID is 1

// Get agent list
$agentsQuery = "SELECT agent_id, imie, nazwisko FROM agenci ORDER BY nazwisko, imie";
$agentsStmt = $pdo->query($agentsQuery);
$agenci = [];
while ($agent = $agentsStmt->fetch(PDO::FETCH_ASSOC)) {
    $agenci[$agent['agent_id']] = $agent['imie'] . ' ' . $agent['nazwisko'];
}

// Define raty structure
$ratyOpis = ['Rata 1', 'Rata 2', 'Rata 3', 'Rata 4'];
$liczbaRat = count($ratyOpis);

// Get all cases/sprawy
$sprawyQuery = "SELECT 
    t.id as id_sprawy,
    t.case_name as identyfikator_sprawy,
    t.is_completed as czy_zakonczona,
    t.amount_won as wywalczona_kwota,
    t.upfront_fee as oplata_wstepna,
    t.success_fee_percentage as stawka_success_fee,
    t.total_commission as calosc_prowizji,
    t.case_name as imie_nazwisko_klienta,
    t.kuba_payout as do_wyplaty_kuba_proc
    FROM test2 t
    ORDER BY t.created_at DESC";
$sprawyStmt = $pdo->query($sprawyQuery);
$sprawyData = [];

// Process each case
while ($sprawa = $sprawyStmt->fetch(PDO::FETCH_ASSOC)) {
    $id_sprawy = $sprawa['id_sprawy'];
    
    // Get agent commissions for this case
    $prowizjeQuery = "SELECT agent_id, percentage FROM sprawa_agent WHERE sprawa_id = ?";
    $prowizjeStmt = $pdo->prepare($prowizjeQuery);
    $prowizjeStmt->execute([$id_sprawy]);
    
    $sprawa['prowizje_proc'] = [];
    while ($prowizja = $prowizjeStmt->fetch(PDO::FETCH_ASSOC)) {
        // Store the percentage as is - don't divide by 100 since format_percent has been updated
        $sprawa['prowizje_proc'][$prowizja['agent_id']] = $prowizja['percentage'];
    }
    
    // Map the installment fields from test2 table structure
    $sprawa['oplaty'] = [];
    
    // Rata 1
    $sprawa['oplaty']['Rata 1'] = [
        'kwota' => $sprawa['id_sprawy'] ? $pdo->query("SELECT installment1_amount FROM test2 WHERE id = {$sprawa['id_sprawy']}")->fetchColumn() : null,
        'is_paid_display' => $sprawa['id_sprawy'] ? (bool)$pdo->query("SELECT installment1_paid FROM test2 WHERE id = {$sprawa['id_sprawy']}")->fetchColumn() : false,
        'faktura_id' => null,
        'paying_invoice_number' => $sprawa['id_sprawy'] ? $pdo->query("SELECT installment1_paid_invoice FROM test2 WHERE id = {$sprawa['id_sprawy']}")->fetchColumn() : null
    ];
    
    // Rata 2
    $sprawa['oplaty']['Rata 2'] = [
        'kwota' => $sprawa['id_sprawy'] ? $pdo->query("SELECT installment2_amount FROM test2 WHERE id = {$sprawa['id_sprawy']}")->fetchColumn() : null,
        'is_paid_display' => $sprawa['id_sprawy'] ? (bool)$pdo->query("SELECT installment2_paid FROM test2 WHERE id = {$sprawa['id_sprawy']}")->fetchColumn() : false,
        'faktura_id' => null,
        'paying_invoice_number' => $sprawa['id_sprawy'] ? $pdo->query("SELECT installment2_paid_invoice FROM test2 WHERE id = {$sprawa['id_sprawy']}")->fetchColumn() : null
    ];
    
    // Rata 3
    $sprawa['oplaty']['Rata 3'] = [
        'kwota' => $sprawa['id_sprawy'] ? $pdo->query("SELECT installment3_amount FROM test2 WHERE id = {$sprawa['id_sprawy']}")->fetchColumn() : null,
        'is_paid_display' => $sprawa['id_sprawy'] ? (bool)$pdo->query("SELECT installment3_paid FROM test2 WHERE id = {$sprawa['id_sprawy']}")->fetchColumn() : false,
        'faktura_id' => null,
        'paying_invoice_number' => $sprawa['id_sprawy'] ? $pdo->query("SELECT installment3_paid_invoice FROM test2 WHERE id = {$sprawa['id_sprawy']}")->fetchColumn() : null
    ];
    
    // Rata 4 (final installment)
    $sprawa['oplaty']['Rata 4'] = [
        'kwota' => $sprawa['id_sprawy'] ? $pdo->query("SELECT final_installment_amount FROM test2 WHERE id = {$sprawa['id_sprawy']}")->fetchColumn() : null,
        'is_paid_display' => $sprawa['id_sprawy'] ? (bool)$pdo->query("SELECT final_installment_paid FROM test2 WHERE id = {$sprawa['id_sprawy']}")->fetchColumn() : false,
        'faktura_id' => null,
        'paying_invoice_number' => $sprawa['id_sprawy'] ? $pdo->query("SELECT final_installment_paid_invoice FROM test2 WHERE id = {$sprawa['id_sprawy']}")->fetchColumn() : null
    ];
    
    // Get commission payment info from commission_payments table
    $commissionQuery = "SELECT * FROM commission_payments WHERE case_id = ? ORDER BY installment_number";
    $commissionStmt = $pdo->prepare($commissionQuery);
    $commissionStmt->execute([$id_sprawy]);
    
    while ($commission = $commissionStmt->fetch(PDO::FETCH_ASSOC)) {
        $rataIndex = $commission['installment_number'] - 1; // Convert from 1-based to 0-based index
        if (isset($ratyOpis[$rataIndex])) {
            $opisRaty = $ratyOpis[$rataIndex];
            // Update commission status and invoice number if available
            $sprawa['oplaty'][$opisRaty]['commission_status'] = (bool)$commission['status'];
            $sprawa['oplaty'][$opisRaty]['commission_invoice'] = $commission['invoice_number'];
            $sprawa['oplaty'][$opisRaty]['commission_agent_id'] = $commission['agent_id'];
        }
    }
    
    $sprawyData[$id_sprawy] = $sprawa;
}

// Function to sync payment statuses (simplified version)
function syncPaymentStatuses($pdo) {
    // Match the database structure from main.sql
    // Update commissions status based on the commission_payments table
    $updateQuery = "UPDATE test2 t
                   JOIN commission_payments cp ON t.id = cp.case_id
                   SET t.installment1_commission_paid = CASE WHEN cp.installment_number = 1 THEN cp.status ELSE t.installment1_commission_paid END,
                       t.installment2_commission_paid = CASE WHEN cp.installment_number = 2 THEN cp.status ELSE t.installment2_commission_paid END,
                       t.installment3_commission_paid = CASE WHEN cp.installment_number = 3 THEN cp.status ELSE t.installment3_commission_paid END,
                       t.final_installment_commission_paid = CASE WHEN cp.installment_number = 4 THEN cp.status ELSE t.final_installment_commission_paid END
                   WHERE cp.status = 1";
    $pdo->exec($updateQuery);
}

// Sync payment statuses
syncPaymentStatuses($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabela spraw</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Add any missing styles from table.php */
        .table-container {
            width: 100%;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        
        th {
            background-color: #f2f2f2;
            text-align: left;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .status-zakonczona, .status-oplacona {
            color: green;
        }
        
        .status-niezakonczona, .status-nieoplacona {
            color: red;
        }
        
        .details-section {
            max-width: 250px;
        }
        
        .currency {
            text-align: right;
        }
        
        .percentage {
            text-align: center;
        }
        
        .action-cell {
            white-space: nowrap;
            text-align: center;
        }
        
        .action-btn {
            padding: 5px;
            margin: 0 2px;
            cursor: pointer;
            border: none;
            background: none;
        }
        
        .edit i {
            color: #2196F3;
        }
        
        .delete i {
            color: #F44336;
        }
        
        .agent-payout {
            margin-bottom: 5px;
            display: block;
        }
        
        .amount-row {
            display: flex;
            justify-content: space-between;
        }
        
        .agent-name {
            font-weight: bold;
        }
        
        .agent-amount {
            color: #2196F3;
        }
        
        .payment-form {
            display: none;
            margin-top: 8px;
            padding: 12px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .payment-form.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-10px); }
        }
        
        .payment-form.closing {
            animation: fadeOut 0.3s ease forwards;
        }
        
        /* Status indicators */
        .saving-indicator {
            color: #007bff;
            font-size: 13px;
            margin: 5px 0;
            display: flex;
            align-items: center;
        }
        
        .saving-indicator:before {
            content: '';
            display: inline-block;
            width: 12px;
            height: 12px;
            margin-right: 8px;
            border: 2px solid #007bff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .success-message {
            color: #28a745;
            font-size: 13px;
            margin: 5px 0;
            display: flex;
            align-items: center;
            font-weight: 500;
        }
        
        .success-message:before {
            content: '✓';
            margin-right: 8px;
            font-weight: bold;
        }
        
        .error-message {
            color: #dc3545;
            font-size: 13px;
            margin: 5px 0;
            display: flex;
            align-items: center;
            font-weight: 500;
        }
        
        .error-message:before {
            content: '✕';
            margin-right: 8px;
            font-weight: bold;
        }
        
        .form-group {
            margin-bottom: 10px;
        }
        
        .form-group label {
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #444;
        }
        
        .form-group input[type="checkbox"] {
            margin-right: 8px;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        .form-group input[type="text"] {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-group input[type="text"]:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        
        .button-group {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 10px;
        }
        
        .button-group button {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .button-group .save {
            background-color: #28a745;
            color: white;
        }
        
        .button-group .save:hover {
            background-color: #218838;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .button-group .cancel {
            background-color: #dc3545;
            color: white;
        }
        
        .button-group .cancel:hover {
            background-color: #c82333;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .uzupelnij-link {
            display: inline-block;
            margin-top: 5px;
            padding: 4px 10px;
            font-size: 13px;
            text-decoration: none;
            color: #fff;
            background-color: #dc3545;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .uzupelnij-link:hover {
            background-color: #c82333;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .agent-payout.zaplecono-faktura {
            background-color: #d4edda;
            border-left: 3px solid #28a745;
            padding: 8px 10px;
            border-radius: 4px;
        }
        
        .status-zapłacono {
            color: #28a745;
            font-size: 13px;
            margin-top: 5px;
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        
        .status-zapłacono:before {
            content: '✓';
            margin-right: 5px;
            font-weight: bold;
        }
        
        .faktura-info {
            font-size: 12px;
            color: #666;
            font-style: italic;
            margin-top: 3px;
        }
        
        .agent-name {
            font-weight: 500;
            color: #444;
            display: inline-block;
            margin-right: 5px;
        }
        
        .agent-amount {
            display: inline-block;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .agent-payout.kuba-payout {
            background-color: #fff3cd;
            border-left: 3px solid #ffc107;
            padding: 8px 10px;
            border-radius: 4px;
        }
        
        .agent-payout:not(.kuba-payout) {
            background-color: #f8f9fa;
            border-left: 3px solid #6c757d;
            padding: 8px 10px;
            border-radius: 4px;
        }
        
        .agent-payout {
            position: relative;
            margin-bottom: 10px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .agent-payout:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            z-index: 1;
        }
        
        .prowizje-rata-cell {
            padding: 10px;
            vertical-align: top;
        }
        
        .payment-status {
            margin-top: 5px;
        }

        /* Reset i podstawy */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    line-height: 1.4;
    margin: 20px;
    background-color: #f9f9f9;
    color: #333;
}

h1 {
    text-align: center;
    color: #444;
    margin-bottom: 30px;
}

/* Kontener tabeli dla ewentualnego przewijania */
.table-container {
    width: 100%;
    overflow-x: auto; /* Umożliwia przewijanie poziome, jeśli tabela jest za szeroka */
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    background-color: #fff;
    border-radius: 5px;
}

/* Główna tabela */
table {
    border-collapse: collapse;
    width: 100%; /* Tabela zajmuje całą szerokość kontenera */
    min-width: 1600px; /* Minimalna szerokość, aby wymusić przewijanie na mniejszych ekranach */
    font-size: 13px; /* Nieco mniejsza czcionka dla zwartości */
    border: 1px solid #ddd;
}

/* Komórki nagłówka i danych */
th, td {
    border: 1px solid #e0e0e0;
    padding: 8px 10px; /* Dostosowany padding */
    text-align: left;
    vertical-align: top; /* Wyrównanie do góry dla spójności */
    white-space: nowrap; /* Zapobiega łamaniu tekstu w komórkach */
}

/* Nagłówek tabeli */
thead th {
    background-color: #eef4f7; /* Jaśniejszy, subtelny kolor tła */
    color: #333;
    font-weight: 600; /* Lekko pogrubione */
    text-align: center; /* Domyślnie centrujemy nagłówki */
    vertical-align: middle;
    border-bottom: 2px solid #cddde5;
}
thead tr:first-child th {
     border-top: none; /* Usuń górną ramkę dla pierwszego rzędu nagłówka */
}


/* Zebra-striping dla wierszy danych */
tbody tr:nth-child(even) {
    background-color: #f8fafd;
}
tbody tr:hover {
    background-color: #e8f0f5; /* Subtelny efekt hover */
}


/* Specyficzne wyrównania i formatowanie */
.currency,
.percentage,
td:nth-child(3), /* Wywalczona kwota */
td:nth-child(4), /* Opłata wstępna */
td:nth-child(6)  /* Całość prowizji */
{
    text-align: right !important; /* Wymuszenie dla liczb */
}


.checkbox {
    text-align: center !important;
    font-size: 1.1em;
}

/* Formatowanie dla list wewnątrz komórek */
.details-section {
    margin: 0;
    padding: 0;
    list-style: none; /* Usunięcie punktorów jeśli użyjemy list */
    font-size: 0.95em;
}
.details-section div, .details-section span { /* Zamiast .details-section strong */
    display: block; /* Każdy wpis w nowej linii */
    margin-bottom: 3px;
    padding-left: 5px;
}
.details-section strong { /* Można zostawić dla etykiet jeśli trzeba */
    display: inline-block;
    min-width: 55px; /* Dopasowanie */
    font-weight: normal; /* Normalna czcionka dla etykiet */
    color: #555;
}

/* Kolory dla statusu (przykładowe) */
.status-oplacona { color: #28a745; font-weight: bold; }
.status-nieoplacona { color: #dc3545; }

/* Wyróżnienie ważnych kolumn (opcjonalne) */
th:nth-child(1), td:nth-child(1) { /* Sprawa */
    font-weight: 500;
    background-color: #fdfdfe; /* Lekkie tło dla pierwszej kolumny */
}

/* Style dla tooltipa */
.payment-info {
    position: absolute;
    background-color: #333;
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 0.9em;
    z-index: 1000;
    display: none;
    white-space: nowrap;
}
.agent-payout:hover .payment-info {
    display: block;
}

@keyframes glow {
    0% { box-shadow: 0 0 5px rgba(220, 53, 69, 0.3); }
    50% { box-shadow: 0 0 15px rgba(220, 53, 69, 0.6); }
    100% { box-shadow: 0 0 5px rgba(220, 53, 69, 0.3); }
}
.search-container {
    margin: 20px 0;
    text-align: center;
    position: relative;
}
.search-input {
    padding: 10px;
    width: 300px;
    border: 2px solid #ddd;
    border-radius: 5px;
    font-size: 16px;
    transition: border-color 0.3s;
}
.search-input:focus {
    border-color: #007bff;
    outline: none;
}
.highlight {
    background-color: #e3f2fd;
    border: 2px solid #2196f3;
    animation: highlight-pulse 3s infinite;
    animation-timing-function: ease-in-out;
}
@keyframes highlight-pulse {
    0% { background-color: #e3f2fd; }
    50% { background-color: #e8f5fd; }
    100% { background-color: #e3f2fd; }
}
/* Style dla autocomplete */
.autocomplete-suggestions {
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    width: 300px;
    max-height: 200px;
    overflow-y: auto;
    background: white;
    border: 1px solid #ddd;
    border-radius: 0 0 5px 5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    z-index: 1000;
    display: none;
}
.autocomplete-suggestion {
    padding: 8px 12px;
    cursor: pointer;
    transition: background-color 0.2s;
}
.autocomplete-suggestion:hover {
    background-color: #f0f0f0;
}
.autocomplete-suggestion.selected {
    background-color: #e3f2fd;
}

/* Nowe style dla komórek prowizji */
.prowizje-rata-cell {
    padding: 5px 8px;
    vertical-align: top;
}

/* Style dla agenta */
.agent-payout {
    margin-bottom: 8px;
    padding: 5px;
    display: block;
    position: relative;
    border-radius: 3px;
}

/* Standardowy wygląd dla niezapłaconych */
.agent-payout:not(.kuba-payout) {
    background-color: #ffebee;
    border-left: 3px solid #f44336;
}

/* Uzupełnij link */
.uzupelnij-link {
    display: inline-block;
    margin-top: 5px;
    padding: 4px 10px;
    font-size: 13px;
    text-decoration: none;
    color: #fff;
    background-color: #dc3545;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.uzupelnij-link:hover {
    background-color: #c82333;
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

/* Wygląd dla Kuby */
.agent-payout.kuba-payout {
    background-color: #fff3cd;
    border-left: 3px solid #ffc107;
}

/* Wygląd dla zapłaconych */
.agent-payout.zaplecono-faktura {
    background-color: #d4edda;
    border-left: 3px solid #28a745;
}

/* Style dla agent name i amount */
.agent-name {
    font-weight: normal;
    color: #555;
    display: inline-block;
    margin-right: 5px;
}

.agent-amount {
    display: inline-block;
    font-weight: bold;
}

/* Status zapłacone */
.status-zapłacono {
    color: #28a745;
    font-size: 0.85em;
    margin-top: 3px;
    font-style: italic;
}

/* Numer faktury */
.faktura-info {
    font-size: 12px;
    color: #666;
    font-style: italic;
}

/* Style dla formularza płatności */
.payment-form {
    display: none;
    margin-top: 5px;
    padding: 8px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.payment-form.active {
    display: block;
}

.form-group {
    margin-bottom: 5px;
}

.button-group {
    display: flex;
    justify-content: flex-end;
    gap: 5px;
    margin-top: 5px;
}

.button-group button {
    padding: 3px 8px;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    font-size: 0.85em;
}

.button-group .save {
    background-color: #28a745;
    color: white;
}

.button-group .cancel {
    background-color: #dc3545;
    color: white;
}

/* Style dla linków nawigacyjnych */
a.strona {
    display: inline-block;
    padding: 8px 16px;
    margin: 4px;
    text-decoration: none;
    color: #007bff;
    background-color: #e9f2ff;
    border: 1px solid #007bff;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s ease-in-out;
}

a.strona:hover {
    background-color: #007bff;
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,123,255,0.2);
}

a.strona:active {
    transform: scale(0.98);
    box-shadow: none;
}

/* Modal dla formularza płatności */
.payment-modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.payment-modal.active {
    display: flex;
    opacity: 1;
}

.modal-content {
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    width: 100%;
    max-width: 450px;
    transform: translateY(-20px);
    transition: transform 0.3s ease;
    overflow: hidden;
    animation: modalSlideIn 0.3s forwards;
}

@keyframes modalSlideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.modal-header h3 {
    margin: 0;
    color: #343a40;
    font-size: 18px;
    font-weight: 600;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: #6c757d;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    transition: color 0.2s;
}

.modal-close:hover {
    color: #343a40;
}

.modal-body {
    padding: 20px;
}

.payment-details {
    background-color: #f8f9fa;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 15px;
    border-left: 4px solid #007bff;
}

.payment-details p {
    margin: 5px 0;
    font-size: 14px;
}

.payment-details strong {
    display: inline-block;
    width: 100px;
    color: #495057;
}

#modalPaymentStatus {
    margin: 15px 0;
    min-height: 24px;
}

/* Improve uzupelnij-link for popup trigger */
.uzupelnij-link {
    display: inline-block;
    margin-top: 5px;
    padding: 5px 12px;
    font-size: 13px;
    text-decoration: none;
    color: #fff;
    background-color: #007bff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.uzupelnij-link:hover {
    background-color: #0069d9;
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}
    </style>
</head>
<body>

<h1>Podsumowanie Spraw</h1>
<a href="/import" class="strona">Importuj dane z faktur</a>
<a href="/agents" class="strona">Dodaj nowego agenta</a>
<a href="/wizard" class="strona">Dodaj Nową Sprawę</a>

<div class="search-container">
    <input type="text" id="searchInput" class="search-input" placeholder="Wyszukaj sprawę..." autocomplete="off">
    <div id="autocompleteSuggestions" class="autocomplete-suggestions"></div>
</div>

<div class="table-container">
    <table>
         <thead>
            <!-- Nagłówek (bez zmian od ostatniej wersji) -->
             <tr><th rowspan="2">Sprawa</th><th rowspan="2">Zakończona?</th><th rowspan="2">Wywalczona kwota</th><th rowspan="2">Opłata wstępna</th><th rowspan="2">Success fee</th><th rowspan="2">Całość prowizji (obl.)</th><th colspan="2">Prowizje (%)</th><th colspan="<?php echo $liczbaRat; ?>">Raty klienta</th><th colspan="<?php echo $liczbaRat + 1; ?>">Prowizje agentów</th></tr>
            <tr><th>% Agenci</th><th>% Do wypłaty Kuba</th><?php foreach ($ratyOpis as $opisRaty): ?><th><?php echo $opisRaty; ?></th><?php endforeach; ?><?php foreach ($ratyOpis as $opisRaty): ?><th>Kwota do wyplaty agentowi</th><?php endforeach; ?><th>Edycja sprawy</th></tr>
        </thead>
        <tbody>
            <?php if (empty($sprawyData)): ?>
                <tr><td colspan="<?php echo 8 + $liczbaRat + $liczbaRat + 1; ?>">Brak danych.</td></tr>
            <?php else: ?>
                <?php foreach ($sprawyData as $id_sprawy => $sprawa): ?>
                    <tr data-sprawa-id="<?php echo $id_sprawy; ?>">
                        <!-- Podstawowe dane (bez zmian) -->
                        <td><?php echo htmlspecialchars($sprawa['identyfikator_sprawy']); ?></td>
                        <td class="status-cell">
                            <?php if ($sprawa['czy_zakonczona']): ?><span class="status-zakonczona" title="Zakończona"><i class="fa-solid fa-circle-check"></i></span>
                            <?php else: ?><span class="status-niezakonczona" title="Niezakończona"><i class="fa-solid fa-circle-xmark"></i></span><?php endif; ?>
                        </td>
                        <td class="currency"><?php echo format_currency($sprawa['wywalczona_kwota']); ?></td>
                        <td class="currency"><?php echo format_currency($sprawa['oplata_wstepna']); ?></td>
                        <td class="percentage"><?php echo format_percent($sprawa['stawka_success_fee']); ?></td>
                        <td class="currency"><?php echo format_currency($sprawa['calosc_prowizji']); ?></td>

                        <!-- Prowizje % (bez zmian) -->
                        <td><div class="details-section"><?php if (!empty($sprawa['prowizje_proc'])): foreach ($sprawa['prowizje_proc'] as $agent_id => $proc): ?><span><?php echo htmlspecialchars($agenci[$agent_id] ?? "Agent {$agent_id}"); ?>: <?php echo format_percent($proc); ?></span><br><?php endforeach; else: ?> - <?php endif; ?></div></td>
                        <td class="percentage"><?php echo format_percent($sprawa['do_wyplaty_kuba_proc'] ?? 0.0); ?></td>

                        <!-- Opłaty Ogólne - Logika statusu uwzględnia 0zł ORAZ wynik algorytmu -->
                        <?php foreach ($ratyOpis as $opisRaty): ?>
                            <?php
                                $daneRaty = $sprawa['oplaty'][$opisRaty] ?? ['kwota' => null, 'is_paid_display' => false, 'paying_invoice_number' => null];
                                $kwotaRaty = $daneRaty['kwota'];
                                $invoiceNumber = $daneRaty['paying_invoice_number'] ?? null;
                                $czyOplaconaPrzezAlgorytm = $daneRaty['is_paid_display'];
                                $czyKwotaJestZerowa = ($kwotaRaty !== null && abs($kwotaRaty) < $epsilon);
                                $czyFakturaOplaconaWBazie = false;
                                if (isset($daneRaty['faktura_id']) && $daneRaty['faktura_id']) {
                                    $stmt = $pdo->prepare("SELECT Status FROM test WHERE numer = ?");
                                    $stmt->execute([$daneRaty['faktura_id']]);
                                    $fakturaStatus = $stmt->fetchColumn();
                                    $czyFakturaOplaconaWBazie = ($fakturaStatus === 'Opłacona');
                                }
                                $finalnieWyswietlJakoOplacona = $czyOplaconaPrzezAlgorytm || $czyKwotaJestZerowa || $czyFakturaOplaconaWBazie;
                                $statusClass = $finalnieWyswietlJakoOplacona ? 'status-oplacona' : 'status-nieoplacona';
                                $statusSymbol = $finalnieWyswietlJakoOplacona ? '☑' : '☐';
                                $wyswietlanaKwota = format_currency($kwotaRaty);
                                $invoiceTextHtml = '';
                                if ($invoiceNumber !== null) {
                                    $invoiceTextHtml = '<br><small class="invoice-details"> Faktura: ' . htmlspecialchars($invoiceNumber) . '</small>';
                                }
                            ?>
                            <td class="currency <?php echo $statusClass; ?>">
                                <?php echo $wyswietlanaKwota; ?>
                                <span class="checkbox"><?php echo $statusSymbol; ?></span>
                                <?php echo $invoiceTextHtml; ?>
                            </td>
                        <?php endforeach; ?>

                        <!-- Sekcja Prowizje - Wyświetlanie -->
                        <?php foreach ($ratyOpis as $opisRaty): ?>
                            <td class="prowizje-rata-cell">
                                <div class="details-section">
                                <?php 
                                    $kwotaRaty = $sprawa['oplaty'][$opisRaty]['kwota'] ?? 0.0;
                                    $czyRataOplacona = $sprawa['oplaty'][$opisRaty]['is_paid_display'] ?? false;
                                    if ($kwotaRaty > 0 && !empty($sprawa['prowizje_proc'])): 
                                        // Najpierw wyświetl prowizje dla innych agentów
                                        foreach ($sprawa['prowizje_proc'] as $agent_id => $proc): 
                                            if ($agent_id != $kuba_id): // Pomiń Kubę w pierwszej pętli
                                                // Divide by 100 to convert from percentage to multiplier
                                                $kwotaProwizji = $kwotaRaty * ($proc / 100);
                                                if ($kwotaProwizji > $epsilon):
                                                    $uniqueId = "payment_{$sprawa['id_sprawy']}_{$opisRaty}_{$agent_id}";
                                                    
                                                    // Pobierz dane o płatności dla tego agenta
                                                    $stmtCheck = $pdo->prepare("SELECT status as czy_oplacone, invoice_number as numer_faktury FROM commission_payments 
                                                        WHERE case_id = ? AND agent_id = ? AND installment_number = ?");
                                                    $installmentNumber = array_search($opisRaty, $ratyOpis) + 1; // Konwertuj nazwę raty na numer
                                                    $stmtCheck->execute([$sprawa['id_sprawy'], $agent_id, $installmentNumber]);
                                                    $platnosc = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                                                    
                                                    $czyOplacone = $platnosc ? (bool)$platnosc['czy_oplacone'] : false;
                                                    $numerFaktury = $platnosc ? $platnosc['numer_faktury'] : null;
                                                    
                                                    // Ustaw klasy CSS na podstawie statusu
                                                    $payoutClass = $czyOplacone ? 'zaplecono-faktura' : '';
                                ?>
                                                    <div class="agent-payout <?php echo $payoutClass; ?>" data-payment-id="payment_<?php echo $sprawa['id_sprawy']; ?>_<?php echo $opisRaty; ?>_<?php echo $agent_id; ?>">
                                                            <span class="agent-name"><?php echo htmlspecialchars($agenci[$agent_id] ?? "Agent {$agent_id}"); ?>:</span>
                                                            <span class="agent-amount"><?php echo format_currency($kwotaProwizji); ?></span>
                                                        
                                                        <?php if ($czyOplacone && $numerFaktury): ?>
                                                            <div class="status-zapłacono">Zapłacono (Faktura: <?php echo htmlspecialchars($numerFaktury); ?>)</div>
                                                        <?php elseif ($czyRataOplacona): ?>
                                                            <a href="#" class="uzupelnij-link" onclick="openPaymentModal('payment_<?php echo $sprawa['id_sprawy']; ?>_<?php echo $opisRaty; ?>_<?php echo $agent_id; ?>')">Uzupełnij</a>
                                                        <?php endif; ?>
                                                    </div>
                                <?php 
                                                endif;
                                            endif;
                                        endforeach;
                                        
                                        // Teraz wyświetl prowizję dla Kuby używając obliczonego procentu
                                        $kubaKwotaProwizji = $kwotaRaty * ($sprawa['do_wyplaty_kuba_proc'] / 100);
                                        if ($kubaKwotaProwizji > $epsilon):
                                            $kubaUniqueId = "payment_{$sprawa['id_sprawy']}_{$opisRaty}_kuba";
                                            
                                            // Pobierz dane o płatności dla Kuby
                                            $stmtCheck = $pdo->prepare("SELECT status as czy_oplacone, invoice_number as numer_faktury FROM commission_payments 
                                                WHERE case_id = ? AND agent_id = ? AND installment_number = ?");
                                            $installmentNumber = array_search($opisRaty, $ratyOpis) + 1; // Konwertuj nazwę raty na numer
                                            $stmtCheck->execute([$sprawa['id_sprawy'], $kuba_id, $installmentNumber]);
                                            $platnosc = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                                            
                                            $czyOplacone = $platnosc ? (bool)$platnosc['czy_oplacone'] : false;
                                            $numerFaktury = $platnosc ? $platnosc['numer_faktury'] : null;
                                            
                                            // Ustaw klasy CSS na podstawie statusu
                                            $kubaPayoutClass = 'kuba-payout';
                                            if ($czyOplacone) {
                                                $kubaPayoutClass .= ' zaplecono-faktura';
                                            }
                                ?>
                                            <div class="agent-payout <?php echo $kubaPayoutClass; ?>" data-payment-id="payment_<?php echo $sprawa['id_sprawy']; ?>_<?php echo $opisRaty; ?>_kuba">
                                                <span class="agent-name">Kuba:</span>
                                                    <span class="agent-amount"><?php echo format_currency($kubaKwotaProwizji); ?></span>
                                                
                                                <?php if ($czyOplacone && $numerFaktury): ?>
                                                    <div class="status-zapłacono">Zapłacono (Faktura: <?php echo htmlspecialchars($numerFaktury); ?>)</div>
                                                <?php elseif ($czyRataOplacona): ?>
                                                    <a href="#" class="uzupelnij-link" onclick="openPaymentModal('payment_<?php echo $sprawa['id_sprawy']; ?>_<?php echo $opisRaty; ?>_kuba')">Uzupełnij</a>
                                                <?php endif; ?>
                                            </div>
                                <?php 
                                        endif;
                                    endif; 
                                ?>
                                </div>
                            </td>
                        <?php endforeach; ?>
                        <td class="action-cell">
                            <a href="/sprawy/<?php echo $sprawa['id_sprawy']; ?>/edit" class="action-btn edit" title="Edytuj">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                            <button type="button" class="action-btn delete" 
                                data-sprawa-id="<?php echo $sprawa['id_sprawy']; ?>"
                                data-sprawa-nazwisko="<?php echo htmlspecialchars($sprawa['imie_nazwisko_klienta'] ?? $sprawa['identyfikator_sprawy']); ?>"
                                data-sprawa-nazwa="<?php echo htmlspecialchars($sprawa['identyfikator_sprawy']); ?>"
                                onclick="openDeleteModal(this)"
                                title="Usuń">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- MODAL do potwierdzenia usunięcia sprawy -->
<div id="deleteCaseModal" class="modal" style="display:none; position:fixed; z-index:2000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); align-items:center; justify-content:center;">
    <div style="background:#fff; padding:32px 24px; border-radius:8px; max-width:400px; margin:auto; box-shadow:0 8px 32px rgba(0,0,0,0.2); position:relative;">
        <h2 style="margin-top:0;">Potwierdź usunięcie sprawy</h2>
        <p id="deleteCaseText"></p>
        <div style="margin-bottom:12px;">
            <label for="deleteCaseInput" style="font-size:0.95em;">Aby potwierdzić, przepisz imię i nazwisko osoby, której dotyczy sprawa:</label>
            <input type="text" id="deleteCaseInput" style="width:100%; padding:6px; margin-top:4px; border:1px solid #ccc; border-radius:4px;">
        </div>
        <div style="display:flex; gap:8px;">
            <button id="deleteCaseConfirmBtn" class="save" style="background:#dc3545; color:#fff; border:none; border-radius:4px; padding:8px 16px; font-weight:600;" disabled>Usuń</button>
            <button onclick="closeDeleteModal()" style="background:#eee; color:#333; border:none; border-radius:4px; padding:8px 16px;">Anuluj</button>
        </div>
        <button onclick="closeDeleteModal()" style="position:absolute; top:8px; right:12px; background:none; border:none; font-size:1.2em; color:#888; cursor:pointer;">&times;</button>
    </div>
</div>

<!-- MODAL dla formularza płatności -->
<div id="paymentFormModal" class="modal payment-modal" style="display:none; position:fixed; z-index:2000; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); align-items:center; justify-content:center;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Uzupełnij informacje o płatności</h3>
            <button type="button" class="modal-close" onclick="closePaymentModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="payment-details">
                <p class="payment-for"><strong>Płatność dla: </strong><span id="paymentAgentName"></span></p>
                <p class="payment-amount"><strong>Kwota: </strong><span id="paymentAmount"></span></p>
                <p class="payment-installment"><strong>Rata: </strong><span id="paymentInstallment"></span></p>
            </div>
            <form id="modalPaymentForm" onsubmit="event.preventDefault(); savePaymentInfoModal();">
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="modalPaidCheckbox">
                        Zapłacono
                    </label>
                </div>
                <div class="form-group">
                    <label for="modalInvoiceInput">Numer faktury:</label>
                    <input type="text" id="modalInvoiceInput" placeholder="Wprowadź numer faktury">
                </div>
                <div id="modalPaymentStatus"></div>
                <div class="button-group">
                    <button type="submit" class="save">Zapisz</button>
                    <button type="button" class="cancel" onclick="closePaymentModal()">Anuluj</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add script for form functionality -->
<script>
// Funkcja do przełączania widoczności formularza
function togglePaymentForm(uniqueId) {
    const form = document.getElementById('form_' + uniqueId);
        if (form) {
        // Dodaj klasę aktywną z animacją
        if (form.classList.contains('active')) {
            // Najpierw dodaj klasę closing dla animacji zamykania
            form.classList.add('closing');
            // Po zakończeniu animacji, usuń klasy
            setTimeout(() => {
                form.classList.remove('active');
                form.classList.remove('closing');
            }, 300);
        } else {
            form.classList.add('active');
        }
    }
}

// Zmienne dla aktualnie wybranej płatności
let currentPaymentId = '';
let currentPaymentData = {};

// Funkcja do otwierania modalu płatności
function openPaymentModal(uniqueId) {
    // Zapisz ID dla późniejszego użycia
    currentPaymentId = uniqueId;
    
    // Pobierz dane z ID
    const [_, id_sprawy, opis_raty, id_agenta] = uniqueId.split('_');
    
    console.log('Opening payment modal with data:', {
        uniqueId: uniqueId,
        id_sprawy: id_sprawy,
        opis_raty: opis_raty,
        id_agenta: id_agenta
    });
    
    // Znajdź element z kwotą w najbliższym rodzicu
    const agentPayoutElement = document.querySelector(`.agent-payout[data-payment-id="${uniqueId}"]`);
    const agentNameElement = agentPayoutElement.querySelector('.agent-name');
    const amountElement = agentPayoutElement.querySelector('.agent-amount');
    
    // Pobierz nazwę agenta i kwotę
    const agentName = agentNameElement.textContent.replace(':', '').trim();
    const amount = amountElement.textContent.trim();
    
    // Ustaw dane w modalu
    document.getElementById('paymentAgentName').textContent = agentName;
    document.getElementById('paymentAmount').textContent = amount;
    document.getElementById('paymentInstallment').textContent = opis_raty;
    
    // Konwersja opisu raty na numer instalacji
    const ratyOpisy = ['Rata 1', 'Rata 2', 'Rata 3', 'Rata 4'];
    const installmentNumber = ratyOpisy.indexOf(opis_raty) + 1;
    
    // Wyczyść poprzednie statusy i ustaw domyślne wartości
    document.getElementById('modalPaidCheckbox').checked = false;
    document.getElementById('modalInvoiceInput').value = '';
    document.getElementById('modalPaymentStatus').innerHTML = '';
    
    // Zapisz dane dla zapisywania
    currentPaymentData = {
        id_sprawy: id_sprawy,
        opis_raty: opis_raty,
        id_agenta: id_agenta,
        agentName: agentName,
        amount: amount,
        installmentNumber: installmentNumber
    };
    
    console.log('Current payment data:', currentPaymentData);
    
    // Pokaż modal od razu
    const modal = document.getElementById('paymentFormModal');
    modal.style.display = 'flex';
    
    // Dodaj klasę active po krótkim opóźnieniu dla animacji
    setTimeout(() => {
        modal.classList.add('active');
    }, 10);
    
    // Pokaż ładowanie podczas pobierania danych
    document.getElementById('modalPaymentStatus').innerHTML = '<div class="saving-indicator">Pobieranie danych...</div>';
    
    // Pobierz aktualny status płatności
    const agent_id_param = id_agenta === 'kuba' ? '1' : id_agenta;
    const url = `/get-payment-status?case_id=${id_sprawy}&installment_number=${installmentNumber}&agent_id=${agent_id_param}`;
    
    console.log('Fetching payment status from:', url);
    
    fetch(url)
        .then(response => {
            console.log('Payment status response:', response.status);
            if (!response.ok) {
                throw new Error(`Server returned ${response.status}: ${response.statusText}`);
            }
            return response.json().catch(e => {
                console.error('Error parsing JSON:', e);
                return null;
            });
        })
        .then(data => {
            console.log('Payment status data:', data);
            document.getElementById('modalPaymentStatus').innerHTML = '';
            
            if (data) {
                // Aktualizuj formularz na podstawie danych
                document.getElementById('modalPaidCheckbox').checked = data.status ? true : false;
                document.getElementById('modalInvoiceInput').value = data.invoice_number || '';
            }
        })
        .catch(error => {
            console.error('Błąd podczas pobierania statusu płatności:', error);
            document.getElementById('modalPaymentStatus').innerHTML = 
                `<div class="error-message">Błąd podczas pobierania danych: ${error.message}</div>`;
        });
}

// Funkcja do zamykania modalu płatności
function closePaymentModal() {
    const modal = document.getElementById('paymentFormModal');
    
    // Animacja zamykania
    modal.classList.remove('active');
    
    // Po zakończeniu animacji, ukryj modal
    setTimeout(() => {
        modal.style.display = 'none';
        // Wyczyść dane
        currentPaymentId = '';
        currentPaymentData = {};
    }, 300);
}

// Zamknij modal po kliknięciu poza zawartością
window.addEventListener('click', function(event) {
    const modal = document.getElementById('paymentFormModal');
    if (event.target === modal) {
        closePaymentModal();
    }
});

// Funkcja do zapisywania informacji o płatności z modalu
function savePaymentInfoModal() {
    if (!currentPaymentId || !currentPaymentData) {
        console.error('Brak danych do zapisania');
        return;
    }
    
    const isPaid = document.getElementById('modalPaidCheckbox').checked;
    const invoiceNumber = document.getElementById('modalInvoiceInput').value;
    const statusDiv = document.getElementById('modalPaymentStatus');
    
    // Pokaż ładowanie
    statusDiv.innerHTML = '<div class="saving-indicator">Zapisywanie...</div>';
    
    // Poprawiona konwersja kwoty - usuwamy spacje, zamieniamy przecinek na kropkę i usuwamy 'zł'
    let kwota = 0;
    try {
        kwota = parseFloat(currentPaymentData.amount.replace(/\s/g, '').replace(',', '.').replace('zł', '').trim());
        if (isNaN(kwota)) {
            kwota = 0;
        }
    } catch (e) {
        console.error('Error parsing amount:', e);
        kwota = 0;
    }
    
    // Przygotuj dane do wysłania
    const paymentData = {
        case_id: currentPaymentData.id_sprawy,
        agent_id: currentPaymentData.id_agenta === 'kuba' ? '1' : currentPaymentData.id_agenta,
        installment_number: currentPaymentData.installmentNumber,
        amount: kwota,
        status: isPaid ? 1 : 0,
        invoice_number: invoiceNumber,
        created_at: isPaid ? new Date().toISOString().split('T')[0] : null
    };

    console.log('Saving payment with data:', paymentData);

    // Wyślij dane do serwera
    fetch('/update-payment', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(paymentData)
    })
    .then(response => {
        console.log('Update payment response:', response);
        if (!response.ok) {
            console.error('Response not OK:', response.status, response.statusText);
            return response.text().then(text => {
                console.error('Response text:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    return { success: false, error: `Server returned ${response.status}: ${text || response.statusText}` };
                }
            });
        }
        return response.json().catch(e => {
            console.error('Error parsing JSON response:', e);
            return { success: false, error: 'Invalid response format' };
        });
    })
    .then(data => {
        console.log('Update payment result data:', data);
        if (data.success) {
            // Aktualizuj wyświetlanie z animacją sukcesu
            statusDiv.innerHTML = '<div class="success-message">Zapisano pomyślnie!</div>';
            
            // Odśwież stronę po zapisie
            setTimeout(() => {
                location.reload(); 
            }, 1500);
        } else {
            console.error('Błąd podczas zapisywania płatności:', data.error);
            statusDiv.innerHTML = `<div class="error-message">Błąd: ${data.error || 'Nieznany błąd'}</div>`;
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        statusDiv.innerHTML = `<div class="error-message">Błąd sieciowy: ${error.message}</div>`;
    });
}

// Funkcja do wczytywania zapisanych informacji przy ładowaniu strony
document.addEventListener('DOMContentLoaded', function() {
    // Inicjalizacja formularza płatności
    const modal = document.getElementById('paymentFormModal');
    if (modal) {
        // Make sure the modal is hidden initially
        modal.style.display = 'none';
        modal.classList.remove('active');
    }

    // Funkcja do obsługi wyszukiwania
    const searchInput = document.getElementById('searchInput');
    const rows = document.querySelectorAll('tr[data-sprawa-id]');
    const suggestionsContainer = document.getElementById('autocompleteSuggestions');
    let selectedIndex = -1;

    // Funkcja do pobierania unikalnych wartości do podpowiedzi
    function getSuggestions(searchTerm) {
        const suggestions = new Set();
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchTerm.toLowerCase())) {
                // Dodaj nazwę sprawy do podpowiedzi
                const sprawaName = row.querySelector('td:first-child').textContent.trim();
                if (sprawaName) {
                    suggestions.add(sprawaName);
                }
            }
        });
        return Array.from(suggestions);
    }

    // Funkcja do podświetlania i przewijania do pasującego rekordu
    function highlightAndScrollToMatch(searchTerm, shouldScroll = false) {
        // Usuń poprzednie podświetlenia
        rows.forEach(row => {
            row.classList.remove('highlight');
        });

        // Znajdź pierwszy pasujący wiersz
        const matchingRow = Array.from(rows).find(row => {
            const text = row.textContent.toLowerCase();
            return text.includes(searchTerm.toLowerCase());
        });

        if (matchingRow) {
            matchingRow.classList.add('highlight');
            // Płynne przewijanie tylko jeśli shouldScroll jest true
            if (shouldScroll) {
                matchingRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }

    // Funkcja do wyświetlania podpowiedzi
    function showSuggestions(suggestions) {
        suggestionsContainer.innerHTML = '';
        if (suggestions.length > 0) {
            suggestions.forEach((suggestion, index) => {
                const div = document.createElement('div');
                div.className = 'autocomplete-suggestion';
                div.textContent = suggestion;
                div.addEventListener('click', () => {
                    searchInput.value = suggestion;
                    suggestionsContainer.style.display = 'none';
                    highlightAndScrollToMatch(suggestion, true); // Scrolluj po kliknięciu
                });
                suggestionsContainer.appendChild(div);
            });
            suggestionsContainer.style.display = 'block';
        } else {
            suggestionsContainer.style.display = 'none';
        }
    }

    // Aktualizacja wybranej podpowiedzi
    function updateSelectedSuggestion() {
        const suggestions = document.querySelectorAll('.autocomplete-suggestion');
        suggestions.forEach((suggestion, index) => {
            suggestion.classList.toggle('selected', index === selectedIndex);
            if (index === selectedIndex) {
                suggestion.scrollIntoView({ block: 'nearest' });
            }
        });
    }

    // Obsługa wpisywania tekstu
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.trim();
        selectedIndex = -1;
        
        if (searchTerm.length > 0) {
            const suggestions = getSuggestions(searchTerm);
            showSuggestions(suggestions);
            highlightAndScrollToMatch(searchTerm, false); // Nie scrolluj podczas wpisywania
        } else {
            suggestionsContainer.style.display = 'none';
            rows.forEach(row => row.classList.remove('highlight'));
        }
    });

    // Obsługa zdarzeń klawiatury
    searchInput.addEventListener('keydown', function(e) {
        const suggestions = document.querySelectorAll('.autocomplete-suggestion');
        
        switch(e.key) {
            case 'ArrowDown':
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, suggestions.length - 1);
                updateSelectedSuggestion();
                break;
            case 'ArrowUp':
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, -1);
                updateSelectedSuggestion();
                break;
            case 'Enter':
                e.preventDefault();
                if (selectedIndex >= 0 && suggestions[selectedIndex]) {
                    const selectedText = suggestions[selectedIndex].textContent;
                    searchInput.value = selectedText;
                    suggestionsContainer.style.display = 'none';
                    highlightAndScrollToMatch(selectedText, true); // Scrolluj po Enter
                }
                break;
            case 'Escape':
                suggestionsContainer.style.display = 'none';
                selectedIndex = -1;
                break;
        }
    });

    // Zamknij podpowiedzi po kliknięciu poza nimi
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
            suggestionsContainer.style.display = 'none';
        }
    });
});

// Modal logic
let deleteCaseId = null;
let deleteCaseNazwisko = '';

function openDeleteModal(btn) {
    deleteCaseId = btn.getAttribute('data-sprawa-id');
    deleteCaseNazwisko = btn.getAttribute('data-sprawa-nazwisko');
    const caseName = btn.getAttribute('data-sprawa-nazwa');
    document.getElementById('deleteCaseText').innerHTML = `Czy na pewno chcesz usunąć sprawę <b>${caseName}</b>?<br>To działanie jest nieodwracalne.`;
    document.getElementById('deleteCaseInput').value = '';
    document.getElementById('deleteCaseConfirmBtn').disabled = true;
    document.getElementById('deleteCaseModal').style.display = 'flex';
    document.getElementById('deleteCaseInput').focus();
}
function closeDeleteModal() {
    document.getElementById('deleteCaseModal').style.display = 'none';
    deleteCaseId = null;
    deleteCaseNazwisko = '';
}
document.getElementById('deleteCaseInput').addEventListener('input', function() {
    const val = this.value.trim();
    document.getElementById('deleteCaseConfirmBtn').disabled = (val !== deleteCaseNazwisko);
});
document.getElementById('deleteCaseConfirmBtn').onclick = function() {
    if (!deleteCaseId) return;
    // Wyślij żądanie usunięcia (POST)
    fetch(`/sprawy/${deleteCaseId}/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ confirm: true })
    })
    .then(res => {
        if (res.ok) {
            location.reload();
        } else {
            alert('Wystąpił błąd podczas usuwania sprawy.');
        }
    });
};
</script>
</body>
</html>
