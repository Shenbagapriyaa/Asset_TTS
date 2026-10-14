<?php

namespace App\Controllers;

class AssetsController extends BaseController
{
    private array $fields = [
        'asset_id', 'serial_no', 'department_id', 'location_id', 'machine_type_id',
        'brand_id', 'model', 'installation_date', 'last_service_date',
        'service_interval_days', 'floor_id', 'line_id', 'line_code', 'machine_category'
    ];

    public function index()
    {
        $db = $this->tenantDb();
        $request = $this->request;
        $search = trim((string) $request->getGet('search'));
        $perPage = (int) $request->getGet('perPage');
        if (!in_array($perPage, [10,25,50,100], true)) $perPage = 10;
        $page = max(1, (int) $request->getGet('page'));
        $sortAllow = ['created_at','asset_id','serial_no','department','brand','model','machine_type','status','qr_status'];
        $sort = (string) $request->getGet('sort');
        if (!in_array($sort, $sortAllow, true)) $sort = 'created_at';
        $direction = strtoupper((string) $request->getGet('direction')) === 'ASC' ? 'ASC' : 'DESC';

        $builder = $db->table('assets a')
            ->select('a.*, d.name AS department, b.name AS brand, mt.name AS machine_type, l.name AS location, f.name AS floor, ln.name AS line_name')
            ->join('departments d','d.id=a.department_id','left')
            ->join('brands b','b.id=a.brand_id','left')
            ->join('machine_types mt','mt.id=a.machine_type_id','left')
            ->join('locations l','l.id=a.location_id','left')
            ->join('floors f','f.id=a.floor_id','left')
            ->join('lines ln','ln.id=a.line_id','left')
            ->where('a.deleted_at', null);
        if ($search !== '') {
            $builder->groupStart()->like('a.asset_id',$search)->orLike('a.serial_no',$search)
                ->orLike('d.name',$search)->orLike('b.name',$search)->orLike('a.model',$search)
                ->orLike('mt.name',$search)->orLike('a.status',$search)->orLike('a.qr_status',$search)
                ->orLike('a.line_code',$search)->groupEnd();
        }
        foreach (['department'=>'d.name','brand'=>'b.name','model'=>'a.model','machine_type'=>'mt.name','status'=>'a.status','qr_status'=>'a.qr_status'] as $param=>$column) {
            $val = trim((string)$request->getGet($param));
            if ($val !== '' && $val !== 'all') $builder->where($column, $val);
        }
        $from = (string)$request->getGet('from'); $to = (string)$request->getGet('to');
        if ($from !== '') $builder->where('DATE(a.created_at) >=', $from);
        if ($to !== '') $builder->where('DATE(a.created_at) <=', $to);

        $countBuilder = clone $builder;
        $total = $countBuilder->countAllResults();
        $sortMap = ['department'=>'d.name','brand'=>'b.name','machine_type'=>'mt.name'];
        $sortColumn = $sortMap[$sort] ?? 'a.' . $sort;
        $rows = $builder->orderBy($sortColumn, $direction)->limit($perPage, ($page-1)*$perPage)->get()->getResultArray();

        $counts = [];
        foreach (['Total Assets'=>'all','Active'=>'Active','Idle'=>'Idle','Maintenance'=>'Maintenance','Scrap'=>'Scrap'] as $label=>$status) {
            $q = $db->table('assets')->where('deleted_at', null);
            if ($status !== 'all') $q->where('status',$status);
            $counts[$label] = $q->countAllResults();
        }
        $counts['Brands'] = $db->table('brands')->countAllResults();
        $counts['Machine Types'] = $db->table('machine_types')->countAllResults();

        return view('assets/index', compact('rows','total','perPage','page','search','sort','direction','counts'));
    }

    public function new()
    {
        if ($r = $this->requireMutationPermission()) return $r;
        return view('assets/form', ['asset'=>null, 'masters'=>$this->masters(), 'title'=>'Asset Creation']);
    }

    public function create()
    {
        if ($r = $this->requireMutationPermission()) return $r;
        return $this->saveAsset(null);
    }

    public function edit($id)
    {
        if (!$this->canMutate()) return redirect()->to('/assets')->with('error','Permission denied.');
        $db = $this->tenantDb();
        $asset = $db->table('assets')->where('id',(int)$id)->where('deleted_at',null)->get()->getRowArray();
        if (!$asset) return $this->response->setStatusCode(404)->setBody('Asset not found.');
        return view('assets/form', ['asset'=>$asset, 'masters'=>$this->masters(), 'title'=>'Edit Asset']);
    }

    public function update($id)
    {
        if ($r = $this->requireMutationPermission()) return $r;
        return $this->saveAsset((int)$id);
    }

    private function saveAsset(?int $id)
    {
        $db = $this->tenantDb();
        $assetId = trim((string)$this->request->getPost('asset_id'));
        $rules = [
            'asset_id'=>'required|max_length[100]',
            'department_id'=>'required|is_natural_no_zero',
            'machine_type_id'=>'required|is_natural_no_zero',
            'brand_id'=>'required|is_natural_no_zero',
            'serial_no'=>'permit_empty|max_length[100]',
            'installation_date'=>'permit_empty|valid_date[Y-m-d]',
            'last_service_date'=>'permit_empty|valid_date[Y-m-d]',
            'service_interval_days'=>'permit_empty|is_natural_no_zero',
        ];
        if (!$this->validate($rules)) return redirect()->back()->withInput()->with('errors',$this->validator->getErrors());
        if ($this->request->getPost('installation_date') && $this->request->getPost('installation_date') > date('Y-m-d')) {
            return redirect()->back()->withInput()->with('error','Installation date cannot be in the future.');
        }
        if ($this->request->getPost('last_service_date') && $this->request->getPost('installation_date') && $this->request->getPost('last_service_date') < $this->request->getPost('installation_date')) {
            return redirect()->back()->withInput()->with('error','Last service date cannot be before installation date.');
        }
        $dup = $db->table('assets')->where('asset_id',$assetId)->where('deleted_at',null);
        if ($id) $dup->where('id !=',$id);
        if ($dup->countAllResults() > 0) return redirect()->back()->withInput()->with('errors',['asset_id'=>'This Asset ID already exists in this company.']);
        $data = [];
        foreach ($this->fields as $field) {
            $value = $this->request->getPost($field);
            $data[$field] = ($value === '') ? null : $value;
        }
        $data['machine_category'] = $data['machine_category'] ?: 'Regular';
        if ($id) {
            $db->table('assets')->where('id',$id)->where('deleted_at',null)->update($data);
            return redirect()->to('/assets')->with('message','Asset updated successfully.');
        }
        $data['status']='Active'; $data['qr_status']='Not Printed';
        $data['created_at']=date('Y-m-d H:i:s'); $data['updated_at']=date('Y-m-d H:i:s');
        $db->table('assets')->insert($data);
        return redirect()->to('/assets')->with('message','Asset created successfully.');
    }

    public function delete($id)
    {
        if ($r = $this->requireMutationPermission()) return $r;
        $this->tenantDb()->table('assets')->where('id',(int)$id)->where('deleted_at',null)->update(['deleted_at'=>date('Y-m-d H:i:s')]);
        return redirect()->to('/assets')->with('message','Asset moved to deleted records.');
    }

    public function bulkDelete()
    {
        if ($r = $this->requireMutationPermission()) return $r;
        $ids = array_values(array_filter(array_map('intval',(array)$this->request->getPost('ids'))));
        if (!$ids) return redirect()->to('/assets')->with('error','Select at least one asset.');
        $this->tenantDb()->table('assets')->whereIn('id',$ids)->where('deleted_at',null)->update(['deleted_at'=>date('Y-m-d H:i:s')]);
        return redirect()->to('/assets')->with('message',count($ids).' selected asset(s) moved to deleted records.');
    }

    public function lines($floorId)
    {
        $rows = $this->tenantDb()->table('lines')->where('floor_id',(int)$floorId)->get()->getResultArray();
        return $this->response->setJSON($rows);
    }

    private function masters(): array
    {
        $db=$this->tenantDb(); $out=[];
        foreach (['departments','locations','machine_types','brands','floors','lines','machine_categories'] as $table) {
            $out[$table]=$db->table($table)->orderBy('name')->get()->getResultArray();
        }
        return $out;
    }
}
