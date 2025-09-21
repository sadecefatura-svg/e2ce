<?php $view_file = __FILE__; ?>
<h2>Create Post</h2>
<form method="post" action="/admin/posts/create">
  <div class="mb-3">
    <label class="form-label">Language</label>
    <select class="form-select" name="language_code" required>
      <?php foreach (($langs ?? []) as $code => $name): ?>
        <option value="<?= htmlspecialchars($code) ?>" <?= ($default_lang ?? 'en') === $code ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="mb-3">
    <label class="form-label">Title</label>
    <input class="form-control" name="title" required>
  </div>

  <div class="mb-3">
    <label class="form-label">Slug</label>
    <input class="form-control" name="slug" required>
    <small class="text-muted">Unique per language.</small>
  </div>

  <div class="mb-3">
    <label class="form-label">Section (Category)</label>
    <input class="form-control" name="section" placeholder="e.g. Technology">
  </div>

  <div class="mb-3">
    <label class="form-label">Tags (comma separated)</label>
    <input class="form-control" name="tags" placeholder="ai, search, trends">
  </div>

  <div class="mb-3">
    <label class="form-label">Excerpt</label>
    <textarea class="form-control" name="excerpt" rows="2"></textarea>
  </div>

  <div class="mb-3">
    <label class="form-label">Body</label>
    <textarea class="form-control" name="body" id="editor" rows="8"></textarea>
  </div>
  <div class="mb-3">
    <label class="form-label">Cover Media ID (optional)</label>
    <div class="d-flex gap-2">
      <input type="number" class="form-control" name="cover_media_id" id="cover_media_id" placeholder="e.g. 123">
      <button type="button" class="btn btn-outline-secondary" onclick="E2MediaPicker.open('cover_media_id','cover_preview')">Choose…</button>
    </div>
    <div class="mt-2">
      <img id="cover_preview" src="" class="img-fluid rounded d-none" alt="Cover preview">
    </div>
  </div>
  <div class="mb-3">
    <label class="form-label">Status</label>
    <select class="form-select" name="status">
      <option value="draft">draft</option>
      <option value="published">published</option>
      <option value="scheduled">scheduled</option>
    </select>
  </div>

  <input type="hidden" name="translation_key" value="<?= htmlspecialchars($translation_key ?? '', ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8') ?>">
  <button class="btn btn-primary">Save</button>
  <a class="btn btn-secondary" href="/admin/posts">Cancel</a>
</form>

<!-- CKEditor: çift CDN fallback -->
<script>
  (function() {
    const urls = [
      'https://cdn.ckeditor.com/ckeditor5/43.0.0/classic/ckeditor.js',
      'https://cdn.jsdelivr.net/npm/@ckeditor/ckeditor5-build-classic@43.0.0/build/ckeditor.js'
    ];

    function loadScript(src, onload, onerror) {
      const s = document.createElement('script');
      s.src = src;
      s.defer = true;
      s.onload = onload;
      s.onerror = onerror;
      document.head.appendChild(s);
    }

    function startEditor() {
      if (!window.ClassicEditor) {
        console.error('CKEditor not loaded');
        return;
      }
      ClassicEditor.create(document.querySelector('#editor'), {
        simpleUpload: {
          uploadUrl: '/admin/media/ckeditor-upload'
        },
        link: {
          addTargetToExternalLinks: true
        }
      }).catch(console.error);
    }

    function tryLoad(i) {
      if (i >= urls.length) {
        console.error('CKEditor load failed');
        return;
      }
      loadScript(urls[i], () => window.ClassicEditor ? startEditor() : tryLoad(i + 1), () => tryLoad(i + 1));
    }
    (document.readyState === 'loading') ? document.addEventListener('DOMContentLoaded', () => tryLoad(0)): tryLoad(0);
  })();
</script>
<script src="/assets/js/media-picker.js"></script>