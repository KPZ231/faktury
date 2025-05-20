<?php
/**
 * Navigation component that includes both:
 * - Desktop sidebar (shown on screens > 1024px)
 * - Mobile navigation (shown on screens <= 1024px)
 */
?>

<!-- Desktop Sidebar Navigation (visible on screens > 1024px) -->
<nav class="cleannav">
    <ul class="cleannav__list">
        <li class="cleannav__item">
            <a href="/" class="cleannav__link <?php echo ($current_page ?? '') === 'home' ? 'active' : ''; ?>" data-tooltip="Strona główna">
                <i class="fa-solid fa-home cleannav__icon"></i>
            </a>
        </li>
        <li class="cleannav__item">
            <a href="/table" class="cleannav__link <?php echo ($current_page ?? '') === 'table' ? 'active' : ''; ?>" data-tooltip="Tabela danych">
                <i class="fa-solid fa-table cleannav__icon"></i>
            </a>
        </li>
        <li class="cleannav__item">
            <a href="/agents" class="cleannav__link <?php echo ($current_page ?? '') === 'agents' ? 'active' : ''; ?>" data-tooltip="Agenci">
                <i class="fa-solid fa-users cleannav__icon"></i>
            </a>
        </li>
        <li class="cleannav__item">
            <a href="/invoices" class="cleannav__link <?php echo ($current_page ?? '') === 'invoices' ? 'active' : ''; ?>" data-tooltip="Faktury">
                <i class="fa-solid fa-file-invoice cleannav__icon"></i>
            </a>
        </li>
        <li class="cleannav__item">
            <a href="/import" class="cleannav__link <?php echo ($current_page ?? '') === 'import' ? 'active' : ''; ?>" data-tooltip="Import danych">
                <i class="fa-solid fa-upload cleannav__icon"></i>
            </a>
        </li>
        <?php if (isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'superadmin')): ?>
        <li class="cleannav__item">
            <a href="/database" class="cleannav__link <?php echo ($current_page ?? '') === 'database' ? 'active' : ''; ?>" data-tooltip="Baza danych">
                <i class="fa-solid fa-database cleannav__icon"></i>
            </a>
        </li>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin'): ?>
        <li class="cleannav__item">
            <a href="/admin/users" class="cleannav__link <?php echo ($current_page ?? '') === 'users' ? 'active' : ''; ?>" data-tooltip="Użytkownicy">
                <i class="fa-solid fa-user-shield cleannav__icon"></i>
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

<!-- Mobile Navigation Button (visible on screens <= 1024px) -->
<button class="mobile-menu-btn" aria-label="Open menu">
    <i class="fa-solid fa-bars"></i>
</button>

<!-- Mobile Navigation Menu (hidden by default) -->
<nav class="mobile-nav">
    <button class="mobile-menu-close" aria-label="Close menu">
        <i class="fa-solid fa-times"></i>
    </button>
    
    <ul class="mobile-nav-list">
        <li class="mobile-nav-item">
            <a href="/" class="mobile-nav-link <?php echo ($current_page ?? '') === 'home' ? 'active' : ''; ?>">
                <i class="fa-solid fa-home mobile-nav-icon"></i>
                <span>Strona główna</span>
            </a>
        </li>
        <li class="mobile-nav-item">
            <a href="/table" class="mobile-nav-link <?php echo ($current_page ?? '') === 'table' ? 'active' : ''; ?>">
                <i class="fa-solid fa-table mobile-nav-icon"></i>
                <span>Tabela danych</span>
            </a>
        </li>
        <li class="mobile-nav-item">
            <a href="/agents" class="mobile-nav-link <?php echo ($current_page ?? '') === 'agents' ? 'active' : ''; ?>">
                <i class="fa-solid fa-users mobile-nav-icon"></i>
                <span>Agenci</span>
            </a>
        </li>
        <li class="mobile-nav-item">
            <a href="/invoices" class="mobile-nav-link <?php echo ($current_page ?? '') === 'invoices' ? 'active' : ''; ?>">
                <i class="fa-solid fa-file-invoice mobile-nav-icon"></i>
                <span>Faktury</span>
            </a>
        </li>
        <li class="mobile-nav-item">
            <a href="/import" class="mobile-nav-link <?php echo ($current_page ?? '') === 'import' ? 'active' : ''; ?>">
                <i class="fa-solid fa-upload mobile-nav-icon"></i>
                <span>Import danych</span>
            </a>
        </li>
        <?php if (isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'superadmin')): ?>
        <li class="mobile-nav-item">
            <a href="/database" class="mobile-nav-link <?php echo ($current_page ?? '') === 'database' ? 'active' : ''; ?>">
                <i class="fa-solid fa-database mobile-nav-icon"></i>
                <span>Baza danych</span>
            </a>
        </li>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin'): ?>
        <li class="mobile-nav-item">
            <a href="/admin/users" class="mobile-nav-link <?php echo ($current_page ?? '') === 'users' ? 'active' : ''; ?>">
                <i class="fa-solid fa-user-shield mobile-nav-icon"></i>
                <span>Użytkownicy</span>
            </a>
        </li>
        <?php endif; ?>
        <li class="mobile-nav-item">
            <a href="/logout" class="mobile-nav-link">
                <i class="fa-solid fa-sign-out-alt mobile-nav-icon"></i>
                <span>Wyloguj</span>
            </a>
        </li>
    </ul>
</nav>

<!-- Mobile Navigation Overlay -->
<div class="mobile-nav-overlay"></div> 