<?= $this->extend('layout') ?><?= $this->section('content') ?>
<div class="panel"><h1><?= esc($title) ?> <a href="<?= site_url('assets') ?>" style="float:right;text-decoration:none">×</a></h1><p class="muted">Manual Entry</p>
<?php $v=static fn($k,$default='')=>old($k,$asset[$k]??$default); ?>
<?php if(session()->getFlashdata('errors')): ?><div class="flash err"><?php foreach(session()->getFlashdata('errors') as $e): ?><div><?= esc($e) ?></div><?php endforeach ?></div><?php endif ?>
<form method="post" action="<?= $asset ? site_url('assets/'.$asset['id']) : site_url('assets') ?>"><?= csrf_field() ?><div class="grid4">
<div class="field"><label>Asset ID <span class="required">*</span></label><input name="asset_id" required value="<?= esc($v('asset_id')) ?>" placeholder="Enter Asset ID"></div>
<div class="field"><label>Serial No</label><input name="serial_no" value="<?= esc($v('serial_no')) ?>" placeholder="Enter Serial No"></div>
<?php foreach([['department_id','Department','departments',true],['location_id','Location','locations',false],['machine_type_id','Machine Type','machine_types',true],['brand_id','Brand','brands',true]] as [$field,$label,$table,$required]): ?>
<div class="field"><label><?= $label ?> <?= $required?'<span class="required">*</span>':'' ?></label><select name="<?= $field ?>" <?= $required?'required':'' ?>><option value="">Select <?= $label ?></option><?php foreach($masters[$table] as $m): ?><option value="<?= $m['id'] ?>" <?= (string)$v($field)===(string)$m['id']?'selected':'' ?>><?= esc($m['name']) ?></option><?php endforeach ?></select></div>
<?php endforeach ?>
<div class="field"><label>Model</label><input name="model" value="<?= esc($v('model')) ?>" placeholder="Enter Model"></div>
<div class="field"><label>Installation Date</label><input type="date" name="installation_date" max="<?= date('Y-m-d') ?>" value="<?= esc($v('installation_date')) ?>"></div>
<div class="field"><label>Last Service Date</label><input type="date" name="last_service_date" value="<?= esc($v('last_service_date')) ?>"></div>
<div class="field"><label>Service Interval (Days)</label><input type="number" min="1" step="1" name="service_interval_days" value="<?= esc($v('service_interval_days')) ?>" placeholder="e.g. 90"></div>
<div class="field"><label>Floor</label><select id="floor_id" name="floor_id"><option value="">Select Floor</option><?php foreach($masters['floors'] as $m): ?><option value="<?= $m['id'] ?>" <?= (string)$v('floor_id')===(string)$m['id']?'selected':'' ?>><?= esc($m['name']) ?></option><?php endforeach ?></select></div>
<div class="field"><label>Line</label><select id="line_id" name="line_id"><option value="">Select Line</option><?php foreach($masters['lines'] as $m): ?><option data-floor="<?= $m['floor_id'] ?>" data-code="<?= esc($m['line_code']) ?>" value="<?= $m['id'] ?>" <?= (string)$v('line_id')===(string)$m['id']?'selected':'' ?>><?= esc($m['name']) ?></option><?php endforeach ?></select></div>
<div class="field"><label>Line Code</label><input id="line_code" name="line_code" value="<?= esc($v('line_code')) ?>" placeholder="Auto-filled or enter manually"></div>
<div class="field"><label>Machine Category</label><select name="machine_category"><?php foreach($masters['machine_categories'] as $m): ?><option value="<?= esc($m['name']) ?>" <?= $v('machine_category','Regular')===$m['name']?'selected':'' ?>><?= esc($m['name']) ?></option><?php endforeach ?></select></div>
</div><div class="toolbar"><button class="btn" type="submit"><?= $asset?'Update Asset':'Create Asset' ?></button><a class="btn gray" href="<?= site_url('assets') ?>">Cancel</a></div></form></div>
<script>
const floor=document.getElementById('floor_id'),line=document.getElementById('line_id'),code=document.getElementById('line_code');
function filterLines(){const f=floor.value;let first='';[...line.options].forEach((o,i)=>{if(i===0)return;const show=!f||o.dataset.floor===f;o.hidden=!show;if(show&&!first)first=o.value});if(line.selectedOptions[0]?.hidden)line.value='';fillCode()}
function fillCode(){const o=line.selectedOptions[0];if(o&&o.dataset.code)code.value=o.dataset.code}
floor.addEventListener('change',filterLines);line.addEventListener('change',fillCode);filterLines();
</script><?= $this->endSection() ?>
