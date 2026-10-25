<?php
declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

// 1. Bloqueio de segurança para backend, banco sqlite e arquivos ocultos
if (
    str_starts_with($uri, '/backend') ||
    str_starts_with($uri, '/.') ||
    str_contains($uri, '.sqlite') ||
    str_starts_with($uri, '/tests')
) {
    http_response_code(403);
    echo "403 Forbidden";
    exit;
}

// 2. Roteamento de endpoints de API (/api/* -> api.php)
if (str_starts_with($uri, '/api')) {
    $_GET['endpoint'] = substr($uri, 4) ?: '/';
    require __DIR__ . '/api.php';
    exit;
}

// 3. Raiz: serve index.html
if ($uri === '/' || $uri === '') {
    $filePath = __DIR__ . '/index.html';
    if (file_exists($filePath)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($filePath);
        exit;
    }
}

// 4. Arquivos estáticos (app.js, styles.css, data.js, imagens, etc.)
$filePath = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $uri);
if (is_file($filePath)) {
    return false; // Permite que o servidor embutido do PHP sirva diretamente
}

// 5. Fallback 404
http_response_code(404);
echo "404 Not Found";
