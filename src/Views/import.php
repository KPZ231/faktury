<?php
// Sprawdź, czy istnieje sesja
if (!isset($_SESSION)) {
    session_start();
}

// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Faktur - System Faktur</title>
    <link rel="shortcut icon" href="../../assets/images/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                <a href="/invoices" class="cleannav__link active" data-tooltip="Faktury">
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
                <a href="/podsumowanie-spraw" class="cleannav__link" data-tooltip="Podsumowanie Faktur">
                    <i class="fa-solid fa-file-invoice-dollar cleannav__icon"></i>
                </a>
            </li>
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin'): ?>
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
        <h1>Import Faktur</h1>
    </header>

    <div id="uploadFile">
        <form id="uploadForm" method="POST" enctype="multipart/form-data" action="/handle-import">
            <h2>Import pliku CSV z fakturami</h2>
            <p class="import-description">
                Wybierz plik CSV zawierający dane faktur do zaimportowania. 
                Upewnij się, że plik zawiera odpowiednie kolumny zgodne ze strukturą bazy danych.
            </p>
            
            <label for="file">Wybierz plik CSV:</label>
            <input type="file" id="file" name="file" accept=".csv" required>
            
            <div class="file-tips">
                <p><i class="fa-solid fa-lightbulb"></i> <strong>Format pliku:</strong> Wymagany format to CSV z nagłówkami.</p>
                <p><i class="fa-solid fa-info-circle"></i> <strong>Struktura danych:</strong> Plik powinien zawierać kolumny zgodne z nazwami w bazie danych:</p>
                <ul class="column-list">
                    <li>LP - liczba porządkowa</li>
                    <li>numer - numer faktury</li>
                    <li>Typ - typ dokumentu</li>
                    <li>Sprzedający - nazwa sprzedawcy</li>
                    <li>Nazwa skrócona działu - nazwa działu</li>
                    <li>NIP sprzedającego - NIP sprzedającego</li>
                    <li>Status - status faktury</li>
                    <li>Data wystawienia - data w formacie YYYY-MM-DD</li>
                    <li>Data sprzedaży - data w formacie YYYY-MM-DD</li>
                    <li>Termin płatności - data w formacie YYYY-MM-DD</li>
                    <li>Nabywca - nazwa nabywcy</li>
                    <li>NIP - NIP nabywcy</li>
                    <li>Wartość netto - kwota netto</li>
                    <li>VAT - kwota podatku VAT</li>
                    <li>Wartość brutto - kwota brutto</li>
                    <li>Płatność - sposób płatności</li>
                    <li>Data płatności - data płatności</li>
                    <li>Kwota opłacona - kwota już opłacona</li>
                    <li>Waluta - waluta faktury</li>
                </ul>
                <p><i class="fa-solid fa-shield-alt"></i> <strong>Bezpieczeństwo:</strong> Dane są walidowane przed importem <span class="security-badge">CHRONIONE</span></p>
            </div>
            
            <button type="submit">
                <i class="fa-solid fa-upload"></i> 
                Importuj do bazy
            </button>
        </form>
    </div>

    <div id="importResult" class="import-result"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('uploadForm');
            const resultDiv = document.getElementById('importResult');
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(form);
                const submitButton = form.querySelector('button[type="submit"]');
                
                // Zmień tekst przycisku na czas importu
                submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Importowanie...';
                submitButton.disabled = true;
                
                fetch('/handle-import', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = `
                            <div class="import-success">
                                <i class="fa-solid fa-check-circle"></i>
                                <p>${data.message}</p>
                            </div>
                        `;
                    } else {
                        resultDiv.innerHTML = `
                            <div class="import-error">
                                <i class="fa-solid fa-exclamation-triangle"></i>
                                <p>${data.error}</p>
                            </div>
                        `;
                    }
                    
                    // Przywróć oryginalny tekst przycisku
                    submitButton.innerHTML = '<i class="fa-solid fa-upload"></i> Importuj do bazy';
                    submitButton.disabled = false;
                })
                .catch(error => {
                    resultDiv.innerHTML = `
                        <div class="import-error">
                            <i class="fa-solid fa-exclamation-triangle"></i>
                            <p>Wystąpił błąd podczas importu: ${error.message}</p>
                        </div>
                    `;
                    
                    // Przywróć oryginalny tekst przycisku
                    submitButton.innerHTML = '<i class="fa-solid fa-upload"></i> Importuj do bazy';
                    submitButton.disabled = false;
                });
            });
        });
    </script>
</body>
</html> 