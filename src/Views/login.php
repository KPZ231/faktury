<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logowanie - Faktury</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f5f7fa;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 0;
            margin: 0;
            font-family: "Poppins", sans-serif;
        }
        
        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 8px;
            background: linear-gradient(90deg, var(--primary-dark), var(--primary-color));
        }
        
        .login-header {
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .form-group {
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(100, 181, 246, 0.2);
        }
        
        .form-group.input-with-icon {
            position: relative;
        }
        
        .form-group.input-with-icon input {
            padding-left: 45px;
        }
        
        .form-group.input-with-icon i {
            position: absolute;
            left: 15px;
            top: 43px;
            color: var(--text-secondary);
            font-size: 1.1rem;
        }
        
        .login-button {
            background: var(--primary-color);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
        }
        
        .login-button:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(100, 181, 246, 0.4);
        }
        
        .login-button:active {
            transform: translateY(0);
        }
        
        .error-message {
            background-color: rgba(244, 67, 54, 0.1);
            color: #d32f2f;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            border-left: 4px solid #d32f2f;
            text-align: left;
        }
        
        .login-footer {
            margin-top: 30px;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        
        .login-footer p {
            margin-bottom: 5px;
        }
        
        .users-info {
            margin-top: 15px;
            background-color: rgba(33, 150, 243, 0.1);
            padding: 12px;
            border-radius: 8px;
            text-align: left;
            font-size: 0.85rem;
        }
        
        .users-info h3 {
            margin-top: 0;
            color: var(--primary-color);
            font-size: 0.95rem;
            margin-bottom: 8px;
        }
        
        .user-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px dashed rgba(0, 0, 0, 0.1);
        }
        
        .user-row:last-child {
            border-bottom: none;
        }
        
        .user-role {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .role-admin {
            background-color: #4caf50;
            color: white;
        }
        
        .role-superadmin {
            background-color: #ff9800;
            color: white;
        }
    </style>
</head>
<body>
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
            
            <div class="users-info">
                <h3>Dostępni użytkownicy:</h3>
                <div class="user-row">
                    <span><strong>admin</strong> / admin</span>
                    <span class="user-role role-admin">Admin</span>
                </div>
                <div class="user-row">
                    <span><strong>root</strong> / superadmin</span>
                    <span class="user-role role-superadmin">Superadmin</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 