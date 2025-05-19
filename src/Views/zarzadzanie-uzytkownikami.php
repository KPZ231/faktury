<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zarządzanie Użytkownikami - Faktury</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/table.css">
    <link rel="shortcut icon" href="/assets/images/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        /* Dodatkowe style dla strony zarządzania użytkownikami */
        body {
            background-color: #f5f7fa;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #4a86e8, #3a76d8);
            color: white;
            padding: 30px 0;
            text-align: center;
            margin-bottom: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        
        .page-header-icon {
            font-size: 36px;
            margin-bottom: 15px;
            display: block;
        }
        
        .page-header-title {
            font-size: 28px;
            margin: 0;
            font-weight: 600;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }
        
        .page-header-subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin-top: 10px;
            font-weight: 300;
        }
        
        .card-body {
            font-family: 'Poppins', sans-serif;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }
        
        .password-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #777;
            padding: 5px;
            font-size: 16px;
        }
        
        .password-toggle:hover {
            color: #4a86e8;
        }
        
        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            line-height: 1.4;
        }
        
        .password-validation {
            margin-top: 10px;
            padding: 8px 12px;
            background-color: #f8f9fa;
            border-radius: 4px;
            font-size: 13px;
            display: none;
        }
        
        .validation-item {
            margin-bottom: 5px;
            color: #dc3545;
        }
        
        .validation-item.valid {
            color: #28a745;
        }
        
        .validation-item i {
            margin-right: 5px;
            width: 16px;
            text-align: center;
        }
        
        .password-input-wrapper input {
            width: 100%;
            padding: 10px 40px 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            font-family: 'Roboto', sans-serif;
        }
        
        .password-input-wrapper input:focus {
            outline: none;
            border-color: #4a86e8;
            box-shadow: 0 0 0 0.2rem rgba(74, 134, 232, 0.25);
        }
        
        .password-match-message {
            font-size: 12px;
            margin-top: 5px;
        }
        
        .match-success {
            color: #28a745;
        }
        
        .match-error {
            color: #dc3545;
        }
        
        .user-management-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 0 auto 30px auto;
            max-width: 1200px;
        }
        
        .user-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .card-header {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        
        .card-header h2 {
            margin: 0;
            font-size: 1.2rem;
            color: #333;
            display: flex;
            align-items: center;
        }
        
        .card-header h2 i {
            margin-right: 10px;
            color: #4a86e8;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .users-table-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .btn {
            display: inline-block;
            font-weight: 500;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            user-select: none;
            border: 1px solid transparent;
            padding: 0.5rem 1rem;
            font-size: 1rem;
            line-height: 1.5;
            border-radius: 0.25rem;
            transition: color 0.15s, background-color 0.15s, border-color 0.15s, box-shadow 0.15s;
            cursor: pointer;
            text-decoration: none;
        }
        
        .btn-primary {
            color: #fff;
            background-color: #4a86e8;
            border-color: #4a86e8;
        }
        
        .btn-primary:hover {
            background-color: #3a76d8;
            border-color: #3a76d8;
        }
        
        @media (max-width: 768px) {
            .user-management-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header-title {
                font-size: 24px;
            }
            
            .page-header {
                padding: 20px 0;
            }
        }
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
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin'): ?>
            <li class="cleannav__item">
                <a href="/zarzadzanie-uzytkownikami" class="cleannav__manage-btn active" data-tooltip="Zarządzanie Użytkownikami">
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

    <div class="container">
        <!-- Nowy header -->
        <div class="page-header">
            <i class="fas fa-users-cog page-header-icon"></i>
            <h1 class="page-header-title">Zarządzanie Użytkownikami</h1>
            <p class="page-header-subtitle">Dodawaj, edytuj i usuwaj konta użytkowników w systemie</p>
        </div>

        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?>">
                <?= htmlspecialchars($_SESSION['flash_message']) ?>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <div class="user-management-grid">
            <!-- Section: Add User -->
            <div class="user-card">
                <div class="card-header">
                    <h2><i class="fas fa-user-plus"></i> Dodaj Użytkownika</h2>
                </div>
                <div class="card-body">
                    <form action="/zarzadzanie-uzytkownikami/add" method="post" id="addUserForm">
                        <div class="form-group">
                            <label for="username">Nazwa użytkownika</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Hasło</label>
                            <div class="password-input-wrapper password-container">
                                <input type="password" id="password" name="password" required>
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-requirements">
                                Hasło musi zawierać minimum 8 znaków i co najmniej jeden znak specjalny.
                            </div>
                            <div class="password-validation" id="passwordValidation">
                                <div class="validation-item" id="length">
                                    <i class="fas fa-times-circle"></i> Minimum 8 znaków
                                </div>
                                <div class="validation-item" id="special">
                                    <i class="fas fa-times-circle"></i> Co najmniej jeden znak specjalny
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Potwierdź hasło</label>
                            <div class="password-input-wrapper password-container">
                                <input type="password" id="confirm_password" name="confirm_password" required>
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-match-message" id="passwordMatch"></div>
                        </div>
                        <div class="form-group">
                            <label for="role">Uprawnienia</label>
                            <select id="role" name="role" required>
                                <option value="user">Użytkownik</option>
                                <option value="admin">Administrator</option>
                                <option value="superadmin">Superadmin</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" id="addUserBtn">
                            <i class="fas fa-plus-circle"></i> Dodaj Użytkownika
                        </button>
                    </form>
                </div>
            </div>

            <!-- Section: Change Password -->
            <div class="user-card">
                <div class="card-header">
                    <h2><i class="fas fa-key"></i> Zmień Hasło</h2>
                </div>
                <div class="card-body">
                    <form action="/zarzadzanie-uzytkownikami/change-password" method="post" id="changePasswordForm">
                        <div class="form-group">
                            <label for="user_id_password">Użytkownik</label>
                            <select id="user_id_password" name="user_id" required>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="new_password">Nowe hasło</label>
                            <div class="password-input-wrapper password-container">
                                <input type="password" id="new_password" name="password" required>
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('new_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-requirements">
                                Hasło musi zawierać minimum 8 znaków i co najmniej jeden znak specjalny.
                            </div>
                            <div class="password-validation" id="newPasswordValidation">
                                <div class="validation-item" id="newLength">
                                    <i class="fas fa-times-circle"></i> Minimum 8 znaków
                                </div>
                                <div class="validation-item" id="newSpecial">
                                    <i class="fas fa-times-circle"></i> Co najmniej jeden znak specjalny
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="confirm_new_password">Potwierdź nowe hasło</label>
                            <div class="password-input-wrapper password-container">
                                <input type="password" id="confirm_new_password" name="confirm_password" required>
                                <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirm_new_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-match-message" id="newPasswordMatch"></div>
                        </div>
                        <button type="submit" class="btn btn-warning" id="changePasswordBtn">
                            <i class="fas fa-save"></i> Zmień Hasło
                        </button>
                    </form>
                </div>
            </div>

            <!-- Section: Change Role -->
            <div class="user-card">
                <div class="card-header">
                    <h2><i class="fas fa-user-shield"></i> Zmień Uprawnienia</h2>
                </div>
                <div class="card-body">
                    <form action="/zarzadzanie-uzytkownikami/change-role" method="post">
                        <div class="form-group">
                            <label for="user_id_role">Użytkownik</label>
                            <select id="user_id_role" name="user_id" required>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="new_role">Nowa rola</label>
                            <select id="new_role" name="role" required>
                                <option value="user">Użytkownik</option>
                                <option value="admin">Administrator</option>
                                <option value="superadmin">Superadmin</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-user-tag"></i> Zmień Uprawnienia
                        </button>
                    </form>
                </div>
            </div>

            <!-- Section: Delete User -->
            <div class="user-card">
                <div class="card-header">
                    <h2><i class="fas fa-user-times"></i> Usuń Użytkownika</h2>
                </div>
                <div class="card-body">
                    <form action="/zarzadzanie-uzytkownikami/delete" method="post" onsubmit="return confirm('Czy na pewno chcesz usunąć tego użytkownika?');">
                        <div class="form-group">
                            <label for="user_id_delete">Użytkownik</label>
                            <select id="user_id_delete" name="user_id" required>
                                <?php foreach ($users as $user): ?>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash-alt"></i> Usuń Użytkownika
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- User List -->
        <div class="users-table-container">
            <h2><i class="fas fa-list"></i> Lista Użytkowników</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nazwa Użytkownika</th>
                        <th>Rola</th>
                        <th>Data Utworzenia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td>
                                <span class="user-role role-<?= $user['role'] ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td><?= $user['created_at'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        // Funkcja do przełączania widoczności hasła
        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Walidacja hasła dla formularza dodawania użytkownika
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const passwordValidation = document.getElementById('passwordValidation');
            const passwordMatchMessage = document.getElementById('passwordMatch');
            const addUserForm = document.getElementById('addUserForm');
            const addUserBtn = document.getElementById('addUserBtn');
            
            const newPasswordInput = document.getElementById('new_password');
            const confirmNewPasswordInput = document.getElementById('confirm_new_password');
            const newPasswordValidation = document.getElementById('newPasswordValidation');
            const newPasswordMatchMessage = document.getElementById('newPasswordMatch');
            const changePasswordForm = document.getElementById('changePasswordForm');
            const changePasswordBtn = document.getElementById('changePasswordBtn');
            
            // Funkcja do walidacji hasła
            function validatePassword(password, validationElement, lengthId, specialId) {
                const lengthRegex = /.{8,}/;
                const specialRegex = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]+/;
                
                const lengthItem = document.getElementById(lengthId);
                const specialItem = document.getElementById(specialId);
                
                // Walidacja długości
                if (lengthRegex.test(password)) {
                    lengthItem.classList.add('valid');
                    lengthItem.querySelector('i').classList.remove('fa-times-circle');
                    lengthItem.querySelector('i').classList.add('fa-check-circle');
                } else {
                    lengthItem.classList.remove('valid');
                    lengthItem.querySelector('i').classList.remove('fa-check-circle');
                    lengthItem.querySelector('i').classList.add('fa-times-circle');
                }
                
                // Walidacja znaku specjalnego
                if (specialRegex.test(password)) {
                    specialItem.classList.add('valid');
                    specialItem.querySelector('i').classList.remove('fa-times-circle');
                    specialItem.querySelector('i').classList.add('fa-check-circle');
                } else {
                    specialItem.classList.remove('valid');
                    specialItem.querySelector('i').classList.remove('fa-check-circle');
                    specialItem.querySelector('i').classList.add('fa-times-circle');
                }
                
                // Pokazuj walidację tylko gdy pole jest aktywne lub niepuste
                if (password.length > 0) {
                    validationElement.style.display = 'block';
                } else {
                    validationElement.style.display = 'none';
                }
                
                return lengthRegex.test(password) && specialRegex.test(password);
            }
            
            // Funkcja do sprawdzania zgodności haseł
            function checkPasswordMatch(password, confirmPassword, matchElement) {
                if (confirmPassword.length === 0) {
                    matchElement.textContent = '';
                    matchElement.classList.remove('match-success', 'match-error');
                    return false;
                }
                
                if (password === confirmPassword) {
                    matchElement.textContent = 'Hasła są zgodne';
                    matchElement.classList.add('match-success');
                    matchElement.classList.remove('match-error');
                    return true;
                } else {
                    matchElement.textContent = 'Hasła nie są zgodne';
                    matchElement.classList.add('match-error');
                    matchElement.classList.remove('match-success');
                    return false;
                }
            }
            
            // Obsługa walidacji dla formularza dodawania użytkownika
            if (passwordInput && confirmPasswordInput) {
                passwordInput.addEventListener('input', function() {
                    validatePassword(this.value, passwordValidation, 'length', 'special');
                    if (confirmPasswordInput.value.length > 0) {
                        checkPasswordMatch(this.value, confirmPasswordInput.value, passwordMatchMessage);
                    }
                });
                
                confirmPasswordInput.addEventListener('input', function() {
                    checkPasswordMatch(passwordInput.value, this.value, passwordMatchMessage);
                });
                
                // Walidacja formularza przed wysłaniem
                addUserForm.addEventListener('submit', function(e) {
                    const isPasswordValid = validatePassword(passwordInput.value, passwordValidation, 'length', 'special');
                    const doPasswordsMatch = checkPasswordMatch(passwordInput.value, confirmPasswordInput.value, passwordMatchMessage);
                    
                    if (!isPasswordValid || !doPasswordsMatch) {
                        e.preventDefault();
                        alert('Sprawdź poprawność danych formularza. Hasło musi spełniać wymagania i hasła muszą być zgodne.');
                    }
                });
            }
            
            // Obsługa walidacji dla formularza zmiany hasła
            if (newPasswordInput && confirmNewPasswordInput) {
                newPasswordInput.addEventListener('input', function() {
                    validatePassword(this.value, newPasswordValidation, 'newLength', 'newSpecial');
                    if (confirmNewPasswordInput.value.length > 0) {
                        checkPasswordMatch(this.value, confirmNewPasswordInput.value, newPasswordMatchMessage);
                    }
                });
                
                confirmNewPasswordInput.addEventListener('input', function() {
                    checkPasswordMatch(newPasswordInput.value, this.value, newPasswordMatchMessage);
                });
                
                // Walidacja formularza przed wysłaniem
                changePasswordForm.addEventListener('submit', function(e) {
                    const isPasswordValid = validatePassword(newPasswordInput.value, newPasswordValidation, 'newLength', 'newSpecial');
                    const doPasswordsMatch = checkPasswordMatch(newPasswordInput.value, confirmNewPasswordInput.value, newPasswordMatchMessage);
                    
                    if (!isPasswordValid || !doPasswordsMatch) {
                        e.preventDefault();
                        alert('Sprawdź poprawność danych formularza. Hasło musi spełniać wymagania i hasła muszą być zgodne.');
                    }
                });
            }
        });
    </script>
</body>
</html> 