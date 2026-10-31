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

$router->get('/finance/card-purchases/{id}', [\VTD\Controllers\FinanceCardsController::class, 'purchaseDetail']);

// Cartões: mesmas barreiras de plano e sessão do financeiro.
$router->get('/finance/banks', [\VTD\Controllers\FinanceCardsController::class, 'banks']);
$router->get('/finance/cards', [\VTD\Controllers\FinanceCardsController::class, 'cards']);
$router->post('/finance/cards', [\VTD\Controllers\FinanceCardsController::class, 'create']);
$router->put('/finance/cards/{id}', [\VTD\Controllers\FinanceCardsController::class, 'update']);
$router->post('/finance/cards/{id}/archive', [\VTD\Controllers\FinanceCardsController::class, 'archive']);
$router->get('/finance/cards/{id}/invoices', [\VTD\Controllers\FinanceCardsController::class, 'invoices']);
$router->get('/finance/card-invoices/{id}', [\VTD\Controllers\FinanceCardsController::class, 'invoice']);
$router->post('/finance/card-purchases/preview', [\VTD\Controllers\FinanceCardsController::class, 'preview']);
$router->post('/finance/card-purchases', [\VTD\Controllers\FinanceCardsController::class, 'purchase']);
$router->put('/finance/card-purchases/{id}', [\VTD\Controllers\FinanceCardsController::class, 'updatePurchase']);
$router->post('/finance/card-purchases/{id}/cancel', [\VTD\Controllers\FinanceCardsController::class, 'cancel']);
$router->post('/finance/card-invoices/{id}/settle', [\VTD\Controllers\FinanceCardsController::class, 'settle']);

// Financeiro V2: painel consolidado, agenda e recorrências
$router->get('/finance/v2/dashboard', [\VTD\Controllers\FinanceV2Controller::class, 'dashboard']);
$router->post('/finance/v2/recurring', [\VTD\Controllers\FinanceV2Controller::class, 'createRecurring']);
$router->post('/finance/v2/recurring/generate', [\VTD\Controllers\FinanceV2Controller::class, 'generateRecurring']);
$router->post('/finance/v2/recurring/{id}/stop', [\VTD\Controllers\FinanceV2Controller::class, 'stopRecurring']);

// Administração & Painel Master (Requer role admin)
$router->get('/admin/overview', [\VTD\Controllers\AdminController::class, 'overview']);
$router->get('/admin/clients', [\VTD\Controllers\AdminController::class, 'clients']);
$router->post('/admin/clients', [\VTD\Controllers\AdminController::class, 'createClient']);
$router->put('/admin/clients/{id}', [\VTD\Controllers\AdminController::class, 'updateClient']);
$router->patch('/admin/clients/{id}/toggle', [\VTD\Controllers\AdminController::class, 'toggleClient']);
$router->put('/admin/suppliers/{id}', [\VTD\Controllers\AdminController::class, 'updateSupplier']);
$router->post('/admin/suppliers', [\VTD\Controllers\AdminController::class, 'createSupplier']);
$router->put('/admin/chapters/{index}', [\VTD\Controllers\AdminController::class, 'updateChapter']);
$router->put('/admin/calendar/{index}', [\VTD\Controllers\AdminController::class, 'updateMonth']);

// Painel Master Avançado
$router->get('/master/overview', [\VTD\Controllers\MasterController::class, 'overview']);
$router->get('/master/clients', [\VTD\Controllers\MasterController::class, 'listClients']);
$router->post('/master/clients', [\VTD\Controllers\MasterController::class, 'createClient']);
$router->get('/master/clients/{id}', [\VTD\Controllers\MasterController::class, 'clientDetail']);
$router->patch('/master/clients/{id}/toggle', [\VTD\Controllers\MasterController::class, 'toggleClientStatus']);
$router->post('/master/clients/{id}/resend-invite', [\VTD\Controllers\MasterController::class, 'resendInvite']);
$router->delete('/master/clients/{id}/accesses/{accessId}', [\VTD\Controllers\MasterController::class, 'revokeAccess']);
$router->get('/master/products', [\VTD\Controllers\MasterController::class, 'listProducts']);
$router->get('/master/integrations', [\VTD\Controllers\MasterController::class, 'listIntegrations']);
$router->post('/master/integrations', [\VTD\Controllers\MasterController::class, 'createIntegration']);
$router->put('/master/integrations/{id}', [\VTD\Controllers\MasterController::class, 'updateIntegration']);
$router->patch('/master/integrations/{id}/toggle', [\VTD\Controllers\MasterController::class, 'toggleIntegration']);
$router->get('/master/events', [\VTD\Controllers\MasterController::class, 'listEvents']);
$router->get('/master/events/{id}', [\VTD\Controllers\MasterController::class, 'eventDetail']);
$router->post('/master/events/{id}/reprocess', [\VTD\Controllers\MasterController::class, 'reprocessEvent']);
$router->get('/master/settings', [\VTD\Controllers\MasterController::class, 'getSettings']);
$router->post('/master/settings', [\VTD\Controllers\MasterController::class, 'saveSettings']);

// Webhook Receiver & Integrações
$router->post('/webhooks/receiver/{token}', [\VTD\Controllers\WebhookReceiverController::class, 'handle']);
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
