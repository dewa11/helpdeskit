-- Migration: Add lifecycle timestamps to tickets
-- Adds assigned_at, started_at, finished_at, and closed_at to track ticket lifecycle milestones.

-- Add columns (idempotent on MySQL 8+)
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS assigned_at DATETIME NULL;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS started_at DATETIME NULL;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS finished_at DATETIME NULL;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS closed_at DATETIME NULL;

-- No backfill performed; historical rows remain NULL for these fields.

-- END
