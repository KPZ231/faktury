<?php
namespace Dell\Faktury\Controllers;

class LoginController
{
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

        // Przykładowa autoryzacja (do rozbudowy)
        if ($username === 'admin' && $password === 'admin') {
            $_SESSION['user'] = $username;
            $_SESSION['user_id'] = 1; // Przykładowe ID użytkownika
            $_SESSION['login_time'] = time();
            
            // Zapisz ostatnie logowanie
            error_log("User {$username} logged in successfully");
            
            // Przekieruj na stronę główną
            header('Location: /');
            exit;
        } else {
            $error = 'Nieprawidłowy login lub hasło';
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