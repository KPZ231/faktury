<?php if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin'): ?>
    <script>window.location.href = '/?access_denied=1';</script>
<?php endif; ?>

<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zarządzanie bazą danych</title>
    <link rel="shortcut icon" href="/assets/images/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ... existing code ... */
    </style>
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
                <a href="/podsumowanie-spraw" class="cleannav__link" data-tooltip="Podsumowanie Faktur">
                    <i class="fa-solid fa-file-invoice-dollar cleannav__icon"></i>
                </a>
            </li>
            <li class="cleannav__item">
                <a href="/database" class="cleannav__manage-btn active" data-tooltip="Zarządzaj bazą">
                    <i class="fa-solid fa-database cleannav__icon"></i>
                </a>
            </li>
            <li class="cleannav__item">
                <a href="/logout" class="cleannav__link" data-tooltip="Wyloguj">
                    <i class="fa-solid fa-sign-out-alt cleannav__icon"></i>
                </a>
            </li>
        </ul>
    </nav>

    <header>
        <h1>Zarządzanie bazą danych</h1>
        <div class="superadmin-badge">
            <i class="fa-solid fa-shield-alt"></i>
            <span>Panel administratora bazy danych</span>
        </div>
    </header>

    <div class="database-panel">
        <div class="panel-header">
            <h2><i class="fa-solid fa-database"></i> Przegląd bazy danych</h2>
            <p>Na tej stronie możesz zarządzać tabelami w bazie danych. Pamiętaj, że operacje usuwania są nieodwracalne!</p>
        </div>
        
        <!-- Panel logowania do bazy danych -->
        <div class="db-login-panel">
            <h3><i class="fa-solid fa-key"></i> Logowanie do bazy danych</h3>
            <p class="db-login-info">Niektóre operacje wymagają podwyższonych uprawnień. Możesz zalogować się do bazy danych z odpowiednimi uprawnieniami.</p>
            
            <form id="dbLoginForm" class="db-login-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="dbHost">Host:</label>
                        <input type="text" id="dbHost" name="host" value="localhost" required>
                    </div>
                    <div class="form-group">
                        <label for="dbName">Nazwa bazy:</label>
                        <input type="text" id="dbName" name="dbname" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="dbUser">Użytkownik:</label>
                        <input type="text" id="dbUser" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="dbPass">Hasło:</label>
                        <input type="password" id="dbPass" name="password">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-db-login">
                        <i class="fa-solid fa-sign-in-alt"></i> Zaloguj do bazy
                    </button>
                    <div id="dbConnectionStatus" class="connection-status">
                        <i class="fa-solid fa-circle-info"></i> Nie połączono z bazą danych
                    </div>
                </div>
            </form>
        </div>

        <?php if (empty($databaseInfo['tables'])): ?>
            <div class="no-data">
                <i class="fa-solid fa-ghost fa-3x"></i>
                <p>Brak tabel w bazie danych.</p>
            </div>
        <?php else: ?>
            <?php foreach ($databaseInfo['tables'] as $table): ?>
                <div class="database-table" data-table-name="<?= htmlspecialchars($table['name']) ?>">
                    <h3>
                        <i class="fa-solid fa-table"></i>
                        <?= htmlspecialchars($table['name']) ?>
                    </h3>
                    
                    <div class="database-table-info">
                        <div><i class="fa-solid fa-list"></i> Liczba rekordów: <?= $table['records'] ?></div>
                        <div><i class="fa-solid fa-columns"></i> Liczba kolumn: <?= count($table['columns']) ?></div>
                    </div>
                    
                    <div class="database-table-actions">
                        <button class="btn-backup" data-action="backup">
                            <i class="fa-solid fa-copy"></i> Utwórz kopię
                        </button>
                        <button class="btn-truncate" data-action="truncate">
                            <i class="fa-solid fa-eraser"></i> Wyczyść dane
                        </button>
                        <button class="btn-drop" data-action="drop">
                            <i class="fa-solid fa-trash"></i> Usuń tabelę
                        </button>
                    </div>
                    
                    <div class="column-list">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nazwa kolumny</th>
                                    <th>Typ</th>
                                    <th>Klucz</th>
                                    <th>Domyślna wartość</th>
                                    <th>Dodatkowe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($table['columns'] as $column): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($column['Field']) ?></td>
                                        <td><?= htmlspecialchars($column['Type']) ?></td>
                                        <td><?= htmlspecialchars($column['Key'] ?: '-') ?></td>
                                        <td><?= $column['Default'] !== null ? htmlspecialchars($column['Default']) : '<em>NULL</em>' ?></td>
                                        <td><?= htmlspecialchars($column['Extra'] ?: '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Modal dla potwierdzenia akcji -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Potwierdź operację</h3>
            <p id="modalMessage">Czy na pewno chcesz wykonać tę operację?</p>
            <div class="modal-actions">
                <button id="cancelAction" class="close-modal">Anuluj</button>
                <button id="confirmAction" class="confirm-action">Potwierdź</button>
            </div>
        </div>
    </div>

    <div id="notificationContainer"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const notificationContainer = document.getElementById('notificationContainer');
            const modal = document.getElementById('confirmModal');
            const modalMessage = document.getElementById('modalMessage');
            const confirmBtn = document.getElementById('confirmAction');
            const cancelBtn = document.getElementById('cancelAction');
            
            // Database login form handling
            const dbLoginForm = document.getElementById('dbLoginForm');
            const dbConnectionStatus = document.getElementById('dbConnectionStatus');
            
            dbLoginForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                dbConnectionStatus.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Łączenie...';
                dbConnectionStatus.className = 'connection-status';
                
                try {
                    const response = await fetch('/database/connect', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        dbConnectionStatus.innerHTML = '<i class="fa-solid fa-check-circle"></i> Połączono z bazą danych';
                        dbConnectionStatus.className = 'connection-status connected';
                        showNotification('Połączenie z bazą danych nawiązane pomyślnie', 'success');
                        
                        // Wyświetl informacje o uprawnieniach
                        let privilegesInfo = '';
                        if (result.privileges) {
                            const truncateAllowed = result.privileges.truncate;
                            if (truncateAllowed) {
                                privilegesInfo = ' (z uprawnieniami do czyszczenia tabel)';
                            } else {
                                privilegesInfo = ' (bez uprawnień do czyszczenia tabel)';
                            }
                        }
                        
                        dbConnectionStatus.innerHTML += privilegesInfo;
                        
                        // Odśwież stronę po 2 sekundach aby załadować dane z nową bazą
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        dbConnectionStatus.innerHTML = `<i class="fa-solid fa-exclamation-circle"></i> Błąd połączenia`;
                        dbConnectionStatus.className = 'connection-status error';
                        showNotification(result.message, 'error');
                    }
                } catch (error) {
                    dbConnectionStatus.innerHTML = '<i class="fa-solid fa-exclamation-circle"></i> Błąd połączenia';
                    dbConnectionStatus.className = 'connection-status error';
                    showNotification('Wystąpił błąd podczas łączenia z bazą danych: ' + error.message, 'error');
                }
            });
            
            let currentAction = null;
            let currentTable = null;
            
            // Funkcja do wyświetlania powiadomień
            function showNotification(message, type = 'success') {
                const notification = document.createElement('div');
                notification.className = `notification ${type}`;
                notification.innerHTML = `
                    <div class="notification-icon">
                        <i class="fa-solid ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                    </div>
                    <div class="notification-message">${message}</div>
                `;
                
                notificationContainer.appendChild(notification);
                
                // Automatyczne usuwanie powiadomienia po 5 sekundach
                setTimeout(() => {
                    notification.classList.add('fade-out');
                    setTimeout(() => {
                        notification.remove();
                    }, 500);
                }, 5000);
            }
            
            // Obsługa przycisków akcji
            document.querySelectorAll('.database-table-actions button').forEach(button => {
                button.addEventListener('click', function() {
                    const action = this.dataset.action;
                    const tableElement = this.closest('.database-table');
                    const tableName = tableElement.dataset.tableName;
                    
                    currentAction = action;
                    currentTable = tableName;
                    
                    // Przygotuj odpowiedni komunikat w modalu
                    switch(action) {
                        case 'backup':
                            modalMessage.textContent = `Czy na pewno chcesz utworzyć kopię zapasową tabeli "${tableName}"?`;
                            confirmBtn.textContent = 'Utwórz kopię';
                            confirmBtn.className = 'confirm-action btn-backup';
                            break;
                        case 'truncate':
                            modalMessage.textContent = `UWAGA! Czy na pewno chcesz usunąć WSZYSTKIE dane z tabeli "${tableName}"? Tej operacji NIE MOŻNA cofnąć!`;
                            confirmBtn.textContent = 'Usuń dane';
                            confirmBtn.className = 'confirm-action btn-truncate';
                            break;
                        case 'drop':
                            modalMessage.textContent = `UWAGA! Czy na pewno chcesz USUNĄĆ tabelę "${tableName}" z bazy danych? Tej operacji NIE MOŻNA cofnąć!`;
                            confirmBtn.textContent = 'Usuń tabelę';
                            confirmBtn.className = 'confirm-action btn-drop';
                            break;
                    }
                    
                    // Pokaż modal
                    modal.style.display = 'block';
                });
            });
            
            // Zamknij modal po kliknięciu przycisku Anuluj
            cancelBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
            
            // Obsługa przycisku potwierdzenia w modalu
            confirmBtn.addEventListener('click', async function() {
                if (!currentAction || !currentTable) return;
                
                const formData = new FormData();
                formData.append('table', currentTable);
                
                try {
                    let endpoint = '';
                    switch(currentAction) {
                        case 'backup':
                            endpoint = '/database/backup';
                            break;
                        case 'truncate':
                            endpoint = '/database/truncate';
                            break;
                        case 'drop':
                            endpoint = '/database/drop';
                            break;
                    }
                    
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showNotification(result.message, 'success');
                        
                        // Jeśli to było usunięcie tabeli, ukryj element tabeli
                        if (currentAction === 'drop') {
                            document.querySelector(`.database-table[data-table-name="${currentTable}"]`).remove();
                        }
                        
                        // Jeśli to było czyszczenie danych, aktualizuj licznik rekordów
                        if (currentAction === 'truncate') {
                            const tableElement = document.querySelector(`.database-table[data-table-name="${currentTable}"]`);
                            const recordCountElement = tableElement.querySelector('.database-table-info div:first-child');
                            recordCountElement.innerHTML = '<i class="fa-solid fa-list"></i> Liczba rekordów: 0';
                        }
                    } else {
                        showNotification(result.message, 'error');
                    }
                } catch (error) {
                    showNotification('Wystąpił błąd podczas wykonywania operacji: ' + error.message, 'error');
                }
                
                // Zamknij modal
                modal.style.display = 'none';
            });
            
            // Zamknij modal po kliknięciu poza nim
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
    </script>
</body>

</html> 