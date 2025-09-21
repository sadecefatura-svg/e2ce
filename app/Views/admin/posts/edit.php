<?php $view_file = __FILE__; ?>
<h2>Edit Post</h2>

<?php if (empty($row)): ?>
  <div class="alert alert-warning">Post not found.</div>
<?php else: ?>
  <form method="post" action="/admin/posts/edit">
    <input type="hidden" name="id" value="<?= (int)($row['id'] ?? 0) ?>">

    <div class="mb-3">
      <label class="form-label">Language</label>
      <select class="form-select" name="language_code" required>
        <?php foreach (($langs ?? []) as $code => $name): ?>
          <option value="<?= htmlspecialchars($code) ?>" <?= ($row['language_code'] ?? 'en') === $code ? 'selected' : '' ?>>
            <?= htmlspecialchars($name) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Title</label>
      <input class="form-control" name="title" value="<?= htmlspecialchars($row['title'] ?? '') ?>" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Slug</label>
      <input class="form-control" name="slug" value="<?= htmlspecialchars($row['slug'] ?? '') ?>" required>
      <small class="text-muted">Unique per language.</small>
    </div>

    <div class="mb-3">
      <label class="form-label">Section (Category)</label>
      <input class="form-control" name="section" value="<?= htmlspecialchars($row['section'] ?? '') ?>" placeholder="e.g. Technology">
    </div>

    <div class="mb-3">
      <label class="form-label">Tags (comma separated)</label>
      <input class="form-control" name="tags" value="<?= htmlspecialchars($row['tags'] ?? '') ?>" placeholder="ai, search, trends">
    </div>

    <div class="mb-3">
      <label class="form-label">Excerpt</label>
      <textarea class="form-control" name="excerpt" rows="2"><?= htmlspecialchars($row['excerpt'] ?? '') ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Body</label>
      <textarea class="form-control" name="body" id="editor" rows="12"><?= htmlspecialchars($row['body'] ?? '') ?></textarea>
      <div class="mt-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#mediaPicker">
          Insert image from library
        </button>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Cover Media</label>
      <div class="d-flex gap-2">
        <input type="number" class="form-control" name="cover_media_id" id="cover_media_id"
          value="<?= isset($row['cover_media_id']) ? (int)$row['cover_media_id'] : '' ?>"
          placeholder="e.g. 123">
        <button type="button" class="btn btn-outline-secondary"
          onclick="window.E2MediaPicker && E2MediaPicker.open('cover_media_id','cover_preview')">
          Choose
        </button>
      </div>
      <div class="mt-2">
        <img id="cover_preview"
          src="<?= !empty($row['cover_url']) ? htmlspecialchars($row['cover_url']) : '' ?>"
          class="img-fluid rounded <?= empty($row['cover_url']) ? 'd-none' : '' ?>"
          alt="Cover preview">
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label">Status</label>
      <select class="form-select" name="status">
        <option value="draft" <?= ($row['status'] ?? '') === 'draft'     ? 'selected' : '' ?>>draft</option>
        <option value="published" <?= ($row['status'] ?? '') === 'published' ? 'selected' : '' ?>>published</option>
        <option value="scheduled" <?= ($row['status'] ?? '') === 'scheduled' ? 'selected' : '' ?>>scheduled</option>
      </select>
    </div>

    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <button class="btn btn-primary">Save</button>
    <a class="btn btn-secondary" href="/admin/posts">Cancel</a>
  </form>

  <!-- Media Library Modal -->
  <div class="modal fade" id="mediaPicker" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Media Library</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="media-grid" class="row row-cols-2 row-cols-md-4 g-3"><!-- JS will fill --></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Media picker (cover seçimi) -->
  <script src="/assets/js/media-picker.js" defer></script>

  <!-- CKEditor: çift CDN + retry ile sağlam loader -->
  <script>
    (function() {
      const urls = [
        // jsDelivr build (çoğu bölgede hızlı ve SimpleUploadAdapter içerir)
        'https://cdn.jsdelivr.net/npm/@ckeditor/ckeditor5-build-classic@41.4.2/build/ckeditor.js',
        // Resmi CDN (yedek)
        'https://cdn.ckeditor.com/ckeditor5/43.0.0/classic/ckeditor.js'
      ];

      function loadScript(src, ok, fail) {
        const s = document.createElement('script');
        s.src = src;
        s.defer = true;
        s.onload = ok;
        s.onerror = fail;
        document.head.appendChild(s);
      }

      function initEditor() {
        if (!window.ClassicEditor) {
          setTimeout(initEditor, 120);
          return;
        }
        ClassicEditor.create(document.querySelector('#editor'), {
          simpleUpload: {
            uploadUrl: '/admin/media/ckeditor-upload'
          },
          link: {
            addTargetToExternalLinks: true
          }
        }).then(ed => {
          window.__editor = ed;
        }).catch(err => {
          console.error('CKEditor init error:', err);
          alert('Editor could not be initialized. See console.');
        });
      }

      function start(i) {
        if (i >= urls.length) {
          console.error('CKEditor could not be loaded from any CDN.');
          alert('CKEditor could not be loaded.');
          return;
        }
        loadScript(urls[i], initEditor, () => start(i + 1));
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => start(0));
      } else {
        start(0);
      }
    })();
  </script>

  <script>
    // Modal açılınca kütüphaneyi listele ve butonları bağla
    (function() {
      const mp = document.getElementById('mediaPicker');
      if (!mp) return;

      mp.addEventListener('show.bs.modal', async () => {
        try {
          const res = await fetch('/admin/media/json', {
            credentials: 'same-origin'
          });
          if (!res.ok) throw new Error('media fetch failed');
          const data = await res.json();
          const items = (data && data.items) ? data.items : [];
          const grid = document.getElementById('media-grid');
          grid.innerHTML = items.map(it => `
            <div class="col">
              <div class="card h-100">
                <img class="card-img-top" src="${it.path}" alt="${it.original_name}">
                <div class="card-body p-2 small">
                  <div class="text-truncate" title="${it.original_name}">${it.original_name}</div>
                  <div class="text-muted">${(it.width||'?')}×${(it.height||'?')} • ${(it.mime||'')}</div>
                </div>
                <div class="card-footer p-2 d-grid gap-1">
                  <button type="button" class="btn btn-sm btn-outline-primary" data-insert="${it.path}">Insert to editor</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-cover-id="${it.id}" data-src="${it.path}">Use as cover</button>
                </div>
              </div>
            </div>
          `).join('');
        } catch (e) {
          document.getElementById('media-grid').innerHTML =
            '<div class="col"><div class="alert alert-danger">Could not load media.</div></div>';
        }
      });

      mp.addEventListener('click', (e) => {
        const btnInsert = e.target.closest('[data-insert]');
        const btnCover = e.target.closest('[data-cover-id]');
        if (btnInsert) {
          const src = btnInsert.getAttribute('data-insert');
          if (window.__editor) {
            const ed = window.__editor;
            ed.model.change(writer => {
              const image = writer.createElement('imageBlock', {
                src
              });
              ed.model.insertContent(image, ed.model.document.selection);
            });
          } else {
            alert('Editor not ready yet.');
          }
        }
        if (btnCover) {
          const id = btnCover.getAttribute('data-cover-id');
          const src = btnCover.getAttribute('data-src');
          const inp = document.getElementById('cover_media_id');
          if (inp) {
            inp.value = id;
            inp.dispatchEvent(new Event('change'));
          }
          const prev = document.getElementById('cover_preview');
          if (prev) {
            prev.src = src;
            prev.classList.remove('d-none');
          }
        }
      });
    })();
  </script>
<?php endif; ?>