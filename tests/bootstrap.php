<?php
// CLI test processes always start on a disposable database, never runtime config.
require_once __DIR__.'/../backend/src/Autoloader.php';
\VTD\Autoloader::register();
\VTD\Config\Config::set('db.driver','sqlite');
\VTD\Config\Config::set('db.database',sys_get_temp_dir().'/vtd-suite-'.getmypid().'.sqlite');
\VTD\Config\Config::set('mail.driver','mock');
\VTD\Config\Config::set('app_env','testing');
