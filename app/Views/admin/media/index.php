<?php $view_file = __FILE__; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Media</h2>
    <div>
        <a class="btn btn-outline-secondary btn-sm" href="/admin">Dashboard</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form id="uploadForm" method="post" action="/admin/media/upload" enctype="multipart/form-data">
            <div class="row g-2 align-items-center">
                <div class="col-md-6">
                    <input class="form-control" type="file" name="file" accept="image/*" required>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="variant" title="Convert/resize">
                        <option value="orig">Keep original</option>
                        <option value="webp_1200">Convert to WebP (1200w)</option>
                        <option value="webp_800">Convert to WebP (800w)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100">Upload</button>
                </div>
            </div>
            <?php $cfg = require __DIR__ . '/../../../../config/config.php'; ?>
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token($cfg['security']['csrf_key']), ENT_QUOTES, 'UTF-8') ?>">
            <small class="text-muted d-block mt-2">Allowed: jpg, png, gif, webp • Max 10 MB each</small>
        </form>
    </div>
</div>

<?php if (!empty($rows)): ?>
    <div class="row row-cols-2 row-cols-md-4 g-3">
        <?php foreach ($rows as $m): ?>
            <div class="col">
                <div class="card h-100">
                    <a href="<?= htmlspecialchars($m['path']) ?>" target="_blank" rel="noopener">
                        <img src="<?= htmlspecialchars($m['path']) ?>" class="card-img-top" alt="<?= htmlspecialchars($m['original_name']) ?>">
                    </a>
                    <div class="card-body p-2">
                        <div class="small text-truncate" title="<?= htmlspecialchars($m['original_name']) ?>"><?= htmlspecialchars($m['original_name']) ?></div>
                        <div class="small text-muted">
                            <?= (int)$m['width'] ?>×<?= (int)$m['height'] ?> • <?= htmlspecialchars($m['mime']) ?>
                        </div>
                    </div>
                    <div class="card-footer p-2">
                        <div class="d-flex gap-2">
                            <input class="form-control form-control-sm" value="<?= htmlspecialchars($m['path']) ?>" onclick="this.select();" readonly>
                            <form method="post" action="/admin/media/delete" onsubmit="return confirm('Delete this file?')">
                                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token($cfg['security']['csrf_key']), ENT_QUOTES, 'UTF-8') ?>">
                                <button class="btn btn-sm btn-outline-danger">Del</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="alert alert-info">No media yet. Upload images above.</div>
<?php endif; ?>