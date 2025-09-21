<?php $view_file = __FILE__; ?>
<h2>Set New Password</h2>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!empty($ok)): ?>
  <div class="alert alert-success"><?= htmlspecialchars($ok) ?></div>
  <p><a class="btn btn-success" href="/admin">Go to Login</a></p>
<?php else: ?>
<form method="post" action="/auth/reset" class="mt-3" autocomplete="off">
  <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '') ?>">
  <div class="mb-3">
    <label class="form-label">New password</label>
    <input class="form-control" type="password" name="password" required minlength="6">
  </div>
  <div class="mb-3">
    <label class="form-label">Confirm password</label>
    <input class="form-control" type="password" name="password2" required minlength="6">
  </div>
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8') ?>">
  <button class="btn btn-primary">Update password</button>
</form>
<p class="mt-3"><a href="/admin">Back to login</a></p>
<?php endif; ?>
