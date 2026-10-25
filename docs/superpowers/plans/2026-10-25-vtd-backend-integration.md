# Integração Backend VTD Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar o backend completo em PHP e MySQL via PDO com arquitetura em camadas para a hospedagem Hostinger, importando 234 fornecedores, 8 capítulos e 12 meses de `data.js`, isolando dados multi-tenant e financeiro por loja com centavos inteiros, integrando e-mails transacionais (Hostinger SMTP) e webhook da Greenn, e conectando o frontend existente sem alterar seu design visual.

**Architecture:** Arquitetura limpa em camadas com PHP puro (PSR-4 autoloader nativo, sem dependências externas obrigatórias), separando `backend/` privado de `public/` web root. Acesso seguro via PDO Singleton, sessões estritas, CSRF, rate limiting, middlewares de plano (`essential` vs `plus`), serviço de financeiro com regras de centavos e auditoria, e cliente de API no frontend substituindo o `localStorage`.

**Tech Stack:** PHP 8.1+, MySQL 8+ / MariaDB via PDO (com suporte fallback a SQLite para testes rápidos em CLI), LiteSpeed/Apache (.htaccess), HTML5, CSS3 vanilla, JavaScript ES6+.

**Spec:** `docs/superpowers/specs/2026-10-25-vtd-backend-integration-design.md`

## Global Constraints

- Preservar 100% do layout visual, classes CSS, paleta de cores, ícones e navegação por hash (`#/home`, `#/suppliers`, `#/calendar`, `#/guide`, `#/favorites`, `#/finance`, `#/account`, `#/admin`).
- Nenhuma credencial ou arquivo do backend exposto em pasta pública sem proteção `.htaccess`.
- Toda manipulação de valores financeiros no banco e backend deve ser feita em centavos inteiros (`BIGINT`).
- Rotas `/api/finance/*` devem responder `403 Forbidden` (`PLAN_UPGRADE_REQUIRED`) se o plano da loja não for `plus`.
- Toda consulta financeira deve conter a cláusula de isolamento `tenant_id = :tenant_id`.
- Suporte a chamadas RESTful diretas (`/api/...`) com fallback transparente para query string (`api.php?endpoint=...`).

## Review Focus

1. **Tentativa de acesso financeiro por conta Essential:** Requisição a qualquer endpoint `/api/finance/*` deve retornar HTTP 403 mesmo manipulando o front.
2. **Isolamento de lojas no financeiro e anotações:** Lançamento financeiro ou anotação criada pelo Usuário A da Loja A jamais deve aparecer para o Usuário B da Loja B.
3. **Quitação de contas com data efetiva:** Ao quitar lançamento pendente de outubro em novembro, o valor realizado deve passar a compor o saldo de abertura e realizado de novembro, não de outubro.
4. **Idempotência no Webhook da Greenn:** Duas chamadas seguidas da Greenn com o mesmo ID de transação não devem duplicar o tenant nem o usuário.
5. **Tentativas excessivas de login:** Mais de 5 tentativas com senha incorreta devem bloquear o IP/usuário temporariamente (HTTP 429).

---

### Task 1: Fundação do Backend, Configurações e Conexão PDO

**Files:**
- Create: `backend/.htaccess`
- Create: `backend/config/config.php`
- Create: `backend/config/.env.example.php`
- Create: `backend/src/Autoloader.php`
- Create: `backend/src/Database/Connection.php`
- Create: `backend/src/Core/Request.php`
- Create: `backend/src/Core/Response.php`
- Create: `backend/src/Core/Router.php`
- Create: `public/api.php`
- Create: `public/.htaccess`
- Test: `tests/test_foundation.php`

**Interfaces:**
- Produces:
  - `VTD\Database\Connection::getInstance(): PDO`
  - `VTD\Core\Router::get(string $path, callable|array $handler, array $middlewares = [])`
  - `VTD\Core\Router::post(string $path, callable|array $handler, array $middlewares = [])`
  - `VTD\Core\Router::dispatch(): void`
  - `VTD\Core\Response::json(mixed $data, int $status = 200, array $headers = []): void`
  - `VTD\Core\Response::error(string $code, string $message, int $status = 400): void`

- [ ] **Step 1: Write the failing test for foundation**

```php
// tests/test_foundation.php
require_once __DIR__ . '/../backend/src/Autoloader.php';
\VTD\Autoloader::register();

assert(class_exists('\VTD\Database\Connection'));
assert(class_exists('\VTD\Core\Router'));
assert(class_exists('\VTD\Core\Response'));

$pdo = \VTD\Database\Connection::getInstance();
assert($pdo instanceof \PDO);

$router = new \VTD\Core\Router();
$router->get('/health', fn() => \VTD\Core\Response::json(['status' => 'ok']));
echo "Foundation test passed\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_foundation.php`
Expected: FAIL with fatal error or class not found.

- [ ] **Step 3: Implement Autoloader, Connection, Core classes and api.php**

Implement:
1. `backend/src/Autoloader.php`: Mapeia namespace `VTD\` para `backend/src/`.
2. `backend/config/config.php`: Lê `.env.php` (se existir) com fallbacks para variáveis de ambiente e suporte a SQLite em testes/desenvolvimento e MySQL na Hostinger.
3. `backend/src/Database/Connection.php`: Singleton PDO com `ATTR_ERRMODE => ERRMODE_EXCEPTION`, `ATTR_DEFAULT_FETCH_MODE => FETCH_ASSOC`, UTF8MB4.
4. `backend/src/Core/Request.php`: Parse de JSON do `php://input`, query params e headers.
5. `backend/src/Core/Response.php`: Métodos `json()` e `error()`.
6. `backend/src/Core/Router.php`: Match de rotas via URI ou query string `endpoint`.
7. `public/api.php` e `.htaccess`: Entrada e regras de reescrita.
8. `backend/.htaccess`: `Require all denied`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_foundation.php`
Expected: Output "Foundation test passed".

---

### Task 2: Esquema do Banco de Dados e Script de Seed (`data.js`)

**Files:**
- Create: `backend/migrations/001_initial_schema.sql`
- Create: `backend/src/Database/Migrator.php`
- Create: `backend/seeds/seed_data.php`
- Test: `tests/test_migrations_and_seeds.php`

**Interfaces:**
- Consumes: `VTD\Database\Connection`
- Produces:
  - `VTD\Database\Migrator::runMigrations(): void`
  - `VTD\Database\Migrator::runSeeds(): void`

- [ ] **Step 1: Write the failing test for schema and seeds**

```php
// tests/test_migrations_and_seeds.php
require_once __DIR__ . '/../backend/src/Autoloader.php';
\VTD\Autoloader::register();

$migrator = new \VTD\Database\Migrator();
$migrator->runMigrations();
$migrator->runSeeds();

$pdo = \VTD\Database\Connection::getInstance();
$suppliersCount = $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
$chaptersCount = $pdo->query("SELECT COUNT(*) FROM guide_chapters")->fetchColumn();
$monthsCount = $pdo->query("SELECT COUNT(*) FROM calendar_months")->fetchColumn();

assert($suppliersCount == 234, "Expected 234 suppliers, got $suppliersCount");
assert($chaptersCount == 8, "Expected 8 chapters, got $chaptersCount");
assert($monthsCount == 12, "Expected 12 months, got $monthsCount");
echo "Migrations and seeds test passed\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_migrations_and_seeds.php`
Expected: FAIL with missing Migrator or table not found.

- [ ] **Step 3: Implement 001_initial_schema.sql, Migrator.php and seed_data.php**

1. `001_initial_schema.sql`: DDL completo das tabelas (`tenants`, `users`, `password_resets`, `rate_limits`, `suppliers`, `guide_chapters`, `calendar_months`, `user_favorites`, `user_checklists`, `user_chapter_progress`, `financial_categories`, `financial_transactions`, `financial_audit_logs`, `webhook_events`).
2. `seed_data.php`: Extrai fielmente os dados de `data.js` (234 fornecedores, 8 capítulos e 12 meses) e insere nas tabelas. Cria também o tenant inicial padrão e o usuário admin de teste.
3. `Migrator.php`: Executa as instruções SQL no PDO conectado.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_migrations_and_seeds.php`
Expected: PASS (234 fornecedores, 8 capítulos, 12 meses confirmados).

---

### Task 3: Autenticação, Sessões Seguras e Controle de Acesso

**Files:**
- Create: `backend/src/Security/SessionManager.php`
- Create: `backend/src/Security/Csrf.php`
- Create: `backend/src/Security/RateLimiter.php`
- Create: `backend/src/Middlewares/AuthMiddleware.php`
- Create: `backend/src/Controllers/AuthController.php`
- Test: `tests/test_auth.php`

**Interfaces:**
- Consumes: `VTD\Database\Connection`, `VTD\Core\Request`, `VTD\Core\Response`
- Produces:
  - `VTD\Controllers\AuthController::login(Request $req): void`
  - `VTD\Controllers\AuthController::logout(Request $req): void`
  - `VTD\Controllers\AuthController::me(Request $req): void`
  - `VTD\Controllers\AuthController::updateProfile(Request $req): void`
  - `VTD\Middlewares\AuthMiddleware::handle(Request $req): ?array`

- [ ] **Step 1: Write the failing test for authentication**

```php
// tests/test_auth.php
require_once __DIR__ . '/../backend/src/Autoloader.php';
\VTD\Autoloader::register();

// Test login with valid credentials
$auth = new \VTD\Controllers\AuthController();
// Test login with invalid password -> error
// Test rate limiter after 5 attempts -> 429
// Test AuthMiddleware with valid vs invalid session
echo "Auth test assertions...\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_auth.php`
Expected: FAIL.

- [ ] **Step 3: Implement SessionManager, Csrf, RateLimiter and AuthController**

1. `SessionManager`: Inicializa `session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => ...])`.
2. `RateLimiter`: Grava tentativas na tabela `rate_limits`, bloqueando se `attempts >= 5` dentro de 15 minutos.
3. `Csrf`: Gera e valida token `X-CSRF-Token` em requisições de mutação (POST, PUT, DELETE).
4. `AuthController`:
   - `login`: Busca usuário por email, valida `password_verify`, grava sessão `$_SESSION['user_id'] = $user['id']`.
   - `logout`: Destrói sessão.
   - `me`: Retorna dados do usuário, tenant, plano e token CSRF.
   - `updateProfile`: Atualiza nome, email, loja e saldo inicial da loja.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_auth.php`
Expected: PASS.

---

### Task 4: Serviço de E-mail Transacional (`MailerService`)

**Files:**
- Create: `backend/src/Services/MailerService.php`
- Create: `backend/src/Views/emails/welcome.php`
- Create: `backend/src/Views/emails/reset_password.php`
- Create: `backend/src/Views/emails/upgrade_plus.php`
- Modify: `backend/src/Controllers/AuthController.php` (métodos `forgotPassword` e `resetPassword`)
- Test: `tests/test_mailer.php`

**Interfaces:**
- Consumes: `backend/config/config.php`
- Produces:
  - `VTD\Services\MailerService::send(string $toEmail, string $toName, string $subject, string $htmlBody): bool`
  - `VTD\Services\MailerService::sendWelcomeCredentials(string $email, string $name, string $password, string $plan): bool`
  - `VTD\Services\MailerService::sendPasswordReset(string $email, string $token): bool`

- [ ] **Step 1: Write the failing test for MailerService**

```php
// tests/test_mailer.php
require_once __DIR__ . '/../backend/src/Autoloader.php';
\VTD\Autoloader::register();

$mailer = new \VTD\Services\MailerService(['mock' => true]);
$sent = $mailer->sendWelcomeCredentials('teste@exemplo.com', 'João', 'Senha123', 'plus');
assert($sent === true);
echo "MailerService test passed\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_mailer.php`
Expected: FAIL.

- [ ] **Step 3: Implement MailerService and HTML email templates**

1. `MailerService.php`: Implementação de cliente SMTP puro (suporta `smtp.hostinger.com` SSL 465 / TLS 587 e fallback para `mail()` ou modo mock em testes).
2. Templates HTML responsivos com estilo visual VTD (cabeçalho preto/amarelo, dados da conta, botão de login).
3. Conectar geração de token no `forgotPassword` e envio do link de recuperação.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_mailer.php`
Expected: PASS.

---

### Task 5: Endpoints de Conteúdo e Interações do Usuário

**Files:**
- Create: `backend/src/Controllers/SupplierController.php`
- Create: `backend/src/Controllers/CalendarController.php`
- Create: `backend/src/Controllers/GuideController.php`
- Create: `backend/src/Controllers/UserContentController.php`
- Test: `tests/test_content_and_user_interactions.php`

**Interfaces:**
- Consumes: `VTD\Database\Connection`, `VTD\Middlewares\AuthMiddleware`
- Produces:
  - `GET /api/suppliers` (busca com paginação, filtro de segmento, whatsApp e catálogo)
  - `GET /api/suppliers/{id}`
  - `GET /api/favorites`, `POST /api/favorites`, `DELETE /api/favorites/{id}`
  - `GET /api/calendar`, `GET /api/checklists`, `POST /api/checklists`
  - `GET /api/chapters`, `GET /api/reading-progress`, `POST /api/reading-progress`, `GET /api/notes`, `POST /api/notes`

- [ ] **Step 1: Write the failing test for content and interactions**

```php
// tests/test_content_and_user_interactions.php
require_once __DIR__ . '/../backend/src/Autoloader.php';
\VTD\Autoloader::register();

// Test supplier search for "ABP"
// Test favorite toggle for authenticated user
// Test calendar checklist toggle
// Test chapter completion and notes persistence
echo "Content interactions assertions...\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_content_and_user_interactions.php`
Expected: FAIL.

- [ ] **Step 3: Implement Controllers and register routes in Router**

1. `SupplierController`: Paginação eficiente no SQL (`LIMIT` e `OFFSET`), filtros de busca textual insensível a acentos/maiúsculas.
2. `UserContentController`: Manipula favoritos, checklists, progresso de leitura e anotações vinculados estritamente ao `user_id` da sessão ativa.
3. `CalendarController` e `GuideController`: Retornam os dados estruturados do calendário e capítulos.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_content_and_user_interactions.php`
Expected: PASS.

---

### Task 6: Módulo Financeiro com Isolamento, Centavos e Auditoria

**Files:**
- Create: `backend/src/Middlewares/PlanMiddleware.php`
- Create: `backend/src/Services/FinanceService.php`
- Create: `backend/src/Controllers/FinanceController.php`
- Test: `tests/test_finance_module.php`

**Interfaces:**
- Consumes: `VTD\Database\Connection`, `VTD\Middlewares\AuthMiddleware`
- Produces:
  - `VTD\Middlewares\PlanMiddleware::requirePlan(string $requiredPlan): void`
  - `VTD\Services\FinanceService::calculateMonthOverview(string $tenantId, string $month): array`
  - `VTD\Services\FinanceService::settleTransaction(string $tenantId, string $txId, string $effectiveDate, string $userId): array`
  - Endpoints `/api/finance/overview`, `/api/finance/transactions`, `/api/finance/categories`, `/api/finance/export-csv`

- [ ] **Step 1: Write the failing test for the financial engine**

```php
// tests/test_finance_module.php
require_once __DIR__ . '/../backend/src/Autoloader.php';
\VTD\Autoloader::register();

// Test: user with plan 'essential' gets 403 Forbidden
// Test: transaction creation with cents (R$ 100,50 -> 10050)
// Test: balance calculations (initial balance + paid income - paid expense)
// Test: settling pending transaction with effective date changes month of impact
// Test: audit log recorded on creation and deletion
echo "Finance module assertions...\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_finance_module.php`
Expected: FAIL.

- [ ] **Step 3: Implement PlanMiddleware, FinanceService and FinanceController**

1. `PlanMiddleware`: Verifica se `tenant.plan === 'plus'`. Se for `essential`, retorna HTTP 403 com `code: 'PLAN_UPGRADE_REQUIRED'`.
2. `FinanceService`:
   - Aritmética estrita em inteiros (`int` / `BIGINT`).
   - Saldo de Abertura do Mês, Realizado e Projetado.
   - Quitação com registro de `settled_at` e atualização de `date` para a data efetiva.
   - Gravação automática de `financial_audit_logs` com snapshot antes e depois.
3. `FinanceController`:
   - CRUD de transações e categorias.
   - Exportação em CSV no padrão brasileiro (separador `;`, codificação UTF-8 com BOM).

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_finance_module.php`
Expected: PASS.

---

### Task 7: Administração e Provisionamento de Contas (`/api/admin/*`)

**Files:**
- Create: `backend/src/Middlewares/AdminMiddleware.php`
- Create: `backend/src/Controllers/AdminController.php`
- Test: `tests/test_admin_module.php`

**Interfaces:**
- Consumes: `VTD\Database\Connection`, `VTD\Middlewares\AuthMiddleware`, `VTD\Services\MailerService`
- Produces:
  - `GET /api/admin/overview`
  - `GET /api/admin/clients`, `POST /api/admin/clients`, `PUT /api/admin/clients/{id}`, `PATCH /api/admin/clients/{id}/toggle`
  - `PUT /api/admin/suppliers/{id}`, `PUT /api/admin/chapters/{id}`, `PUT /api/admin/calendar/{id}`

- [ ] **Step 1: Write the failing test for AdminController**

```php
// tests/test_admin_module.php
require_once __DIR__ . '/../backend/src/Autoloader.php';
\VTD\Autoloader::register();

// Test: non-admin gets 403 Forbidden
// Test: admin creates client with plan 'plus' -> tenant and user created, welcome email triggered
// Test: toggle client status -> active <-> suspended
// Test: suspended user cannot login
echo "Admin module assertions...\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_admin_module.php`
Expected: FAIL.

- [ ] **Step 3: Implement AdminMiddleware and AdminController**

1. `AdminMiddleware`: Exige `$_SESSION['user_role'] === 'admin'`.
2. `AdminController`:
   - Listagem e criação de clientes (gera Loja/Tenant, Usuário com senha hash, categorias financeiras iniciais e dispara e-mail de boas-vindas com as credenciais via `MailerService`).
   - Alteração de status e edição de conteúdos administrativos.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_admin_module.php`
Expected: PASS.

---

### Task 8: Webhook da Greenn com Automação de Acesso

**Files:**
- Create: `backend/src/Controllers/GreennWebhookController.php`
- Test: `tests/test_greenn_webhook.php`

**Interfaces:**
- Consumes: `VTD\Database\Connection`, `VTD\Services\MailerService`
- Produces:
  - `POST /api/webhooks/greenn`

- [ ] **Step 1: Write the failing test for Greenn webhook**

```php
// tests/test_greenn_webhook.php
require_once __DIR__ . '/../backend/src/Autoloader.php';
\VTD\Autoloader::register();

// Test: invalid secret token -> 401 Unauthorized
// Test: order_paid event for 'Kit + Financeiro' -> provisions tenant with plan 'plus', creates user, sends welcome email
// Test: same order_paid event re-sent -> returns 200 without duplicating user/tenant (idempotency)
// Test: order_refunded event -> marks tenant status = 'suspended'
echo "Greenn webhook assertions...\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_greenn_webhook.php`
Expected: FAIL.

- [ ] **Step 3: Implement GreennWebhookController**

1. Validação de token de segurança da Greenn.
2. Tratamento de eventos `order_paid`, `order_refunded`, `order_chargedback`.
3. Tabela `webhook_events` para registro de idempotência.
4. Criação automática de loja, usuário, categorias e disparo do e-mail de acesso.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_greenn_webhook.php`
Expected: PASS.

---

### Task 9: Adaptação do Frontend (`app.js`) para a API REST

**Files:**
- Modify: `C:\Users\Isaias\Music\vtd-front\app.js`
- Test: `tests/test_frontend_smoke.php` (validação de endpoints consumidos e sintaxe JS)

**Interfaces:**
- Consumes: Todos os endpoints `/api/*`
- Produces: UI funcional sem dependência de `localStorage` para persistência, preservando 100% dos seletores, classes e elementos do DOM.

- [ ] **Step 1: Write client HTTP wrapper in `app.js`**

Implementar objeto auxiliar `api` em `app.js` com `fetch`, `credentials: 'include'` e envio de cabeçalho `X-CSRF-Token`.

- [ ] **Step 2: Substituir tela de login por formulário real de email/senha**

Substituir botões de demonstração (`enter`, `enter-admin`) por campos reais de e-mail e senha, submetendo para `api.post('/api/auth/login', ...)`.

- [ ] **Step 3: Conectar navegação, fornecedores, favoritos e calendário à API**

Substituir leitura/escrita em `S.suppliers`, `S.favs`, `S.checks`, `S.notes`, `S.read` por chamadas assíncronas à API com atualização de estado em memória.

- [ ] **Step 4: Conectar módulo financeiro e administração à API**

Substituir mutações financeiras locais por requisições `/api/finance/*` e `/api/admin/*`.

- [ ] **Step 5: Ocultar banner de demonstração em produção e remover alternador forçado**

Ocultar banner demonstrativo e garantir exibição do menu de administração somente para usuários administradores.

- [ ] **Step 6: Verificar sintaxe e execução do front**

Run: `php -l public/api.php` e validar carregamento no navegador.

---

### Task 10: Documentação de Deploy na Hostinger e Verificação Completa

**Files:**
- Create: `docs/DEPLOY_HOSTINGER.md`
- Create: `tests/run_all_tests.php`

- [ ] **Step 1: Write comprehensive test runner executing all backend test suites**

```php
// tests/run_all_tests.php
$suites = [
    'test_foundation.php',
    'test_migrations_and_seeds.php',
    'test_auth.php',
    'test_mailer.php',
    'test_content_and_user_interactions.php',
    'test_finance_module.php',
    'test_admin_module.php',
    'test_greenn_webhook.php'
];
foreach ($suites as $suite) {
    passthru("php " . __DIR__ . "/$suite", $code);
    if ($code !== 0) exit($code);
}
echo "\nAll integration tests passed successfully!\n";
```

- [ ] **Step 2: Run all tests to verify end-to-end correctness**

Run: `php tests/run_all_tests.php`
Expected: Output "All integration tests passed successfully!".

- [ ] **Step 3: Write DEPLOY_HOSTINGER.md**

Passo a passo detalhado para o hPanel da Hostinger:
1. Criação do banco MySQL e usuário no hPanel.
2. Configuração do arquivo `backend/config/.env.php`.
3. Execução das migrações e sementes via script ou importação de `.sql`.
4. Upload dos arquivos (conteúdo de `public/` dentro de `public_html/` e `backend/` na raiz da hospedagem).
5. Configuração das credenciais SMTP e URL do Webhook na Greenn.
