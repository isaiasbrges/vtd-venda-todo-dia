<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/src/Autoloader.php';
\VTD\Autoloader::register();

// Reseta banco de teste
$dbPath = __DIR__ . '/../backend/storage/vtd_test.sqlite';
\VTD\Config\Config::set('db.driver', 'sqlite');
\VTD\Config\Config::set('db.database', $dbPath);
\VTD\Database\Connection::reset();

assert(class_exists('\VTD\Controllers\GreennWebhookController'));

$mailer = new \VTD\Services\MailerService(['driver' => 'mock']);
$webhook = new \VTD\Controllers\GreennWebhookController($mailer);

// 1. Test secret validation
$invalidReq = new \VTD\Core\Request('POST', '/webhooks/greenn', ['token' => 'wrong-secret'], []);
assert($webhook->validateSecret($invalidReq) === false, "Invalid token must be rejected");

$validReq = new \VTD\Core\Request('POST', '/webhooks/greenn', ['token' => 'test-secret'], []);
assert($webhook->validateSecret($validReq) === true, "Valid token must be accepted");

// 2. Test Sale Approved for new buyer (Kit + Financeiro)
$payload = [
    'event'       => 'order_paid',
    'id'          => 'greenn_tx_1001',
    'product_id'  => 'kit-plus',
    'buyer_name'  => 'Lucas Oliveira',
    'buyer_email' => 'lucas@comprador.com'
];

$res = $webhook->processPayload($payload);
assert($res['success'] === true);
assert($res['action'] === 'client_created');

// Verify buyer can login and has plus plan
$auth = new \VTD\Controllers\AuthController();
$pdo = \VTD\Database\Connection::getInstance();
$buyerUser = $pdo->prepare("SELECT * FROM users WHERE email = 'lucas@comprador.com'");
$buyerUser->execute();
$u = $buyerUser->fetch();
assert(!empty($u));

$buyerTenant = $pdo->prepare("SELECT * FROM tenants WHERE id = :tid");
$buyerTenant->execute([':tid' => $u['tenant_id']]);
$t = $buyerTenant->fetch();
assert($t['plan'] === 'plus', "Must have plus plan");

// Verify welcome email was sent to buyer
$sentMails = $mailer->getSentMails();
$lastMail = end($sentMails);
assert($lastMail['to'] === 'lucas@comprador.com');
assert(str_contains(mb_strtolower($lastMail['body']), 'kit + financeiro'));

// 3. Test Idempotency (same event re-sent by Greenn)
$resDuplicate = $webhook->processPayload($payload);
assert($resDuplicate['success'] === true);
assert($resDuplicate['idempotent'] === true, "Duplicate event must be recognized as idempotent");

// 4. Test Refund event
$refundPayload = [
    'event'       => 'order_refunded',
    'id'          => 'greenn_tx_1001_ref',
    'buyer_email' => 'lucas@comprador.com'
];
$resRefund = $webhook->processPayload($refundPayload);
assert($resRefund['success'] === true);
assert($resRefund['action'] === 'client_suspended');

$buyerTenantAfter = $pdo->prepare("SELECT status FROM tenants WHERE id = :tid");
$buyerTenantAfter->execute([':tid' => $u['tenant_id']]);
assert($buyerTenantAfter->fetchColumn() === 'suspended', "Tenant must be suspended on refund");

echo "Greenn webhook test passed (idempotency, buyer provisioning & refund suspended verified)\n";
