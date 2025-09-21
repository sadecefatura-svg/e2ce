<?php $view_file = __FILE__; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2>Auth Audit</h2>
  <div><a class="btn btn-outline-secondary btn-sm" href="/admin">Dashboard</a></div>
</div>

<div class="table-responsive">
  <table class="table table-sm table-striped">
    <thead>
      <tr>
        <th>#</th><th>Time</th><th>OK</th><th>Email</th><th>User ID</th><th>IP</th><th>Reason</th><th>User-Agent</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= htmlspecialchars($r['created_at']) ?></td>
        <td><?= $r['ok'] ? '✅' : '❌' ?></td>
        <td><?= htmlspecialchars((string)$r['email']) ?></td>
        <td><?= htmlspecialchars((string)$r['user_id']) ?></td>
        <td><?= htmlspecialchars((string)$r['ip']) ?></td>
        <td><?= htmlspecialchars((string)$r['reason']) ?></td>
        <td class="small text-muted"><?= htmlspecialchars((string)$r['user_agent']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
