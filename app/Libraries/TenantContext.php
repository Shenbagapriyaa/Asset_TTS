<?php

namespace App\Libraries;

use RuntimeException;

class TenantContext
{
    private ?array $tenant = null;
    private $connection = null;

    public function resolveByCode(string $code): ?array
    {
        $central = db_connect();
        return $central->table('tenants')
            ->where('company_code', strtoupper(trim($code)))
            ->where('status', 'active')
            ->get()->getRowArray() ?: null;
    }

    public function setTenant(array $tenant): void
    {
        $this->tenant = $tenant;
        $dbConfig = [
            'DSN' => '',
            'hostname' => $tenant['db_host'] ?: env('TENANT_DB_HOST', '127.0.0.1'),
            'username' => env('TENANT_DB_USER', 'root'),
            'password' => env('TENANT_DB_PASS', ''),
            'database' => $tenant['db_name'],
            'DBDriver' => 'MySQLi',
            'DBPrefix' => '',
            'pConnect' => false,
            'DBDebug' => (ENVIRONMENT !== 'production'),
            'charset' => 'utf8mb4',
            'DBCollat' => 'utf8mb4_general_ci',
            'port' => (int) env('TENANT_DB_PORT', 3306),
            'foreignKeys' => true,
            'strictOn' => true,
        ];
        $this->connection = \Config\Database::connect($dbConfig, false);
        service('session')->set('tenant_id', (int) $tenant['id']);
        service('session')->set('tenant_code', $tenant['company_code']);
        service('session')->set('tenant_db', $tenant['db_name']);
    }

    public function current(): ?array { return $this->tenant; }

    public function db()
    {
        if ($this->connection) return $this->connection;
        $session = service('session');
        $dbName = $session->get('tenant_db');
        if (!$dbName) throw new RuntimeException('Tenant context has not been selected.');

        $tenantId = (int) $session->get('tenant_id');
        $tenantCode = (string) $session->get('tenant_code');
        if ($tenantId > 0 && $tenantCode !== '') {
            $tenant = [
                'id' => $tenantId,
                'company_code' => $tenantCode,
                'db_name' => $dbName,
                'db_host' => env('TENANT_DB_HOST', '127.0.0.1'),
            ];
        } else {
            $tenant = db_connect()->table('tenants')
                ->where('db_name', $dbName)
                ->where('status', 'active')
                ->get()->getRowArray();
            if (!$tenant) {
                $session->remove(['tenant_id', 'tenant_code', 'tenant_db']);
                throw new RuntimeException('Tenant session is invalid. Please sign in again.');
            }
        }

        $this->setTenant($tenant);
        return $this->connection;
    }
}
