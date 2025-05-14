<?php
// --- Konfiguracja Połączenia z Bazą Danych (bez zmian) ---
$db_host = 'localhost';
$db_name = 'projektimport';
$db_user = 'root';
$db_pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$db_host;dbname=$db_name;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
     $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// // --- Funkcje pomocnicze (bez zmian) ---
// function format_currency($amount) {
//     if ($amount === null) return '0,00 zł';
//     return number_format((float)$amount, 2, ',', ' ') . ' zł';
// }
// function format_percent($rate) {
//     if ($rate === null) return '0,00%';
//     return number_format((float)$rate * 100, 2, ',', '') . '%';
// }

// --- Pobieranie i Przetwarzanie Danych ---
$sprawyData = [];
$agenci = [];
$kuba_id = 1; // Załóżmy ID Kuby=1, zweryfikuj!
$paidFakturyPerSprawa = [];
$epsilon = 0.001; // Tolerancja dla porównań kwot

try {
    // Pobierz Agentów (bez zmian)
    $stmtAgenci = $pdo->query("SELECT id_agenta, nazwa_agenta FROM Agenci ORDER BY id_agenta");
    while($agent = $stmtAgenci->fetch()){ $agenci[$agent['id_agenta']] = $agent['nazwa_agenta']; }

    // 1. Pobierz Sprawy (bez zmian)
    $stmtSprawy = $pdo->query("SELECT * FROM Sprawy ORDER BY id_sprawy");
    while ($sprawa = $stmtSprawy->fetch()) {
        $id_sprawy_biezacej = $sprawa['id_sprawy'];
        $sprawyData[$id_sprawy_biezacej] = $sprawa;
        $sprawyData[$id_sprawy_biezacej]['prowizje_proc'] = [];
        $sprawyData[$id_sprawy_biezacej]['oplaty'] = [];
        $sprawyData[$id_sprawy_biezacej]['wyplaty_szczegoly'] = [];
        $sprawyData[$id_sprawy_biezacej]['oplaty_map'] = [];
        $sprawyData[$id_sprawy_biezacej]['calosc_prowizji'] = $sprawa['oplata_wstepna'] + ($sprawa['wywalczona_kwota'] * $sprawa['stawka_success_fee']);
    }

    // 2. Pobierz Prowizje % (bez zmian)
    $stmtProwizje = $pdo->prepare("SELECT id_sprawy, id_agenta, udzial_prowizji_proc FROM Prowizje_Agentow_Spraw");
    $stmtProwizje->execute();
    while ($prowizja = $stmtProwizje->fetch()) { if (isset($sprawyData[$prowizja['id_sprawy']])) $sprawyData[$prowizja['id_sprawy']]['prowizje_proc'][$prowizja['id_agenta']] = (float)$prowizja['udzial_prowizji_proc']; }

    // 3. Oblicz "Do wypłaty Kuba" (bez zmian)
    foreach ($sprawyData as $id_sprawy => &$sprawaRef) { $kuba_proc = $sprawaRef['prowizje_proc'][$kuba_id] ?? 0.0; $inni_agenci_proc_suma = 0.0; foreach ($sprawaRef['prowizje_proc'] as $agent_id => $proc) { if ($agent_id != $kuba_id) $inni_agenci_proc_suma += $proc; } $sprawaRef['do_wyplaty_kuba_proc'] = $kuba_proc - $inni_agenci_proc_suma; } unset($sprawaRef);

    // 4. Pobierz Opłaty i przygotuj strukturę, obliczając Ostatnią tylko jeśli istnieje
    $stmtOplaty = $pdo->prepare("SELECT id_sprawy, id_oplaty_sprawy, opis_raty, oczekiwana_kwota FROM Oplaty_Spraw ORDER BY id_sprawy"); // Sortowanie po ID sprawy wystarczy
    $stmtOplaty->execute();
    // Grupuj pobrane raty wg ID sprawy i opisu raty dla łatwego dostępu
    $tempOplaty = [];
    while ($oplata = $stmtOplaty->fetch()) {
        $tempOplaty[$oplata['id_sprawy']][$oplata['opis_raty']] = $oplata;
        // Zapisz mapowanie ID->Opis od razu
        if(isset($sprawyData[$oplata['id_sprawy']])) {
           $sprawyData[$oplata['id_sprawy']]['oplaty_map'][$oplata['id_oplaty_sprawy']] = $oplata['opis_raty'];
        }
    }

    $ratyOpis = ['Rata 1', 'Rata 2', 'Rata 3', 'Rata 4', 'Rata 5', 'Rata 6', 'Ostatnia']; // Definicja rat

    foreach ($sprawyData as $id_sprawy => &$sprawaRef) {
        $suma_rat_z_bazy_nie_ostatnich = 0.0;
        $ostatnia_rata_z_bazy = null;

        // Przejdź przez zdefiniowane typy rat
        foreach($ratyOpis as $opisRaty) {
            // Sprawdź, czy ta rata istnieje w pobranych danych dla tej sprawy
            if (isset($tempOplaty[$id_sprawy][$opisRaty])) {
                $oplata = $tempOplaty[$id_sprawy][$opisRaty];
                $kwotaRaty = (float)$oplata['oczekiwana_kwota'];

                if ($opisRaty != 'Ostatnia') {
                    $suma_rat_z_bazy_nie_ostatnich += $kwotaRaty;
                    $is_paid = (abs($kwotaRaty) < $epsilon); // Opłacona, jeśli kwota 0
                    $sprawaRef['oplaty'][$opisRaty] = [
                        'id' => $oplata['id_oplaty_sprawy'],
                        'kwota' => $kwotaRaty,
                        'is_paid_display' => $is_paid,
                        'exists_in_db' => true // Dodajemy flagę, że istniała
                    ];
                } else {
                    $ostatnia_rata_z_bazy = $oplata; // Zapamiętaj dane Ostatniej z bazy
                }
            } else {
                // Rata NIE ISTNIEJE w bazie (i nie jest Ostatnią, bo Ostatnią obsłużymy poniżej)
                if ($opisRaty != 'Ostatnia') {
                    $sprawaRef['oplaty'][$opisRaty] = [
                        'id' => null,
                        'kwota' => null, // Będzie wyświetlone jako 0,00 zł
                        'is_paid_display' => true, // Traktuj jako opłaconą
                        'exists_in_db' => false
                    ];
                }
            }
        } // Koniec pętli po $ratyOpis

        // Oblicz kwotę dla "Ostatnia" TYLKO jeśli istniała w bazie lub jest potrzebna
        $obliczona_ostatnia_kwota = max(0, $sprawaRef['calosc_prowizji'] - $suma_rat_z_bazy_nie_ostatnich);

        // Zapisz dane dla Ostatniej raty
        $kwota_do_zapisu_ostatnia = $obliczona_ostatnia_kwota; // Domyślnie użyj obliczonej
        $is_last_paid = (abs($kwota_do_zapisu_ostatnia) < $epsilon); // Czy obliczona jest 0?

        $sprawaRef['oplaty']['Ostatnia'] = [
            'id' => $ostatnia_rata_z_bazy ? $ostatnia_rata_z_bazy['id_oplaty_sprawy'] : null,
            'kwota' => $kwota_do_zapisu_ostatnia,
            'is_paid_display' => $is_last_paid, // Opłacona jeśli obliczona kwota jest 0
            'exists_in_db' => ($ostatnia_rata_z_bazy !== null)
        ];

    } unset($sprawaRef);

    // 5. Pobierz WSZYSTKIE FAKTURY, POSORTOWANE
    $stmtFaktury = $pdo->prepare("SELECT id_faktury, id_sprawy, numer_faktury, wartosc_brutto_pln, data_ostatniej_wplaty, status FROM Faktury WHERE id_sprawy IS NOT NULL ORDER BY id_sprawy, data_ostatniej_wplaty ASC, id_faktury ASC");
    $stmtFaktury->execute();
    while ($faktura = $stmtFaktury->fetch()) { 
        $id_sprawy_faktury = $faktura['id_sprawy']; 
        if (!isset($paidFakturyPerSprawa[$id_sprawy_faktury])) 
            $paidFakturyPerSprawa[$id_sprawy_faktury] = []; 
        $paidFakturyPerSprawa[$id_sprawy_faktury][] = [ 
            'id_faktury' => $faktura['id_faktury'], 
            'numer_faktury' => $faktura['numer_faktury'], 
            'kwota_brutto' => (float)$faktura['wartosc_brutto_pln'], 
            'data_wplaty' => $faktura['data_ostatniej_wplaty'],
            'status' => $faktura['status'],
            'used' => false 
        ]; 
    }

    // 6. Algorytm Dopasowania Chronologicznego Faktur do Rat
    // === POPRAWKA: Pomija raty już oznaczone jako opłacone (te z kwotą 0) ===
    $ratyOpisOrder = ['Rata 1', 'Rata 2', 'Rata 3', 'Rata 4', 'Rata 5', 'Rata 6', 'Ostatnia'];
    foreach ($sprawyData as $id_sprawy => &$sprawaRef) {
                // === DODANO: Inicjalizacja klucza na numer faktury ===
                foreach($ratyOpisOrder as $or) {
                    if (isset($sprawaRef['oplaty'][$or])) {
                        $sprawaRef['oplaty'][$or]['paying_invoice_number'] = null;
                    }
               }
        if (!isset($paidFakturyPerSprawa[$id_sprawy])) continue;

        foreach ($ratyOpisOrder as $opisRaty) {
            if (!isset($sprawaRef['oplaty'][$opisRaty])) continue;

            // <<< POPRAWKA: Pomiń, jeśli rata już jest oznaczona jako opłacona (bo miała kwotę 0) >>>
            if ($sprawaRef['oplaty'][$opisRaty]['is_paid_display'] === true) {
                continue;
            }
            // <<< KONIEC POPRAWKI >>>

            $kwotaRaty = $sprawaRef['oplaty'][$opisRaty]['kwota'];
            // Poniższe sprawdzenie nie jest już krytyczne, ale zostawiamy dla bezpieczeństwa
             if ($kwotaRaty === null) continue;

            // Szukaj pasującej, nieużytej faktury
            foreach ($paidFakturyPerSprawa[$id_sprawy] as $index => &$fakturaRef) {
                if (!$fakturaRef['used'] && abs($fakturaRef['kwota_brutto'] - $kwotaRaty) < $epsilon) {
                    // Ustaw status opłacenia na podstawie statusu faktury
                    $sprawaRef['oplaty'][$opisRaty]['is_paid_display'] = ($fakturaRef['status'] === 'Opłacona');
                    $sprawaRef['oplaty'][$opisRaty]['paying_invoice_number'] = $fakturaRef['numer_faktury'] ?? 'Brak numeru';
                    $fakturaRef['used'] = true;
                    break;
                }
            }
             unset($fakturaRef);
        }
    }
    unset($sprawaRef);

} catch (\PDOException $e) {
    die("Błąd podczas pobierania danych: " . $e->getMessage());
}

$ratyOpis = ['Rata 1', 'Rata 2', 'Rata 3', 'Rata 4', 'Rata 5', 'Rata 6', 'Ostatnia'];
$liczbaRat = count($ratyOpis);

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Podsumowanie Spraw</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="style.css">
     <style>
        /* Reset i podstawy */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.4; margin: 20px; background-color: #f9f9f9; color: #333;}
        h1 { text-align: center; color: #444; margin-bottom: 30px;}
        .table-container { width: 100%; overflow-x: auto; box-shadow: 0 2px 5px rgba(0,0,0,0.1); background-color: #fff; border-radius: 5px;}
        table { border-collapse: collapse; width: 100%; min-width: 1400px; font-size: 13px; border: 1px solid #ddd;}
        th, td { border: 1px solid #e0e0e0; padding: 8px 10px; text-align: left; vertical-align: top; white-space: normal; }
        td { white-space: nowrap; }
        td.prowizje-rata-cell { white-space: normal; vertical-align: top;}

        thead th { background-color: #eef4f7; color: #333; font-weight: 600; text-align: center; vertical-align: middle; border-bottom: 2px solid #cddde5;}
        thead tr:first-child th { border-top: none; }
        tbody tr:nth-child(even) { background-color: #f8fafd;}
        tbody tr:hover { background-color: #e8f0f5; }
        .currency, .percentage, td:nth-child(n+3):not(:nth-child(7)):not(:nth-child(8)):not(:nth-child(n+10)) { text-align: right !important;}
        td:nth-child(7), td:nth-child(8), td:nth-child(n+10) { text-align: left !important; }
        .checkbox { font-size: 1.1em; margin-left: 5px; vertical-align: middle; display: inline-block;}
        .details-section { margin: 0; padding: 0; list-style: none; font-size: 0.95em;}
        .details-section span { margin-bottom: 2px; padding-left: 0px;}

        th:nth-child(1), td:nth-child(1) { font-weight: 500; background-color: #fdfdfe;}
        .status-cell { text-align: center !important; font-size: 1.4em; vertical-align: middle;}
        .status-zakonczona .fa-circle-check { color: #28a745; }
        .status-niezakonczona .fa-circle-xmark { color: #dc3545; }
        .status-cell i[title]:hover { cursor: default; }
        /* POPRAWKA KOLORU: Przywrócono czerwony dla nieopłaconych */
        .status-oplacona { color: #28a745; font-weight: bold; }
        .status-nieoplacona { color: #dc3545; } /* Czerwony dla nieopłaconych */

         .prowizje-rata-cell .agent-payout {
            display: flex;
            flex-direction: column;
            margin-bottom: 3px;
            white-space: nowrap;
        }
        .prowizje-rata-cell .agent-payout .amount-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }
        .prowizje-rata-cell .agent-name {
            text-align: left;
            font-weight: normal;
            color: #555;
            padding-right: 10px;
            flex-shrink: 0;
        }
        .prowizje-rata-cell .agent-amount {
            text-align: right;
            flex-grow: 1;
        }
        .payment-status {
            font-size: 0.85em;
            color: #666;
            margin-top: 2px;
            padding-left: 4px;
            border-left: 2px solid #ddd;
        }
        .nr-faktury-cell { text-align: left !important; vertical-align: top; }
        a.strona{display:inline-block;padding:8px 16px;margin:4px;text-decoration:none;color:#007bff;background-color:#e9f2ff;border:1px solid #007bff;border-radius:6px;font-weight:500;transition:all 0.2s ease-in-out;}
        a.strona:hover{background-color:#007bff;color:#fff;transform:translateY(-2px);box-shadow:0 4px 10px rgba(0,123,255,0.2);}
        a.strona:active{transform:scale(0.98);box-shadow:none;}
        /* Styl dla numeru faktury pod ratą */
 small.invoice-details {
    display: block;
    margin-top: 5px;
    font-size: 0.85em;
    color: #52525b; /* Ciemniejszy, stonowany szary (np. Tailwind zinc-600) */
    white-space: normal;
    text-align: right;
    font-weight: normal;
    font-style: italic; /* Kursywa dla elegancji */
    letter-spacing: 0.3px; /* Lepsze odczytanie małych liter, dodaje "powietrza" */
    border-bottom: 1px dashed lightskyblue; /* Delikatna kreskowana linia pod spodem (np. Tailwind slate-300) */
    padding-bottom: 4px; /* Odstęp pod tekstem do linii */

}
         
        /* Nowe styles dla wyróżnienia Kuby */
        .prowizje-rata-cell .agent-payout.kuba-payout {
            background-color: #fff3cd;
            padding: 4px 8px;
            border-radius: 4px;
            border-left: 3px solid #ffc107;
            margin: 4px 0;
        }
        .prowizje-rata-cell .agent-payout.kuba-payout .agent-name {
            color: #856404;
            font-weight: 600;
        }
        .prowizje-rata-cell .agent-payout.kuba-payout .agent-amount {
            color: #856404;
            font-weight: 600;
        }

        /* Style dla opłaconych rat */
        .prowizje-rata-cell .agent-payout.paid-installment {
            background-color: #ffebee;
            padding: 4px 8px;
            border-radius: 4px;
            border-left: 3px solid #dc3545;
            margin: 4px 0;
            animation: glow 1.5s infinite;
            position: relative;
        }
        .prowizje-rata-cell .agent-payout.paid-installment .agent-name {
            color: #b71c1c;
            font-weight: 600;
        }
        .prowizje-rata-cell .agent-payout.paid-installment .agent-amount {
            color: #b71c1c;
            font-weight: 600;
        }
        .prowizje-rata-cell .agent-payout.paid-installment.payment-confirmed {
            animation: none;
            background-color: #e8f5e9;
            border-left: 3px solid #2e7d32;
        }
        .prowizje-rata-cell .agent-payout.paid-installment.payment-confirmed .agent-name,
        .prowizje-rata-cell .agent-payout.paid-installment.payment-confirmed .agent-amount {
            color: #155724;
        }

        /* Style dla przycisku i formularza */
        .payment-form-toggle {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            font-size: 0.85em;
            padding: 2px 6px;
            margin-top: 2px;
            text-decoration: underline;
            display: block;
        }
        .payment-form-toggle:hover {
            color: #b71c1c;
        }
        .payment-form {
            display: none;
            margin-top: 8px;
            padding: 8px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .payment-form.active {
            display: block;
        }
        .payment-form .form-group {
            margin-bottom: 8px;
        }
        .payment-form label {
            display: block;
            margin-bottom: 4px;
            color: #666;
        }
        .payment-form input[type="text"] {
            width: 100%;
            padding: 4px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .payment-form .button-group {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }
        .payment-form button {
            padding: 4px 8px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        .payment-form button.save {
            background-color: #28a745;
            color: white;
        }
        .payment-form button.cancel {
            background-color: #dc3545;
            color: white;
        }
        .payment-form button:hover {
            opacity: 0.9;
        }
        .payment-status {
            font-size: 0.85em;
            color: #666;
            margin-top: 2px;
            padding-left: 4px;
            border-left: 2px solid #ddd;
        }
        .payment-status.paid {
            color: #28a745;
            border-left-color: #28a745;
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
        /* Nowe style dla autocomplete */
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
        .action-cell {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
        }
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border: none;
            border-radius: 50%;
            background: #f3f6fa;
            color: #007bff;
            font-size: 1.2em;
            cursor: pointer;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            text-decoration: none;
            outline: none;
        }
        .action-btn.edit:hover {
            background: #e3f2fd;
            color: #0056b3;
        }
        .action-btn.delete {
            color: #dc3545;
        }
        .action-btn.delete:hover {
            background: #fdecea;
            color: #b71c1c;
        }
    </style>
</head>
<body>

<h1>Podsumowanie Spraw</h1>
<a href="/import" class="strona">Importuj dane z faktur</a>
<a href="/agents" class="strona">Dodaj nowego agenta</a>
<a href="/sprawy/nowa" class="strona">Dodaj Nową Sprawę</a>

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
                                $daneRaty = $sprawa['oplaty'][$opisRaty] ?? ['kwota' => null, 'is_paid_display' => false, 'paying_invoice_number' => null]; // Dodano domyślny paying_invoice_number
                                $kwotaRaty = $daneRaty['kwota'];
                                $invoiceNumber = $daneRaty['paying_invoice_number'] ?? null; // <<< TUTAJ JEST DEFINIOWANA
                                $czyOplaconaPrzezAlgorytm = $daneRaty['is_paid_display']; // Wynik algorytmu
                                $czyKwotaJestZerowa = ($kwotaRaty !== null && abs($kwotaRaty) < $epsilon); // Czy kwota = 0?
                                $czyFakturaOplaconaWBazie = false;
                                if (isset($daneRaty['faktura_id']) && $daneRaty['faktura_id']) {
                                    $stmt = $pdo->prepare("SELECT status FROM Faktury WHERE id = ?");
                                    $stmt->execute([$daneRaty['faktura_id']]);
                                    $fakturaStatus = $stmt->fetchColumn();
                                    $czyFakturaOplaconaWBazie = ($fakturaStatus === 'Opłacona');
                                }
                                $finalnieWyswietlJakoOplacona = $czyOplaconaPrzezAlgorytm || $czyKwotaJestZerowa || $czyFakturaOplaconaWBazie; // Opłacona jeśli 0 LUB przez algorytm
                                $statusClass = $finalnieWyswietlJakoOplacona ? 'status-oplacona' : 'status-nieoplacona'; // Użyj właściwego koloru
                                $statusSymbol = $finalnieWyswietlJakoOplacona ? '☑' : '☐';
                                $wyswietlanaKwota = format_currency($kwotaRaty);
                                // === DODANO: Przygotowanie tekstu numeru faktury ===
                                $invoiceTextHtml = ''; // Domyślnie pusty
                                // Wyświetl numer faktury zawsze, jeśli istnieje
                                if ($invoiceNumber !== null) {
                                    $invoiceTextHtml = '<br><small class="invoice-details"> Faktura: ' . htmlspecialchars($invoiceNumber) . '</small>';
                                }
                                // =================================================
                            ?>
                            <td class="currency <?php echo $statusClass; ?>">
                                <?php echo $wyswietlanaKwota; ?>
                                <span class="checkbox"><?php echo $statusSymbol; ?></span>
                                <?php echo $invoiceTextHtml; // <<< DODANO: Wyświetlenie numeru faktury >>> ?>
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
                                                $kwotaProwizji = $kwotaRaty * $proc;
                                                if ($kwotaProwizji > $epsilon):
                                                    $paidClass = $czyRataOplacona ? 'paid-installment' : '';
                                                    $uniqueId = "payment_{$sprawa['id_sprawy']}_{$opisRaty}_{$agent_id}";
                                ?>
                                                    <span class="agent-payout <?php echo $paidClass; ?>">
                                                        <div class="amount-row">
                                                            <span class="agent-name"><?php echo htmlspecialchars($agenci[$agent_id] ?? "Agent {$agent_id}"); ?>:</span>
                                                            <span class="agent-amount"><?php echo format_currency($kwotaProwizji); ?></span>
                                                        </div>
                                                        <?php if ($czyRataOplacona): ?>
                                                        <div class="payment-status" id="status_<?php echo $uniqueId; ?>"></div>
                                                        <button class="payment-form-toggle" onclick="togglePaymentForm('<?php echo $uniqueId; ?>')">
                                                            Uzupełnij
                                                        </button>
                                                        <div class="payment-form" id="form_<?php echo $uniqueId; ?>">
                                                            <form onsubmit="event.preventDefault(); savePaymentInfo('<?php echo $uniqueId; ?>');">
                                                                <div class="form-group">
                                                                    <label>
                                                                        <input type="checkbox" 
                                                                               id="paid_<?php echo $uniqueId; ?>" 
                                                                               onchange="updatePaymentInfo('<?php echo $uniqueId; ?>')">
                                                                        Zapłacono
                                                                    </label>
                                                                </div>
                                                                <div class="form-group">
                                                                    <label for="invoice_<?php echo $uniqueId; ?>">Numer faktury:</label>
                                                                    <input type="text" 
                                                                           id="invoice_<?php echo $uniqueId; ?>" 
                                                                           placeholder="Wprowadź numer faktury">
                                                                </div>
                                                                <div class="button-group">
                                                                    <button type="submit" class="save">Zapisz</button>
                                                                    <button type="button" class="cancel" onclick="togglePaymentForm('<?php echo $uniqueId; ?>')">Anuluj</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                        <?php endif; ?>
                                                    </span>
                                <?php 
                                                endif;
                                            endif;
                                        endforeach;
                                        
                                        // Teraz wyświetl prowizję dla Kuby używając obliczonego procentu
                                        $kubaKwotaProwizji = $kwotaRaty * $sprawa['do_wyplaty_kuba_proc'];
                                        if ($kubaKwotaProwizji > $epsilon):
                                            $kubaPaidClass = $czyRataOplacona ? 'paid-installment' : '';
                                            $kubaUniqueId = "payment_{$sprawa['id_sprawy']}_{$opisRaty}_kuba";
                                ?>
                                            <span class="agent-payout kuba-payout <?php echo $kubaPaidClass; ?>">
                                                <div class="amount-row">
                                                    <span class="agent-name"><?php echo htmlspecialchars($agenci[$kuba_id] ?? "Kuba"); ?>:</span>
                                                    <span class="agent-amount"><?php echo format_currency($kubaKwotaProwizji); ?></span>
                                                </div>
                                                <?php if ($czyRataOplacona): ?>
                                                <div class="payment-status" id="status_<?php echo $kubaUniqueId; ?>"></div>
                                                <button class="payment-form-toggle" onclick="togglePaymentForm('<?php echo $kubaUniqueId; ?>')">
                                                    Uzupełnij
                                                </button>
                                                <div class="payment-form" id="form_<?php echo $kubaUniqueId; ?>">
                                                    <form onsubmit="event.preventDefault(); savePaymentInfo('<?php echo $kubaUniqueId; ?>');">
                                                        <div class="form-group">
                                                            <label>
                                                                <input type="checkbox" 
                                                                       id="paid_<?php echo $kubaUniqueId; ?>" 
                                                                       onchange="updatePaymentInfo('<?php echo $kubaUniqueId; ?>')">
                                                                Zapłacono
                                                            </label>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="invoice_<?php echo $kubaUniqueId; ?>">Numer faktury:</label>
                                                            <input type="text" 
                                                                   id="invoice_<?php echo $kubaUniqueId; ?>" 
                                                                   placeholder="Wprowadź numer faktury">
                                                        </div>
                                                        <div class="button-group">
                                                            <button type="submit" class="save">Zapisz</button>
                                                            <button type="button" class="cancel" onclick="togglePaymentForm('<?php echo $kubaUniqueId; ?>')">Anuluj</button>
                                                        </div>
                                                    </form>
                                                </div>
                                                <?php endif; ?>
                                            </span>
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

<script>
// Funkcja do przełączania widoczności formularza
function togglePaymentForm(uniqueId) {
    const form = document.getElementById('form_' + uniqueId);
    form.classList.toggle('active');
}

// Funkcja do aktualizacji statusu płatności
function updatePaymentInfo(uniqueId) {
    const isPaid = document.getElementById('paid_' + uniqueId).checked;
    const invoiceNumber = document.getElementById('invoice_' + uniqueId).value;
    const statusDiv = document.getElementById('status_' + uniqueId);
    
    if (isPaid) {
        statusDiv.innerHTML = `Zapłacono${invoiceNumber ? ' (Faktura: ' + invoiceNumber + ')' : ''}`;
        statusDiv.className = 'payment-status payment-confirmed';
    } else {
        statusDiv.innerHTML = '';
        statusDiv.className = 'payment-status';
    }
}

// Funkcja do zapisywania informacji o płatności
function savePaymentInfo(uniqueId) {
    console.log('Saving payment info for:', uniqueId);
    
    const isPaid = document.getElementById('paid_' + uniqueId).checked;
    const invoiceNumber = document.getElementById('invoice_' + uniqueId).value;
    const statusDiv = document.getElementById('status_' + uniqueId);
    
    // Pobierz dane z ID
    const [_, id_sprawy, opis_raty, id_agenta] = uniqueId.split('_');
    
    // Znajdź element z kwotą w najbliższym rodzicu
    const agentPayoutElement = document.getElementById('paid_' + uniqueId).closest('.agent-payout');
    const amountElement = agentPayoutElement.querySelector('.agent-amount');
    // Poprawiona konwersja kwoty - usuwamy spacje, zamieniamy przecinek na kropkę i usuwamy 'zł'
    const kwota = parseFloat(amountElement.textContent.replace(/\s/g, '').replace(',', '.').replace('zł', '').trim());
    
    // Przygotuj dane do wysłania
    const paymentData = {
        id_sprawy: id_sprawy,
        id_agenta: id_agenta === 'kuba' ? '1' : id_agenta,
        opis_raty: opis_raty,
        kwota: kwota,
        czy_oplacone: isPaid,
        numer_faktury: invoiceNumber,
        data_platnosci: isPaid ? new Date().toISOString().split('T')[0] : null
    };

    console.log('Sending data:', paymentData);

    // Wyślij dane do serwera
    fetch('/update-payment', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(paymentData)
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            // Aktualizuj status płatności
            if (isPaid) {
                statusDiv.innerHTML = `Zapłacono${invoiceNumber ? ' (Faktura: ' + invoiceNumber + ')' : ''}`;
                statusDiv.className = 'payment-status payment-confirmed';
                // Dodaj klasę do całego wiersza
                agentPayoutElement.classList.add('payment-confirmed');
            } else {
                statusDiv.innerHTML = '';
                statusDiv.className = 'payment-status';
                // Usuń klasę z całego wiersza
                agentPayoutElement.classList.remove('payment-confirmed');
            }
            // Zamknij formularz po pomyślnym zapisie
            togglePaymentForm(uniqueId);
        } else {
            console.error('Błąd podczas zapisywania płatności:', data.error);
            alert('Wystąpił błąd podczas zapisywania płatności. Spróbuj ponownie.');
        }
    })
    .catch(error => {
        console.error('Błąd:', error);
        alert('Wystąpił błąd podczas zapisywania płatności. Spróbuj ponownie.');
    });
}

// Funkcja do wczytywania zapisanych informacji przy ładowaniu strony
document.addEventListener('DOMContentLoaded', function() {
    const paymentForms = document.querySelectorAll('.payment-form');
    
    paymentForms.forEach(form => {
        const uniqueId = form.id.replace('form_', '');
        const [_, id_sprawy, opis_raty, id_agenta] = uniqueId.split('_');
        
        // Pobierz status płatności z serwera
        fetch(`/get-payment-status?id_sprawy=${id_sprawy}&opis_raty=${opis_raty}&id_agenta=${id_agenta === 'kuba' ? '1' : id_agenta}`)
            .then(response => response.json())
            .then(data => {
                if (data) {
                    // Ustaw checkbox i pole faktury
                    const paidCheckbox = document.getElementById('paid_' + uniqueId);
                    const invoiceInput = document.getElementById('invoice_' + uniqueId);
                    const statusDiv = document.getElementById('status_' + uniqueId);
                    const agentPayoutElement = paidCheckbox.closest('.agent-payout');
                    
                    paidCheckbox.checked = data.czy_oplacone;
                    invoiceInput.value = data.numer_faktury || '';
                    
                    // Aktualizuj status
                    if (data.czy_oplacone) {
                        statusDiv.innerHTML = `Zapłacono${data.numer_faktury ? ' (Faktura: ' + data.numer_faktury + ')' : ''}`;
                        statusDiv.className = 'payment-status payment-confirmed';
                        // Dodaj klasę do całego wiersza
                        agentPayoutElement.classList.add('payment-confirmed');
                    } else {
                        statusDiv.innerHTML = '';
                        statusDiv.className = 'payment-status';
                        // Usuń klasę z całego wiersza
                        agentPayoutElement.classList.remove('payment-confirmed');
                    }
                }
            })
            .catch(error => {
                console.error('Błąd podczas pobierania statusu płatności:', error);
            });
    });
});

document.addEventListener('DOMContentLoaded', function() {
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
<?php
$pdo = null; // Zamknij połączenie
?>