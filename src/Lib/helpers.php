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
        // Jeśli wartość jest już w procentach (większa niż 1), nie mnożymy przez 100
        if ((float)$rate > 1) {
            return number_format((float)$rate, 2, ',', '') . '%';
        }
        // Jeśli wartość jest w formacie dziesiętnym (mniejsza niż 1), mnożymy przez 100
        return number_format((float)$rate * 100, 2, ',', '') . '%';
    }
}

// Tutaj możesz dodać inne globalne funkcje pomocnicze
?>