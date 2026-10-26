-- VTD Financeiro V2 - aplicar UMA VEZ no MySQL de producao, apos backup.
-- data atual da transacao passa a significar VENCIMENTO/COMPETENCIA.
-- settled_at ja existe e representa a data efetiva da quitacao.
ALTER TABLE financial_transactions ADD COLUMN recurrence_rule_id VARCHAR(36) NULL;
CREATE INDEX idx_finance_tenant_date_status ON financial_transactions(tenant_id, date, status);
CREATE UNIQUE INDEX uq_finance_recurrence_occurrence ON financial_transactions(recurrence_rule_id, date);

CREATE TABLE IF NOT EXISTS financial_recurring_rules (
  id VARCHAR(36) NOT NULL PRIMARY KEY,
  tenant_id VARCHAR(36) NOT NULL,
  created_by_user_id VARCHAR(36) NOT NULL,
  description VARCHAR(190) NOT NULL,
  type ENUM('income','expense') NOT NULL,
  value_cents BIGINT NOT NULL,
  category_id INT NOT NULL,
  day_of_month TINYINT UNSIGNED NOT NULL,
  starts_on DATE NOT NULL,
  ends_on DATE NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_recurring_tenant_active (tenant_id, is_active),
  CONSTRAINT chk_recurring_value CHECK (value_cents > 0),
  CONSTRAINT chk_recurring_day CHECK (day_of_month BETWEEN 1 AND 31)
);
