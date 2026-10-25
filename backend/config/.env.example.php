<?php
// Configurações do ambiente de produção (Hostinger)
return [
    'app_env' => 'production',
    'app_debug' => false,
    'app_url' => 'https://vendatododia.online',
    'app_secret' => 'coloque_aqui_uma_chave_aleatoria_longa_e_segura',

    // Banco de Dados MySQL na Hostinger
    'db' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'port'     => 3306,
        'database' => 'u916310211_vtd',
        'username' => 'u916310211_vtd',
        'password' => 'sua_senha_segura_aqui',
        'charset'  => 'utf8mb4'
    ],

    // SMTP Hostinger para envio de acessos
    'mail' => [
        'driver'       => 'smtp',
        'host'         => 'smtp.hostinger.com',
        'port'         => 465,
        'encryption'   => 'ssl', // 'ssl' ou 'tls'
        'username'     => 'contato@vendatododia.online',
        'password'     => 'senha_do_email_hostinger',
        'from_name'    => 'VTD · Venda Todo Dia',
        'from_address' => 'contato@vendatododia.online'
    ],

    // Integração Webhook da Greenn
    'greenn' => [
        'webhook_secret' => 'sua_chave_secreta_webhook_greenn',
        'essential_product_id' => '12345',
        'plus_product_id' => '67890'
    ]
];
