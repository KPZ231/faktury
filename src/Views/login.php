<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Logowanie - Faktury</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="shortcut icon" href="/assets/images/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="/assets/js/responsive.js" defer></script>
    <style>
        .body_login {
            padding-left: 0 !important; /* Remove sidebar padding */
        }
    </style>
</head>
<body class="body_login">
    <div class="login-container">
        <div class="login-header">
            <h1>Logowanie do systemu</h1>
            <p><?php echo isset($required) && $required ? 'Aby kontynuować, musisz się zalogować' : 'Wprowadź dane logowania'; ?></p>
        </div>
        
        <?php if (isset($error)): ?>
        <div class="error-message">
            <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <form class="login-form" action="/login" method="post">
            <div class="form-group input-with-icon">
                <label for="username">Nazwa użytkownika</label>
                <i class="fa-solid fa-user"></i>
                <input type="text" id="username" name="username" placeholder="Wprowadź nazwę użytkownika" required autofocus>
            </div>
            
            <div class="form-group input-with-icon">
                <label for="password">Hasło</label>
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="password" name="password" placeholder="Wprowadź hasło" required>
            </div>
            
            <button type="submit" class="login-button">
                <i class="fa-solid fa-right-to-bracket"></i> Zaloguj się
            </button>
        </form>
        
        <div class="login-footer">
            <p>System zarządzania fakturami</p>
        </div>
    </div>
</body>
</html> 