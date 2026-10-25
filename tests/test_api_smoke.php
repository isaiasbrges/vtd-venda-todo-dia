<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/src/Autoloader.php';
\VTD\Autoloader::register();

echo "=== INICIANDO TESTE DE FUMAÇA DA API (ROUTER + CONTROLLERS) ===\n";

// Configura banco de teste
$pdo = \VTD\Database\Connection::getInstance();
$migrator = new \VTD\Database\Migrator($pdo);
$migrator->runMigrations();
$migrator->runSeeds();

\VTD\Core\Response::$stopOnExit = false;

// 1. Testa Health Check
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/health';
$_GET['endpoint'] = '/health';

ob_start();
// Testamos router diretamente
$router = new \VTD\Core\Router();
$router->get('/health', function($req) {
    \VTD\Core\Response::json(['status' => 'healthy', 'version' => '1.0.0']);
});
$router->dispatch();
$output = ob_get_clean();
$data = json_decode($output, true);
assert($data['data']['status'] === 'healthy');
echo "PASS: Health check respondeu corretamente via Router.\n";

// 2. Testa Login através do AuthController no Router
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/auth/login';
$_GET['endpoint'] = '/auth/login';

$router = new \VTD\Core\Router();
$router->post('/auth/login', [\VTD\Controllers\AuthController::class, 'login']);

// Simula payload de login admin
$GLOBALS['test_raw_input'] = json_encode([
    'email' => 'demo@exemplo.com',
    'password' => '123456'
]);

ob_start();
$router->dispatch();
$loginOutput = ob_get_clean();
$loginData = json_decode($loginOutput, true);

assert($loginData['success'] === true, "Falha no login do admin: $loginOutput");
assert($loginData['data']['user']['email'] === 'demo@exemplo.com');
assert(!empty($loginData['data']['csrf_token']));
echo "PASS: Login admin realizado com sucesso via Router com CSRF retornado.\n";

// 3. Testa Lista de Fornecedores via Router
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/suppliers';
$_GET['endpoint'] = '/suppliers';
$GLOBALS['test_raw_input'] = '';

$router = new \VTD\Core\Router();
$router->get('/suppliers', [\VTD\Controllers\SupplierController::class, 'list']);

ob_start();
$router->dispatch();
$supOutput = ob_get_clean();
$supData = json_decode($supOutput, true);

assert($supData['success'] === true);
assert($supData['data']['total'] >= 234, "Esperado no mínimo 234 fornecedores, obtido: " . ($supData['data']['total'] ?? 0));
echo "PASS: Listagem de fornecedores retornou {$supData['data']['total']} fornecedores ativos.\n";

echo "=== TODOS OS TESTES DE FUMAÇA DA API PASSARAM COM SUCESSO! ===\n";
