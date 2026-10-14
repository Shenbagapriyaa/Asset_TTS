-- Run after creating asset_central and central_schema.sql.
INSERT INTO tenants(company_name,company_code,db_name,db_host,status,created_at)
VALUES ('Demo Company','DEMO','asset_tenant_demo','127.0.0.1','active',NOW())
ON DUPLICATE KEY UPDATE company_name=VALUES(company_name);
-- Create a super admin password hash using:
-- php -r "echo password_hash('ChangeMe123', PASSWORD_DEFAULT), PHP_EOL;"
-- INSERT INTO super_admins(name,email,password_hash,created_at)
-- VALUES ('Super Admin','superadmin@example.com','PASTE_HASH_HERE',NOW());
