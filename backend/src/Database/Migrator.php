<?php
declare(strict_types=1);

namespace VTD\Database;

use PDO;
use VTD\Config\Config;

class Migrator
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::getInstance();
    }

    public function runMigrations(): void
    {
        // Explicit CLI migration only. Never invoked by an HTTP request.
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(191) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $files = glob(dirname(__DIR__, 2) . '/migrations/*.sql');
        sort($files);
        foreach ($files as $file) {
            $version = basename($file);
            $check = $this->pdo->prepare('SELECT version FROM schema_migrations WHERE version = ?');
            $check->execute([$version]);
            if ($check->fetchColumn()) continue;
            $sql = preg_replace('/--[^\r\n]*/', '', file_get_contents($file));
            if ($driver === 'sqlite') {
                $sql = str_replace('INTEGER PRIMARY KEY AUTO_INCREMENT', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
                $sql = preg_replace('/\bENUM\([^)]*\)/i', 'VARCHAR(30)', $sql);
                $sql = preg_replace('/\bAUTO_INCREMENT\b/i', '', $sql);
                $sql = preg_replace('/ENGINE\s*=\s*InnoDB[^;]*;/i', ';', $sql);
            }
            // Legacy installations have no migration history. Inspect additive DDL
            // so an existing or partially applied 003 can be resumed safely.
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                if (preg_match('/^ALTER TABLE (\w+) ADD COLUMN (\w+)/i', $stmt, $m) && $this->hasColumn($m[1], $m[2])) continue;
                if (preg_match('/^CREATE (?:UNIQUE )?INDEX (\w+) ON (\w+)/i', $stmt, $m) && $this->hasIndex($m[2], $m[1])) continue;
                $extraIndexes = [];
                if ($driver === 'sqlite' && preg_match('/^CREATE TABLE IF NOT EXISTS (\w+)/i', $stmt, $table)) {
                    $stmt = preg_replace_callback('/,\s*INDEX (\w+)\s*\(([^)]+)\)/i', function($match) use (&$extraIndexes, $table) {
                        $extraIndexes[] = 'CREATE INDEX IF NOT EXISTS '.$match[1].' ON '.$table[1].' ('.$match[2].')';
                        return '';
                    }, $stmt);
                }
                $this->pdo->exec($stmt);
                foreach ($extraIndexes as $indexSql) $this->pdo->exec($indexSql);
            }
            $this->pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            foreach ($this->pdo->query("PRAGMA table_info($table)")->fetchAll() as $row) if ($row['name'] === $column) return true;
            return false;
        }
        $s = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
        $s->execute([$table, $column]); return (int)$s->fetchColumn() > 0;
    }

    private function hasIndex(string $table, string $index): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $s = $this->pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND name=?");
            $s->execute([$index]); return (int)$s->fetchColumn() > 0;
        }
        $s = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?');
        $s->execute([$table, $index]); return (int)$s->fetchColumn() > 0;
    }

    public function runSeeds(): void
    {
        $jsonFile = dirname(__DIR__, 2) . '/seeds/initial_data.json';
        if (!file_exists($jsonFile)) {
            throw new \RuntimeException("Arquivo initial_data.json não encontrado.");
        }

        $data = json_decode(file_get_contents($jsonFile), true);

        if (Config::get('db.driver') === 'sqlite') {
            $this->pdo->exec('PRAGMA foreign_keys = OFF;');
        }

        $this->pdo->beginTransaction();

        try {
            // 1. Tenants iniciais
            $sqlTenant = Config::get('db.driver') === 'mysql' ? "
                INSERT INTO tenants (id, name, slug, plan, initial_balance_cents, status)
                VALUES (:id, :name, :slug, :plan, :initial_balance_cents, :status)
                ON DUPLICATE KEY UPDATE name=VALUES(name), plan=VALUES(plan), initial_balance_cents=VALUES(initial_balance_cents)
            " : "
                INSERT OR REPLACE INTO tenants (id, name, slug, plan, initial_balance_cents, status)
                VALUES (:id, :name, :slug, :plan, :initial_balance_cents, :status)
            ";
            $stmtTenant = $this->pdo->prepare($sqlTenant);

            $stmtTenant->execute([
                ':id' => 'demo-store-1',
                ':name' => 'Minha loja',
                ':slug' => 'minha-loja',
                ':plan' => 'plus',
                ':initial_balance_cents' => 200000,
                ':status' => 'active'
            ]);

            $stmtTenant->execute([
                ':id' => 'demo-store-2',
                ':name' => 'Loja Ana',
                ':slug' => 'loja-ana',
                ':plan' => 'essential',
                ':initial_balance_cents' => 0,
                ':status' => 'active'
            ]);

            // 2. Usuários iniciais
            $passHash = password_hash('123456', PASSWORD_DEFAULT);
            $sqlUser = Config::get('db.driver') === 'mysql'
                ? "INSERT INTO users (id, tenant_id, name, email, password_hash, role)
                   VALUES (:id, :tenant_id, :name, :email, :password_hash, :role)
                   ON DUPLICATE KEY UPDATE name=VALUES(name), role=VALUES(role)"
                : "INSERT OR REPLACE INTO users (id, tenant_id, name, email, password_hash, role)
                   VALUES (:id, :tenant_id, :name, :email, :password_hash, :role)";

            $stmtUser = $this->pdo->prepare($sqlUser);

            $stmtUser->execute([
                ':id' => 'user-1',
                ':tenant_id' => 'demo-store-1',
                ':name' => 'João Silva',
                ':email' => 'demo@exemplo.com',
                ':password_hash' => $passHash,
                ':role' => 'admin'
            ]);

            $stmtUser->execute([
                ':id' => 'user-2',
                ':tenant_id' => 'demo-store-2',
                ':name' => 'Ana Souza',
                ':email' => 'ana@example.com',
                ':password_hash' => $passHash,
                ':role' => 'user'
            ]);

            // 3. Fornecedores
            $sqlSupp = Config::get('db.driver') === 'mysql'
                ? "INSERT INTO suppliers (id, name, brands, products, category, segment, site, instagram, instagram_handle, whatsapp, phones, email, facebook, youtube, linkedin, tiktok, catalogs, contact_url, score, notes, is_active)
                   VALUES (:id, :name, :brands, :products, :category, :segment, :site, :instagram, :instagram_handle, :whatsapp, :phones, :email, :facebook, :youtube, :linkedin, :tiktok, :catalogs, :contact_url, :score, :notes, 1)
                   ON DUPLICATE KEY UPDATE name=VALUES(name), products=VALUES(products)"
                : "INSERT OR REPLACE INTO suppliers (id, name, brands, products, category, segment, site, instagram, instagram_handle, whatsapp, phones, email, facebook, youtube, linkedin, tiktok, catalogs, contact_url, score, notes, is_active)
                   VALUES (:id, :name, :brands, :products, :category, :segment, :site, :instagram, :instagram_handle, :whatsapp, :phones, :email, :facebook, :youtube, :linkedin, :tiktok, :catalogs, :contact_url, :score, :notes, 1)";

            $stmtSupp = $this->pdo->prepare($sqlSupp);

            foreach ($data['suppliers'] as $s) {
                $stmtSupp->execute([
                    ':id'               => (string)$s['id'],
                    ':name'             => (string)$s['nome'],
                    ':brands'           => (string)($s['marcas'] ?? ''),
                    ':products'         => (string)($s['produtos'] ?? ''),
                    ':category'         => (string)($s['categoria'] ?? ''),
                    ':segment'          => (string)($s['seg'] ?? ''),
                    ':site'             => (string)($s['site'] ?? ''),
                    ':instagram'        => (string)($s['ig'] ?? ''),
                    ':instagram_handle' => (string)($s['igh'] ?? ''),
                    ':whatsapp'         => json_encode($s['wa'] ?? []),
                    ':phones'           => json_encode($s['tel'] ?? []),
                    ':email'            => (string)($s['email'] ?? ''),
                    ':facebook'         => (string)($s['fb'] ?? ''),
                    ':youtube'          => (string)($s['yt'] ?? ''),
                    ':linkedin'         => (string)($s['li'] ?? ''),
                    ':tiktok'           => (string)($s['tt'] ?? ''),
                    ':catalogs'         => json_encode($s['cat'] ?? []),
                    ':contact_url'      => (string)($s['contato'] ?? ''),
                    ':score'            => (int)($s['score'] ?? 0),
                    ':notes'            => (string)($s['nota'] ?? '')
                ]);
            }

            // 4. Capítulos do Guia
            $sqlChap = Config::get('db.driver') === 'mysql'
                ? "INSERT INTO guide_chapters (id, chapter_number, name, title, body, task)
                   VALUES (:id, :chapter_number, :name, :title, :body, :task)
                   ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body)"
                : "INSERT OR REPLACE INTO guide_chapters (id, chapter_number, name, title, body, task)
                   VALUES (:id, :chapter_number, :name, :title, :body, :task)";

            $stmtChap = $this->pdo->prepare($sqlChap);

            foreach ($data['chapters'] as $idx => $c) {
                $chapNum = $idx + 1;
                $stmtChap->execute([
                    ':id'             => $chapNum,
                    ':chapter_number' => $chapNum,
                    ':name'           => (string)$c['name'],
                    ':title'          => (string)$c['title'],
                    ':body'           => (string)$c['body'],
                    ':task'           => (string)$c['task']
                ]);
            }

            // 5. Meses do Calendário
            $sqlMonth = Config::get('db.driver') === 'mysql'
                ? "INSERT INTO calendar_months (id, month_number, name, short_name, `lead`, buy_tip, practical_tip, dates, suggested_items)
                   VALUES (:id, :month_number, :name, :short_name, :lead, :buy_tip, :practical_tip, :dates, :suggested_items)
                   ON DUPLICATE KEY UPDATE name=VALUES(name), `lead`=VALUES(`lead`)"
                : "INSERT OR REPLACE INTO calendar_months (id, month_number, name, short_name, `lead`, buy_tip, practical_tip, dates, suggested_items)
                   VALUES (:id, :month_number, :name, :short_name, :lead, :buy_tip, :practical_tip, :dates, :suggested_items)";

            $stmtMonth = $this->pdo->prepare($sqlMonth);

            foreach ($data['months'] as $idx => $m) {
                $monthNum = $idx; // 0 a 11
                $stmtMonth->execute([
                    ':id'              => $monthNum,
                    ':month_number'    => $monthNum,
                    ':name'            => (string)$m['n'],
                    ':short_name'      => (string)$m['s'],
                    ':lead'            => (string)$m['lead'],
                    ':buy_tip'         => (string)$m['buy'],
                    ':practical_tip'   => (string)$m['tip'],
                    ':dates'           => json_encode($m['dates']),
                    ':suggested_items' => json_encode($m['items'])
                ]);
            }

            // 6. Categorias Financeiras padrão para o Tenant 1
            $defaultCategories = ['Serviços', 'Mercadorias', 'Moradia', 'Alimentação', 'Transporte', 'Outros'];
            $sqlCat = Config::get('db.driver') === 'mysql' ? "
                INSERT IGNORE INTO financial_categories (tenant_id, name)
                VALUES (:tenant_id, :name)
            " : "
                INSERT OR IGNORE INTO financial_categories (tenant_id, name)
                VALUES (:tenant_id, :name)
            ";
            $stmtCat = $this->pdo->prepare($sqlCat);

            foreach ($defaultCategories as $catName) {
                $stmtCat->execute([':tenant_id' => 'demo-store-1', ':name' => $catName]);
            }

            // 7. Lançamentos Financeiros de exemplo (Outubro 2026)
            $catMap = [];
            $rows = $this->pdo->query("SELECT id, name FROM financial_categories WHERE tenant_id = 'demo-store-1'")->fetchAll();
            foreach ($rows as $r) {
                $catMap[$r['name']] = (int)$r['id'];
            }

            $demoTxs = [
                ['t1', 'Pagamento de cliente', 'income', 400000, 'Serviços', '2026-10-05', 'paid'],
                ['t2', 'Vendas recebidas', 'income', 450000, 'Mercadorias', '2026-10-18', 'paid'],
                ['t3', 'Aluguel e despesas da loja', 'expense', 200000, 'Moradia', '2026-10-10', 'paid'],
                ['t4', 'Compras de alimentação', 'expense', 150000, 'Alimentação', '2026-10-15', 'paid'],
                ['t5', 'Deslocamentos', 'expense', 80000, 'Transporte', '2026-10-18', 'paid'],
                ['t6', 'Outras despesas', 'expense', 150000, 'Outros', '2026-10-21', 'paid'],
                ['t7', 'Energia elétrica', 'expense', 20000, 'Moradia', '2026-10-25', 'pending'],
                ['t8', 'Cartão de crédito', 'expense', 65000, 'Outros', '2026-10-28', 'pending'],
                ['t9', 'Pedido a receber', 'income', 120000, 'Mercadorias', '2026-10-30', 'pending'],
            ];

            $sqlTx = Config::get('db.driver') === 'mysql'
                ? "INSERT INTO financial_transactions (id, tenant_id, description, type, value_cents, category_id, date, status, created_by_user_id)
                   VALUES (:id, :tenant_id, :description, :type, :value_cents, :category_id, :date, :status, :created_by_user_id)
                   ON DUPLICATE KEY UPDATE description=VALUES(description)"
                : "INSERT OR REPLACE INTO financial_transactions (id, tenant_id, description, type, value_cents, category_id, date, status, created_by_user_id)
                   VALUES (:id, :tenant_id, :description, :type, :value_cents, :category_id, :date, :status, :created_by_user_id)";

            $stmtTx = $this->pdo->prepare($sqlTx);

            foreach ($demoTxs as $dtx) {
                $stmtTx->execute([
                    ':id'                 => $dtx[0],
                    ':tenant_id'          => 'demo-store-1',
                    ':description'        => $dtx[1],
                    ':type'               => $dtx[2],
                    ':value_cents'        => $dtx[3],
                    ':category_id'        => $catMap[$dtx[4]] ?? 1,
                    ':date'               => $dtx[5],
                    ':status'             => $dtx[6],
                    ':created_by_user_id' => 'user-1'
                ]);
            }

            // 8. Produtos padrão do sistema
            $products = [
                [
                    'id'          => 'kit-essencial',
                    'name'        => 'Kit Essencial',
                    'plan_level'  => 'essential',
                    'description' => 'Acesso completo ao catálogo de fornecedores, favoritos, calendário comercial e guia de estudos.',
                    'features'    => json_encode(['suppliers', 'favorites', 'calendar', 'guide'])
                ],
                [
                    'id'          => 'kit-plus',
                    'name'        => 'Kit + Financeiro',
                    'plan_level'  => 'plus',
                    'description' => 'Tudo do Kit Essencial mais o painel completo de controle financeiro com projeções e conciliação.',
                    'features'    => json_encode(['suppliers', 'favorites', 'calendar', 'guide', 'finance'])
                ]
            ];

            $sqlProd = Config::get('db.driver') === 'mysql'
                ? "INSERT INTO products (id, name, plan_level, description, features)
                   VALUES (:id, :name, :plan_level, :description, :features)
                   ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description)"
                : "INSERT OR REPLACE INTO products (id, name, plan_level, description, features)
                   VALUES (:id, :name, :plan_level, :description, :features)";
            $stmtProd = $this->pdo->prepare($sqlProd);
            foreach ($products as $p) {
                $stmtProd->execute([
                    ':id'          => $p['id'],
                    ':name'        => $p['name'],
                    ':plan_level'  => $p['plan_level'],
                    ':description' => $p['description'],
                    ':features'    => $p['features']
                ]);
            }

            // 9. Acessos iniciais vinculados
            $accesses = [
                ['acc-1', 'demo-store-1', 'user-1', 'kit-plus', 'manual', 'INITIAL-ADMIN', 'active'],
                ['acc-2', 'demo-store-2', 'user-2', 'kit-essencial', 'manual', 'INITIAL-USER', 'active']
            ];
            $sqlAcc = Config::get('db.driver') === 'mysql'
                ? "INSERT INTO client_accesses (id, tenant_id, user_id, product_id, source, external_reference, status)
                   VALUES (:id, :tenant_id, :user_id, :product_id, :source, :external_reference, :status)
                   ON DUPLICATE KEY UPDATE status=VALUES(status)"
                : "INSERT OR REPLACE INTO client_accesses (id, tenant_id, user_id, product_id, source, external_reference, status)
                   VALUES (:id, :tenant_id, :user_id, :product_id, :source, :external_reference, :status)";
            $stmtAcc = $this->pdo->prepare($sqlAcc);
            foreach ($accesses as $acc) {
                $stmtAcc->execute([
                    ':id'                 => $acc[0],
                    ':tenant_id'          => $acc[1],
                    ':user_id'            => $acc[2],
                    ':product_id'         => $acc[3],
                    ':source'             => $acc[4],
                    ':external_reference' => $acc[5],
                    ':status'             => $acc[6]
                ]);
            }

            // 10. Integrações padrão (Checkout principal e Checkout alternativo)
            $integrations = [
                [
                    'id'                 => 'integ-checkout-principal',
                    'name'               => 'Checkout principal',
                    'provider'           => 'greenn',
                    'environment'        => 'production',
                    'status'             => 'active',
                    'webhook_token'      => 'token_greenn_principal_live',
                    'offer_mappings'     => json_encode(['offer_plus_01' => 'kit-plus', 'offer_ess_01' => 'kit-essencial']),
                    'enabled_events'     => json_encode(['order_paid', 'order_refunded']),
                    'last_event_at'      => date('Y-m-d H:i:s', time() - 300),
                    'last_event_status'  => 'success',
                    'last_event_summary' => 'Compra aprovada → liberar acesso'
                ],
                [
                    'id'                 => 'integ-checkout-secundario',
                    'name'               => 'Checkout alternativo',
                    'provider'           => 'kiwify',
                    'environment'        => 'production',
                    'status'             => 'inactive',
                    'webhook_token'      => 'token_kiwify_secundario_live',
                    'offer_mappings'     => json_encode(['kw_plus' => 'kit-plus']),
                    'enabled_events'     => json_encode(['order_approved']),
                    'last_event_at'      => date('Y-m-d H:i:s', time() - 7200),
                    'last_event_status'  => 'ignored',
                    'last_event_summary' => 'Aguardando ativação'
                ]
            ];
            $sqlInteg = Config::get('db.driver') === 'mysql'
                ? "INSERT INTO integrations (id, name, provider, environment, status, webhook_token, offer_mappings, enabled_events, last_event_at, last_event_status, last_event_summary)
                   VALUES (:id, :name, :provider, :environment, :status, :webhook_token, :offer_mappings, :enabled_events, :last_event_at, :last_event_status, :last_event_summary)
                   ON DUPLICATE KEY UPDATE name=VALUES(name), status=VALUES(status)"
                : "INSERT OR REPLACE INTO integrations (id, name, provider, environment, status, webhook_token, offer_mappings, enabled_events, last_event_at, last_event_status, last_event_summary)
                   VALUES (:id, :name, :provider, :environment, :status, :webhook_token, :offer_mappings, :enabled_events, :last_event_at, :last_event_status, :last_event_summary)";
            $stmtInteg = $this->pdo->prepare($sqlInteg);
            foreach ($integrations as $integ) {
                $stmtInteg->execute($integ);
            }

            // 11. Eventos de exemplo para visualização imediata
            $events = [
                [
                    'integration_id'     => 'integ-checkout-principal',
                    'provider'           => 'greenn',
                    'event_id'           => 'evt_demo_101',
                    'event_type'         => 'order_paid',
                    'external_reference' => 'PED-9821',
                    'client_email'       => 'ana@example.com',
                    'payload'            => json_encode(['order_id' => 'PED-9821', 'buyer' => ['name' => 'Ana Souza', 'email' => 'ana@example.com'], 'offer' => 'offer_plus_01']),
                    'status'             => 'success',
                    'failure_reason'     => null,
                    'created_at'         => date('Y-m-d H:i:s', time() - 1800)
                ],
                [
                    'integration_id'     => 'integ-checkout-principal',
                    'provider'           => 'greenn',
                    'event_id'           => 'evt_demo_102',
                    'event_type'         => 'invite_sent',
                    'external_reference' => 'INV-3341',
                    'client_email'       => 'carla@example.com',
                    'payload'            => json_encode(['email' => 'carla@example.com', 'action' => 'invite_sent']),
                    'status'             => 'success',
                    'failure_reason'     => null,
                    'created_at'         => date('Y-m-d H:i:s', time() - 3600)
                ],
                [
                    'integration_id'     => 'integ-checkout-principal',
                    'provider'           => 'greenn',
                    'event_id'           => 'evt_demo_103',
                    'event_type'         => 'webhook_received',
                    'external_reference' => 'PED-UNKNOWN-01',
                    'client_email'       => 'comprador@desconhecido.com',
                    'payload'            => json_encode(['order_id' => 'PED-UNKNOWN-01', 'offer_code' => 'oferta_sem_vinculo']),
                    'status'             => 'failed',
                    'failure_reason'     => 'Produto sem vínculo com oferta interna.',
                    'created_at'         => date('Y-m-d H:i:s', time() - 7200)
                ]
            ];
            $insertEvent = Config::get('db.driver') === 'mysql' ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
            $stmtEvt = $this->pdo->prepare("$insertEvent INTO integration_events
                (integration_id, provider, event_id, event_type, external_reference, client_email, payload, status, failure_reason, created_at)
                VALUES (:integration_id, :provider, :event_id, :event_type, :external_reference, :client_email, :payload, :status, :failure_reason, :created_at)
            ");
            foreach ($events as $evt) {
                $stmtEvt->execute($evt);
            }

            $this->pdo->commit();
            if (Config::get('db.driver') === 'sqlite') {
                $this->pdo->exec('PRAGMA foreign_keys = ON;');
            }
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            if (Config::get('db.driver') === 'sqlite') {
                $this->pdo->exec('PRAGMA foreign_keys = ON;');
            }
            throw $e;
        }
    }
}
