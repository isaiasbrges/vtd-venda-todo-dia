<?php
declare(strict_types=1);

namespace VTD\Controllers;

use VTD\Core\Request;
use VTD\Core\Response;
use VTD\Database\Connection;
use VTD\Middlewares\AdminMiddleware;
use VTD\Services\MailerService;

class AdminController
{
    private MailerService $mailer;

    public function __construct(?MailerService $mailer = null)
    {
        $this->mailer = $mailer ?? new MailerService();
    }

    public function overview(Request $request): void
    {
        (new AdminMiddleware())->handle($request);
        $pdo = Connection::getInstance();

        $suppliers = (int)$pdo->query("SELECT COUNT(*) FROM suppliers WHERE is_active = 1")->fetchColumn();
        $clients = (int)$pdo->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
        $chapters = (int)$pdo->query("SELECT COUNT(*) FROM guide_chapters")->fetchColumn();
        $months = (int)$pdo->query("SELECT COUNT(*) FROM calendar_months")->fetchColumn();

        Response::json([
            'suppliers' => $suppliers,
            'clients'   => $clients,
            'chapters'  => $chapters,
            'months'    => $months
        ]);
    }

    public function clients(Request $request): void
    {
        (new AdminMiddleware())->handle($request);
        $pdo = Connection::getInstance();

        $sql = "
            SELECT t.id, t.name as store, t.slug, t.plan, t.status, t.created_at,
                   u.name, u.email, u.id as user_id
            FROM tenants t
            JOIN users u ON u.tenant_id = t.id
            ORDER BY t.created_at DESC
        ";
        $rows = $pdo->query($sql)->fetchAll();

        $list = array_map(function($r) {
            return [
                'id'         => $r['id'],
                'user_id'    => $r['user_id'],
                'name'       => $r['name'],
                'email'      => $r['email'],
                'store'      => $r['store'],
                'plan'       => $r['plan'],
                'active'     => $r['status'] === 'active',
                'created_at' => $r['created_at']
            ];
        }, $rows);

        Response::json($list);
    }

    public function provisionClient(array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        $email = trim(strtolower((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');
        $store = trim((string)($data['store'] ?? ''));
        $plan = ($data['plan'] ?? 'plus') === 'essential' ? 'essential' : 'plus';

        if (empty($name) || empty($email) || empty($password)) {
            throw new \InvalidArgumentException("Nome, e-mail e senha são obrigatórios.");
        }

        if (empty($store)) {
            $store = 'Loja de ' . explode(' ', $name)[0];
        }

        $pdo = Connection::getInstance();

        // Verifica se email já existe
        $check = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $check->execute([':email' => $email]);
        if ($check->fetch()) {
            throw new \RuntimeException("Este e-mail já está cadastrado.");
        }

        $tenantId = 'tenant_' . bin2hex(random_bytes(8));
        $userId = 'user_' . bin2hex(random_bytes(8));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($store)) . '-' . bin2hex(random_bytes(3));
        $passHash = password_hash($password, PASSWORD_DEFAULT);

        $pdo->beginTransaction();
        try {
            // Cria Tenant
            $stmtT = $pdo->prepare("
                INSERT INTO tenants (id, name, slug, plan, initial_balance_cents, status)
                VALUES (:id, :name, :slug, :plan, 0, 'active')
            ");
            $stmtT->execute([
                ':id'   => $tenantId,
                ':name' => $store,
                ':slug' => $slug,
                ':plan' => $plan
            ]);

            // Cria Usuário
            $stmtU = $pdo->prepare("
                INSERT INTO users (id, tenant_id, name, email, password_hash, role)
                VALUES (:id, :tid, :name, :email, :pass, 'user')
            ");
            $stmtU->execute([
                ':id'    => $userId,
                ':tid'   => $tenantId,
                ':name'  => $name,
                ':email' => $email,
                ':pass'  => $passHash
            ]);

            // Cria Categorias Financeiras padrão
            $defaultCategories = ['Serviços', 'Mercadorias', 'Moradia', 'Alimentação', 'Transporte', 'Outros'];
            $stmtCat = $pdo->prepare("
                INSERT OR IGNORE INTO financial_categories (tenant_id, name)
                VALUES (:tid, :cat)
            ");
            if (\VTD\Config\Config::get('db.driver') === 'mysql') {
                $stmtCat = $pdo->prepare("
                    INSERT IGNORE INTO financial_categories (tenant_id, name)
                    VALUES (:tid, :cat)
                ");
            }
            foreach ($defaultCategories as $c) {
                $stmtCat->execute([':tid' => $tenantId, ':cat' => $c]);
            }

            $pdo->commit();

            // Dispara e-mail com as credenciais
            $this->mailer->sendWelcomeCredentials($email, $name, $password, $plan);

            return [
                'user' => [
                    'id'    => $userId,
                    'name'  => $name,
                    'email' => $email,
                    'role'  => 'user'
                ],
                'tenant' => [
                    'id'     => $tenantId,
                    'name'   => $store,
                    'slug'   => $slug,
                    'plan'   => $plan,
                    'status' => 'active'
                ]
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function createClient(Request $request): void
    {
        (new AdminMiddleware())->handle($request);

        try {
            $client = $this->provisionClient($request->body());
            Response::json($client, 201);
        } catch (\Throwable $e) {
            Response::error('CLIENT_CREATE_ERROR', $e->getMessage(), 400);
        }
    }

    public function updateClient(Request $request, array $params): void
    {
        (new AdminMiddleware())->handle($request);
        $id = $params['id'] ?? '';

        $pdo = Connection::getInstance();
        $stmtT = $pdo->prepare("SELECT * FROM tenants WHERE id = :id");
        $stmtT->execute([':id' => $id]);
        $tenant = $stmtT->fetch();

        if (!$tenant) {
            Response::error('NOT_FOUND', 'Cliente/Loja não encontrado.', 404);
        }

        $name = trim((string)$request->body('name', ''));
        $plan = ($request->body('plan') === 'plus') ? 'plus' : 'essential';
        $active = $request->body('active');
        $status = ($active === true || $active === 'true' || $active === 'active') ? 'active' : 'suspended';
        $password = (string)$request->body('password', '');

        $pdo->beginTransaction();
        try {
            $stmtUpdateT = $pdo->prepare("UPDATE tenants SET plan = :plan, status = :status WHERE id = :id");
            $stmtUpdateT->execute([':plan' => $plan, ':status' => $status, ':id' => $id]);

            if (!empty($name)) {
                $stmtUpdateU = $pdo->prepare("UPDATE users SET name = :name WHERE tenant_id = :tid");
                $stmtUpdateU->execute([':name' => $name, ':tid' => $id]);
            }

            if (!empty($password) && strlen($password) >= 6) {
                $stmtPass = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE tenant_id = :tid");
                $stmtPass->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':tid' => $id]);
            }

            $pdo->commit();
            Response::json(['message' => 'Cliente atualizado com sucesso.']);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::error('UPDATE_ERROR', $e->getMessage(), 500);
        }
    }

    public function toggleClientStatus(string $tenantId): string
    {
        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("SELECT status FROM tenants WHERE id = :id");
        $stmt->execute([':id' => $tenantId]);
        $curr = $stmt->fetchColumn();

        $newStatus = ($curr === 'active') ? 'suspended' : 'active';
        $stmtUp = $pdo->prepare("UPDATE tenants SET status = :status WHERE id = :id");
        $stmtUp->execute([':status' => $newStatus, ':id' => $tenantId]);

        return $newStatus;
    }

    public function toggleClient(Request $request, array $params): void
    {
        (new AdminMiddleware())->handle($request);
        $id = $params['id'] ?? '';

        try {
            $status = $this->toggleClientStatus($id);
            Response::json(['status' => $status]);
        } catch (\Throwable $e) {
            Response::error('TOGGLE_ERROR', $e->getMessage(), 400);
        }
    }

    public function updateSupplier(Request $request, array $params): void
    {
        (new AdminMiddleware())->handle($request);
        $id = $params['id'] ?? '';

        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("
            UPDATE suppliers
            SET name = :name, segment = :seg, products = :produtos, category = :cat, site = :site,
                instagram = :ig, whatsapp = :wa, catalogs = :catalogs, email = :email, phones = :tel, notes = :nota
            WHERE id = :id
        ");
        $stmt->execute([
            ':name'     => (string)$request->body('nome', ''),
            ':seg'      => (string)$request->body('seg', ''),
            ':produtos' => (string)$request->body('produtos', ''),
            ':cat'      => (string)$request->body('categoria', ''),
            ':site'     => (string)$request->body('site', ''),
            ':ig'       => (string)$request->body('ig', ''),
            ':wa'       => json_encode($request->body('wa') ?: []),
            ':catalogs' => json_encode($request->body('cat') ?: []),
            ':email'    => (string)$request->body('email', ''),
            ':tel'      => json_encode($request->body('tel') ?: []),
            ':nota'     => (string)$request->body('nota', ''),
            ':id'       => $id
        ]);

        Response::json(['message' => 'Fornecedor atualizado com sucesso.']);
    }

    public function updateChapter(Request $request, array $params): void
    {
        (new AdminMiddleware())->handle($request);
        $num = (int)($params['index'] ?? 0);

        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("UPDATE guide_chapters SET title = :title, body = :body, task = :task WHERE chapter_number = :num");
        $stmt->execute([
            ':title' => (string)$request->body('title', ''),
            ':body'  => (string)$request->body('body', ''),
            ':task'  => (string)$request->body('task', ''),
            ':num'   => $num + 1
        ]);

        Response::json(['message' => 'Capítulo atualizado com sucesso.']);
    }

    public function updateMonth(Request $request, array $params): void
    {
        (new AdminMiddleware())->handle($request);
        $num = (int)($params['index'] ?? 0);

        $pdo = Connection::getInstance();
        $stmt = $pdo->prepare("
            UPDATE calendar_months
            SET lead = :lead, buy_tip = :buy, practical_tip = :tip, suggested_items = :items
            WHERE month_number = :num
        ");
        $stmt->execute([
            ':lead'  => (string)$request->body('lead', ''),
            ':buy'   => (string)$request->body('buy', ''),
            ':tip'   => (string)$request->body('tip', ''),
            ':items' => json_encode($request->body('items') ?: []),
            ':num'   => $num
        ]);

        Response::json(['message' => 'Mês do calendário atualizado com sucesso.']);
    }
}
