<?php

namespace Dell\Faktury\Controllers;

class UserManagementController
{
    private $db;

    public function __construct()
    {
        global $pdo;
        $this->db = $pdo;
    }

    /**
     * Display user management page
     */
    public function index()
    {
        // Check if user is superadmin
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
            header('Location: /?access_denied=1');
            exit;
        }

        // Get all users from database
        $users = $this->getAllUsers();
        
        // Display the view
        require BASE_DIR . '/src/Views/zarzadzanie-uzytkownikami.php';
    }

    /**
     * Get all users
     */
    private function getAllUsers()
    {
        $stmt = $this->db->prepare("SELECT id, username, role, created_at FROM users ORDER BY id");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Add a new user
     */
    public function addUser()
    {
        // Check if user is superadmin
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
            header('Location: /?access_denied=1');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'user';

            // Validate inputs
            $errors = [];
            if (empty($username)) {
                $errors[] = 'Nazwa użytkownika jest wymagana';
            }
            if (empty($password)) {
                $errors[] = 'Hasło jest wymagane';
            }
            if (!in_array($role, ['user', 'admin', 'superadmin'])) {
                $errors[] = 'Nieprawidłowa rola użytkownika';
            }

            // Check if username already exists
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Użytkownik o takiej nazwie już istnieje';
            }

            if (empty($errors)) {
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Insert new user
                $stmt = $this->db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                $result = $stmt->execute([$username, $hashedPassword, $role]);

                if ($result) {
                    $_SESSION['flash_message'] = 'Użytkownik został dodany pomyślnie';
                    $_SESSION['flash_type'] = 'success';
                } else {
                    $_SESSION['flash_message'] = 'Wystąpił błąd podczas dodawania użytkownika';
                    $_SESSION['flash_type'] = 'error';
                }
            } else {
                $_SESSION['flash_message'] = implode(', ', $errors);
                $_SESSION['flash_type'] = 'error';
            }

            header('Location: /zarzadzanie-uzytkownikami');
            exit;
        }
    }

    /**
     * Change user password
     */
    public function changePassword()
    {
        // Check if user is superadmin
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
            header('Location: /?access_denied=1');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = $_POST['user_id'] ?? 0;
            $password = $_POST['password'] ?? '';

            // Validate inputs
            $errors = [];
            if (empty($userId) || !is_numeric($userId)) {
                $errors[] = 'Nieprawidłowy identyfikator użytkownika';
            }
            if (empty($password)) {
                $errors[] = 'Hasło jest wymagane';
            }

            if (empty($errors)) {
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Update user password
                $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $result = $stmt->execute([$hashedPassword, $userId]);

                if ($result) {
                    $_SESSION['flash_message'] = 'Hasło zostało zmienione pomyślnie';
                    $_SESSION['flash_type'] = 'success';
                } else {
                    $_SESSION['flash_message'] = 'Wystąpił błąd podczas zmiany hasła';
                    $_SESSION['flash_type'] = 'error';
                }
            } else {
                $_SESSION['flash_message'] = implode(', ', $errors);
                $_SESSION['flash_type'] = 'error';
            }

            header('Location: /zarzadzanie-uzytkownikami');
            exit;
        }
    }

    /**
     * Change user role
     */
    public function changeRole()
    {
        // Check if user is superadmin
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
            header('Location: /?access_denied=1');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = $_POST['user_id'] ?? 0;
            $role = $_POST['role'] ?? '';

            // Validate inputs
            $errors = [];
            if (empty($userId) || !is_numeric($userId)) {
                $errors[] = 'Nieprawidłowy identyfikator użytkownika';
            }
            if (!in_array($role, ['user', 'admin', 'superadmin'])) {
                $errors[] = 'Nieprawidłowa rola użytkownika';
            }

            if (empty($errors)) {
                // Update user role
                $stmt = $this->db->prepare("UPDATE users SET role = ? WHERE id = ?");
                $result = $stmt->execute([$role, $userId]);

                if ($result) {
                    $_SESSION['flash_message'] = 'Uprawnienia zostały zmienione pomyślnie';
                    $_SESSION['flash_type'] = 'success';
                } else {
                    $_SESSION['flash_message'] = 'Wystąpił błąd podczas zmiany uprawnień';
                    $_SESSION['flash_type'] = 'error';
                }
            } else {
                $_SESSION['flash_message'] = implode(', ', $errors);
                $_SESSION['flash_type'] = 'error';
            }

            header('Location: /zarzadzanie-uzytkownikami');
            exit;
        }
    }

    /**
     * Delete user
     */
    public function deleteUser()
    {
        // Check if user is superadmin
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin') {
            header('Location: /?access_denied=1');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userId = $_POST['user_id'] ?? 0;

            // Validate inputs
            $errors = [];
            if (empty($userId) || !is_numeric($userId)) {
                $errors[] = 'Nieprawidłowy identyfikator użytkownika';
            }

            // Check if user is not trying to delete themselves
            if ($_SESSION['user_id'] == $userId) {
                $errors[] = 'Nie możesz usunąć własnego konta';
            }

            if (empty($errors)) {
                // Delete user
                $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
                $result = $stmt->execute([$userId]);

                if ($result) {
                    $_SESSION['flash_message'] = 'Użytkownik został usunięty pomyślnie';
                    $_SESSION['flash_type'] = 'success';
                } else {
                    $_SESSION['flash_message'] = 'Wystąpił błąd podczas usuwania użytkownika';
                    $_SESSION['flash_type'] = 'error';
                }
            } else {
                $_SESSION['flash_message'] = implode(', ', $errors);
                $_SESSION['flash_type'] = 'error';
            }

            header('Location: /zarzadzanie-uzytkownikami');
            exit;
        }
    }
} 