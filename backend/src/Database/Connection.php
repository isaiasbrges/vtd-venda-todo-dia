<?php
declare(strict_types=1);

namespace VTD\Database;

use PDO;
use PDOException;
use VTD\Config\Config;

class Connection
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::connect();
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private static function connect(): void
    {
        $driver = Config::get('db.driver', 'mysql');

        try {
            if ($driver === 'sqlite') {
                $dbPath = Config::get('db.database');
                $dsn = "sqlite:{$dbPath}";
                $pdo = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                $pdo->exec('PRAGMA foreign_keys = ON;');
            } else {
                $host = Config::get('db.host', 'localhost');
                $port = Config::get('db.port', 3306);
                $database = Config::get('db.database');
                $charset = Config::get('db.charset', 'utf8mb4');
                $username = Config::get('db.username', 'root');
                $password = Config::get('db.password', '');

                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
                $pdo = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci"
                ]);
            }

            self::$instance = $pdo;
        } catch (PDOException $e) {
            throw new PDOException("Erro ao conectar ao banco de dados: " . $e->getMessage(), (int)$e->getCode());
        }
    }
}
