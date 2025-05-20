<?php
/**
 * Base template for all views
 * Usage:
 * 1. Copy this file and rename it to your view name
 * 2. Set the $current_page variable before including this file
 * 3. Replace the content section with your view-specific content
 */

// Set page title and current page if not set
$page_title = $page_title ?? 'System Faktur';
$current_page = $current_page ?? '';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <!-- Include common header -->
    <?php include __DIR__ . '/header.php'; ?>
    
    <!-- Page-specific title -->
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Page-specific styles can be added here -->
    <style>
        /* Add page-specific styles here */
    </style>
</head>
<body>
    <!-- Include navigation components -->
    <?php include __DIR__ . '/navigation.php'; ?>
    
    <!-- Main header -->
    <header>
        <h1><?php echo htmlspecialchars($page_title); ?></h1>
        
        <!-- Optional user info component -->
        <?php if (file_exists(__DIR__ . '/user_info.php')): ?>
            <?php include __DIR__ . '/user_info.php'; ?>
        <?php endif; ?>
    </header>
    
    <!-- Main content -->
    <main>
        <!-- Replace this section with your specific content -->
        <div class="content-wrapper">
            <h2>Content Section</h2>
            <p>Replace this with your specific page content.</p>
        </div>
    </main>
    
    <!-- Optional footer -->
    <footer>
        <p>&copy; <?php echo date('Y'); ?> System Faktur</p>
    </footer>

    <!-- Page-specific scripts can be added here -->
    <script>
        // Add page-specific scripts here
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Page specific scripts loaded');
        });
    </script>
</body>
</html> 