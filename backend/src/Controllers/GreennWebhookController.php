<?php
declare(strict_types=1);

namespace VTD\Controllers;

use VTD\Core\Request;
use VTD\Core\Response;
use VTD\Database\Connection;
use VTD\Config\Config;
use VTD\Services\MailerService;

class GreennWebhookController
{
    private MailerService $mailer;

    public function __construct(?MailerService $mailer = null)
    {
        $this->mailer = $mailer ?? new MailerService();
    }

    public function validateSecret(Request $request): bool
    {
        $expected = Config::get('greenn.webhook_secret');
        if (empty($expected)) {
            return false;
        }

        $token = $request->query('token')
            ?? $request->body('token')
            ?? $request->header('x-greenn-token')
            ?? $request->header('x-greenn-signature');

        return hash_equals((string)$expected, (string)$token);
    }

    public function handle(Request $request): void
    {
        if (!$this->validateSecret($request)) {
            Response::error('UNAUTHORIZED_WEBHOOK', 'Chave secreta do webhook inválida.', 401);
        }

        $payload = $request->body();
        if (empty($payload)) {
            Response::error('EMPTY_PAYLOAD', 'Corpo da requisição vazio.', 400);
        }

        try {
            $result = $this->processPayload($payload);
            Response::json($result);
        } catch (\Throwable $e) {
            Response::error('WEBHOOK_PROCESSING_ERROR', $e->getMessage(), 500);
        }
    }

    public function processPayload(array $payload): array
    {
        $pdo = Connection::getInstance();

        $eventId = (string)($payload['id'] ?? $payload['sale']['id'] ?? $payload['transaction_id'] ?? bin2hex(random_bytes(10)));
        $event = strtolower(trim((string)($payload['event'] ?? $payload['status'] ?? 'unknown')));
        if ($event === 'saleupdated') {
            $saleStatus = strtolower(trim((string)($payload['sale']['status'] ?? '')));
            $event = match ($saleStatus) {
                'paid' => 'order_paid',
                'refunded', 'chargeback' => 'order_refunded',
                default => 'unknown',
            };
        }

        // Normalização de eventos da Greenn
        $isPaid = in_array($event, ['order_paid', 'approved', 'transacao_aprovada', 'pago'], true);
        $isRefund = in_array($event, ['order_refunded', 'chargeback', 'reembolso', 'cancelado'], true);

        // 1. Verificação de Idempotência
        $stmtCheck = $pdo->prepare("SELECT id FROM webhook_events WHERE event_id = :eid AND provider = 'greenn'");
        $stmtCheck->execute([':eid' => $eventId]);
        if ($stmtCheck->fetch()) {
            return [
                'success'    => true,
                'idempotent' => true,
                'message'    => 'Evento já processado anteriormente.'
            ];
        }

        // 2. Extração dos dados do comprador
        $buyerEmail = trim(strtolower((string)($payload['sale']['client']['email'] ?? $payload['sale']['customer']['email'] ?? $payload['buyer_email'] ?? $payload['customer']['email'] ?? $payload['email'] ?? '')));
        $buyerName = trim((string)($payload['sale']['client']['name'] ?? $payload['sale']['customer']['name'] ?? $payload['buyer_name'] ?? $payload['customer']['name'] ?? $payload['name'] ?? 'Cliente VTD'));
        $productId = (string)($payload['sale']['product']['id'] ?? $payload['sale']['product_id'] ?? $payload['product_id'] ?? $payload['offer_id'] ?? $payload['product']['id'] ?? '');

        $plusProductId = (string)Config::get('greenn.plus_product_id', 'kit-plus');
        $isPlusPlan = ($productId === $plusProductId) || str_contains(strtolower($payload['product_name'] ?? ''), 'financeiro');
        $plan = $isPlusPlan ? 'plus' : 'essential';

        $pdo->beginTransaction();
        try {
            $action = 'ignored';

            if ($isPaid && !empty($buyerEmail)) {
                // Checa se usuário já existe
                $stmtUser = $pdo->prepare("
                    SELECT u.id as user_id, u.name, u.tenant_id, t.plan as current_plan
                    FROM users u
                    JOIN tenants t ON t.id = u.tenant_id
                    WHERE u.email = :email
                ");
                $stmtUser->execute([':email' => $buyerEmail]);
                $existing = $stmtUser->fetch();

                if ($existing) {
                    // Se já existe e comprou upgrade para plus
                    if ($plan === 'plus' && $existing['current_plan'] !== 'plus') {
                        $stmtUp = $pdo->prepare("UPDATE tenants SET plan = 'plus', status = 'active' WHERE id = :tid");
                        $stmtUp->execute([':tid' => $existing['tenant_id']]);
                        $this->mailer->sendUpgradeNotification($buyerEmail, $existing['name']);
                        $action = 'client_upgraded';
                    } else {
                        // Reativa se estava suspenso
                        $stmtReactivate = $pdo->prepare("UPDATE tenants SET status = 'active' WHERE id = :tid");
                        $stmtReactivate->execute([':tid' => $existing['tenant_id']]);
                        $action = 'client_reactivated';
                    }
                } else {
                    // Novo comprador: cria tenant, user e categorias
                    $tenantId = 'tenant_' . bin2hex(random_bytes(8));
                    $userId = 'user_' . bin2hex(random_bytes(8));
                    $tempPassword = 'Vtd' . rand(100000, 999999) . '!';
                    $passHash = password_hash($tempPassword, PASSWORD_DEFAULT);
                    $storeName = 'Loja de ' . explode(' ', $buyerName)[0];
                    $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($storeName)) . '-' . bin2hex(random_bytes(3));

                    $stmtT = $pdo->prepare("
                        INSERT INTO tenants (id, name, slug, plan, initial_balance_cents, status)
                        VALUES (:id, :name, :slug, :plan, 0, 'active')
                    ");
                    $stmtT->execute([':id' => $tenantId, ':name' => $storeName, ':slug' => $slug, ':plan' => $plan]);

                    $stmtU = $pdo->prepare("
                        INSERT INTO users (id, tenant_id, name, email, password_hash, role)
                        VALUES (:id, :tid, :name, :email, :pass, 'user')
                    ");
                    $stmtU->execute([':id' => $userId, ':tid' => $tenantId, ':name' => $buyerName, ':email' => $buyerEmail, ':pass' => $passHash]);

                    // Categorias padrão
                    $defaultCategories = ['Serviços', 'Mercadorias', 'Moradia', 'Alimentação', 'Transporte', 'Outros'];
                    $stmtCat = $pdo->prepare("
                        INSERT OR IGNORE INTO financial_categories (tenant_id, name)
                        VALUES (:tid, :cat)
                    ");
                    if (Config::get('db.driver') === 'mysql') {
                        $stmtCat = $pdo->prepare("
                            INSERT IGNORE INTO financial_categories (tenant_id, name)
                            VALUES (:tid, :cat)
                        ");
                    }
                    foreach ($defaultCategories as $c) {
                        $stmtCat->execute([':tid' => $tenantId, ':cat' => $c]);
                    }

                    // Envia e-mail de acesso
                    $this->mailer->sendWelcomeCredentials($buyerEmail, $buyerName, $tempPassword, $plan);
                    $action = 'client_created';
                }
            } elseif ($isRefund && !empty($buyerEmail)) {
                // Reembolso: suspende o tenant
                $stmtSuspend = $pdo->prepare("
                    UPDATE tenants
                    SET status = 'suspended'
                    WHERE id IN (SELECT tenant_id FROM users WHERE email = :email)
                ");
                $stmtSuspend->execute([':email' => $buyerEmail]);
                $action = 'client_suspended';
            }

            // Registra evento para idempotência
            $stmtEvent = $pdo->prepare("
                INSERT INTO webhook_events (provider, event_id, event_type, payload)
                VALUES ('greenn', :eid, :etype, :payload)
            ");
            $stmtEvent->execute([
                ':eid'     => $eventId,
                ':etype'   => $event,
                ':payload' => json_encode($payload)
            ]);

            $pdo->commit();

            return [
                'success' => true,
                'action'  => $action,
                'event'   => $event
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
