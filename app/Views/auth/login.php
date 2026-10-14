<?= $this->extend('layout') ?><?= $this->section('content') ?>
<div class="auth panel"><h1>Sign in</h1><p class="muted">Enter your company code and account details.</p>
<?php if(session()->getFlashdata('error')): ?><div class="flash err"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
<form method="post" action="<?= site_url('login') ?>"><?= csrf_field() ?>
<div class="field"><label>Company Code *</label><input name="company_code" required value="<?= old('company_code') ?>" placeholder="e.g. ACME"></div>
<div class="field"><label>Email *</label><input type="email" name="email" required value="<?= old('email') ?>" placeholder="name@company.com"></div>
<div class="field"><label>Password *</label><input type="password" name="password" required minlength="8"></div>
<button class="btn" type="submit" style="width:100%">Continue</button></form><p><a href="<?= site_url('forgot-password') ?>">Forgot password?</a></p><p><a href="<?= site_url('super-admin/login') ?>">Super Admin</a></p></div>
<?= $this->endSection() ?>
