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
        $migrationFile = dirname(__DIR__, 2) . '/migrations/001_initial_schema.sql';
        $sql = file_get_contents($migrationFile);

        $driver = Config::get('db.driver', 'mysql');

        if ($driver === 'sqlite') {
            // Ajustes para dialeto SQLite
            $sql = str_replace('INTEGER PRIMARY KEY AUTO_INCREMENT', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
            $sql = str_replace('AUTO_INCREMENT', '', $sql);
            $sql = preg_replace('/ENGINE\s*=\s*InnoDB.*?;/i', ';', $sql);
        }

        // Divide instruções por ponto e vírgula
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                $this->pdo->exec($stmt);
            }
        }
    }

    public function runSeeds(): void
    {
        $jsonFile = dirname(__DIR__, 2) . '/seeds/initial_data.json';
        if (!file_exists($jsonFile)) {
            throw new \RuntimeException("Arquivo initial_data.json não encontrado.");
        }

        $data = json_decode(file_get_contents($jsonFile), true);

        $this->pdo->beginTransaction();

        try {
            // 1. Tenants iniciais
            $stmtTenant = $this->pdo->prepare("
                INSERT OR REPLACE INTO tenants (id, name, slug, plan, initial_balance_cents, status)
                VALUES (:id, :name, :slug, :plan, :initial_balance_cents, :status)
            ");
            // Se for MySQL usamos ON DUPLICATE KEY UPDATE
            if (Config::get('db.driver') === 'mysql') {
                $stmtTenant = $this->pdo->prepare("
                    INSERT INTO tenants (id, name, slug, plan, initial_balance_cents, status)
                    VALUES (:id, :name, :slug, :plan, :initial_balance_cents, :status)
                    ON DUPLICATE KEY UPDATE name=VALUES(name), plan=VALUES(plan), initial_balance_cents=VALUES(initial_balance_cents)
                ");
            }

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
                ? "INSERT INTO calendar_months (id, month_number, name, short_name, lead, buy_tip, practical_tip, dates, suggested_items)
                   VALUES (:id, :month_number, :name, :short_name, :lead, :buy_tip, :practical_tip, :dates, :suggested_items)
                   ON DUPLICATE KEY UPDATE name=VALUES(name), lead=VALUES(lead)"
                : "INSERT OR REPLACE INTO calendar_months (id, month_number, name, short_name, lead, buy_tip, practical_tip, dates, suggested_items)
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
            $stmtCat = $this->pdo->prepare("
                INSERT OR IGNORE INTO financial_categories (tenant_id, name)
                VALUES (:tenant_id, :name)
            ");
            if (Config::get('db.driver') === 'mysql') {
                $stmtCat = $this->pdo->prepare("
                    INSERT IGNORE INTO financial_categories (tenant_id, name)
                    VALUES (:tenant_id, :name)
                ");
            }

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

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
