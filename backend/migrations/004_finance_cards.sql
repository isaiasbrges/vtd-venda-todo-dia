-- Additive card accounting. Run through Migrator in an isolated environment first.
CREATE TABLE IF NOT EXISTS financial_credit_cards (
 id VARCHAR(36) PRIMARY KEY, tenant_id VARCHAR(36) NOT NULL,
 bank_id VARCHAR(100) NOT NULL, bank_name VARCHAR(255) NOT NULL,
 nickname VARCHAR(80) NOT NULL, brand VARCHAR(30) NOT NULL, last_four VARCHAR(4) NOT NULL DEFAULT '',
 limit_cents BIGINT NOT NULL, closing_day INT NOT NULL, due_day INT NOT NULL,
 is_active INT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (tenant_id) REFERENCES tenants(id), CHECK (limit_cents >= 0),
 CHECK (closing_day BETWEEN 1 AND 31), CHECK (due_day BETWEEN 1 AND 31)
);
CREATE INDEX idx_cards_tenant ON financial_credit_cards(tenant_id, is_active);
CREATE TABLE IF NOT EXISTS financial_card_purchases (
 id VARCHAR(36) PRIMARY KEY, tenant_id VARCHAR(36) NOT NULL, card_id VARCHAR(36) NOT NULL,
 description VARCHAR(190) NOT NULL, purchase_date DATE NOT NULL, value_cents BIGINT NOT NULL,
 category_id INT NOT NULL, installments INT NOT NULL, operation_key VARCHAR(100) NOT NULL,
 request_hash VARCHAR(64) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE (tenant_id, operation_key), FOREIGN KEY (card_id) REFERENCES financial_credit_cards(id),
 FOREIGN KEY (tenant_id) REFERENCES tenants(id), FOREIGN KEY (category_id) REFERENCES financial_categories(id),
 CHECK (value_cents > 0), CHECK (installments BETWEEN 1 AND 120)
);
CREATE TABLE IF NOT EXISTS financial_card_invoices (
 id VARCHAR(36) PRIMARY KEY, tenant_id VARCHAR(36) NOT NULL, card_id VARCHAR(36) NOT NULL,
 cycle_month VARCHAR(7) NOT NULL, closing_date DATE NOT NULL, due_date DATE NOT NULL,
 is_settled INT NOT NULL DEFAULT 0,
 UNIQUE (card_id, cycle_month), FOREIGN KEY (card_id) REFERENCES financial_credit_cards(id),
 FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
CREATE INDEX idx_card_invoices_tenant ON financial_card_invoices(tenant_id, due_date);
CREATE TABLE IF NOT EXISTS financial_card_installments (
 id VARCHAR(36) PRIMARY KEY, tenant_id VARCHAR(36) NOT NULL, purchase_id VARCHAR(36) NOT NULL,
 invoice_id VARCHAR(36) NOT NULL, transaction_id VARCHAR(36) NOT NULL UNIQUE, installment_number INT NOT NULL,
 UNIQUE (purchase_id, installment_number), FOREIGN KEY (purchase_id) REFERENCES financial_card_purchases(id),
 FOREIGN KEY (invoice_id) REFERENCES financial_card_invoices(id), FOREIGN KEY (transaction_id) REFERENCES financial_transactions(id),
 FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
CREATE TABLE IF NOT EXISTS financial_card_invoice_payments (
 id VARCHAR(36) PRIMARY KEY, tenant_id VARCHAR(36) NOT NULL, invoice_id VARCHAR(36) NOT NULL UNIQUE,
 value_cents BIGINT NOT NULL, effective_date DATE NOT NULL, operation_key VARCHAR(100) NOT NULL,
 UNIQUE (tenant_id, operation_key), FOREIGN KEY (invoice_id) REFERENCES financial_card_invoices(id),
 FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
CREATE TABLE IF NOT EXISTS financial_transaction_methods (
 transaction_id VARCHAR(36) PRIMARY KEY, tenant_id VARCHAR(36) NOT NULL, method VARCHAR(20) NOT NULL,
 FOREIGN KEY (transaction_id) REFERENCES financial_transactions(id) ON DELETE CASCADE,
 FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
