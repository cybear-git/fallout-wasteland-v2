<?php
declare(strict_types=1);

/**
 * CSRF-защита для форм
 * 
 * @package FalloutWasteland
 */

require_once __DIR__ . '/auth.php';

// Генерируем токен при первом обращении
generateCsrfToken();

/**
 * Рендеринг скрытого поля с CSRF-токеном
 * 
 * @return string HTML input field
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

/**
 * Валидация токена с возвратом ответа в JSON для AJAX
 * 
 * @param string $token Токен из запроса
 * @return array ['success' => bool, 'error' => string|null]
 */
function validateCsrfTokenJson(string $token): array {
    if (validateCsrfToken($token)) {
        return ['success' => true, 'error' => null];
    }
    return ['success' => false, 'error' => 'Неверный CSRF-токен'];
}
