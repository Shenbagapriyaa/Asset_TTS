<?php

namespace Config;

use CodeIgniter\Database\Config;

class Database extends Config
{
    public string $filesPath = APPPATH . 'Database' . DIRECTORY_SEPARATOR;
    public string $defaultGroup = 'default';

    public array $default = [
        'DSN' => '',
        'hostname' => '127.0.0.1',
        'username' => 'root',
        'password' => '',
        'database' => 'asset_central',
        'DBDriver' => 'MySQLi',
        'DBPrefix' => '',
        'pConnect' => false,
        'DBDebug' => true,
        'charset' => 'utf8mb4',
        'DBCollat' => 'utf8mb4_general_ci',
        'swapPre' => '',
        'encrypt' => false,
        'compress' => false,
        'strictOn' => true,
        'failover' => [],
        'port' => 3306,
        'foreignKeys' => true,
        'busyTimeout' => 1000,
        'dateFormat' => ['date' => 'Y-m-d', 'datetime' => 'Y-m-d H:i:s', 'time' => 'H:i:s'],
    ];

    public function __construct()
    {
        parent::__construct();

        $this->default['hostname'] = env('CENTRAL_DB_HOST', $this->default['hostname']);
        $this->default['username'] = env('CENTRAL_DB_USER', $this->default['username']);
        $this->default['password'] = env('CENTRAL_DB_PASS', $this->default['password']);
        $this->default['database'] = env('CENTRAL_DB_NAME', $this->default['database']);
        $this->default['port'] = (int) env('CENTRAL_DB_PORT', $this->default['port']);
    }
}
