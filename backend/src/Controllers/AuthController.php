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

    public function createPasswordResetToken(string $email): ?string
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = :email");
        $stmt->execute([':email' => trim(strtolower($email))]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hora

        $stmtInsert = $pdo->prepare("
            INSERT INTO password_resets (email, token_hash, expires_at)
            VALUES (:email, :token_hash, :expires_at)
        ");
        $stmtInsert->execute([
            ':email'      => trim(strtolower($email)),
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt
        ]);

        return $token;
    }

    public function applyPasswordReset(string $token, string $newPassword): bool
    {
        if (strlen($newPassword) < 6) {
            return false;
        }

        $pdo = Connection::getInstance();
        $tokenHash = hash('sha256', $token);

        $stmt = $pdo->prepare("
            SELECT id, email, expires_at, used_at
            FROM password_resets
            WHERE token_hash = :token_hash
        ");
        $stmt->execute([':token_hash' => $tokenHash]);
        $reset = $stmt->fetch();

        if (!$reset || !empty($reset['used_at'])) {
            return false;
        }

        if (strtotime($reset['expires_at']) < time()) {
            return false;
        }

        $pdo->beginTransaction();
        try {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmtUser = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE email = :email");
            $stmtUser->execute([':hash' => $newHash, ':email' => $reset['email']]);

            $stmtMark = $pdo->prepare("UPDATE password_resets SET used_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmtMark->execute([':id' => $reset['id']]);

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return false;
        }
    }

    public function forgotPassword(Request $request): void
    {
        $email = trim((string)$request->body('email', ''));
        if (empty($email)) {
            Response::error('VALIDATION_ERROR', 'Informe o seu e-mail cadastrado.', 422);
        }

        $token = $this->createPasswordResetToken($email);
        if ($token) {
            $pdo = Connection::getInstance();
            $stmt = $pdo->prepare("SELECT name FROM users WHERE email = :email");
            $stmt->execute([':email' => strtolower($email)]);
            $user = $stmt->fetch();
            $name = $user['name'] ?? 'Cliente';

            $mailer = new \VTD\Services\MailerService();
            $mailer->sendPasswordReset($email, $name, $token);
        }

        // Resposta sempre positiva para evitar enumeração de emails
        Response::json([
            'message' => 'Se o e-mail estiver cadastrado, as instruções de recuperação foram enviadas.'
        ]);
    }

    public function resetPassword(Request $request): void
    {
        $token = trim((string)$request->body('token', ''));
        $newPassword = (string)$request->body('new_password', '');

        if (empty($token) || strlen($newPassword) < 6) {
            Response::error('VALIDATION_ERROR', 'Token inválido ou senha com menos de 6 caracteres.', 422);
        }

        $success = $this->applyPasswordReset($token, $newPassword);
        if (!$success) {
            Response::error('INVALID_TOKEN', 'Link de recuperação expirado ou já utilizado.', 400);
        }

        Response::json(['message' => 'Sua senha foi redefinida com sucesso. Faça login com a nova senha.']);
    }
}

