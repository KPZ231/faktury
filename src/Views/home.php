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
        .hero-section {
            position: relative;
            padding: 60px 20px;
            margin: -60px -20px 40px -20px;
            background: linear-gradient(135deg, #2c3e50, #4CA1AF);
            border-radius: 0 0 50px 50px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            color: white;
            text-align: center;
        }
        
        .hero-section::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255, 255, 255, 0.1)" fill-opacity="1" d="M0,96L48,128C96,160,192,224,288,229.3C384,235,480,181,576,144C672,107,768,85,864,101.3C960,117,1056,171,1152,197.3C1248,224,1344,224,1392,224L1440,224L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
            background-position: bottom;
            background-repeat: no-repeat;
            background-size: 100%;
            opacity: 0.4;
            z-index: 1;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero-logo {
            font-size: 5rem;
            margin-bottom: 20px;
            color: white;
            text-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            animation: pulse 2s infinite alternate ease-in-out;
        }
        
        @keyframes pulse {
            from { transform: scale(1); }
            to { transform: scale(1.1); }
        }
        
        .hero-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
        
        .hero-subtitle {
            font-size: 1.4rem;
            opacity: 0.9;
            max-width: 700px;
            margin: 0 auto 30px auto;
            font-weight: 300;
        }
        
        .hero-cta {
            display: inline-block;
            background: white;
            color: #2c3e50;
            padding: 14px 30px;
            border-radius: 30px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            margin-top: 20px;
            font-size: 1.1rem;
        }
        
        .hero-cta:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin: 60px 0;
        }
        
        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 30px 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            height: 100%;
            text-decoration: none;
            color: inherit;
        }
        
        .feature-card::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, #4CA1AF, #2c3e50);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
        }
        
        .feature-card:hover::after {
            transform: scaleX(1);
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #4CA1AF;
            transition: all 0.4s ease;
        }
        
        .feature-card:hover .feature-icon {
            transform: scale(1.2) rotate(10deg);
            color: #2c3e50;
        }
        
        .feature-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }
        
        .feature-description {
            color: #777;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 20px;
            flex-grow: 1;
        }
        
        .action-button {
            display: inline-block;
            background: linear-gradient(to right, #4CA1AF, #2c3e50);
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            align-self: flex-start;
            margin: auto;
        }
        
        .action-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 15px rgba(44, 62, 80, 0.3);
        }
        
        @media (max-width: 768px) {
            .hero-section {
                padding: 40px 20px;
                margin: -60px -20px 30px -20px;
            }
            
            .hero-logo {
                font-size: 3rem;
            }
            
            .hero-title {
                font-size: 1.8rem;
            }
            
            .hero-subtitle {
                font-size: 1.1rem;
            }
            
            .features {
                grid-template-columns: 1fr;
                gap: 20px;
                margin: 40px 0;
            }
        }
        
        /* Animation keyframes */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
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
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-content">
                <i class="fa-solid fa-file-invoice-dollar hero-logo"></i>
                <h1 class="hero-title">Nowoczesne zarządzanie fakturami</h1>
                <p class="hero-subtitle">Kompleksowe rozwiązanie do kontrolowania faktur, płatności i prowizji agentów w jednym miejscu</p>
                <a href="/invoices" class="hero-cta">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> Rozpocznij pracę
                </a>
            </div>
        </section>
        
        <!-- Features Section -->
        <section class="features">
            <a href="/invoices" class="feature-card">
                <i class="fa-solid fa-file-invoice feature-icon"></i>
                <h3 class="feature-title">Zarządzanie Fakturami</h3>
                <p class="feature-description">Łatwe importowanie i przeglądanie faktur. Filtrowanie według daty, statusu płatności i innych kryteriów.</p>
                <button class="action-button">Przejdź do faktur</button>
            </a>
            
            <a href="/agents" class="feature-card">
                <i class="fa-solid fa-user-tie feature-icon"></i>
                <h3 class="feature-title">Zarządzanie Agentami</h3>
                <p class="feature-description">Dodawaj i zarządzaj agentami, przypisuj im sprawy i kontroluj prowizje. Monitoruj efektywność swoich agentów.</p>
                <button class="action-button">Zarządzaj agentami</button>
            </a>
            
            <a href="/test" class="feature-card">
                <i class="fa-solid fa-table feature-icon"></i>
                <h3 class="feature-title">Tabele i Raporty</h3>
                <p class="feature-description">Dane prezentowane w czytelnych tabelach z zaawansowanymi opcjami sortowania, filtrowania i eksportu.</p>
                <button class="action-button">Zobacz tabele</button>
            </a>
            
            <a href="/wizard" class="feature-card">
                <i class="fa-solid fa-wand-magic-sparkles feature-icon"></i>
                <h3 class="feature-title">Kreator Rekordów</h3>
                <p class="feature-description">Łatwe tworzenie nowych wpisów z pomocą intuicyjnego, interaktywnego kreatora. Oszczędzaj czas przy dodawaniu danych.</p>
                <button class="action-button">Uruchom kreator</button>
            </a>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animacja elementów na stronie
            const featureCards = document.querySelectorAll('.feature-card');
            
            // Animuj karty funkcji
            featureCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.animation = `fadeInUp 0.6s ease-out ${index * 0.1}s forwards`;
                }, 300);
            });
        });
    </script>
</body>
</html> 