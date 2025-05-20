# Dokumentacja Implementacji Responsywnego Menu

## Cel implementacji

Zapewnienie prawidłowego wyświetlania menu nawigacyjnego na różnych urządzeniach zgodnie z następującymi wytycznymi:

- **Urządzenia mobilne (≤ 768px)**: Menu hamburger zamiast paska bocznego
- **Tablety (769px - 1024px)**: Menu hamburger (tak samo jak na telefonach)
- **Komputery (> 1024px)**: Tradycyjny pasek boczny

## Zaimplementowane zmiany

### 1. Utworzono nowy plik CSS dla responsywnej nawigacji

Plik `public/assets/css/responsive-nav.css` zawiera wszystkie style związane z responsywnością menu, w tym:
- Style dla desktopów (> 1024px) - sidebar
- Style dla tabletów i telefonów (≤ 1024px) - menu hamburger
- Specjalne style dla urządzeń z notchem (iPhone X, 11, 12, 13)

### 2. Utworzono komponenty do ponownego użycia

- `src/Views/components/header.php` - zawiera wszystkie potrzebne meta tagi, linki do plików CSS i JavaScript
- `src/Views/components/navigation.php` - zawiera zarówno menu boczne, jak i mobilne
- `src/Views/components/base_template.php` - szablon bazowy dla wszystkich widoków

### 3. Zastosowano media queries z odpowiednimi breakpointami

```css
/* Desktop (> 1024px) */
@media (min-width: 1025px) {
  /* Desktop styles */
}

/* Tablet and Mobile (≤ 1024px) */
@media (max-width: 1024px) {
  /* Tablet and mobile styles */
}
```

### 4. Poprawiono JavaScript odpowiedzialny za inicjowanie menu mobilnego

Funkcja `initMobileNavigation()` w pliku `public/assets/js/responsive.js` została zmodyfikowana, aby używała tego samego breakpointu (1024px) co style CSS.

## Jak używać komponentów

### Dodanie responsywnego menu do istniejących widoków

1. Dodaj link do `responsive-nav.css` w sekcji `<head>`:

```html
<link rel="stylesheet" href="/assets/css/responsive-nav.css">
```

2. Dodaj następujący kod na początku strony (przed elementem `<header>`):

```php
<?php $current_page = 'nazwa_strony'; // np. 'home', 'table', 'agents', itp. ?>
<?php include __DIR__ . '/../components/navigation.php'; ?>
```

### Tworzenie nowych widoków

1. Skopiuj `src/Views/components/base_template.php` i dostosuj go do swoich potrzeb
2. Ustaw zmienne `$page_title` i `$current_page` przed zawartością strony
3. Zastąp sekcję zawartości swoim własnym kodem HTML

Przykład:

```php
<?php
$page_title = 'Nazwa Strony';
$current_page = 'nazwa_strony';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <?php include __DIR__ . '/../components/header.php'; ?>
    <title><?php echo htmlspecialchars($page_title); ?></title>
</head>
<body>
    <?php include __DIR__ . '/../components/navigation.php'; ?>
    
    <header>
        <h1><?php echo htmlspecialchars($page_title); ?></h1>
        <?php include __DIR__ . '/../components/user_info.php'; ?>
    </header>
    
    <main>
        <!-- Zawartość strony -->
    </main>
</body>
</html>
```

## Uwagi techniczne

### Unikanie konfliktów CSS

Jeśli w projekcie są inne style CSS, które mogą kolidować z naszymi regułami, zawsze używaj `!important` dla krytycznych własności, takich jak `display`, `width`, `left` itp.

### Dodawanie nowych elementów do menu

Aby dodać nowe elementy do menu, należy je dodać zarówno do menu desktopowego (`.cleannav__list`), jak i mobilnego (`.mobile-nav-list`) w pliku `src/Views/components/navigation.php`.

### Optymalizacja dla urządzeń z notchem

Style dla urządzeń z notchem (np. iPhone X, 11, 12, 13) są zawarte w pliku CSS i wykorzystują zmienną `env(safe-area-inset-top)` do dostosowania paddingu.

## Rozwiązywanie problemów

### Menu nie jest widoczne na desktopie

1. Upewnij się, że plik `responsive-nav.css` jest poprawnie dołączony
2. Sprawdź, czy nie ma konfliktów CSS (inne reguły z `!important` nadpisujące nasze style)
3. Otwórz konsolę deweloperską i sprawdź, czy nie ma błędów JavaScript

### Menu hamburger nie pojawia się na tabletach

1. Upewnij się, że używany jest właściwy breakpoint (1024px)
2. Sprawdź, czy JavaScript odpowiedzialny za inicjowanie menu mobilnego jest poprawnie dołączony i wykonywany

### Problemy z JavaScript

1. Upewnij się, że plik `responsive.js` jest poprawnie dołączony
2. Sprawdź w konsoli, czy nie ma błędów JavaScript
3. Upewnij się, że `initMobileNavigation()` jest wywoływana dla tabletów i telefonów

---

Dokumentacja przygotowana: <?php echo date('Y-m-d'); ?> 