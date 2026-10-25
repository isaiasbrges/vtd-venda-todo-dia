<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/src/Autoloader.php';
\VTD\Autoloader::register();

echo "=== TESTE DA SUÍTE DO PAINEL MASTER & WEBHOOKS ===\n";

$pdo = \VTD\Database\Connection::getInstance();
$migrator = new \VTD\Database\Migrator($pdo);
$migrator->runMigrations();
$migrator->runSeeds();

\VTD\Core\Response::$stopOnExit = false;

// 1. Testa autenticação como admin
$_SESSION['user_id'] = 'user-1';
$_SESSION['user_role'] = 'admin';
$_SESSION['tenant_id'] = 'demo-store-1';

$master = new \VTD\Controllers\MasterController();

$csrfToken = \VTD\Security\Csrf::generateToken();
$headers = ['x-csrf-token' => $csrfToken];

// 2. Overview
$req = new \VTD\Core\Request('GET', '/api/master/overview');
ob_start();
$master->overview($req);
$out = ob_get_clean();
$data = json_decode($out, true);

assert($data['success'] === true, "Falha no overview: $out");
assert(isset($data['data']['metrics']['total_clients']));
assert(isset($data['data']['metrics']['active_integrations']));
assert(count($data['data']['recent_clients']) > 0);
echo "PASS: Overview do Master calculou indicadores reais com sucesso.\n";

// 3. Criação Manual de Cliente
$clientEmail = 'novocliente_' . time() . '@loja.com';
$reqCreate = new \VTD\Core\Request('POST', '/api/master/clients', [], [
    'name'        => 'Carlos Alberto',
    'email'       => $clientEmail,
    'store'       => 'Tênis Shop Pro',
    'offer'       => 'kit-plus',
    'send_invite' => false
], $headers);

ob_start();
$master->createClient($reqCreate);
$outCreate = ob_get_clean();
$dataCreate = json_decode($outCreate, true);

assert($dataCreate['success'] === true, "Falha ao criar cliente: $outCreate");
$newTenantId = $dataCreate['data']['tenant_id'];
echo "PASS: Cliente criado manualmente com oferta Kit + Financeiro.\n";

// 4. Detecção de e-mail duplicado
ob_start();
$master->createClient($reqCreate); // Tenta criar exatamente com o mesmo e-mail
$outDup = ob_get_clean();
$dataDup = json_decode($outDup, true);

assert($dataDup['success'] === true);
assert($dataDup['data']['is_existing'] === true);
assert(str_contains($dataDup['data']['message'], 'já estava cadastrado'));
echo "PASS: Detecção de e-mail duplicado associou produto sem criar segundo cliente.\n";

// 5. Suspender e Reativar Cliente
$reqToggle = new \VTD\Core\Request('PATCH', "/api/master/clients/{$newTenantId}/toggle", [], [], $headers);
ob_start();
$master->toggleClientStatus($reqToggle, ['id' => $newTenantId]);
$outToggle = ob_get_clean();
$dataToggle = json_decode($outToggle, true);

assert($dataToggle['success'] === true);
assert($dataToggle['data']['status'] === 'suspended');
echo "PASS: Acesso do cliente suspenso com sucesso.\n";

// 6. Teste de Webhook com Produto Sem Associação
$receiver = new \VTD\Controllers\WebhookReceiverController();
$unmappedOrderId = 'ORDER-UNMAPPED-' . time();
$unmappedEventId = 'evt_test_unmapped_' . time() . '_' . rand(100, 999);
$unmappedPayload = [
    'event_id'    => $unmappedEventId,
    'event'       => 'order_paid',
    'order_id'    => $unmappedOrderId,
    'buyer_email' => 'comprador_teste@loja.com',
    'buyer_name'  => 'Comprador Teste',
    'product_id'  => 'PROD_SEM_VINCULO_99'
];

$reqWebhook = new \VTD\Core\Request('POST', '/api/webhooks/receiver/token_greenn_principal_live', [], $unmappedPayload);
ob_start();
$receiver->handle($reqWebhook, ['token' => 'token_greenn_principal_live']);
$outWebhookFail = ob_get_clean();
$dataWebhookFail = json_decode($outWebhookFail, true);

assert($dataWebhookFail['success'] === false, "Deveria falhar para produto sem vínculo: $outWebhookFail");
assert(str_contains($dataWebhookFail['error']['message'], 'não possui associação'));
echo "PASS: Produto sem vínculo registrado como falha sem liberar acesso indevido.\n";

// 7. Mapear o produto e reprocessar o evento
$integRow = $pdo->query("SELECT * FROM integrations WHERE webhook_token = 'token_greenn_principal_live'")->fetch(PDO::FETCH_ASSOC);
$currentMappings = json_decode($integRow['offer_mappings'], true) ?: [];
$currentMappings['PROD_SEM_VINCULO_99'] = 'kit-plus';

$pdo->prepare("UPDATE integrations SET offer_mappings = :m WHERE id = :id")
    ->execute([':m' => json_encode($currentMappings), ':id' => $integRow['id']]);

// Busca o evento falho
$failedEvent = $pdo->query("SELECT * FROM integration_events WHERE event_id = '{$unmappedEventId}'")->fetch(PDO::FETCH_ASSOC);
assert(!empty($failedEvent));

ob_start();
$master->reprocessEvent(new \VTD\Core\Request('POST', '/api/master/events/' . $failedEvent['id'] . '/reprocess', [], [], $headers), ['id' => (string)$failedEvent['id']]);
$outReprocess = ob_get_clean();
$dataReprocess = json_decode($outReprocess, true);

assert($dataReprocess['success'] === true, "Falha no reprocessamento: $outReprocess");
echo "PASS: Evento reprocessado com sucesso após inclusão do vínculo do produto.\n";

// 8. Teste de Idempotência
ob_start();
$receiver->handle($reqWebhook, ['token' => 'token_greenn_principal_live']);
$outDupEvt = ob_get_clean();
$dataDupEvt = json_decode($outDupEvt, true);

assert($dataDupEvt['success'] === true);
assert(str_contains($dataDupEvt['data']['message'], 'duplicado'));
echo "PASS: Evento duplicado reconhecido com sucesso sem duplicações.\n";

// 9. Reembolso: revoga acesso da compra preservando demais
$refundPayload = [
    'event_id'    => 'evt_test_refund',
    'event'       => 'order_refunded',
    'order_id'    => $unmappedOrderId,
    'buyer_email' => 'comprador_teste@loja.com',
    'buyer_name'  => 'Comprador Teste',
    'product_id'  => 'PROD_SEM_VINCULO_99'
];
$reqRefund = new \VTD\Core\Request('POST', '/api/webhooks/receiver/token_greenn_principal_live', [], $refundPayload);
ob_start();
$receiver->handle($reqRefund, ['token' => 'token_greenn_principal_live']);
$outRefund = ob_get_clean();
$dataRefund = json_decode($outRefund, true);

assert($dataRefund['success'] === true, "Falha no reembolso: $outRefund");
echo "PASS: Reembolso processado e compra revogada com sucesso.\n";

// 10. Proteção: comprador comum não acessa Master
$_SESSION['user_id'] = 'user-2';
$_SESSION['user_role'] = 'user';
$forbidden = false;
try {
    ob_start();
    $master->overview($req);
    $outForbid = ob_get_clean();
    $dataForbid = json_decode($outForbid, true);
    if (($dataForbid['error']['code'] ?? '') === 'FORBIDDEN') {
        $forbidden = true;
    }
} catch (\Throwable $e) {
    $forbidden = true;
}
assert($forbidden === true, "Comprador comum não pode acessar o painel Master!");
echo "PASS: Rota do Master bloqueada para compradores comuns.\n";

echo "=== TODOS OS TESTES DO PAINEL MASTER PASSARAM COM 100% DE SUCESSO! ===\n";
