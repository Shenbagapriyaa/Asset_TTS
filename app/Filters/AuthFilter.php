<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = service('session');
        if (!$session->get('user_id')) return redirect()->to('/')->with('error', 'Please sign in.');
        if ($session->get('two_factor_enabled') && !$session->get('two_factor_verified')) {
            return redirect()->to('/two-factor/verify');
        }
        $last = (int) ($session->get('last_activity') ?? time());
        if (time() - $last > 1800) {
            $session->destroy();
            return redirect()->to('/')->with('error', 'Session expired. Please sign in again.');
        }
        $session->set('last_activity', time());
        return null;
    }
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
