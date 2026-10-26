<?php
declare(strict_types=1);

namespace VTD\Services;

use PDO;
use VTD\Database\Connection;

class FinanceService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::getInstance();
    }

    public function calculateMonthOverview(string $tenantId, string $month): array
    {
        // 1. Obtém saldo inicial configurado na loja
        $stmtTenant = $this->pdo->prepare("SELECT initial_balance_cents FROM tenants WHERE id = :tid");
        $stmtTenant->execute([':tid' => $tenantId]);
        $initialBalance = (int)($stmtTenant->fetchColumn() ?: 0);

        // 2. Acumulado realizado ANTES do mês atual
        // Saldo de abertura do mês = initial_balance + sum(income antes do mês) - sum(expense antes do mês)
        $monthStart = $month . '-01';
        $stmtBefore = $this->pdo->prepare("
            SELECT type, SUM(value_cents) as total
            FROM financial_transactions
            WHERE tenant_id = :tid AND status = 'paid' AND COALESCE(settled_at, date) < :start
            GROUP BY type
        ");
        $stmtBefore->execute([':tid' => $tenantId, ':start' => $monthStart]);
        $rowsBefore = $stmtBefore->fetchAll();

        $beforeInc = 0;
        $beforeOut = 0;
        foreach ($rowsBefore as $r) {
            if ($r['type'] === 'income') $beforeInc = (int)$r['total'];
            if ($r['type'] === 'expense') $beforeOut = (int)$r['total'];
        }
        $startBalance = $initialBalance + $beforeInc - $beforeOut;

        // 3. Lançamentos realizados NO mês atual
        $stmtMonth = $this->pdo->prepare("
            SELECT type, SUM(value_cents) as total
            FROM financial_transactions
            WHERE tenant_id = :tid AND status = 'paid' AND strftime('%Y-%m', COALESCE(settled_at, date)) = :month
            GROUP BY type
        ");
        // Ajuste sintaxe MySQL vs SQLite
        if (\VTD\Config\Config::get('db.driver') === 'mysql') {
            $stmtMonth = $this->pdo->prepare("
                SELECT type, SUM(value_cents) as total
                FROM financial_transactions
                WHERE tenant_id = :tid AND status = 'paid' AND DATE_FORMAT(COALESCE(settled_at, date), '%Y-%m') = :month
                GROUP BY type
            ");
        }
        $stmtMonth->execute([':tid' => $tenantId, ':month' => $month]);
        $rowsMonth = $stmtMonth->fetchAll();

        $monthInc = 0;
        $monthOut = 0;
        foreach ($rowsMonth as $r) {
            if ($r['type'] === 'income') $monthInc = (int)$r['total'];
            if ($r['type'] === 'expense') $monthOut = (int)$r['total'];
        }
        $currentBalance = $startBalance + $monthInc - $monthOut;

        // 4. Lançamentos pendentes NO mês atual (para saldo projetado)
        $stmtPending = $this->pdo->prepare("
            SELECT type, SUM(value_cents) as total
            FROM financial_transactions
            WHERE tenant_id = :tid AND status = 'pending' AND strftime('%Y-%m', date) = :month
            GROUP BY type
        ");
        if (\VTD\Config\Config::get('db.driver') === 'mysql') {
            $stmtPending = $this->pdo->prepare("
                SELECT type, SUM(value_cents) as total
                FROM financial_transactions
                WHERE tenant_id = :tid AND status = 'pending' AND DATE_FORMAT(date, '%Y-%m') = :month
                GROUP BY type
            ");
        }
        $stmtPending->execute([':tid' => $tenantId, ':month' => $month]);
        $rowsPending = $stmtPending->fetchAll();

        $pendingInc = 0;
        $pendingOut = 0;
        foreach ($rowsPending as $r) {
            if ($r['type'] === 'income') $pendingInc = (int)$r['total'];
            if ($r['type'] === 'expense') $pendingOut = (int)$r['total'];
        }
        $projectedBalance = $currentBalance + $pendingInc - $pendingOut;

        // 5. Série semanal para o gráfico (5 semanas)
        $stmtWeekly = $this->pdo->prepare("
            SELECT COALESCE(settled_at, date) as date, type, value_cents
            FROM financial_transactions
            WHERE tenant_id = :tid AND status = 'paid' AND strftime('%Y-%m', COALESCE(settled_at, date)) = :month
        ");
        if (\VTD\Config\Config::get('db.driver') === 'mysql') {
            $stmtWeekly = $this->pdo->prepare("
                SELECT COALESCE(settled_at, date) as date, type, value_cents
                FROM financial_transactions
                WHERE tenant_id = :tid AND status = 'paid' AND DATE_FORMAT(COALESCE(settled_at, date), '%Y-%m') = :month
            ");
        }
        $stmtWeekly->execute([':tid' => $tenantId, ':month' => $month]);
        $txList = $stmtWeekly->fetchAll();

        $weeklyInc = [0, 0, 0, 0, 0];
        $weeklyOut = [0, 0, 0, 0, 0];
        foreach ($txList as $tx) {
            $day = (int)substr($tx['date'], 8, 2);
            $weekIdx = min(4, (int)floor(($day - 1) / 7));
            if ($tx['type'] === 'income') {
                $weeklyInc[$weekIdx] += (int)$tx['value_cents'];
            } else {
                $weeklyOut[$weekIdx] += (int)$tx['value_cents'];
            }
        }

        // 6. Despesas por categoria
        $stmtCat = $this->pdo->prepare("
            SELECT fc.name, SUM(ft.value_cents) as total
            FROM financial_transactions ft
            JOIN financial_categories fc ON fc.id = ft.category_id
            WHERE ft.tenant_id = :tid AND ft.type = 'expense' AND ft.status = 'paid'
                  AND strftime('%Y-%m', COALESCE(ft.settled_at, ft.date)) = :month
            GROUP BY fc.name
            ORDER BY total DESC
        ");
        if (\VTD\Config\Config::get('db.driver') === 'mysql') {
            $stmtCat = $this->pdo->prepare("
                SELECT fc.name, SUM(ft.value_cents) as total
                FROM financial_transactions ft
                JOIN financial_categories fc ON fc.id = ft.category_id
                WHERE ft.tenant_id = :tid AND ft.type = 'expense' AND ft.status = 'paid'
                      AND DATE_FORMAT(COALESCE(ft.settled_at, ft.date), '%Y-%m') = :month
                GROUP BY fc.name
                ORDER BY total DESC
            ");
        }
        $stmtCat->execute([':tid' => $tenantId, ':month' => $month]);
        $categoriesChart = array_map(function($r) {
            return [
                'name'        => $r['name'],
                'value_cents' => (int)$r['total']
            ];
        }, $stmtCat->fetchAll());

        return [
            'month' => $month,
            'stats' => [
                'start'     => $startBalance,
                'inc'       => $monthInc,
                'out'       => $monthOut,
                'balance'   => $currentBalance,
                'projected' => $projectedBalance
            ],
            'chart' => [
                'income'  => $weeklyInc,
                'expense' => $weeklyOut
            ],
            'categories' => $categoriesChart
        ];
    }

    public function getTransactions(string $tenantId, string $month, array $filters = []): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $status = trim((string)($filters['status'] ?? ''));
        $type = trim((string)($filters['type'] ?? ''));

        $conditions = ["ft.tenant_id = :tid"];
        $params = [':tid' => $tenantId];

        if (!empty($month)) {
            if (\VTD\Config\Config::get('db.driver') === 'mysql') {
                $conditions[] = "DATE_FORMAT(ft.date, '%Y-%m') = :month";
            } else {
                $conditions[] = "strftime('%Y-%m', ft.date) = :month";
            }
            $params[':month'] = $month;
        }

        if (!empty($search)) {
            $conditions[] = "ft.description LIKE :search";
            $params[':search'] = "%{$search}%";
        }

        if (!empty($status)) {
            $conditions[] = "ft.status = :status";
            $params[':status'] = $status;
        }

        if (!empty($type)) {
            $conditions[] = "ft.type = :type";
            $params[':type'] = $type;
        }

        $whereClause = implode(" AND ", $conditions);
        $sql = "
            SELECT ft.*, fc.name as category_name
            FROM financial_transactions ft
            LEFT JOIN financial_categories fc ON fc.id = ft.category_id
            WHERE {$whereClause}
            ORDER BY ft.date DESC, ft.created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(function($r) {
            return [
                'id'          => $r['id'],
                'description' => $r['description'],
                'type'        => $r['type'],
                'value'       => (int)$r['value_cents'], // em centavos inteiros
                'category'    => $r['category_name'] ?? 'Outros',
                'category_id' => (int)$r['category_id'],
                'date'        => $r['date'],
                'status'      => $r['status'],
                'settled_at'  => $r['settled_at'],
                'due_date'    => $r['date'],
                'paid_at'     => $r['settled_at']
            ];
        }, $rows);
    }

    public function createTransaction(string $tenantId, array $data, string $userId): array
    {
        $id = $data['id'] ?? ('tx_' . bin2hex(random_bytes(10)));
        $desc = trim((string)($data['description'] ?? ''));
        $type = ($data['type'] ?? 'expense') === 'income' ? 'income' : 'expense';
        $valueCents = (int)($data['value_cents'] ?? 0);
        $catId = (int)($data['category_id'] ?? 1);
        $date = trim((string)($data['date'] ?? date('Y-m-d')));
        $status = ($data['status'] ?? 'pending') === 'paid' ? 'paid' : 'pending';

        $this->validateFinancialInput($tenantId, $desc, $valueCents, $catId, $date);
        $settledAt = $status === 'paid' ? (string)($data['paid_at'] ?? date('Y-m-d')) : null;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO financial_transactions (id, tenant_id, description, type, value_cents, category_id, date, status, settled_at, created_by_user_id)
                VALUES (:id, :tid, :desc, :type, :val, :cat, :date, :status, :settled_at, :uid)
            ");
            $stmt->execute([
                ':id'     => $id,
                ':tid'    => $tenantId,
                ':desc'   => $desc,
                ':type'   => $type,
                ':val'    => $valueCents,
                ':cat'    => $catId,
                ':date'   => $date,
                ':status' => $status,
                ':settled_at' => $settledAt,
                ':uid'    => $userId
            ]);

            // Grava auditoria
            $stmtAudit = $this->pdo->prepare("
                INSERT INTO financial_audit_logs (tenant_id, transaction_id, user_id, action, snapshot_after)
                VALUES (:tid, :txid, :uid, 'create', :after)
            ");
            $stmtAudit->execute([
                ':tid'   => $tenantId,
                ':txid'  => $id,
                ':uid'   => $userId,
                ':after' => json_encode($data)
            ]);

            $this->pdo->commit();

            return [
                'id'          => $id,
                'description' => $desc,
                'type'        => $type,
                'value'       => $valueCents,
                'category_id' => $catId,
                'date'        => $date,
                'status'      => $status
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function updateTransaction(string $tenantId, string $id, array $data, string $userId): array
    {
        $stmtBefore = $this->pdo->prepare("SELECT * FROM financial_transactions WHERE id = :id AND tenant_id = :tid");
        $stmtBefore->execute([':id' => $id, ':tid' => $tenantId]);
        $before = $stmtBefore->fetch();

        if (!$before) {
            throw new \RuntimeException("Lançamento não encontrado.");
        }

        $desc = trim((string)($data['description'] ?? $before['description']));
        $type = ($data['type'] ?? $before['type']) === 'income' ? 'income' : 'expense';
        $valueCents = isset($data['value_cents']) ? (int)$data['value_cents'] : (int)$before['value_cents'];
        $catId = isset($data['category_id']) ? (int)$data['category_id'] : (int)$before['category_id'];
        $date = trim((string)($data['date'] ?? $before['date']));
        $status = ($data['status'] ?? $before['status']) === 'paid' ? 'paid' : 'pending';

        $this->validateFinancialInput($tenantId, $desc, $valueCents, $catId, $date);
        $settledAt = $status === 'paid' ? ($before['settled_at'] ?: date('Y-m-d')) : null;
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                UPDATE financial_transactions
                SET description = :desc, type = :type, value_cents = :val, category_id = :cat, date = :date, status = :status, settled_at = :settled_at
                WHERE id = :id AND tenant_id = :tid
            ");
            $stmt->execute([
                ':desc'   => $desc,
                ':type'   => $type,
                ':val'    => $valueCents,
                ':cat'    => $catId,
                ':date'   => $date,
                ':status' => $status,
                ':settled_at' => $settledAt,
                ':id'     => $id,
                ':tid'    => $tenantId
            ]);

            $stmtAudit = $this->pdo->prepare("
                INSERT INTO financial_audit_logs (tenant_id, transaction_id, user_id, action, snapshot_before, snapshot_after)
                VALUES (:tid, :txid, :uid, 'update', :before, :after)
            ");
            $stmtAudit->execute([
                ':tid'    => $tenantId,
                ':txid'   => $id,
                ':uid'    => $userId,
                ':before' => json_encode($before),
                ':after'  => json_encode($data)
            ]);

            $this->pdo->commit();

            return [
                'id'          => $id,
                'description' => $desc,
                'type'        => $type,
                'value'       => $valueCents,
                'category_id' => $catId,
                'date'        => $date,
                'status'      => $status
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function settleTransaction(string $tenantId, string $id, string $effectiveDate, string $userId): array
    {
        $stmtBefore = $this->pdo->prepare("SELECT * FROM financial_transactions WHERE id = :id AND tenant_id = :tid");
        $stmtBefore->execute([':id' => $id, ':tid' => $tenantId]);
        $before = $stmtBefore->fetch();

        if (!$before) {
            throw new \RuntimeException("Lançamento não encontrado.");
        }

        if (!self::validDate($effectiveDate)) throw new \InvalidArgumentException('Data de quitação inválida.');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                UPDATE financial_transactions
                SET status = 'paid', settled_at = :eff_date
                WHERE id = :id AND tenant_id = :tid
            ");
            $stmt->execute([
                ':eff_date' => $effectiveDate,
                ':id'       => $id,
                ':tid'      => $tenantId
            ]);

            $stmtAudit = $this->pdo->prepare("
                INSERT INTO financial_audit_logs (tenant_id, transaction_id, user_id, action, snapshot_before, snapshot_after)
                VALUES (:tid, :txid, :uid, 'settle', :before, :after)
            ");
            $stmtAudit->execute([
                ':tid'    => $tenantId,
                ':txid'   => $id,
                ':uid'    => $userId,
                ':before' => json_encode($before),
                ':after'  => json_encode(['status' => 'paid', 'date' => $effectiveDate, 'settled_at' => $effectiveDate])
            ]);

            $this->pdo->commit();

            $before['status'] = 'paid';
            $before['settled_at'] = $effectiveDate;
            return $before;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function deleteTransaction(string $tenantId, string $id, string $userId): void
    {
        $stmtBefore = $this->pdo->prepare("SELECT * FROM financial_transactions WHERE id = :id AND tenant_id = :tid");
        $stmtBefore->execute([':id' => $id, ':tid' => $tenantId]);
        $before = $stmtBefore->fetch();

        if (!$before) {
            throw new \RuntimeException("Lançamento não encontrado.");
        }

        $this->pdo->beginTransaction();
        try {
            $stmtAudit = $this->pdo->prepare("
                INSERT INTO financial_audit_logs (tenant_id, transaction_id, user_id, action, snapshot_before)
                VALUES (:tid, :txid, :uid, 'delete', :before)
            ");
            $stmtAudit->execute([
                ':tid'    => $tenantId,
                ':txid'   => $id,
                ':uid'    => $userId,
                ':before' => json_encode($before)
            ]);

            $stmt = $this->pdo->prepare("DELETE FROM financial_transactions WHERE id = :id AND tenant_id = :tid");
            $stmt->execute([':id' => $id, ':tid' => $tenantId]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getCategories(string $tenantId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT fc.id, fc.name, COUNT(ft.id) as tx_count
            FROM financial_categories fc
            LEFT JOIN financial_transactions ft ON ft.category_id = fc.id AND ft.tenant_id = fc.tenant_id
            WHERE fc.tenant_id = :tid
            GROUP BY fc.id, fc.name
            ORDER BY fc.name ASC
        ");
        $stmt->execute([':tid' => $tenantId]);
        return $stmt->fetchAll();
    }

    public function createCategory(string $tenantId, string $name): array
    {
        $name = trim($name);
        if (empty($name)) {
            throw new \InvalidArgumentException("Nome da categoria não pode ser vazio.");
        }

        $stmt = $this->pdo->prepare("INSERT INTO financial_categories (tenant_id, name) VALUES (:tid, :name)");
        $stmt->execute([':tid' => $tenantId, ':name' => $name]);

        return [
            'id'       => (int)$this->pdo->lastInsertId(),
            'name'     => $name,
            'tx_count' => 0
        ];
    }

    public function deleteCategory(string $tenantId, int $id): void
    {
        $stmtCheck = $this->pdo->prepare("SELECT COUNT(*) FROM financial_transactions WHERE category_id = :cid AND tenant_id = :tid");
        $stmtCheck->execute([':cid' => $id, ':tid' => $tenantId]);
        $count = (int)$stmtCheck->fetchColumn();

        if ($count > 0) {
            throw new \RuntimeException("A categoria possui {$count} lançamentos vinculados e não pode ser excluída.");
        }

        $stmt = $this->pdo->prepare("DELETE FROM financial_categories WHERE id = :cid AND tenant_id = :tid");
        $stmt->execute([':cid' => $id, ':tid' => $tenantId]);
    }
    public static function validDate(string $date): bool
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }

    private function validateFinancialInput(string $tenantId, string $desc, int $cents, int $categoryId, string $date): void
    {
        if ($desc === '' || mb_strlen($desc) > 190 || $cents <= 0) {
            throw new \InvalidArgumentException('Informe descrição de até 190 caracteres e valor maior que zero.');
        }
        if (!self::validDate($date)) throw new \InvalidArgumentException('Data de vencimento inválida.');
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM financial_categories WHERE tenant_id = :tenant AND id = :category');
        $stmt->execute([':tenant' => $tenantId, ':category' => $categoryId]);
        if (!(int)$stmt->fetchColumn()) throw new \InvalidArgumentException('Categoria não pertence à sua loja.');
    }

}
