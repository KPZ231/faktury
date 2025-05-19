<?php
namespace Dell\Faktury\Controllers;

class LoginController
{
    private $db;

    public function __construct()
    {
        global $pdo;
        $this->db = $pdo;
    }

    public function showLoginForm()
    {
        // Sprawdź, czy użytkownik został przekierowany z powodu braku autoryzacji
        $required = isset($_GET['required']) ? true : false;
        
        require __DIR__ . '/../Views/login.php';
    }

    public function login()
    {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Proszę wprowadzić nazwę użytkownika i hasło';
            require __DIR__ . '/../Views/login.php';
            return;
        }

        try {
            // Pobierz użytkownika z bazy danych
            $stmt = $this->db->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Sprawdź czy użytkownik istnieje i hasło się zgadza
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user'] = $user['username'];
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                // Zapisz ostatnie logowanie
                error_log("User {$username} logged in successfully with role {$user['role']}");
                
                // Przekieruj na stronę główną
                header('Location: /');
                exit;
            } else {
                $error = 'Nieprawidłowy login lub hasło';
                require __DIR__ . '/../Views/login.php';
            }
        } catch (\PDOException $e) {
            error_log("Database error during login: " . $e->getMessage());
            $error = 'Wystąpił błąd podczas logowania. Spróbuj ponownie później.';
            require __DIR__ . '/../Views/login.php';
        }
    }

    public function logout()
    {
        // Zapisz informację o wylogowaniu do logów
        if (isset($_SESSION['user'])) {
            error_log("User {$_SESSION['user']} logged out");
        }
        
        // Wyczyść wszystkie dane sesji
        $_SESSION = array();
        
        // Usuń ciasteczko sesji, jeśli istnieje
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Zniszcz sesję
        session_destroy();
        
        // Przekieruj na stronę logowania
        header('Location: /login?required=1');
        exit;
    }
} 