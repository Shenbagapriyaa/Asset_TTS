<?php

namespace App\Controllers;

use RobThree\Auth\Providers\Qr\QRServerProvider;
use RobThree\Auth\TwoFactorAuth;

class TwoFactorController extends BaseController
{
    private function authenticator(): TwoFactorAuth
    {
        return new TwoFactorAuth(new QRServerProvider(), 'AssetManager');
    }

    private function secret(): string
    {
        $existing = service('session')->get('pending_totp_secret');
        if (is_string($existing) && preg_match('/^[A-Z2-7]{32}$/', $existing)) return $existing;
        service('session')->remove('pending_totp_secret');
        $secret = $this->authenticator()->createSecret();
        service('session')->set('pending_totp_secret',$secret);
        return $secret;
    }

    public function setup()
    {
        if (!service('session')->get('user_id')) return redirect()->to('/');
        $secret = $this->secret();
        $email = (string)service('session')->get('email');
        $otpauth = 'otpauth://totp/AssetManager:' . rawurlencode($email) . '?secret=' . $secret . '&issuer=AssetManager&digits=6&period=30';
        return view('auth/totp_setup',['secret'=>$secret,'otpauth'=>$otpauth]);
    }

    public function confirm()
    {
        $code = preg_replace('/\D/','',(string)$this->request->getPost('code'));
        $secret = (string)service('session')->get('pending_totp_secret');
        if (!$secret || strlen($code)!==6 || !$this->validTotp($secret,$code)) {
            return redirect()->back()->with('error','The code is invalid. Two-factor authentication was not enabled.');
        }
        $db=$this->tenantDb();
        $db->table('users')->where('id',service('session')->get('user_id'))->update(['totp_secret'=>$secret,'totp_enabled'=>1]);
        $codes=[];
        for($i=0;$i<8;$i++) $codes[]=strtoupper(bin2hex(random_bytes(5)));
        foreach($codes as $c) $db->table('recovery_codes')->insert(['user_id'=>service('session')->get('user_id'),'code_hash'=>password_hash($c,PASSWORD_DEFAULT),'used_at'=>null]);
        service('session')->remove('pending_totp_secret');
        service('session')->set(['two_factor_enabled'=>true,'two_factor_verified'=>true]);
        return view('auth/recovery_codes',['codes'=>$codes]);
    }

    public function verify()
    {
        if (!service('session')->get('user_id')) return redirect()->to('/');
        return view('auth/totp_verify');
    }

    public function attemptVerify()
    {
        $code=preg_replace('/\D/','',(string)$this->request->getPost('code'));
        $db=$this->tenantDb();
        $user=$db->table('users')->where('id',service('session')->get('user_id'))->get()->getRowArray();
        $session=service('session'); $attempts=(int)$session->get('totp_failures');
        if ($attempts>=5) return redirect()->back()->with('error','Too many invalid codes. Sign in again later.');
        if (!$user || !$this->validTotp($user['totp_secret'] ?? '',$code)) {
            $session->set('totp_failures',$attempts+1);
            return redirect()->back()->with('error','Invalid authentication code.');
        }
        $session->set(['two_factor_verified'=>true,'totp_failures'=>0]);
        $db->table('audit_logs')->insert(['user_id'=>$user['id'],'event'=>'totp_verified','ip_address'=>$this->request->getIPAddress(),'created_at'=>date('Y-m-d H:i:s')]);
        return redirect()->to('/assets');
    }

    public function useRecovery()
    {
        $code=trim((string)$this->request->getPost('recovery_code'));
        $db=$this->tenantDb();
        $rows=$db->table('recovery_codes')->where('user_id',service('session')->get('user_id'))->where('used_at',null)->get()->getResultArray();
        foreach($rows as $row) if(password_verify($code,$row['code_hash'])) {
            $db->table('recovery_codes')->where('id',$row['id'])->update(['used_at'=>date('Y-m-d H:i:s')]);
            service('session')->set(['two_factor_verified'=>true,'totp_failures'=>0]);
            return redirect()->to('/assets')->with('message','Recovery code accepted. It cannot be reused.');
        }
        return redirect()->back()->with('error','Invalid or already used recovery code.');
    }

    private function validTotp(string $secret,string $code): bool
    {
        return strlen($code) === 6
            && ctype_digit($code)
            && preg_match('/^[A-Z2-7]{32}$/', $secret) === 1
            && $this->authenticator()->verifyCode($secret, $code, 1);
    }
}
