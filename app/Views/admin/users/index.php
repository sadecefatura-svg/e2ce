<?php $view_file = __FILE__; ?>
<h2 class="mb-3">Users</h2>

<div class="d-flex justify-content-between align-items-center mb-3">
  <form class="d-flex gap-2" method="get" action="/admin/users">
    <input type="text" class="form-control" name="q" placeholder="Search name or email" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
    <button class="btn btn-outline-secondary">Search</button>
  </form>
  <a class="btn btn-success" href="/admin/users/create">Create User</a>
</div>

<table class="table table-striped align-middle">
  <thead>
    <tr>
      <th style="width:80px">ID</th>
      <th>Name</th>
      <th>Email</th>
      <th style="width:120px">Role</th>
      <th style="width:100px">Active</th>
      <th style="width:160px"></th>
    </tr>
  </thead>
  <tbody>
  <?php if (!empty($rows)): foreach ($rows as $u): ?>
    <tr>
      <td><?= (int)$u['id'] ?></td>
      <td><?= htmlspecialchars($u['full_name'] ?? '') ?></td>
      <td><?= htmlspecialchars($u['email']) ?></td>
      <td><span class="badge bg-secondary"><?= htmlspecialchars($u['role_name']) ?></span></td>
      <td><?= $u['is_active'] ? 'Yes' : 'No' ?></td>
      <td class="text-end">
        <a class="btn btn-sm btn-primary" href="/admin/users/edit?id=<?= (int)$u['id'] ?>">Edit</a>
        <?php if ((int)$u['id'] !== (int)($me_id ?? 0)): ?>
          <a class="btn btn-sm btn-outline-danger" href="/admin/users/delete?id=<?= (int)$u['id'] ?>&_csrf=<?= urlencode($_csrf) ?>"
             onclick="return confirm('Delete this user?');">Delete</a>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; else: ?>
    <tr><td colspan="6" class="text-muted">No users found.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
