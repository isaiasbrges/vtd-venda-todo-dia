<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/src/Autoloader.php';
\VTD\Autoloader::register();

// Reseta banco de teste
$dbPath = __DIR__ . '/../backend/storage/vtd_test.sqlite';
\VTD\Config\Config::set('db.driver', 'sqlite');
\VTD\Config\Config::set('db.database', $dbPath);
\VTD\Database\Connection::reset();

assert(class_exists('\VTD\Middlewares\AdminMiddleware'));
assert(class_exists('\VTD\Controllers\AdminController'));

$pdo = \VTD\Database\Connection::getInstance();
$mailer = new \VTD\Services\MailerService(['driver' => 'mock']);
$admin = new \VTD\Controllers\AdminController($mailer);

// 1. Test AdminMiddleware logic
$middleware = new \VTD\Middlewares\AdminMiddleware();
assert($middleware->checkAdminRole('admin') === true);
assert($middleware->checkAdminRole('user') === false);
assert($middleware->checkAdminRole(null) === false);

// 2. Test provision client with plan 'plus' for financial control access
$newClient = $admin->provisionClient([
    'name'     => 'Marcos Vinicius',
    'email'    => 'marcos@lojapro.com',
    'password' => 'Loja123456!',
    'store'    => 'Loja Pro VTD',
    'plan'     => 'plus'
]);

assert(!empty($newClient['user']['id']));
assert(!empty($newClient['tenant']['id']));
assert($newClient['tenant']['plan'] === 'plus', "Must have plus plan");
assert($newClient['tenant']['name'] === 'Loja Pro VTD');

// Verify default categories provisioned for new client
$cats = $pdo->prepare("SELECT COUNT(*) FROM financial_categories WHERE tenant_id = :tid");
$cats->execute([':tid' => $newClient['tenant']['id']]);
assert((int)$cats->fetchColumn() === 6, "Must provision 6 default categories for new client");

// Verify welcome email was dispatched with credentials
$sentMails = $mailer->getSentMails();
$lastMail = end($sentMails);
assert($lastMail['to'] === 'marcos@lojapro.com');
assert(str_contains($lastMail['body'], 'Loja123456!'));

// Verify new client can authenticate
$auth = new \VTD\Controllers\AuthController();
$login = $auth->authenticate('marcos@lojapro.com', 'Loja123456!');
assert($login !== null, "New client must be able to login");
assert($login['tenant']['plan'] === 'plus');

// 3. Test toggle client status to suspended
$admin->toggleClientStatus($newClient['tenant']['id']);
$loginSuspended = $auth->authenticate('marcos@lojapro.com', 'Loja123456!');
assert($loginSuspended['tenant']['status'] === 'suspended');

echo "Admin module test passed (client provisioning with financial dashboard & welcome email verified)\n";
