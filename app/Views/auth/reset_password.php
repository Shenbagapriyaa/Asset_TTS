<?= $this->extend('layout') ?><?= $this->section('content') ?>
<div class="auth panel">
    <h1>Reset password</h1>
    <?php if ($invalid): ?>
        <div class="flash err">This reset link is invalid or expired. Request a new link.</div>
        <p><a href="<?= site_url('forgot-password') ?>">Request another link</a></p>
    <?php else: ?>
        <form method="post" action="<?= site_url('reset-password') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="company_code" value="<?= esc($companyCode) ?>">
            <input type="hidden" name="token" value="<?= esc($token) ?>">
            <div class="field"><label>New password *</label><input type="password" name="password" required minlength="12" maxlength="128" autocomplete="new-password"></div>
            <div class="field"><label>Confirm new password *</label><input type="password" name="password_confirm" required minlength="12" maxlength="128" autocomplete="new-password"></div>
            <button class="btn" type="submit">Update password</button>
        </form>
    <?php endif ?>
</div>
<?= $this->endSection() ?>