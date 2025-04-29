<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Powiadomienie</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .notification {
            position: fixed;
            top: 20px;
            right: -100%;
            padding: 20px;
            background: #323232;
            color: #fff;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            max-width: 300px;
            font-family: sans-serif;
            transition: right 0.5s ease-out;
            z-index: 1000;
        }
        .notification.show {
            right: 20px;
        }
    </style>
</head>
<body>
    <div class="notification" id="notification">
        <?= isset($message) ? $message : '<em>Brak komunikatu.</em>' ?>
    </div>
    <script>
        // Dodanie klasy .show po załadowaniu, by animować wjazd z prawej
        document.addEventListener('DOMContentLoaded', function() {
            var notif = document.getElementById('notification');
            setTimeout(function() { notif.classList.add('show'); }, 50);
            // Automatyczne ukrycie po 5 sekundach
            setTimeout(function() { notif.classList.remove('show'); }, 5050);
        });
    </script>
</body>
</html>