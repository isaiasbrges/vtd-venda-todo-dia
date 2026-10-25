-- Tabela de Lojas / Organizações (Multi-Tenant)
CREATE TABLE IF NOT EXISTS tenants (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    plan VARCHAR(20) NOT NULL DEFAULT 'essential', -- 'essential', 'plus'
    initial_balance_cents BIGINT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active', -- 'active', 'suspended'
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de Usuários
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(36) PRIMARY KEY,
    tenant_id VARCHAR(36) NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user', -- 'admin', 'user'
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Tokens de Recuperação de Senha
CREATE TABLE IF NOT EXISTS password_resets (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(191) NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Rate Limiting
CREATE TABLE IF NOT EXISTS rate_limits (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    action_key VARCHAR(191) NOT NULL UNIQUE,
    attempts INT NOT NULL DEFAULT 1,
    last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 234 Fornecedores (Conteúdo Compartilhado)
CREATE TABLE IF NOT EXISTS suppliers (
    id VARCHAR(20) PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    brands TEXT NULL,
    products TEXT NULL,
    category VARCHAR(100) NULL,
    segment VARCHAR(100) NULL,
    site VARCHAR(255) NULL,
    instagram VARCHAR(255) NULL,
    instagram_handle VARCHAR(100) NULL,
    whatsapp TEXT NULL, -- JSON
    phones TEXT NULL,   -- JSON
    email VARCHAR(255) NULL,
    facebook VARCHAR(255) NULL,
    youtube VARCHAR(255) NULL,
    linkedin VARCHAR(255) NULL,
    tiktok VARCHAR(255) NULL,
    catalogs TEXT NULL, -- JSON
    contact_url VARCHAR(255) NULL,
    score INT NOT NULL DEFAULT 0,
    notes TEXT NULL,
    is_active INT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 8 Capítulos do Guia (Conteúdo Compartilhado)
CREATE TABLE IF NOT EXISTS guide_chapters (
    id INT PRIMARY KEY,
    chapter_number INT NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    task TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 12 Meses do Calendário (Conteúdo Compartilhado)
CREATE TABLE IF NOT EXISTS calendar_months (
    id INT PRIMARY KEY,
    month_number INT NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    short_name VARCHAR(10) NOT NULL,
    `lead` TEXT NOT NULL,
    buy_tip TEXT NOT NULL,
    practical_tip TEXT NOT NULL,
    dates TEXT NOT NULL,          -- JSON
    suggested_items TEXT NOT NULL,-- JSON
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Favoritos do Usuário
CREATE TABLE IF NOT EXISTS user_favorites (
    user_id VARCHAR(36) NOT NULL,
    supplier_id VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, supplier_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
);

-- Checklists do Usuário
CREATE TABLE IF NOT EXISTS user_checklists (
    user_id VARCHAR(36) NOT NULL,
    check_key VARCHAR(64) NOT NULL,
    checked INT NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, check_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Progresso e Notas dos Capítulos
CREATE TABLE IF NOT EXISTS user_chapter_progress (
    user_id VARCHAR(36) NOT NULL,
    chapter_id INT NOT NULL,
    completed INT NOT NULL DEFAULT 0,
    note TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, chapter_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (chapter_id) REFERENCES guide_chapters(id) ON DELETE CASCADE
);

-- Categorias Financeiras por Loja
CREATE TABLE IF NOT EXISTS financial_categories (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    tenant_id VARCHAR(36) NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (tenant_id, name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- Lançamentos Financeiros (Isolamento por Loja)
CREATE TABLE IF NOT EXISTS financial_transactions (
    id VARCHAR(36) PRIMARY KEY,
    tenant_id VARCHAR(36) NOT NULL,
    description VARCHAR(255) NOT NULL,
    type VARCHAR(20) NOT NULL, -- 'income', 'expense'
    value_cents BIGINT NOT NULL,
    category_id INT NOT NULL,
    date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending', -- 'pending', 'paid'
    settled_at DATE NULL,
    created_by_user_id VARCHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES financial_categories(id),
    FOREIGN KEY (created_by_user_id) REFERENCES users(id)
);

-- Trilha de Auditoria Financeira
CREATE TABLE IF NOT EXISTS financial_audit_logs (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    tenant_id VARCHAR(36) NOT NULL,
    transaction_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    action VARCHAR(20) NOT NULL, -- 'create', 'update', 'settle', 'delete'
    snapshot_before TEXT NULL,  -- JSON
    snapshot_after TEXT NULL,   -- JSON
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Eventos de Webhook (Idempotência Greenn)
CREATE TABLE IF NOT EXISTS webhook_events (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    provider VARCHAR(50) NOT NULL,
    event_id VARCHAR(191) NOT NULL UNIQUE,
    event_type VARCHAR(100) NOT NULL,
    payload TEXT NOT NULL,
    processed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
