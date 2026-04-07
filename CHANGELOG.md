# AitiCore Flex Changelog

## Unreleased

## v0.3.0 - 2026-04-01
- Added upgrade orchestration scaffolding with `php aiti upgrade:check` (read-only) and `php aiti upgrade:apply` (dry-run default).
- Added versioned upgrade catalog and guide structure under `upgrade-guides/`.
- Added strict framework governance rules in `AGENT.md` for SemVer, ownership boundaries, backups, and safe patching.
- Added dependency injection container capabilities (`bind`, `singleton`, `instance`, auto constructor resolution) and callable resolver for controller/route invocation.
- Added `405 Method Not Allowed` responses with `Allow` header support.
- Added `php aiti route:cache` and bootstrap route cache loading from `storage/cache/routes.php`.
- Expanded migrate command with migration history table and new actions: `status` and `rollback --step=N`.
- Synced CLI version output to read from `VERSION`.
- Added development-only runtime error popup injection in `public/index.php` while keeping production responses generic.

## v0.2.0 - 2026-04-01
- Added built-in `router.php` so `php aiti serve` always routes dynamic requests consistently and lets static files bypass the app.
- Added custom 404 view handling with fallback to plain text when no error view exists.
- Added automatic `HEAD` to `GET` route matching while keeping `HEAD` responses bodyless.
- Added `php aiti migrate update|drop` SQL migration runner backed by PDO and `database/update` / `database/drop`.

## v0.1.0 - 2026-02-20
- Initial framework skeleton with CI-ish app structure.
- Added bootstrap lifecycle (`public/index.php` -> kernel -> response).
- Added `.env` loader and config reader.
- Added router, route collection, and middleware pipeline.
- Added view renderer with escaped output by default.
- Added CSRF token generation + verification for web middleware group.
- Added single CLI entrypoint `php aiti` and core commands:
  - `--version`
  - `list`
  - `serve` (`server` alias)
  - `route:list`
  - `key:generate`
  - `preset:bootstrap`
- Added local bootstrap preset copier from `node_modules` to `public/assets/vendor`.
- Added initial feature tests (router, escaping, csrf).
