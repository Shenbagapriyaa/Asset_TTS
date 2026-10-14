<?php

namespace App\Controllers;

use App\Libraries\TenantContext;

class AuthController extends BaseController
{
    public function login()
    {
        return view('auth/login', ['title' => 'Sign in']);
    }

    public function forgotPassword()
    {
        return view('auth/forgot_password', ['title' => 'Reset password']);
    }

    public function requestPasswordReset()
    {
        $rules = [
            'company_code' => 'required|alpha_numeric|min_length[2]|max_length[24]',
            'email' => 'required|valid_email',
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $companyCode = strtoupper(trim((string) $this->request->getPost('company_code')));
        $emailAddress = strtolower(trim((string) $this->request->getPost('email')));
        $tenantContext = service('tenantContext');
        $tenant = $tenantContext->resolveByCode($companyCode);

        if ($tenant) {
            $tenantContext->setTenant($tenant);
            $db = $tenantContext->db();
            $user = $db->table('users')->where('email', $emailAddress)->where('active', 1)->get()->getRowArray();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $db->table('password_resets')->where('user_id', $user['id'])->delete();
                $db->table('password_resets')->insert([
                    'user_id' => $user['id'],
                    'token_hash' => hash('sha256', $token),
                    'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                $resetUrl = site_url('reset-password') . '?' . http_build_query([
                    'company_code' => $companyCode,
                    'token' => $token,
                ]);
                $mailer = service('email');
                $mailer->setTo($emailAddress);
                $mailer->setSubject('Reset your Asset Management password');
                $mailer->setMessage("Use this link within one hour to reset your password:\n\n{$resetUrl}\n\nIf you did not request this, you can ignore this email.");

                if (!$mailer->send()) {
                    log_message('error', 'Password reset email could not be sent for tenant {code}. {errors}', [
                        'code' => $companyCode,
                        'errors' => json_encode($mailer->printDebugger(['headers'])),
                    ]);
                }
            }
        }

        return redirect()->to('/forgot-password')->with('message', 'If an active account matches those details, a reset link will be sent.');
    }

    public function resetPassword()
    {
        $companyCode = strtoupper(trim((string) $this->request->getGet('company_code')));
        $token = (string) $this->request->getGet('token');
        $reset = $this->findPasswordReset($companyCode, $token);

        if (!$reset) {
            return view('auth/reset_password', ['title' => 'Reset password', 'invalid' => true]);
        }

        return view('auth/reset_password', [
            'title' => 'Reset password',
            'invalid' => false,
            'companyCode' => $companyCode,
            'token' => $token,
        ]);
    }

    public function updatePassword()
    {
        $rules = [
            'company_code' => 'required|alpha_numeric|min_length[2]|max_length[24]',
            'token' => 'required|exact_length[64]|alpha_numeric',
            'password' => 'required|min_length[12]|max_length[128]',
            'password_confirm' => 'required|matches[password]',
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $companyCode = strtoupper(trim((string) $this->request->getPost('company_code')));
        $token = (string) $this->request->getPost('token');
        $reset = $this->findPasswordReset($companyCode, $token);

        if (!$reset) {
            return view('auth/reset_password', ['title' => 'Reset password', 'invalid' => true]);
        }

        $db = $reset['db'];
        $db->transBegin();
        $lockedReset = $db->query(
            'SELECT password_resets.user_id FROM password_resets INNER JOIN users ON users.id = password_resets.user_id WHERE password_resets.token_hash = ? AND password_resets.expires_at > ? AND users.active = 1 FOR UPDATE',
            [hash('sha256', $token), date('Y-m-d H:i:s')]
        )->getRowArray();

        if (!$lockedReset) {
            $db->transRollback();
            return view('auth/reset_password', ['title' => 'Reset password', 'invalid' => true]);
        }

        $userId = (int) $lockedReset['user_id'];
        $db->table('users')->where('id', $userId)->update([
            'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
        ]);
        $db->table('password_resets')->where('user_id', $userId)->delete();
        $db->table('audit_logs')->insert([
            'user_id' => $userId,
            'event' => 'password_reset',
            'ip_address' => $this->request->getIPAddress(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$db->transStatus()) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', 'Password could not be updated. Please request a new reset link.');
        }

        $db->transCommit();
        return redirect()->to('/')->with('message', 'Password updated. Sign in with your new password.');
    }

    private function findPasswordReset(string $companyCode, string $token): ?array
    {
        if (!preg_match('/^[A-Z0-9]{2,24}$/', $companyCode) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $tenantContext = service('tenantContext');
        $tenant = $tenantContext->resolveByCode($companyCode);
        if (!$tenant) {
            return null;
        }

        $tenantContext->setTenant($tenant);
        $db = $tenantContext->db();
        $reset = $db->table('password_resets')
            ->select('password_resets.user_id')
            ->join('users', 'users.id = password_resets.user_id')
            ->where('password_resets.token_hash', hash('sha256', $token))
            ->where('password_resets.expires_at >', date('Y-m-d H:i:s'))
            ->where('users.active', 1)
            ->get()->getRowArray();

        return $reset ? ['db' => $db, 'user_id' => (int) $reset['user_id']] : null;
    }

    public function attemptLogin()
    {
        $rules = [
            'company_code' => 'required|alpha_numeric|min_length[2]|max_length[32]',
            'email' => 'required|valid_email',
            'password' => 'required|min_length[8]',
        ];
        if (!$this->validate($rules)) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

        try {
            $ctx = service('tenantContext');
            $tenant = $ctx->resolveByCode((string) $this->request->getPost('company_code'));
            if (!$tenant) return redirect()->back()->withInput()->with('error', 'Unknown or inactive company code.');
            $ctx->setTenant($tenant);
            $db = $ctx->db();
            $email = strtolower(trim((string) $this->request->getPost('email')));
            $user = $db->table('users')->where('email', $email)->where('active', 1)->get()->getRowArray();
            $key = 'login_fail_' . sha1($tenant['company_code'] . $email);
            $session = service('session');
            if ((int) $session->get($key) >= 5) return redirect()->back()->with('error', 'Too many attempts. Wait 15 minutes and try again.');
            if (!$user || !password_verify((string) $this->request->getPost('password'), $user['password_hash'])) {
                $session->set($key, (int) $session->get($key) + 1);
                $db->table('audit_logs')->insert(['user_id' => $user['id'] ?? null, 'event' => 'login_failed', 'ip_address' => $this->request->getIPAddress(), 'created_at' => date('Y-m-d H:i:s')]);
                return redirect()->back()->withInput()->with('error', 'Invalid credentials.');
            }
            $session->remove($key);
            $session->regenerate(true);
            $session->set([
                'user_id' => (int) $user['id'], 'email' => $user['email'],
                'name' => $user['name'], 'role' => $user['role'],
                'two_factor_enabled' => (bool) $user['totp_enabled'],
                'two_factor_verified' => !(bool) $user['totp_enabled'],
                'last_activity' => time(),
            ]);
            $db->table('audit_logs')->insert(['user_id' => $user['id'], 'event' => 'login_password_ok', 'ip_address' => $this->request->getIPAddress(), 'created_at' => date('Y-m-d H:i:s')]);
            if (!$user['totp_enabled']) return redirect()->to('/two-factor/setup');
            return redirect()->to('/two-factor/verify');
        } catch (\Throwable $exception) {
            log_message('error', 'Login failed due to a database or service error: {message}', ['message' => $exception->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'Sign-in is temporarily unavailable. Check the database configuration and try again.');
        }
    }

    public function logout()
    {
        $session = service('session');
        if ($session->get('user_id') && $session->get('tenant_db')) {
            try { service('tenantContext')->db()->table('audit_logs')->insert(['user_id' => $session->get('user_id'), 'event' => 'logout', 'ip_address' => $this->request->getIPAddress(), 'created_at' => date('Y-m-d H:i:s')]); } catch (\Throwable $e) {}
        }
        $session->destroy();
        return redirect()->to('/')->with('message', 'You have signed out.');
    }

    public function tenantUnavailable()
    {
        return view('errors/tenant_unavailable');
    }
}
