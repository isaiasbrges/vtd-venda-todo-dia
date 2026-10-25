<?php
declare(strict_types=1);

namespace VTD\Controllers;

use VTD\Core\Request;
use VTD\Core\Response;
use VTD\Database\Connection;
use VTD\Security\SessionManager;
use VTD\Security\Csrf;
use VTD\Security\RateLimiter;
use VTD\Middlewares\AuthMiddleware;

class AuthController
{
    private RateLimiter $rateLimiter;

    public function __construct(?RateLimiter $rateLimiter = null)
    {
        $this->rateLimiter = $rateLimiter ?? new RateLimiter();
    }

    public function authenticate(string $email, string $password): ?array
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("
            SELECT u.*, t.name as store_name, t.slug as store_slug, t.plan as store_plan, t.initial_balance_cents, t.status as store_status
            FROM users u
            JOIN tenants t ON t.id = u.tenant_id
            WHERE u.email = :email
        ");
        $stmt->execute([':email' => trim(strtolower($email))]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        if (!password_verify($password, $row['password_hash'])) {
            return null;
        }

        return [
            'user' => [
                'id'    => $row['id'],
                'name'  => $row['name'],
                'email' => $row['email'],
                'role'  => $row['role']
            ],
            'tenant' => [
                'id'                    => $row['tenant_id'],
                'name'                  => $row['store_name'],
                'slug'                  => $row['store_slug'],
                'plan'                  => $row['store_plan'],
                'initial_balance_cents' => (int)$row['initial_balance_cents'],
                'status'                => $row['store_status']
            ]
        ];
    }

    public function login(Request $request): void
    {
        $email = trim((string)$request->body('email', ''));
        $password = (string)$request->body('password', '');

        if (empty($email) || empty($password)) {
            Response::error('VALIDATION_ERROR', 'Informe seu e-mail e sua senha para entrar.', 422);
        }

        $rateKey = 'login:' . $request->getIp() . ':' . strtolower($email);
        if ($this->rateLimiter->tooManyAttempts($rateKey, 5, 15)) {
            Response::error('TOO_MANY_ATTEMPTS', 'Muitas tentativas com dados incorretos. Por favor, aguarde 15 minutos.', 429);
        }

        $authData = $this->authenticate($email, $password);

        if (!$authData) {
            $this->rateLimiter->hit($rateKey);
            Response::error('INVALID_CREDENTIALS', 'E-mail ou senha incorretos.', 401);
        }

        if ($authData['tenant']['status'] === 'suspended') {
            Response::error('ACCOUNT_SUSPENDED', 'O acesso desta loja está suspenso. Entre em contato com o suporte.', 403);
        }

        // Sucesso
        $this->rateLimiter->clear($rateKey);
        SessionManager::regenerate();
        SessionManager::set('user_id', $authData['user']['id']);
        SessionManager::set('tenant_id', $authData['tenant']['id']);
        SessionManager::set('user_role', $authData['user']['role']);

        $csrfToken = Csrf::generateToken();

        Response::json([
            'user'       => $authData['user'],
            'tenant'     => $authData['tenant'],
            'csrf_token' => $csrfToken
        ]);
    }

    public function me(Request $request): void
    {
        $authMiddleware = new AuthMiddleware();
        $account = $authMiddleware->handle($request);

        $csrfToken = Csrf::generateToken();

        Response::json([
            'user' => [
                'id'    => $account['user_id'],
                'name'  => $account['user_name'],
                'email' => $account['user_email'],
                'role'  => $account['user_role']
            ],
            'tenant' => [
                'id'                    => $account['tenant_id'],
                'name'                  => $account['store_name'],
                'slug'                  => $account['store_slug'],
                'plan'                  => $account['store_plan'],
                'initial_balance_cents' => (int)$account['initial_balance_cents'],
                'status'                => $account['store_status']
            ],
            'csrf_token' => $csrfToken
        ]);
    }

    public function logout(Request $request): void
    {
        SessionManager::destroy();
        Response::json(['message' => 'Sessão encerrada com sucesso.']);
    }

    public function updateProfile(Request $request): void
    {
        $authMiddleware = new AuthMiddleware();
        $account = $authMiddleware->handle($request);

        $name = trim((string)$request->body('name', $account['user_name']));
        $store = trim((string)$request->body('store', $account['store_name']));
        $email = trim((string)$request->body('email', $account['user_email']));
        $initialBalanceCents = $request->body('initial_balance_cents');

        if (empty($name) || empty($store) || empty($email)) {
            Response::error('VALIDATION_ERROR', 'Nome, loja e e-mail são obrigatórios.', 422);
        }

        $pdo = Connection::getInstance();
        $pdo->beginTransaction();

        try {
            // Atualiza usuário
            $stmtUser = $pdo->prepare("UPDATE users SET name = :name, email = :email WHERE id = :id");
            $stmtUser->execute([':name' => $name, ':email' => strtolower($email), ':id' => $account['user_id']]);

            // Atualiza loja
            if ($initialBalanceCents !== null) {
                $stmtTenant = $pdo->prepare("
                    UPDATE tenants
                    SET name = :store, initial_balance_cents = :init_balance
                    WHERE id = :tenant_id
                ");
                $stmtTenant->execute([
                    ':store' => $store,
                    ':init_balance' => (int)$initialBalanceCents,
                    ':tenant_id' => $account['tenant_id']
                ]);
            } else {
                $stmtTenant = $pdo->prepare("UPDATE tenants SET name = :store WHERE id = :tenant_id");
                $stmtTenant->execute([':store' => $store, ':tenant_id' => $account['tenant_id']]);
            }

            $pdo->commit();

            Response::json(['message' => 'Preferências salvas com sucesso.']);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::error('UPDATE_ERROR', 'Não foi possível atualizar o perfil: ' . $e->getMessage(), 500);
        }
    }
}
