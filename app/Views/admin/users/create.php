<?php $view_file = __FILE__; ?>
<h2 class="mb-3">Create User</h2>

<form method="post" action="/admin/users/create" autocomplete="off">
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Full name</label>
      <input class="form-control" name="full_name" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Email</label>
      <input class="form-control" type="email" name="email" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Password</label>
      <input class="form-control" type="password" name="password" required minlength="6">
    </div>
    <div class="col-md-6">
      <label class="form-label">Role</label>
      <select class="form-select" name="role_id" required>
        <?php foreach ($roles as $r): ?>
          <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label">Active</label>
      <select class="form-select" name="is_active">
        <option value="1" selected>Yes</option>
        <option value="0">No</option>
      </select>
    </div>
  </div>

  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8') ?>">
  <div class="mt-3 d-flex gap-2">
    <button class="btn btn-primary">Save</button>
    <a class="btn btn-secondary" href="/admin/users">Cancel</a>
  </div>
</form>
