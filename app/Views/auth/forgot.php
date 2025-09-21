
<?php $view_file = __FILE__; ?>
<h2>Forgot Password</h2>
<?php if (!empty($msg)): ?>
  <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<form method="post" action="/auth/forgot" class="mt-3" autocomplete="off">
  <div class="mb-3">
    <label class="form-label">Email</label>
    <input class="form-control" type="email" name="email" required autofocus>
  </div>
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8') ?>">
  <button class="btn btn-primary">Send reset link</button>
</form>
<p class="mt-3"><a href="/admin">Back to login</a></p>
