-- Run manually on the server (do not apply via direct DB UI edits without review).
-- Creates per-marketer monthly collection target + ad spend.
-- Note: `year_month` is a MySQL reserved word — keep the backticks.

CREATE TABLE IF NOT EXISTS marketer_monthly_budgets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  `year_month` CHAR(7) NOT NULL,
  target_amount INT UNSIGNED NULL,
  spend_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY marketer_monthly_budgets_user_month_unique (user_id, `year_month`),
  CONSTRAINT marketer_monthly_budgets_user_id_foreign
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
