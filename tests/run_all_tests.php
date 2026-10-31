<?php
declare(strict_types=1);

echo "====================================================\n";
echo "      VTD - SUITE GERAL DE TESTES AUTOMATIZADOS     \n";
echo "====================================================\n\n";

$tests = [
    'CSV financeiro e compatibilidade PHP'  => 'test_finance_csv.php',
    'Cartões, parcelas e faturas isolados'    => 'test_finance_cards.php',
    'Acesso aos cartões, plano e CSRF'       => 'test_finance_cards_access.php',
    'Fundação & PDO Dual-Driver'               => 'test_foundation.php',
    'Migrações & Seeds (234 Fornec / 8 Cap)'  => 'test_migrations_and_seeds.php',
    'Segurança, Sessões & Rate Limiter'        => 'test_auth.php',
    'Serviço de E-mail (Hostinger SMTP)'       => 'test_mailer.php',
    'Conteúdo, Favoritos, Checklists & Notas'  => 'test_content_and_user_interactions.php',
    'Financeiro (Centavos, Quitação & Isolam)' => 'test_finance_module.php',
    'Administração & Provisionamento'          => 'test_admin_module.php',
    'Webhook Greenn (Idempotência & Eventos)'  => 'test_greenn_webhook.php',
    'Painel Master & Webhook Receiver'         => 'test_master_panel.php',
    'Configuração de integrações do Master'    => 'test_master_integrations.php',
    'Fumaça da API (Rotas & Dispatcher)'       => 'test_api_smoke.php'
];

$passedCount = 0;
$failedCount = 0;
$results = [];

$phpBinary = PHP_BINARY ?: 'php';

foreach ($tests as $name => $file) {
    $fullPath = __DIR__ . DIRECTORY_SEPARATOR . $file;
    if (!file_exists($fullPath)) {
        echo "[-] ARQUIVO NÃO ENCONTRADO: {$file}\n";
        $failedCount++;
        $results[$name] = 'NOT_FOUND';
        continue;
    }

    echo "Executando: {$name}... ";
    
    $cmd = escapeshellarg($phpBinary) . ' -d zend.assertions=1 -d assert.exception=1 -d auto_prepend_file=' . escapeshellarg(__DIR__ . '/bootstrap.php') . ' ' . escapeshellarg($fullPath) . ' 2>&1';
    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);

    if ($exitCode === 0) {
        echo "[ OK ]\n";
        $passedCount++;
        $results[$name] = 'PASS';
    } else {
        echo "[ FALHOU ] (Exit code {$exitCode})\n";
        echo "--- Saída do Teste ---\n";
        echo implode("\n", array_slice($output, -15)) . "\n";
        echo "----------------------\n";
        $failedCount++;
        $results[$name] = 'FAIL';
    }
}

echo "\n====================================================\n";
echo "RESUMO DOS TESTES:\n";
echo "Total de Suítes: " . count($tests) . "\n";
echo "Aprovados:       {$passedCount}\n";
echo "Reprovados:      {$failedCount}\n";
echo "====================================================\n";

if ($failedCount > 0) {
    exit(1);
}

echo "Todos os testes passaram com sucesso absoluto!\n";
exit(0);
