-- Run manually when birthday_message_steps already exists but campaign names are empty/wrong.
-- Sets the three AiSensy LIVE campaigns used by the admin Birthday Messages pipeline.
-- Safe to re-run (idempotent UPDATEs + INSERT … WHERE NOT EXISTS).

-- 1) Reminder 7 days before
UPDATE birthday_message_steps
SET
  campaign_name = 'happy_birthday_reminder_plant_tree',
  enabled = 1,
  sort_order = 10,
  updated_at = NOW()
WHERE days_before = 7 AND kind = 'marketing';

INSERT INTO birthday_message_steps (days_before, kind, enabled, campaign_name, image_path, sort_order, created_at, updated_at)
SELECT 7, 'marketing', 1, 'happy_birthday_reminder_plant_tree', NULL, 10, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM birthday_message_steps WHERE days_before = 7 AND kind = 'marketing'
);

-- 2) Reminder 3 days before (same campaign; code sends {{2}} = "3")
UPDATE birthday_message_steps
SET
  campaign_name = 'happy_birthday_reminder_plant_tree',
  enabled = 1,
  sort_order = 20,
  updated_at = NOW()
WHERE days_before = 3 AND kind = 'marketing';

INSERT INTO birthday_message_steps (days_before, kind, enabled, campaign_name, image_path, sort_order, created_at, updated_at)
SELECT 3, 'marketing', 1, 'happy_birthday_reminder_plant_tree', NULL, 20, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM birthday_message_steps WHERE days_before = 3 AND kind = 'marketing'
);

-- 3) Birthday day — not donated (compulsory branch)
UPDATE birthday_message_steps
SET
  campaign_name = 'birthday_marketing_on_birthday',
  enabled = 1,
  sort_order = 30,
  updated_at = NOW()
WHERE days_before = 0 AND kind = 'marketing';

INSERT INTO birthday_message_steps (days_before, kind, enabled, campaign_name, image_path, sort_order, created_at, updated_at)
SELECT 0, 'marketing', 1, 'birthday_marketing_on_birthday', NULL, 30, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM birthday_message_steps WHERE days_before = 0 AND kind = 'marketing'
);

-- 4) Birthday day — donated warm wish (compulsory branch)
UPDATE birthday_message_steps
SET
  campaign_name = 'happy_birthday_current_day_warm_msg',
  enabled = 1,
  sort_order = 40,
  updated_at = NOW()
WHERE days_before = 0 AND kind = 'warm_wish';

INSERT INTO birthday_message_steps (days_before, kind, enabled, campaign_name, image_path, sort_order, created_at, updated_at)
SELECT 0, 'warm_wish', 1, 'happy_birthday_current_day_warm_msg', NULL, 40, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM birthday_message_steps WHERE days_before = 0 AND kind = 'warm_wish'
);

-- Optional: turn the master switch on
-- UPDATE birthday_message_settings SET enabled = 1, updated_at = NOW() WHERE id = 1;
