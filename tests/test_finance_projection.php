<?php
declare(strict_types=1);
require_once __DIR__.'/../backend/src/Autoloader.php';
\VTD\Autoloader::register();
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE tenants (id TEXT, initial_balance_cents INTEGER);
CREATE TABLE financial_categories (id INTEGER, name TEXT);
CREATE TABLE financial_transactions (id INTEGER, tenant_id TEXT, type TEXT, value_cents INTEGER, category_id INTEGER, date TEXT, settled_at TEXT, status TEXT);
INSERT INTO tenants VALUES ("a",10000),("b",0);
INSERT INTO financial_categories VALUES (1,"Estoque");');
$insert = $pdo->prepare('INSERT INTO financial_transactions VALUES (?,?,?,?,?,?,?,?)');
foreach ([
 [1,'a','income',20000,1,'2026-11-02',null,'pending'],
 [2,'a','expense',5000,1,'2026-11-10',null,'pending'],
 [3,'a','income',30000,1,'2026-10-01','2026-11-15','paid'],
 [4,'a','expense',2000,1,'2026-11-01','2026-11-22','paid'],
 [5,'a','expense',9000,1,'2026-11-01','2026-12-01','paid'],
 [6,'a','expense',8000,1,'2026-12-01',null,'pending'],
 [7,'b','expense',999999,1,'2026-11-02',null,'pending']
] as $row) $insert->execute($row);
$service = new \VTD\Services\FinanceService($pdo);
$o = $service->calculateMonthOverview('a','2026-11');
assert($o['stats']['balance'] === 38000);
assert($o['stats']['projected'] === 53000);
assert($o['projection_chart']['income'] === [20000,0,30000,0,0]);
assert($o['projection_chart']['expense'] === [0,5000,0,2000,0]);
assert($o['projection_categories'] === [['name'=>'Estoque','value_cents'=>7000]]);
assert(array_sum($o['chart']['income']) === 30000);
assert(array_sum($o['chart']['expense']) === 2000);
assert($o['stats']['start'] + array_sum($o['projection_chart']['income']) - array_sum($o['projection_chart']['expense']) === $o['stats']['projected']);
$pdo->exec("UPDATE financial_transactions SET status='paid', settled_at='2026-11-03' WHERE id=1");
$settled = $service->calculateMonthOverview('a','2026-11');
assert($settled['projection_chart'] === $o['projection_chart']);
assert($settled['stats']['balance'] === 58000);
$pdo->exec("UPDATE financial_transactions SET settled_at='2026-12-03' WHERE id=1");
$later = $service->calculateMonthOverview('a','2026-11');
assert(array_sum($later['projection_chart']['income']) === 30000);
assert($service->calculateMonthOverview('a','2027-02')['projection_categories'] === []);
echo "PASS: pending, paid, settlement dates, no duplicates, month boundaries, tenant isolation, categories and forecast reconciliation\n";
