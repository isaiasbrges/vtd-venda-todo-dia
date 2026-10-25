<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/src/Autoloader.php';
\VTD\Autoloader::register();

// Reseta banco de teste
$dbPath = __DIR__ . '/../backend/storage/vtd_test.sqlite';
if (file_exists($dbPath)) {
    unlink($dbPath);
}
\VTD\Config\Config::set('db.driver', 'sqlite');
\VTD\Config\Config::set('db.database', $dbPath);
\VTD\Database\Connection::reset();

$migrator = new \VTD\Database\Migrator();
$migrator->runMigrations();
$migrator->runSeeds();

$pdo = \VTD\Database\Connection::getInstance();
$suppliersCount = (int)$pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
$chaptersCount = (int)$pdo->query("SELECT COUNT(*) FROM guide_chapters")->fetchColumn();
$monthsCount = (int)$pdo->query("SELECT COUNT(*) FROM calendar_months")->fetchColumn();
$tenantsCount = (int)$pdo->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
$usersCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

assert($suppliersCount === 234, "Expected 234 suppliers, got $suppliersCount");
assert($chaptersCount === 8, "Expected 8 chapters, got $chaptersCount");
assert($monthsCount === 12, "Expected 12 months, got $monthsCount");
assert($tenantsCount >= 1, "Expected at least 1 initial tenant, got $tenantsCount");
assert($usersCount >= 1, "Expected at least 1 initial admin/demo user, got $usersCount");

// Verifica estrutura de um fornecedor
$firstSupplier = $pdo->query("SELECT * FROM suppliers WHERE id = '1'")->fetch();
assert(!empty($firstSupplier), "Supplier with id 1 must exist");
assert($firstSupplier['name'] === 'ABP IMPORT', "Supplier 1 name must match ABP IMPORT");

echo "Migrations and seeds test passed (234 suppliers, 8 chapters, 12 months verified)\n";
