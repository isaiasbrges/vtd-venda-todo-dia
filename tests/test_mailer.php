<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/src/Autoloader.php';
\VTD\Autoloader::register();

// Reseta banco de teste
$dbPath = __DIR__ . '/../backend/storage/vtd_test.sqlite';
\VTD\Config\Config::set('db.driver', 'sqlite');
\VTD\Config\Config::set('db.database', $dbPath);
\VTD\Database\Connection::reset();

assert(class_exists('\VTD\Services\MailerService'));

$mailer = new \VTD\Services\MailerService(['driver' => 'mock']);

// Test Welcome Email
$sentWelcome = $mailer->sendWelcomeCredentials('cliente@loja.com', 'Carlos Silva', 'MinhaSenhaForte123', 'plus');
assert($sentWelcome === true, "Welcome email must return true");

$sentMails = $mailer->getSentMails();
assert(count($sentMails) === 1, "Exactly one email must be recorded");
assert($sentMails[0]['to'] === 'cliente@loja.com');
assert(str_contains($sentMails[0]['subject'], 'Venda Todo Dia'));
assert(str_contains($sentMails[0]['body'], 'MinhaSenhaForte123'));
assert(str_contains(mb_strtolower($sentMails[0]['body']), 'kit + financeiro'));

// Test Password Reset Flow in AuthController
$auth = new \VTD\Controllers\AuthController();
$resetToken = $auth->createPasswordResetToken('demo@exemplo.com');
assert(!empty($resetToken), "Reset token must be generated");

$sentReset = $mailer->sendPasswordReset('demo@exemplo.com', 'João Silva', $resetToken);
assert($sentReset === true);

// Verify reset token works to change password
$resetSuccess = $auth->applyPasswordReset($resetToken, 'NovaSenha456');
assert($resetSuccess === true, "Password reset must succeed with valid token");

// Verify can login with new password
$reLogin = $auth->authenticate('demo@exemplo.com', 'NovaSenha456');
assert($reLogin !== null, "Must be able to authenticate with new password");

// Verify cannot re-use old or used reset token
$reUse = $auth->applyPasswordReset($resetToken, 'OutraSenha');
assert($reUse === false, "Used token must be rejected");

echo "MailerService and password reset test passed\n";
