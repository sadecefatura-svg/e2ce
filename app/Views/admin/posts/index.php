<?php $view_file = __FILE__; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Posts</h2>
  <div class="d-flex gap-2">
    <a class="btn btn-sm btn-outline-secondary" href="/admin">Dashboard</a>
    <a class="btn btn-sm btn-primary" href="/admin/posts/create">Create</a>
  </div>
</div>

<form class="row g-2 mb-3" method="get" action="/admin/posts">
  <div class="col-auto">
    <select class="form-select form-select-sm" name="lang" onchange="this.form.submit()">
      <option value="">All languages</option>
      <?php foreach(($langs ?? []) as $code=>$name): ?>
        <option value="<?= htmlspecialchars($code) ?>" <?= ($lang??'')===$code?'selected':'' ?>><?= htmlspecialchars($name) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</form>

<?php if (!empty($rows)): ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr>
        <th>ID</th><th>Lang</th><th>Title</th><th>Slug</th><th>Status</th><th>Updated</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><span class="badge bg-secondary"><?= htmlspecialchars($r['language_code'] ?? 'en') ?></span></td>
          <td><?= htmlspecialchars($r['title']) ?></td>
          <td><?= htmlspecialchars($r['slug']) ?></td>
          <td><?= htmlspecialchars($r['status']) ?></td>
          <td><?= htmlspecialchars($r['updated_at'] ?? $r['created_at']) ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary" href="/admin/posts/edit?id=<?= (int)$r['id'] ?>">Edit</a>
            <form class="d-inline" method="post" action="/admin/posts/delete" onsubmit="return confirm('Delete?')">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <?php
// ...
$cfg = require dirname(__DIR__, 4) . '/config/config.php'; // DÜZELTİLMİŞ YOL
// ...
?>
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token($cfg['security']['csrf_key']), ENT_QUOTES, 'UTF-8') ?>">
              <button class="btn btn-sm btn-outline-danger">Del</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <div class="alert alert-info">No posts.</div>
<?php endif; ?>
