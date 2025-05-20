<?php
// Include helper functions for formatting
require_once __DIR__ . '/../Lib/helpers.php';

// Initialize database connection
require_once __DIR__ . '/../../config/database.php';
global $pdo;

// Define constants and helper variables
$epsilon = 0.01; // For floating point comparisons
$kuba_id = 1; // Assuming Kuba's agent ID is 1

// Get agent list (now using new schema)
$agentsQuery = "SELECT id_agenta, nazwa_agenta FROM agenci ORDER BY nazwa_agenta";
$agentsStmt = $pdo->query($agentsQuery);
$agenci = [];
while ($agent = $agentsStmt->fetch(PDO::FETCH_ASSOC)) {
    $agenci[$agent['id_agenta']] = $agent['nazwa_agenta'];
}

// Define raty structure
$ratyOpis = ['Rata 1', 'Rata 2', 'Rata 3', 'Rata 4', 'Rata 5', 'Rata 6', 'Rata Końcowa'];
$liczbaRat = count($ratyOpis);

// Get all cases/sprawy (new schema)
$sprawyQuery = "SELECT DISTINCT
    s.id_sprawy,
    s.identyfikator_sprawy,
    s.czy_zakonczona,
    s.wywalczona_kwota,
    s.oplata_wstepna,
    s.stawka_success_fee,
    s.identyfikator_sprawy as imie_nazwisko_klienta
    FROM sprawy s";

// Dodaj warunek filtrowania po agencie, jeśli został wybrany
if (isset($filterByAgentId) && $filterByAgentId) {
    $sprawyQuery .= " INNER JOIN prowizje_agentow_spraw pas ON s.id_sprawy = pas.id_sprawy
                      WHERE pas.id_agenta = :agent_id";
}

$sprawyQuery .= " ORDER BY s.id_sprawy DESC";

// Przygotuj i wykonaj zapytanie
$sprawyStmt = $pdo->prepare($sprawyQuery);
if (isset($filterByAgentId) && $filterByAgentId) {
    $sprawyStmt->bindParam(':agent_id', $filterByAgentId);
}
$sprawyStmt->execute();
$sprawyData = [];

while ($sprawa = $sprawyStmt->fetch(PDO::FETCH_ASSOC)) {
    $id_sprawy = $sprawa['id_sprawy'];

    // Get agent commissions for this case (new schema)
    $prowizjeQuery = "SELECT id_agenta, udzial_prowizji_proc FROM prowizje_agentow_spraw WHERE id_sprawy = ?";
    $prowizjeStmt = $pdo->prepare($prowizjeQuery);
    $prowizjeStmt->execute([$id_sprawy]);
    $sprawa['prowizje_proc'] = [];
    while ($prowizja = $prowizjeStmt->fetch(PDO::FETCH_ASSOC)) {
        $sprawa['prowizje_proc'][$prowizja['id_agenta']] = $prowizja['udzial_prowizji_proc'];
    }

    // Oblicz całość prowizji
    $sprawa['calosc_prowizji'] = floatval($sprawa['oplata_wstepna']) + (floatval($sprawa['wywalczona_kwota']) * floatval($sprawa['stawka_success_fee']));

    // Oblicz do_wyplaty_kuba_proc (jako udział, np. 0.25 = 25%)
    $kuba_proc = $sprawa['prowizje_proc'][$kuba_id] ?? 0.0;
    $suma_udzialow_agentow = 0.0;
    foreach ($sprawa['prowizje_proc'] as $agent_id => $udzial) {
        if ($agent_id != $kuba_id) {
            $suma_udzialow_agentow += floatval($udzial);
        }
    }
    $sprawa['do_wyplaty_kuba_proc'] = max(0, $kuba_proc - $suma_udzialow_agentow);

    // Map the installment fields from oplaty_spraw
    $sprawa['oplaty'] = [];
    foreach ($ratyOpis as $opisRaty) {
        $stmt = $pdo->prepare("SELECT oczekiwana_kwota, czy_oplacona, faktura_id FROM oplaty_spraw WHERE id_sprawy = ? AND opis_raty = ?");
        $stmt->execute([$id_sprawy, $opisRaty]);
        $rata = $stmt->fetch(PDO::FETCH_ASSOC);
        $sprawa['oplaty'][$opisRaty] = [
            'kwota' => $rata['oczekiwana_kwota'] ?? null,
            'is_paid_display' => (bool)($rata['czy_oplacona'] ?? false),
            'faktura_id' => $rata['faktura_id'] ?? null,
            'paying_invoice_number' => null
        ];
    }

    // Get commission payment info from agenci_wyplaty (new schema)
    // This is the key change - properly retrieve all agent payment data
    $sprawa['prowizje_raty'] = [];
    foreach ($ratyOpis as $opisRaty) {
        // Fix: Use proper format matching what's stored in the database
        // Create all possible variations of the payment description to account for case differences
        $paymentDescOptions = [
            'Prowizja ' . $opisRaty,
            'Prowizja Rata ' . str_replace('Rata ', '', $opisRaty),
            'Prowizja rata ' . str_replace('Rata ', '', $opisRaty)
        ];

        $placeholders = implode(',', array_fill(0, count($paymentDescOptions), '?'));
        $params = array_merge([$id_sprawy], $paymentDescOptions);

        $stmt = $pdo->prepare("SELECT id_agenta, kwota, czy_oplacone, numer_faktury, opis_raty FROM agenci_wyplaty 
                              WHERE id_sprawy = ? 
                              AND opis_raty IN ($placeholders)");
        $stmt->execute($params);

        while ($prow = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sprawa['prowizje_raty'][$opisRaty][$prow['id_agenta']] = [
                'kwota' => $prow['kwota'],
                'czy_oplacone' => (bool)$prow['czy_oplacone'],
                'numer_faktury' => $prow['numer_faktury'],
                'opis_raty' => $prow['opis_raty'] // Add this to track which format was found
            ];
        }
    }

    $sprawyData[$id_sprawy] = $sprawa;
}

// Function to sync payment statuses (simplified version)
function syncPaymentStatuses($pdo)
{
    // Update payment statuses based on agenci_wyplaty table
    $updateQuery = "UPDATE oplaty_spraw os
                   JOIN agenci_wyplaty aw ON os.id_sprawy = aw.id_sprawy 
                   AND os.opis_raty COLLATE utf8mb4_polish_ci = REPLACE(aw.opis_raty, 'Prowizja ', '')
                   SET os.czy_oplacona = aw.czy_oplacone
                   WHERE aw.czy_oplacone = 1";
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
    <link rel="stylesheet" href="/assets/css/test.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="/assets/js/responsive.js" defer></script>

</head>
<style>
    :root {
        --primary-color: #64b5f6;
        --primary-light: #9be7ff;
        --primary-dark: #2286c3;
        --secondary-color: #e3f2fd;
        --text-primary: #37474f;
        --text-secondary: #546e7a;
        --background: #f5f7fa;
        --card-bg: #ffffff;
        --border-color: #eceff1;
        --shadow-light: 0 2px 8px rgba(0, 0, 0, 0.05);
        --shadow-medium: 0 4px 12px rgba(0, 0, 0, 0.08);
        --accent-color: #81c784;
        --error-color: #ef5350;
        --header-height: 18vh; /* Added header height variable for consistency */
    }

    header {
        position: fixed; /* Changed from absolute to fixed */
        top: 0;
        left: 60px;
        width: calc(100% - 60px);
        height: var(--header-height);
        background: linear-gradient(135deg,
                var(--primary-dark),
                var(--primary-color));
        color: white;
        display: flex;
        justify-content: center;
        align-items: center;
        box-shadow: var(--shadow-medium);
        z-index: 1000;
    }

    header h1 {
        font-size: 2.2rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        margin: 0;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
        color: white;
    }
    
    /* Add spacing for content to appear below header */
    .content-wrapper {
        margin-top: calc(var(--header-height) + 20px); /* Header height plus some extra space */
        padding: 0 20px;
    }
    
    /* Adjust body styling */
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        line-height: 1.4;
        margin: 0; /* Reset margin */
        margin-left: 60px; /* Adjusted for the collapsed navigation */
        background-color: #f9f9f9;
        color: #333;
        transition: margin-left 0.3s ease;
        padding: 0; /* Reset padding */
    }
    
    /* When navigation is expanded, adjust margins */
    body.nav-expanded {
        margin-left: 220px; /* Adjusted to account for expanded nav width */
    }


    .status-cell {
        text-align: center !important;
        font-size: 1.4em;
        vertical-align: middle;
        
    }
    /* Adjust nav-toggle button to not overlap with header */
    .nav-toggle {
        position: fixed;
        top: calc(var(--header-height) + 10px);
        left: 70px;
        z-index: 1002;
        background-color: #333;
        color: white;
        border: none;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    
    .nav-toggle.expanded {
        left: 230px; /* Move toggle button when nav is expanded */
    }

    /* Style dla opłaconych rat */
    .prowizje-rata-cell .agent-payout.paid-installment {
        background-color: #f8f9fa; /* Changed from #ffebee to neutral color */
        padding: 4px 8px;
        border-radius: 4px;
        border-left: 3px solid #007bff; /* Changed from #dc3545 to neutral blue */
        margin: 4px 0;
        animation: none; /* Removed glow animation */
        position: relative;
    }
    .prowizje-rata-cell .agent-payout.paid-installment .agent-name {
        color: #555; /* Changed from #b71c1c to neutral color */
        font-weight: 600;
    }
    .prowizje-rata-cell .agent-payout.paid-installment .agent-amount {
        color: #2c3e50; /* Changed from #b71c1c to neutral color */
        font-weight: 600;
    }

    /* Styles for agent-info when installment is not paid */
    .agent-info {
        margin-bottom: 8px;
        padding: 5px;
        display: block;
        position: relative;
        border-radius: 3px;
        background-color: #f8f9fa;
        border-left: 3px solid #adb5bd;
    }

    .agent-info .agent-name {
        font-weight: normal;
        color: #555;
    }

    .agent-info .agent-amount {
        font-weight: normal;
        color: #555;
    }

    .kuba-info {
        border-left: 3px solid #ffc107;
    }

    /* Style dla powiadomień */
    .notification {
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 10px 15px;
        border-radius: 4px;
        color: white;
        font-weight: 500;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        z-index: 1000;
        transition: opacity 0.3s ease;
    }

    .notification.success {
        background-color: #4CAF50;
    }

    .notification.info {
        background-color: #2196F3;
    }

    .notification.error {
        background-color: #F44336;
    }

    /* Styl dla agent-info bez czerwonych kolorów */

    /* Mobile navigation styles */
    .mobile-menu-btn {
        display: none;
        position: fixed;
        top: 10px;
        left: 10px;
        width: 40px;
        height: 40px;
        background-color: rgba(0, 0, 0, 0.7);
        color: white;
        border: none;
        border-radius: 5px;
        z-index: 1010;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        cursor: pointer;
    }
    
    .mobile-nav {
        position: fixed;
        top: 0;
        left: -280px;
        width: 280px;
        height: 100vh;
        background-color: #000;
        z-index: 1005;
        transition: left 0.3s ease;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2);
        overflow-y: auto;
        padding-top: 60px;
    }
    
    .mobile-nav.active {
        left: 0;
    }
    
    .mobile-nav-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        display: none;
    }
    
    .mobile-nav-overlay.active {
        display: block;
    }
    
    .mobile-menu-close {
        position: absolute;
        top: 10px;
        right: 10px;
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
    }
    
    .mobile-nav-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .mobile-nav-item {
        margin: 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .mobile-nav-link {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        color: white;
        text-decoration: none;
        transition: background-color 0.2s;
    }
    
    .mobile-nav-link:hover,
    .mobile-nav-link.active {
        background-color: rgba(255, 255, 255, 0.1);
    }
    
    .mobile-nav-icon {
        margin-right: 15px;
        width: 20px;
        text-align: center;
    }
    
    /* Responsive table styles */
    .swipe-indicator {
        display: none; /* Hide swipe indicator completely */
    }
    
    /* Media queries for responsive design */
    @media (max-width: 992px) {
        body {
            padding-top: 60px;
            padding-left: 0;
        }
        
        header {
            left: 0;
            width: 100%;
            height: 60px;
        }
        
        header h1 {
            font-size: 1.6rem;
        }
        
        .content-wrapper {
            margin-top: 70px;
            padding: 0;  /* Remove horizontal padding to maximize width */
        }
        
        .cleannav {
            display: none;
        }
        
        .mobile-menu-btn {
            display: flex;
        }
        
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            width: 100%;
            position: relative;
            max-width: 100%;
            margin-left: 0; /* Move table to the left */
            padding-left: 0; /* Remove any padding */
        }
        
        table {
            width: 100%; /* Full width table */
            table-layout: auto; /* Allow table to size based on content */
        }
        
        .search-container {
            width: 100%;
            padding: 0 10px;
        }
        
        .search-input {
            width: 100%;
        }
    }
    
    @media (max-width: 768px) {
        .mobile-menu-btn {
            top: 10px;
        }
        
        header {
            height: 50px;
        }
        
        header h1 {
            font-size: 1.4rem;
        }
        
        .content-wrapper {
            margin-top: 60px;
            padding: 0; /* Remove padding to maximize space */
        }
        
        /* Table styling for mobile without text truncation */
        .table-container {
            overflow-x: auto;
            width: 100%;
            margin: 10px 0;
            margin-left: 0; /* Move table fully to the left */
            position: relative;
            z-index: 1;
        }
        
        table {
            width: 100%;
            table-layout: auto; /* Let content determine width */
            position: relative;
        }
        
        tbody {
            position: relative;
            z-index: 1;
        }
        
        tr {
            position: relative;
            z-index: 1;
        }
        
        th, td {
            padding: 8px 5px;
            font-size: 0.85rem;
            white-space: normal; /* Allow text wrapping */
            overflow: visible; /* Show all content */
            text-overflow: clip; /* Don't add ellipsis */
            max-width: none; /* Allow cells to be as wide as needed */
            word-wrap: break-word; /* Break words to prevent overflow */
        }
        
        /* Fix sticky header on mobile */
        thead {
            position: sticky;
            top: 50px;
            z-index: 2;
        }
        
        th {
            background-color: #f8f9fa;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            white-space: normal; /* Allow text wrapping in headers too */
        }
    }
    
    @media (max-width: 480px) {
        header h1 {
            font-size: 1.2rem;
        }
        
        .mobile-menu-btn {
            width: 35px;
            height: 35px;
            font-size: 18px;
        }
        
        th, td {
            padding: 6px 4px;
            font-size: 0.8rem;
            max-width: none; /* Remove max-width constraint */
            white-space: normal; /* Enable wrapping */
        }
    }
    
    /* Specific styling for podsumowanie-spraw tables on mobile and tablet */
    @media (max-width: 992px) {
        /* Ensure the table takes full width but content is visible */
        .table-container {
            max-width: 100vw;
            width: 100%;
            overflow-x: visible;
        }
        
        /* Make sure the table itself is fully visible */
        .table-container table {
            width: 100%;
            margin: 0;
            border-collapse: collapse;
        }
        
        /* Adjust column widths to be more appropriate for the content */
        .table-container th:first-child,
        .table-container td:first-child {
            min-width: 80px; /* Sprawa column */
        }
        
        .table-container th:nth-child(2),
        .table-container td:nth-child(2) {
            min-width: 40px; /* Zakończona column */
        }
        
        /* Currency columns */
        .table-container .currency {
            min-width: 70px;
            text-align: right;
        }
        
        /* Percentage columns */
        .table-container .percentage {
            min-width: 50px;
            text-align: right;
        }
        
        /* Details columns */
        .table-container .details-section {
            min-width: 120px;
        }
        
        /* Action cell - keep minimal */
        .table-container .action-cell {
            min-width: 70px;
        }
        
        /* Make sure the table fills the horizontal space without causing overflow */
        .content-wrapper {
            overflow-x: hidden;
            width: 100%;
        }
    }
</style>

<body>
    <?php include_once __DIR__ . '/components/user_info.php'; ?>
    <!-- Mobile hamburger menu button -->
    <button class="mobile-menu-btn" id="mobileMenuBtn">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Mobile navigation overlay -->
    <div class="mobile-nav-overlay" id="mobileNavOverlay"></div>
    
    <!-- Mobile slide-out navigation -->
    <nav class="mobile-nav" id="mobileNav">
        <button class="mobile-menu-close" id="mobileMenuClose">
            <i class="fas fa-times"></i>
        </button>
        <ul class="mobile-nav-list">
            <li class="mobile-nav-item">
                <a href="/" class="mobile-nav-link">
                    <i class="fa-solid fa-house mobile-nav-icon"></i>
                    <span>Strona główna</span>
                </a>
            </li>
            <li class="mobile-nav-item">
                <a href="/invoices" class="mobile-nav-link">
                    <i class="fa-solid fa-file-invoice mobile-nav-icon"></i>
                    <span>Faktury</span>
                </a>
            </li>
            <li class="mobile-nav-item">
                <a href="/agents" class="mobile-nav-link">
                    <i class="fa-solid fa-user-plus mobile-nav-icon"></i>
                    <span>Dodaj agenta</span>
                </a>
            </li>
            <li class="mobile-nav-item">
                <a href="/wizard" class="mobile-nav-link">
                    <i class="fa-solid fa-wand-magic-sparkles mobile-nav-icon"></i>
                    <span>Kreator rekordu</span>
                </a>
            </li>
            <li class="mobile-nav-item">
                <a href="/podsumowanie-spraw" class="mobile-nav-link active">
                    <i class="fa-solid fa-file-invoice-dollar mobile-nav-icon"></i>
                    <span>Podsumowanie Faktur</span>
                </a>
            </li>
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin'): ?>
                <li class="mobile-nav-item">
                    <a href="/zarzadzanie-uzytkownikami" class="mobile-nav-link">
                        <i class="fa-solid fa-users-cog mobile-nav-icon"></i>
                        <span>Zarządzanie Użytkownikami</span>
                    </a>
                </li>
                <li class="mobile-nav-item">
                    <a href="/database" class="mobile-nav-link">
                        <i class="fa-solid fa-database mobile-nav-icon"></i>
                        <span>Zarządzaj bazą</span>
                    </a>
                </li>
            <?php endif; ?>
            <li class="mobile-nav-item">
                <a href="/logout" class="mobile-nav-link">
                    <i class="fa-solid fa-sign-out-alt mobile-nav-icon"></i>
                    <span>Wyloguj</span>
                </a>
            </li>
        </ul>
    </nav>
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
                <a href="/wizard" class="cleannav__link" data-tooltip="Kreator rekordu">
                    <i class="fa-solid fa-wand-magic-sparkles cleannav__icon"></i>
                </a>
            </li>
            <li class="cleannav__item">
                <a href="/podsumowanie-spraw" class="cleannav__link active" data-tooltip="Podsumowanie Faktur">
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
        <h1>Podsumowanie Spraw</h1>
    </header>

    <!-- Added content wrapper div to contain all content below header -->
    <div class="content-wrapper">


        <div class="search-container">
            <input type="text" id="searchInput" class="search-input" placeholder="Wyszukaj sprawę..." autocomplete="off">
            <div id="autocompleteSuggestions" class="autocomplete-suggestions"></div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <!-- Nagłówek (bez zmian od ostatniej wersji) -->
                    <tr>
                        <th rowspan="2">Sprawa</th>
                        <th rowspan="2">Zakończona?</th>
                        <th rowspan="2">Wywalczona kwota</th>
                        <th rowspan="2">Opłata wstępna</th>
                        <th rowspan="2">Success fee</th>
                        <th rowspan="2">Całość prowizji (obl.)</th>
                        <th colspan="2">Prowizje (%)</th>
                        <th colspan="<?php echo $liczbaRat; ?>">Raty klienta</th>
                        <th colspan="<?php echo $liczbaRat + 1; ?>">Prowizje agentów</th>
                    </tr>
                    <tr>
                        <th>% Agenci</th>
                        <th>% Do wypłaty Kuba</th><?php foreach ($ratyOpis as $opisRaty): ?><th><?php echo $opisRaty; ?></th><?php endforeach; ?><?php foreach ($ratyOpis as $opisRaty): ?><th>Kwota do wyplaty agentowi</th><?php endforeach; ?><th>Edycja sprawy</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sprawyData)): ?>
                        <tr>
                            <td colspan="<?php echo 8 + $liczbaRat + $liczbaRat + 1; ?>">Brak danych.</td>
                        </tr>
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
                                <td class="percentage"><?php echo format_percent($sprawa['stawka_success_fee'] * 100); ?></td>
                                <td class="currency"><?php echo format_currency($sprawa['calosc_prowizji']); ?></td>

                                <!-- Prowizje % (bez zmian) -->
                                <td>
                                    <div class="details-section"><?php if (!empty($sprawa['prowizje_proc'])): foreach ($sprawa['prowizje_proc'] as $agent_id => $proc): ?><span <?php if (isset($filterByAgentId) && $filterByAgentId == $agent_id): ?>class="agent-highlight" <?php endif; ?>><?php echo htmlspecialchars($agenci[$agent_id] ?? "Agent {$agent_id}"); ?>: <?php echo format_percent($proc); ?></span><br><?php endforeach;
                                                                                                                                                                                                                                                                                                                                                                                                                else: ?> - <?php endif; ?></div>
                                </td>
                                <td class="percentage"><?php echo format_percent($sprawa['do_wyplaty_kuba_proc'] ?? 0.0); ?></td>

                                <!-- Opłaty Ogólne - Logika statusu uwzględnia 0zł ORAZ wynik algorytmu -->
                                <?php foreach ($ratyOpis as $opisRaty): ?>
                                    <?php
                                    $daneRaty = $sprawa['oplaty'][$opisRaty] ?? ['kwota' => null, 'is_paid_display' => false, 'paying_invoice_number' => null];
                                    $kwotaRaty = $daneRaty['kwota'];
                                    $invoiceNumber = $daneRaty['paying_invoice_number'] ?? null;

                                    $czyRataIstnieje = $kwotaRaty > 0;

                                    // Log diagnostyczny
                                    error_log("Rata: $opisRaty, Kwota: $kwotaRaty, CzyIstnieje: " . ($czyRataIstnieje ? 'tak' : 'nie') .
                                        ", CzyOplacona: " . ($daneRaty['is_paid_display'] ? 'tak' : 'nie') .
                                        ", Faktura: " . ($invoiceNumber ?? 'brak'));

                                    if ($czyRataIstnieje) {
                                        // Sprawdź warunki opłacenia w tabeli faktury
                                        $czyOplaconaPrzezFakture = false;
                                        $pasujacaFaktura = null;

                                        // Sprawdź czy istnieje już przypisana faktura dla tej raty
                                        $stmt = $pdo->prepare("
                                                SELECT f.numer, f.Status 
                                                FROM faktury f
                                                JOIN oplaty_spraw os ON f.numer = os.faktura_id
                                                WHERE os.id_sprawy = ? 
                                                AND os.opis_raty = ?
                                                AND f.Nabywca = ?
                                                AND f.`Kwota opłacona` = ?
                                                AND f.Status = 'Opłacona'
                                            ");
                                        $stmt->execute([$sprawa['id_sprawy'], $opisRaty, $sprawa['identyfikator_sprawy'], $kwotaRaty]);
                                        $faktura = $stmt->fetch(PDO::FETCH_ASSOC);

                                        if ($faktura) {
                                            $czyOplaconaPrzezFakture = true;
                                            $pasujacaFaktura = $faktura['numer'];
                                        } else {
                                            // Jeśli nie ma przypisanej faktury, sprawdź czy istnieje wolna opłacona faktura
                                            $stmt = $pdo->prepare("
                                                    SELECT f.numer, f.Status 
                                                    FROM faktury f
                                                    LEFT JOIN oplaty_spraw os ON f.numer = os.faktura_id
                                                    WHERE f.Nabywca = ? 
                                                    AND f.`Kwota opłacona` = ?
                                                    AND f.Status = 'Opłacona'
                                                    AND os.faktura_id IS NULL
                                                ");
                                            $stmt->execute([$sprawa['identyfikator_sprawy'], $kwotaRaty]);
                                            $faktura = $stmt->fetch(PDO::FETCH_ASSOC);

                                            if ($faktura) {
                                                $pasujacaFaktura = $faktura['numer'];
                                                // Przypisz opłaconą fakturę do tej raty
                                                $stmt = $pdo->prepare("
                                                        UPDATE oplaty_spraw 
                                                        SET faktura_id = ?, czy_oplacona = 1 
                                                        WHERE id_sprawy = ? AND opis_raty = ?
                                                    ");
                                                $stmt->execute([$faktura['numer'], $sprawa['id_sprawy'], $opisRaty]);
                                                $czyOplaconaPrzezFakture = true;
                                            }
                                        }

                                        $finalnieWyswietlJakoOplacona = $czyOplaconaPrzezFakture;
                                        $statusClass = $finalnieWyswietlJakoOplacona ? 'status-oplacona' : 'status-nieoplacona';
                                        $statusSymbol = $finalnieWyswietlJakoOplacona ? '☑' : '☐';
                                        $wyswietlanaKwota = format_currency($kwotaRaty);

                                        // Przygotuj informację o fakturze
                                        $invoiceTextHtml = '';
                                        if ($pasujacaFaktura !== null) {
                                            $invoiceTextHtml = '<br><small class="invoice-details"> Faktura: ' . htmlspecialchars($pasujacaFaktura) . '</small>';
                                        }
                                    } else {
                                        // Rata nie istnieje lub jest zerowa
                                        $statusClass = '';
                                        $statusSymbol = '';
                                        $wyswietlanaKwota = '<span style="color: #999; font-style: italic;">brak raty</span>';
                                        $invoiceTextHtml = '';
                                    }
                                    ?>
                                    <td class="currency <?php echo $statusClass; ?>">
                                        <?php echo $wyswietlanaKwota; ?>
                                        <?php if ($czyRataIstnieje): ?>
                                            <span class="checkbox"><?php echo $statusSymbol; ?></span>
                                        <?php endif; ?>
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

                                            // Log diagnostyczny dla sekcji prowizji
                                            error_log("Sekcja prowizji - Rata: $opisRaty, Kwota: $kwotaRaty, CzyOplacona: " . ($czyRataOplacona ? 'tak' : 'nie'));
                                            if ($kwotaRaty > 0 && !empty($sprawa['prowizje_proc'])):
                                                // Najpierw wyświetl prowizje dla innych agentów
                                                foreach ($sprawa['prowizje_proc'] as $agent_id => $proc):
                                                    if ($agent_id != $kuba_id): // Pomiń Kubę w pierwszej pętli
                                                        // Używamy wartości procentowej bezpośrednio, bo jest już w formacie dziesiętnym
                                                        $kwotaProwizji = $kwotaRaty * $proc;
                                                        if ($kwotaProwizji > $epsilon):
                                                            $uniqueId = "payment_{$sprawa['id_sprawy']}_{$opisRaty}_{$agent_id}";

                                                            // Pobierz dane o płatności dla tego agenta z prowizje_raty
                                                            $czyOplacone = false;
                                                            $numerFaktury = null;
                                                            if (isset($sprawa['prowizje_raty'][$opisRaty][$agent_id])) {
                                                                $platnosc = $sprawa['prowizje_raty'][$opisRaty][$agent_id];
                                                                $czyOplacone = $platnosc['czy_oplacone'] ?? false;
                                                                $numerFaktury = $platnosc['numer_faktury'] ?? null;
                                                            }

                                                            // Ustaw klasy CSS na podstawie statusu
                                                            $payoutClass = $czyOplacone ? 'zaplecono-faktura' : '';
                                            ?>
                                                            <?php if ($czyRataOplacona): ?>
                                                            <div class="agent-payout <?php echo $payoutClass; ?>" data-payment-id="payment_<?php echo $sprawa['id_sprawy']; ?>_<?php echo $opisRaty; ?>_<?php echo $agent_id; ?>">
                                                            <?php else: ?>
                                                            <div class="agent-info" data-payment-id="payment_<?php echo $sprawa['id_sprawy']; ?>_<?php echo $opisRaty; ?>_<?php echo $agent_id; ?>">
                                                            <?php endif; ?>
                                                                <span class="agent-name"><?php echo htmlspecialchars($agenci[$agent_id] ?? "Agent {$agent_id}"); ?>:</span>
                                                                <span class="agent-amount"><?php echo format_currency($kwotaProwizji); ?></span>

                                                                <?php if ($czyOplacone && $numerFaktury): ?>
                                                                    <div class="status-zapłacono">Zapłacono (Faktura: <?php echo htmlspecialchars($numerFaktury); ?>)</div>
                                                                <?php elseif ($czyRataOplacona && !$czyOplacone): ?>
                                                                    <a href="#" class="uzupelnij-link" onclick="openPaymentModal('payment_<?php echo $sprawa['id_sprawy']; ?>_<?php echo $opisRaty; ?>_<?php echo $agent_id; ?>')">Uzupełnij</a>
                                                                <?php endif; ?>
                                                            </div>
                                                    <?php
                                                        endif;
                                                    endif;
                                                endforeach;

                                                // Teraz wyświetl prowizję dla Kuby używając obliczonego procentu
                                                $kubaKwotaProwizji = $kwotaRaty * $sprawa['do_wyplaty_kuba_proc'];
                                                if ($kubaKwotaProwizji > $epsilon):
                                                    $kubaUniqueId = "payment_{$sprawa['id_sprawy']}_{$opisRaty}_{$kuba_id}";

                                                    // Pobierz dane o płatności dla Kuby z prowizje_raty
                                                    $czyOplacone = false;
                                                    $numerFaktury = null;
                                                    if (isset($sprawa['prowizje_raty'][$opisRaty][$kuba_id])) {
                                                        $platnosc = $sprawa['prowizje_raty'][$opisRaty][$kuba_id];
                                                        $czyOplacone = $platnosc['czy_oplacone'] ?? false;
                                                        $numerFaktury = $platnosc['numer_faktury'] ?? null;
                                                    }

                                                    // Ustaw klasy CSS na podstawie statusu
                                                    $kubaPayoutClass = 'kuba-payout';
                                                    if ($czyOplacone) {
                                                        $kubaPayoutClass .= ' zaplacono-faktura';
                                                    }
                                                    ?>
                                                    <?php if ($czyRataOplacona): ?>
                                                    <div class="agent-payout <?php echo $kubaPayoutClass; ?>" data-payment-id="payment_<?php echo $sprawa['id_sprawy']; ?>_<?php echo $opisRaty; ?>_<?php echo $kuba_id; ?>">
                                                    <?php else: ?>
                                                    <div class="agent-info kuba-info" data-payment-id="payment_<?php echo $sprawa['id_sprawy']; ?>_<?php echo $opisRaty; ?>_<?php echo $kuba_id; ?>">
                                                    <?php endif; ?>
                                                        <span class="agent-name">Kuba:</span>
                                                        <span class="agent-amount"><?php echo format_currency($kubaKwotaProwizji); ?></span>

                                                        <?php if ($czyOplacone && $numerFaktury): ?>
                                                            <div class="status-zapłacono">Zapłacono (Faktura: <?php echo htmlspecialchars($numerFaktury); ?>)</div>
                                                        <?php elseif ($czyRataOplacona && !$czyOplacone): ?>
                                                            <a href="#" class="uzupelnij-link" onclick="openPaymentModal('payment_<?php echo $sprawa['id_sprawy']; ?>_<?php echo $opisRaty; ?>_<?php echo $kuba_id; ?>')">Uzupełnij</a>
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
                                    <a href="/wizard?id=<?php echo $sprawa['id_sprawy']; ?>" class="action-btn edit" title="Edytuj">
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
    </div> <!-- End of content-wrapper -->

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

            // Wyczyść poprzednie statusy i ustaw domyślne wartości
            document.getElementById('modalPaidCheckbox').checked = false;
            document.getElementById('modalInvoiceInput').value = '';
            document.getElementById('modalPaymentStatus').innerHTML = '';

            // Extract installment number or text from opis_raty
            let installmentNumber;
            if (opis_raty.includes('Końcowa')) {
                installmentNumber = 'Końcowa';
            } else {
                // Extract digit from "Rata X"
                const match = opis_raty.match(/\d+/);
                installmentNumber = match ? match[0] : opis_raty.replace('Rata ', '');
            }

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
            const agent_id_param = id_agenta;
            const url = `/get-payment-status?case_id=${id_sprawy}&installment_number=${currentPaymentData.installmentNumber}&agent_id=${agent_id_param}`;

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

                        // Log the payment_desc to help with debugging format issues
                        if (data.payment_desc) {
                            console.log('Found payment description format:', data.payment_desc);
                        }
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

            // Walidacja danych
            if (isPaid && !invoiceNumber.trim()) {
                statusDiv.innerHTML = '<div class="error-message">Podaj numer faktury przy zaznaczeniu jako opłacone</div>';
                return;
            }

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
                                return {
                                    success: false,
                                    error: `Server returned ${response.status}: ${text || response.statusText}`
                                };
                            }
                        });
                    }
                    return response.json().catch(e => {
                        console.error('Error parsing JSON response:', e);
                        return {
                            success: false,
                            error: 'Invalid response format'
                        };
                    });
                })
                .then(data => {
                    console.log('Update payment result data:', data);
                    if (data.success) {
                        // Log the payment_desc to help with debugging format issues
                        if (data.payment_desc) {
                            console.log('Saved with payment description:', data.payment_desc);
                        }

                        // Apply the green background directly to the element without waiting for page reload
                        try {
                            const paymentElement = document.querySelector(`.agent-payout[data-payment-id="${currentPaymentId}"]`);
                            if (paymentElement) {
                                if (isPaid) {
                                    // Add the green background class
                                    paymentElement.classList.add('zaplecono-faktura');

                                    // Add paid status text
                                    let statusText = paymentElement.querySelector('.status-zapłacono');
                                    if (!statusText) {
                                        statusText = document.createElement('div');
                                        statusText.className = 'status-zapłacono';
                                        statusText.textContent = `Zapłacono (Faktura: ${invoiceNumber})`;
                                        paymentElement.appendChild(statusText);
                                    } else {
                                        statusText.textContent = `Zapłacono (Faktura: ${invoiceNumber})`;
                                    }

                                    // Remove the "Uzupełnij" link if it exists
                                    const uzupelnijLink = paymentElement.querySelector('.uzupelnij-link');
                                    if (uzupelnijLink) {
                                        uzupelnijLink.remove();
                                    }

                                    // Also update any related invoice cells in the row
                                    const row = paymentElement.closest('tr');
                                    if (row) {
                                        try {
                                            // Find the right installment cell
                                            let installmentCell;
                                            if (currentPaymentData.installmentNumber === 'Końcowa') {
                                                // For final installment, find the cell with "Rata Końcowa"
                                                const allCells = row.querySelectorAll('td');
                                                for (let i = 0; i < allCells.length; i++) {
                                                    if (allCells[i].textContent.includes('Końcowa')) {
                                                        installmentCell = allCells[i];
                                                        break;
                                                    }
                                                }
                                            } else {
                                                // For numeric installments
                                                const installmentNum = parseInt(currentPaymentData.installmentNumber);
                                                installmentCell = row.querySelector(`td:nth-child(${8 + installmentNum})`);
                                            }

                                            if (installmentCell) {
                                                installmentCell.classList.add('status-oplacona');
                                                const checkboxSpan = installmentCell.querySelector('.checkbox');
                                                if (checkboxSpan) {
                                                    checkboxSpan.innerHTML = '☑';
                                                }

                                                // Add invoice info if not already present
                                                if (!installmentCell.querySelector('.invoice-details')) {
                                                    const invoiceDetailsSpan = document.createElement('small');
                                                    invoiceDetailsSpan.className = 'invoice-details';
                                                    invoiceDetailsSpan.innerHTML = `<br>Faktura: ${invoiceNumber}`;
                                                    installmentCell.appendChild(invoiceDetailsSpan);
                                                }
                                            } else {
                                                console.warn('Could not find installment cell for', currentPaymentData.installmentNumber);
                                            }
                                        } catch (cellError) {
                                            console.error('Error updating cell:', cellError);
                                        }
                                    }
                                } else {
                                    // Remove the green background if unpaid
                                    paymentElement.classList.remove('zaplecono-faktura');

                                    // Remove paid status text
                                    const statusText = paymentElement.querySelector('.status-zapłacono');
                                    if (statusText) {
                                        statusText.remove();
                                    }

                                    // Add the "Uzupełnij" link if not already present
                                    if (!paymentElement.querySelector('.uzupelnij-link')) {
                                        const uzupelnijLink = document.createElement('a');
                                        uzupelnijLink.className = 'uzupelnij-link';
                                        uzupelnijLink.href = '#';
                                        uzupelnijLink.textContent = 'Uzupełnij';
                                        uzupelnijLink.onclick = function(e) {
                                            e.preventDefault();
                                            openPaymentModal(currentPaymentId);
                                        };
                                        paymentElement.appendChild(uzupelnijLink);
                                    }
                                }
                            } else {
                                console.error('Could not find payment element:', currentPaymentId);
                            }
                        } catch (e) {
                            console.error('Error updating UI:', e);
                        }

                        // Aktualizuj wyświetlanie z animacją sukcesu
                        statusDiv.innerHTML = '<div class="success-message">Zapisano pomyślnie!</div>';

                        // Store the payment status in localStorage for persistence across page loads
                        try {
                            const paymentKey = `payment_status_${currentPaymentData.id_sprawy}_${currentPaymentData.installmentNumber}_${currentPaymentData.id_agenta}`;
                            localStorage.setItem(paymentKey, JSON.stringify({
                                isPaid: isPaid,
                                invoiceNumber: invoiceNumber,
                                timestamp: new Date().getTime()
                            }));
                            console.log('Saved payment status to localStorage with key:', paymentKey);
                        } catch (storageError) {
                            console.error('Error storing payment status in localStorage:', storageError);
                        }

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
            console.log('DOM loaded - initializing payment system');

            // Inicjalizacja formularza płatności
            const modal = document.getElementById('paymentFormModal');
            if (modal) {
                // Make sure the modal is hidden initially
                modal.style.display = 'none';
                modal.classList.remove('active');
            }

            // Apply green background to all paid invoices after the page loads
            const paidElements = document.querySelectorAll('.agent-payout');
            let paidCount = 0;

            paidElements.forEach(element => {
                const statusElement = element.querySelector('.status-zapłacono');
                if (statusElement) {
                    element.classList.add('zaplecono-faktura');
                    paidCount++;
                }
            });

            console.log(`Found ${paidCount} paid invoices out of ${paidElements.length} total`);

            // Check localStorage for any saved payment statuses
            try {
                const paymentKeys = Object.keys(localStorage).filter(key => key.startsWith('payment_status_'));
                console.log(`Found ${paymentKeys.length} saved payment statuses in localStorage`);

                paymentKeys.forEach(key => {
                    try {
                        const savedStatus = JSON.parse(localStorage.getItem(key));
                        const [_, id_sprawy, installmentNumber, id_agenta] = key.split('_').slice(-3);

                        if (savedStatus.isPaid) {
                            // Look for matching element
                            const paymentId = `payment_${id_sprawy}_${installmentNumber}_${id_agenta}`;
                            const element = document.querySelector(`.agent-payout[data-payment-id="${paymentId}"]`);

                            if (element && !element.classList.contains('zaplecono-faktura')) {
                                // Element exists but doesn't have the paid class - add it
                                element.classList.add('zaplecono-faktura');
                                console.log(`Applied saved paid status to element: ${paymentId}`);
                            }
                        }
                    } catch (parseError) {
                        console.error('Error parsing saved payment status:', parseError);
                    }
                });
            } catch (localStorageError) {
                console.error('Error accessing localStorage:', localStorageError);
            }

            // Function to update payment status
            function updatePaymentStatus(uniqueId, paid, confirmed, invoiceNumber) {
                // Find element by data-payment-id attribute instead of class name
                const paymentElement = document.querySelector(`[data-payment-id="${currentPaymentId}"]`);
                
                if (!paymentElement) {
                    console.error('Payment element not found:', currentPaymentId);
                    return;
                }
                
                // Handle class conversions between agent-info and agent-payout
                const isKuba = paymentElement.getAttribute('data-payment-id').includes('_kuba');
                
                if (paid) {
                    // If paid, convert agent-info to agent-payout if needed
                    if (paymentElement.classList.contains('agent-info')) {
                        paymentElement.classList.remove('agent-info');
                        if (paymentElement.classList.contains('kuba-info')) {
                            paymentElement.classList.remove('kuba-info');
                            paymentElement.classList.add('agent-payout', 'kuba-payout');
                        } else {
                            paymentElement.classList.add('agent-payout');
                        }
                    }
                    
                    // Add 'zaplacono-faktura' class to indicate payment
                    paymentElement.classList.add('zaplacono-faktura');
                    
                    // Show payment info
                    const paymentInfo = document.createElement('div');
                    paymentInfo.className = 'payment-info';
                    paymentInfo.innerHTML = `<div class="invoice-number">Faktura: ${invoiceNumber}</div>`;
                    
                    // Remove any existing payment info
                    const existingPaymentInfo = paymentElement.querySelector('.payment-info');
                    if (existingPaymentInfo) {
                        existingPaymentInfo.remove();
                    }
                    
                    paymentElement.appendChild(paymentInfo);
                } else {
                    // If not paid, convert agent-payout to agent-info
                    if (paymentElement.classList.contains('agent-payout')) {
                        paymentElement.classList.remove('agent-payout', 'zaplacono-faktura');
                        if (paymentElement.classList.contains('kuba-payout')) {
                            paymentElement.classList.remove('kuba-payout');
                            paymentElement.classList.add('agent-info', 'kuba-info');
                        } else {
                            paymentElement.classList.add('agent-info');
                        }
                    }
                    
                    // Remove payment info
                    const existingPaymentInfo = paymentElement.querySelector('.payment-info');
                    if (existingPaymentInfo) {
                        existingPaymentInfo.remove();
                    }
                }
                
                // Remove any existing notification
                const existingNotification = document.getElementById('notification-' + currentPaymentId);
                if (existingNotification) {
                    existingNotification.remove();
                }
                
                // Show success notification
                const notification = document.createElement('div');
                notification.id = 'notification-' + currentPaymentId;
                notification.className = 'notification ' + (paid ? 'success' : 'info');
                notification.textContent = paid ? 'Płatność zapisana!' : 'Status płatności zaktualizowany';
                notification.style.opacity = '0';
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.style.opacity = '1';
                }, 10);
                
                setTimeout(() => {
                    notification.style.opacity = '0';
                    setTimeout(() => {
                        notification.remove();
                    }, 500);
                }, 3000);
            }

            // Function to refresh all payment statuses on the page
            function refreshAllPaymentStatuses() {
                const paymentElements = document.querySelectorAll('[data-payment-status]');
                paymentElements.forEach(element => {
                    const [caseId, installmentDesc, agentId] = element.getAttribute('data-payment-status').split('-');
                    updatePaymentStatus(caseId, installmentDesc, agentId);
                });
            }

            // Refresh payment statuses when page loads
            refreshAllPaymentStatuses();

            // Add event listener for payment updates
            document.addEventListener('paymentUpdated', function(e) {
                const {
                    caseId,
                    installmentDesc,
                    agentId
                } = e.detail;
                updatePaymentStatus(caseId, installmentDesc, agentId);
            });

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
                        matchingRow.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
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
                        suggestion.scrollIntoView({
                            block: 'nearest'
                        });
                    }
                });
            }

            // Obsługa wpisywania tekstu
            if (searchInput) {
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

                    switch (e.key) {
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
            }

            // Zamknij podpowiedzi po kliknięciu poza nimi
            document.addEventListener('click', function(e) {
                if (searchInput && suggestionsContainer &&
                    !searchInput.contains(e.target) &&
                    !suggestionsContainer.contains(e.target)) {
                    suggestionsContainer.style.display = 'none';
                }
            });

            // Setup for expandable navigation
            const navToggle = document.getElementById('navToggle');
            const cleannav = document.querySelector('.cleannav');

            if (navToggle && cleannav) {
                // Check if there's a saved state in localStorage
                const navExpanded = localStorage.getItem('testNavExpanded') === 'true';

                // Apply initial state
                if (navExpanded) {
                    cleannav.classList.add('expanded');
                    navToggle.classList.add('expanded');
                }

                // Add toggle functionality
                navToggle.addEventListener('click', function() {
                    cleannav.classList.toggle('expanded');
                    this.classList.toggle('expanded');
                    document.body.classList.toggle('nav-expanded');

                    // Save state to localStorage
                    const isExpanded = cleannav.classList.contains('expanded');
                    localStorage.setItem('testNavExpanded', isExpanded);
                });

                // Also apply class to body if nav is initially expanded
                if (navExpanded) {
                    document.body.classList.add('nav-expanded');
                }
            }

            // Set up interval to refresh payment statuses periodically
            setInterval(refreshAllPaymentStatuses, 60000); // Refresh every minute
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
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        confirm: true
                    })
                })
                .then(res => {
                    if (res.ok) {
                        location.reload();
                    } else {
                        alert('Wystąpił błąd podczas usuwania sprawy.');
                    }
                });
        };
        
        // Mobile Navigation functionality
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const mobileNav = document.getElementById('mobileNav');
            const mobileNavOverlay = document.getElementById('mobileNavOverlay');
            const mobileMenuClose = document.getElementById('mobileMenuClose');
            
            // Function to open mobile menu
            function openMobileMenu() {
                mobileNav.classList.add('active');
                mobileNavOverlay.classList.add('active');
                document.body.style.overflow = 'hidden'; // Prevent scrolling when menu is open
            }
            
            // Function to close mobile menu
            function closeMobileMenu() {
                mobileNav.classList.remove('active');
                mobileNavOverlay.classList.remove('active');
                document.body.style.overflow = ''; // Restore scrolling
            }
            
            // Add event listeners
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', openMobileMenu);
            }
            
            if (mobileMenuClose) {
                mobileMenuClose.addEventListener('click', closeMobileMenu);
            }
            
            if (mobileNavOverlay) {
                mobileNavOverlay.addEventListener('click', closeMobileMenu);
            }
            
            // Close menu when clicking on a link (for better UX)
            const mobileNavLinks = document.querySelectorAll('.mobile-nav-link');
            mobileNavLinks.forEach(link => {
                link.addEventListener('click', closeMobileMenu);
            });
            
            // Handle device orientation changes
            window.addEventListener('orientationchange', function() {
                // Give some time for the orientation to change
                setTimeout(function() {
                    // Adjust the sticky table headers if they exist
                    const tableHeaders = document.querySelectorAll('thead th');
                    if (tableHeaders.length > 0) {
                        // Apply appropriate sticky positioning based on screen width
                        const isMobile = window.innerWidth < 768;
                        tableHeaders.forEach(header => {
                            header.style.top = isMobile ? '60px' : '0';
                        });
                    }
                }, 300);
            });
            
            // Fix table layout for mobile viewing
            const tableContainers = document.querySelectorAll('.table-container');
            tableContainers.forEach(container => {
                // Add mobile-specific attributes
                container.setAttribute('data-mobile-optimized', 'true');
                
                // Remove the code that adds the swipe indicator
                // No longer needed since we want to display the full table
            });
        });
    </script>
</body>

</html>