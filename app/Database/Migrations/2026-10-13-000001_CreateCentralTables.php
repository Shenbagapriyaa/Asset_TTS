<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCentralTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'=>['type'=>'INT','constraint'=>11,'unsigned'=>true,'auto_increment'=>true],
            'company_name'=>['type'=>'VARCHAR','constraint'=>150],
            'company_code'=>['type'=>'VARCHAR','constraint'=>24],
            'db_name'=>['type'=>'VARCHAR','constraint'=>64],
            'db_host'=>['type'=>'VARCHAR','constraint'=>190,'default'=>'127.0.0.1'],
            'status'=>['type'=>'ENUM','constraint'=>['active','inactive'],'default'=>'active'],
            'created_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('id',true);$this->forge->addUniqueKey('company_code');$this->forge->addUniqueKey('db_name');$this->forge->createTable('tenants',true);
        $this->forge->addField([
            'id'=>['type'=>'INT','constraint'=>11,'unsigned'=>true,'auto_increment'=>true],
            'name'=>['type'=>'VARCHAR','constraint'=>120],
            'email'=>['type'=>'VARCHAR','constraint'=>190],
            'password_hash'=>['type'=>'VARCHAR','constraint'=>255],
            'created_at'=>['type'=>'DATETIME'],
        ]);
        $this->forge->addKey('id',true);$this->forge->addUniqueKey('email');$this->forge->createTable('super_admins',true);
    }
    public function down(){ $this->forge->dropTable('super_admins',true);$this->forge->dropTable('tenants',true); }
}