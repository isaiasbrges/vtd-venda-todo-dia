<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/src/Autoloader.php';
\VTD\Autoloader::register();
use VTD\Database\Connection;
use VTD\Database\Migrator;
use VTD\Services\FinanceCardsService;
if(!in_array('--apply',$argv,true)&&!in_array('--check',$argv,true)){
    fwrite(STDERR,"Uso: php backend/migrate.php --check | --apply\nUse --apply somente depois de validar a copia e o backup. Nenhum seed sera executado.\n");
    exit(2);
}
$pdo=Connection::getInstance();
if(in_array('--apply',$argv,true))(new Migrator($pdo))->runMigrations();
echo FinanceCardsService::ready($pdo) ? "Estrutura de cartoes disponivel. Nenhum seed executado.\n" : "Estrutura de cartoes ainda nao instalada.\n";
