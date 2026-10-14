# Setup and testing notes

## Required PHP extensions
Run `php -m` and ensure `intl`, `mbstring`, `mysqli`, `openssl`, `json`, `xml` and `curl` are enabled.

## Central database
Create `asset_central` in phpMyAdmin. Configure `.env` with the central and tenant DB values. The database user used for local development needs `CREATE DATABASE`, `CREATE`, `ALTER`, `INDEX`, `INSERT`, `UPDATE`, `DELETE`, and `SELECT` permissions.

The application reads central connection values from `CENTRAL_DB_HOST`, `CENTRAL_DB_NAME`, `CENTRAL_DB_USER`, `CENTRAL_DB_PASS`, and `CENTRAL_DB_PORT`. Tenant database creation and connections use the corresponding `TENANT_DB_*` values.

## Password reset email
Set the `email.*` SMTP values in `.env` (`email.protocol`, `email.fromEmail`, `email.fromName`, `email.SMTPHost`, `email.SMTPUser`, `email.SMTPPass`, `email.SMTPPort`, and `email.SMTPCrypto`). SMTP credentials belong only in the local `.env`, never in source control. Reset links are single-use and expire after one hour.

## Create first super admin
After migrations, run `php spark superadmin:create` from the project directory. Enter the Super Admin name, email, and a unique password of at least 12 characters when prompted. The CLI password prompt may echo input, so run it in a private terminal. Never use a demo password in production.

## Manual acceptance testing
1. Create tenant `ACME`; verify `asset_tenant_acme` exists and has master tables.
2. Create tenant `BETA`; create an asset in each with Asset ID `M-001`; both must succeed.
3. Add a second `M-001` to ACME; server should reject it.
4. Log into ACME and attempt to access a record ID belonging only to BETA; it should return 404.
5. Disable BETA from super admin; BETA login must be blocked.
6. Set up TOTP with a real authenticator; invalid code must not enable 2FA.
7. Use a recovery code once; verify the same code fails the second time.
8. Create a viewer; verify Create/Edit/Delete buttons are hidden and direct POSTs are denied.
9. Seed 1,000+ assets and test all page sizes, filters, date range, sort, search and bulk delete.
10. Configure SMTP; request a password-reset link, verify it expires after one hour, and confirm the link cannot be reused.

## Phase branches
Suggested branch names: `phase-1-asset-crud`, `phase-2-auth-totp`, `phase-3-multitenancy`.
