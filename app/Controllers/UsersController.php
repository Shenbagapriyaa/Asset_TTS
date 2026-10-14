<?php

namespace App\Controllers;

class UsersController extends BaseController
{
    public function index()
    {
        return view('admin/users',['users'=>$this->tenantDb()->table('users')->orderBy('name')->get()->getResultArray()]);
    }
    public function create()
    {
        $rules=['name'=>'required|max_length[120]','email'=>'required|valid_email','password'=>'required|min_length[8]','role'=>'required|in_list[admin,manager,viewer]'];
        if(!$this->validate($rules)) return redirect()->back()->withInput()->with('errors',$this->validator->getErrors());
        $this->tenantDb()->table('users')->insert([
            'name'=>$this->request->getPost('name'),'email'=>strtolower($this->request->getPost('email')),
            'password_hash'=>password_hash((string)$this->request->getPost('password'),PASSWORD_DEFAULT),
            'role'=>$this->request->getPost('role'),'active'=>1,'totp_enabled'=>0,'created_at'=>date('Y-m-d H:i:s')
        ]);
        return redirect()->to('/admin/users')->with('message','User created. They must configure 2FA at first login.');
    }
    public function toggle($id)
    {
        $db=$this->tenantDb();$u=$db->table('users')->where('id',(int)$id)->get()->getRowArray();
        if(!$u)return $this->response->setStatusCode(404);
        $db->table('users')->where('id',(int)$id)->update(['active'=>$u['active']?0:1]);
        return redirect()->to('/admin/users')->with('message','User status updated.');
    }
    public function resetTwoFactor($id)
    {
        $db=$this->tenantDb();
        $db->table('users')->where('id',(int)$id)->update(['totp_enabled'=>0,'totp_secret'=>null]);
        $db->table('recovery_codes')->where('user_id',(int)$id)->delete();
        return redirect()->to('/admin/users')->with('message','2FA reset. User must set it up again.');
    }
}
