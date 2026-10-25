-- Migração 002: Painel Master, Produtos, Acessos, Integrações e Logs de Webhooks

-- 1. Catálogo de Produtos do Sistema
CREATE TABLE IF NOT EXISTS products (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    plan_level VARCHAR(20) NOT NULL, -- 'essential', 'plus'
    description TEXT NULL,
    features TEXT NULL,             -- JSON
    is_active INT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 2. Acessos e Compras Vinculadas por Cliente
CREATE TABLE IF NOT EXISTS client_accesses (
    id VARCHAR(36) PRIMARY KEY,
    tenant_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    product_id VARCHAR(50) NOT NULL,
    source VARCHAR(20) NOT NULL DEFAULT 'manual', -- 'manual', 'webhook'
    external_reference VARCHAR(191) NULL,         -- ID do pedido / transação externa
    status VARCHAR(20) NOT NULL DEFAULT 'active', -- 'active', 'revoked', 'expired'
    expires_at DATETIME NULL,
    granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- 3. Integrações de Checkout e Webhooks
CREATE TABLE IF NOT EXISTS integrations (
    id VARCHAR(36) PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    provider VARCHAR(50) NOT NULL,                -- 'greenn', 'kiwify', 'hotmart', 'custom'
    environment VARCHAR(20) NOT NULL DEFAULT 'production', -- 'production', 'sandbox'
    status VARCHAR(20) NOT NULL DEFAULT 'active', -- 'active', 'inactive'
    webhook_token VARCHAR(64) NOT NULL UNIQUE,
    secret_key VARCHAR(255) NULL,
    offer_mappings TEXT NOT NULL DEFAULT ('{}'),    -- JSON {"ext_offer_id": "product_id"}
    enabled_events TEXT NOT NULL DEFAULT ('[]'),    -- JSON ["order_paid", "order_refunded", ...]
    last_event_at DATETIME NULL,
    last_event_status VARCHAR(20) NULL,           -- 'success', 'failed', 'ignored'
    last_event_summary TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 4. Logs Detalhados de Eventos e Webhooks
CREATE TABLE IF NOT EXISTS integration_events (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    integration_id VARCHAR(36) NULL,
    provider VARCHAR(50) NOT NULL,
    event_id VARCHAR(191) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    external_reference VARCHAR(191) NULL,
    client_email VARCHAR(191) NULL,
    payload TEXT NOT NULL,                        -- JSON sanitizado
    status VARCHAR(20) NOT NULL,                  -- 'success', 'failed', 'ignored', 'pending'
    failure_reason TEXT NULL,
    attempts INT NOT NULL DEFAULT 1,
    last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (integration_id) REFERENCES integrations(id) ON DELETE SET NULL
);

-- 5. Configurações Globais do Sistema
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 6. Histórico de Auditoria do Master (Quem alterou o quê)
CREATE TABLE IF NOT EXISTS master_audit_logs (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    admin_user_id VARCHAR(36) NOT NULL,
    action VARCHAR(50) NOT NULL,                  -- 'create_client', 'suspend_client', 'add_access', 'reprocess_event', etc.
    target_type VARCHAR(50) NOT NULL,             -- 'client', 'integration', 'event', 'product'
    target_id VARCHAR(191) NOT NULL,
    details TEXT NULL,                            -- JSON
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_user_id) REFERENCES users(id)
);
