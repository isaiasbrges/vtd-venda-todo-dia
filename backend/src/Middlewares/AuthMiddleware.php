<?php
declare(strict_types=1);

namespace VTD\Middlewares;

use VTD\Core\Request;
use VTD\Core\Response;
use VTD\Security\SessionManager;
use VTD\Security\Csrf;
use VTD\Database\Connection;

class AuthMiddleware
{
    public function handle(Request $request): array
    {
        $userId = SessionManager::get('user_id');

        if (empty($userId)) {
            Response::error('UNAUTHORIZED', 'Sessão expirada ou não autenticada. Faça login novamente.', 401);
        }

        // Validação de CSRF em requisições de mutação
        $method = $request->getMethod();
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = $request->header('x-csrf-token') ?? $request->body('_csrf_token');
            // Ignora CSRF apenas em webhooks específicos
            if (!str_starts_with($request->getPath(), '/webhooks/')) {
                if (!Csrf::validateToken($token)) {
                    Response::error('CSRF_ERROR', 'Token de segurança CSRF inválido ou expirado. Recarregue a página.', 403);
                }
            }
        }

        // Carrega dados atualizados do usuário e do tenant
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("
            SELECT u.id as user_id, u.tenant_id, u.name as user_name, u.email as user_email, u.role as user_role,
                   t.name as store_name, t.slug as store_slug, t.plan as store_plan, t.initial_balance_cents, t.status as store_status
            FROM users u
            JOIN tenants t ON t.id = u.tenant_id
            WHERE u.id = :user_id
        ");
        $stmt->execute([':user_id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            SessionManager::destroy();
            Response::error('UNAUTHORIZED', 'Usuário não encontrado.', 401);
        }

        if ($user['store_status'] === 'suspended') {
            SessionManager::destroy();
            Response::error('ACCOUNT_SUSPENDED', 'O acesso desta loja está temporariamente suspenso. Entre em contato com o suporte.', 403);
        }

        return $user;
    }
}
