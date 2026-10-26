<?php
declare(strict_types=1);
namespace VTD\Controllers;

use VTD\Core\Request;
use VTD\Core\Response;
use VTD\Database\Connection;
use VTD\Middlewares\PlanMiddleware;
use VTD\Services\FinanceService;
use PDO;

final class FinanceV2Controller
{
    private function context(Request $request): array { return (new PlanMiddleware())->handle($request); }

    public function dashboard(Request $request): void
    {
        $account = $this->context($request);
        $month = (string)$request->query('month', date('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) { Response::error('VALIDATION_ERROR', 'Mês inválido.', 422); return; }
        $pdo = Connection::getInstance();
        $start = $month . '-01';
        $end = date('Y-m-d', strtotime($start . ' +1 month'));
        $today = date('Y-m-d');
        $overview = (new FinanceService())->calculateMonthOverview($account['tenant_id'], $month);
        $q = $pdo->prepare("SELECT id, description, type, value_cents, date, category_id
          FROM financial_transactions WHERE tenant_id = :tid AND status = 'pending'
          AND date < :end ORDER BY date ASC, created_at ASC LIMIT 50");
        $q->execute([':tid' => $account['tenant_id'], ':end' => $end]);
        $all = $q->fetchAll(PDO::FETCH_ASSOC);
        $upcoming = []; $overdue = []; $totalOverdue = 0;
        foreach ($all as $row) {
            $row['value_cents'] = (int)$row['value_cents'];
            $row['days_until'] = (int)floor((strtotime($row['date']) - strtotime($today)) / 86400);
            if ($row['date'] < $today) { $overdue[] = $row; $totalOverdue += $row['value_cents']; }
            elseif ($row['date'] >= $start) $upcoming[] = $row;
        }
        $rec = $pdo->prepare("SELECT id, description, type, value_cents, day_of_month, starts_on, ends_on, is_active
                             FROM financial_recurring_rules WHERE tenant_id = :tid ORDER BY created_at DESC");
        $rec->execute([':tid' => $account['tenant_id']]);
        Response::json([
            'overview' => $overview,
            'agenda' => ['upcoming' => $upcoming, 'overdue' => $overdue, 'overdue_count' => count($overdue), 'overdue_cents' => $totalOverdue],
            'recurring' => $rec->fetchAll(PDO::FETCH_ASSOC),
            'alerts' => array_values(array_filter([
                count($overdue) ? ['type'=>'overdue','message'=>count($overdue).' compromisso(s) vencido(s).'] : null,
                ($overview['stats']['projected'] < 0) ? ['type'=>'negative_cash','message'=>'Saldo projetado negativo neste mês.'] : null
            ]))
        ]);
    }

    public function createRecurring(Request $request): void
    {
        $account = $this->context($request);
        $body = $request->body();
        $description = trim((string)($body['description'] ?? ''));
        $type = (string)($body['type'] ?? '');
        $cents = (int)($body['value_cents'] ?? 0);
        $catId = (int)($body['category_id'] ?? 0);
        $day = (int)($body['day_of_month'] ?? 0);
        $start = (string)($body['starts_on'] ?? date('Y-m-d'));
        $end = trim((string)($body['ends_on'] ?? ''));
        if ($description === '' || mb_strlen($description) > 190 || !in_array($type,['income','expense'],true) ||
            $cents <= 0 || $day < 1 || $day > 31 || !FinanceService::validDate($start) ||
            ($end !== '' && (!FinanceService::validDate($end) || $end < $start))) {
            Response::error('VALIDATION_ERROR','Confira a descrição, tipo, valor, dia e período da recorrência.',422); return;
        }
        $pdo = Connection::getInstance();
        $q = $pdo->prepare('SELECT COUNT(*) FROM financial_categories WHERE tenant_id=:tid AND id=:cat');
        $q->execute([':tid'=>$account['tenant_id'],':cat'=>$catId]);
        if (!(int)$q->fetchColumn()) { Response::error('VALIDATION_ERROR','Categoria inválida.',422); return; }
        $id = 'rec_' . bin2hex(random_bytes(8));
        $q = $pdo->prepare("INSERT INTO financial_recurring_rules
          (id, tenant_id, created_by_user_id, description, type, value_cents, category_id, day_of_month, starts_on, ends_on)
          VALUES (:id,:tid,:uid,:desc,:type,:cents,:cat,:day,:start,:end)");
        $q->execute([':id'=>$id,':tid'=>$account['tenant_id'],':uid'=>$account['user_id'],':desc'=>$description,
            ':type'=>$type,':cents'=>$cents,':cat'=>$catId,':day'=>$day,':start'=>$start,':end'=>$end ?: null]);
        Response::json(['id'=>$id,'message'=>'Recorrência criada. Gere os próximos lançamentos na agenda.'],201);
    }

    public function stopRecurring(Request $request,array $params): void
    {
        $account = $this->context($request);
        $q = Connection::getInstance()->prepare('UPDATE financial_recurring_rules SET is_active=0 WHERE id=:id AND tenant_id=:tid');
        $q->execute([':id'=>(string)($params['id']??''),':tid'=>$account['tenant_id']]);
        Response::json(['message'=>'Recorrência desativada; lançamentos existentes preservados.']);
    }

    public function generateRecurring(Request $request): void
    {
        $account = $this->context($request);
        $month = (string)$request->body('month',date('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month)) {
            Response::error('VALIDATION_ERROR','Mês inválido.',422); return;
        }
        $pdo = Connection::getInstance();
        $q = $pdo->prepare('SELECT * FROM financial_recurring_rules WHERE tenant_id=:tid AND is_active=1');
        $q->execute([':tid'=>$account['tenant_id']]);
        $count = 0;
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $rule) {
            $maxDay = (int)date('t',strtotime($month.'-01'));
            $date = $month.'-'.str_pad((string)min((int)$rule['day_of_month'],$maxDay),2,'0',STR_PAD_LEFT);
            if ($date < $rule['starts_on'] || ($rule['ends_on'] && $date > $rule['ends_on'])) continue;
            $insert = $pdo->prepare("INSERT IGNORE INTO financial_transactions
              (id,tenant_id,description,type,value_cents,category_id,date,status,created_by_user_id,recurrence_rule_id)
              VALUES (:id,:tid,:desc,:type,:cents,:cat,:date,'pending',:uid,:rule)");
            $insert->execute([':id'=>'tx_'.bin2hex(random_bytes(10)),':tid'=>$account['tenant_id'],
                ':desc'=>$rule['description'],':type'=>$rule['type'],':cents'=>$rule['value_cents'],
                ':cat'=>$rule['category_id'],':date'=>$date,':uid'=>$account['user_id'],':rule'=>$rule['id']]);
            $count += $insert->rowCount();
        }
        Response::json(['created'=>$count,'month'=>$month,'message'=>'Lançamentos gerados sem duplicar competências já existentes.']);
    }
}
