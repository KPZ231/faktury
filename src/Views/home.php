<?php
require_once __DIR__ . '/../../config/database.php';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <base href="/zestawienie11/">
    <title>Podejrzyj Faktury</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .notification {
            position: fixed;
            top: 20px;
            right: -100%;
            padding: 20px;
            background: #323232;
            color: #fff;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            max-width: 300px;
            font-family: sans-serif;
            transition: right 0.5s ease-out;
            z-index: 1000;
        }
        .notification.show { right: 20px; }
        table { width: 95%; margin: 30px auto; border-collapse: collapse; }
        table, th, td { border: 1px solid #ccc; }
        th, td { padding: 8px; text-align: center; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <header>
        <h1>Podejrzyj Faktury</h1>
    </header>

    <div id="uploadFile">
        <form id="uploadForm" method="POST" enctype="multipart/form-data">
            <label for="file">Wybierz plik:</label><br>
            <input type="file" id="file" name="file" required><br><br>
            <button type="submit">Wyślij plik</button>
        </form>
    </div>

    <section id="dataTable">
        <?php
        try {
            $columns = [
                'LP','numer','Typ','Sprzedający','Nazwa skrócona działu','NIP sprzedającego',
                'Status','Data wystawienia','Data sprzedaży','Termin płatności','Nabywca','NIP',
                'Ulica i nr','Kod pocztowy','Miejscowość','Kraj','E-mail klienta','Telefon klienta',
                'Telefon komórkowy','Wartość netto','VAT','Wartość brutto','Wartość netto PLN','VAT PLN',
                'Wartość brutto PLN','Płatność','Data płatności','Kwota opłacona','Waluta',
                'Nr zamówienia','Adresat','Kategoria','Uwagi','Kody GTU','Oznaczenia dotyczące procedur',
                'Oryginalny dokument','Przyczyna korekty'
            ];
            $sql = 'SELECT `' . implode('`,`', $columns) . '` FROM `test` ORDER BY `LP` DESC';
            $stmt = $pdo->query($sql);

            if ($stmt->rowCount() > 0) {
                echo '<table>';
                // Header
                echo '<thead><tr>';
                foreach ($columns as $col) {
                    echo '<th>' . htmlspecialchars($col, ENT_QUOTES) . '</th>';
                }
                echo '</tr></thead><tbody>';
                // Rows
                while ($row = $stmt->fetch()) {
                    echo '<tr>';
                    foreach ($columns as $col) {
                        echo '<td>' . htmlspecialchars($row[$col], ENT_QUOTES) . '</td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p style="text-align:center;">Brak danych do wyświetlenia.</p>';
            }
        } catch (PDOException $e) {
            echo '<p style="color:red; text-align:center;">Błąd pobierania danych: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>';
        }
        ?>
    </section>

    <div id="notificationContainer"></div>

    <script>
    document.getElementById('uploadForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const form = e.currentTarget;
        const data = new FormData(form);
        try {
            const res = await fetch('upload-file', {
                method: 'POST',
                body: data,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const json = await res.json();
            showNotification(json.message);
        } catch {
            showNotification('Wystąpił błąd podczas wysyłania.');
        }
        form.reset();
    });
    function showNotification(msg) {
        const cont = document.getElementById('notificationContainer');
        const n = document.createElement('div');
        n.className = 'notification';
        n.textContent = msg;
        cont.appendChild(n);
        setTimeout(() => n.classList.add('show'), 50);
        setTimeout(() => {
            n.classList.remove('show');
            setTimeout(() => cont.removeChild(n), 500);
        }, 5050);
    }
    </script>
</body>
</html>
