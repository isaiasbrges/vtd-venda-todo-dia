<?php
declare(strict_types=1);

namespace VTD\Security;

class Csrf
{
    private const SESSION_KEY = '_vtd_csrf_token';

    public static function generateToken(): string
    {
        $token = SessionManager::get(self::SESSION_KEY);
        if (empty($token)) {
            $token = bin2hex(random_bytes(32));
            SessionManager::set(self::SESSION_KEY, $token);
        }
        return $token;
    }

    public static function getToken(): string
    {
        return self::generateToken();
    }

    public static function validateToken(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $sessionToken = SessionManager::get(self::SESSION_KEY);
        if (empty($sessionToken)) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }
}
