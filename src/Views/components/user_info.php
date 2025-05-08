<?php
// Komponent wyświetlający informacje o zalogowanym użytkowniku
if (isset($_SESSION['user']) && isset($_SESSION['user_role'])) {
    $username = htmlspecialchars($_SESSION['user'], ENT_QUOTES);
    $role = htmlspecialchars($_SESSION['user_role'], ENT_QUOTES);
    
    // Dodaj odpowiednie klasy CSS i ikony dla różnych ról
    $roleClass = $role === 'superadmin' ? 'role-superadmin' : 'role-admin';
    $icon = $role === 'superadmin' ? 'fa-user-shield' : 'fa-user';
    
    echo '<div class="user-info-container">';
    echo '<i class="fa-solid ' . $icon . ' user-icon"></i>';
    echo '<div class="user-info">';
    echo '<span class="user-name">Zalogowany jako: <strong>' . $username . '</strong></span>';
    echo '<span class="user-role ' . $roleClass . '">' . ($role === 'superadmin' ? 'Superadmin' : 'Admin') . '</span>';
    echo '</div>';
    echo '</div>';
}
?>

<style>
.user-info-container {
    position: fixed;
    top: 15px;
    right: 15px;
    background-color: rgba(255, 255, 255, 0.95);
    border-radius: 8px;
    padding: 10px 15px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.15);
    z-index: 1500;
    max-width: 250px;
    display: flex;
    align-items: center;
    gap: 10px;
    backdrop-filter: blur(5px);
    border: 1px solid rgba(230, 230, 230, 0.8);
    transition: all 0.3s ease;
    animation: fade-in 0.5s ease;
}

@keyframes fade-in {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.user-info-container:hover {
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    transform: translateY(-2px);
}

.user-info {
    display: flex;
    flex-direction: column;
    font-size: 14px;
}

.user-icon {
    font-size: 20px;
    color: #555;
}

.user-name {
    margin-bottom: 5px;
    color: #333;
    font-weight: 500;
}

.user-name strong {
    color: #1976D2;
    font-weight: 700;
}

.user-role {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    text-align: center;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    letter-spacing: 0.5px;
}

.role-admin {
    background-color: #4caf50;
    color: white;
}

.role-superadmin {
    background-color: #ff9800;
    color: white;
}

/* Responsywność dla urządzeń mobilnych */
@media (max-width: 768px) {
    .user-info-container {
        top: auto;
        bottom: 15px;
        right: 15px;
        max-width: 200px;
        padding: 8px 12px;
    }
    
    .user-icon {
        font-size: 18px;
    }
    
    .user-info {
        font-size: 13px;
    }
}
</style> 