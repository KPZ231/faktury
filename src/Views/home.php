<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Faktur - Strona Główna</title>
    <link rel="shortcut icon" href="../../assets/images/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .landing-container {
            text-align: center;
            
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .logo-container {
            margin-bottom: 30px;
        }
        
        .logo {
            font-size: 3.5rem;
            color: var(--primary-color);
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
        
        .welcome-message {
            font-size: 2.2rem;
            margin-bottom: 20px;
            color: var(--primary-dark);
            animation: fadeInDown 1s ease-out;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .subtitle {
            color: var(--text-secondary);
            margin-bottom: 50px;
            font-size: 1.2rem;
            animation: fadeIn 1.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .features {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            margin-bottom: 60px;
            animation: fadeInUp 1s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .feature-card {
            background-color: white;
            border-radius: 15px;
            padding: 25px;
            width: 280px;
            box-shadow: var(--shadow-light);
            transition: all 0.4s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .feature-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(100, 181, 246, 0.1) 0%, rgba(0, 0, 0, 0) 75%);
            opacity: 0;
            transition: opacity 0.4s ease;
            z-index: 1;
        }
        
        .feature-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: var(--shadow-medium);
        }
        
        .feature-card:hover::before {
            opacity: 1;
        }
        
        .feature-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 20px;
            transition: transform 0.4s ease;
            position: relative;
            z-index: 2;
        }
        
        .feature-card:hover .feature-icon {
            transform: scale(1.2);
        }
        
        .feature-title {
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--primary-dark);
            font-size: 1.3rem;
            position: relative;
            z-index: 2;
        }
        
        .feature-description {
            color: var(--text-secondary);
            font-size: 1rem;
            line-height: 1.5;
            position: relative;
            z-index: 2;
        }
        
        .action-button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 30px;
            padding: 10px 20px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-top: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.3s ease, transform 0.3s ease, background-color 0.3s ease;
            display: inline-block;
        }
        
        .feature-card:hover .action-button {
            opacity: 1;
            transform: translateY(0);
        }
        
        .action-button:hover {
            background-color: var(--primary-dark);
        }
        
        .additional-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
            opacity: 0;
            animation: fadeIn 1.5s ease-out forwards;
            animation-delay: 0.5s;
        }
        
        .section-card {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow-light);
            transition: all 0.3s ease;
            text-align: left;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
            border-left: 4px solid transparent;
        }
        
        .section-card:nth-child(1) {
            border-left-color: #4caf50;
        }
        
        .section-card:nth-child(2) {
            border-left-color: #ff9800;
        }
        
        .section-card:nth-child(3) {
            border-left-color: #e91e63;
        }
        
        .section-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }
        
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .section-icon {
            color: var(--primary-color);
            font-size: 1.2rem;
        }
        
        .section-content {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .view-more {
            color: var(--primary-color);
            font-weight: 500;
            margin-top: auto;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .section-card:hover .view-more {
            color: var(--primary-dark);
            transform: translateX(5px);
        }
        
        .footer-note {
            margin-top: 60px;
            color: var(--text-secondary);
            font-size: 0.9rem;
            border-top: 1px solid var(--border-color);
            padding-top: 20px;
        }
        
        @media (max-width: 768px) {
            .features {
                flex-direction: column;
                align-items: center;
            }
            
            .feature-card {
                width: 90%;
                max-width: 350px;
            }
            
            .welcome-message {
                font-size: 1.8rem;
            }
            
            .additional-sections {
                grid-template-columns: 1fr;
            }
        }
    </style>
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