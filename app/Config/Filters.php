<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;
use App\Filters\TenantFilter;
use App\Filters\AuthFilter;
use App\Filters\RoleFilter;
use App\Filters\SuperAdminFilter;

class Filters extends BaseConfig
{
    public array $required = [
        'before' => ['forcehttps', 'pagecache'],
        'after' => ['pagecache', 'performance'],
    ];

    public array $aliases = [
        'csrf' => CSRF::class,
        'toolbar' => DebugToolbar::class,
        'forcehttps' => ForceHTTPS::class,
        'pagecache' => PageCache::class,
        'performance' => PerformanceMetrics::class,
        'honeypot' => Honeypot::class,
        'invalidchars' => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'tenant' => TenantFilter::class,
        'auth' => AuthFilter::class,
        'role' => RoleFilter::class,
        'superadmin' => SuperAdminFilter::class,
    ];

    public array $globals = [
        'before' => ['csrf'],
            'after' => ['toolbar' => ['except' => ['/', 'login', 'forgot-password', 'reset-password']]],
    ];
    public array $methods = [];
    public array $filters = [];
}
