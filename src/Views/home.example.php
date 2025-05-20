<?php
/**
 * Przykładowa implementacja strony głównej z użyciem nowych komponentów
 * UWAGA: To jest przykład demonstracyjny, nie zastępuje on istniejącego pliku home.php
 */

// Ustaw zmienne dla strony
$page_title = 'System Faktur - Strona Główna';
$current_page = 'home';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <!-- Dołącz wspólny nagłówek z metadanymi, CSS i JS -->
    <?php include __DIR__ . '/components/header.php'; ?>
    
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Style specyficzne dla strony głównej -->
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
        
        /* Responsywne style dla sekcji hero */
        @media (max-width: 1024px) {
            .hero-section {
                margin-top: 0;
                padding: 40px 15px;
            }
            
            .hero-title {
                font-size: 2rem !important;
            }
            
            .hero-subtitle {
                font-size: 1.2rem !important;
            }
        }
    </style>
</head>
<body>
    <!-- Dołącz komponent nawigacji (zawiera zarówno sidebar, jak i menu mobilne) -->
    <?php include __DIR__ . '/components/navigation.php'; ?>
    
    <!-- Main header -->
    <header>
        <h1>System Faktur</h1>
        
        <!-- Dołącz informacje o użytkowniku -->
        <?php include __DIR__ . '/components/user_info.php'; ?>
    </header>
    
    <!-- Main content -->
    <main>
        <div class="content-wrapper">
            <!-- Hero section -->
            <section class="hero-section">
                <div class="hero-content">
                    <div class="hero-logo">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                    <h2 class="hero-title">Witaj w Systemie Faktur</h2>
                    <p class="hero-subtitle">Kompleksowe rozwiązanie do zarządzania fakturami i prowizjami</p>
                    <a href="/table" class="hero-cta">
                        <i class="fa-solid fa-table"></i> Zobacz dane
                    </a>
                </div>
            </section>
            
            <!-- Features section -->
            <div class="features">
                <a href="/table" class="feature-card">
                    <div class="feature-icon">
                        <i class="fa-solid fa-table"></i>
                    </div>
                    <h3 class="feature-title">Tabela danych</h3>
                    <p class="feature-description">Przeglądaj wszystkie dane w formie tabeli. Sortuj, filtruj i eksportuj dane według potrzeb.</p>
                    <button class="action-button">Otwórz</button>
                </a>
                
                <a href="/agents" class="feature-card">
                    <div class="feature-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <h3 class="feature-title">Agenci</h3>
                    <p class="feature-description">Zarządzaj agentami i ich prowizjami. Dodawaj nowych agentów i przypisuj im sprawy.</p>
                    <button class="action-button">Otwórz</button>
                </a>
                
                <a href="/invoices" class="feature-card">
                    <div class="feature-icon">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                    <h3 class="feature-title">Faktury</h3>
                    <p class="feature-description">Przeglądaj i zarządzaj fakturami. Śledź płatności i generuj raporty.</p>
                    <button class="action-button">Otwórz</button>
                </a>
                
                <a href="/import" class="feature-card">
                    <div class="feature-icon">
                        <i class="fa-solid fa-upload"></i>
                    </div>
                    <h3 class="feature-title">Import danych</h3>
                    <p class="feature-description">Importuj dane z plików CSV lub Excel. Szybko i efektywnie załaduj dane do systemu.</p>
                    <button class="action-button">Otwórz</button>
                </a>
            </div>
        </div>
    </main>
    
    <!-- Footer -->
    <footer>
        <p>&copy; <?php echo date('Y'); ?> System Faktur</p>
    </footer>
</body>
</html> 