<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/src/Autoloader.php';
\VTD\Autoloader::register();

echo "Configurando banco de dados local...\n";
$pdo = \VTD\Database\Connection::getInstance();
$migrator = new \VTD\Database\Migrator($pdo);
$migrator->runMigrations();
$migrator->runSeeds();

$suppliersCount = $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
$usersCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

echo "Sucesso! Fornecedores semeados: {$suppliersCount} | Usuários: {$usersCount}\n";
