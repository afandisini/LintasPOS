ALTER TABLE api_tokens
    CHANGE COLUMN name device_name VARCHAR(100) NOT NULL DEFAULT 'api-client',
    ADD COLUMN device_uuid VARCHAR(191) NULL AFTER device_name,
    ADD COLUMN platform VARCHAR(30) NULL AFTER device_uuid,
    ADD COLUMN app_version VARCHAR(50) NULL AFTER platform,
    ADD COLUMN updated_at DATETIME NOT NULL AFTER created_at;
