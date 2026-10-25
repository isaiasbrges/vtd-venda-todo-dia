<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/src/Autoloader.php';
\VTD\Autoloader::register();

// Reseta banco de teste
$dbPath = __DIR__ . '/../backend/storage/vtd_test.sqlite';
\VTD\Config\Config::set('db.driver', 'sqlite');
\VTD\Config\Config::set('db.database', $dbPath);
\VTD\Database\Connection::reset();

assert(class_exists('\VTD\Security\SessionManager'));
assert(class_exists('\VTD\Security\Csrf'));
assert(class_exists('\VTD\Security\RateLimiter'));
assert(class_exists('\VTD\Middlewares\AuthMiddleware'));
assert(class_exists('\VTD\Controllers\AuthController'));

// Test RateLimiter
$rateLimiter = new \VTD\Security\RateLimiter();
$key = 'test-ip-action';
$rateLimiter->clear($key);

for ($i = 1; $i <= 5; $i++) {
    assert($rateLimiter->tooManyAttempts($key, 5) === false, "Attempt $i should not exceed limit");
    $rateLimiter->hit($key);
}
assert($rateLimiter->tooManyAttempts($key, 5) === true, "6th attempt must be blocked");
$rateLimiter->clear($key);
assert($rateLimiter->tooManyAttempts($key, 5) === false, "Must be unblocked after clear");

// Test CSRF
$token = \VTD\Security\Csrf::generateToken();
assert(!empty($token), "CSRF token must not be empty");
assert(\VTD\Security\Csrf::validateToken($token) === true, "CSRF token must be valid");
assert(\VTD\Security\Csrf::validateToken('invalid-token') === false, "Invalid CSRF token must fail");

// Test AuthController logic (without exit in test)
$auth = new \VTD\Controllers\AuthController();
$loginSuccess = $auth->authenticate('demo@exemplo.com', '123456');
assert(!empty($loginSuccess), "Valid credentials must return user & tenant");
assert($loginSuccess['user']['email'] === 'demo@exemplo.com');
assert($loginSuccess['tenant']['plan'] === 'plus');

$loginFail = $auth->authenticate('demo@exemplo.com', 'wrongpassword');
assert($loginFail === null, "Wrong password must return null");

$loginNotFound = $auth->authenticate('naoexiste@exemplo.com', '123456');
assert($loginNotFound === null, "Nonexistent user must return null");

echo "Auth test passed\n";
