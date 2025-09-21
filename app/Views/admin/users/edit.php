<?php $view_file = __FILE__; ?>
<h2 class="mb-3">Edit User</h2>

<?php if (!$row): ?>
  <div class="alert alert-warning">User not found.</div>
  <a class="btn btn-secondary" href="/admin/users">Back</a>
<?php else: ?>
<form method="post" action="/admin/users/edit" autocomplete="off">
  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Full name</label>
      <input class="form-control" name="full_name" value="<?= htmlspecialchars($row['full_name'] ?? '') ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Email</label>
      <input class="form-control" type="email" name="email" value="<?= htmlspecialchars($row['email']) ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">New password (optional)</label>
      <input class="form-control" type="password" name="password" placeholder="Leave blank to keep current">
    </div>
    <div class="col-md-6">
      <label class="form-label">Role</label>
      <select class="form-select" name="role_id" required>
        <?php foreach ($roles as $r): ?>
          <option value="<?= (int)$r['id'] ?>" <?= ((int)$r['id']===(int)$row['role_id'])?'selected':'' ?>>
            <?= htmlspecialchars($r['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label">Active</label>
      <select class="form-select" name="is_active">
        <option value="1" <?= $row['is_active']?'selected':'' ?>>Yes</option>
        <option value="0" <?= !$row['is_active']?'selected':'' ?>>No</option>
      </select>
    </div>
  </div>

  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8') ?>">
  <div class="mt-3 d-flex gap-2">
    <button class="btn btn-primary">Save</button>
    <a class="btn btn-secondary" href="/admin/users">Cancel</a>
  </div>
</form>
<?php endif; ?>
