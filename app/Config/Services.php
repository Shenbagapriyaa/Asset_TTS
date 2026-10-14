<?php

namespace Config;

use App\Libraries\TenantContext;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function tenantContext(bool $getShared = true): TenantContext
    {
        if ($getShared) {
            return static::getSharedInstance('tenantContext');
        }
        return new TenantContext();
    }
}
