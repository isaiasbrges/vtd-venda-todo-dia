<?php
declare(strict_types=1);
require_once __DIR__.'/../backend/src/Autoloader.php';
\VTD\Autoloader::register();
use VTD\Database\Migrator;
use VTD\Services\FinanceCardsService;
use VTD\Services\FinanceService;
if(getenv('VTD_CARDS_TEST_MYSQL')==='1') {
    if(getenv('DB_DATABASE')!=='vtd_test') throw new RuntimeException('MySQL cards tests require the disposable vtd_test database.');
    \VTD\Config\Config::set('db.driver','mysql');
    $pdo=\VTD\Database\Connection::getInstance();
} else {
    $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA foreign_keys=ON');
}
$m=new Migrator($pdo); $m->runMigrations(); $m->runMigrations();
assert(FinanceCardsService::ready($pdo));
$pdo->exec("DELETE FROM schema_migrations WHERE version='004_finance_cards.sql'");
assert(!FinanceCardsService::ready($pdo)); // incomplete migration stays unavailable
$m->runMigrations();assert(FinanceCardsService::ready($pdo));
foreach(['a','b'] as $t) { $pdo->prepare('INSERT INTO tenants (id,name,slug,plan) VALUES (?,?,?,?)')->execute([$t,$t,$t,'plus']); $pdo->prepare('INSERT INTO users (id,tenant_id,name,email,password_hash) VALUES (?,?,?,?,?)')->execute([$t,$t,$t,$t.'@test.local','unused']); $pdo->prepare('INSERT INTO financial_categories (tenant_id,name) VALUES (?,?)')->execute([$t,'Mercadorias']); }
// Empty IDs from legacy create endpoints must still generate distinct records.
$ledger=new FinanceService($pdo);
$cat=(int)$pdo->query("SELECT id FROM financial_categories WHERE tenant_id='a'")->fetchColumn();
$legacy=['id'=>'','description'=>'Receita','type'=>'income','value_cents'=>500,'category_id'=>$cat,'date'=>'2026-10-01'];
$l1=$ledger->createTransaction('a',$legacy,'a');$l2=$ledger->createTransaction('a',$legacy,'a');assert($l1['id']!==$l2['id'] && $l1['id']!=='');
$s=new FinanceCardsService($pdo);
$card=$s->saveCard('a',['bank_id'=>'other','bank_name'=>'Banco de teste','nickname'=>'Loja','brand'=>'visa','last_four'=>'1234','limit_cents'=>100000,'closing_day'=>25,'due_day'=>5]);
assert(count($s->cards('a'))===1); assert($s->cards('b')===[]);
$purchase=['card_id'=>$card['id'],'description'=>'Estoque','value_cents'=>10000,'category_id'=>$cat,'purchase_date'=>'2026-10-25','installments'=>3,'operation_key'=>'purchase-test-1'];
$x=$s->purchase('a',$purchase,'a'); $again=$s->purchase('a',$purchase,'a'); assert($x['id']===$again['id']);
$rows=$pdo->query("SELECT value_cents,date FROM financial_transactions WHERE tenant_id='a' AND type='expense' ORDER BY date")->fetchAll();
assert(array_column($rows,'value_cents')===[3334,3333,3333]); assert(array_column($rows,'date')===['2026-11-05','2026-12-05','2027-01-05']);
assert((new FinanceService($pdo))->calculateMonthOverview('a','2026-11')['stats']['out']===0);
$inv=$s->invoices('a',$card['id'])[0];
$denied=false; try{$s->invoice('b',$inv['id']);}catch(InvalidArgumentException $e){$denied=true;} assert($denied);
$tx=$pdo->query("SELECT id FROM financial_transactions WHERE tenant_id='a' AND type='expense' ORDER BY date")->fetchColumn();
$denied=false; try{(new FinanceService($pdo))->settleTransaction('a',$tx,'2026-11-05','a');}catch(InvalidArgumentException $e){$denied=true;} assert($denied);
$s->settle('a',$inv['id'],'2026-11-06','a','payment-test-1'); $s->settle('a',$inv['id'],'2026-11-06','a','payment-test-1');
assert((new FinanceService($pdo))->calculateMonthOverview('a','2026-11')['stats']['out']===3334);
assert((int)$pdo->query("SELECT COUNT(*) FROM financial_card_invoice_payments WHERE tenant_id='a'")->fetchColumn()===1);
assert($s->cards('a')[0]['committed_cents']===6666);
assert(FinanceCardsService::cycle('2028-02-29',31,5)['due_date']==='2028-03-05');
assert(FinanceCardsService::cycle('2026-12-26',25,5)['due_date']==='2027-02-05');
assert(FinanceCardsService::cycle('2026-10-25',25,30)['due_date']==='2026-10-30');
$pdo->exec('DELETE FROM schema_migrations'); $m->runMigrations(); assert((int)$pdo->query("SELECT SUM(value_cents) FROM financial_transactions WHERE tenant_id='a'")->fetchColumn()===11000);
// Reject editing/deleting paid purchases, wrong categories, reused keys and cancelled cards.
$denied=false;try{$s->cancel('a',$x['id'],'a');}catch(InvalidArgumentException $e){$denied=true;}assert($denied);
$denied=false;try{$s->purchase('a',array_merge($purchase,['value_cents'=>10001]),'a');}catch(InvalidArgumentException $e){$denied=true;}assert($denied);
$denied=false;try{$s->purchase('b',$purchase,'b');}catch(InvalidArgumentException $e){$denied=true;}assert($denied);
$new=array_merge($purchase,['operation_key'=>'purchase-test-2','value_cents'=>501,'installments'=>2,'purchase_date'=>'2027-02-01']);
$edit=$s->purchase('a',$new,'a');
$s->purchase('a',array_merge($new,['value_cents'=>503]),'a',$edit['id']);
$s->cancel('a',$edit['id'],'a');
assert((int)$pdo->query("SELECT SUM(value_cents) FROM financial_transactions WHERE tenant_id='a'")->fetchColumn()===11000);
$s->archive('a',$card['id']);
$denied=false;try{$s->purchase('a',array_merge($new,['operation_key'=>'purchase-test-3']),'a');}catch(InvalidArgumentException $e){$denied=true;}assert($denied);
$catalog=FinanceCardsService::banks();usort($catalog,fn($a,$b)=>mb_strlen($b['name'])<=>mb_strlen($a['name']));
$official=$s->saveCard('a',['bank_id'=>$catalog[0]['id'],'nickname'=>'Instituição com nome longo','brand'=>'other','limit_cents'=>0,'closing_day'=>31,'due_day'=>5]);
assert($official['bank_name']===$catalog[0]['name']);
echo "Cartoes: migracao repetida, isolamento, ciclos, parcelas, idempotencia e quitacao sem duplicacao OK\n";
