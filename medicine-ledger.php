<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/middleware/auth.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Medicine Ledger · Optms Rx</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    /* ---- colorful matte square badges ---- */
    .ml-bdg { display:inline-flex; align-items:center; gap:5px; padding:3px 8px; border-radius:4px;
              font-size:11.5px; font-weight:700; letter-spacing:.02em; line-height:1.45;
              border:1px solid; white-space:nowrap; }
    .ml-bdg i { font-size:11px; }
    .ml-bdg.teal   { background:#D9F0ED; color:#0F766E; border-color:#B5E1DC; }
    .ml-bdg.blue   { background:#DDEBFA; color:#1D5FA8; border-color:#BED9F3; }
    .ml-bdg.amber  { background:#F9EDD3; color:#9A6206; border-color:#F0DCAC; }
    .ml-bdg.orange { background:#FBE7D9; color:#B4451C; border-color:#F4CFB6; }
    .ml-bdg.purple { background:#EBE4F9; color:#6D3FC0; border-color:#D8C9F1; }
    .ml-bdg.red    { background:#F9E0E0; color:#B4352F; border-color:#F2C2C2; }
    .ml-bdg.green  { background:#DCF0E2; color:#1E7A44; border-color:#BEE2CA; }
    .ml-bdg.pink   { background:#F8E0EC; color:#B03070; border-color:#EFBFD8; }
    .ml-bdg.indigo { background:#E2E6FA; color:#4349B3; border-color:#C8CFF2; }
    .ml-bdg.cyan   { background:#DCF0F6; color:#14708E; border-color:#BDE0EC; }
    .ml-bdg.slate  { background:#E9EDF1; color:#4B5563; border-color:#D5DCE3; }

    /* ---- search ---- */
    .ml-search-wrap { position:relative; }
    .ml-search-wrap > i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--mf-text-2); font-size:15px; }
    .ml-search { padding-left:38px !important; }
    .ml-hits { position:absolute; inset-inline:0; top:calc(100% + 6px); z-index:40; background:#fff;
               border:1px solid #E4EBF4; border-radius:10px; box-shadow:0 14px 34px -16px rgba(22,50,92,.28); overflow:hidden; }
    .ml-hit { display:flex; align-items:center; gap:10px; padding:9px 14px; cursor:pointer; border-bottom:1px solid #F0F4F9; }
    .ml-hit:last-child { border-bottom:0; }
    .ml-hit:hover, .ml-hit.active { background:#F4F9FA; }
    .ml-hit .nm { font-weight:600; }
    .ml-hit .mt { font-size:11.5px; color:var(--mf-text-2); }

    /* ---- header badges ---- */
    .ml-head { display:flex; flex-wrap:wrap; align-items:center; gap:12px; }

    /* ---- batch chips: matte square, one per batch ---- */
    .ml-chips { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .ml-chip { display:inline-flex; align-items:center; gap:7px; padding:5px 10px; border-radius:4px;
               font-size:12px; font-weight:700; letter-spacing:.01em; border:1.5px solid; cursor:pointer;
               background:#fff; transition:transform .08s ease, box-shadow .12s ease; }
    .ml-chip small { font-weight:600; opacity:.78; font-size:11px; }
    .ml-chip:hover { transform:translateY(-1px); }
    .ml-chip.on { box-shadow:0 0 0 2px #fff inset, 0 0 0 2.5px currentColor; }
    .ml-chip.teal   { background:#D9F0ED; color:#0F766E; border-color:#B5E1DC; }
    .ml-chip.blue   { background:#DDEBFA; color:#1D5FA8; border-color:#BED9F3; }
    .ml-chip.amber  { background:#F9EDD3; color:#9A6206; border-color:#F0DCAC; }
    .ml-chip.orange { background:#FBE7D9; color:#B4451C; border-color:#F4CFB6; }
    .ml-chip.purple { background:#EBE4F9; color:#6D3FC0; border-color:#D8C9F1; }
    .ml-chip.red    { background:#F9E0E0; color:#B4352F; border-color:#F2C2C2; }
    .ml-chip.green  { background:#DCF0E2; color:#1E7A44; border-color:#BEE2CA; }
    .ml-chip.pink   { background:#F8E0EC; color:#B03070; border-color:#EFBFD8; }
    .ml-chip.indigo { background:#E2E6FA; color:#4349B3; border-color:#C8CFF2; }
    .ml-chip.cyan   { background:#DCF0F6; color:#14708E; border-color:#BDE0EC; }
    .ml-chip.slate  { background:#E9EDF1; color:#4B5563; border-color:#D5DCE3; }

    /* ---- type tabs ---- */
    .ml-tabs { display:flex; flex-wrap:wrap; gap:6px; }
    .ml-tab { border:1px solid #E1E8F2; background:#fff; color:var(--mf-text-2); border-radius:4px;
              padding:5px 11px; font-size:12.5px; font-weight:700; cursor:pointer; }
    .ml-tab .cnt { font-size:11px; font-weight:800; background:#EEF2F7; border-radius:3px; padding:1px 6px; margin-left:6px; color:var(--mf-text-2); }
    .ml-tab:hover { border-color:#BFD4D2; color:var(--mf-primary-dark); }
    .ml-tab.on { background:var(--mf-primary); border-color:var(--mf-primary); color:#fff; }
    .ml-tab.on .cnt { background:rgba(255,255,255,.22); color:#fff; }

    /* ---- loading bar while a request waits ---- */
    .ml-load { height:3px; border-radius:2px; overflow:hidden; background:#E1E8F2; visibility:hidden; }
    .ml-load.on { visibility:visible; }
    .ml-load::after { content:''; display:block; height:100%; width:38%; border-radius:2px;
                      background:var(--mf-primary); animation:mlload 1s ease-in-out infinite; }
    @keyframes mlload { 0%{margin-left:-38%} 100%{margin-left:100%} }

    .ml-view-eye { line-height:1; padding:4px 8px; }
    .ml-def dt { font-size:11.5px; font-weight:600; color:var(--mf-text-2); margin:0; }
    .ml-def dd { margin:0; font-weight:600; }
    .ml-def > div { display:flex; justify-content:space-between; align-items:center; gap:14px; padding:7px 0; border-bottom:1px dashed #E7EDF4; }
    .ml-def > div:last-child { border-bottom:0; }
    .num { font-variant-numeric: tabular-nums; }
  </style>
</head>
<body data-page="medicine-ledger">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">
        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-journal-text me-2 text-primary"></i>Medicine Ledger</h1>
            <p class="page-sub">Every movement of one medicine — purchases, sales, returns and adjustments — with a running balance.</p>
          </div>
        </div>

        <!-- search -->
        <div class="card-mf mb-3">
          <div class="p-3">
            <div class="ml-search-wrap">
              <i class="bi bi-search"></i>
              <input id="mlSearch" class="form-control form-control-mf ml-search" autocomplete="off"
                     placeholder="Search by medicine name, brand, or batch no…">
              <div class="ml-hits d-none" id="mlHits"></div>
            </div>
            <div class="text-2 small-xs mt-2">Pick a medicine to open its full stock-movement ledger.</div>
          </div>
          <div class="ml-load" id="mlLoad"></div>
        </div>

        <!-- empty state -->
        <div class="card-mf" id="mlEmpty">
          <div class="empty-state py-5">
            <i class="bi bi-journal-medical" style="font-size:36px"></i>
            <div class="fw-semibold mt-2">Search a medicine to see its ledger</div>
            <div class="text-2 small">Purchases in · sales out · returns · adjustments — one line each, with balance after every entry.</div>
          </div>
        </div>

        <!-- ledger -->
        <div id="mlMain" class="d-none">
          <div class="card-mf mb-3">
            <div class="p-3">
              <div class="ml-head">
                <div class="me-auto">
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h5 class="mb-0 fw-bold" id="mlName"></h5>
                    <span class="text-2 small" id="mlUnit"></span>
                  </div>
                  <div class="text-2 small" id="mlBrand"></div>
                </div>
                <span id="mlBatchesBdg"></span>
                <span id="mlExpiryBdg"></span>
                <span id="mlStockBdg"></span>
              </div>
            </div>
          </div>

          <div class="row g-3 mb-3" id="mlKpis"></div>

          <div class="card-mf mb-3">
            <div class="card-head">
              <strong>Batches</strong>
              <span class="text-2 small ms-auto">Click a batch to filter the ledger</span>
            </div>
            <div class="p-3"><div class="ml-chips" id="mlChips"></div></div>
          </div>

          <div class="card-mf">
            <div class="card-head flex-wrap gap-2">
              <strong>Movement ledger</strong>
              <div class="ml-tabs ms-auto" id="mlTabs"></div>
              <select id="mlPeriod" class="form-select form-select-mf form-select-sm" style="width:auto">
                <option value="all">All time</option>
                <option value="30">Last 30 days</option>
                <option value="90">Last 90 days</option>
                <option value="180">Last 180 days</option>
                <option value="365">Last 365 days</option>
              </select>
            </div>
            <div class="table-responsive">
              <table class="table table-mf align-middle">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Batch</th>
                    <th>Reference</th>
                    <th class="text-end">In</th>
                    <th class="text-end">Out</th>
                    <th class="text-end">Balance</th>
                    <th class="text-end">Value</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody id="mlRows"></tbody>
              </table>
            </div>
            <div class="px-3 py-2 border-top text-2 small" id="mlFoot"></div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- view popup -->
  <div class="modal fade" id="mlView" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="mlViewTitle"></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body"><dl class="ml-def mb-0" id="mlViewBody"></dl></div>
        <div class="modal-footer">
          <a class="btn btn-mf btn-sm" id="mlViewSrc" target="_blank" rel="noopener">
            <i class="bi bi-box-arrow-up-right me-1"></i>Open in source document
          </a>
          <button type="button" class="btn btn-mf-outline btn-sm" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/app.js"></script>
  <script>
    (function () {
      const MF = window.MF, D = window.MF_DATA;
      const $ = (s) => document.querySelector(s);
      const esc = MF.esc;

      /* matte square tones — batch colour is stable per batch no */
      const TONES = ['teal', 'blue', 'amber', 'orange', 'purple', 'pink', 'green', 'indigo', 'cyan', 'red', 'slate'];
      const toneOf = (str) => {
        let h = 0;
        for (const c of String(str || '—')) h = (h * 31 + c.charCodeAt(0)) >>> 0;
        return TONES[h % TONES.length];
      };

      const KIND = {
        purchase:        { label: 'Purchase',         tone: 'teal',   icon: 'arrow-down-left',       tab: 'purchases' },
        sale:            { label: 'Sale',             tone: 'blue',   icon: 'arrow-up-right',        tab: 'sales' },
        sale_return:     { label: 'Sale return',      tone: 'amber',  icon: 'arrow-counterclockwise', tab: 'returns' },
        purchase_return: { label: 'Purchase return',  tone: 'orange', icon: 'box-arrow-left',        tab: 'returns' },
        adjustment:      { label: 'Adjustment',       tone: 'purple', icon: 'sliders',               tab: 'adjustments' }
      };

      const state = { med: null, kpis: null, batches: [], entries: [], tab: 'all', period: 'all', batch: 'all' };
      let viewModal = null;

      /* ---------------- demo data (backend off) ---------------- */
      function demoSearch(q) {
        const ql = q.toLowerCase();
        return (D.medicines || []).filter((m) => {
          const batches = MF.batchesOf(m.id);
          return String(m.name || '').toLowerCase().includes(ql)
              || String(m.brandRef || m.brand_name || '').toLowerCase().includes(ql)
              || batches.some((b) => String(b.batchNo || b.batch_no || '').toLowerCase().includes(ql));
        }).slice(0, 12).map((m) => ({
          id: m.id, name: m.name, brand: m.brandRef || m.brand_name || '', unit: m.unit || '',
          mrp: Number(m.mrp) || 0,
          batchCount: MF.batchesOf(m.id).length,
          stock: MF.stockOf(m.id),
          nextExpiry: nextExpiryOf(m.id)
        }));
      }
      function nextExpiryOf(medId) {
        const list = MF.batchesOf(medId)
          .filter((b) => (Number(b.qty) || Number(b.quantity) || 0) > 0 && (b.expiry || b.expiry_date))
          .map((b) => String(b.expiry || b.expiry_date).slice(0, 10)).sort();
        return list[0] || null;
      }
      function demoLedger(medId) {
        const m = MF.med(medId) || {};
        const medName = String(m.name || '');
        const same = (row) => {
          const id = row.medId || row.medicineId || row.medicine_id;
          if (id != null && id !== '') return String(id) === String(medId);
          const nm = row.medicine_name || row.name || row.medicine || '';
          return nm !== '' && nm === medName;
        };
        const entries = [];
        (D.purchases || D.purchaseInvoices || []).forEach((inv) => {
          (inv.items || inv.lines || []).forEach((it) => {
            if (!same(it)) return;
            const qty = Math.abs(Number(it.qty) || 0) + Math.abs(Number(it.freeQty ?? it.free_qty) || 0);
            const ref = inv.invoice_no || inv.no || '';
            entries.push({ kind: 'purchase', date: inv.invoice_date || inv.date || '', ref, party: inv.supplier_name || inv.supplier || '',
              batch: it.batch_no || it.batchNo || '', in: qty, out: 0, rate: Number(it.rate) || 0,
              value: Number(it.amount) || qty * (Number(it.rate) || 0), note: '', src: 'purchase-invoices.php?invoice=' + encodeURIComponent(ref) });
          });
        });
        (D.sales || D.salesInvoices || []).forEach((inv) => {
          (inv.items || inv.lines || []).forEach((it) => {
            if (!same(it)) return;
            const qty = Math.abs(Number(it.qty) || 0);
            const ref = inv.invoice_no || inv.no || '';
            entries.push({ kind: 'sale', date: inv.sale_date || inv.date || '', ref, party: inv.customer_name || inv.customer || 'Walk-in',
              batch: it.batch_no || it.batchNo || '', in: 0, out: qty, rate: Number(it.rate) || 0,
              value: Number(it.amount) || qty * (Number(it.rate) || 0), note: '', src: 'sales-invoices.php?invoice=' + encodeURIComponent(ref) });
          });
        });
        const pushReturns = (arr, kind, dateKeys, srcPage) => (arr || []).forEach((ret) => {
          (ret.items || ret.lines || []).forEach((it) => {
            if (!same(it)) return;
            const qty = Math.abs(Number(it.qty) || 0);
            const ref = ret.return_no || ret.no || '';
            entries.push({ kind, date: ret.return_date || dateKeys.map((k) => ret[k]).find(Boolean) || '', ref,
              party: ret.customer_name || ret.supplier_name || ret.party || '',
              batch: it.batch_no || it.batchNo || '', in: kind === 'sale_return' ? qty : 0, out: kind === 'purchase_return' ? qty : 0,
              rate: Number(it.rate) || 0, value: Number(it.amount) || qty * (Number(it.rate) || 0),
              note: ret.reason || '', src: srcPage + '?ref=' + encodeURIComponent(ref) });
          });
        });
        pushReturns(D.salesReturns, 'sale_return', ['date'], 'sales-return.php');
        pushReturns(D.purchaseReturns, 'purchase_return', ['date'], 'purchase-return.php');
        (D.stockAdjustments || D.adjustments || []).forEach((a, i) => {
          if (!same(a)) return;
          const d = Number(a.qtyChange ?? a.qty_change) || 0;
          entries.push({ kind: 'adjustment', date: String(a.created_at || a.date || '').slice(0, 10), ref: a.ref || ('ADJ-' + (i + 1)),
            party: a.adjustedBy || a.adjusted_by || '', batch: a.batchNo || a.batch_no || '',
            in: Math.max(d, 0), out: Math.max(-d, 0), rate: 0, value: 0, note: a.reason || a.notes || '', src: 'stock-adjustment.php' });
        });
        // chronological running balance, then newest first
        entries.sort((a, b) => String(a.date).localeCompare(String(b.date)) || String(a.ref).localeCompare(String(b.ref)));
        let bal = 0;
        entries.forEach((e) => { bal += e.in - e.out; e.balance = bal; });
        entries.reverse();

        const batches = MF.batchesOf(medId).map((b) => ({
          id: b.id, batchNo: b.batchNo || b.batch_no || '', purchaseDate: b.purchaseDate || b.purchase_date || null,
          expiry: b.expiry || b.expiry_date || null, qty: Number(b.qty) || Number(b.quantity) || 0,
          purchaseRate: Number(b.purchaseRate) || Number(b.purchase_rate) || 0, mrp: Number(b.mrp) || 0
        }));
        const stock = batches.reduce((s, b) => s + b.qty, 0);
        const cutoff = MF.today(); const d30 = new Date(); d30.setDate(d30.getDate() - 30);
        const sold30 = entries.filter((e) => e.kind === 'sale' && e.date >= d30.toISOString().slice(0, 10) && e.date <= cutoff)
          .reduce((s, e) => s + e.out, 0);
        const lastP = entries.filter((e) => e.kind === 'purchase')[0] || null;
        return {
          medicine: {
            id: m.id, name: m.name || '', brand: m.brandRef || m.brand_name || '', unit: m.unit || '', mrp: Number(m.mrp) || 0,
            minStock: Number(m.minStock) || 0, reorderLevel: Number(m.reorderLevel) || 0,
            batchCount: batches.length, stock,
            stockValue: batches.reduce((s, b) => s + b.qty * b.purchaseRate, 0),
            nextExpiry: nextExpiryOf(medId)
          },
          kpis: {
            currentStock: stock, sold30,
            lastPurchase: lastP ? { date: lastP.date, ref: lastP.ref, qty: lastP.in, rate: lastP.rate } : null,
            stockValue: batches.reduce((s, b) => s + b.qty * b.purchaseRate, 0)
          },
          batches, entries
        };
      }

      /* ---------------- api ---------------- */
      async function apiSearch(q) {
        if (MF.Api.live) {
          const res = await MF.Api.get('medicine-ledger.php?q=' + encodeURIComponent(q));
          return Array.isArray(res.data) ? res.data : [];
        }
        return demoSearch(q);
      }
      async function apiLedger(id) {
        if (MF.Api.live) {
          const res = await MF.Api.get('medicine-ledger.php?medicineId=' + encodeURIComponent(id));
          return res.data;
        }
        return demoLedger(id);
      }

      /* ---------------- badges ---------------- */
      const bdg = (tone, icon, text) => `<span class="ml-bdg ${tone}">${icon ? `<i class="bi bi-${icon}"></i>` : ''}${esc(text)}</span>`;
      const typeBadge = (e) => { const k = KIND[e.kind] || KIND.adjustment; return bdg(k.tone, k.icon, k.label); };
      const batchBadge = (no) => no ? bdg(toneOf(no), 'upc', no) : '<span class="text-2">—</span>';

      function expiryBadge(iso) {
        if (!iso) return bdg('slate', 'calendar2-x', 'No expiry on hand');
        const days = MF.daysTo(String(iso).slice(0, 10));
        const txt = 'Exp: ' + MF.fmtMonthYear(String(iso).slice(0, 10)) + ' · ' + (days < 0 ? 'expired' : days + 'd');
        if (days < 0 || days < 30) return bdg('red', 'calendar2-x', txt);
        if (days < 90) return bdg('orange', 'calendar2-week', txt);
        if (days < 180) return bdg('amber', 'calendar2-week', txt);
        return bdg('green', 'calendar2-check', txt);
      }
      function stockBadge(med) {
        const st = Number(med.stock) || 0;
        if (st <= 0) return bdg('red', 'x-circle', 'Out of stock');
        if (st <= Math.max(Number(med.reorderLevel) || 0, Number(med.minStock) || 0)) return bdg('amber', 'exclamation-triangle', 'Low stock');
        return bdg('green', 'check-circle', 'In stock');
      }

      /* ---------------- render ---------------- */
      function renderHits(hits) {
        const box = $('#mlHits');
        if (!hits || !hits.length) { box.classList.add('d-none'); box.innerHTML = ''; return; }
        box.innerHTML = hits.map((h) => `
          <div class="ml-hit" data-id="${esc(h.id)}" data-name="${esc(h.name)}">
            <i class="bi bi-capsule text-primary"></i>
            <div class="me-auto">
              <div class="nm">${esc(h.name)}</div>
              <div class="mt">${esc([h.brand, h.unit].filter(Boolean).join(' · '))}</div>
            </div>
            ${bdg('slate', 'collection', (h.batchCount || 0) + ' batch' + ((h.batchCount || 0) === 1 ? '' : 'es'))}
            ${bdg((h.stock || 0) > 0 ? 'teal' : 'red', 'box-seam', MF.num(h.stock || 0))}
          </div>`).join('');
        box.classList.remove('d-none');
        box.querySelectorAll('.ml-hit').forEach((el) => el.addEventListener('mousedown', (ev) => {
          ev.preventDefault();
          box.classList.add('d-none');
          $('#mlSearch').value = el.dataset.name;
          selectMed(el.dataset.id);
        }));
      }

      function renderHeader() {
        const m = state.med;
        $('#mlName').textContent = m.name;
        $('#mlUnit').textContent = m.unit ? '· ' + m.unit : '';
        $('#mlBrand').textContent = [m.brand, m.mrp ? 'MRP ' + MF.fmt(m.mrp, 2) : ''].filter(Boolean).join(' · ');
        $('#mlBatchesBdg').innerHTML = bdg('indigo', 'collection', m.batchCount + ' batch' + (m.batchCount === 1 ? '' : 'es'));
        $('#mlExpiryBdg').innerHTML = expiryBadge(m.nextExpiry);
        $('#mlStockBdg').innerHTML = stockBadge(m);
      }

      function renderKpis() {
        const k = state.kpis || {};
        const lp = k.lastPurchase;
        const cards = [
          { label: 'Current stock', value: MF.num(k.currentStock || 0), sub: (state.med.unit || 'units'), icon: 'box-seam', tone: 'info' },
          { label: 'Sold · last 30 days', value: MF.num(k.sold30 || 0), sub: 'units out', icon: 'cart-check', tone: 'primary' },
          { label: 'Last purchase', value: lp ? MF.fmtDate(lp.date) : '—', sub: lp ? (lp.ref + ' · ' + MF.num(lp.qty) + ' @ ' + MF.fmt(lp.rate, 2)) : 'no purchase yet', icon: 'bag-plus', tone: 'success' },
          { label: 'Stock value @ cost', value: MF.fmt(k.stockValue || 0), sub: 'purchase rate × on hand', icon: 'currency-rupee', tone: 'warning' }
        ];
        $('#mlKpis').innerHTML = cards.map((c) => `
          <div class="col-6 col-md-3">
            <div class="card-mf kpi-card">
              <div class="kpi-icon tone-${c.tone}"><i class="bi bi-${c.icon}"></i></div>
              <div>
                <div class="kpi-label">${c.label}</div>
                <div class="kpi-value num" style="font-size:${String(c.value).length > 10 ? '1.02rem' : '1.25rem'}">${esc(c.value)}</div>
                <div class="text-2 small-xs">${esc(c.sub)}</div>
              </div>
            </div>
          </div>`).join('');
      }

      function renderChips() {
        const chips = [];
        chips.push(`<button type="button" class="ml-chip slate${state.batch === 'all' ? ' on' : ''}" data-batch="all">
          <i class="bi bi-layers"></i>All batches</button>`);
        state.batches.forEach((b) => {
          const tone = toneOf(b.batchNo);
          const expTxt = b.expiry ? 'Exp ' + MF.fmtMonthYear(String(b.expiry).slice(0, 10)) : 'no expiry';
          chips.push(`<button type="button" class="ml-chip ${tone}${String(state.batch) === String(b.batchNo) ? ' on' : ''}" data-batch="${esc(b.batchNo)}">
            <i class="bi bi-upc"></i>${esc(b.batchNo)}<small>${MF.num(b.qty)} · ${esc(expTxt)}</small></button>`);
        });
        $('#mlChips').innerHTML = chips.join('');
        $('#mlChips').querySelectorAll('.ml-chip').forEach((el) => el.addEventListener('click', () => {
          state.batch = el.dataset.batch;
          renderChips(); renderTabs(); renderTable();
        }));
      }

      const TABS = [['all', 'All'], ['purchases', 'Purchases'], ['sales', 'Sales'], ['returns', 'Returns'], ['adjustments', 'Adjustments']];
      function periodCut() {
        if (state.period === 'all') return null;
        const d = new Date(); d.setDate(d.getDate() - Number(state.period));
        return d.toISOString().slice(0, 10);
      }
      function baseFiltered() {
        const cut = periodCut();
        return state.entries.filter((e) =>
          (state.batch === 'all' || String(e.batch) === String(state.batch)) &&
          (!cut || e.date >= cut));
      }
      function renderTabs() {
        const base = baseFiltered();
        const counts = { all: base.length, purchases: 0, sales: 0, returns: 0, adjustments: 0 };
        base.forEach((e) => { counts[(KIND[e.kind] || KIND.adjustment).tab] += 1; });
        $('#mlTabs').innerHTML = TABS.map(([id, label]) =>
          `<button type="button" class="ml-tab${state.tab === id ? ' on' : ''}" data-tab="${id}">${label}<span class="cnt">${counts[id]}</span></button>`).join('');
        $('#mlTabs').querySelectorAll('.ml-tab').forEach((el) => el.addEventListener('click', () => {
          state.tab = el.dataset.tab; renderTabs(); renderTable();
        }));
      }

      function renderTable() {
        const rows = baseFiltered().filter((e) => state.tab === 'all' || (KIND[e.kind] || KIND.adjustment).tab === state.tab);
        $('#mlRows').innerHTML = rows.length ? rows.map((e, i) => `
          <tr>
            <td class="num">${MF.fmtDate(e.date)}</td>
            <td>${typeBadge(e)}</td>
            <td>${batchBadge(e.batch)}</td>
            <td>
              <div class="fw-semibold">${esc(e.ref || '—')}</div>
              ${e.party ? `<div class="text-2 small-xs">${esc(e.party)}</div>` : ''}
            </td>
            <td class="text-end num ${e.in ? 'fw-semibold text-success' : 'text-2'}">${e.in ? '+' + MF.num(e.in) : '—'}</td>
            <td class="text-end num ${e.out ? 'fw-semibold text-danger' : 'text-2'}">${e.out ? '−' + MF.num(e.out) : '—'}</td>
            <td class="text-end num fw-semibold">${MF.num(e.balance)}</td>
            <td class="text-end num">${MF.fmt(e.value, 2)}</td>
            <td class="text-end"><button type="button" class="btn btn-mf-soft btn-sm ml-view-eye" data-i="${i}" title="View detail"><i class="bi bi-eye"></i></button></td>
          </tr>`).join('')
          : `<tr><td colspan="9"><div class="empty-state"><i class="bi bi-clipboard-x"></i>No ${state.tab === 'all' ? 'movements' : state.tab} in this period${state.batch === 'all' ? '' : ' for batch ' + esc(state.batch)}.</div></td></tr>`;
        const totalIn = rows.reduce((s, e) => s + e.in, 0);
        const totalOut = rows.reduce((s, e) => s + e.out, 0);
        $('#mlFoot').innerHTML = rows.length
          ? `${rows.length} ${rows.length === 1 ? 'entry' : 'entries'} · in <strong class="text-success">${MF.num(totalIn)}</strong> · out <strong class="text-danger">${MF.num(totalOut)}</strong> · net <strong>${MF.num(totalIn - totalOut)}</strong>`
          : '';
        $('#mlRows').querySelectorAll('.ml-view-eye').forEach((btn) => btn.addEventListener('click', () => openView(rows[Number(btn.dataset.i)])));
      }

      function openView(e) {
        if (!e) return;
        $('#mlViewTitle').textContent = e.ref || 'Movement detail';
        const qty = e.in ? { label: 'Quantity in', val: '+' + MF.num(e.in) } : { label: 'Quantity out', val: '−' + MF.num(e.out) };
        const rows = [
          ['Reference', `<span class="fw-bold">${esc(e.ref || '—')}</span>`],
          ['Date', `<span class="num">${MF.fmtDate(e.date)}</span>`],
          ['Type', typeBadge(e)],
          ['Batch', batchBadge(e.batch)],
          [qty.label, `<span class="num fw-bold ${e.in ? 'text-success' : 'text-danger'}">${qty.val}</span>`],
          ['Rate', `<span class="num">${MF.fmt(e.rate, 2)}</span>`],
          ['Value', `<span class="num fw-bold">${MF.fmt(e.value, 2)}</span>`],
          ['Balance after', `<span class="num">${MF.num(e.balance)}</span>`]
        ];
        if (e.party) rows.splice(3, 0, [e.kind === 'sale' || e.kind === 'sale_return' ? 'Customer' : e.kind === 'adjustment' ? 'Adjusted by' : 'Supplier', esc(e.party)]);
        rows.push(['Note', e.note ? esc(e.note) : '<span class="text-2">—</span>']);
        $('#mlViewBody').innerHTML = rows.map(([dt, dd]) => `<div><dt>${dt}</dt><dd>${dd}</dd></div>`).join('');
        const src = $('#mlViewSrc');
        if (e.src && e.src !== '#') { src.href = e.src; src.classList.remove('d-none'); }
        else src.classList.add('d-none');
        viewModal.show();
      }

      /* ---------------- flow ---------------- */
      async function selectMed(id) {
        $('#mlLoad').classList.add('on');
        try {
          const data = await apiLedger(id);
          if (!data || !data.medicine) throw new Error('No ledger data');
          state.med = data.medicine;
          state.kpis = data.kpis || {};
          state.batches = Array.isArray(data.batches) ? data.batches : [];
          state.entries = (Array.isArray(data.entries) ? data.entries : []).map((e) => ({
            kind: e.kind, date: e.date || '', ref: e.ref || '', party: e.party || '', batch: e.batch || '',
            in: Number(e.in) || 0, out: Number(e.out) || 0, balance: Number(e.balance) || 0,
            rate: Number(e.rate) || 0, value: Number(e.value) || 0, note: e.note || '', src: e.src || '#'
          }));
          state.tab = 'all'; state.batch = 'all';
          $('#mlPeriod').value = 'all';
          $('#mlEmpty').classList.add('d-none');
          $('#mlMain').classList.remove('d-none');
          renderHeader(); renderKpis(); renderChips(); renderTabs(); renderTable();
          $('#mlMain').scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (err) {
          MF.toast(err.message || 'Could not load the ledger', 'err', 'Medicine ledger');
        } finally {
          $('#mlLoad').classList.remove('on');
        }
      }

      document.addEventListener('DOMContentLoaded', async () => {
        await MF.boot();
        viewModal = new bootstrap.Modal($('#mlView'));

        let timer = null;
        const box = $('#mlSearch');
        box.addEventListener('input', () => {
          clearTimeout(timer);
          const q = box.value.trim();
          if (q.length < 2) { $('#mlHits').classList.add('d-none'); return; }
          timer = setTimeout(async () => {
            try { renderHits(await apiSearch(q)); }
            catch (err) { MF.toast(err.message || 'Search failed', 'err', 'Medicine ledger'); }
          }, 220);
        });
        box.addEventListener('focus', () => { if ($('#mlHits').innerHTML) $('#mlHits').classList.remove('d-none'); });
        box.addEventListener('blur', () => setTimeout(() => $('#mlHits').classList.add('d-none'), 150));
        box.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') $('#mlHits').classList.add('d-none'); });

        $('#mlPeriod').addEventListener('change', (ev) => { state.period = ev.target.value; renderTabs(); renderTable(); });

        // deep link: medicine-ledger.php?medicineId=5
        const pre = new URLSearchParams(location.search).get('medicineId');
        if (pre) selectMed(pre);
      });
    })();
  </script>
</body>
</html>
