<?php
declare(strict_types=1);

namespace VTD\Middlewares;

use VTD\Core\Request;
use VTD\Core\Response;
use VTD\Security\SessionManager;

class AdminMiddleware
{
    public function checkAdminRole(?string $role): bool
    {
        return $role === 'admin';
    }

    public function handle(Request $request): array
    {
        $authMiddleware = new AuthMiddleware();
        $account = $authMiddleware->handle($request);

        $role = $account['user_role'] ?? '';
        if (!$this->checkAdminRole($role)) {
            Response::error('FORBIDDEN', 'Acesso restrito a administradores da plataforma.', 403);
            throw new \RuntimeException('FORBIDDEN');
        }

        return $account;
    }
}
