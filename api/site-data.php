<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../backend/bootstrap.php';

try {
    $pdo = tenji_db();
    echo json_encode(
        [
            'ok' => true,
            'settings' => tenji_get_settings($pdo),
            'products' => tenji_get_products($pdo),
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to load site data']);
}

