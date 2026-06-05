SET @col_exists := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'toko'
    AND column_name = 'app_name'
);

SET @sql_add := IF(
  @col_exists = 0,
  "ALTER TABLE `toko` ADD COLUMN `app_name` varchar(255) NOT NULL DEFAULT 'LintasPos' AFTER `id`",
  'SELECT 1'
);

PREPARE stmt_add FROM @sql_add;
EXECUTE stmt_add;
DEALLOCATE PREPARE stmt_add;

UPDATE `toko`
SET `app_name` = COALESCE(NULLIF(TRIM(`app_name`), ''), 'LintasPos');
