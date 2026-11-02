<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Logo Corrector NTT</title>
    <link rel="icon" href="{{ route('logo') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --ink: #14213d; --red: #c8102e; --muted: #6b7280; --line: #e5e7eb; --bg: #f6f7f9; }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--ink); }
        .btn-primary { --bs-btn-bg: var(--ink); --bs-btn-border-color: var(--ink); --bs-btn-hover-bg: #0b1426; --bs-btn-hover-border-color: #0b1426; --bs-btn-disabled-bg: var(--ink); --bs-btn-disabled-border-color: var(--ink); }
        .btn-outline-primary { --bs-btn-color: var(--ink); --bs-btn-border-color: #cfd5df; --bs-btn-hover-bg: var(--ink); --bs-btn-hover-border-color: var(--ink); }
        .wrap { max-width: 920px; }
        header img { height: 42px; }

        .dropzone { display: block; border: 2px dashed #cbd2dc; border-radius: 16px; padding: 5rem 1rem; text-align: center; cursor: pointer; background: #fff; transition: .15s; }
        .dropzone:hover, .dropzone.drag { border-color: var(--ink); background: #f3f5f9; }
        .dropzone i { font-size: 2.6rem; }

        .card-plain { background: #fff; border: 1px solid var(--line); border-radius: 16px; }
        .toolbar { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; padding: .75rem 1rem; border-bottom: 1px solid var(--line); }
        .toolbar .name { color: var(--muted); font-size: .875rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; flex: 1; }
        .canvas { background: repeating-conic-gradient(#eef0f3 0% 25%, #f8f9fb 0% 50%) 50% / 20px 20px; padding: 16px; overflow: hidden; }

        img.raw { image-orientation: none; display: block; max-width: 100%; max-height: 70vh; width: auto; user-select: none; -webkit-user-drag: none; }
        .stage { position: relative; width: fit-content; margin: 0 auto; touch-action: none; cursor: crosshair; }
        .candidate { position: absolute; border: 2px dashed #f2b705; cursor: pointer; }
        .candidate:hover { background: rgba(242,183,5,.15); }
        .selection { position: absolute; border: 2px solid var(--red); box-shadow: 0 0 0 9999px rgba(15,23,42,.35); cursor: move; }
        .selection .ghost { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; opacity: .55; }
        .selection .ghost img { max-width: 100%; max-height: 100%; }
        .handle { position: absolute; width: 12px; height: 12px; background: #fff; border: 2px solid var(--red); border-radius: 3px; }
        .handle[data-h="nw"] { left: -7px; top: -7px; cursor: nwse-resize; }
        .handle[data-h="ne"] { right: -7px; top: -7px; cursor: nesw-resize; }
        .handle[data-h="sw"] { left: -7px; bottom: -7px; cursor: nesw-resize; }
        .handle[data-h="se"] { right: -7px; bottom: -7px; cursor: nwse-resize; }

        .compare { position: relative; width: fit-content; margin: 0 auto; user-select: none; }
        .compare .before { position: absolute; inset: 0; clip-path: inset(0 100% 0 0); }
        .compare .before img.raw { width: 100%; height: 100%; max-height: none; }
        .compare .divider { position: absolute; top: 0; bottom: 0; left: 0%; width: 2px; background: #fff; box-shadow: 0 0 0 1px rgba(0,0,0,.25); pointer-events: none; }
        .compare .divider::after { content: '\F12A \F133'; font-family: bootstrap-icons; position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); background: #fff; border-radius: 999px; padding: 4px 6px; font-size: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.25); white-space: nowrap; }
        .compare .tag { position: absolute; top: 10px; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; background: rgba(15,23,42,.75); color: #fff; }
        .compare input[type=range] { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: ew-resize; margin: 0; }

        .hint { color: var(--muted); font-size: .85rem; }
        .ok-badge { background: #ecfdf3; color: #067647; border-radius: 999px; padding: .3rem .75rem; font-size: .85rem; font-weight: 500; }
        details summary { cursor: pointer; color: var(--muted); font-size: .85rem; list-style: none; }
        details summary::-webkit-details-marker { display: none; }
    </style>
</head>
<body>
<main class="wrap container py-4">
    <header class="d-flex align-items-center gap-2 mb-3">
        <img src="{{ route('logo') }}" alt="Lambang Provinsi NTT">
        <div class="lh-sm">
            <div class="fw-bold fs-5">Logo Corrector NTT</div>
            <div class="hint">Ganti logo pada infografis dengan logo resmi Pemprov NTT, tanpa mengubah desain.</div>
        </div>
    </header>

    <div id="alert" class="alert alert-danger d-none" role="alert"></div>

    <section id="app" class="card-plain">
        <div class="toolbar">
            <label class="btn btn-primary btn-sm mb-0" for="file-input"><i class="bi bi-upload me-1"></i> Unggah Foto</label>
            <input id="file-input" type="file" accept="image/png,image/jpeg,image/webp" hidden>
            <span id="file-name" class="name"></span>
            <a id="btn-download" class="btn btn-danger btn-sm disabled" href="#" aria-disabled="true"><i class="bi bi-download me-1"></i> Unduh Hasil</a>
        </div>

        <div id="canvas" class="canvas">
            {{-- Belum ada gambar --}}
            <label id="view-empty" class="dropzone" for="file-input">
                <i class="bi bi-image"></i>
                <div class="fw-semibold mt-2">Klik Unggah Foto atau seret infografis/poster ke sini</div>
                <div class="hint">JPG, PNG, WEBP · maks. {{ round($maxUploadKb / 1024) }} MB</div>
            </label>

            {{-- Sedang diproses --}}
            <div id="view-busy" class="text-center py-5 d-none">
                <span class="spinner-border"></span>
                <div id="busy-text" class="mt-2 hint">Memproses…</div>
            </div>

            {{-- Atur area logo --}}
            <div id="view-edit" class="d-none">
                <div id="stage" class="stage">
                    <img id="preview" class="raw" alt="Pratinjau gambar" draggable="false">
                    <div id="candidates"></div>
                    <div id="selection" class="selection d-none">
                        <div class="ghost"><img id="ghost-logo" src="{{ route('logo') }}" alt=""></div>
                        @foreach (['nw', 'ne', 'se', 'sw'] as $h)
                            <div class="handle" data-h="{{ $h }}"></div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Hasil: sebelum & sesudah --}}
            <div id="view-result" class="d-none">
                <div id="compare" class="compare">
                    <img id="after-img" class="raw" alt="Sesudah">
                    <div class="before"><img id="before-img" class="raw" alt="Sebelum"></div>
                    <div class="divider"></div>
                    <span class="tag" style="left:10px">Sebelum</span>
                    <span class="tag" style="right:10px">Sesudah</span>
                    <input id="compare-range" type="range" min="0" max="100" value="0" aria-label="Geser perbandingan">
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 px-3 py-2">
            <span id="status" class="hint">Logo otomatis diganti dengan logo resmi Pemprov NTT. Seluruh bagian gambar lainnya tetap sama persis.</span>
            <div class="d-flex gap-2 align-items-start flex-wrap ms-auto">
                <button id="btn-edit" class="btn btn-outline-primary btn-sm d-none"><i class="bi bi-pencil me-1"></i> Ubah Area</button>
                <button id="btn-detect" class="btn btn-outline-primary btn-sm d-none"><i class="bi bi-magic me-1"></i> Deteksi Logo</button>
                <button id="btn-replace" class="btn btn-outline-primary btn-sm d-none" disabled><i class="bi bi-arrow-repeat me-1"></i> Ganti Logo</button>
                <details class="pt-1">
                    <summary><i class="bi bi-sliders me-1"></i> Pengaturan</summary>
                    <div class="pt-2" style="width: 240px">
                        <label class="form-label small mb-1" for="removal">Hapus logo lama</label>
                        <select id="removal" class="form-select form-select-sm mb-2">
                            <option value="auto">Otomatis</option>
                            <option value="fill">Isi warna latar</option>
                            <option value="inpaint">Rekonstruksi latar</option>
                            <option value="none">Tempel di atas saja</option>
                        </select>
                        <label class="form-label small mb-0 d-flex justify-content-between" for="scale">Ukuran logo <span id="scale-val">92%</span></label>
                        <input id="scale" type="range" class="form-range" min="30" max="100" value="92">
                    </div>
                </details>
            </div>
        </div>
    </section>

    <footer class="text-center mt-3">
        <a href="https://ihsanmokhsen.com" target="_blank" rel="noopener" class="small text-secondary text-decoration-none">IT-BPAD</a>
    </footer>
</main>

<script>
(() => {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const $ = id => document.getElementById(id);
    const state = { id: null, width: 0, height: 0, box: null, candidates: [], hasResult: false };

    // Satu kartu dengan empat tampilan: kosong, proses, atur area, hasil.
    function show(view, busyText) {
        ['empty', 'busy', 'edit', 'result'].forEach(v => $('view-' + v).classList.toggle('d-none', v !== view));
        $('btn-edit').classList.toggle('d-none', view !== 'result');
        $('btn-detect').classList.toggle('d-none', view !== 'edit');
        $('btn-replace').classList.toggle('d-none', view !== 'edit');
        $('btn-download').classList.toggle('disabled', !state.hasResult || view === 'busy');
        if (busyText) $('busy-text').textContent = busyText;
        if (view === 'edit') render();
    }
    const setStatus = (html, ok = false) => { $('status').innerHTML = html; $('status').className = ok ? 'ok-badge' : 'hint'; };
    const showError = msg => { const a = $('alert'); a.textContent = msg; a.classList.remove('d-none'); };
    const clearError = () => $('alert').classList.add('d-none');

    async function api(url, options = {}) {
        const res = await fetch(url, { ...options, headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', ...(options.headers || {}) } });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            const first = data.errors ? Object.values(data.errors)[0][0] : null;
            throw new Error(first || data.message || 'Terjadi kesalahan pada server.');
        }
        return data;
    }

    function busy(btn, on, label) {
        if (on) { btn.dataset.label = btn.innerHTML; btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> ${label}`; btn.disabled = true; }
        else { btn.innerHTML = btn.dataset.label; btn.disabled = false; }
    }

    // ---------- Unggah ----------
    const card = $('app'), input = $('file-input');
    input.addEventListener('change', () => input.files[0] && upload(input.files[0]));
    ['dragenter', 'dragover'].forEach(e => card.addEventListener(e, ev => { ev.preventDefault(); $('view-empty').classList.add('drag'); }));
    ['dragleave', 'drop'].forEach(e => card.addEventListener(e, ev => { ev.preventDefault(); $('view-empty').classList.remove('drag'); }));
    card.addEventListener('drop', ev => ev.dataTransfer.files[0] && upload(ev.dataTransfer.files[0]));

    async function upload(file) {
        clearError();
        const prevView = state.id ? (state.hasResult ? 'result' : 'edit') : 'empty';
        show('busy', 'Mengunggah…');
        const fd = new FormData(); fd.append('image', file);
        try {
            const data = await api(@json(route('upload')), { method: 'POST', body: fd });
            Object.assign(state, { id: data.id, width: data.width, height: data.height, box: null, candidates: [], hasResult: false });
            $('file-name').textContent = `${data.name} · ${data.width} × ${data.height} px`;
            await new Promise((ok, fail) => { $('preview').onload = ok; $('preview').onerror = fail; $('preview').src = data.url; });

            // Langsung deteksi dan ganti; area manual hanya dibutuhkan jika deteksi gagal.
            show('busy', 'Mendeteksi dan mengganti logo…');
            if (!(await runDetect()) || !(await replaceLogo())) show('edit');
        } catch (e) { showError(e.message); show(prevView); }
        finally { input.value = ''; }
    }

    $('btn-edit').addEventListener('click', () => {
        show('edit');
        setStatus('Seret kotak merah atau sudutnya agar pas dengan logo, lalu klik Ganti Logo.');
    });

    // ---------- Area logo ----------
    const stage = $('stage'), sel = $('selection'), preview = $('preview');
    const ratio = () => preview.clientWidth / state.width;

    function clampBox(b) {
        let { x, y, w, h } = b;
        w = Math.max(4, Math.min(Math.round(w), state.width));
        h = Math.max(4, Math.min(Math.round(h), state.height));
        x = Math.max(0, Math.min(Math.round(x), state.width - w));
        y = Math.max(0, Math.min(Math.round(y), state.height - h));
        return { x, y, w, h };
    }

    function setBox(b) { state.box = b ? clampBox(b) : null; render(); }

    function render() {
        const r = ratio(), b = state.box;
        sel.classList.toggle('d-none', !b);
        $('btn-replace').disabled = !b;
        if (b) Object.assign(sel.style, { left: b.x * r + 'px', top: b.y * r + 'px', width: b.w * r + 'px', height: b.h * r + 'px' });
        renderGhost();
        // Kandidat lain tetap terlihat sebagai alternatif yang bisa diklik.
        $('candidates').innerHTML = state.candidates.map((c, i) => {
            const [x, y, w, h] = c.box;
            return `<div class="candidate" data-i="${i}" title="Pilih kandidat ini" style="left:${x * r}px;top:${y * r}px;width:${w * r}px;height:${h * r}px"></div>`;
        }).join('');
    }

    function renderGhost() {
        const s = $('scale').value / 100;
        sel.querySelector('.ghost').style.padding = `${(1 - s) / 2 * sel.clientHeight}px ${(1 - s) / 2 * sel.clientWidth}px`;
    }

    window.addEventListener('resize', render);
    $('scale').addEventListener('input', e => { $('scale-val').textContent = e.target.value + '%'; renderGhost(); });

    let drag = null;
    const toImg = ev => {
        const rect = preview.getBoundingClientRect(), r = ratio();
        return { x: (ev.clientX - rect.left) / r, y: (ev.clientY - rect.top) / r };
    };

    stage.addEventListener('pointerdown', ev => {
        if (!state.id) return;
        const cand = ev.target.closest('.candidate');
        if (cand && !ev.target.closest('#selection')) { const [x, y, w, h] = state.candidates[+cand.dataset.i].box; setBox({ x, y, w, h }); return; }
        const p = toImg(ev), handle = ev.target.dataset.h;
        if (handle) drag = { mode: 'resize', handle, start: p, box: { ...state.box } };
        else if (ev.target.closest('#selection')) drag = { mode: 'move', start: p, box: { ...state.box } };
        else drag = { mode: 'draw', start: p };
        stage.setPointerCapture(ev.pointerId);
        ev.preventDefault();
    });

    stage.addEventListener('pointermove', ev => {
        if (!drag) return;
        const p = toImg(ev), dx = p.x - drag.start.x, dy = p.y - drag.start.y;
        if (drag.mode === 'draw') {
            if (Math.abs(dx) < 3 && Math.abs(dy) < 3) return;
            const x0 = Math.max(0, Math.min(p.x, drag.start.x)), y0 = Math.max(0, Math.min(p.y, drag.start.y));
            const x1 = Math.min(state.width, Math.max(p.x, drag.start.x)), y1 = Math.min(state.height, Math.max(p.y, drag.start.y));
            setBox({ x: x0, y: y0, w: x1 - x0, h: y1 - y0 });
        } else if (drag.mode === 'move') {
            setBox({ ...drag.box, x: drag.box.x + dx, y: drag.box.y + dy });
        } else {
            let { x, y, w, h } = drag.box; const hd = drag.handle;
            if (hd.includes('e')) w = Math.max(4, w + dx);
            if (hd.includes('s')) h = Math.max(4, h + dy);
            if (hd.includes('w')) { const nx = Math.max(0, Math.min(x + dx, x + w - 4)); w += x - nx; x = nx; }
            if (hd.includes('n')) { const ny = Math.max(0, Math.min(y + dy, y + h - 4)); h += y - ny; y = ny; }
            setBox({ x, y, w: Math.min(w, state.width - x), h: Math.min(h, state.height - y) });
        }
    });
    stage.addEventListener('pointerup', () => drag = null);
    stage.addEventListener('pointercancel', () => drag = null);

    // ---------- Deteksi ----------
    async function runDetect() {
        clearError();
        const btn = $('btn-detect');
        busy(btn, true, 'Mendeteksi…');
        try {
            const data = await api(@json(url('/gambar')) + `/${state.id}/deteksi`, { method: 'POST' });
            state.candidates = data.candidates;
            if (data.candidates.length) {
                const [x, y, w, h] = data.candidates[0].box;
                setBox({ x, y, w, h });
                setStatus(data.candidates.length > 1
                    ? 'Logo terdeteksi. Klik kotak kuning untuk memilih kandidat lain, atau seret untuk menyesuaikan.'
                    : 'Logo terdeteksi. Seret kotak atau sudutnya untuk menyesuaikan.');
                return true;
            }
            render();
            setStatus('Logo tidak terdeteksi. Seret pada gambar untuk menandai logo, lalu klik Ganti Logo.');
        } catch (e) { showError(e.message); }
        finally { busy(btn, false); render(); }
        return false;
    }
    $('btn-detect').addEventListener('click', runDetect);

    // ---------- Ganti ----------
    $('btn-replace').addEventListener('click', replaceLogo);

    async function replaceLogo() {
        if (!state.box) return false;
        clearError();
        const btn = $('btn-replace');
        busy(btn, true, 'Memproses…');
        try {
            const data = await api(@json(url('/gambar')) + `/${state.id}/ganti`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...state.box, removal: $('removal').value, scale: $('scale').value / 100, align: 'center' }),
            });
            $('before-img').src = preview.src;
            $('after-img').src = data.url;
            $('btn-download').href = data.download;
            state.hasResult = true;
            setStatus('<i class="bi bi-check-circle-fill me-1"></i> Logo diganti · desain lain tidak berubah. Geser gambar untuk membandingkan.', true);
            // Mulai dengan hasil penuh; geser ke kanan untuk melihat gambar asli.
            $('compare-range').value = 0;
            $('compare-range').dispatchEvent(new Event('input'));
            show('result');
            return true;
        } catch (e) { showError(e.message); }
        finally { busy(btn, false); }
    }

    $('compare-range').addEventListener('input', e => {
        const v = e.target.value;
        document.querySelector('#compare .before').style.clipPath = `inset(0 ${100 - v}% 0 0)`;
        document.querySelector('#compare .divider').style.left = v + '%';
    });
})();
</script>
</body>
</html>
