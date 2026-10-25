<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/src/Autoloader.php';
\VTD\Autoloader::register();

assert(class_exists('\VTD\Database\Connection'), 'Class Connection must exist');
assert(class_exists('\VTD\Core\Router'), 'Class Router must exist');
assert(class_exists('\VTD\Core\Request'), 'Class Request must exist');
assert(class_exists('\VTD\Core\Response'), 'Class Response must exist');

$pdo = \VTD\Database\Connection::getInstance();
assert($pdo instanceof \PDO, 'PDO instance must be returned');

$router = new \VTD\Core\Router();
$matched = false;
$router->get('/health', function($req) use (&$matched) {
    $matched = true;
});

// Simulate dispatch
$req = new \VTD\Core\Request('GET', '/health', [], []);
$router->dispatchRequest($req);
assert($matched === true, 'Router must match GET /health');

echo "Foundation test passed\n";
