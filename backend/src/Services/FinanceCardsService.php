<?php
declare(strict_types=1);
namespace VTD\Services;

use PDO;
use InvalidArgumentException;
use VTD\Database\Connection;

class FinanceCardsService
{
    private PDO $pdo;
    public function __construct(?PDO $pdo = null) { $this->pdo = $pdo ?? Connection::getInstance(); }
    private static function id(): string { return bin2hex(random_bytes(16)); }
    private function query(string $sql, array $params = []): array { $s=$this->pdo->prepare($sql); $s->execute($params); return $s->fetchAll(); }
    private function one(string $sql, array $params): array { return $this->query($sql,$params)[0] ?? []; }
    private function exec(string $sql, array $params): void { $this->pdo->prepare($sql)->execute($params); }
    private function atomic(callable $action): mixed {
        $this->pdo->beginTransaction();
        try { $result=$action(); $this->pdo->commit(); return $result; }
        catch (\Throwable $e) { if($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
    private function lock(): string { return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql' ? ' FOR UPDATE' : ''; }
    public static function banks(): array {
        return json_decode(file_get_contents(dirname(__DIR__,2).'/resources/banks.json'),true,512,JSON_THROW_ON_ERROR);
    }
    public static function ready(PDO $pdo): bool {
        try {
            if(!$pdo->query("SELECT version FROM schema_migrations WHERE version='004_finance_cards.sql'")->fetchColumn()) return false;
            $pdo->query('SELECT c.id FROM financial_credit_cards c, financial_card_purchases p, financial_card_invoices f, financial_card_installments i, financial_card_invoice_payments pay, financial_transaction_methods m WHERE 1=0');
            return true;
        } catch (\PDOException $e) {
            if (in_array((string)$e->getCode(),['42S02','HY000'],true) && (str_contains($e->getMessage(),'no such table') || str_contains($e->getMessage(),"doesn't exist"))) return false;
            throw $e;
        }
    }
    private static function cents(mixed $value, bool $zero=false): int {
        if(!is_int($value) || $value<($zero?0:1) || $value>1000000000000) throw new InvalidArgumentException('Informe um valor válido de até R$ 10 bilhões.');
        return $value;
    }
    private static function key(array $d): string {
        $k=(string)($d['operation_key']??'');
        if(!preg_match('/^[a-zA-Z0-9_-]{8,100}$/',$k)) throw new InvalidArgumentException('Identificador da operação inválido.');
        return $k;
    }
    private function card(string $t,string $id,bool $lock=false): array {
        $c=$this->one('SELECT * FROM financial_credit_cards WHERE tenant_id=? AND id=?'.($lock?$this->lock():''),[$t,$id]);
        if(!$c) throw new InvalidArgumentException('Cartão não encontrado.');
        return $c;
    }
    public function cards(string $t): array {
        $rows=$this->query("SELECT c.*, COALESCE((SELECT SUM(ft.value_cents) FROM financial_card_installments i JOIN financial_transactions ft ON ft.id=i.transaction_id AND ft.tenant_id=i.tenant_id JOIN financial_card_invoices f ON f.id=i.invoice_id AND f.tenant_id=i.tenant_id WHERE i.tenant_id=c.tenant_id AND f.card_id=c.id AND ft.status='pending'),0) AS committed_cents FROM financial_credit_cards c WHERE c.tenant_id=? ORDER BY c.is_active DESC,c.nickname",[$t]);
        $nextMap=[];
        $next=$this->query("SELECT f.id,f.card_id,f.due_date,SUM(ft.value_cents) AS total_cents FROM financial_card_invoices f JOIN financial_card_installments i ON i.invoice_id=f.id AND i.tenant_id=f.tenant_id JOIN financial_transactions ft ON ft.id=i.transaction_id AND ft.tenant_id=i.tenant_id WHERE f.tenant_id=? AND f.is_settled=0 AND ft.status='pending' GROUP BY f.id,f.card_id,f.due_date ORDER BY f.due_date",[$t]);
        foreach($next as $bill) if(!isset($nextMap[$bill['card_id']])) {$bill['total_cents']=(int)$bill['total_cents'];$nextMap[$bill['card_id']]=$bill;}
        $bankMap=[]; foreach(self::banks() as $b) $bankMap[$b['id']]=$b;
        foreach($rows as &$c) { $c['logo']=$bankMap[$c['bank_id']]['logo']??null; $c['next_invoice']=$nextMap[$c['id']]??null; foreach(['limit_cents','committed_cents','closing_day','due_day','is_active'] as $k) $c[$k]=(int)$c[$k]; $c['available_cents']=$c['limit_cents']-$c['committed_cents']; }
        return $rows;
    }
    public function saveCard(string $t,array $d,?string $id=null): array {
        foreach(['nickname','brand','bank_id'] as $k) if(!is_string($d[$k]??null)||trim($d[$k])==='') throw new InvalidArgumentException('Preencha instituição, apelido e bandeira.');
        $name=trim($d['nickname']);
        if(mb_strlen($name)>80) throw new InvalidArgumentException('Apelido de até 80 caracteres.');
        $bankId=$d['bank_id']; $bankName='';
        if($bankId==='other') $bankName=trim((string)($d['bank_name']??''));
        else foreach(self::banks() as $b) if($b['id']===$bankId) $bankName=$b['name'];
        if($bankName===''||mb_strlen($bankName)>255) throw new InvalidArgumentException('Selecione uma instituição ou informe seu nome.');
        if(!in_array($d['brand'],['visa','mastercard','elo','amex','hipercard','other'],true)) throw new InvalidArgumentException('Bandeira inválida.');
        $last=(string)($d['last_four']??''); if($last!==''&&!preg_match('/^\d{4}$/',$last)) throw new InvalidArgumentException('Informe somente os quatro últimos dígitos.');
        foreach(['closing_day','due_day'] as $k) if(!is_int($d[$k]??null)||$d[$k]<1||$d[$k]>31) throw new InvalidArgumentException('Dias de fechamento e vencimento devem estar entre 1 e 31.');
        $limit=self::cents($d['limit_cents']??null,true);
        return $this->atomic(function()use($t,$d,$id,$name,$bankId,$bankName,$last,$limit) {
            if($id) {
                $this->card($t,$id,true);
                $this->exec('UPDATE financial_credit_cards SET bank_id=?,bank_name=?,nickname=?,brand=?,last_four=?,limit_cents=?,closing_day=?,due_day=? WHERE tenant_id=? AND id=?',[$bankId,$bankName,$name,$d['brand'],$last,$limit,$d['closing_day'],$d['due_day'],$t,$id]);
            } else {
                $id=self::id();
                $this->exec('INSERT INTO financial_credit_cards (id,tenant_id,bank_id,bank_name,nickname,brand,last_four,limit_cents,closing_day,due_day) VALUES (?,?,?,?,?,?,?,?,?,?)',[$id,$t,$bankId,$bankName,$name,$d['brand'],$last,$limit,$d['closing_day'],$d['due_day']]);
            }
            return $this->card($t,$id);
        });
    }
    public function archive(string $t,string $id): array {
        return $this->atomic(function()use($t,$id){$this->card($t,$id,true);$this->exec('UPDATE financial_credit_cards SET is_active=0 WHERE tenant_id=? AND id=?',[$t,$id]);return $this->card($t,$id);});
    }
    private static function monthDate(string $month,int $day): string {
        $d=new \DateTimeImmutable($month.'-01');
        return $month.'-'.str_pad((string)min($day,(int)$d->format('t')),2,'0',STR_PAD_LEFT);
    }
    public static function cycle(string $date,int $closing,int $due): array {
        if(!FinanceService::validDate($date)||$closing<1||$closing>31||$due<1||$due>31) throw new InvalidArgumentException('Data ou ciclo inválido.');
        $month=substr($date,0,7); $close=self::monthDate($month,$closing);
        if($date>$close) {$month=(new \DateTimeImmutable($month.'-01'))->modify('+1 month')->format('Y-m');$close=self::monthDate($month,$closing);}
        return self::cycleMonth($month,$close,$due);
    }
    private static function cycleMonth(string $month,string $close,int $due): array {
        $dueDate=self::monthDate($month,$due);
        if($dueDate<=$close) $dueDate=self::monthDate((new \DateTimeImmutable($month.'-01'))->modify('+1 month')->format('Y-m'),$due);
        return ['cycle_month'=>$month,'closing_date'=>$close,'due_date'=>$dueDate];
    }
    public function preview(string $t,array $d): array {
        $card=$this->card($t,(string)($d['card_id']??''));
        $cycle=self::cycle((string)($d['purchase_date']??''),(int)$card['closing_day'],(int)$card['due_day']);
        if(!empty($d['first_cycle'])) {
            if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',(string)$d['first_cycle'])) throw new InvalidArgumentException('Primeira fatura inválida.');
            if($d['first_cycle']<substr((string)$d['purchase_date'],0,7)) throw new InvalidArgumentException('A fatura não pode fechar antes do mês da compra.');
            $cycle=self::cycleMonth($d['first_cycle'],self::monthDate($d['first_cycle'],(int)$card['closing_day']),(int)$card['due_day']);
        }
        return $cycle;
    }
    public function purchase(string $t,array $d,string $u,?string $editId=null): array {
        $key=self::key($d);
        $desc=trim((string)($d['description']??'')); $value=self::cents($d['value_cents']??null);
        $n=$d['installments']??1; if(!is_int($n)||$n<1||$n>120||$n>$value) throw new InvalidArgumentException('Informe de 1 a 120 parcelas, de pelo menos um centavo.');
        if($desc===''||mb_strlen($desc)>190) throw new InvalidArgumentException('Descrição de 1 a 190 caracteres.');
        $date=(string)($d['purchase_date']??''); if(!FinanceService::validDate($date)) throw new InvalidArgumentException('Data da compra inválida.');
        $hash=hash('sha256',json_encode([$d['card_id']??'',$desc,$value,$d['category_id']??null,$date,$n,$d['first_cycle']??null],JSON_THROW_ON_ERROR));
        return $this->atomic(function()use($t,$d,$u,$editId,$key,$desc,$value,$n,$date,$hash) {
            $card=$this->card($t,(string)($d['card_id']??''),true);
            if(!(int)$card['is_active']) throw new InvalidArgumentException('Este cartão está arquivado.');
            $old=$this->one('SELECT * FROM financial_card_purchases WHERE tenant_id=? AND operation_key=?',[$t,$key]);
            if(!$editId&&$old) {if($old['request_hash']!==$hash) throw new InvalidArgumentException('Identificador já utilizado em outra compra.');return $old;}
            $cat=(int)($d['category_id']??0);
            if(!$this->one('SELECT id FROM financial_categories WHERE tenant_id=? AND id=?',[$t,$cat])) throw new InvalidArgumentException('Categoria não pertence à sua loja.');
            $cycle=$this->preview($t,$d);
            if($editId) {
                $original=$this->one('SELECT * FROM financial_card_purchases WHERE tenant_id=? AND id=?',[$t,$editId]);
                if(!$original||$original['card_id']!==$card['id']||$original['status']!=='active') throw new InvalidArgumentException('Compra não pode ser alterada neste cartão.');
                $this->removeInstallments($t,$editId,$u);
                $id=$editId;
                $this->exec('UPDATE financial_card_purchases SET description=?,purchase_date=?,value_cents=?,category_id=?,installments=?,request_hash=? WHERE tenant_id=? AND id=?',[$desc,$date,$value,$cat,$n,$hash,$t,$id]);
            } else {
                $id=self::id();
                $this->exec('INSERT INTO financial_card_purchases (id,tenant_id,card_id,description,purchase_date,value_cents,category_id,installments,operation_key,request_hash) VALUES (?,?,?,?,?,?,?,?,?,?)',[$id,$t,$card['id'],$desc,$date,$value,$cat,$n,$key,$hash]);
            }
            for($i=0;$i<$n;$i++) {
                $month=(new \DateTimeImmutable($cycle['cycle_month'].'-01'))->modify("+$i month")->format('Y-m');
                $dates=self::cycleMonth($month,self::monthDate($month,(int)$card['closing_day']),(int)$card['due_day']);
                $invoice=$this->one('SELECT * FROM financial_card_invoices WHERE tenant_id=? AND card_id=? AND cycle_month=?',[$t,$card['id'],$month]);
                if($invoice&&(int)$invoice['is_settled']) throw new InvalidArgumentException('Uma das faturas já foi paga. Escolha um ciclo em aberto.');
                if(!$invoice) {
                    $invoice=['id'=>self::id(),'due_date'=>$dates['due_date']];
                    $this->exec('INSERT INTO financial_card_invoices (id,tenant_id,card_id,cycle_month,closing_date,due_date) VALUES (?,?,?,?,?,?)',[$invoice['id'],$t,$card['id'],$month,$dates['closing_date'],$dates['due_date']]);
                }
                $part=intdiv($value,$n)+($i<($value%$n)?1:0); $tx=self::id();
                $label=$desc.($n>1?' · '.($i+1).'/'.$n:'');
                $this->exec("INSERT INTO financial_transactions (id,tenant_id,description,type,value_cents,category_id,date,status,created_by_user_id) VALUES (?,?,?,'expense',?,?,?,'pending',?)",[$tx,$t,$label,$part,$cat,$invoice['due_date'],$u]);
                $this->exec('INSERT INTO financial_card_installments (id,tenant_id,purchase_id,invoice_id,transaction_id,installment_number) VALUES (?,?,?,?,?,?)',[self::id(),$t,$id,$invoice['id'],$tx,$i+1]);
                $this->audit($t,$tx,$u,'create',['purchase_id'=>$id,'value_cents'=>$part]);
            }
            return $this->one('SELECT * FROM financial_card_purchases WHERE tenant_id=? AND id=?',[$t,$id]);
        });
    }
    private function removeInstallments(string $t,string $purchase,string $u): void {
        $rows=$this->query('SELECT ft.* FROM financial_card_installments i JOIN financial_transactions ft ON ft.id=i.transaction_id AND ft.tenant_id=i.tenant_id WHERE i.tenant_id=? AND i.purchase_id=?'.$this->lock(),[$t,$purchase]);
        foreach($rows as $r) if($r['status']==='paid') throw new InvalidArgumentException('Compra com parcela paga não pode ser editada ou cancelada.');
        foreach($rows as $r) {
            $this->audit($t,$r['id'],$u,'delete',$r);
            $this->exec('DELETE FROM financial_card_installments WHERE tenant_id=? AND transaction_id=?',[$t,$r['id']]);
            $this->exec('DELETE FROM financial_transactions WHERE tenant_id=? AND id=?',[$t,$r['id']]);
        }
    }
    public function purchaseDetail(string $t,string $id): array {
        $p=$this->one('SELECT * FROM financial_card_purchases WHERE tenant_id=? AND id=?',[$t,$id]);
        if(!$p||$p['status']!=='active') throw new InvalidArgumentException('Compra não encontrada.');
        $first=$this->one('SELECT f.cycle_month FROM financial_card_installments i JOIN financial_card_invoices f ON f.id=i.invoice_id AND f.tenant_id=i.tenant_id WHERE i.tenant_id=? AND i.purchase_id=? ORDER BY i.installment_number',[$t,$id]);
        $p['first_cycle']=$first['cycle_month']??null;return $p;
    }
    public function cancel(string $t,string $id,string $u): array {
        return $this->atomic(function()use($t,$id,$u) {
            $p=$this->one('SELECT * FROM financial_card_purchases WHERE tenant_id=? AND id=?',[$t,$id]);
            if(!$p) throw new InvalidArgumentException('Compra não encontrada.');
            $this->card($t,$p['card_id'],true); $this->removeInstallments($t,$id,$u);
            $this->exec("UPDATE financial_card_purchases SET status='cancelled' WHERE tenant_id=? AND id=?",[$t,$id]);
            return ['id'=>$id,'status'=>'cancelled'];
        });
    }
    public function invoices(string $t,string $card): array {
        $this->card($t,$card);
        $rows=$this->query("SELECT f.*,COALESCE(SUM(ft.value_cents),0) total_cents,COALESCE(SUM(CASE WHEN ft.status='pending' THEN ft.value_cents ELSE 0 END),0) pending_cents FROM financial_card_invoices f LEFT JOIN financial_card_installments i ON i.invoice_id=f.id AND i.tenant_id=f.tenant_id LEFT JOIN financial_transactions ft ON ft.id=i.transaction_id AND ft.tenant_id=i.tenant_id WHERE f.tenant_id=? AND f.card_id=? GROUP BY f.id,f.tenant_id,f.card_id,f.cycle_month,f.closing_date,f.due_date,f.is_settled ORDER BY f.due_date",[$t,$card]);
        foreach($rows as &$r) { $r['total_cents']=(int)$r['total_cents'];$r['pending_cents']=(int)$r['pending_cents'];$r['is_settled']=(int)$r['is_settled']; }
        return array_values(array_filter($rows,fn($r)=>$r['total_cents']>0));
    }
    public function invoice(string $t,string $id,bool $locked=false): array {
        $f=$this->one('SELECT * FROM financial_card_invoices WHERE tenant_id=? AND id=?'.($locked?$this->lock():''),[$t,$id]);
        if(!$f) throw new InvalidArgumentException('Fatura não encontrada.');
        $f['card']=$this->card($t,$f['card_id']);
        $f['items']=$this->query('SELECT ft.*,i.purchase_id,i.installment_number,p.installments,p.purchase_date FROM financial_card_installments i JOIN financial_transactions ft ON ft.id=i.transaction_id AND ft.tenant_id=i.tenant_id JOIN financial_card_purchases p ON p.id=i.purchase_id AND p.tenant_id=i.tenant_id WHERE i.tenant_id=? AND i.invoice_id=? ORDER BY p.purchase_date,ft.description'.($locked?$this->lock():''),[$t,$id]);
        $f['total_cents']=array_sum(array_map(fn($r)=>(int)$r['value_cents'],$f['items']));
        $f['pending_cents']=array_sum(array_map(fn($r)=>$r['status']==='pending'?(int)$r['value_cents']:0,$f['items']));
        return $f;
    }
    public function settle(string $t,string $id,string $date,string $u,string $key): array {
        self::key(['operation_key'=>$key]);
        if(!FinanceService::validDate($date)) throw new InvalidArgumentException('Data efetiva inválida.');
        return $this->atomic(function()use($t,$id,$date,$u,$key){
            $f=$this->invoice($t,$id); $this->card($t,$f['card_id'],true); $f=$this->invoice($t,$id,true);
            $prior=$this->one('SELECT * FROM financial_card_invoice_payments WHERE tenant_id=? AND operation_key=?',[$t,$key]);
            if($prior) {if($prior['invoice_id']!==$id||$prior['effective_date']!==$date) throw new InvalidArgumentException('Identificador de pagamento já utilizado.');return $this->invoice($t,$id);}
            if((int)$f['is_settled']) return $f;
            if(!$f['items']) throw new InvalidArgumentException('A fatura está vazia.');
            foreach($f['items'] as $r) if($r['status']==='pending') {
                $this->exec("UPDATE financial_transactions SET status='paid',settled_at=? WHERE tenant_id=? AND id=?",[$date,$t,$r['id']]);
                $this->audit($t,$r['id'],$u,'settle',['invoice_id'=>$id,'settled_at'=>$date,'value_cents'=>(int)$r['value_cents']]);
            }
            $this->exec('INSERT INTO financial_card_invoice_payments (id,tenant_id,invoice_id,value_cents,effective_date,operation_key) VALUES (?,?,?,?,?,?)',[self::id(),$t,$id,$f['pending_cents'],$date,$key]);
            $this->exec('UPDATE financial_card_invoices SET is_settled=1 WHERE tenant_id=? AND id=?',[$t,$id]);
            return $this->invoice($t,$id);
        });
    }
    private function audit(string $t,string $tx,string $u,string $action,array $data): void {
        $this->exec('INSERT INTO financial_audit_logs (tenant_id,transaction_id,user_id,action,snapshot_after) VALUES (?,?,?,?,?)',[$t,$tx,$u,$action,json_encode($data,JSON_THROW_ON_ERROR)]);
    }
}
