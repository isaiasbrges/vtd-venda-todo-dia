# Guia Completo de Deploy na Hostinger — VTD (Viver do Tênis)

Este guia orienta o deploy da plataforma VTD em ambiente de hospedagem compartilhada da **Hostinger** (hPanel), utilizando Apache/LiteSpeed, PHP 8.2+ e MySQL via PDO.

---

## 1. Requisitos do Ambiente

- **Servidor Web**: Apache ou LiteSpeed com suporte a `.htaccess` e `mod_rewrite` ativo.
- **PHP**: Versão 8.2, 8.3 ou 8.4 com as seguintes extensões ativas:
  - `pdo`
  - `pdo_mysql`
  - `json`
  - `mbstring`
  - `openssl`
  - `curl`
  - `session`
- **Banco de Dados**: MySQL 8.0+ ou MariaDB 10.5+ com suporte a charset `utf8mb4`.

---

## 2. Estrutura de Arquivos no Servidor

Na Hostinger, o diretório raiz público é `/home/uXXXXXXX/public_html/` (onde `uXXXXXXX` é o seu usuário).

A estrutura de arquivos do VTD foi desenhada para garantir máxima segurança:

```text
public_html/
├── .htaccess                     # Regras de reescrita da API (/api/* -> api.php) e proteção de arquivos
├── api.php                       # Ponto de entrada frontal seguro da API REST
├── index.html                    # Interface pública do VTD (Frontend)
├── app.js                        # Aplicação cliente conectada à API REST
├── data.js                       # Definições legadas e referências de layout
├── styles.css                    # Estilos visuais do VTD (preservados 100%)
├── backend/
│   ├── .htaccess                 # Bloqueio total de acesso HTTP direto ("Deny from all")
│   ├── config/
│   │   ├── .env.php              # [CRÍTICO] Credenciais do banco MySQL, SMTP e Greenn
│   │   └── .env.example.php      # Modelo de configuração de ambiente
│   ├── migrations/
│   │   └── 001_initial_schema.sql # Esquema completo com tabelas relacionais
│   ├── seeds/
│   │   ├── export_json.js        # Script de extração dos 234 fornecedores
│   │   └── initial_data.json     # Catálogo completo de fornecedores, capítulos e meses
│   ├── src/                      # Código fonte estruturado em camadas (PSR-4)
│   │   ├── Autoloader.php
│   │   ├── Config/
│   │   ├── Core/
│   │   ├── Database/
│   │   ├── Security/
│   │   ├── Middlewares/
│   │   ├── Services/
│   │   ├── Controllers/
│   │   └── Views/emails/
│   └── storage/                  # Pasta para logs e locks de rate limit
```

---

## 3. Passo a Passo de Instalação no hPanel da Hostinger

### Passo 1: Criar o Banco de Dados MySQL
1. Acesse o **hPanel da Hostinger** (`hpanel.hostinger.com`).
2. Vá em **Bancos de Dados** -> **Bancos de Dados MySQL**.
3. Crie um novo banco de dados:
   - **Nome do Banco**: `uXXXXXXX_vtd`
   - **Usuário MySQL**: `uXXXXXXX_vtduser`
   - **Senha**: gere uma senha forte e anote-a.
4. Clique em **Criar**.

### Passo 2: Importar o Esquema e Dados Iniciais
1. Na lista de bancos de dados, clique em **Entrar no phpMyAdmin** ao lado do banco recém-criado.
2. Na aba **Importar**, clique em **Escolher arquivo** e selecione `backend/migrations/001_initial_schema.sql`.
3. Clique em **Executar** no rodapé.
4. *(Opcional)* Se preferir rodar a migração e os seeds de forma automática via script, você pode executar o Migrator via terminal SSH da Hostinger:
   ```bash
   php -r "require 'backend/src/Autoloader.php'; \VTD\Autoloader::register(); \$pdo = \VTD\Database\Connection::getInstance(); \$m = new \VTD\Database\Migrator(\$pdo); \$m->runMigrations(); \$m->runSeeds(); echo 'Migrado com sucesso!';"
   ```

### Passo 3: Enviar os Arquivos para a Hostinger
1. No hPanel, vá em **Arquivos** -> **Gerenciador de Arquivos** (ou utilize um cliente SFTP como FileZilla).
2. Navegue até a pasta `public_html/`.
3. Faça o upload de todos os arquivos do projeto VTD.
4. Verifique as permissões de pastas:
   - Diretórios em geral: `755`
   - Arquivos em geral: `644`
   - Pasta `backend/storage/`: `755` (ou `775`, com permissão de escrita para o processo PHP).

### Passo 4: Criar e Configurar o `.env.php`
1. No diretório `public_html/backend/config/`, copie o arquivo `.env.example.php` para `.env.php`.
2. Edite o `.env.php` com as credenciais reais do seu ambiente:

```php
<?php
declare(strict_types=1);

return [
    'app_env'    => 'production',
    'app_debug'  => false,
    'app_url'    => 'https://seudominio.com.br',
    'app_secret' => 'SUBSTITUA_POR_UMA_CHAVE_ALEATORIA_LONGA',
    'db' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'port'     => 3306,
        'database' => 'uXXXXXXX_vtd',
        'username' => 'uXXXXXXX_vtduser',
        'password' => 'SUA_SENHA_FORTE_DO_BANCO',
        'charset'  => 'utf8mb4',
    ],
    'mail' => [
        'driver'       => 'smtp',
        'host'         => 'smtp.hostinger.com',
        'port'         => 465, // SSL
        'encryption'   => 'ssl', // ou 'tls' na porta 587
        'username'     => 'acesso@seudominio.com.br',
        'password'     => 'SENHA_DO_EMAIL_HOSTINGER',
        'from_address' => 'acesso@seudominio.com.br',
        'from_name'    => 'VTD - Viver do Tênis',
    ],
    'greenn' => [
        'webhook_secret'   => 'SEU_TOKEN_SECRETO_CONFIGURADO_NA_GREENN',
        'essential_product_id' => 'ID_DO_PRODUTO_ESSENCIAL',
        'plus_product_id'      => 'ID_DO_PRODUTO_PLUS',
    ],
    'security' => [
        'session_lifetime' => 86400 * 7, // 7 dias
        'csrf_secret'      => 'GERE_UMA_CHAVE_ALEATORIA_LONGA_AQUI_64_CHARS',
        'rate_limit'       => [
            'login_max_attempts' => 5,
            'decay_minutes'      => 15
        ]
    ]
];
```

---

## 4. Configuração do E-mail Transacional na Hostinger (SMTP)

1. No **hPanel**, vá em **E-mails** -> **Contas de E-mail**.
2. Crie uma conta (ex: `acesso@seudominio.com.br` ou `suporte@seudominio.com.br`).
3. Anote os dados do servidor SMTP da Hostinger:
   - **Servidor SMTP**: `smtp.hostinger.com`
   - **Porta**: `465` (SSL) ou `587` (TLS)
   - **Autenticação**: Sim, com e-mail completo e senha
4. O `MailerService` enviará automaticamente:
   - **Boas-vindas com dados de acesso**: quando uma nova loja/cliente for provisionada ou ao comprar na Greenn.
   - **Recuperação de Senha**: link seguro com token temporário (expira em 2 horas).
   - **Upgrade de Plano**: notificação quando o plano for atualizado para `plus`.

---

## 5. Configuração do Webhook da Greenn

1. Acesse sua conta na plataforma da **Greenn** (`greenn.com.br`).
2. Vá em **Configurações** / **Ferramentas** -> **Webhooks**.
3. Cadastre um novo webhook:
   - **URL de Destino**: `https://seudominio.com.br/api/webhooks/greenn`
   - **Método**: `POST`
   - **Eventos Monitorados**:
     - `order_paid` / `payment_approved` (Compra aprovada)
     - `order_refunded` / `subscription_canceled` (Reembolso / Cancelamento)
   - **Chave Secreta / Token**: copie o token gerado e insira na chave `'webhook_secret'` do `backend/config/.env.php`.
4. Mapeie os códigos ou slugs das ofertas no `.env.php`:
   - Ofertas que dão acesso ao plano **Essential** (fornecedores, favoritos, calendário, guia).
   - Ofertas que dão acesso ao plano **Plus** (tudo do Essential + Dashboard Financeiro multi-tenant).

---

## 6. Verificação de Funcionamento e Checklist de Segurança

| Verificação | Teste / Procedimento | Resultado Esperado |
| :--- | :--- | :--- |
| **Proteção do Backend** | Acesse `https://seudominio.com.br/backend/config/.env.php` | Retorna erro **403 Forbidden** |
| **Health Check da API** | Acesse `https://seudominio.com.br/api/health` | Retorna `{"success":true,"data":{"status":"healthy",...}}` |
| **Login do Administrador** | Faça login no modal com `demo@exemplo.com` / `123456` | Redireciona para o painel com token CSRF e perfil ativo |
| **Acesso ao Financeiro** | Usuário `plus` acessa módulo financeiro | Dashboard carrega métricas em centavos convertidas para BRL |
| **Bloqueio no Plano Essential** | Usuário `essential` acessa `/api/finance/overview` | Servidor bloqueia com código HTTP **403 PLAN_UPGRADE_REQUIRED** |
| **Disparo de E-mails** | Crie um novo cliente pelo painel Admin | O cliente recebe o e-mail de boas-vindas com suas credenciais |
| **Webhook da Greenn** | Envie um teste de webhook no painel da Greenn | Status HTTP 200 retornado e cliente criado no banco |

---

## 7. Usuários Padrão para Primeiro Acesso

Após a execução dos seeds iniciais, estão disponíveis as seguintes contas de demonstração:

- **Administrador**:
  - **E-mail**: `demo@exemplo.com`
  - **Senha**: `123456`
  - **Privilégios**: Acesso total, incluindo fornecedores, capítulos, calendário, financeiro e painel administrativo de clientes.

- **Usuário Comum (Loja Ana Souza)**:
  - **E-mail**: `ana@example.com`
  - **Senha**: `123456`
  - **Privilégios**: Plano Essential.

*(Recomendação de Segurança: Altere as senhas no primeiro login através da tela Minha Conta ou pelo Painel Admin).*
