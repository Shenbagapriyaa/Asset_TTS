<?php

namespace App\Filters;

use App\Libraries\TenantContext;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class TenantFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = service('session');
        $tenantId = $session->get('tenant_id');
        $tenantDb = $session->get('tenant_db');
        if ($tenantId && $tenantDb) return null;

        // Login is allowed to select a tenant; protected tenant pages require a selected tenant.
        if (str_ends_with(uri_string(), 'login') || uri_string() === '') return null;
        return redirect()->to('/')->with('error', 'Choose your company code and sign in first.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
