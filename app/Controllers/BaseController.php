<?php

namespace App\Controllers;

use CodeIgniter\Controller;

abstract class BaseController extends Controller
{
    protected $helpers = ['url', 'form'];
    protected function tenantDb()
    {
        return service('tenantContext')->db();
    }
    protected function canMutate(): bool
    {
        return in_array(service('session')->get('role'), ['admin', 'manager'], true);
    }
    protected function requireMutationPermission()
    {
        if (!$this->canMutate()) return redirect()->to('/assets')->with('error', 'You do not have permission to change assets.');
        return null;
    }
}
