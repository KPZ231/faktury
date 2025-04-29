<?php

namespace Dell\Faktury\Controllers;

use Dell\Faktury\Models\User;

class HomeController
{
    public function index(): void
    {
        // Pobranie listy użytkowników
        $users = User::all();

        // Załadowanie widoku
        include __DIR__ . '/../Views/home.php';
    }

    public function upload(): void {
        header('Content-Type: application/json; charset=UTF-8');

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['message' => 'Błąd przesyłania pliku.']);
            return;
        }

        $uploadDir = __DIR__ . '/../../Uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $uploadedFile = $_FILES['file'];
        $filename = basename($uploadedFile['name']);
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
            $msg = 'Plik "' . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') . '" został poprawnie przesłany!';
        } else {
            $msg = 'Wystąpił błąd podczas zapisywania pliku.';
        }

        echo json_encode(['message' => $msg]);
    }
}
