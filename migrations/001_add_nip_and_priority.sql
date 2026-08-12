-- Migration: Add `nip` to users and `priority` to tickets
-- Safe to run multiple times; it's idempotent where possible.

-- Add nip column to users if not exists (MySQL 8+ supports IF NOT EXISTS)
ALTER TABLE users ADD COLUMN IF NOT EXISTS nip VARCHAR(128) NULL;

-- Populate nip from email where empty
UPDATE users SET nip = email WHERE (nip IS NULL OR nip = '') AND (email IS NOT NULL AND email != '');

-- Create unique index on nip if not exists (works on MySQL 8+)
CREATE UNIQUE INDEX IF NOT EXISTS ux_users_nip ON users(nip);

-- Add priority column to tickets if not exists
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS priority VARCHAR(32) NULL;

-- Set default priority for existing rows to 'Normal' where NULL
UPDATE tickets SET priority = 'Normal' WHERE priority IS NULL;

-- END
