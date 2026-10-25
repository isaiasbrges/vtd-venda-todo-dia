<?php
declare(strict_types=1);

// Ponto de entrada da API VTD
require_once __DIR__ . '/backend/src/Autoloader.php';
\VTD\Autoloader::register();

\VTD\Security\SessionManager::start();

$router = new \VTD\Core\Router();

// Health check
$router->get('/health', function($req) {
    \VTD\Core\Response::json([
        'status' => 'healthy',
        'time'   => date('Y-m-d H:i:s'),
        'php'    => PHP_VERSION
    ]);
});

// Autenticação & Conta
$router->post('/auth/login', [\VTD\Controllers\AuthController::class, 'login']);
$router->post('/auth/logout', [\VTD\Controllers\AuthController::class, 'logout']);
$router->get('/me', [\VTD\Controllers\AuthController::class, 'me']);
$router->put('/me', [\VTD\Controllers\AuthController::class, 'updateProfile']);
$router->post('/auth/forgot-password', [\VTD\Controllers\AuthController::class, 'forgotPassword']);
$router->post('/auth/reset-password', [\VTD\Controllers\AuthController::class, 'resetPassword']);

// Fornecedores & Favoritos
$router->get('/suppliers', [\VTD\Controllers\SupplierController::class, 'list']);
$router->get('/suppliers/{id}', [\VTD\Controllers\SupplierController::class, 'detail']);
$router->get('/favorites', [\VTD\Controllers\UserContentController::class, 'getFavorites']);
$router->post('/favorites', [\VTD\Controllers\UserContentController::class, 'setFavorite']);

// Calendário & Checklists
$router->get('/calendar', [\VTD\Controllers\CalendarController::class, 'list']);
$router->get('/checklists', [\VTD\Controllers\UserContentController::class, 'getChecklists']);
$router->post('/checklists', [\VTD\Controllers\UserContentController::class, 'setChecklist']);

// Guia, Progresso & Anotações
$router->get('/chapters', [\VTD\Controllers\GuideController::class, 'list']);
$router->get('/reading-progress', [\VTD\Controllers\UserContentController::class, 'getReadingProgress']);
$router->post('/reading-progress', [\VTD\Controllers\UserContentController::class, 'setReadingProgress']);
$router->get('/notes', [\VTD\Controllers\UserContentController::class, 'getNotes']);
$router->post('/notes', [\VTD\Controllers\UserContentController::class, 'setNote']);

// Financeiro (Requer Plano plus)
$router->get('/finance/overview', [\VTD\Controllers\FinanceController::class, 'overview']);
$router->get('/finance/transactions', [\VTD\Controllers\FinanceController::class, 'listTransactions']);
$router->post('/finance/transactions', [\VTD\Controllers\FinanceController::class, 'createTransaction']);
$router->put('/finance/transactions/{id}', [\VTD\Controllers\FinanceController::class, 'updateTransaction']);
$router->delete('/finance/transactions/{id}', [\VTD\Controllers\FinanceController::class, 'deleteTransaction']);
$router->post('/finance/transactions/{id}/settle', [\VTD\Controllers\FinanceController::class, 'settleTransaction']);
$router->get('/finance/categories', [\VTD\Controllers\FinanceController::class, 'listCategories']);
$router->post('/finance/categories', [\VTD\Controllers\FinanceController::class, 'createCategory']);
$router->delete('/finance/categories/{id}', [\VTD\Controllers\FinanceController::class, 'deleteCategory']);
$router->get('/finance/export-csv', [\VTD\Controllers\FinanceController::class, 'exportCsv']);

// Administração (Requer role admin)
$router->get('/admin/overview', [\VTD\Controllers\AdminController::class, 'overview']);
$router->get('/admin/clients', [\VTD\Controllers\AdminController::class, 'clients']);
$router->post('/admin/clients', [\VTD\Controllers\AdminController::class, 'createClient']);
$router->put('/admin/clients/{id}', [\VTD\Controllers\AdminController::class, 'updateClient']);
$router->patch('/admin/clients/{id}/toggle', [\VTD\Controllers\AdminController::class, 'toggleClient']);
$router->put('/admin/suppliers/{id}', [\VTD\Controllers\AdminController::class, 'updateSupplier']);
$router->put('/admin/chapters/{index}', [\VTD\Controllers\AdminController::class, 'updateChapter']);
$router->put('/admin/calendar/{index}', [\VTD\Controllers\AdminController::class, 'updateMonth']);

// Webhook Greenn
$router->post('/webhooks/greenn', [\VTD\Controllers\GreennWebhookController::class, 'handle']);

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
