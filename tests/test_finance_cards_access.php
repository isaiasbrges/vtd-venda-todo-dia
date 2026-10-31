<?php
declare(strict_types=1);
require_once __DIR__.'/../backend/src/Autoloader.php';
\VTD\Autoloader::register();
use VTD\Config\Config;
use VTD\Database\Connection;
use VTD\Database\Migrator;
use VTD\Core\Request;
use VTD\Controllers\FinanceCardsController;
use VTD\Security\SessionManager;
if(isset($argv[1])) {
    Config::set('db.driver','sqlite');Config::set('db.database',':memory:');Connection::reset();
    $p=Connection::getInstance();(new Migrator($p))->runMigrations();
    $plan=$argv[1]==='essential'?'essential':'plus';
    $p->prepare('INSERT INTO tenants (id,name,slug,plan) VALUES (?,?,?,?)')->execute(['access-test','Test','access-test',$plan]);
    $p->prepare('INSERT INTO users (id,tenant_id,name,email,password_hash) VALUES (?,?,?,?,?)')->execute(['access-user','access-test','Test','access@test.local','unused']);
    SessionManager::start();if($argv[1]!=='anonymous')SessionManager::set('user_id','access-user');
    $c=new FinanceCardsController();
    if($argv[1]==='csrf') $c->create(new Request('POST','/finance/cards',[],[]));
    else $c->cards(new Request('GET','/finance/cards'));
    exit;
}
foreach(['anonymous'=>'UNAUTHORIZED','essential'=>'PLAN_UPGRADE_REQUIRED','csrf'=>'CSRF_ERROR','plus'=>null] as $mode=>$expected) {
    $command=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($mode);
    $out=[];$code=0;exec($command,$out,$code);assert($code===0);
    $data=json_decode(implode("\n",$out),true,512,JSON_THROW_ON_ERROR);
    if($expected)assert(($data['error']['code']??null)===$expected);
    else assert($data['success']===true && $data['data']===[]);
}
echo "Acesso: sessao, plano Essential, CSRF e Plus OK\n";
