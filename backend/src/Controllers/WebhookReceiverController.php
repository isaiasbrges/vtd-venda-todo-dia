<?php
declare(strict_types=1);

namespace VTD\Controllers;

use VTD\Core\Request;
use VTD\Core\Response;
use VTD\Database\Connection;
use VTD\Services\MailerService;
use PDO;

class WebhookReceiverController
{
    private MailerService $mailer;

    public function __construct(?MailerService $mailer = null)
    {
        $this->mailer = $mailer ?? new MailerService();
    }

    private function getPdo(): PDO
    {
        return Connection::getInstance();
    }

    /**
     * Recebimento de Webhook via Token de Integração (/api/webhooks/receiver/{token})
     */
    public function handle(Request $request, array $params): void
    {
        $token = (string)($params['token'] ?? '');
        $pdo = $this->getPdo();

        // 1. Busca integração ativa pelo token
        $stmt = $pdo->prepare("SELECT * FROM integrations WHERE webhook_token = :token AND status = 'active'");
        $stmt->execute([':token' => $token]);
        $integration = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$integration) {
            Response::error('INVALID_WEBHOOK_TOKEN', 'Token de integração inválido ou integração inativa.', 401);
            return;
        }

        $body = $request->body();
        if (empty($body)) {
            Response::error('EMPTY_PAYLOAD', 'Corpo da requisição vazio.', 400);
            return;
        }

        // 2. Extrai dados padronizados de acordo com o provedor
        $normalized = $this->normalizePayload($integration['provider'], $body, $request);

        // 3. Sanitiza payload para log seguro (sem senhas, tokens ou dados de cartão)
        $sanitizedPayload = $body;
        unset($sanitizedPayload['token'], $sanitizedPayload['password'], $sanitizedPayload['credit_card'], $sanitizedPayload['cvv']);

        // 4. Registra o evento ANTES de processar
        $stmtEvent = $pdo->prepare("
            INSERT INTO integration_events (integration_id, provider, event_id, event_type, external_reference, client_email, payload, status, failure_reason)
            VALUES (:integ_id, :provider, :event_id, :event_type, :ext_ref, :email, :payload, 'pending', NULL)
        ");
        $stmtEvent->execute([
            ':integ_id'   => $integration['id'],
            ':provider'   => $integration['provider'],
            ':event_id'   => $normalized['event_id'],
            ':event_type' => $normalized['event_type'],
            ':ext_ref'    => $normalized['order_id'],
            ':email'      => $normalized['buyer_email'],
            ':payload'    => json_encode($sanitizedPayload, JSON_UNESCAPED_UNICODE)
        ]);
        $eventDbId = (int)$pdo->lastInsertId();

        // 5. Verifica Idempotência: se já foi processado anteriormente
        $checkDup = $pdo->prepare("
            SELECT id FROM integration_events 
            WHERE integration_id = :integ_id AND event_id = :event_id AND status = 'success' AND id != :current_id
        ");
        $checkDup->execute([
            ':integ_id'   => $integration['id'],
            ':event_id'   => $normalized['event_id'],
            ':current_id' => $eventDbId
        ]);
        if ($checkDup->fetch()) {
            $pdo->prepare("UPDATE integration_events SET status = 'ignored', failure_reason = 'Evento duplicado já processado anteriormente.' WHERE id = :id")
                ->execute([':id' => $eventDbId]);
            Response::json(['success' => true, 'message' => 'Evento duplicado reconhecido e ignorado.']);
            return;
        }

        // 6. Processa o evento com as regras de negócio
        $res = $this->processEvent($integration, $normalized, $eventDbId);

        if ($res['success']) {
            Response::json(['success' => true, 'message' => $res['message']]);
        } else {
            Response::error('EVENT_PROCESSING_ERROR', $res['message'], 400);
        }
    }

    /**
     * Processamento de um registro de evento existente (para reprocessamento)
     */
    public function processEventRecord(array $event): array
    {
        $pdo = $this->getPdo();
        $payload = json_decode($event['payload'] ?? '{}', true) ?: [];

        // Busca a integração correspondente
        $integration = null;
        if (!empty($event['integration_id'])) {
            $stmt = $pdo->prepare("SELECT * FROM integrations WHERE id = :id");
            $stmt->execute([':id' => $event['integration_id']]);
            $integration = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$integration) {
            $integration = [
                'id'             => $event['integration_id'],
                'provider'       => $event['provider'],
                'offer_mappings' => '{}',
                'enabled_events' => '["order_paid","order_refunded"]'
            ];
        }

        $dummyReq = new Request('POST', '/api/webhooks/receiver', [], $payload);
        $normalized = $this->normalizePayload($event['provider'], $payload, $dummyReq);

        return $this->processEvent($integration, $normalized, (int)$event['id'], true);
    }

    /**
     * Motor de processamento do evento
     */
    private function processEvent(array $integration, array $normalized, int $eventId, bool $isReprocess = false): array
    {
        $pdo = $this->getPdo();
        $offerMappings = json_decode($integration['offer_mappings'] ?? '{}', true) ?: [];

        $eventType = $normalized['event_type'];
        $productExternalCode = (string)$normalized['product_id'];

        // Se for compra pendente: registra sem liberar
        if (in_array($eventType, ['order_waiting', 'order_pending', 'waiting_payment'])) {
            $pdo->prepare("UPDATE integration_events SET status = 'ignored', failure_reason = 'Compra pendente de pagamento. Acesso não liberado.' WHERE id = :id")
                ->execute([':id' => $eventId]);
            return ['success' => true, 'message' => 'Evento pendente registrado.'];
        }

        // Verifica mapeamento da oferta
        $internalProduct = $offerMappings[$productExternalCode] ?? null;

        // Se o produto não tiver associação configurada: REGISTRA FALHA e NÃO LIBERA por suposição
        if (!$internalProduct) {
            $reason = "Produto externo '{$productExternalCode}' não possui associação com nenhuma oferta interna.";
            $pdo->prepare("UPDATE integration_events SET status = 'failed', failure_reason = :reason WHERE id = :id")
                ->execute([':reason' => $reason, ':id' => $eventId]);

            $pdo->prepare("UPDATE integrations SET last_event_at = CURRENT_TIMESTAMP, last_event_status = 'failed', last_event_summary = :sum WHERE id = :id")
                ->execute([':sum' => 'Falha: produto sem vínculo', ':id' => $integration['id']]);

            return ['success' => false, 'message' => $reason];
        }

        $buyerEmail = trim(strtolower($normalized['buyer_email']));
        $buyerName = trim($normalized['buyer_name']);
        if (empty($buyerEmail)) {
            $reason = "E-mail do comprador não informado no payload.";
            $pdo->prepare("UPDATE integration_events SET status = 'failed', failure_reason = :reason WHERE id = :id")
                ->execute([':reason' => $reason, ':id' => $eventId]);
            return ['success' => false, 'message' => $reason];
        }

        $orderId = $normalized['order_id'];

        // 1. REEMBOLSO OU CHARGEBACK
        if (in_array($eventType, ['order_refunded', 'order_chargeback', 'refunded', 'chargeback'])) {
            $pdo->beginTransaction();
            try {
                // Localiza o acesso específico daquela compra
                $stmtAcc = $pdo->prepare("SELECT * FROM client_accesses WHERE external_reference = :ref AND status = 'active'");
                $stmtAcc->execute([':ref' => $orderId]);
                $access = $stmtAcc->fetch(PDO::FETCH_ASSOC);

                if ($access) {
                    $pdo->prepare("UPDATE client_accesses SET status = 'revoked', revoked_at = CURRENT_TIMESTAMP WHERE id = :id")
                        ->execute([':id' => $access['id']]);

                    // Verifica se o cliente tem outros acessos Plus
                    $checkPlus = $pdo->prepare("
                        SELECT COUNT(*) FROM client_accesses 
                        WHERE tenant_id = :tid AND product_id = 'kit-plus' AND status = 'active'
                    ");
                    $checkPlus->execute([':tid' => $access['tenant_id']]);
                    if ((int)$checkPlus->fetchColumn() === 0) {
                        // Não tem outro Plus: rebaixa para essential ou suspende se não tiver nenhum
                        $checkAny = $pdo->prepare("SELECT COUNT(*) FROM client_accesses WHERE tenant_id = :tid AND status = 'active'");
                        $checkAny->execute([':tid' => $access['tenant_id']]);
                        if ((int)$checkAny->fetchColumn() === 0) {
                            $pdo->prepare("UPDATE tenants SET status = 'suspended', updated_at = CURRENT_TIMESTAMP WHERE id = :tid")
                                ->execute([':tid' => $access['tenant_id']]);
                        } else {
                            $pdo->prepare("UPDATE tenants SET plan = 'essential', updated_at = CURRENT_TIMESTAMP WHERE id = :tid")
                                ->execute([':tid' => $access['tenant_id']]);
                        }
                    }
                }

                $pdo->prepare("UPDATE integration_events SET status = 'success', failure_reason = NULL WHERE id = :id")
                    ->execute([':id' => $eventId]);
                $pdo->prepare("UPDATE integrations SET last_event_at = CURRENT_TIMESTAMP, last_event_status = 'success', last_event_summary = 'Reembolso processado com sucesso' WHERE id = :id")
                    ->execute([':id' => $integration['id']]);

                $pdo->commit();
                return ['success' => true, 'message' => 'Reembolso processado. Acesso revogado preservando demais compras.'];
            } catch (\Throwable $e) {
                $pdo->rollBack();
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }

        // 2. COMPRA APROVADA: localiza ou cria cliente e libera a oferta associada
        if (in_array($eventType, ['order_paid', 'payment_approved', 'approved'])) {
            $pdo->beginTransaction();
            try {
                // Verifica se usuário já existe
                $stmtUser = $pdo->prepare("SELECT id, tenant_id FROM users WHERE email = :email");
                $stmtUser->execute([':email' => $buyerEmail]);
                $existingUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

                $tenantId = '';
                $userId = '';
                $isNew = false;
                $tempPass = bin2hex(random_bytes(6));

                if ($existingUser) {
                    $tenantId = $existingUser['tenant_id'];
                    $userId = $existingUser['id'];

                    if ($internalProduct === 'kit-plus') {
                        $pdo->prepare("UPDATE tenants SET plan = 'plus', status = 'active', updated_at = CURRENT_TIMESTAMP WHERE id = :id")
                            ->execute([':id' => $tenantId]);
                    }
                } else {
                    $isNew = true;
                    $tenantId = 'tenant_' . bin2hex(random_bytes(8));
                    $userId = 'user_' . bin2hex(random_bytes(8));
                    $plan = $internalProduct === 'kit-plus' ? 'plus' : 'essential';
                    $storeName = 'Loja de ' . ($buyerName ?: 'Cliente');
                    $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($storeName)) . '-' . bin2hex(random_bytes(3));
                    $passHash = password_hash($tempPass, PASSWORD_DEFAULT);

                    // Cria tenant
                    $pdo->prepare("INSERT INTO tenants (id, name, slug, plan, status) VALUES (:id, :name, :slug, :plan, 'active')")
                        ->execute([':id' => $tenantId, ':name' => $storeName, ':slug' => $slug, ':plan' => $plan]);

                    // Cria user
                    $pdo->prepare("INSERT INTO users (id, tenant_id, name, email, password_hash, role) VALUES (:id, :tid, :name, :email, :pass, 'user')")
                        ->execute([':id' => $userId, ':tid' => $tenantId, ':name' => $buyerName ?: 'Cliente', ':email' => $buyerEmail, ':pass' => $passHash]);

                    // Categorias padrão
                    $defaultCats = ['Vendas', 'Mercadorias', 'Serviços', 'Marketing', 'Operacional', 'Outros'];
                    $stmtC = $pdo->prepare("INSERT INTO financial_categories (tenant_id, name) VALUES (:tid, :name)");
                    foreach ($defaultCats as $c) $stmtC->execute([':tid' => $tenantId, ':name' => $c]);
                }

                // Cria o acesso vinculado à compra em client_accesses
                $accId = 'acc_' . bin2hex(random_bytes(8));
                $stmtAcc = $pdo->prepare("
                    INSERT INTO client_accesses (id, tenant_id, user_id, product_id, source, external_reference, status)
                    VALUES (:id, :tid, :uid, :pid, 'webhook', :ref, 'active')
                ");
                $stmtAcc->execute([
                    ':id'  => $accId,
                    ':tid' => $tenantId,
                    ':uid' => $userId,
                    ':pid' => $internalProduct,
                    ':ref' => $orderId
                ]);

                // Atualiza status do evento
                $pdo->prepare("UPDATE integration_events SET status = 'success', failure_reason = NULL, last_attempt_at = CURRENT_TIMESTAMP WHERE id = :id")
                    ->execute([':id' => $eventId]);

                $pdo->prepare("UPDATE integrations SET last_event_at = CURRENT_TIMESTAMP, last_event_status = 'success', last_event_summary = 'Compra aprovada -> acesso liberado' WHERE id = :id")
                    ->execute([':id' => $integration['id']]);

                $pdo->commit();

                // Disparo de e-mail de acesso caso seja novo usuário
                if ($isNew && !$isReprocess) {
                    $this->mailer->sendWelcome($buyerEmail, $buyerName ?: 'Cliente', $tempPass, $internalProduct === 'kit-plus' ? 'plus' : 'essential');
                }

                return ['success' => true, 'message' => "Acesso liberado com sucesso para o produto '{$internalProduct}'."];
            } catch (\Throwable $e) {
                $pdo->rollBack();
                $pdo->prepare("UPDATE integration_events SET status = 'failed', failure_reason = :reason WHERE id = :id")
                    ->execute([':reason' => $e->getMessage(), ':id' => $eventId]);
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }

        // Tipo de evento desconhecido
        $pdo->prepare("UPDATE integration_events SET status = 'ignored', failure_reason = 'Tipo de evento não processável.' WHERE id = :id")
            ->execute([':id' => $eventId]);
        return ['success' => true, 'message' => 'Evento ignorado (sem ação configurada).'];
    }

    /**
     * Normaliza payloads de diferentes gateways (Greenn, Kiwify, Hotmart, Custom)
     */
    private function normalizePayload(string $provider, array $body, Request $request): array
    {
        $eventId = (string)($body['event_id'] ?? $body['id'] ?? $body['hashing'] ?? bin2hex(random_bytes(10)));
        $eventType = (string)($body['event'] ?? $body['event_type'] ?? $body['type'] ?? $body['status'] ?? 'unknown');
        $orderId = (string)($body['order_id'] ?? $body['order']['id'] ?? $body['transaction_id'] ?? $body['id'] ?? 'ORD-' . time());
        
        $buyerEmail = '';
        $buyerName = '';
        $productId = '';

        if ($provider === 'greenn') {
            // Greenn envia atualizações de venda como saleupdated. Somente
            // converta para pagamento confirmado quando o status aninhado for paid.
            if (strtolower($eventType) === 'saleupdated') {
                $saleStatus = strtolower(trim((string)($body['sale']['status'] ?? '')));
                $eventType = match ($saleStatus) {
                    'paid' => 'order_paid',
                    'refunded', 'refund', 'chargeback' => 'order_refunded',
                    'waiting', 'pending', 'unpaid', 'open' => 'order_pending',
                    default => 'saleupdated',
                };
            }
            $orderId = (string)($body['sale']['id'] ?? $body['sale']['code'] ?? $body['sale']['hash'] ?? $orderId);
            $eventId = (string)($body['event_id'] ?? $body['id'] ?? $body['hashing'] ??
                ($body['sale']['id'] ?? '') . ':' . ($body['sale']['status'] ?? ''));
            $buyerEmail = (string)($body['sale']['client']['email'] ?? $body['sale']['customer']['email'] ?? $body['sale']['buyer']['email'] ?? $body['buyer']['email'] ?? $body['client']['email'] ?? $body['buyer_email'] ?? $body['email'] ?? '');
            $buyerName = (string)($body['sale']['client']['name'] ?? $body['sale']['customer']['name'] ?? $body['sale']['buyer']['name'] ?? $body['buyer']['name'] ?? $body['client']['name'] ?? $body['buyer_name'] ?? $body['name'] ?? '');
            $productId = (string)($body['sale']['product']['id'] ?? $body['sale']['product_id'] ?? $body['sale']['offer']['id'] ?? $body['sale']['offer_id'] ?? $body['product']['id'] ?? $body['product_id'] ?? $body['offer']['id'] ?? $body['offer_code'] ?? '');
        } elseif ($provider === 'kiwify') {
            $buyerEmail = (string)($body['Customer']['email'] ?? $body['customer']['email'] ?? '');
            $buyerName = (string)($body['Customer']['full_name'] ?? $body['customer']['name'] ?? '');
            $productId = (string)($body['Product']['product_id'] ?? $body['product_id'] ?? '');
            if (($body['order_status'] ?? '') === 'paid') $eventType = 'order_paid';
            if (($body['order_status'] ?? '') === 'refunded') $eventType = 'order_refunded';
        } else {
            // Padrão Custom / Genérico
            $buyerEmail = (string)($body['buyer']['email'] ?? $body['email'] ?? '');
            $buyerName = (string)($body['buyer']['name'] ?? $body['name'] ?? '');
            $productId = (string)($body['product_id'] ?? $body['offer_id'] ?? $body['offer_code'] ?? '');
        }

        return [
            'event_id'    => $eventId,
            'event_type'  => strtolower($eventType),
            'order_id'    => $orderId,
            'buyer_email' => $buyerEmail,
            'buyer_name'  => $buyerName,
            'product_id'  => $productId
        ];
    }
}
