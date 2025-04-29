<?php
require_once __DIR__ . '/../../config/database.php';
?>
<!DOCTYPE html>
<html lang="pl">
<style>
    /* Styl powiadomienia */
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
    .notification.show {
        right: 20px;
    }
    table {
        width: 80%;
        margin: 30px auto;
        border-collapse: collapse;
    }
    table, th, td {
        border: 1px solid #ccc;
    }
    th, td {
        padding: 10px;
        text-align: center;
    }
    th {
        background: #f2f2f2;
    }
</style>
<head>
    <meta charset="UTF-8">
    <title>Strona główna</title>
    <link rel="stylesheet" href="././assets/css/style.css">
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
            $stmt = $pdo->query(
                'SELECT 
                    `Nabywca`, 
                    `Data wystawienia`, 
                    `Wartość netto PLN`, 
                    `Produkt/usługa`, 
                    `Etykiety` 
                 FROM `test` 
                 ORDER BY `Data wystawienia` DESC'
            );

            if ($stmt->rowCount() > 0) {
                echo '<table>';
                echo '<thead><tr>'
                   . '<th>Nabywca</th>'
                   . '<th>Data wystawienia</th>'
                   . '<th>Wartość netto PLN</th>'
                   . '<th>Produkt/usługa</th>'
                   . '<th>Etykiety</th>'
                   . '</tr></thead><tbody>';
                while ($row = $stmt->fetch()) {
                    echo '<tr>'
                       . '<td>' . htmlspecialchars($row['Nabywca']) . '</td>'
                       . '<td>' . htmlspecialchars($row['Data wystawienia']) . '</td>'
                       . '<td>' . htmlspecialchars($row['Wartość netto PLN']) . '</td>'
                       . '<td>' . htmlspecialchars($row['Produkt/usługa']) . '</td>'
                       . '<td>' . htmlspecialchars($row['Etykiety']) . '</td>'
                       . '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p style="text-align:center;">Brak danych do wyświetlenia.</p>';
            }
        } catch (PDOException $e) {
            echo '<p style="color:red;text-align:center;">Błąd pobierania danych: '
               . htmlspecialchars($e->getMessage()) . '</p>';
        }
        ?>
    </section>

    <div id="notificationContainer"></div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const form = e.currentTarget;
            const formData = new FormData(form);
            try {
                const response = await fetch('upload-file', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                showNotification(data.message);
            } catch {
                showNotification('Wystąpił błąd podczas wysyłania.');
            }
            form.reset();
        });

        function showNotification(message) {
            const container = document.getElementById('notificationContainer');
            const notif = document.createElement('div');
            notif.className = 'notification';
            notif.textContent = message;
            container.appendChild(notif);
            setTimeout(() => notif.classList.add('show'), 50);
            setTimeout(() => {
                notif.classList.remove('show');
                setTimeout(() => container.removeChild(notif), 500);
            }, 5050);
        }
    </script>

</body>
</html>
