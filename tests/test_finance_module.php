<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/src/Autoloader.php';
\VTD\Autoloader::register();

// Reseta banco de teste
$dbPath = __DIR__ . '/../backend/storage/vtd_test.sqlite';
\VTD\Config\Config::set('db.driver', 'sqlite');
\VTD\Config\Config::set('db.database', $dbPath);
\VTD\Database\Connection::reset();

assert(class_exists('\VTD\Middlewares\PlanMiddleware'));
assert(class_exists('\VTD\Services\FinanceService'));
assert(class_exists('\VTD\Controllers\FinanceController'));

$pdo = \VTD\Database\Connection::getInstance();
$service = new \VTD\Services\FinanceService();

$tenantPlus = 'demo-store-1';
$userPlus = 'user-1';

// 1. Test calculation for October 2026
$overview = $service->calculateMonthOverview($tenantPlus, '2026-10');
assert(isset($overview['stats']), "Overview must contain stats");
$stats = $overview['stats'];

// In demo seeds:
// initialBalance = 200000 (R$ 2.000,00)
// Paid Income: t1 (400000) + t2 (450000) = 850000 (R$ 8.500,00)
// Paid Expense: t3 (200000) + t4 (150000) + t5 (80000) + t6 (150000) = 580000 (R$ 5.800,00)
// Start = 200000
// Inc = 850000
// Out = 580000
// Balance = 200000 + 850000 - 580000 = 470000 (R$ 4.700,00)
assert($stats['start'] === 200000, "Start balance must be 200000, got {$stats['start']}");
assert($stats['inc'] === 850000, "Paid income must be 850000, got {$stats['inc']}");
assert($stats['out'] === 580000, "Paid out must be 580000, got {$stats['out']}");
assert($stats['balance'] === 470000, "Realized balance must be 470000, got {$stats['balance']}");

// Pending:
// t7 pending expense (20000) + t8 pending expense (65000) = 85000
// t9 pending income (120000)
// Projected = balance (470000) + 120000 - 85000 = 505000 (R$ 5.050,00)
assert($stats['projected'] === 505000, "Projected balance must be 505000, got {$stats['projected']}");

// 2. Test create transaction with centavos & audit log
$newTx = $service->createTransaction($tenantPlus, [
    'description' => 'Serviço de Consultoria Especial',
    'type'        => 'income',
    'value_cents' => 100000, // R$ 1.000,00
    'category_id' => 1,
    'date'        => '2026-10-22',
    'status'      => 'paid'
], $userPlus);

assert(!empty($newTx['id']));

// Check updated balance (now 470000 + 100000 = 570000)
$afterCreate = $service->calculateMonthOverview($tenantPlus, '2026-10');
assert($afterCreate['stats']['balance'] === 570000);

// Check audit log
$stmtAudit = $pdo->prepare("SELECT * FROM financial_audit_logs WHERE transaction_id = :txid AND action = 'create'");
$stmtAudit->execute([':txid' => $newTx['id']]);
$audit = $stmtAudit->fetch();
assert(!empty($audit), "Audit log must be recorded on creation");

// 3. Test settlement of pending account with effective date in next month (November 2026)
// Settle t7 (Energia elétrica: 20000) with effective date 2026-11-02
$originalDue = $pdo->query("SELECT date FROM financial_transactions WHERE id='t7'")->fetchColumn();
$settled = $service->settleTransaction($tenantPlus, 't7', '2026-11-02', $userPlus);
assert($settled['status'] === 'paid');
assert($settled['date'] === $originalDue);
assert($settled['settled_at'] === '2026-11-02');

// Now October start/inc/out should NOT include t7, but November start balance will reflect October realized
$novOverview = $service->calculateMonthOverview($tenantPlus, '2026-11');
assert($novOverview['stats']['start'] === 570000, "November opening balance must match October closing balance");
assert($novOverview['stats']['out'] === 20000, "November paid expense must include settled t7");

// 4. Test Isolation between tenants
$tenantEssential = 'demo-store-2';
$novEssential = $service->calculateMonthOverview($tenantEssential, '2026-11');
assert($novEssential['stats']['balance'] === 0, "Tenant 2 must have 0 balance, isolated from Tenant 1");

// 5. Test PlanMiddleware logic
$middleware = new \VTD\Middlewares\PlanMiddleware();
$allowPlus = $middleware->checkAccess('plus');
assert($allowPlus === true);
$denyEssential = $middleware->checkAccess('essential');
assert($denyEssential === false);

echo "Finance module test passed (centavos, balance formulas, effective settlement, audit log & isolation verified)\n";
