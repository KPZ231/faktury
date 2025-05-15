<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Faktur - Strona Główna</title>
    <link rel="shortcut icon" href="../../assets/images/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include_once __DIR__ . '/components/user_info.php'; ?>

    <nav class="cleannav">
        <ul class="cleannav__list">
            <li class="cleannav__item">
                <a href="/" class="cleannav__link active" data-tooltip="Strona główna">
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
                <a href="/table" class="cleannav__link" data-tooltip="Tabela z danymi">
                    <i class="fa-solid fa-table cleannav__icon"></i>
                </a>
            </li>
            <li class="cleannav__item">
                <a href="/wizard" class="cleannav__link" data-tooltip="Kreator rekordu">
                    <i class="fa-solid fa-wand-magic-sparkles cleannav__icon"></i>
                </a>
            </li>
            <li class="cleannav__item">
                <a href="/test" class="cleannav__link" data-tooltip="Test">
                    <i class="fa-solid fa-vial cleannav__icon"></i>
                </a>
            </li>
            <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin'): ?>
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

    <header>
        <h1>System Zarządzania Fakturami</h1>
    </header>

    <div class="landing-container">
        <div class="logo-container">
            <i class="fa-solid fa-file-invoice-dollar logo"></i>
        </div>
        
        <h2 class="welcome-message">Witaj w systemie zarządzania fakturami</h2>
        <p class="subtitle">Efektywne zarządzanie fakturami i płatnościami w jednym miejscu</p>
        
        <div class="features">
            <a href="/invoices" class="feature-card">
                <i class="fa-solid fa-file-invoice feature-icon"></i>
                <h3 class="feature-title">Zarządzanie Fakturami</h3>
                <p class="feature-description">Łatwe importowanie i przeglądanie faktur. Filtrowanie według daty i innych kryteriów.</p>
                <button class="action-button">Przejdź do faktur</button>
            </a>
            
            <a href="/agents" class="feature-card">
                <i class="fa-solid fa-user-tie feature-icon"></i>
                <h3 class="feature-title">Zarządzanie Agentami</h3>
                <p class="feature-description">Dodawaj i zarządzaj agentami, przypisuj im sprawy i kontroluj prowizje.</p>
                <button class="action-button">Zarządzaj agentami</button>
            </a>
            
            <a href="/table" class="feature-card">
                <i class="fa-solid fa-table feature-icon"></i>
                <h3 class="feature-title">Tabele i Raporty</h3>
                <p class="feature-description">Dane prezentowane w czytelnych tabelach z możliwością sortowania i filtrowania.</p>
                <button class="action-button">Zobacz tabele</button>
            </a>
            
            <a href="/wizard" class="feature-card">
                <i class="fa-solid fa-wand-magic-sparkles feature-icon"></i>
                <h3 class="feature-title">Kreator Rekordów</h3>
                <p class="feature-description">Łatwe tworzenie nowych wpisów z pomocą interaktywnego kreatora.</p>
                <button class="action-button">Uruchom kreator</button>
            </a>
        </div>
        
        <div class="footer-note">
            <p>© 2024 System Zarządzania Fakturami - Wszystkie prawa zastrzeżone</p>
        </div>
    </div>

    <script>
        // Dodatkowe animacje podczas przewijania
        document.addEventListener('DOMContentLoaded', function() {
            // Animacja kart przy przewijaniu
            const featureCards = document.querySelectorAll('.feature-card');
            
            function animateOnScroll() {
                featureCards.forEach((card, index) => {
                    // Dodaj opóźnienie animacji dla każdej kolejnej karty
                    setTimeout(() => {
                        card.style.animation = 'fadeInUp 0.8s ease-out forwards';
                    }, index * 150);
                });
            }
            
            // Uruchom animację po załadowaniu strony
            animateOnScroll();
        });
    </script>
</body>
</html> 