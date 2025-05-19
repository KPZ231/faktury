<?php
// Set maximum error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Basic HTML structure that doesn't depend on any backend components
?>
<!DOCTYPE html>
<html>
<head>
    <title>Clarum - Strona Główna</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background: #f4f4f4;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        h1 {
            color: #333;
        }
        .btn {
            display: inline-block;
            background: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            margin: 5px;
        }
        .btn:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Clarum - System Faktur</h1>
            <p>Witaj w systemie do zarządzania fakturami</p>
        </header>
        
        <div class="content">
            <p>Ta strona została wygenerowana przez plik simple.php aby sprawdzić czy podstawowe renderowanie działa poprawnie.</p>
            
            <h2>Status Systemu:</h2>
            <ul>
                <li>PHP działa: <strong>Tak</strong></li>
                <li>Wersja PHP: <?php echo phpversion(); ?></li>
                <li>Serwer: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Nieznany'; ?></li>
            </ul>
            
            <h2>Dostępne Opcje:</h2>
            <a href="debug.php" class="btn">Uruchom Diagnostykę</a>
            <a href="test.php" class="btn">Test PHP</a>
            <a href="index.php" class="btn">Spróbuj Głównej Aplikacji</a>
        </div>
    </div>
</body>
</html> 