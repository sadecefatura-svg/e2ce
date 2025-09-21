<?php $view_file = __FILE__; ?>
<div class="row justify-content-center">
  <div class="col-md-4">
    <h2 class="mb-4">Admin Login</h2>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" action="/admin/login">
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input class="form-control" type="email" name="email" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input class="form-control" type="password" name="password" required>
      </div>
      <input type="hidden" name="_csrf" value="<?= \App\Core\Csrf::token((require __DIR__ . '/../../../config/config.php')['security']['csrf_key']) ?>">
      <button class="btn btn-primary w-100">Login</button>
      <div class="mt-2"><a href="/auth/forgot">Forgot password?</a></div>
    </form>
  </div>
</div>
