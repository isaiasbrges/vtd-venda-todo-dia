# Especificação Técnica: Integração Backend VTD (PHP + MySQL PDO)

- **Data**: 2026-10-25
- **Status**: Aprovado para Planejamento
- **Ambiente de Destino**: Hospedagem Compartilhada Hostinger (PHP 8.x, MySQL/MariaDB via PDO, LiteSpeed/Apache)
- **Escopo**: Preservação total do frontend (HTML/CSS/JS) substituindo a camada de persistência mockada em `localStorage` por API REST robusta, segura e multi-tenant com regras de negócio no servidor.

---

## 1. Visão Geral e Princípios Arquiteturais

1. **Preservação Visual e Funcional**: Todas as rotas baseadas em hash (`#/home`, `#/suppliers`, `#/calendar`, `#/guide`, `#/favorites`, `#/finance`, `#/account`, `#/admin`), estilos, SVGs e fluxos de navegação serão 100% preservados. O layout não será redesenhado.
2. **Separação de Responsabilidades em Camadas**:
   - `Config`: Carregamento de credenciais e variáveis fora do diretório público (`.env.php`).
   - `Database`: Conexão PDO segura (Singleton), UTF-8MB4, modo de erro por exceções, migrations e seeds.
   - `Security`: Sessões seguras (`HttpOnly`, `SameSite=Lax`, `Secure`), validação de CSRF em mutações e *Rate Limiting* por IP/usuário contra força bruta.
   - `Middlewares`: Barreiras de autenticação (`AuthMiddleware`), validação estrita de plano (`PlanMiddleware`: `essential` vs. `plus`) e autorização de administração (`AdminMiddleware`).
   - `Controllers & Services`: Regras de negócio de isolamento multi-tenant, validação de centavos inteiros e saldo financeiro no servidor.
   - `API Router`: Ponto de entrada com suporte a roteamento limpo `/api/*` e fallback transparente para query string `api.php?endpoint=*` (essencial para ambientes compartilhados e compatibilidade universal).
3. **Remoção de Modos Demonstrativos em Produção**:
   - Remoção dos botões de atalho de demo (`enter`, `enter-admin`) na tela de login, substituindo-os por login real com e-mail e senha.
   - Remoção do banner superior de demonstração e do botão de alternância manual de ofertas.

---

## 2. Estrutura de Diretórios e Implantação

```
vtd-front/
├── backend/                             # Camada privada (inacessível diretamente via web)
│   ├── .htaccess                        # "Require all denied"
│   ├── config/
│   │   ├── config.php                   # Leitor de configuração e variáveis de ambiente
│   │   └── .env.example.php             # Arquivo de exemplo para deploy na Hostinger
│   ├── src/
│   │   ├── Autoloader.php               # Autoloader nativo PSR-4
│   │   ├── Database/
│   │   │   ├── Connection.php           # PDO Singleton com reconexão e transações
│   │   │   └── Migrator.php             # Executor de migrações e sementes
│   │   ├── Core/
│   │   │   ├── Router.php               # Roteador RESTful com match de métodos e URLs
│   │   │   ├── Request.php              # Parse JSON de corpo, query params e headers
│   │   │   ├── Response.php             # Envio padronizado de JSON (sucesso/erro/cabeçalhos)
│   │   │   └── ExceptionHandler.php     # Captura de exceções e log seguro
│   │   ├── Security/
│   │   │   ├── SessionManager.php       # Gestor de sessão PHP com flags estritas
│   │   │   ├── Csrf.php                 # Geração e validação de tokens CSRF
│   │   │   └── RateLimiter.php          # Limitação de tentativas de autenticação
│   │   ├── Middlewares/
│   │   │   ├── AuthMiddleware.php       # Exige usuário autenticado
│   │   │   ├── PlanMiddleware.php       # Restringe rotas financeiras a plano 'plus'
│   │   │   └── AdminMiddleware.php      # Restringe acesso a usuários com role 'admin'
│   │   ├── Controllers/
│   │   │   ├── AuthController.php       # Login, logout, me, recuperação de senha
│   │   │   ├── SupplierController.php   # Busca, paginação e detalhes de fornecedores
│   │   │   ├── UserContentController.php# Favoritos, checklists, progresso de leitura e notas
│   │   │   ├── CalendarController.php   # Meses, datas comemorativas e sugestões
│   │   │   ├── GuideController.php      # Capítulos do guia e tarefas
│   │   │   ├── FinanceController.php    # Transações, categorias, resumo, quitação e CSV
│   │   │   └── AdminController.php      # Gestão de clientes/lojas e conteúdos
│   │   └── Services/
│   │       ├── FinanceService.php       # Cálculos financeiros em centavos e conciliação
│   │       └── SeedService.php          # Importador dos 234 fornecedores, 8 caps, 12 meses
│   ├── migrations/
│   │   └── 001_initial_schema.sql       # DDL completo das tabelas InnoDB MySQL
│   └── seeds/
│       └── initial_data.json            # Extração estruturada dos dados originais de data.js
├── public/                              # Diretório público (public_html na Hostinger)
│   ├── .htaccess                        # Reescrita para api.php e proteção de arquivos ocultos
│   ├── api.php                          # Gateway único de despacho das requisições /api/*
│   ├── index.html                       # Shell principal da aplicação SPA
│   ├── vendas.html                      # Página de apresentação comercial
│   ├── styles.css                       # Folha de estilo visual original
│   ├── app.js                           # Frontend integrado via fetch à API
│   └── assets/                          # Imagens WebP e mídias estáticas
└── docs/
    ├── DEPLOY_HOSTINGER.md              # Instruções de configuração no hPanel e banco MySQL
    └── superpowers/specs/               # Especificações e planos
```

---

## 3. Esquema Relacional do Banco de Dados (MySQL 8+ / MariaDB)

### 3.1 Tabelas de Controle de Acesso e Multi-Tenant

```sql
-- Lojas / Organizações
CREATE TABLE tenants (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    plan ENUM('essential', 'plus') NOT NULL DEFAULT 'essential',
    initial_balance_cents BIGINT NOT NULL DEFAULT 0,
    status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuários
CREATE TABLE users (
    id VARCHAR(36) PRIMARY KEY,
    tenant_id VARCHAR(36) NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tokens de Recuperação de Senha
CREATE TABLE password_resets (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(191) NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_resets_token (token_hash),
    INDEX idx_resets_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate Limiting para Proteção de Força Bruta
CREATE TABLE rate_limits (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    action_key VARCHAR(191) NOT NULL UNIQUE,
    attempts INT NOT NULL DEFAULT 1,
    last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Conteúdo Compartilhado (Importação de `data.js`)

```sql
-- 234 Fornecedores
CREATE TABLE suppliers (
    id VARCHAR(20) PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    brands TEXT NULL,
    products TEXT NULL,
    category VARCHAR(100) NULL,
    segment VARCHAR(100) NULL,
    site VARCHAR(255) NULL,
    instagram VARCHAR(255) NULL,
    instagram_handle VARCHAR(100) NULL,
    whatsapp JSON NULL,
    phones JSON NULL,
    email VARCHAR(255) NULL,
    facebook VARCHAR(255) NULL,
    youtube VARCHAR(255) NULL,
    linkedin VARCHAR(255) NULL,
    tiktok VARCHAR(255) NULL,
    catalogs JSON NULL,
    contact_url VARCHAR(255) NULL,
    score TINYINT NOT NULL DEFAULT 0,
    notes TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_suppliers_segment (segment),
    INDEX idx_suppliers_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8 Capítulos do Guia
CREATE TABLE guide_chapters (
    id INT PRIMARY KEY,
    chapter_number INT NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    title VARCHAR(255) NOT NULL,
    body LONGTEXT NOT NULL,
    task TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12 Meses do Calendário Comercial
CREATE TABLE calendar_months (
    id INT PRIMARY KEY,
    month_number INT NOT NULL UNIQUE, -- 0 a 11 ou 1 a 12
    name VARCHAR(50) NOT NULL,
    short_name VARCHAR(10) NOT NULL,
    lead TEXT NOT NULL,
    buy_tip TEXT NOT NULL,
    practical_tip TEXT NOT NULL,
    dates JSON NOT NULL,
    suggested_items JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.3 Dados Privados dos Usuários / Lojas

```sql
-- Fornecedores Favoritos por Usuário
CREATE TABLE user_favorites (
    user_id VARCHAR(36) NOT NULL,
    supplier_id VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, supplier_id),
    CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_fav_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Checklists do Calendário e Início por Usuário
CREATE TABLE user_checklists (
    user_id VARCHAR(36) NOT NULL,
    check_key VARCHAR(64) NOT NULL,
    checked TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, check_key),
    CONSTRAINT fk_check_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Progresso e Anotações dos Capítulos por Usuário
CREATE TABLE user_chapter_progress (
    user_id VARCHAR(36) NOT NULL,
    chapter_id INT NOT NULL,
    completed TINYINT(1) NOT NULL DEFAULT 0,
    note TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, chapter_id),
    CONSTRAINT fk_prog_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_prog_chapter FOREIGN KEY (chapter_id) REFERENCES guide_chapters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.4 Controle Financeiro Isolado (Plano `plus`)

```sql
-- Categorias Financeiras Isoladas por Loja
CREATE TABLE financial_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tenant_category (tenant_id, name),
    CONSTRAINT fk_fcat_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lançamentos Financeiros (Entradas e Saídas)
CREATE TABLE financial_transactions (
    id VARCHAR(36) PRIMARY KEY,
    tenant_id VARCHAR(36) NOT NULL,
    description VARCHAR(255) NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    value_cents BIGINT NOT NULL CHECK (value_cents > 0),
    category_id INT NOT NULL,
    date DATE NOT NULL, -- Data de competência / vencimento / pagamento
    status ENUM('pending', 'paid') NOT NULL DEFAULT 'pending',
    settled_at DATE NULL, -- Data efetiva do pagamento/recebimento
    created_by_user_id VARCHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ftx_tenant_date (tenant_id, date),
    INDEX idx_ftx_tenant_status (tenant_id, status),
    CONSTRAINT fk_ftx_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    CONSTRAINT fk_ftx_category FOREIGN KEY (category_id) REFERENCES financial_categories(id) ON DELETE RESTRICT,
    CONSTRAINT fk_ftx_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trilha de Auditoria Financeira
CREATE TABLE financial_audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(36) NOT NULL,
    transaction_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    action ENUM('create', 'update', 'settle', 'delete') NOT NULL,
    snapshot_before JSON NULL,
    snapshot_after JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_faudit_tenant (tenant_id),
    INDEX idx_faudit_tx (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 4. Regras de Negócio e Cálculos Financeiros

1. **Centavos Inteiros**:
   - Nenhum valor monetário é manipulado como ponto flutuante no banco de dados. R$ 1.500,50 é armazenado estritamente como `150050`.
   - No frontend, a exibição e os campos monetários mantêm a formatação em Real (`money(cents)` e `value/100`), sendo convertidos para centavos inteiros antes do envio à API.
2. **Cálculo de Saldos no Servidor**:
   - **Saldo Inicial**: Configurado em `tenants.initial_balance_cents`.
   - **Saldo de Abertura do Mês M**:
     $$\text{Abertura}_M = \text{initial\_balance\_cents} + \sum_{\text{data} < M, \text{paid}} (\text{entradas} - \text{saídas})$$
   - **Entradas e Saídas Realizadas no Mês M**:
     $$\text{Entradas}_M = \sum_{\text{data} \in M, \text{paid}} \text{entradas}, \quad \text{Saídas}_M = \sum_{\text{data} \in M, \text{paid}} \text{saídas}$$
   - **Saldo Atual / Realizado**:
     $$\text{Saldo Atual}_M = \text{Abertura}_M + \text{Entradas}_M - \text{Saídas}_M$$
   - **Saldo Projetado**:
     $$\text{Saldo Projetado}_M = \text{Saldo Atual}_M + \sum_{\text{data} \in M, \text{pending}} (\text{entradas} - \text{saídas})$$
3. **Quitação com Data Efetiva**:
   - Ao liquidar um lançamento pendente (`POST /api/finance/transactions/{id}/settle`), o payload exige `{ "effective_date": "YYYY-MM-DD" }`.
   - O status é alterado para `paid`, `settled_at` é gravado com a data efetiva e `date` é atualizado para a data efetiva, transferindo o impacto financeiro para o mês de competência real.
4. **Isolamento e Controle de Acesso**:
   - Toda consulta em `financial_transactions`, `financial_categories` e `financial_audit_logs` inclui obrigatoriamente a cláusula `WHERE tenant_id = :tenant_id`.
   - O `PlanMiddleware` rejeita requisições a rotas `/api/finance/*` com código HTTP `403 Forbidden` (`PLAN_UPGRADE_REQUIRED`) se `tenant.plan !== 'plus'`.

---

## 5. Contratos dos Endpoints da API

Todas as respostas da API seguem o envelope JSON padrão:
- **Sucesso**:
  ```json
  {
    "success": true,
    "data": { ... }
  }
  ```
- **Erro**:
  ```json
  {
    "success": false,
    "error": {
      "code": "VALIDATION_ERROR",
      "message": "Descrição amigável do erro",
      "details": {}
    }
  }
  ```

### 5.1 Autenticação e Conta
- `POST /api/auth/login`: `{ "email": "...", "password": "..." }` -> Inicia sessão, retorna dados do usuário, loja e token CSRF.
- `POST /api/auth/logout`: Destrói sessão.
- `POST /api/auth/forgot-password`: `{ "email": "..." }` -> Cria token de recuperação e expiração.
- `POST /api/auth/reset-password`: `{ "token": "...", "new_password": "..." }` -> Atualiza hash da senha.
- `GET /api/me`: Retorna sessão atual, perfil, loja, plano e token CSRF.
- `PUT /api/me`: `{ "name": "...", "store": "...", "email": "...", "initial_balance_cents": 200000 }` -> Atualiza configurações da conta e saldo inicial da loja.

### 5.2 Fornecedores e Favoritos
- `GET /api/suppliers?search=&segment=&wa=1&cat=1&page=1&limit=12`: Busca fornecedores com paginação no banco.
- `GET /api/suppliers/{id}`: Detalhes do fornecedor.
- `GET /api/favorites`: Lista de IDs de fornecedores favoritados pelo usuário ativo.
- `POST /api/favorites`: `{ "supplier_id": "1" }` -> Adiciona aos favoritos.
- `DELETE /api/favorites/{supplier_id}`: Remove dos favoritos.

### 5.3 Calendário e Checklists
- `GET /api/calendar`: Lista dos 12 meses, datas estratégicas e sugestões de produtos.
- `GET /api/checklists`: Mapa `{ [check_key]: true }` das marcações salvas do lojista.
- `POST /api/checklists`: `{ "key": "m-10-1", "checked": true }` -> Atualiza item do checklist.

### 5.4 Guia e Anotações
- `GET /api/chapters`: Lista dos 8 capítulos do guia.
- `GET /api/reading-progress`: Lista de IDs de capítulos concluídos `[0, 1, 2]`.
- `POST /api/reading-progress`: `{ "chapter_id": 1, "completed": true }` -> Conclui ou reabre capítulo.
- `GET /api/notes`: Mapa `{ [chapter_id]: "texto" }` das anotações do usuário.
- `POST /api/notes`: `{ "chapter_id": 1, "note": "..." }` -> Salva anotação.

### 5.5 Financeiro (`plan = 'plus'`)
- `GET /api/finance/overview?month=YYYY-MM`: Retorna resumo do mês (saldo de abertura, entradas recebidas, saídas pagas, saldo atual, saldo projetado, série semanal do gráfico e total por categoria).
- `GET /api/finance/transactions?month=YYYY-MM&type=&status=&search=`: Lista lançamentos filtrados.
- `POST /api/finance/transactions`: `{ "description": "...", "type": "income|expense", "value_cents": 10000, "category_id": 1, "date": "2026-10-25", "status": "pending|paid" }` -> Cria lançamento e registra auditoria.
- `PUT /api/finance/transactions/{id}`: Atualiza lançamento e audita.
- `DELETE /api/finance/transactions/{id}`: Exclui lançamento e audita.
- `POST /api/finance/transactions/{id}/settle`: `{ "effective_date": "YYYY-MM-DD" }` -> Quita lançamento.
- `GET /api/finance/categories`: Lista de categorias da loja com contagem de lançamentos vinculados.
- `POST /api/finance/categories`: `{ "name": "Nova Categoria" }`.
- `DELETE /api/finance/categories/{id}`: Exclui categoria se não houver lançamentos vinculados.
- `GET /api/finance/export-csv?month=YYYY-MM`: Gera e faz streaming do arquivo CSV com formatação brasileira.

### 5.6 Administração (`role = 'admin'`) e Provisionamento de Contas
- `GET /api/admin/overview`: Contagem de fornecedores, clientes, capítulos e meses.
- `GET /api/admin/clients`: Lista de clientes/lojas com status, plano e data de criação.
- `POST /api/admin/clients`: **Criação de Conta para Cliente**:
  - Payload: `{ "name": "Nome do Cliente", "email": "cliente@email.com", "password": "...", "store": "Nome da Loja", "plan": "plus", "initial_balance_cents": 0 }`.
  - Comportamento: Cria a loja/tenant do cliente e o usuário com senha criptografada (`password_hash`), provisionando as categorias financeiras padrão para que o cliente tenha acesso imediato ao seu dashboard de controle financeiro isolado.
- `PUT /api/admin/clients/{id}`: Edição de dados do cliente, troca de plano (`essential` <-> `plus`) ou redefinição de senha.
- `PATCH /api/admin/clients/{id}/toggle`: Alterna status entre `active` e `suspended`.
- `PUT /api/admin/suppliers/{id}`: Atualização cadastral de fornecedor.
- `PUT /api/admin/chapters/{id}`: Atualização de texto/tarefa do capítulo.
- `PUT /api/admin/calendar/{id}`: Atualização de sugestões e orientações do mês.

### 5.7 Integração com a Plataforma Greenn (`/api/webhooks/greenn`)

A plataforma **Greenn** é o provedor oficial de checkout e vendas dos infoprodutos.

1. **Configuração e Segurança**:
   - Chave secreta de validação do webhook da Greenn no `.env.php` (`GREENN_WEBHOOK_SECRET`).
   - Validação da autenticidade da requisição enviada pelos servidores da Greenn.
   - Tabela `webhook_events` para garantir **idempotência**: se a Greenn reenviar o mesmo evento ou ID de transação, o sistema não duplica lojas nem usuários.

2. **Automação no Fluxo de Compra da Greenn**:
   - **Venda Aprovada (`order_paid` / `approved`)**:
     - O webhook captura os dados do comprador enviados pela Greenn (Nome, E-mail, Telefone, ID da Oferta/Produto).
     - Identifica a oferta comprada:
       - Se for o produto/oferta do *Kit Essencial* -> vincula ao plano `essential`.
       - Se for o produto/oferta do *Kit + Financeiro* -> vincula ao plano `plus` (libera o dashboard financeiro imediatamente).
     - Caso o cliente seja novo:
       - Cria o registro em `tenants` (loja individual);
       - Cria o usuário em `users` com senha temporária segura ou hash de acesso;
       - Inicializa as categorias financeiras padrão para o dashboard.
     - Caso o cliente já possua cadastro e adquira o upgrade:
       - Atualiza o plano da loja para `plus`, liberando o financeiro no próximo login.
   - **Reembolso ou Cancelamento (`order_refunded` / `chargeback`)**:
     - Suspende automaticamente a loja (`tenants.status = 'suspended'`), revogando o acesso do cliente com segurança.

---

### 5.8 Serviço de E-mail Transacional e Envio de Acessos (`MailerService`)

Para que os compradores e clientes recebam suas credenciais automaticamente sem esforço manual:

1. **Configuração SMTP (Padrão Hostinger)**:
   - Configurado no `.env.php`:
     - `MAIL_DRIVER`: `'smtp'` (ou `'mail'` para função nativa)
     - `SMTP_HOST`: `smtp.hostinger.com` (servidor de e-mail profissional da Hostinger)
     - `SMTP_PORT`: `465` (SSL) ou `587` (TLS)
     - `SMTP_USER`: e-mail corporativo (ex.: `acesso@seudominio.com.br`)
     - `SMTP_PASS`: senha do e-mail
     - `MAIL_FROM_NAME`: `'VTD · Venda Todo Dia'`
     - `MAIL_FROM_ADDRESS`: e-mail de envio oficial
   - Implementação de cliente SMTP nativo e seguro em PHP puro (sem necessidade de carregar bibliotecas externas pesadas).

2. **Templates de E-mail Responsivos (HTML + Texto)**:
   - **E-mail 1: Boas-vindas e Entrega de Acesso (Compra na Greenn ou Cadastro no Admin)**:
     - Assunto: `Seu acesso ao Venda Todo Dia chegou!`
     - Conteúdo: Saudação personalizada, confirmação do plano (`Kit Essencial` ou `Kit + Financeiro`), link direto para login (`https://seudominio.com.br/#/login`), e-mail de acesso e senha gerada (com recomendação de alteração no primeiro acesso).
     - Identidade visual com o visual do VTD (cabeçalho premium, botão de ação em destaque e rodapé de suporte).
   - **E-mail 2: Recuperação de Senha**:
     - Assunto: `Recuperação de acesso · Venda Todo Dia`
     - Conteúdo: Link com token temporário criptográfico de 60 minutos para redefinição segura de senha.
   - **E-mail 3: Notificação de Upgrade**:
     - Assunto: `Módulo Financeiro liberado na sua conta!`
     - Conteúdo: Avisa que o dashboard financeiro foi ativado na loja do cliente após a confirmação da compra.

---

## 6. Adaptação do Frontend (`app.js`)

1. **Centralização HTTP (`apiClient`)**:
   - Criação de objeto auxiliar `api` com métodos `get(url)`, `post(url, data)`, `put(url, data)`, `delete(url)`.
   - Adiciona automaticamente os cabeçalhos `Content-Type: application/json` e `X-CSRF-Token`.
   - Suporte transparente a URLs amigáveis com fallback de query string (`/api/suppliers` ou `api.php?endpoint=suppliers`).
   - Redirecionamento automático para `#/login` em caso de sessão expirada (`401`).
2. **Substituição da Autenticação no Front**:
   - A tela `#/login` renderiza formulário real com campos de E-mail e Senha.
   - Remoção dos botões "Explorar Kit + Financeiro", "Explorar Kit Essencial" e "Conhecer a administração".
   - Submissão via `api.post('/api/auth/login', { email, password })`.
3. **Limpeza da Barra Superior e Menus**:
   - Ocultação do banner demonstrativo (`.demo-banner`).
   - A opção de menu "Administração" é exibida apenas se `user.role === 'admin'`.
   - A área financeira exibe a tela de upgrade caso o plano retornado pelo servidor seja `essential`.

---

## 7. Estratégia de Testes e Validação

1. **Testes de Integração Backend (CLI PHP)**:
   - Script de teste ponta a ponta (`tests/run_tests.php`) validando:
     - Criação de tenants e isolamento de dados entre duas lojas distintas.
     - Login com credenciais válidas e rejeição de senha incorreta.
     - Bloqueio de rota financeira para plano `essential` (esperado 403).
     - Validação de saldo realizado vs projetado e conciliação com data efetiva de quitação.
     - Gravação correta na trilha de auditoria.
2. **Verificação no Navegador**:
   - Validação da renderização sem quebras de layout nas resoluções Desktop (1280px+) e Mobile (375px).
   - Teste funcional de busca de fornecedores, filtros de WhatsApp e favoritos.
   - Teste de checklist e gravação de anotações no Guia.
   - Teste de inclusão, quitação e exclusão de lançamentos financeiros.
