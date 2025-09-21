(function () {
    async function fetchMedia() {
        const res = await fetch('/admin/media/json', { credentials: 'same-origin' });
        if (!res.ok) throw new Error('media fetch failed');
        return res.json();
    }
    function modal(html) {
        const wrap = document.createElement('div');
        wrap.innerHTML = `
        <div class="modal fade" id="mpModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Select cover</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">${html}</div>
            </div>
          </div>
        </div>`;
        document.body.appendChild(wrap);
        const modalEl = wrap.querySelector('#mpModal');
        const m = new bootstrap.Modal(modalEl);
        m.show();
        // Modal kapanınca wrapper'ı kaldır
        modalEl.addEventListener('hidden.bs.modal', () => wrap.remove());
        return m;
    }

    async function openPicker(targetInputId, previewImgId) {
        try {
            const data = await fetchMedia();
            const items = (data && data.items) ? data.items : [];
            const grid = items.map(it => `
            <div class="col">
              <div class="card h-100">
                <img class="card-img-top" src="${it.path}" alt="${it.original_name}">
                <div class="card-body p-2 small">
                  <div class="text-truncate" title="${it.original_name}">${it.original_name}</div>
                  <div class="text-muted">${(it.width ?? '?')}×${(it.height ?? '?')} • ${it.mime ?? ''}</div>
                </div>
                <div class="card-footer p-2">
                  <button class="btn btn-sm btn-primary w-100" data-pick="${it.id}" data-src="${it.path}">Use</button>
                </div>
              </div>
            </div>
          `).join('');
            const html = `<div class="row row-cols-2 row-cols-md-4 g-3">${grid}</div>`;
            const m = modal(html);
            document.querySelector('#mpModal').addEventListener('click', (e) => {
                const btn = e.target.closest('[data-pick]');
                if (!btn) return;
                const id = btn.getAttribute('data-pick');
                const src = btn.getAttribute('data-src');
                const inp = document.getElementById(targetInputId);
                if (inp) { inp.value = id; inp.dispatchEvent(new Event('change')); }
                const prev = document.getElementById(previewImgId);
                if (prev) { prev.src = src; prev.classList.remove('d-none'); }
                m.hide();
            });
        } catch (err) {
            alert('Could not load media: ' + err.message);
        }
    }

    window.E2MediaPicker = { open: openPicker };
})();
