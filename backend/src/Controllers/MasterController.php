<?php
declare(strict_types=1);

namespace VTD\Controllers;

use VTD\Core\Request;
use VTD\Core\Response;
use VTD\Database\Connection;
use VTD\Middlewares\AdminMiddleware;
use VTD\Services\MailerService;
use VTD\Config\Config;
use PDO;

class MasterController
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

    private function logAudit(string $adminUserId, string $action, string $targetType, string $targetId, array $details = []): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("
            INSERT INTO master_audit_logs (admin_user_id, action, target_type, target_id, details, ip_address)
            VALUES (:admin_user_id, :action, :target_type, :target_id, :details, :ip)
        ");
        $stmt->execute([
            ':admin_user_id' => $adminUserId,
            ':action'        => $action,
            ':target_type'   => $targetType,
            ':target_id'     => $targetId,
            ':details'       => json_encode($details, JSON_UNESCAPED_UNICODE),
            ':ip'            => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    }

    /**
     * 1. VISÃO GERAL: Indicadores calculados a partir de dados reais
     */
    public function overview(Request $request): void
    {
        $auth = (new AdminMiddleware())->handle($request);
        $pdo = $this->getPdo();

        // 1. Contadores Reais
        $totalClients = (int)$pdo->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
        $activeAccesses = (int)$pdo->query("SELECT COUNT(*) FROM tenants WHERE status = 'active'")->fetchColumn();
        $activeIntegrations = (int)$pdo->query("SELECT COUNT(*) FROM integrations WHERE status = 'active'")->fetchColumn();
        $failedEvents = (int)$pdo->query("SELECT COUNT(*) FROM integration_events WHERE status = 'failed'")->fetchColumn();

        // 2. Clientes recentes
        $sqlClients = "
            SELECT t.id as tenant_id, t.name as store_name, t.plan, t.status, t.created_at,
                   u.id as user_id, u.name as client_name, u.email as client_email,
                   COALESCE((
                       SELECT source FROM client_accesses ca 
                       WHERE ca.tenant_id = t.id 
                       ORDER BY ca.granted_at DESC LIMIT 1
                   ), 'manual') as origin
            FROM tenants t
            JOIN users u ON u.tenant_id = t.id
            ORDER BY t.created_at DESC
            LIMIT 6
        ";
        $recentClients = $pdo->query($sqlClients)->fetchAll(PDO::FETCH_ASSOC);

        $formattedClients = array_map(function($c) {
            $initials = '';
            $parts = explode(' ', trim($c['client_name']));
            if (count($parts) >= 2) {
                $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
            } else {
                $initials = strtoupper(substr($parts[0] ?? 'VT', 0, 2));
            }

            return [
                'tenant_id' => $c['tenant_id'],
                'user_id'   => $c['user_id'],
                'name'      => $c['client_name'],
                'email'     => $c['client_email'],
                'store'     => $c['store_name'],
                'initials'  => $initials,
                'offer'     => $c['plan'] === 'plus' ? 'Kit + Financeiro' : 'Kit Essencial',
                'plan_code' => $c['plan'],
                'access'    => $c['status'] === 'active' ? 'Liberado' : 'Suspenso',
                'status'    => $c['status'],
                'origin'    => ucfirst($c['origin'] ?? 'manual'),
                'created_at'=> $c['created_at']
            ];
        }, $recentClients);

        // 3. Resumo das integrações
        $sqlInteg = "
            SELECT id, name, provider, environment, status, last_event_at, last_event_status, last_event_summary
            FROM integrations
            ORDER BY created_at ASC
        ";
        $integrations = $pdo->query($sqlInteg)->fetchAll(PDO::FETCH_ASSOC);

        $formattedIntegrations = array_map(function($i) {
            $relativeTime = 'Nunca executado';
            if (!empty($i['last_event_at'])) {
                $diff = time() - strtotime($i['last_event_at']);
                if ($diff < 60) $relativeTime = 'Há poucos segundos';
                elseif ($diff < 3600) $relativeTime = 'Há ' . floor($diff / 60) . ' minutos';
                elseif ($diff < 86400) $relativeTime = 'Há ' . floor($diff / 3600) . ' horas';
                else $relativeTime = 'Há ' . floor($diff / 86400) . ' dias';
            }

            return [
                'id'            => $i['id'],
                'name'          => $i['name'],
                'provider'      => $i['provider'],
                'status'        => $i['status'],
                'is_active'     => $i['status'] === 'active',
                'summary'       => $i['last_event_summary'] ?? 'Compra aprovada → liberar acesso',
                'last_event'    => $relativeTime,
                'last_status'   => $i['last_event_status']
            ];
        }, $integrations);

        // 4. Últimos eventos
        $sqlEvents = "
            SELECT id, integration_id, provider, event_id, event_type, external_reference, client_email, status, failure_reason, created_at
            FROM integration_events
            ORDER BY created_at DESC
            LIMIT 5
        ";
        $recentEvents = $pdo->query($sqlEvents)->fetchAll(PDO::FETCH_ASSOC);

        $formattedEvents = array_map(function($e) {
            $time = date('H:i', strtotime($e['created_at']));
            $title = match($e['event_type']) {
                'order_paid', 'order_approved' => 'Compra aprovada',
                'order_refunded'              => 'Reembolso realizado',
                'invite_sent'                 => 'Convite de acesso',
                'webhook_received'            => 'Webhook recebido',
                default                       => ucfirst(str_replace('_', ' ', $e['event_type']))
            };

            $desc = '';
            if ($e['status'] === 'success') {
                $desc = 'Acesso liberado para ' . ($e['client_email'] ?: 'cliente');
            } elseif ($e['status'] === 'failed') {
                $desc = $e['failure_reason'] ?: 'Falha no processamento do evento';
            } else {
                $desc = 'Evento registrado com sucesso';
            }

            $badge = match($e['status']) {
                'success' => 'Sucesso',
                'failed'  => 'Atenção',
                default   => 'Ignorado'
            };

            return [
                'id'          => $e['id'],
                'time'        => $time,
                'title'       => $title,
                'subtitle'    => $e['client_email'] ? 'Cliente: ' . $e['client_email'] : 'Origem: ' . $e['provider'],
                'description' => $desc,
                'badge'       => $badge,
                'status'      => $e['status'],
                'is_failed'   => $e['status'] === 'failed',
                'reference'   => $e['external_reference']
            ];
        }, $recentEvents);

        Response::json([
            'metrics' => [
                'total_clients'       => $totalClients,
                'active_accesses'     => $activeAccesses,
                'active_integrations' => $activeIntegrations,
                'failed_events'       => $failedEvents
            ],
            'recent_clients' => $formattedClients,
            'integrations'   => $formattedIntegrations,
            'recent_events'  => $formattedEvents
        ]);
    }

    /**
     * 2. CLIENTES: Listagem com busca, filtros e paginação
     */
    public function listClients(Request $request): void
    {
        (new AdminMiddleware())->handle($request);
        $pdo = $this->getPdo();

        $search = trim((string)$request->query('search', ''));
        $offer = trim((string)$request->query('offer', ''));
        $status = trim((string)$request->query('status', ''));
        $origin = trim((string)$request->query('origin', ''));
        $page = max(1, (int)$request->query('page', 1));
        $limit = max(1, min(50, (int)$request->query('limit', 15)));
        $offset = ($page - 1) * $limit;

        $conditions = ["1=1"];
        $params = [];

        if (!empty($search)) {
            $conditions[] = "(u.name LIKE :search OR u.email LIKE :search OR t.name LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        if (!empty($offer)) {
            $conditions[] = "t.plan = :offer";
            $params[':offer'] = $offer === 'kit-plus' || $offer === 'plus' ? 'plus' : 'essential';
        }

        if (!empty($status)) {
            $conditions[] = "t.status = :status";
            $params[':status'] = $status;
        }

        $whereSql = implode(' AND ', $conditions);

        // Contagem total
        $countSql = "
            SELECT COUNT(*) 
            FROM tenants t 
            JOIN users u ON u.tenant_id = t.id 
            WHERE {$whereSql}
        ";
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        // Consulta paginada
        $sql = "
            SELECT t.id as tenant_id, t.name as store_name, t.plan, t.status, t.initial_balance_cents, t.created_at,
                   u.id as user_id, u.name as client_name, u.email as client_email,
                   (
                       SELECT ca.source FROM client_accesses ca 
                       WHERE ca.tenant_id = t.id 
                       ORDER BY ca.granted_at DESC LIMIT 1
                   ) as origin,
                   (
                       SELECT COUNT(*) FROM client_accesses ca 
                       WHERE ca.tenant_id = t.id AND ca.status = 'active'
                   ) as active_products_count
            FROM tenants t
            JOIN users u ON u.tenant_id = t.id
            WHERE {$whereSql}
            ORDER BY t.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $clients = array_map(function($r) {
            $initials = '';
            $parts = explode(' ', trim($r['client_name']));
            if (count($parts) >= 2) {
                $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
            } else {
                $initials = strtoupper(substr($parts[0] ?? 'VT', 0, 2));
            }

            return [
                'tenant_id'             => $r['tenant_id'],
                'user_id'               => $r['user_id'],
                'name'                  => $r['client_name'],
                'email'                 => $r['client_email'],
                'store'                 => $r['store_name'],
                'initials'              => $initials,
                'plan'                  => $r['plan'],
                'offer_name'            => $r['plan'] === 'plus' ? 'Kit + Financeiro' : 'Kit Essencial',
                'status'                => $r['status'],
                'access_label'          => $r['status'] === 'active' ? 'Liberado' : 'Suspenso',
                'origin'                => ucfirst($r['origin'] ?? 'manual'),
                'active_products_count' => (int)$r['active_products_count'],
                'initial_balance_cents' => (int)$r['initial_balance_cents'],
                'created_at'            => $r['created_at']
            ];
        }, $rows);

        Response::json([
            'clients'     => $clients,
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int)ceil($total / $limit)
        ]);
    }

    /**
     * 2. CLIENTES: Criação manual com detecção de e-mail existente e convite seguro
     */
    public function createClient(Request $request): void
    {
        $admin = (new AdminMiddleware())->handle($request);
        $pdo = $this->getPdo();

        $name = trim((string)$request->body('name', ''));
        $email = trim(strtolower((string)$request->body('email', '')));
        $store = trim((string)$request->body('store', ''));
        $offer = ($request->body('offer') === 'kit-essencial' || $request->body('plan') === 'essential') ? 'essential' : 'plus';
        $status = $request->body('status', 'active') === 'suspended' ? 'suspended' : 'active';
        $expiresAt = !empty($request->body('expires_at')) ? $request->body('expires_at') : null;
        $sendInvite = (bool)$request->body('send_invite', true);

        if (empty($name) || empty($email)) {
            Response::error('VALIDATION_ERROR', 'Nome e e-mail são obrigatórios para cadastrar o cliente.', 422);
            return;
        }

        if (empty($store)) {
            $store = 'Loja de ' . explode(' ', $name)[0];
        }

        // Verifica se e-mail já existe
        $checkUser = $pdo->prepare("
            SELECT u.id, u.tenant_id, u.name, t.plan, t.status 
            FROM users u 
            JOIN tenants t ON t.id = u.tenant_id 
            WHERE u.email = :email
        ");
        $checkUser->execute([':email' => $email]);
        $existing = $checkUser->fetch(PDO::FETCH_ASSOC);

        $pdo->beginTransaction();

        try {
            if ($existing) {
                // Cliente já existe: associa nova oferta sem duplicar
                $tenantId = $existing['tenant_id'];
                $userId = $existing['id'];

                // Se a nova oferta for 'plus', faz upgrade do tenant
                if ($offer === 'plus') {
                    $pdo->prepare("UPDATE tenants SET plan = 'plus', updated_at = CURRENT_TIMESTAMP WHERE id = :id")
                        ->execute([':id' => $tenantId]);
                }

                // Cria registro em client_accesses
                $accessId = 'acc_' . bin2hex(random_bytes(8));
                $productId = $offer === 'plus' ? 'kit-plus' : 'kit-essencial';

                $stmtAcc = $pdo->prepare("
                    INSERT INTO client_accesses (id, tenant_id, user_id, product_id, source, external_reference, status, expires_at)
                    VALUES (:id, :tenant_id, :user_id, :product_id, 'manual', 'MANUAL-ADD', 'active', :expires_at)
                ");
                $stmtAcc->execute([
                    ':id'         => $accessId,
                    ':tenant_id'  => $tenantId,
                    ':user_id'    => $userId,
                    ':product_id' => $productId,
                    ':expires_at' => $expiresAt
                ]);

                $this->logAudit($admin['user_id'], 'add_product_existing_client', 'client', $tenantId, [
                    'email'   => $email,
                    'product' => $productId
                ]);

                $pdo->commit();

                Response::json([
                    'message'      => "O e-mail já estava cadastrado. O produto foi adicionado à conta existente com sucesso sem duplicação.",
                    'is_existing'  => true,
                    'tenant_id'    => $tenantId,
                    'user_id'      => $userId,
                    'invite_sent'  => false
                ], 200);
                return;
            }

            // Novo cliente
            $tenantId = 'tenant_' . bin2hex(random_bytes(8));
            $userId = 'user_' . bin2hex(random_bytes(8));
            $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($store)) . '-' . bin2hex(random_bytes(3));
            
            // Senha temporária aleatória segura (não exibida em puro)
            $tempPass = bin2hex(random_bytes(8));
            $passHash = password_hash($tempPass, PASSWORD_DEFAULT);

            // 1. Tenants
            $stmtTenant = $pdo->prepare("
                INSERT INTO tenants (id, name, slug, plan, initial_balance_cents, status)
                VALUES (:id, :name, :slug, :plan, 0, :status)
            ");
            $stmtTenant->execute([
                ':id'     => $tenantId,
                ':name'   => $store,
                ':slug'   => $slug,
                ':plan'   => $offer,
                ':status' => $status
            ]);

            // 2. Users
            $stmtUser = $pdo->prepare("
                INSERT INTO users (id, tenant_id, name, email, password_hash, role)
                VALUES (:id, :tenant_id, :name, :email, :password_hash, 'user')
            ");
            $stmtUser->execute([
                ':id'            => $userId,
                ':tenant_id'     => $tenantId,
                ':name'          => $name,
                ':email'         => $email,
                ':password_hash' => $passHash
            ]);

            // 3. Categorias financeiras padrão
            $defaultCategories = ['Vendas', 'Mercadorias', 'Serviços', 'Marketing', 'Operacional', 'Outros'];
            $stmtCat = $pdo->prepare("INSERT INTO financial_categories (tenant_id, name) VALUES (:tenant_id, :name)");
            foreach ($defaultCategories as $catName) {
                $stmtCat->execute([':tenant_id' => $tenantId, ':name' => $catName]);
            }

            // 4. Client Accesses
            $accessId = 'acc_' . bin2hex(random_bytes(8));
            $productId = $offer === 'plus' ? 'kit-plus' : 'kit-essencial';
            $stmtAcc = $pdo->prepare("
                INSERT INTO client_accesses (id, tenant_id, user_id, product_id, source, external_reference, status, expires_at)
                VALUES (:id, :tenant_id, :user_id, :product_id, 'manual', 'MANUAL-CREATION', 'active', :expires_at)
            ");
            $stmtAcc->execute([
                ':id'         => $accessId,
                ':tenant_id'  => $tenantId,
                ':user_id'    => $userId,
                ':product_id' => $productId,
                ':expires_at' => $expiresAt
            ]);

            $this->logAudit($admin['user_id'], 'create_client', 'client', $tenantId, [
                'name'  => $name,
                'email' => $email,
                'offer' => $offer
            ]);

            $pdo->commit();

            // 5. Envio de Convite por E-mail (se solicitado)
            $inviteSent = false;
            $inviteMessage = '';

            if ($sendInvite) {
                $mailDriver = Config::get('mail.driver', 'mock');
                $smtpHost = Config::get('mail.host', '');

                if ($mailDriver === 'mock' && empty($smtpHost)) {
                    $inviteMessage = "Cliente cadastrado. O convite por e-mail não foi enviado pois o serviço SMTP da Hostinger ainda não está configurado.";
                } else {
                    $sent = $this->mailer->sendWelcome($email, $name, $tempPass, $offer);
                    if ($sent) {
                        $inviteSent = true;
                        $inviteMessage = "Convite de acesso e credenciais enviados por e-mail com sucesso.";
                    } else {
                        $inviteMessage = "Cliente cadastrado, mas houve falha no envio do e-mail. Verifique os logs do servidor.";
                    }
                }
            } else {
                $inviteMessage = "Cliente cadastrado sem envio de e-mail automático.";
            }

            Response::json([
                'success'        => true,
                'message'        => 'Cliente criado com sucesso.',
                'tenant_id'      => $tenantId,
                'user_id'        => $userId,
                'invite_sent'    => $inviteSent,
                'invite_message' => $inviteMessage
            ], 201);

        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::error('SERVER_ERROR', 'Erro ao salvar cliente: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 2. CLIENTES: Ficha detalhada do cliente com permissões e histórico
     */
    public function clientDetail(Request $request, array $params): void
    {
        (new AdminMiddleware())->handle($request);
        $pdo = $this->getPdo();
        $tenantId = (string)($params['id'] ?? '');

        $sql = "
            SELECT t.id as tenant_id, t.name as store_name, t.slug, t.plan, t.status, t.initial_balance_cents, t.created_at,
                   u.id as user_id, u.name as client_name, u.email as client_email
            FROM tenants t
            JOIN users u ON u.tenant_id = t.id
            WHERE t.id = :id
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $tenantId]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$client) {
            Response::error('CLIENT_NOT_FOUND', 'Cliente não encontrado.', 404);
            return;
        }

        // Produtos e acessos vinculados
        $sqlAcc = "
            SELECT ca.id, ca.product_id, ca.source, ca.external_reference, ca.status, ca.expires_at, ca.granted_at,
                   p.name as product_name, p.plan_level
            FROM client_accesses ca
            LEFT JOIN products p ON p.id = ca.product_id
            WHERE ca.tenant_id = :id
            ORDER BY ca.granted_at DESC
        ";
        $stmtAcc = $pdo->prepare($sqlAcc);
        $stmtAcc->execute([':id' => $tenantId]);
        $accesses = $stmtAcc->fetchAll(PDO::FETCH_ASSOC);

        // Histórico de auditoria
        $sqlAudit = "
            SELECT action, target_type, details, created_at
            FROM master_audit_logs
            WHERE target_id = :id
            ORDER BY created_at DESC
            LIMIT 10
        ";
        $stmtAudit = $pdo->prepare($sqlAudit);
        $stmtAudit->execute([':id' => $tenantId]);
        $history = $stmtAudit->fetchAll(PDO::FETCH_ASSOC);

        Response::json([
            'client'   => $client,
            'accesses' => $accesses,
            'history'  => $history
        ]);
    }

    /**
     * 2. CLIENTES: Suspender ou Reativar conta
     */
    public function toggleClientStatus(Request $request, array $params): void
    {
        $admin = (new AdminMiddleware())->handle($request);
        $pdo = $this->getPdo();
        $tenantId = (string)($params['id'] ?? '');

        $stmt = $pdo->prepare("SELECT status FROM tenants WHERE id = :id");
        $stmt->execute([':id' => $tenantId]);
        $current = $stmt->fetchColumn();

        if (!$current) {
            Response::error('CLIENT_NOT_FOUND', 'Cliente não encontrado.', 404);
            return;
        }

        $newStatus = $current === 'active' ? 'suspended' : 'active';
        $update = $pdo->prepare("UPDATE tenants SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $update->execute([':status' => $newStatus, ':id' => $tenantId]);

        $this->logAudit($admin['user_id'], 'toggle_client_status', 'client', $tenantId, [
            'from' => $current,
            'to'   => $newStatus
        ]);

        Response::json([
            'status'  => $newStatus,
            'message' => $newStatus === 'active' ? 'Acesso do cliente reativado.' : 'Acesso do cliente suspenso com sucesso.'
        ]);
    }

    /**
     * 2. CLIENTES: Reenvio de convite com token seguro
     */
    public function resendInvite(Request $request, array $params): void
    {
        $admin = (new AdminMiddleware())->handle($request);
        $pdo = $this->getPdo();
        $tenantId = (string)($params['id'] ?? '');

        $stmt = $pdo->prepare("
            SELECT u.email, u.name, t.plan 
            FROM users u 
            JOIN tenants t ON t.id = u.tenant_id 
            WHERE t.id = :id
        ");
        $stmt->execute([':id' => $tenantId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::error('CLIENT_NOT_FOUND', 'Cliente não encontrado.', 404);
            return;
        }

        $mailDriver = Config::get('mail.driver', 'mock');
        $smtpHost = Config::get('mail.host', '');

        if ($mailDriver === 'mock' && empty($smtpHost)) {
            Response::error('MAIL_NOT_CONFIGURED', 'Serviço de e-mail (SMTP) não configurado. Adicione as credenciais no arquivo .env.php para disparar e-mails.', 400);
            return;
        }

        // Gera token temporário de redefinição
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 7200);

        $stmtReset = $pdo->prepare("
            INSERT INTO password_resets (email, token_hash, expires_at)
            VALUES (:email, :token_hash, :expires_at)
        ");
        $stmtReset->execute([
            ':email'      => $user['email'],
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt
        ]);

        $sent = $this->mailer->sendPasswordReset($user['email'], $token);

        $this->logAudit($admin['user_id'], 'resend_invite', 'client', $tenantId, [
            'email' => $user['email'],
            'sent'  => $sent
        ]);

        if ($sent) {
            Response::json(['message' => 'Convite reenviado com sucesso para ' . $user['email'] . '.']);
        } else {
            Response::error('MAIL_SEND_ERROR', 'Não foi possível enviar o e-mail. Verifique as configurações de SMTP da Hostinger.', 500);
        }
    }

    /**
     * 2. CLIENTES: Revogação de um produto específico (sem remover outros acessos)
     */
    public function revokeAccess(Request $request, array $params): void
    {
        $admin = (new AdminMiddleware())->handle($request);
        $pdo = $this->getPdo();
        $accessId = (string)($params['accessId'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM client_accesses WHERE id = :id");
        $stmt->execute([':id' => $accessId]);
        $acc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$acc) {
            Response::error('ACCESS_NOT_FOUND', 'Acesso não encontrado.', 404);
            return;
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE client_accesses SET status = 'revoked', revoked_at = CURRENT_TIMESTAMP WHERE id = :id")
                ->execute([':id' => $accessId]);

            // Verifica se o cliente ainda possui algum outro acesso Plus ativo
            $checkPlus = $pdo->prepare("
                SELECT COUNT(*) FROM client_accesses 
                WHERE tenant_id = :tenant_id AND product_id = 'kit-plus' AND status = 'active'
            ");
            $checkPlus->execute([':tenant_id' => $acc['tenant_id']]);
            $hasPlus = (int)$checkPlus->fetchColumn() > 0;

            if (!$hasPlus) {
                // Se não tem outro Plus, rebaixa para essential
                $pdo->prepare("UPDATE tenants SET plan = 'essential', updated_at = CURRENT_TIMESTAMP WHERE id = :id")
                    ->execute([':id' => $acc['tenant_id']]);
            }

            $this->logAudit($admin['user_id'], 'revoke_product_access', 'client', $acc['tenant_id'], [
                'access_id'  => $accessId,
                'product_id' => $acc['product_id']
            ]);

            $pdo->commit();

            Response::json(['message' => 'Produto revogado com sucesso. Demais acessos válidos foram preservados.']);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::error('SERVER_ERROR', 'Erro ao revogar produto: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 3. PRODUTOS E ACESSOS: Lista produtos do sistema e estatísticas
     */
    public function listProducts(Request $request): void
    {
        (new AdminMiddleware())->handle($request);
        $pdo = $this->getPdo();

        $sql = "
            SELECT p.id, p.name, p.plan_level, p.description, p.features, p.is_active,
                   (
                       SELECT COUNT(DISTINCT ca.tenant_id) 
                       FROM client_accesses ca 
                       WHERE ca.product_id = p.id AND ca.status = 'active'
                   ) as active_clients
            FROM products p
            ORDER BY p.id ASC
        ";
        $products = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        Response::json($products);
    }

    /**
     * 4. INTEGRAÇÕES: Listagem das integrações configuradas
     */
    public function listIntegrations(Request $request): void
    {
        (new AdminMiddleware())->handle($request);
        $pdo = $this->getPdo();

        $sql = "SELECT * FROM integrations ORDER BY created_at ASC";
        $integrations = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $baseUrl = Config::get('app.url', 'http://localhost:8000');

        $formatted = array_map(function($i) use ($baseUrl) {
            return [
                'id'             => $i['id'],
                'name'           => $i['name'],
                'provider'       => $i['provider'],
                'environment'    => $i['environment'],
                'status'         => $i['status'],
                'webhook_token'  => $i['webhook_token'],
                'webhook_url'    => rtrim($baseUrl, '/') . '/api/webhooks/receiver/' . $i['webhook_token'],
                'offer_mappings' => json_decode($i['offer_mappings'] ?? '{}', true) ?: [],
                'enabled_events' => json_decode($i['enabled_events'] ?? '[]', true) ?: [],
                'last_event_at'  => $i['last_event_at'],
                'last_status'    => $i['last_event_status'],
                'last_summary'   => $i['last_event_summary']
            ];
        }, $integrations);

        Response::json($formatted);
    }

    /**
     * 4. INTEGRAÇÕES: Cadastro de nova integração com geração de URL segura
     */
    public function createIntegration(Request $request): void
    {
        $admin = (new AdminMiddleware())->handle($request);
        $pdo = $this->getPdo();

        $name = trim((string)$request->body('name', ''));
        $provider = trim((string)$request->body('provider', 'greenn'));
        $env = $request->body('environment', 'production') === 'sandbox' ? 'sandbox' : 'production';
        $secret = trim((string)$request->body('secret_key', ''));
        $offerMappings = $request->body('offer_mappings', []);
        $enabledEvents = $request->body('enabled_events', ['order_paid', 'order_refunded']);

        if (empty($name)) {
            Response::error('VALIDATION_ERROR', 'Nome da integração é obrigatório.', 422);
            return;
        }

        $id = 'integ_' . bin2hex(random_bytes(8));
        $webhookToken = bin2hex(random_bytes(24));

        $stmt = $pdo->prepare("
            INSERT INTO integrations (id, name, provider, environment, status, webhook_token, secret_key, offer_mappings, enabled_events)
            VALUES (:id, :name, :provider, :env, 'active', :token, :secret, :mappings, :events)
        ");
        $stmt->execute([
            ':id'       => $id,
            ':name'     => $name,
            ':provider' => $provider,
            ':env'      => $env,
            ':token'    => $webhookToken,
            ':secret'   => !empty($secret) ? $secret : null,
            ':mappings' => json_encode($offerMappings, JSON_UNESCAPED_UNICODE),
            ':events'   => json_encode($enabledEvents, JSON_UNESCAPED_UNICODE)
        ]);

        $this->logAudit($admin['user_id'], 'create_integration', 'integration', $id, [
            'name'     => $name,
            'provider' => $provider
        ]);

        Response::json([
            'id'            => $id,
            'message'       => 'Integração criada com sucesso.',
            'webhook_token' => $webhookToken
        ], 201);
    }

    /**
     * 4. INTEGRAÇÕES: Atualização de integração e mapeamento de ofertas
     */
    public function updateIntegration(Request $request, array $params): void
    {
        $admin = (new AdminMiddleware())->handle($request);
        $pdo = $this->getPdo();
        $id = (string)($params['id'] ?? '');

        $name = trim((string)$request->body('name', ''));
        $provider = trim((string)$request->body('provider', ''));
        $env = $request->body('environment', 'production') === 'sandbox' ? 'sandbox' : 'production';
        $status = $request->body('status', 'active') === 'inactive' ? 'inactive' : 'active';
        $secret = $request->body('secret_key');
        $offerMappings = $request->body('offer_mappings');
        $enabledEvents = $request->body('enabled_events');

        $stmt = $pdo->prepare("SELECT * FROM integrations WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $integ = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$integ) {
            Response::error('INTEGRATION_NOT_FOUND', 'Integração não encontrada.', 404);
            return;
        }

        $fields = [
            'name'        => !empty($name) ? $name : $integ['name'],
            'provider'    => !empty($provider) ? $provider : $integ['provider'],
            'environment' => $env,
            'status'      => $status,
            'updated_at'  => date('Y-m-d H:i:s')
        ];

        if ($secret !== null) {
            $fields['secret_key'] = !empty($secret) ? trim((string)$secret) : null;
        }
        if (is_array($offerMappings)) {
            $fields['offer_mappings'] = json_encode($offerMappings, JSON_UNESCAPED_UNICODE);
        }
        if (is_array($enabledEvents)) {
            $fields['enabled_events'] = json_encode($enabledEvents, JSON_UNESCAPED_UNICODE);
        }

        $setPairs = [];
        $paramsMap = [':id' => $id];
        foreach ($fields as $col => $val) {
            $setPairs[] = "{$col} = :{$col}";
            $paramsMap[":{$col}"] = $val;
        }

        $sql = "UPDATE integrations SET " . implode(', ', $setPairs) . " WHERE id = :id";
        $pdo->prepare($sql)->execute($paramsMap);

        $this->logAudit($admin['user_id'], 'update_integration', 'integration', $id, $fields);

        Response::json(['message' => 'Integração atualizada com sucesso.']);
    }

    /**
     * 4. INTEGRAÇÕES: Alternar status ativo / inativo
     */
    public function toggleIntegration(Request $request, array $params): void
    {
        $admin = (new AdminMiddleware())->handle($request);
        $pdo = $this->getPdo();
        $id = (string)($params['id'] ?? '');

        $stmt = $pdo->prepare("SELECT status FROM integrations WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $current = $stmt->fetchColumn();

        if (!$current) {
            Response::error('INTEGRATION_NOT_FOUND', 'Integração não encontrada.', 404);
            return;
        }

        $newStatus = $current === 'active' ? 'inactive' : 'active';
        $pdo->prepare("UPDATE integrations SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id")
            ->execute([':status' => $newStatus, ':id' => $id]);

        $this->logAudit($admin['user_id'], 'toggle_integration', 'integration', $id, ['status' => $newStatus]);

        Response::json([
            'status'  => $newStatus,
            'message' => $newStatus === 'active' ? 'Integração ativada.' : 'Integração desativada.'
        ]);
    }

    /**
     * 5. EVENTOS E LOGS: Listagem com filtros avançados
     */
    public function listEvents(Request $request): void
    {
        (new AdminMiddleware())->handle($request);
        $pdo = $this->getPdo();

        $integrationId = trim((string)$request->query('integration_id', ''));
        $status = trim((string)$request->query('status', ''));
        $search = trim((string)$request->query('search', ''));
        $page = max(1, (int)$request->query('page', 1));
        $limit = max(1, min(50, (int)$request->query('limit', 20)));
        $offset = ($page - 1) * $limit;

        $conditions = ["1=1"];
        $params = [];

        if (!empty($integrationId)) {
            $conditions[] = "ie.integration_id = :integ_id";
            $params[':integ_id'] = $integrationId;
        }

        if (!empty($status)) {
            $conditions[] = "ie.status = :status";
            $params[':status'] = $status;
        }

        if (!empty($search)) {
            $conditions[] = "(ie.external_reference LIKE :search OR ie.client_email LIKE :search OR ie.event_id LIKE :search)";
            $params[':search'] = "%{$search}%";
        }

        $whereSql = implode(' AND ', $conditions);

        $countSql = "SELECT COUNT(*) FROM integration_events ie WHERE {$whereSql}";
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $sql = "
            SELECT ie.id, ie.integration_id, ie.provider, ie.event_id, ie.event_type, ie.external_reference,
                   ie.client_email, ie.status, ie.failure_reason, ie.attempts, ie.last_attempt_at, ie.created_at,
                   i.name as integration_name
            FROM integration_events ie
            LEFT JOIN integrations i ON i.id = ie.integration_id
            WHERE {$whereSql}
            ORDER BY ie.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::json([
            'events'      => $events,
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int)ceil($total / $limit)
        ]);
    }

    /**
     * 5. EVENTOS E LOGS: Detalhes do evento com payload sanitizado
     */
    public function eventDetail(Request $request, array $params): void
    {
        (new AdminMiddleware())->handle($request);
        $pdo = $this->getPdo();
        $id = (int)($params['id'] ?? 0);

        $sql = "
            SELECT ie.*, i.name as integration_name
            FROM integration_events ie
            LEFT JOIN integrations i ON i.id = ie.integration_id
            WHERE ie.id = :id
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            Response::error('EVENT_NOT_FOUND', 'Evento não encontrado.', 404);
            return;
        }

        // Decodifica e sanitiza payload
        $payload = json_decode($event['payload'] ?? '{}', true) ?: [];
        unset($payload['password'], $payload['token'], $payload['card_cvv'], $payload['card_number']);

        $event['sanitized_payload'] = $payload;
        unset($event['payload']);

        Response::json($event);
    }

    /**
     * 5. EVENTOS E LOGS: Reprocessamento de evento
     */
    public function reprocessEvent(Request $request, array $params): void
    {
        $admin = (new AdminMiddleware())->handle($request);
        $pdo = $this->getPdo();
        $id = (int)($params['id'] ?? 0);

        $stmt = $pdo->prepare("SELECT * FROM integration_events WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            Response::error('EVENT_NOT_FOUND', 'Evento não encontrado.', 404);
            return;
        }

        // Tenta reprocessar usando o webhook controller
        $receiver = new WebhookReceiverController();
        $result = $receiver->processEventRecord($event);

        $this->logAudit($admin['user_id'], 'reprocess_event', 'event', (string)$id, $result);

        if ($result['success']) {
            Response::json(['message' => 'Evento reprocessado com sucesso: ' . $result['message']]);
        } else {
            Response::error('REPROCESS_FAILED', 'Falha ao reprocessar: ' . $result['message'], 400);
        }
    }

    /**
     * 6. CONFIGURAÇÕES: Obter e salvar preferências do sistema
     */
    public function getSettings(Request $request): void
    {
        (new AdminMiddleware())->handle($request);
        $pdo = $this->getPdo();

        $rows = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);

        Response::json([
            'support_email'    => $rows['support_email'] ?? 'suporte@vtd.com.br',
            'support_whatsapp' => $rows['support_whatsapp'] ?? '11999999999',
            'mail_driver'      => Config::get('mail.driver', 'mock'),
            'mail_host'        => Config::get('mail.host', 'smtp.hostinger.com'),
            'app_url'          => Config::get('app.url', 'http://localhost:8000')
        ]);
    }

    public function saveSettings(Request $request): void
    {
        $admin = (new AdminMiddleware())->handle($request);
        $pdo = $this->getPdo();

        $supportEmail = trim((string)$request->body('support_email', ''));
        $supportWa = trim((string)$request->body('support_whatsapp', ''));

        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, updated_at)
            VALUES (:k, :v, CURRENT_TIMESTAMP)
            ON CONFLICT(setting_key) DO UPDATE SET setting_value = :v, updated_at = CURRENT_TIMESTAMP
        ");
        if (Config::get('db.driver') === 'mysql') {
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, updated_at)
                VALUES (:k, :v, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP
            ");
        }

        if (!empty($supportEmail)) $stmt->execute([':k' => 'support_email', ':v' => $supportEmail]);
        if (!empty($supportWa)) $stmt->execute([':k' => 'support_whatsapp', ':v' => $supportWa]);

        $this->logAudit($admin['user_id'], 'save_settings', 'settings', 'global', [
            'email' => $supportEmail,
            'wa'    => $supportWa
        ]);

        Response::json(['message' => 'Configurações atualizadas com sucesso.']);
    }
}
