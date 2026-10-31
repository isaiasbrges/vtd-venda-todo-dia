<?php
declare(strict_types=1);
namespace VTD\Controllers;
use VTD\Core\Request;
use VTD\Core\Response;
use VTD\Database\Connection;
use VTD\Middlewares\PlanMiddleware;
use VTD\Services\FinanceCardsService;

class FinanceCardsController
{
    private function handle(Request $r,callable $action): void {
        $a=(new PlanMiddleware())->handle($r);
        if(!FinanceCardsService::ready(Connection::getInstance())) { Response::error('CARDS_NOT_READY','O cadastro de cartões estará disponível após a atualização do financeiro.',503);return; }
        try { Response::json($action(new FinanceCardsService(),$a)); }
        catch(\InvalidArgumentException $e) { Response::error('VALIDATION_ERROR',$e->getMessage(),422); }
    }
    public function banks(Request $r): void { (new PlanMiddleware())->handle($r); Response::json(FinanceCardsService::banks()); }
    public function cards(Request $r): void { $this->handle($r,fn($s,$a)=>$s->cards($a['tenant_id'])); }
    public function create(Request $r): void { $this->handle($r,fn($s,$a)=>$s->saveCard($a['tenant_id'],$r->body())); }
    public function update(Request $r,array $p): void { $this->handle($r,fn($s,$a)=>$s->saveCard($a['tenant_id'],$r->body(),$p['id'])); }
    public function archive(Request $r,array $p): void { $this->handle($r,fn($s,$a)=>$s->archive($a['tenant_id'],$p['id'])); }
    public function invoices(Request $r,array $p): void { $this->handle($r,fn($s,$a)=>$s->invoices($a['tenant_id'],$p['id'])); }
    public function invoice(Request $r,array $p): void { $this->handle($r,fn($s,$a)=>$s->invoice($a['tenant_id'],$p['id'])); }
    public function preview(Request $r): void { $this->handle($r,fn($s,$a)=>$s->preview($a['tenant_id'],$r->body())); }
    public function purchase(Request $r): void { $this->handle($r,fn($s,$a)=>$s->purchase($a['tenant_id'],$r->body(),$a['user_id'])); }
    public function updatePurchase(Request $r,array $p): void { $this->handle($r,fn($s,$a)=>$s->purchase($a['tenant_id'],$r->body(),$a['user_id'],$p['id'])); }
    public function purchaseDetail(Request $r,array $p): void { $this->handle($r,fn($s,$a)=>$s->purchaseDetail($a['tenant_id'],$p['id'])); }
    public function cancel(Request $r,array $p): void { $this->handle($r,fn($s,$a)=>$s->cancel($a['tenant_id'],$p['id'],$a['user_id'])); }
    public function settle(Request $r,array $p): void { $this->handle($r,fn($s,$a)=>$s->settle($a['tenant_id'],$p['id'],(string)$r->body('effective_date',''),$a['user_id'],(string)$r->body('operation_key',''))); }
}
