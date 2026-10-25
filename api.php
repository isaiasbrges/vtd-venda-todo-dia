<?php
declare(strict_types=1);

// Ponto de entrada da API VTD
require_once __DIR__ . '/backend/src/Autoloader.php';
\VTD\Autoloader::register();

// Inicia sessão segura
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_only_cookies', '1');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

$router = new \VTD\Core\Router();

// Rotas básicas / Health check
$router->get('/health', function($req) {
    \VTD\Core\Response::json([
        'status' => 'healthy',
        'time'   => date('Y-m-d H:i:s'),
        'php'    => PHP_VERSION
    ]);
});

// Despacha requisição
try {
    $router->dispatch();
} catch (\Throwable $e) {
    if (\VTD\Config\Config::get('app_debug', false)) {
        \VTD\Core\Response::error('INTERNAL_SERVER_ERROR', $e->getMessage(), 500, [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
        ]);
    } else {
        \VTD\Core\Response::error('INTERNAL_SERVER_ERROR', 'Ocorreu um erro interno no servidor.', 500);
    }
}
