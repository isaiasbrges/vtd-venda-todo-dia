<?php
declare(strict_types=1);
require_once __DIR__.'/../backend/src/Autoloader.php';
\VTD\Autoloader::register();
\VTD\Core\Response::$stopOnExit=false;
set_error_handler(static function(int $level,string $message):never {throw new ErrorException($message,0,$level);});
ob_start();
\VTD\Core\Response::streamCsv('teste.csv',['Descrição','Valor'],[['Compra; "produto"','33,34']]);
$csv=ob_get_clean();
restore_error_handler();
assert(str_starts_with($csv,"\xEF\xBB\xBF"));
assert(!str_contains($csv,'<br'));
$lines=explode("\n",trim(substr($csv,3)));
assert(str_getcsv($lines[0],';','"','')===['Descrição','Valor']);
assert(str_getcsv($lines[1],';','"','')===['Compra; "produto"','33,34']);
echo "CSV: BOM, cabeçalho e aspas válidos sem avisos PHP OK\n";
