<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/src/Autoloader.php';
\VTD\Autoloader::register();

// Reseta banco de teste
$dbPath = __DIR__ . '/../backend/storage/vtd_test.sqlite';
\VTD\Config\Config::set('db.driver', 'sqlite');
\VTD\Config\Config::set('db.database', $dbPath);
\VTD\Database\Connection::reset();

assert(class_exists('\VTD\Controllers\SupplierController'));
assert(class_exists('\VTD\Controllers\CalendarController'));
assert(class_exists('\VTD\Controllers\GuideController'));
assert(class_exists('\VTD\Controllers\UserContentController'));

// 1. Test SupplierController search & filter
$suppCtrl = new \VTD\Controllers\SupplierController();
$listResult = $suppCtrl->querySuppliers(['search' => 'ABP', 'page' => 1, 'limit' => 12]);
assert($listResult['total'] >= 1, "Should find at least 1 supplier for 'ABP'");
assert($listResult['suppliers'][0]['name'] === 'ABP IMPORT');

// Test segment filter
$educativos = $suppCtrl->querySuppliers(['segment' => 'Brinquedos', 'page' => 1, 'limit' => 500]);
assert($educativos['total'] > 100, "Should find many toys suppliers");

// 2. Test UserContentController (Favorites)
$userCtrl = new \VTD\Controllers\UserContentController();
$userId = 'user-1';

// Add favorite
$userCtrl->toggleFavorite($userId, '1', true);
$favs = $userCtrl->getUserFavorites($userId);
assert(in_array('1', $favs), "Supplier 1 must be in favorites");

// Remove favorite
$userCtrl->toggleFavorite($userId, '1', false);
$favsAfter = $userCtrl->getUserFavorites($userId);
assert(!in_array('1', $favsAfter), "Supplier 1 must be removed from favorites");

// 3. Test Checklists
$userCtrl->saveChecklist($userId, 'm-10-1', true);
$checks = $userCtrl->getUserChecklists($userId);
assert(isset($checks['m-10-1']) && $checks['m-10-1'] === true);

// 4. Test Guide Progress & Notes
$userCtrl->saveChapterProgress($userId, 1, true);
$progress = $userCtrl->getUserReadingProgress($userId);
assert(in_array(1, $progress), "Chapter 1 must be marked completed");

$userCtrl->saveChapterNote($userId, 1, 'Minha anotação importante do capítulo 1');
$notes = $userCtrl->getUserNotes($userId);
assert(($notes[1] ?? '') === 'Minha anotação importante do capítulo 1');

echo "Content and user interactions test passed\n";
