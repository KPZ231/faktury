# Faktury - System Zarządzania Fakturami

## O Projekcie

Aplikacja "Faktury" to system do konwersji, interpretacji i zarządzania danymi z faktur, zaprojektowany by usprawnić proces obsługi dokumentów finansowych. System umożliwia import danych z plików CSV, zarządzanie fakturami, śledzenie płatności oraz obsługę prowizji.

## Główne Funkcjonalności

- **Import faktur** - Możliwość importu danych faktur z plików CSV
- **Zarządzanie fakturami** - Przeglądanie, edycja i zarządzanie fakturami
- **System płatności** - Śledzenie statusów płatności i synchronizacja z zewnętrznymi źródłami
- **System prowizji** - Kalkulacja i zarządzanie prowizjami dla agentów
- **Panel administracyjny** - Zarządzanie bazą danych i użytkownikami systemu

## Technologie

- PHP
- MySQL
- Composer do zarządzania zależnościami
- Fast Route do routingu
- PHPSpreadsheet do obsługi plików Excel/CSV

## Struktura Projektu

- `src/` - Główny kod aplikacji
  - `Controllers/` - Kontrolery obsługujące logikę biznesową
  - `Models/` - Modele danych
  - `Views/` - Szablony widoków
  - `Lib/` - Biblioteki pomocnicze
  - `Routers/` - Konfiguracja routingu
- `public/` - Publiczny katalog dostępny przez serwer WWW
- `config/` - Pliki konfiguracyjne
- `vendor/` - Zależności composer
- `files/` - Katalog na pliki tymczasowe
- `Uploads/` - Katalog na przesłane pliki

## Instalacja

1. Sklonuj repozytorium
2. Zainstaluj zależności:
   ```
   composer install
   ```
3. Skonfiguruj plik `.env` z danymi dostępowymi do bazy danych
4. Skonfiguruj serwer WWW, aby wskazywał na katalog `public/` jako główny katalog aplikacji

## Dostęp do Systemu

System wymaga logowania. Dostępne są różne poziomy uprawnień:
- **Użytkownik standardowy** - Podstawowe operacje na fakturach
- **Superadmin** - Pełny dostęp, w tym zarządzanie bazą danych

## Wymagania

- PHP 7.4 lub nowszy
- MySQL 5.7 lub nowszy
- Rozszerzenie GD dla PHP
- Serwer WWW (Apache/Nginx)