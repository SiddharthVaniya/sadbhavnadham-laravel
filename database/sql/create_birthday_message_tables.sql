-- Run manually on the server when ready (do not auto-apply from the agent).
-- Birthday marketing + warm wish: settings, steps, send log.

CREATE TABLE IF NOT EXISTS birthday_message_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  aisensy_account_id VARCHAR(64) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS birthday_message_steps (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  days_before SMALLINT UNSIGNED NOT NULL,
  kind VARCHAR(32) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  campaign_name VARCHAR(255) NULL,
  image_path VARCHAR(255) NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY birthday_message_steps_days_before_kind_unique (days_before, kind)
);

CREATE TABLE IF NOT EXISTS birthday_message_sends (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  donor_id BIGINT UNSIGNED NOT NULL,
  year SMALLINT UNSIGNED NOT NULL,
  birthday_message_step_id BIGINT UNSIGNED NULL,
  days_before SMALLINT UNSIGNED NOT NULL,
  kind VARCHAR(32) NOT NULL,
  campaign_name VARCHAR(255) NULL,
  sent_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY birthday_sends_donor_year_offset_kind_unique (donor_id, year, days_before, kind),
  KEY birthday_message_sends_year_offset_kind_index (year, days_before, kind),
  CONSTRAINT birthday_message_sends_donor_id_foreign
    FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE CASCADE,
  CONSTRAINT birthday_message_sends_step_id_foreign
    FOREIGN KEY (birthday_message_step_id) REFERENCES birthday_message_steps(id) ON DELETE SET NULL
);

-- Seed defaults (skip if settings row already exists).
INSERT INTO birthday_message_settings (enabled, aisensy_account_id, created_at, updated_at)
SELECT
  COALESCE((SELECT CAST(`value` AS UNSIGNED) FROM settings WHERE `key` = 'send_birthday_whatsapp' LIMIT 1), 0),
  NULLIF((SELECT `value` FROM settings WHERE `key` = 'aisensy_birthday_account_id' LIMIT 1), ''),
  NOW(),
  NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM birthday_message_settings LIMIT 1);

-- Reminder: 7 days before (AiSensy campaign happy_birthday_reminder_plant_tree)
INSERT INTO birthday_message_steps (days_before, kind, enabled, campaign_name, image_path, sort_order, created_at, updated_at)
SELECT 7, 'marketing', 1, 'happy_birthday_reminder_plant_tree', NULL, 10, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM birthday_message_steps WHERE days_before = 7 AND kind = 'marketing');

-- Reminder: 3 days before (same reminder template; {{2}} = days left)
INSERT INTO birthday_message_steps (days_before, kind, enabled, campaign_name, image_path, sort_order, created_at, updated_at)
SELECT 3, 'marketing', 1, 'happy_birthday_reminder_plant_tree', NULL, 20, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM birthday_message_steps WHERE days_before = 3 AND kind = 'marketing');

-- Birthday day: not donated → marketing
INSERT INTO birthday_message_steps (days_before, kind, enabled, campaign_name, image_path, sort_order, created_at, updated_at)
SELECT 0, 'marketing', 1, 'birthday_marketing_on_birthday', NULL, 30, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM birthday_message_steps WHERE days_before = 0 AND kind = 'marketing');

-- Birthday day: donated → warm wish
INSERT INTO birthday_message_steps (days_before, kind, enabled, campaign_name, image_path, sort_order, created_at, updated_at)
SELECT 0, 'warm_wish', 1, 'happy_birthday_current_day_warm_msg', NULL, 40, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM birthday_message_steps WHERE days_before = 0 AND kind = 'warm_wish');
