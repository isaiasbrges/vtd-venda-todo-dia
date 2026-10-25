<?php
declare(strict_types=1);

namespace VTD\Security;

use PDO;
use VTD\Database\Connection;

class RateLimiter
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::getInstance();
    }

    public function tooManyAttempts(string $key, int $maxAttempts = 5, int $decayMinutes = 15): bool
    {
        $stmt = $this->pdo->prepare("SELECT attempts, last_attempt_at FROM rate_limits WHERE action_key = :key");
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch();

        if (!$row) {
            return false;
        }

        $lastAttempt = strtotime($row['last_attempt_at']);
        if (time() - $lastAttempt > ($decayMinutes * 60)) {
            $this->clear($key);
            return false;
        }

        return (int)$row['attempts'] >= $maxAttempts;
    }

    public function hit(string $key): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO rate_limits (action_key, attempts, last_attempt_at)
            VALUES (:key, 1, CURRENT_TIMESTAMP)
            ON CONFLICT(action_key) DO UPDATE SET
                attempts = attempts + 1,
                last_attempt_at = CURRENT_TIMESTAMP
        ");

        // Fallback para MySQL
        if (\VTD\Config\Config::get('db.driver') === 'mysql') {
            $stmt = $this->pdo->prepare("
                INSERT INTO rate_limits (action_key, attempts, last_attempt_at)
                VALUES (:key, 1, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                    attempts = attempts + 1,
                    last_attempt_at = CURRENT_TIMESTAMP
            ");
        }

        $stmt->execute([':key' => $key]);
    }

    public function clear(string $key): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM rate_limits WHERE action_key = :key");
        $stmt->execute([':key' => $key]);
    }
}
