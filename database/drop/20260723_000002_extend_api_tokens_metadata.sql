ALTER TABLE api_tokens
    DROP COLUMN updated_at,
    DROP COLUMN app_version,
    DROP COLUMN platform,
    DROP COLUMN device_uuid,
    CHANGE COLUMN device_name name VARCHAR(100) NOT NULL DEFAULT 'api-client';
