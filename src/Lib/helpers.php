<?php
// src/Lib/helpers.php

if (!function_exists('format_currency')) {
    function format_currency($amount) {
        if ($amount === null) return '0,00 zł';
        return number_format((float)$amount, 2, ',', ' ') . ' zł';
    }
}

if (!function_exists('format_percent')) {
    function format_percent($rate) {
        if ($rate === null) return '0,00%';
        return number_format((float)$rate, 2, ',', '') . '%';
    }
}

// Tutaj możesz dodać inne globalne funkcje pomocnicze
?>