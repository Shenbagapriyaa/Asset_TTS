<?= $this->extend('layout') ?><?= $this->section('content') ?>
<div class="auth panel">
    <h1>Reset password</h1>
    <p class="muted">Enter your company code and account email. If they match an active account, we will send a reset link.</p>
    <?php if (session()->getFlashdata('message')): ?><div class="flash"><?= esc(session()->getFlashdata('message')) ?></div><?php endif ?>
    <?php if (session()->getFlashdata('error')): ?><div class="flash err"><?= esc(session()->getFlashdata('error')) ?></div><?php endif ?>
    <form method="post" action="<?= site_url('forgot-password') ?>">
        <?= csrf_field() ?>
        <div class="field"><label>Company Code *</label><input name="company_code" required value="<?= esc(old('company_code')) ?>" autocomplete="organization"></div>
        <div class="field"><label>Email *</label><input type="email" name="email" required value="<?= esc(old('email')) ?>" autocomplete="email"></div>
        <button class="btn" type="submit">Send reset link</button>
    </form>
    <p><a href="<?= site_url('/') ?>">Back to sign in</a></p>
</div>
<?= $this->endSection() ?>