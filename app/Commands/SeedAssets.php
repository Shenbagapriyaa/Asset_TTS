<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SeedAssets extends BaseCommand
{
    protected $group='Database';
    protected $name='assets:seed';
    protected $description='Seed sample assets for the currently selected tenant database.';
    protected $usage='assets:seed [count]';
    public function run(array $params)
    {
        $count=max(1,min(100000,(int)($params[0]??1000)));
        $ctx=service('tenantContext');
        $tenantCode=CLI::prompt('Tenant company code');
        $tenant=$ctx->resolveByCode((string)$tenantCode);
        if(!$tenant){CLI::error('Tenant not found or inactive.');return;}
        $ctx->setTenant($tenant);$db=$ctx->db();
        $dept=$db->table('departments')->select('id')->get()->getResultArray();
        $brands=$db->table('brands')->select('id')->get()->getResultArray();
        $types=$db->table('machine_types')->select('id')->get()->getResultArray();
        if(!$dept||!$brands||!$types){CLI::error('Master data missing. Create tenant first.');return;}
        for($i=1;$i<=$count;$i++){
            $db->table('assets')->insert([
                'asset_id'=>'SEED-'.str_pad((string)$i,6,'0',STR_PAD_LEFT),'serial_no'=>'SN-'.$i,
                'department_id'=>$dept[($i-1)%count($dept)]['id'],'machine_type_id'=>$types[($i-1)%count($types)]['id'],
                'brand_id'=>$brands[($i-1)%count($brands)]['id'],'model'=>'Model '.(($i%25)+1),
                'machine_category'=>'Regular','status'=>['Active','Idle','Maintenance','Scrap'][$i%4],
                'qr_status'=>$i%3===0?'Printed':'Not Printed','qr_printed_at'=>$i%3===0?date('Y-m-d H:i:s'):null,
                'created_at'=>date('Y-m-d H:i:s',time()-($i*60)),'updated_at'=>date('Y-m-d H:i:s')
            ]);
            if($i%500===0)CLI::write("Inserted {$i} assets");
        }
        CLI::write("Done: {$count} assets seeded.");
    }
}
