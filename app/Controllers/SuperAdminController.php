<?php

namespace App\Controllers;

class SuperAdminController extends BaseController
{
    public function login(){return view('superadmin/login');}
    public function attemptLogin()
    {
        $db=db_connect();
        $email=strtolower(trim((string)$this->request->getPost('email')));
        $u=$db->table('super_admins')->where('email',$email)->get()->getRowArray();
        if(!$u || !password_verify((string)$this->request->getPost('password'),$u['password_hash'])) return redirect()->back()->with('error','Invalid super-admin credentials.');
        service('session')->regenerate(true);service('session')->set('super_admin_id',(int)$u['id']);
        return redirect()->to('/super-admin/tenants');
    }
    public function index()
    {
        return view('superadmin/tenants',['tenants'=>db_connect()->table('tenants')->orderBy('created_at','DESC')->get()->getResultArray()]);
    }
    public function createTenant()
    {
        $rules=['company_name'=>'required|max_length[150]','company_code'=>'required|alpha_numeric|min_length[2]|max_length[24]','admin_name'=>'required|max_length[120]','admin_email'=>'required|valid_email','admin_password'=>'required|min_length[8]'];
        if(!$this->validate($rules))return redirect()->back()->withInput()->with('errors',$this->validator->getErrors());
        $code=strtoupper((string)$this->request->getPost('company_code'));
        $dbName='asset_tenant_'.strtolower($code);
        $central=db_connect();
        $tenant=$central->table('tenants')->where('company_code',$code)->get()->getRowArray();
        if($tenant && $tenant['db_name']!==$dbName)return redirect()->back()->with('error','Company code is already linked to a different database.');
        $tenantCfg=['hostname'=>env('TENANT_DB_HOST','127.0.0.1'),'username'=>env('TENANT_DB_USER','root'),'password'=>env('TENANT_DB_PASS',''),'database'=>'','DBDriver'=>'MySQLi','charset'=>'utf8mb4','DBCollat'=>'utf8mb4_general_ci','port'=>(int)env('TENANT_DB_PORT',3306)];
        $adminDb=\Config\Database::connect($tenantCfg,false);
        $safeName=preg_replace('/[^a-zA-Z0-9_]/','',$dbName);
        $adminDb->query("CREATE DATABASE IF NOT EXISTS `{$safeName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        if (!$tenant) {
            $tenantId=$central->table('tenants')->insert([
                'company_name'=>$this->request->getPost('company_name'),'company_code'=>$code,'db_name'=>$dbName,
                'db_host'=>env('TENANT_DB_HOST','127.0.0.1'),'status'=>'active','created_at'=>date('Y-m-d H:i:s')
            ],true);
            $tenant=['id'=>$tenantId,'company_code'=>$code,'db_name'=>$dbName,'db_host'=>env('TENANT_DB_HOST','127.0.0.1')];
        }
        service('tenantContext')->setTenant($tenant);
        $db=service('tenantContext')->db();
        try {
            $this->createTenantSchema($db);
            $adminEmail=strtolower(trim((string)$this->request->getPost('admin_email')));
            $existingAdmin=$db->table('users')->where('email',$adminEmail)->get()->getRowArray();
            if (!$existingAdmin && $db->table('users')->insert(['name'=>$this->request->getPost('admin_name'),'email'=>$adminEmail,'password_hash'=>password_hash((string)$this->request->getPost('admin_password'),PASSWORD_DEFAULT),'role'=>'admin','active'=>1,'totp_enabled'=>0,'created_at'=>date('Y-m-d H:i:s')])===false) {
                throw new \RuntimeException('Unable to create the first tenant administrator.');
            }
        } catch (\Throwable $exception) {
            log_message('error', 'Tenant initialization failed for {code}: {message}', [
                'code' => $code,
                'message' => $exception->getMessage(),
            ]);
            service('session')->remove(['tenant_id','tenant_code','tenant_db']);
            return redirect()->back()->withInput()->with('error','Tenant setup did not finish. Your database changes were kept; retry the same company code after correcting the issue.');
        }
        service('session')->remove(['tenant_id','tenant_code','tenant_db']);
        $message=$existingAdmin?'Tenant schema is ready; the existing account was not changed.':'Tenant database created and first admin initialized.';
        return redirect()->to('/super-admin/tenants')->with('message',$message);
    }
    private function createTenantSchema($db): void
    {
        $sql = [
        "CREATE TABLE IF NOT EXISTS departments (id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL UNIQUE)",
        "CREATE TABLE IF NOT EXISTS locations (id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL UNIQUE)",
        "CREATE TABLE IF NOT EXISTS machine_types (id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL UNIQUE)",
        "CREATE TABLE IF NOT EXISTS brands (id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL UNIQUE)",
        "CREATE TABLE IF NOT EXISTS floors (id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL UNIQUE)",
        "CREATE TABLE IF NOT EXISTS `lines` (id INT AUTO_INCREMENT PRIMARY KEY,floor_id INT NOT NULL,name VARCHAR(100) NOT NULL,line_code VARCHAR(100) NOT NULL,FOREIGN KEY(floor_id) REFERENCES floors(id))",
        "CREATE TABLE IF NOT EXISTS machine_categories (id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL UNIQUE)",
        "CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL,email VARCHAR(190) NOT NULL UNIQUE,password_hash VARCHAR(255) NOT NULL,role ENUM('admin','manager','viewer') NOT NULL DEFAULT 'viewer',active TINYINT NOT NULL DEFAULT 1,totp_secret VARCHAR(64) NULL,totp_enabled TINYINT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL)",
        "CREATE TABLE IF NOT EXISTS assets (id BIGINT AUTO_INCREMENT PRIMARY KEY,asset_id VARCHAR(100) NOT NULL,serial_no VARCHAR(100) NULL,department_id INT NOT NULL,location_id INT NULL,machine_type_id INT NOT NULL,brand_id INT NOT NULL,model VARCHAR(150) NULL,installation_date DATE NULL,last_service_date DATE NULL,service_interval_days INT NULL,floor_id INT NULL,line_id INT NULL,line_code VARCHAR(100) NULL,machine_category VARCHAR(100) NOT NULL DEFAULT 'Regular',status ENUM('Active','Idle','Maintenance','Scrap') NOT NULL DEFAULT 'Active',qr_status ENUM('Printed','Not Printed') NOT NULL DEFAULT 'Not Printed',qr_printed_at DATETIME NULL,created_at DATETIME NOT NULL,updated_at DATETIME NULL,deleted_at DATETIME NULL,UNIQUE KEY uniq_asset_id (asset_id),INDEX idx_created (created_at),INDEX idx_status (status),FOREIGN KEY(department_id) REFERENCES departments(id),FOREIGN KEY(location_id) REFERENCES locations(id),FOREIGN KEY(machine_type_id) REFERENCES machine_types(id),FOREIGN KEY(brand_id) REFERENCES brands(id),FOREIGN KEY(floor_id) REFERENCES floors(id),FOREIGN KEY(line_id) REFERENCES `lines`(id))",
        "CREATE TABLE IF NOT EXISTS recovery_codes (id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,code_hash VARCHAR(255) NOT NULL,used_at DATETIME NULL,FOREIGN KEY(user_id) REFERENCES users(id))",
        "CREATE TABLE IF NOT EXISTS password_resets (id BIGINT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,token_hash CHAR(64) NOT NULL UNIQUE,expires_at DATETIME NOT NULL,created_at DATETIME NOT NULL,INDEX idx_password_resets_user (user_id),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE)",
        "CREATE TABLE IF NOT EXISTS audit_logs (id BIGINT AUTO_INCREMENT PRIMARY KEY,user_id INT NULL,event VARCHAR(100) NOT NULL,ip_address VARCHAR(45) NULL,created_at DATETIME NOT NULL)"
        ];
        foreach ($sql as $query) {
            if ($db->query($query) === false) {
                throw new \RuntimeException('Unable to create tenant schema.');
            }
        }

        $seedIfMissing = static function (string $table, array $rows) use ($db): void {
            foreach ($rows as $row) {
                $query = $db->table($table);
                foreach ($row as $column => $value) {
                    $query->where($column, $value);
                }
                if ($query->countAllResults() === 0 && $db->table($table)->insert($row) === false) {
                    throw new \RuntimeException('Unable to seed tenant master data.');
                }
            }
        };

        foreach ([
            'departments' => ['Production', 'Maintenance', 'Quality', 'Warehouse'],
            'locations' => ['Factory A', 'Factory B', 'Warehouse'],
            'machine_types' => ['SNLS UBT', 'Overlock', 'Flatlock', 'Cutting'],
            'brands' => ['JUKI', 'Brother', 'Pegasus', 'Typical'],
            'floors' => ['Ground Floor', 'First Floor', 'Second Floor'],
            'machine_categories' => ['Regular', 'Critical', 'Special'],
        ] as $table => $names) {
            $seedIfMissing($table, array_map(static fn (string $name): array => ['name' => $name], $names));
        }

        $seedIfMissing('lines', [
            ['floor_id' => 1, 'name' => 'Line 01', 'line_code' => 'L01'],
            ['floor_id' => 1, 'name' => 'Line 02', 'line_code' => 'L02'],
            ['floor_id' => 2, 'name' => 'Line 11', 'line_code' => 'L11'],
            ['floor_id' => 3, 'name' => 'Line 21', 'line_code' => 'L21'],
        ]);
    }
    public function toggle($id)
    {
        $db=db_connect();$t=$db->table('tenants')->where('id',(int)$id)->get()->getRowArray();
        if(!$t)return $this->response->setStatusCode(404);
        $db->table('tenants')->where('id',(int)$id)->update(['status'=>$t['status']==='active'?'inactive':'active']);
        return redirect()->to('/super-admin/tenants')->with('message','Tenant status updated.');
    }
    public function migrateAll()
    {
        $tenants = db_connect()->table('tenants')->orderBy('id')->get()->getResultArray();
        $failed = [];

        foreach ($tenants as $tenant) {
            $db = null;
            try {
                $db = \Config\Database::connect([
                    'hostname' => $tenant['db_host'] ?: env('TENANT_DB_HOST', '127.0.0.1'),
                    'username' => env('TENANT_DB_USER', 'root'),
                    'password' => env('TENANT_DB_PASS', ''),
                    'database' => $tenant['db_name'],
                    'DBDriver' => 'MySQLi',
                    'charset' => 'utf8mb4',
                    'DBCollat' => 'utf8mb4_general_ci',
                    'port' => (int) env('TENANT_DB_PORT', 3306),
                    'DBDebug' => true,
                    'pConnect' => false,
                ], false);
                $this->createTenantSchema($db);
                $db->close();
            } catch (\Throwable $exception) {
                if ($db) {
                    $db->close();
                }
                $failed[] = $tenant['company_code'];
                log_message('error', 'Tenant schema update failed for {code}: {error}', [
                    'code' => $tenant['company_code'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($failed) {
            return redirect()->to('/super-admin/tenants')->with('error', 'Schema update failed for: ' . implode(', ', $failed));
        }

        return redirect()->to('/super-admin/tenants')->with('message', count($tenants) . ' tenant database(s) updated.');
    }
}
