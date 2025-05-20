<?php
/**
 * Common header component for all views
 * This file should be included at the beginning of each view
 * to ensure consistent styling and responsive behavior
 */
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="description" content="System Faktur - profesjonalne zarządzanie fakturami">
<link rel="shortcut icon" href="/assets/images/favicon.png" type="image/x-icon">

<!-- Base styles -->
<link rel="stylesheet" href="/assets/css/style.css">

<!-- Responsive navigation styles - important for menu behavior -->
<link rel="stylesheet" href="/assets/css/responsive-nav.css">

<!-- Font Awesome icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Responsive behavior script -->
<script src="/assets/js/responsive.js" defer></script>

<!-- Common script to ensure consistent responsive behavior -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log("Header component loaded - initializing responsive behavior");
    
    // Ensure mobile menu is properly initialized
    if (typeof initMobileNavigation === 'function') {
        // If function exists but hasn't been called by responsive.js yet
        if (!document.querySelector('.mobile-menu-btn')) {
            initMobileNavigation();
        }
    } else {
        console.warn("Mobile navigation initialization function not found");
    }
});
</script> 