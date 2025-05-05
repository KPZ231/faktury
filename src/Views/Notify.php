<?php
/**
 * Simple PHP Notification System
 *
 * Usage:
 *   include 'notify.php';
 *   Notify::show("Hello World", "success", 4000);
 *
 * Supported types: info, success, warning, error
 */

class Notify {
    /**
     * Whether the assets have been initialized
     * @var bool
     */
    protected static $initialized = false;

    /**
     * Ensure CSS/JS/container is output once
     */
    protected static function init() {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        // Output container, styles, and scripts
        echo <<<HTML

<!-- Notification Container -->
<style>
    #notify-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
    }
    .notify {
        min-width: 250px;
        margin-top: 10px;
        padding: 12px 16px;
        border-radius: 4px;
        color: #fff;
        font-family: sans-serif;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        transform: translateX(100%);
        transition: transform 0.4s ease, opacity 0.4s ease;
        opacity: 0;
    }
    .notify.show {
        transform: translateX(0);
        opacity: 1;
    }
    .notify.info    { background-color: #3498db; }
    .notify.success { background-color: #2ecc71; }
    .notify.warning { background-color: #f1c40f; color: #333; }
    .notify.error   { background-color: #e74c3c; }
</style>

<div id="notify-container"></div>

<script>
(function() {
    window.showNotify = function(message, type, duration) {
        var container = document.getElementById('notify-container');
        if (!container) return;

        var notif = document.createElement('div');
        notif.className = 'notify ' + (type || 'info');
        notif.textContent = message;
        container.appendChild(notif);

        // Trigger slide-in
        setTimeout(function() {
            notif.classList.add('show');
        }, 10);

        // Remove after duration
        setTimeout(function() {
            notif.classList.remove('show');
            // Remove from DOM after transition
            setTimeout(function() {
                container.removeChild(notif);
            }, 400);
        }, duration || 3000);
    };
})();
</script>

HTML;
    }

    /**
     * Show a notification
     *
     * @param string \$message  Text to display
     * @param string \$type     "info", "success", "warning", or "error"
     * @param int    \$duration Duration in milliseconds before hiding
     */
    public static function show($message, $type = 'info', $duration = 3000) {
        self::init();
        // Escape message for JS
        $msg = addslashes(htmlspecialchars($message, ENT_QUOTES));
        $typeJs = addslashes($type);
        $durJs  = intval($duration);
        echo "<script>showNotify('" . $msg . "','" . $typeJs . "'," . $durJs . ");</script>";
    }
}
