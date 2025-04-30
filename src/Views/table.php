<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <title>Tabela</title>
</head>

<body>
    <header>
        <h1>Podejrzyj Tabele</h1>
    </header>



    <section id="dataTable">
        <h2>Lista spraw i prowizji</h2>
        <?php
        // Wykorzystujemy metodę renderTable z kontrolera
        if (isset($this) && $this instanceof \Dell\Faktury\Controllers\TableController) {
            $this->renderTable();
        } else {
            try {
                // Zapytanie SQL z aliasami dla ładniejszych nagłówków
                $query = "SELECT 
                    case_name AS 'Sprawa',
                    CASE WHEN is_completed = 1 THEN 'Tak' WHEN is_completed = 0 THEN 'Nie' ELSE '' END AS 'Zakończona?',
                    amount_won AS 'Wywalczona kwota',
                    upfront_fee AS 'Opłata wstępna',
                    success_fee_percentage AS 'Success fee %',
                    total_commission AS 'Całość prowizji',
                    kuba_percentage AS 'Prowizja % Kuba',
                    kuba_payout AS 'Do wypłaty Kuba',
                    agent1_percentage AS 'Prowizja % Agent 1',
                    agent2_percentage AS 'Prowizja % Agent 2',
                    agent3_percentage AS 'Prowizja % Agent 3',
                    agent4_percentage AS 'Prowizja % Agent 4',
                    agent5_percentage AS 'Prowizja % Agent 5',
                    installment1_amount AS 'Rata 1',
                    CASE WHEN installment1_paid = 1 THEN 'Tak' WHEN installment1_paid = 0 THEN 'Nie' END AS 'Opłacona?',
                    installment2_amount AS 'Rata 2',
                    CASE WHEN installment2_paid = 1 THEN 'Tak' WHEN installment2_paid = 0 THEN 'Nie' END AS 'Opłacona?',
                    installment3_amount AS 'Rata 3',
                    CASE WHEN installment3_paid = 1 THEN 'Tak' WHEN installment3_paid = 0 THEN 'Nie' END AS 'Opłacona?',
                    final_installment_amount AS 'Rata ostatnia',
                    CASE WHEN final_installment_paid = 1 THEN 'Tak' WHEN final_installment_paid = 0 THEN 'Nie' END AS 'Opłacona?',
                    kuba_installment1_amount AS 'Rata 1 - Kuba',
                    kuba_invoice_number AS 'Nr faktury',
                    kuba_installment2_amount AS 'Rata 2 - Kuba',
                    kuba_installment3_amount AS 'Rata 3 - Kuba',
                    kuba_final_installment_amount AS 'Rata ostatnia - Kuba',
                    agent1_installment1_amount AS 'Rata 1 - Agent 1',
                    agent1_installment2_amount AS 'Rata 2 - Agent 1',
                    agent1_installment3_amount AS 'Rata 3 - Agent 1',
                    agent1_final_installment_amount AS 'Rata ostatnia - Agent 1',
                    agent2_installment1_amount AS 'Rata 1 - Agent 2',
                    agent2_installment2_amount AS 'Rata 2 - Agent 2',
                    agent2_installment3_amount AS 'Rata 3 - Agent 2',
                    agent2_final_installment_amount AS 'Rata ostatnia - Agent 2',
                    agent3_installment1_amount AS 'Rata 1 - Agent 3',
                    agent3_installment2_amount AS 'Rata 2 - Agent 3',
                    agent3_installment3_amount AS 'Rata 3 - Agent 3',
                    agent3_final_installment_amount AS 'Rata ostatnia - Agent 3'
                FROM test2 ORDER BY id DESC";
                
                $stmt = $pdo->query($query);
                
                if ($stmt->rowCount() > 0) {
                    // Najpierw pobieramy wszystkie dane, aby sprawdzić, które kolumny zawierają dane
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($rows)) {
                        echo '<p style="text-align:center;">Brak danych.</p>';
                        return;
                    }
                    
                    // Sprawdzamy, które kolumny zawierają jakiekolwiek niepuste dane
                    $nonEmptyColumns = [];
                    foreach ($rows as $row) {
                        foreach ($row as $column => $value) {
                            if ($value !== null && $value !== '') {
                                $nonEmptyColumns[$column] = true;
                            }
                        }
                    }
                    
                    // Wyświetlamy tylko te kolumny, które mają jakiekolwiek dane
                    echo '<table class="data-table"><thead><tr>';
                    foreach (array_keys($nonEmptyColumns) as $col) {
                        echo '<th class="sortable" data-column="' . htmlspecialchars($col, ENT_QUOTES) . '">' .
                            htmlspecialchars($col, ENT_QUOTES) . '</th>';
                    }
                    echo '</tr></thead><tbody>';
                    
                    // Wyświetlamy dane tylko dla niepustych kolumn
                    foreach ($rows as $row) {
                        echo '<tr>';
                        foreach ($row as $column => $value) {
                            if (isset($nonEmptyColumns[$column])) {
                                echo '<td>' . htmlspecialchars($value !== null ? $value : '', ENT_QUOTES) . '</td>';
                            }
                        }
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                } else {
                    echo '<p style="text-align:center;">Brak danych.</p>';
                }
            } catch (PDOException $e) {
                echo '<p style="color:red; text-align:center;">Błąd: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>';
            }
        }
        ?>
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
    });
    </script>

</body>

</html>