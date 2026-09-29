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
  <title>Schedule / Class · Optms Rx</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .mf-stat { background:#f8fafc; border:1px solid #e7edf4; border-radius:14px; padding:12px 14px; }
    .mf-stat span { display:block; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#6c757d; }
    .mf-stat strong { display:block; margin-top:3px; font-size:1.2rem; font-weight:750; letter-spacing:-.02em; color:#1b2430; }
    .mf-stat.accent { background:#e8eef8; border-color:#d7e2f2; }
    .mf-stat.rx { background:#f7f4ff; border-color:#e6defa; }
    .mf-stat.class { background:#e8f4f1; border-color:#d3e8e2; }
    .mf-kebab { width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; padding:0; }
    .mf-act-menu { min-width:196px; padding:6px; border:1px solid #e7edf4; border-radius:12px; box-shadow:0 12px 32px rgba(16,32,64,.14); z-index:1080; }
    .mf-act-menu .dropdown-item { display:flex; align-items:center; gap:10px; font-size:.84rem; font-weight:600; border-radius:8px; padding:.48rem .65rem; }
    .mf-act-menu .dropdown-item i { width:1.05rem; color:#16325c; }
    .mf-act-menu .dropdown-item:hover { background:#f4f7fb; }
    .mf-act-menu .dropdown-item.text-danger i { color:inherit; }
    .mf-name { font-weight:700; color:#1b2430; }
    .mf-sub { color:#8b9bb0; font-size:.72rem; font-weight:600; margin-top:1px; }
    .mf-ledger { border:1px solid #e3ebf4; border-radius:16px; background:#fff; overflow:hidden; box-shadow:0 1px 2px rgba(22,50,92,.04), 0 10px 28px rgba(22,50,92,.04); }
    .mf-ledger-search { display:flex; align-items:center; gap:10px; padding:12px 16px; border-bottom:1px solid #e7eef6; background:#fff; flex-wrap:wrap; }
    .mf-ledger-search > i { color:#8b9bb0; font-size:15px; }
    .mf-ledger-search input { border:0; outline:0; box-shadow:none !important; background:transparent; flex:1; min-width:180px; padding:4px 0; font-size:.92rem; color:#1b2430; }
    .mf-ledger-search input::placeholder { color:#9aa8b8; }
    .mf-ledger .table-mf { margin:0; }
    .mf-ledger .table-mf thead th { background:#f7f9fc; color:#7b8798; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; border-bottom:1px solid #e7eef6; padding:12px 14px; white-space:nowrap; }
    .mf-ledger .table-mf tbody td { border-bottom:1px solid #f0f4f8; padding:13px 14px; vertical-align:middle; }
    .mf-ledger .table-mf tbody tr:last-child td { border-bottom:0; }
    .mf-ledger .table-mf tbody tr:hover td { background:#f8fbfe; }
    .mf-ledger-foot { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:12px 16px; border-top:1px solid #e3ebf4; background:#fff; }
    .mf-ledger-foot .pagination { gap:4px; }
    .mf-ledger-foot .page-link { border:1px solid #e3ebf4; color:#516278; border-radius:8px; min-width:32px; text-align:center; font-weight:650; padding:.3rem .55rem; }
    .mf-ledger-foot .page-item.active .page-link { background:#16325c; border-color:#16325c; color:#fff; }
    .mf-ledger-foot .page-link:hover { background:#f4f7fb; color:#16325c; }
    .mf-ledger-note { color:#8b9bb0; font-size:.78rem; line-height:1.45; padding:10px 16px 12px; margin:0; border-top:1px solid #eef3f8; background:#fbfcfe; }
    .sc-filters { display:flex; gap:6px; flex-wrap:wrap; }
    .sc-filter { border:1px solid #d7ebe6; background:#fff; color:#516278; border-radius:999px; font-size:.75rem; font-weight:700; padding:4px 10px; }
    .sc-filter.is-on { background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-color:var(--mf-primary); }
    .sc-row { display:flex; align-items:center; gap:10px; min-width:220px; }
    .sc-code { display:inline-flex; align-items:center; justify-content:center; min-width:42px; height:28px; border-radius:8px; padding:0 8px; font-size:.72rem; font-weight:800; letter-spacing:.02em; }
    .sc-code.otc { background:#E7F6EE; color:#157347; }
    .sc-code.h { background:#FEF3E2; color:#B45309; }
    .sc-code.h1 { background:#FFE1CC; color:#C2410C; }
    .sc-code.x, .sc-code.ndps { background:#FDE2E2; color:#B42318; }
    .sc-code.g { background:#E0F2FE; color:#0369A1; }
    .sc-code.class { background:#E6F1EE; color:#0F4D42; }
    .sc-kind { display:inline-flex; align-items:center; border-radius:4px; padding:2px 7px; font-size:.72rem; font-weight:700; }
    .sc-kind.schedule { background:#F1EBFC; color:#6D28D9; }
    .sc-kind.class { background:#E6F1EE; color:#0F4D42; }
    .sc-rule { font-size:.75rem; font-weight:700; }
    .sc-rule.rx { color:#6D28D9; }
    .sc-rule.reg { color:#B42318; }
    .sc-rule.open { color:#157347; }
  </style>
</head>
<body data-page="schedules">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">
        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-shield-check me-2 text-success"></i>Schedule / Class</h1>
            <p class="page-sub" id="scCount"></p>
          </div>
          <div class="ms-auto d-flex gap-2">
            <button class="btn btn-light-mf" id="scExport" type="button"><i class="bi bi-download me-1"></i>Export CSV</button>
            <button class="btn btn-mf" id="scAdd" type="button"><i class="bi bi-plus-lg me-1"></i>Add Schedule / Class</button>
          </div>
        </div>

        <div class="row g-3 mb-3" id="scStats"></div>

        <div class="card-mf mf-ledger">
          <div class="mf-ledger-search">
            <i class="bi bi-search"></i>
            <input id="scSearch" name="sc-list-filter" placeholder="Search code, name or description…" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true">
            <div class="sc-filters" id="scFilters">
              <button type="button" class="sc-filter is-on" data-kind="">All</button>
              <button type="button" class="sc-filter" data-kind="schedule">Schedule</button>
              <button type="button" class="sc-filter" data-kind="class">Class</button>
              <button type="button" class="sc-filter" data-rx="1">Rx required</button>
            </div>
          </div>
          <div class="table-scroll" style="max-height:none;overflow:visible">
            <table class="table table-mf">
              <thead>
                <tr>
                  <th>Schedule / Class</th>
                  <th>Kind</th>
                  <th>Rule</th>
                  <th class="text-end">Medicines</th>
                  <th class="text-end">Batches</th>
                  <th class="text-end">Sales(30D)</th>
                  <th class="text-end">Stock</th>
                  <th class="text-end">MRP value</th>
                  <th>Status</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="scBody"></tbody>
            </table>
          </div>
          <div class="mf-ledger-foot">
            <span class="text-2 small" id="scPageInfo"></span>
            <ul class="pagination pagination-sm mb-0" id="scPager"></ul>
          </div>
          <p class="mf-ledger-note">Schedules control the prescription rule. Classes group medicines by therapy. Counts, stock value and 30-day sales are computed live from the medicine master and batch ledger.</p>
        </div>
      </main>
    </div>
  </div>

  <div class="modal fade" id="scFormModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="scFormTitle">Add Schedule / Class</h5>
          <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
        </div>
        <form class="modal-body" id="scForm" autocomplete="off">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="scKind">Kind <span class="req">*</span></label>
              <select class="form-select" id="scKind">
                <option value="schedule">Schedule</option>
                <option value="class">Class</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="scCode">Code <span class="req">*</span></label>
              <input class="form-control" id="scCode" placeholder="e.g. H1 or Analgesic" autocomplete="off" data-lpignore="true" data-1p-ignore="true">
            </div>
            <div class="col-12">
              <label class="form-label" for="scName">Name <span class="req">*</span></label>
              <input class="form-control" id="scName" placeholder="e.g. Schedule H1" autocomplete="off" data-lpignore="true" data-1p-ignore="true">
            </div>
            <div class="col-12">
              <label class="form-label" for="scDesc">Description</label>
              <input class="form-control" id="scDesc" placeholder="How this should be sold" autocomplete="off">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="scStatus">Status</label>
              <select class="form-select" id="scStatus">
                <option>Active</option>
                <option>Inactive</option>
              </select>
            </div>
            <div class="col-12 d-flex flex-wrap gap-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="scRx">
                <label class="form-check-label" for="scRx">Prescription required</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="scReg">
                <label class="form-check-label" for="scReg">Register required</label>
              </div>
            </div>
          </div>
          <div class="text-2 small mt-3">Schedule codes match the Schedule field on the medicine master. Renaming a schedule code updates those medicines.</div>
        </form>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal" type="button">Cancel</button>
          <button class="btn btn-mf" id="scSave" type="button"><i class="bi bi-check2 me-1"></i>Save</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="scViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="scViewTitle">Schedule</h5>
          <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
        </div>
        <div class="modal-body p-0" id="scViewBody"></div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/app.js"></script>
  <script>
    (function () {
      const MF = window.MF, D = window.MF_DATA || {};
      const $ = (s) => document.querySelector(s);
      const state = { q: '', kind: '', rx: false, page: 1, per: 8, rows: [] };
      let editing = null;
      let searchLock = null;

      const DEFAULTS = [
        { id: 'otc', kind: 'schedule', code: 'OTC', name: 'Over the counter', description: 'No prescription required.', rx_required: 0, register_required: 0, status: 'Active', sort_order: 10 },
        { id: 'g', kind: 'schedule', code: 'G', name: 'Schedule G', description: 'Caution label. Keep the prescription with the bill.', rx_required: 1, register_required: 0, status: 'Active', sort_order: 20 },
        { id: 'h', kind: 'schedule', code: 'H', name: 'Schedule H', description: 'Prescription medicine under the Drugs and Cosmetics Rules.', rx_required: 1, register_required: 0, status: 'Active', sort_order: 30 },
        { id: 'h1', kind: 'schedule', code: 'H1', name: 'Schedule H1', description: 'Prescription plus the H1 register.', rx_required: 1, register_required: 1, status: 'Active', sort_order: 40 },
        { id: 'x', kind: 'schedule', code: 'X', name: 'Schedule X', description: 'Restricted prescription medicine. Record the Rx in the Schedule X register.', rx_required: 1, register_required: 1, status: 'Active', sort_order: 50 },
        { id: 'ndps', kind: 'schedule', code: 'NDPS', name: 'Narcotic / psychotropic', description: 'NDPS medicine. Prescription and the narcotic register are both required.', rx_required: 1, register_required: 1, status: 'Active', sort_order: 60 },
        { id: 'analgesic', kind: 'class', code: 'Analgesic', name: 'Analgesic & antipyretic', description: 'Pain and fever medicines.', rx_required: 0, register_required: 0, status: 'Active', sort_order: 10 },
        { id: 'antibiotic', kind: 'class', code: 'Antibiotic', name: 'Antibiotic', description: 'Antibacterial medicines.', rx_required: 0, register_required: 0, status: 'Active', sort_order: 20 },
        { id: 'antacid', kind: 'class', code: 'Antacid', name: 'Antacid & PPI', description: 'Acidity and ulcer medicines.', rx_required: 0, register_required: 0, status: 'Active', sort_order: 30 },
        { id: 'cardiac', kind: 'class', code: 'Cardiac', name: 'Cardiovascular', description: 'Blood pressure, heart and lipid medicines.', rx_required: 0, register_required: 0, status: 'Active', sort_order: 40 },
        { id: 'diabetes', kind: 'class', code: 'Diabetes', name: 'Antidiabetic', description: 'Diabetes and insulin medicines.', rx_required: 0, register_required: 0, status: 'Active', sort_order: 50 },
        { id: 'respiratory', kind: 'class', code: 'Respiratory', name: 'Respiratory', description: 'Cough, cold, asthma and inhalers.', rx_required: 0, register_required: 0, status: 'Active', sort_order: 60 },
        { id: 'vitamin', kind: 'class', code: 'Vitamin', name: 'Vitamin & supplement', description: 'Vitamins, minerals and nutritional supplements.', rx_required: 0, register_required: 0, status: 'Active', sort_order: 70 },
        { id: 'derma', kind: 'class', code: 'Derma', name: 'Dermatological', description: 'Skin, cream and ointment medicines.', rx_required: 0, register_required: 0, status: 'Active', sort_order: 80 }
      ];

      function holdSearch() {
        const el = $('#scSearch');
        if (!el || searchLock !== null) return;
        searchLock = el.value;
        el.readOnly = true;
      }
      function releaseSearch() {
        const el = $('#scSearch');
        if (!el || searchLock === null) return;
        if (el.value !== searchLock) el.value = searchLock;
        el.readOnly = false;
        state.q = searchLock;
        searchLock = null;
      }
      function same(a, b) { return String(a || '').trim().toLowerCase() === String(b || '').trim().toLowerCase(); }
      function codeTone(row) {
        if (row.kind === 'class') return 'class';
        const code = String(row.code || '').toLowerCase();
        if (code === 'otc') return 'otc';
        if (code === 'h1') return 'h1';
        if (code === 'h') return 'h';
        if (code === 'x' || code === 'ndps') return code;
        if (code === 'g') return 'g';
        return 'class';
      }
      function ruleLabel(row) {
        if (Number(row.register_required)) return { cls: 'reg', text: 'Rx + register' };
        if (Number(row.rx_required)) return { cls: 'rx', text: 'Prescription' };
        return { cls: 'open', text: row.kind === 'schedule' ? 'Open sale' : 'No Rx rule' };
      }
      function medsOf(row) {
        return (D.medicines || []).filter((m) => {
          if (row.kind === 'class') {
            const value = m.class || m.therapeuticClass || m.drugClass || m.className || m.class_name || '';
            return m.classId == row.id || m.class_id == row.id || same(value, row.code) || same(value, row.name);
          }
          return m.scheduleId == row.id || m.schedule_id == row.id || same(m.schedule, row.code);
        });
      }
      function dayOf(v) {
        const m = String(v || '').match(/\d{4}-\d{2}-\d{2}/);
        return m ? m[0] : '';
      }
      function salesWindow() {
        const end = MF.today();
        const start = new Date(end + 'T00:00:00Z');
        start.setUTCDate(start.getUTCDate() - 30);
        return { start: start.toISOString().slice(0, 10), end };
      }
      function lineQty(line) { return Number(line.qty ?? line.quantity ?? 0) || 0; }
      function lineAmount(line) {
        if (line.amount != null) return Number(line.amount) || 0;
        if (line.net != null) return Number(line.net) || 0;
        const rate = Number(line.rate ?? line.mrp ?? 0) || 0;
        const disc = Number(line.discPct ?? line.disc_pct ?? 0) || 0;
        return lineQty(line) * rate * (1 - disc / 100);
      }
      function salesByMedicine() {
        const map = new Map();
        const { start, end } = salesWindow();
        const invoices = (Array.isArray(D.salesInvoices) && D.salesInvoices.length) ? D.salesInvoices : (Array.isArray(D.sales) ? D.sales : []);
        invoices.forEach((inv) => {
          if (!inv || /cancel|void|draft/i.test(String(inv.status || ''))) return;
          const day = dayOf(inv.date || inv.sale_date || inv.invoiceDate || inv.createdAt);
          if (!day || day < start || day > end) return;
          const sign = /return/i.test(String(inv.type || inv.docType || '')) ? -1 : 1;
          (inv.items || inv.lines || []).forEach((line) => {
            const id = line.medId ?? line.medicineId ?? line.medicine_id;
            if (id == null) return;
            const cur = map.get(String(id)) || { qty: 0, amount: 0 };
            cur.qty += sign * lineQty(line);
            cur.amount += sign * lineAmount(line);
            map.set(String(id), cur);
          });
        });
        return map;
      }
      function statsOf(row, salesMap) {
        const meds = medsOf(row);
        if (!meds.length && (row.medicine_count || row.batch_count || row.stock_qty || row.mrp_value)) {
          return {
            meds: [],
            batches: Number(row.batch_count) || 0,
            stock: Number(row.stock_qty) || 0,
            mrp: Number(row.mrp_value) || 0,
            sale: { qty: Number(row.sales_qty_30) || 0, amount: Number(row.sales_amount_30) || 0 },
            count: Number(row.medicine_count) || 0
          };
        }
        const stock = meds.reduce((s, m) => s + (MF.stockOf ? MF.stockOf(m.id) : 0), 0);
        const batches = meds.reduce((s, m) => s + (MF.batchesOf ? MF.batchesOf(m.id).length : 0), 0);
        const mrp = meds.reduce((s, m) => s + (MF.batchesOf ? MF.batchesOf(m.id).reduce((a, b) => a + (Number(b.qty) || 0) * (Number(b.mrp) || 0), 0) : 0), 0);
        const sale = meds.reduce((s, m) => {
          const hit = salesMap.get(String(m.id));
          if (!hit) return s;
          s.qty += hit.qty;
          s.amount += hit.amount;
          return s;
        }, { qty: 0, amount: 0 });
        return { meds, batches, stock, mrp, sale, count: meds.length };
      }
      function salesCell(sale) {
        const s = sale || { qty: 0, amount: 0 };
        if (!s.qty && !s.amount) return '<span class="text-2">—</span>';
        if (s.amount) return `<div class="num fw-semibold">${MF.fmt(s.amount)}</div>${s.qty ? `<div class="mf-sub">${MF.num(s.qty)} qty</div>` : ''}`;
        return `<div class="num fw-semibold">${MF.num(s.qty)}</div><div class="mf-sub">qty</div>`;
      }
      function filtered() {
        const q = state.q.toLowerCase();
        return state.rows.filter((row) => {
          if (state.kind && row.kind !== state.kind) return false;
          if (state.rx && !Number(row.rx_required)) return false;
          if (!q) return true;
          return [row.code, row.name, row.description, row.kind].join(' ').toLowerCase().includes(q);
        });
      }
      function render() {
        const salesMap = salesByMedicine();
        const list = filtered();
        const pages = Math.max(1, Math.ceil(list.length / state.per));
        state.page = Math.min(state.page, pages);
        const slice = list.slice((state.page - 1) * state.per, state.page * state.per);
        const schedules = state.rows.filter((r) => r.kind === 'schedule').length;
        const classes = state.rows.filter((r) => r.kind === 'class').length;
        const rx = state.rows.filter((r) => Number(r.rx_required)).length;
        const linked = state.rows.reduce((s, r) => s + statsOf(r, salesMap).count, 0);
        $('#scCount').textContent = `${schedules} schedule${schedules === 1 ? '' : 's'} · ${classes} class${classes === 1 ? '' : 'es'}`;
        $('#scStats').innerHTML = `
          <div class="col-6 col-md-3"><div class="mf-stat accent"><span>Schedules</span><strong>${MF.num(schedules)}</strong></div></div>
          <div class="col-6 col-md-3"><div class="mf-stat class"><span>Classes</span><strong>${MF.num(classes)}</strong></div></div>
          <div class="col-6 col-md-3"><div class="mf-stat rx"><span>Rx required</span><strong>${MF.num(rx)}</strong></div></div>
          <div class="col-6 col-md-3"><div class="mf-stat"><span>Medicines linked</span><strong>${MF.num(linked)}</strong></div></div>`;
        $('#scBody').innerHTML = slice.map((row) => {
          const s = statsOf(row, salesMap);
          const rule = ruleLabel(row);
          const master = row.kind === 'schedule'
            ? `medicine-master.php?schedule=${encodeURIComponent(row.code)}`
            : 'medicine-master.php';
          return `<tr>
            <td>
              <div class="sc-row">
                <span class="sc-code ${codeTone(row)}">${MF.esc(row.code)}</span>
                <div>
                  <div class="mf-name">${MF.esc(row.name)}</div>
                  ${row.description ? `<div class="mf-sub">${MF.esc(row.description)}</div>` : ''}
                </div>
              </div>
            </td>
            <td><span class="sc-kind ${row.kind}">${row.kind === 'class' ? 'Class' : 'Schedule'}</span></td>
            <td><span class="sc-rule ${rule.cls}">${rule.text}</span></td>
            <td class="text-end num fw-semibold">${MF.num(s.count)}</td>
            <td class="text-end num">${MF.num(s.batches)}</td>
            <td class="text-end">${salesCell(s.sale)}</td>
            <td class="text-end num">${MF.num(s.stock)}</td>
            <td class="text-end num">${MF.fmt(s.mrp)}</td>
            <td>${MF.badge(row.status || 'Active', row.status === 'Inactive' ? 'secondary' : 'success')}</td>
            <td class="text-end">
              <div class="dropdown">
                <button type="button" class="btn btn-icon btn-light-mf mf-kebab" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-label="Actions"><i class="bi bi-three-dots-vertical"></i></button>
                <ul class="dropdown-menu dropdown-menu-end mf-act-menu">
                  <li><button type="button" class="dropdown-item" data-a="view" data-id="${MF.esc(row.id)}"><i class="bi bi-eye"></i><span>View medicines</span></button></li>
                  <li><button type="button" class="dropdown-item" data-a="edit" data-id="${MF.esc(row.id)}"><i class="bi bi-pencil"></i><span>Edit</span></button></li>
                  <li><a class="dropdown-item" href="${master}"><i class="bi bi-capsule"></i><span>Open in master</span></a></li>
                  <li><hr class="dropdown-divider"></li>
                  <li><button type="button" class="dropdown-item text-danger" data-a="del" data-id="${MF.esc(row.id)}"><i class="bi bi-trash3"></i><span>Delete</span></button></li>
                </ul>
              </div>
            </td>
          </tr>`;
        }).join('') || `<tr><td colspan="10"><div class="empty-state"><i class="bi bi-shield-check"></i>No schedule or class matches.</div></td></tr>`;
        $('#scPageInfo').textContent = `Showing ${slice.length ? (state.page - 1) * state.per + 1 : 0}–${(state.page - 1) * state.per + slice.length} of ${list.length}`;
        $('#scPager').innerHTML = Array.from({ length: pages }, (_, i) =>
          `<li class="page-item ${i + 1 === state.page ? 'active' : ''}"><button class="page-link" type="button" data-pg="${i + 1}">${i + 1}</button></li>`).join('');
        $('#scPager').querySelectorAll('[data-pg]').forEach((b) => b.addEventListener('click', () => { state.page = +b.dataset.pg; render(); }));
        $('#scBody').querySelectorAll('[data-a]').forEach((b) => b.addEventListener('click', () => {
          const row = state.rows.find((r) => String(r.id) === String(b.dataset.id));
          if (!row) return;
          if (b.dataset.a === 'view') openView(row);
          if (b.dataset.a === 'edit') openForm(row);
          if (b.dataset.a === 'del') remove(row);
        }));
      }

      function findRow(id) { return state.rows.find((r) => String(r.id) === String(id)); }
      function openForm(row) {
        editing = row || null;
        $('#scFormTitle').textContent = editing ? 'Edit Schedule / Class' : 'Add Schedule / Class';
        $('#scKind').value = editing ? editing.kind : 'schedule';
        $('#scKind').disabled = !!editing;
        $('#scCode').value = editing ? editing.code : '';
        $('#scName').value = editing ? editing.name : '';
        $('#scDesc').value = editing ? (editing.description || '') : '';
        $('#scStatus').value = editing ? (editing.status || 'Active') : 'Active';
        $('#scRx').checked = editing ? !!Number(editing.rx_required) : false;
        $('#scReg').checked = editing ? !!Number(editing.register_required) : false;
        holdSearch();
        bootstrap.Modal.getOrCreateInstance($('#scFormModal')).show();
        setTimeout(() => $('#scCode').focus(), 200);
      }
      function viewHtml(row, meds, stats) {
        const rule = ruleLabel(row);
        const master = row.kind === 'schedule'
          ? `medicine-master.php?schedule=${encodeURIComponent(row.code)}`
          : 'medicine-master.php';
        return `
          <div class="p-3 border-bottom d-flex flex-wrap gap-4 align-items-center">
            <span class="sc-code ${codeTone(row)}">${MF.esc(row.code)}</span>
            <div><div class="kpi-label">Kind</div><div class="fw-bold">${row.kind === 'class' ? 'Class' : 'Schedule'}</div></div>
            <div><div class="kpi-label">Rule</div><div class="fw-bold">${rule.text}</div></div>
            <div><div class="kpi-label">Medicines</div><div class="fw-bold num">${MF.num(stats.count)}</div></div>
            <div><div class="kpi-label">Stock</div><div class="fw-bold num">${MF.num(stats.stock)}</div></div>
            <div><div class="kpi-label">MRP value</div><div class="fw-bold num">${MF.fmt(stats.mrp)}</div></div>
            <div class="ms-auto"><a class="btn btn-mf-soft btn-sm" href="${master}"><i class="bi bi-capsule me-1"></i>Open in master</a></div>
          </div>
          ${row.description ? `<div class="px-3 pt-3 text-2 small">${MF.esc(row.description)}</div>` : ''}
          <table class="table table-mf mb-0">
            <thead><tr><th>Medicine</th><th>Manufacturer</th><th class="text-end">Stock</th><th class="text-end">MRP</th></tr></thead>
            <tbody>${meds.map((m) => `<tr>
              <td><div class="td-title">${MF.esc(m.name)}</div></td>
              <td class="text-2">${MF.esc(m.manufacturer || '—')}</td>
              <td class="text-end num">${MF.num(m.stock != null ? m.stock : (MF.stockOf ? MF.stockOf(m.id) : 0))} ${MF.esc(m.unit || '')}</td>
              <td class="text-end num">${MF.fmt(m.mrp, 2)}</td>
            </tr>`).join('') || '<tr><td colspan="4"><div class="empty-state"><i class="bi bi-capsule"></i>No medicines linked yet.</div></td></tr>'}</tbody>
          </table>`;
      }
      async function openView(row) {
        const stats = statsOf(row, salesByMedicine());
        let meds = stats.meds;
        $('#scViewTitle').textContent = row.code + ' — ' + row.name;
        $('#scViewBody').innerHTML = `<div class="text-center text-2 p-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>`;
        bootstrap.Modal.getOrCreateInstance($('#scViewModal')).show();
        if (MF.Api.live) {
          try {
            const res = await MF.Api.get('schedule-classes.php?id=' + encodeURIComponent(row.id));
            const data = res.data || {};
            if (Array.isArray(data.medicines) && data.medicines.length) meds = data.medicines;
          } catch (e) { /* keep the local list */ }
        }
        $('#scViewBody').innerHTML = viewHtml(row, meds, stats);
      }
      function payload() {
        return {
          kind: $('#scKind').value,
          code: $('#scCode').value.trim(),
          name: $('#scName').value.trim(),
          description: $('#scDesc').value.trim(),
          status: $('#scStatus').value,
          rx_required: $('#scRx').checked ? 1 : 0,
          register_required: $('#scReg').checked ? 1 : 0
        };
      }
      async function save() {
        const body = payload();
        if (!body.code) { MF.toast('Code is required.', 'err', 'Validation'); return; }
        if (!body.name) { MF.toast('Name is required.', 'err', 'Validation'); return; }
        const dup = state.rows.some((r) => r.kind === body.kind && same(r.code, body.kind === 'schedule' ? body.code.toUpperCase() : body.code) && (!editing || String(r.id) !== String(editing.id)));
        if (dup) { MF.toast('That code is already used.', 'warn', 'Duplicate'); return; }
        const btn = $('#scSave');
        btn.disabled = true;
        try {
          if (MF.Api.live) {
            if (editing) await MF.Api.put('schedule-classes.php', Object.assign({ id: editing.id }, body));
            else await MF.Api.post('schedule-classes.php', body);
            await loadRows();
          } else {
            const next = Object.assign({}, editing || {}, body, {
              id: editing ? editing.id : ('local-' + Date.now()),
              code: body.kind === 'schedule' ? body.code.toUpperCase() : body.code
            });
            if (editing && editing.kind === 'schedule' && !same(editing.code, next.code)) {
              (D.medicines || []).forEach((m) => { if (same(m.schedule, editing.code)) m.schedule = next.code; });
            }
            if (!Array.isArray(D.scheduleClasses)) D.scheduleClasses = state.rows.slice();
            if (editing) D.scheduleClasses = D.scheduleClasses.map((r) => String(r.id) === String(editing.id) ? next : r);
            else D.scheduleClasses.push(next);
            state.rows = D.scheduleClasses.slice();
          }
          MF.toast(editing ? body.code + ' updated.' : body.code + ' added.', 'success', editing ? 'Saved' : 'Added');
          releaseSearch();
          bootstrap.Modal.getInstance($('#scFormModal'))?.hide();
          render();
        } catch (e) {
          MF.toast(e.message || 'Could not save.', 'err', 'Save failed');
        } finally {
          btn.disabled = false;
        }
      }
      async function remove(row) {
        const n = statsOf(row, salesByMedicine()).count;
        if (n) { MF.toast(row.code + ' is used by ' + n + ' medicine' + (n === 1 ? '' : 's') + '. Mark it inactive, or move those medicines first.', 'warn', 'In use'); return; }
        const ok = await MF.confirm({ title: 'Delete ' + row.code + '?', message: 'This only removes an unused schedule or class.', confirmText: 'Delete', tone: 'danger' });
        if (!ok) return;
        try {
          if (MF.Api.live) {
            await MF.Api.del('schedule-classes.php?id=' + encodeURIComponent(row.id));
            await loadRows();
          } else {
            D.scheduleClasses = (D.scheduleClasses || state.rows).filter((r) => String(r.id) !== String(row.id));
            state.rows = D.scheduleClasses.slice();
          }
          MF.toast(row.code + ' removed.', 'success', 'Deleted');
          render();
        } catch (e) {
          MF.toast(e.message || 'Could not delete.', 'err', 'Delete failed');
        }
      }
      async function loadRows() {
        if (MF.Api && MF.Api.live) {
          try {
            const res = await MF.Api.get('schedule-classes.php');
            const data = res.data;
            state.rows = Array.isArray(data) ? data : [];
            D.scheduleClasses = state.rows;
            return;
          } catch (e) {
            MF.toast(e.message || 'Schedule / Class table is not installed yet.', 'warn', 'Schedule / Class');
          }
        }
        const stored = Array.isArray(D.scheduleClasses) && D.scheduleClasses.length ? D.scheduleClasses : DEFAULTS;
        state.rows = stored.map((r) => Object.assign({}, r));
        D.scheduleClasses = state.rows;
      }

      $('#scSearch').addEventListener('input', () => {
        if (searchLock !== null) { $('#scSearch').value = searchLock; return; }
        state.q = $('#scSearch').value;
        state.page = 1;
        render();
      });
      $('#scFilters').addEventListener('click', (e) => {
        const btn = e.target.closest('button');
        if (!btn) return;
        if (btn.dataset.rx) {
          state.rx = !state.rx;
          btn.classList.toggle('is-on', state.rx);
        } else {
          state.kind = btn.dataset.kind || '';
          $('#scFilters').querySelectorAll('[data-kind]').forEach((b) => b.classList.toggle('is-on', b === btn));
        }
        state.page = 1;
        render();
      });
      $('#scKind').addEventListener('change', () => {
        if (editing) return;
        const schedule = $('#scKind').value === 'schedule';
        if (schedule && !$('#scCode').value) $('#scRx').checked = true;
        if (!schedule) { $('#scRx').checked = false; $('#scReg').checked = false; }
      });
      $('#scForm').addEventListener('submit', (e) => { e.preventDefault(); save(); });
      $('#scFormModal').addEventListener('show.bs.modal', holdSearch);
      $('#scFormModal').addEventListener('hidden.bs.modal', releaseSearch);
      $('#scAdd').addEventListener('click', () => openForm(null));
      $('#scSave').addEventListener('click', save);
      $('#scExport').addEventListener('click', () => {
        const salesMap = salesByMedicine();
        MF.exportCSV('schedule-class.csv',
          ['Kind', 'Code', 'Name', 'Description', 'Rule', 'Medicines', 'Batches', 'Sales(30D)', 'Stock', 'MRP value', 'Status'],
          filtered().map((row) => {
            const s = statsOf(row, salesMap);
            const rule = ruleLabel(row);
            return [row.kind, row.code, row.name, row.description || '', rule.text, s.count, s.batches, s.sale.amount || s.sale.qty || 0, s.stock, s.mrp, row.status || 'Active'];
          }));
      });

      document.addEventListener('DOMContentLoaded', async () => {
        await MF.boot();
        await loadRows();
        render();
      });
    })();
  </script>
</body>
</html>
