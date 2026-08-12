-- Migration: Add timestamp columns to tickets for lifecycle tracking
-- Adds: assigned_at, started_at, finished_at, closed_at

ALTER TABLE tickets ADD COLUMN IF NOT EXISTS assigned_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS started_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS finished_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS closed_at TIMESTAMP NULL DEFAULT NULL;

-- No further data migration required; existing rows will keep NULL where unknown
