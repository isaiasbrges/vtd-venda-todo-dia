<?php
declare(strict_types=1);

namespace VTD\Controllers;

use VTD\Core\Request;
use VTD\Core\Response;
use VTD\Middlewares\PlanMiddleware;
use VTD\Services\FinanceService;

class FinanceController
{
    private FinanceService $service;

    public function __construct(?FinanceService $service = null)
    {
        $this->service = $service ?? new FinanceService();
    }

    public function overview(Request $request): void
    {
        $account = (new PlanMiddleware())->handle($request);
        $month = (string)$request->query('month', date('Y-m'));

        if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            Response::error('VALIDATION_ERROR', 'Mês inválido.', 422);
            return;
        }

        $overview = $this->service->calculateMonthOverview($account['tenant_id'], $month);
        Response::json($overview);
    }

    public function listTransactions(Request $request): void
    {
        $account = (new PlanMiddleware())->handle($request);
        $month = (string)$request->query('month', date('Y-m'));
        $filters = [
            'search' => (string)$request->query('search', ''),
            'status' => (string)$request->query('status', ''),
            'type'   => (string)$request->query('type', '')
        ];

        if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            Response::error('VALIDATION_ERROR', 'Mês inválido.', 422);
            return;
        }

        $transactions = $this->service->getTransactions($account['tenant_id'], $month, $filters);
        Response::json($transactions);
    }

    public function createTransaction(Request $request): void
    {
        $account = (new PlanMiddleware())->handle($request);

        $val = $request->body('value');
        $valCents = $request->body('value_cents');
        if ($valCents === null && $val !== null) {
            $valCents = (int)round((float)$val * 100);
        }

        $data = [
            'id'          => (string)($request->body('id') ?: ''),
            'payment_method' => (string)$request->body('payment_method', 'unspecified'),
            'description' => (string)$request->body('description', ''),
            'type'        => (string)$request->body('type', 'expense'),
            'value_cents' => (int)$valCents,
            'category_id' => (int)$request->body('category_id', 1),
            'date'        => (string)$request->body('date', date('Y-m-d')),
            'status'      => (string)$request->body('status', 'pending')
        ];

        try {
            $tx = $this->service->createTransaction($account['tenant_id'], $data, $account['user_id']);
            Response::json($tx, 201);
        } catch (\InvalidArgumentException $e) {
            Response::error('VALIDATION_ERROR', $e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('SERVER_ERROR', $e->getMessage(), 500);
        }
    }

    public function updateTransaction(Request $request, array $params): void
    {
        $account = (new PlanMiddleware())->handle($request);
        $id = $params['id'] ?? '';

        $val = $request->body('value');
        $valCents = $request->body('value_cents');
        if ($valCents === null && $val !== null) {
            $valCents = (int)round((float)$val * 100);
        }

        $data = [
            'payment_method' => (string)$request->body('payment_method', 'unspecified'),
            'description' => (string)$request->body('description', ''),
            'type'        => (string)$request->body('type', 'expense'),
            'value_cents' => (int)$valCents,
            'category_id' => (int)$request->body('category_id', 1),
            'date'        => (string)$request->body('date', date('Y-m-d')),
            'status'      => (string)$request->body('status', 'pending')
        ];

        try {
            $tx = $this->service->updateTransaction($account['tenant_id'], $id, $data, $account['user_id']);
            Response::json($tx);
        } catch (\Throwable $e) {
            Response::error('UPDATE_ERROR', $e->getMessage(), 400);
        }
    }

    public function settleTransaction(Request $request, array $params): void
    {
        $account = (new PlanMiddleware())->handle($request);
        $id = $params['id'] ?? '';
        $effectiveDate = (string)$request->body('effective_date', date('Y-m-d'));

        if (empty($effectiveDate)) {
            Response::error('VALIDATION_ERROR', 'Data efetiva da quitação é obrigatória.', 422);
        }

        try {
            $settled = $this->service->settleTransaction($account['tenant_id'], $id, $effectiveDate, $account['user_id']);
            Response::json($settled);
        } catch (\Throwable $e) {
            Response::error('SETTLE_ERROR', $e->getMessage(), 400);
        }
    }

    public function deleteTransaction(Request $request, array $params): void
    {
        $account = (new PlanMiddleware())->handle($request);
        $id = $params['id'] ?? '';

        try {
            $this->service->deleteTransaction($account['tenant_id'], $id, $account['user_id']);
            Response::json(['message' => 'Lançamento excluído com sucesso.']);
        } catch (\Throwable $e) {
            Response::error('DELETE_ERROR', $e->getMessage(), 400);
        }
    }

    public function listCategories(Request $request): void
    {
        $account = (new PlanMiddleware())->handle($request);
        $categories = $this->service->getCategories($account['tenant_id']);
        Response::json($categories);
    }

    public function createCategory(Request $request): void
    {
        $account = (new PlanMiddleware())->handle($request);
        $name = (string)$request->body('name', '');

        try {
            $cat = $this->service->createCategory($account['tenant_id'], $name);
            Response::json($cat, 201);
        } catch (\Throwable $e) {
            Response::error('CATEGORY_ERROR', $e->getMessage(), 422);
        }
    }

    public function deleteCategory(Request $request, array $params): void
    {
        $account = (new PlanMiddleware())->handle($request);
        $id = (int)($params['id'] ?? 0);

        try {
            $this->service->deleteCategory($account['tenant_id'], $id);
            Response::json(['message' => 'Categoria excluída com sucesso.']);
        } catch (\Throwable $e) {
            Response::error('CATEGORY_DELETE_ERROR', $e->getMessage(), 400);
        }
    }

    public function exportCsv(Request $request): void
    {
        $account = (new PlanMiddleware())->handle($request);
        $month = (string)$request->query('month', date('Y-m'));

        if (!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            Response::error('VALIDATION_ERROR', 'Mês inválido.', 422);
            return;
        }

        $txs = $this->service->getTransactions($account['tenant_id'], $month);

        $headers = ['Descrição', 'Tipo', 'Categoria', 'Vencimento', 'Valor (R$)', 'Status', 'Pagamento efetivo', 'Forma', 'Cartão', 'Parcela'];
        $rows = array_map(function($t) {
            $valReais = number_format($t['value'] / 100, 2, ',', '.');
            $statusLabel = $t['status'] === 'paid' ? 'Realizado' : 'Pendente';
            $typeLabel = $t['type'] === 'income' ? 'Entrada' : 'Saída';
            return [
                $t['description'],
                $typeLabel,
                $t['category'],
                $t['date'],
                $valReais,
                $statusLabel,
                $t['settled_at'] ?? '',
                $t['payment_method'] ?? '',
                $t['card']['nickname'] ?? '',
                isset($t['card']) ? $t['card']['installment_number'].'/'.$t['card']['installments'] : ''
            ];
        }, $txs);

        Response::streamCsv("vtd-financeiro-{$month}.csv", $headers, $rows);
    }
}
