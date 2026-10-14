<?php
/**
 * Escape a value safely — handles null, arrays, anything.
 */
if (!function_exists('h')) {
    function h($value) {
        if ($value === null) return '';
        if (is_array($value)) return htmlspecialchars(json_encode($value), ENT_QUOTES, 'UTF-8');
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}