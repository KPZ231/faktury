<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Logowanie</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header>
        <h1>System Faktur</h1>
    </header>

    <div id="uploadFile" style="max-width: 400px;">
        <h2 style="color: var(--primary-color); margin-bottom: 1.5rem; text-align: center;">Logowanie do systemu</h2>
        
        <?php if (!empty($error)): ?>
            <div class="agent-error-message" style="margin-bottom: 1.5rem;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['required'])): ?>
            <div class="agent-error-message" style="margin-bottom: 1.5rem; background-color: #fff3cd; color: #856404; border-left-color: #ffeeba;">
                Zaloguj się, aby uzyskać dostęp do systemu.
            </div>
        <?php endif; ?>
        
        <form method="post" action="/login">
            <div>
                <label for="username" class="agent-form-label">Login:</label>
                <input type="text" id="username" name="username" class="agent-form-input" required>
            </div>
            
            <div>
                <label for="password" class="agent-form-label">Hasło:</label>
                <input type="password" id="password" name="password" class="agent-form-input" required>
            </div>
            
            <button type="submit" class="agent-submit-button">Zaloguj się</button>
        </form>
        
        <p style="text-align: center; margin-top: 1.5rem; color: var(--text-secondary);">
            <small>Domyślne dane: login: <strong>admin</strong>, hasło: <strong>admin</strong></small>
        </p>
    </div>
</body>
</html> 