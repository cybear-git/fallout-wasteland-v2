<?php
declare(strict_types=1);

/**
 * CSRF-защита для форм
 */

require_once __DIR__ . '/auth.php';

generateCsrfToken();

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

function validateCsrfTokenJson(string $token): array {
    if (validateCsrfToken($token)) {
        return ['success' => true, 'error' => null];
    }
    return ['success' => false, 'error' => 'Неверный CSRF-токен'];
}
