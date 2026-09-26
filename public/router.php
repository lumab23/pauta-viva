<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestedFile = realpath(__DIR__ . $path);

// Deixa o servidor entregar os arquivos estáticos diretamente.
if (
    $path !== '/'
    && $requestedFile !== false
    && str_starts_with($requestedFile, __DIR__ . DIRECTORY_SEPARATOR)
    && is_file($requestedFile)
) {
    return false;
}

if (str_starts_with($path, '/api/')) {
    require __DIR__ . '/index.php';
    return true;
}

if ($path === '/') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');
    return true;
}

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'error' => [
        'code' => 'route_not_found',
        'message' => 'Rota não encontrada.',
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

return true;
