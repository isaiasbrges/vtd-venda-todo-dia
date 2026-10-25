<?php
declare(strict_types=1);

namespace VTD\Config;

class Config
{
    private static ?array $settings = null;

    public static function get(?string $key = null, mixed $default = null): mixed
    {
        if (self::$settings === null) {
            self::load();
        }

        if ($key === null) {
            return self::$settings;
        }

        $segments = explode('.', $key);
        $current = self::$settings;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    public static function set(string $key, mixed $value): void
    {
        if (self::$settings === null) {
            self::load();
        }

        $segments = explode('.', $key);
        $current = &self::$settings;

        foreach ($segments as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }

        $current = $value;
    }

    private static function load(): void
    {
        $envFile = __DIR__ . '/.env.php';
        $userConfig = file_exists($envFile) ? require $envFile : [];

        // Defaults
        $storageDir = dirname(__DIR__) . '/storage';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0755, true);
        }

        $defaults = [
            'app_env' => getenv('APP_ENV') ?: 'development',
            'app_debug' => getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1',
            'app_url' => getenv('APP_URL') ?: 'http://localhost:8080',
            'app_secret' => getenv('APP_SECRET') ?: 'vtd-local-dev-secret-key-32chars!!',
            'db' => [
                'driver'   => getenv('DB_DRIVER') ?: 'sqlite',
                'host'     => getenv('DB_HOST') ?: 'localhost',
                'port'     => (int)(getenv('DB_PORT') ?: 3306),
                'database' => getenv('DB_DATABASE') ?: ($storageDir . '/vtd.sqlite'),
                'username' => getenv('DB_USERNAME') ?: 'root',
                'password' => getenv('DB_PASSWORD') ?: '',
                'charset'  => 'utf8mb4'
            ],
            'mail' => [
                'driver'       => 'mock',
                'host'         => 'smtp.hostinger.com',
                'port'         => 465,
                'encryption'   => 'ssl',
                'username'     => '',
                'password'     => '',
                'from_name'    => 'VTD · Venda Todo Dia',
                'from_address' => 'nao-responda@vtd.local'
            ],
            'greenn' => [
                'webhook_secret' => 'test-secret',
                'essential_product_id' => 'kit-essencial',
                'plus_product_id' => 'kit-plus'
            ]
        ];

        self::$settings = array_replace_recursive($defaults, $userConfig);
    }
}
