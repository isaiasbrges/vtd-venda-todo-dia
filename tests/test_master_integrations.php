<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/src/Autoloader.php';
\VTD\Autoloader::register();

use VTD\Config\Config;
use VTD\Core\Request;
use VTD\Core\Response;
use VTD\Controllers\MasterController;
use VTD\Database\Connection;
use VTD\Database\Migrator;
use VTD\Security\Csrf;

if (getenv('DB_DRIVER') !== 'mysql') {
    Config::set('db.driver', 'sqlite');
    Config::set('db.database', ':memory:');
}
Config::set('app_url', 'https://vtd.example');
$pdo = Connection::getInstance();
$migrator = new Migrator($pdo);
$migrator->runMigrations();
$migrator->runSeeds();
Response::$stopOnExit = false;
\VTD\Security\SessionManager::start();
$_SESSION['user_id'] = 'user-1';
$headers = ['x-csrf-token' => Csrf::generateToken()];
$master = new MasterController();

function responseOf(callable $action): array
{
    ob_start();
    try {
        $action();
        return json_decode(ob_get_contents(), true, 512, JSON_THROW_ON_ERROR);
    } finally {
        ob_end_clean();
    }
}

$payload = ['name' => 'Checkout teste', 'provider' => 'custom', 'environment' => 'sandbox',
    'status' => 'inactive', 'secret_key' => 'original-secret', 'offer_mappings' => ['123' => 'kit-plus']];
$created = responseOf(fn() => $master->createIntegration(new Request('POST', '/', [], $payload, $headers)));
assert($created['success'] === true);
$id = $created['data']['id'];
$row = $pdo->query("SELECT * FROM integrations WHERE id = '$id'")->fetch();
assert($row['status'] === 'inactive', 'Creation must respect inactive status');

$updated = responseOf(fn() => $master->updateIntegration(new Request('PUT', '/', [], ['name' => 'Novo nome'], $headers), ['id' => $id]));
assert($updated['success'] === true);
$row = $pdo->query("SELECT * FROM integrations WHERE id = '$id'")->fetch();
assert($row['secret_key'] === 'original-secret');
assert($row['environment'] === 'sandbox' && $row['status'] === 'inactive');

responseOf(fn() => $master->updateIntegration(new Request('PUT', '/', [], ['secret_key' => 'replacement-secret'], $headers), ['id' => $id]));
$logs = $pdo->query('SELECT details FROM master_audit_logs')->fetchAll(PDO::FETCH_COLUMN);
assert(!str_contains(implode('', $logs), 'replacement-secret'), 'Audit must not store secrets');

$listed = responseOf(fn() => $master->listIntegrations(new Request('GET', '/')));
$integration = array_values(array_filter($listed['data'], fn($i) => $i['id'] === $id))[0];
assert($integration['has_secret'] === true);
assert(!isset($integration['secret_key']));
assert(str_starts_with($integration['webhook_url'], 'https://vtd.example/api/'));

foreach ([['provider' => 'invalid'], ['environment' => 'invalid'], ['offer_mappings' => ['123' => 'unknown']], ['enabled_events' => 'invalid']] as $invalid) {
    $result = responseOf(fn() => $master->createIntegration(new Request('POST', '/', [], array_replace($payload, $invalid), $headers)));
    assert($result['error']['code'] === 'VALIDATION_ERROR');
}
$invalidSettings = responseOf(fn() => $master->saveSettings(new Request('POST', '/', [], ['support_email' => 'invalid'], $headers)));
assert($invalidSettings['error']['code'] === 'VALIDATION_ERROR');
$saved = responseOf(fn() => $master->saveSettings(new Request('POST', '/', [], ['support_email' => 'suporte@example.com', 'support_whatsapp' => '11987654321'], $headers)));
assert($saved['success'] === true);
$settings = responseOf(fn() => $master->getSettings(new Request('GET', '/')));
assert($settings['data']['support_email'] === 'suporte@example.com');
assert($settings['data']['app_url'] === 'https://vtd.example');

$admin = new \VTD\Controllers\AdminController();
$supplier = responseOf(fn() => $admin->createSupplier(new Request('POST', '/', [], ['nome' => 'Fornecedor novo', 'wa' => ['11999999999']], $headers)));
assert($supplier['success'] === true);
$supplierId = $supplier['data']['id'];
assert(strlen($supplierId) <= 20);
$found = (new \VTD\Controllers\SupplierController())->querySuppliers(['search' => 'Fornecedor novo']);
assert($found['total'] === 1);
$edited = responseOf(fn() => $admin->updateSupplier(new Request('PUT', '/', [], ['nome' => 'Fornecedor editado'], $headers), ['id' => $supplierId]));
assert($edited['success'] === true);
$missing = responseOf(fn() => $admin->updateSupplier(new Request('PUT', '/', [], ['nome' => 'Inexistente'], $headers), ['id' => 'missing']));
assert($missing['error']['code'] === 'SUPPLIER_NOT_FOUND');
$empty = responseOf(fn() => $admin->createSupplier(new Request('POST', '/', [], ['nome' => ' '], $headers)));
assert($empty['error']['code'] === 'VALIDATION_ERROR');
echo "Master integration configuration tests passed\n";
