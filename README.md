# CI4 Asset Management — multi-tenant, separate database per tenant

This project is structured for the three phases in the manager's assignment:
1. Asset CRUD, database-backed masters, server-side table query, filters, bulk soft-delete, 1,000+ seed assets.
2. CodeIgniter Shield authentication, roles, TOTP setup/login verification, recovery codes and audit log.
3. Central tenant registry; one MySQL database per company; tenant code at login; tenant database creation and migrations.

## Important status note
This repository is the application source package. The CodeIgniter framework and Composer dependencies are installed locally with `composer install`; `vendor/` is intentionally not included. The environment in which this archive was generated had no outbound network access, so the complete app has **not** been executed end-to-end here. Run the setup and acceptance checklist below before presenting it as finished. No screenshot assets were supplied, so the UI is implemented from the written field/table specification rather than pixel-matched screenshots.

## Stack / tenancy decision
- CodeIgniter 4 + MySQL/MariaDB.
- Tenant identification: **company code on login**. This works locally without wildcard DNS/subdomains and is suitable for a demo.
- Central database `asset_central`: tenant registry and super-admin accounts.
- Each tenant database: tenant users, roles, 2FA/recovery data, master tables and assets.
- The company code is resolved from the central registry before connecting to tenant data. Unknown/inactive tenants are rejected.
- All asset routes require authentication; role checks are performed server-side as well as in views.

## Setup (Windows / XAMPP)
1. Install PHP 8.1+ with `intl`, `mbstring`, `mysqli`, `openssl`, `json`, `xml`, `curl`; install Composer.
2. Create a MySQL database named `asset_central`.
3. Copy `.env.example` to `.env` and fill central/tenant DB credentials. Generate an encryption key with `php spark key:generate` after dependencies are installed.
4. Run `composer install`.
5. Run central migrations: `php spark migrate --all` (or use the SQL in `database/central_schema.sql` if migration discovery differs in your CI4 version).
6. Create a super admin using the documented bootstrap SQL / command. See `docs/SETUP_AND_TESTING.md`.
7. Run `php spark serve`.
8. Open `http://localhost:8080`.

## Tenant bootstrap
The Super Admin screen creates a tenant and its database (`asset_tenant_<companycode>`), runs the tenant schema setup and seeds master data. Use alphanumeric company codes only. The DB account must have `CREATE DATABASE` privileges in local development. In production, prefer a provisioned database service/account rather than giving the web app broad DB privileges.

## Main routes
- `/` — tenant-code login.
- `/assets` — asset list with server-side pagination/search/sort/filters.
- `/assets/new`, `/assets/{id}/edit`, `/assets/{id}/delete`, `/assets/bulk-delete`
- `/two-factor/setup`, `/two-factor/confirm`, `/two-factor/verify`, `/two-factor/recovery`
- `/admin/users`
- `/super-admin/tenants` — central tenant management.

## Acceptance checklist
- [ ] Fresh install and migrations succeed on the target PHP/MySQL version.
- [ ] Create at least 3 tenants and confirm each gets its own database.
- [ ] Create the same Asset ID in two tenants; it should be allowed across tenants and rejected within one tenant.
- [ ] Verify tenant A cannot access tenant B records by changing URL IDs or query filters.
- [ ] Verify inactive/unknown tenant codes cannot authenticate.
- [ ] Verify TOTP with Google Authenticator or Microsoft Authenticator, and one-time recovery codes.
- [ ] Verify viewer cannot mutate data by direct POST, manager cannot access admin functions, admin can reset 2FA.
- [ ] Seed at least 1,000 assets and test server-side pagination/search.
- [ ] Configure email delivery before testing password-reset email in a real environment.
- [ ] Run `vendor/bin/phpunit` and manually demo every acceptance criterion.

## Known implementation notes
- The supplied brief's screenshots were not included in the message attachment, so visual matching is based on the text specification.
- Email password reset requires working SMTP configuration in `.env`.
- Review the framework/Shield versions selected by Composer and test the TOTP flow on the actual machine before deployment.
