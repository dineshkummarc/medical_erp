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
  <title>Prescriptions · Optms Rx</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .rx-stat { background:#f8fafc; border:1px solid #e7edf4; border-radius:14px; padding:12px 14px; }
    .rx-stat span { display:block; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#6c757d; }
    .rx-stat strong { display:block; margin-top:3px; font-size:1.2rem; font-weight:750; letter-spacing:-.02em; color:#1b2430; }
    .rx-stat.accent { background:var(--mf-primary-soft); border-color:#d7ebe6; }
    .rx-ledger { border:1px solid #e3ebf4; border-radius:16px; background:#fff; overflow:hidden; box-shadow:0 1px 2px rgba(22,50,92,.04), 0 10px 28px rgba(22,50,92,.04); }
    .rx-ledger-search { display:flex; align-items:center; gap:10px; padding:12px 16px; border-bottom:1px solid #e7eef6; background:#f7f8fa; flex-wrap:wrap; }
    .rx-search-box { display:flex; align-items:center; gap:8px; flex:1; min-width:220px; background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:0 12px; min-height:40px; transition:border-color .15s ease, box-shadow .15s ease; }
    .rx-search-box:focus-within { border-color:var(--mf-primary); box-shadow:0 0 0 3px rgba(23,107,91,.16); }
    .rx-search-box i { color:#8b9bb0; font-size:15px; }
    .rx-search-box input[type="search"] { border:0; outline:0; box-shadow:none !important; background:transparent; width:100%; padding:8px 0; font-size:.92rem; color:#1b2430; }
    .rx-search-box input::placeholder { color:#9aa8b8; }
    .rx-status { width:148px; min-height:40px; border:1px solid #e5e7eb; border-radius:10px; color:#374151; font-size:.88rem; font-weight:600; background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 16 16'%3E%3Cpath fill='%236b7280' d='M3.2 5.5 8 10.3 12.8 5.5'/%3E%3C/svg%3E") no-repeat right 12px center; padding:0 32px 0 12px; appearance:none; transition:border-color .15s ease, box-shadow .15s ease; }
    .rx-status:hover, .rx-status:focus { border-color:var(--mf-primary); box-shadow:0 0 0 3px rgba(23,107,91,.12); outline:0; }
    .rx-muted { color:#8b9bb0; font-size:.75rem; font-weight:600; }
    .rx-name { font-weight:700; color:#1b2430; }
    .rx-ledger .table-mf { margin:0; }
    .rx-ledger .table-mf thead th { background:#f7f9fc; color:#7b8798; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; border-bottom:1px solid #e7eef6; padding:12px 14px; white-space:nowrap; }
    .rx-ledger .table-mf tbody td { border-bottom:1px solid #f0f4f8; padding:13px 14px; vertical-align:middle; }
    .rx-ledger .table-mf tbody tr:hover td { background:#f4faf8; }
    .rx-ledger-foot { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:12px 16px; border-top:1px solid #e3ebf4; }
    .rx-ledger-foot .pagination { gap:4px; }
    .rx-ledger-foot .page-link { border:1px solid #e3ebf4; color:#516278; border-radius:8px; min-width:32px; text-align:center; font-weight:650; padding:.3rem .55rem; transition:background .15s ease, color .15s ease, border-color .15s ease; }
    .rx-ledger-foot .page-item.active .page-link { background:var(--mf-primary); border-color:var(--mf-primary); color:#fff; }
    .rx-ledger-foot .page-link:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-color:var(--mf-primary); }
    .rx-ledger-note { color:#8b9bb0; font-size:.78rem; line-height:1.45; padding:10px 16px 12px; margin:0; border-top:1px solid #eef3f8; background:#fbfcfe; }
    .rx-kebab { width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; padding:0; transition:background .15s ease, color .15s ease, border-color .15s ease; }
    .btn.rx-kebab:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-color:var(--mf-primary); }
    .rx-act-menu { min-width:196px; padding:6px; border:1px solid #e7edf4; border-radius:12px; box-shadow:0 12px 32px rgba(16,32,64,.14); }
    .rx-act-menu .dropdown-item { display:flex; align-items:center; gap:10px; font-size:.84rem; font-weight:600; border-radius:8px; padding:.48rem .65rem; transition:background .15s ease, color .15s ease; }
    .rx-act-menu .dropdown-item i { width:1.05rem; color:var(--mf-primary); }
    .rx-act-menu .dropdown-item:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); }
    .rx-modal .modal-content { border:0; border-radius:16px; }
    .rx-modal .modal-header { padding:16px 20px; border-bottom:1px solid #eef2f6; }
    .rx-modal .modal-title { font-size:1.05rem; font-weight:700; }
    .rx-modal .modal-body { padding:18px 20px 8px; }
    .rx-modal .modal-footer { border-top:1px solid #eef2f6; padding:12px 20px; }
    .rx-label { display:block; font-size:.78rem; font-weight:600; color:#374151; margin-bottom:6px; }
    .rx-label .req { color:#dc3545; }
    .rx-modal .form-control, .rx-pick-btn {
      border:1px solid #e5e7eb; border-radius:10px; min-height:40px; font-size:.9rem; color:#1b2430; background:#fff;
      transition:border-color .15s ease, box-shadow .15s ease;
    }
    .rx-modal .form-control:focus { border-color:var(--mf-primary); box-shadow:0 0 0 3px rgba(23,107,91,.16); }
    .rx-date { position:relative; }
    .rx-date i { position:absolute; right:12px; top:50%; transform:translateY(-50%); color:#6b7280; pointer-events:none; }
    .rx-date input[type="text"] { padding-right:36px; }
    .rx-date-native { position:absolute; right:4px; top:4px; width:32px; height:32px; opacity:0; cursor:pointer; }
    .rx-select { width:100%; min-height:40px; border:1px solid #e5e7eb; border-radius:10px; color:#1b2430; font-size:.9rem; background-color:#fff; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 16 16'%3E%3Cpath fill='%236b7280' d='M3.2 5.5 8 10.3 12.8 5.5'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; padding:0 32px 0 12px; appearance:none; }
    .rx-select.placeholder { color:#9aa3af; }
    .rx-select option { color:#1b2430; background:#fff; }
    .rx-select:hover, .rx-select:focus { border-color:var(--mf-primary); box-shadow:0 0 0 3px rgba(23,107,91,.16); outline:0; color:#1b2430; }
    .rx-line .rx-select { min-height:38px; font-size:.86rem; }
    .rx-lines-head { display:flex; align-items:center; justify-content:space-between; margin:18px 0 8px; }
    .rx-lines-head strong { font-size:.95rem; }
    .rx-add { border:0; background:transparent; color:#6b7280; font-weight:650; font-size:.86rem; border-radius:8px; padding:4px 8px; transition:background .15s ease, color .15s ease; }
    .rx-add:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); }
    .rx-grid { width:100%; border:1px solid #eef2f6; border-radius:12px; overflow:visible; }
    .rx-grid-head, .rx-line { display:grid; grid-template-columns:minmax(160px,1.4fr) 1fr 1fr 1fr 72px 1.1fr 36px; gap:8px; align-items:center; }
    .rx-grid-head { background:#f7f9fc; color:#8b93a0; font-size:11px; font-weight:700; letter-spacing:.06em; padding:10px 12px; border-radius:12px 12px 0 0; }
    .rx-line { padding:10px 12px; border-top:1px solid #f0f4f8; position:relative; }
    .rx-line .form-control { min-height:38px; font-size:.86rem; }
    .rx-suggest { position:absolute; z-index:30; left:0; right:0; top:calc(100% + 4px); background:#fff; border:1px solid #e7edf4; border-radius:12px; box-shadow:0 12px 32px rgba(16,32,64,.14); padding:6px; max-height:180px; overflow:auto; }
    .rx-suggest button { width:100%; border:0; background:transparent; text-align:left; border-radius:8px; padding:7px 8px; font-size:.82rem; font-weight:600; }
    .rx-suggest button:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); }
    .rx-remove { width:32px; height:32px; border:0; background:transparent; color:#9aa3af; border-radius:8px; transition:background .15s ease, color .15s ease; }
    .rx-remove:hover { background:#fdecec; color:#b02a37; }
    .rx-cancel { border:0; background:transparent; color:#374151; font-weight:650; padding:8px 12px; border-radius:8px; transition:background .15s ease, color .15s ease; }
    .rx-cancel:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); }
    .rx-save { border-radius:10px; padding:8px 14px; }
    .rx-save:hover { transform:translateY(-1px); }
    @media (max-width: 900px) {
      .rx-grid-head { display:none; }
      .rx-grid-head, .rx-line { grid-template-columns:1fr 1fr; }
    }
  </style>
</head>
<body data-page="prescriptions">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">
        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-file-medical me-2 text-success"></i>Prescriptions</h1>
            <p class="page-sub" id="rxCount">Recorded prescriptions. This is not a sale.</p>
          </div>
          <div class="ms-auto">
            <button class="btn btn-mf" id="rxRecord" type="button"><i class="bi bi-plus-lg me-1"></i>Record Prescription</button>
          </div>
        </div>

        <div class="row g-3 mb-3" id="rxStats"></div>

        <div class="card-mf rx-ledger">
          <div class="rx-ledger-search">
            <label class="rx-search-box">
              <i class="bi bi-search"></i>
              <input id="rxSearch" type="search" placeholder="Search Rx no, patient, doctor, diagnosis…" autocomplete="off">
            </label>
            <select class="rx-status" id="rxStatus" aria-label="All status">
              <option value="all">All status</option>
              <option value="recorded">Recorded</option>
              <option value="dispensed">Dispensed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="table-scroll" style="max-height:none;overflow:visible">
            <table class="table table-mf">
              <thead>
                <tr>
                  <th>Rx no</th>
                  <th>Date</th>
                  <th>Patient</th>
                  <th>Doctor</th>
                  <th>Diagnosis</th>
                  <th class="text-end">Items</th>
                  <th>Status</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="rxBody"></tbody>
            </table>
          </div>
          <div class="rx-ledger-foot">
            <span class="text-2 small" id="rxPageInfo"></span>
            <ul class="pagination pagination-sm mb-0" id="rxPager"></ul>
          </div>
          <p class="rx-ledger-note">A recorded prescription is the doctor’s order. It does not deduct stock until the medicines are sold at the counter.</p>
        </div>
      </main>
    </div>
  </div>

  <div class="modal fade rx-modal" id="rxModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Record Prescription</h5>
          <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="rx-label" for="rxDateText">Date</label>
              <div class="rx-date">
                <input class="form-control" id="rxDateText" inputmode="numeric" placeholder="DD-MM-YYYY" autocomplete="off">
                <input class="rx-date-native" id="rxDate" type="date" aria-label="Pick date">
                <i class="bi bi-calendar3"></i>
              </div>
            </div>
            <div class="col-md-4">
              <label class="rx-label" for="rxPatient">Customer / Patient <span class="req">*</span></label>
              <input class="form-control" id="rxPatient" placeholder="Patient name" autocomplete="off">
            </div>
            <div class="col-md-4">
              <label class="rx-label" for="rxDoctor">Doctor</label>
              <select class="rx-select" id="rxDoctor">
                <option value="">— select doctor —</option>
              </select>
            </div>
            <div class="col-12">
              <label class="rx-label" for="rxNotes">Diagnosis / Notes</label>
              <input class="form-control" id="rxNotes" placeholder="Optional — recorded as given (demo)" autocomplete="off">
            </div>
          </div>

          <div class="rx-lines-head">
            <strong>Medicines on the prescription</strong>
            <button class="rx-add" id="rxAddLine" type="button"><i class="bi bi-plus"></i> Add line</button>
          </div>
          <div class="rx-grid">
            <div class="rx-grid-head">
              <span>Medicine</span><span>Dosage</span><span>Frequency</span><span>Duration</span><span>Qty</span><span>Instructions</span><span></span>
            </div>
            <div id="rxLines"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="rx-cancel" data-bs-dismiss="modal" type="button">Cancel</button>
          <button class="btn btn-mf rx-save" id="rxSave" type="button"><i class="bi bi-check-lg me-1"></i>Save Prescription</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="rxViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="rxViewTitle">Prescription</h5>
          <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
        </div>
        <div class="modal-body" id="rxViewBody"></div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal" type="button">Close</button>
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
      const MF = window.MF, D = window.MF_DATA || {};
      const $ = (s) => document.querySelector(s);
      const state = { rows: [], q: '', status: 'all', page: 1, per: 12, doctorId: '', seq: 1, doctors: [], medicines: [] };

      function today() {
        return MF.today ? MF.today() : new Date().toISOString().slice(0, 10);
      }

      function statusBadge(s) {
        const tone = { Recorded: 'success', Dispensed: 'info', Cancelled: 'secondary' }[s] || 'secondary';
        return MF.badge(s || 'Recorded', tone);
      }

      function liveData() { return window.MF_DATA || D || {}; }

      function asList(res) {
        const data = res && res.data;
        if (Array.isArray(data)) return data;
        if (data && Array.isArray(data.doctors)) return data.doctors;
        if (data && Array.isArray(data.medicines)) return data.medicines;
        if (data && Array.isArray(data.ledger)) return data.ledger;
        return [];
      }

      function normalizeDoctor(d) {
        return { id: d.id, name: d.name || '', specialty: d.specialty || '', status: d.status || 'Active' };
      }

      function normalizeMedicine(m) {
        return { id: m.id, name: m.name || m.medicine_name || '', generic: m.generic || m.generic_name || '' };
      }

      function doctors() { return state.doctors.length ? state.doctors : (liveData().doctors || []).map(normalizeDoctor).filter((d) => d.name); }
      function medicines() { return state.medicines.length ? state.medicines : (liveData().medicines || []).map(normalizeMedicine).filter((m) => m.name); }

      async function loadCatalogs() {
        const bag = liveData();
        let doctorRows = (bag.doctors || []).map(normalizeDoctor);
        let medicineRows = (bag.medicines || []).map(normalizeMedicine);
        if (MF.Api && MF.Api.live) {
          try {
            const res = await MF.Api.get('doctors.php');
            const rows = asList(res);
            if (rows.length) doctorRows = rows.map(normalizeDoctor);
          } catch (e) { /* keep bootstrap doctors */ }
          try {
            const res = await MF.Api.get('medicines.php');
            const rows = asList(res);
            if (rows.length) medicineRows = rows.map(normalizeMedicine);
          } catch (e) { /* keep bootstrap medicines */ }
        }
        doctorRows = doctorRows.filter((d) => d.name);
        const active = doctorRows.filter((d) => d.status !== 'Inactive');
        state.doctors = (active.length ? active : doctorRows).sort((a, b) => a.name.localeCompare(b.name));
        state.medicines = medicineRows.filter((m) => m.name).sort((a, b) => a.name.localeCompare(b.name));
        fillDoctorMenu();
        fillMedicineSelects();
      }

      function filtered() {
        const q = state.q.toLowerCase();
        return state.rows.filter((r) => {
          if (state.status !== 'all' && String(r.status).toLowerCase() !== state.status) return false;
          if (!q) return true;
          return [r.rx_no, r.patient_name, r.doctor_name, r.diagnosis, r.specialty].join(' ').toLowerCase().includes(q);
        });
      }

      function pageButtons(pages, current) {
        const want = new Set([1, pages, current - 1, current, current + 1]);
        const nums = [...want].filter((n) => n >= 1 && n <= pages).sort((a, b) => a - b);
        let html = '';
        let prev = 0;
        nums.forEach((n) => {
          if (n - prev > 1) html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
          html += `<li class="page-item ${n === current ? 'active' : ''}"><button class="page-link" type="button" data-pg="${n}">${n}</button></li>`;
          prev = n;
        });
        return html;
      }

      function render() {
        const list = filtered();
        const pages = Math.max(1, Math.ceil(list.length / state.per));
        state.page = Math.min(state.page, pages);
        const slice = list.slice((state.page - 1) * state.per, state.page * state.per);
        const recorded = state.rows.filter((r) => r.status === 'Recorded').length;
        $('#rxCount').textContent = `${state.rows.length} prescription${state.rows.length === 1 ? '' : 's'} · recorded orders, not sales`;
        $('#rxStats').innerHTML = `
          <div class="col-6 col-md-3"><div class="rx-stat accent"><span>Prescriptions</span><strong>${MF.num(state.rows.length)}</strong></div></div>
          <div class="col-6 col-md-3"><div class="rx-stat"><span>Recorded</span><strong>${MF.num(recorded)}</strong></div></div>
          <div class="col-6 col-md-3"><div class="rx-stat"><span>Showing</span><strong>${MF.num(list.length)}</strong></div></div>
          <div class="col-6 col-md-3"><div class="rx-stat"><span>Items</span><strong>${MF.num(list.reduce((s, r) => s + (Number(r.item_count) || 0), 0))}</strong></div></div>`;
        $('#rxBody').innerHTML = slice.map((r) => `<tr>
          <td class="rx-name num">${MF.esc(r.rx_no || '—')}</td>
          <td class="num">${r.rx_date ? MF.fmtDate(r.rx_date) : '—'}</td>
          <td>${MF.esc(r.patient_name || '—')}</td>
          <td>${r.doctor_name ? MF.esc(r.doctor_name) : '<span class="text-2">—</span>'}${r.specialty ? `<span class="rx-muted" style="display:block">${MF.esc(r.specialty)}</span>` : ''}</td>
          <td>${r.diagnosis ? MF.esc(r.diagnosis) : '<span class="text-2">—</span>'}</td>
          <td class="text-end num">${MF.num(r.item_count)}</td>
          <td>${statusBadge(r.status)}</td>
          <td class="text-end">
            <div class="dropdown">
              <button type="button" class="btn btn-icon btn-light-mf rx-kebab" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-label="Actions"><i class="bi bi-three-dots-vertical"></i></button>
              <ul class="dropdown-menu dropdown-menu-end rx-act-menu">
                <li><button type="button" class="dropdown-item" data-a="view" data-id="${MF.esc(r.id)}"><i class="bi bi-eye"></i><span>View</span></button></li>
                <li><button type="button" class="dropdown-item" data-a="dispensed" data-id="${MF.esc(r.id)}"><i class="bi bi-bag-check"></i><span>Mark dispensed</span></button></li>
                <li><button type="button" class="dropdown-item" data-a="cancel" data-id="${MF.esc(r.id)}"><i class="bi bi-x-circle"></i><span>Cancel</span></button></li>
              </ul>
            </div>
          </td>
        </tr>`).join('') || '<tr><td colspan="8"><div class="empty-state"><i class="bi bi-file-medical"></i>No prescriptions yet. Record one to start the ledger.</div></td></tr>';
        const start = slice.length ? (state.page - 1) * state.per + 1 : 0;
        $('#rxPageInfo').textContent = `Showing ${start}–${(state.page - 1) * state.per + slice.length} of ${list.length}`;
        $('#rxPager').innerHTML = pageButtons(pages, state.page);
        $('#rxPager').querySelectorAll('[data-pg]').forEach((b) => b.addEventListener('click', () => { state.page = +b.dataset.pg; render(); }));
        $('#rxBody').querySelectorAll('[data-a]').forEach((b) => b.addEventListener('click', () => onAction(b.dataset.a, b.dataset.id)));
      }

      function medicineOptions(selected) {
        const list = medicines();
        const empty = list.length ? '— select medicine —' : 'No medicines found';
        return `<option value="">${empty}</option>` + list.map((m) => `<option value="${MF.esc(m.id)}" data-name="${MF.esc(m.name)}"${String(selected || '') === String(m.id) ? ' selected' : ''}>${MF.esc(m.name)}${m.generic ? ' — ' + MF.esc(m.generic) : ''}</option>`).join('');
      }

      function lineHtml() {
        return `<div class="rx-line">
          <select class="rx-select rx-med placeholder" aria-label="Medicine">${medicineOptions()}</select>
          <input class="form-control rx-dose" placeholder="e.g. 1 tablet" autocomplete="off">
          <input class="form-control rx-freq" placeholder="e.g. Twice daily" autocomplete="off">
          <input class="form-control rx-dur" placeholder="e.g. 5 days" autocomplete="off">
          <input class="form-control rx-qty text-center" value="1" inputmode="numeric" autocomplete="off">
          <input class="form-control rx-note" placeholder="e.g. After food" autocomplete="off">
          <button class="rx-remove" type="button" aria-label="Remove line"><i class="bi bi-x-lg"></i></button>
        </div>`;
      }

      function bindLine(line) {
        const sel = line.querySelector('.rx-med');
        sel.addEventListener('change', () => sel.classList.toggle('placeholder', !sel.value));
        line.querySelector('.rx-remove').addEventListener('click', () => {
          const wrap = $('#rxLines');
          if (wrap.children.length === 1) {
            sel.value = '';
            sel.classList.add('placeholder');
            line.querySelectorAll('.rx-dose,.rx-freq,.rx-dur,.rx-note').forEach((el) => { el.value = ''; });
            line.querySelector('.rx-qty').value = '1';
            return;
          }
          line.remove();
        });
      }

      function fillMedicineSelects() {
        document.querySelectorAll('#rxLines .rx-med').forEach((sel) => {
          const current = sel.value;
          sel.innerHTML = medicineOptions(current);
          sel.classList.toggle('placeholder', !sel.value);
        });
      }

      function addLine() {
        const wrap = $('#rxLines');
        wrap.insertAdjacentHTML('beforeend', lineHtml());
        bindLine(wrap.lastElementChild);
      }

      function isoToDisplay(iso) {
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');
        return m ? m[3] + '-' + m[2] + '-' + m[1] : '';
      }

      function displayToIso(text) {
        const m = /^(\d{2})-(\d{2})-(\d{4})$/.exec((text || '').trim());
        if (!m) return '';
        const iso = m[3] + '-' + m[2] + '-' + m[1];
        const d = new Date(iso + 'T00:00:00');
        if (Number.isNaN(d.getTime()) || d.getMonth() + 1 !== +m[2]) return '';
        return iso;
      }

      function setDate(iso) {
        $('#rxDate').value = iso || '';
        $('#rxDateText').value = isoToDisplay(iso);
      }

      function resetForm() {
        setDate(today());
        $('#rxPatient').value = '';
        $('#rxNotes').value = '';
        state.doctorId = '';
        $('#rxDoctor').value = '';
        $('#rxDoctor').classList.add('placeholder');
        $('#rxLines').innerHTML = '';
        addLine();
      }

      function fillDoctorMenu() {
        const sel = $('#rxDoctor');
        const current = sel.value;
        const list = doctors();
        const empty = list.length ? '— select doctor —' : 'No doctors found';
        sel.innerHTML = `<option value="">${empty}</option>` + list.map((d) => `<option value="${MF.esc(d.id)}">${MF.esc(d.name)}${d.specialty ? ' — ' + MF.esc(d.specialty) : ''}</option>`).join('');
        if (current && [...sel.options].some((o) => o.value === current)) sel.value = current;
        sel.classList.toggle('placeholder', !sel.value);
      }

      function collectItems() {
        return [...$('#rxLines').querySelectorAll('.rx-line')].map((line) => {
          const sel = line.querySelector('.rx-med');
          const opt = sel.options[sel.selectedIndex];
          return {
          medicine_id: sel.value || null,
          medicine_name: opt ? (opt.getAttribute('data-name') || '') : '',
          dosage: line.querySelector('.rx-dose').value.trim(),
          frequency: line.querySelector('.rx-freq').value.trim(),
          duration: line.querySelector('.rx-dur').value.trim(),
          qty: Math.max(1, parseInt(line.querySelector('.rx-qty').value, 10) || 1),
          instructions: line.querySelector('.rx-note').value.trim()
        };
        }).filter((l) => l.medicine_name);
      }

      function customerIdFor(name) {
        const hit = (liveData().customers || []).find((c) => String(c.name).toLowerCase() === name.toLowerCase());
        return hit ? hit.id : null;
      }

      async function save() {
        const patient = $('#rxPatient').value.trim();
        const items = collectItems();
        if (!patient) { MF.toast('Patient name is required.', 'warn', 'Prescription'); $('#rxPatient').focus(); return; }
        if (!items.length) { MF.toast('Add at least one medicine.', 'warn', 'Prescription'); return; }
        const date = displayToIso($('#rxDateText').value) || $('#rxDate').value || today();
        if (!displayToIso($('#rxDateText').value) && !$('#rxDate').value) {
          MF.toast('Enter the date as DD-MM-YYYY.', 'warn', 'Prescription');
          $('#rxDateText').focus();
          return;
        }
        const payload = {
          date: date,
          patient_name: patient,
          customer_id: customerIdFor(patient),
          doctor_id: $('#rxDoctor').value || null,
          diagnosis: $('#rxNotes').value.trim(),
          items
        };
        if (MF.Api.live) {
          const res = await MF.Api.post('prescriptions.php', payload);
          MF.toast(`${res.rx_no || 'Prescription'} recorded`, 'success', 'Saved');
          bootstrap.Modal.getInstance($('#rxModal')).hide();
          await load();
          return;
        }
        const doctor = doctors().find((d) => String(d.id) === String($('#rxDoctor').value));
        state.rows.unshift({
          id: 'demo-' + state.seq,
          rx_no: 'RX-' + String(state.seq).padStart(5, '0'),
          rx_date: payload.date,
          patient_name: patient,
          doctor_name: doctor ? doctor.name : '',
          specialty: doctor ? (doctor.specialty || '') : '',
          diagnosis: payload.diagnosis,
          status: 'Recorded',
          item_count: items.length,
          items
        });
        state.seq += 1;
        MF.toast('Saved in this browser only. Run the migration to store it.', 'info', 'Demo');
        bootstrap.Modal.getInstance($('#rxModal')).hide();
        render();
      }

      async function onAction(action, id) {
        const row = state.rows.find((r) => String(r.id) === String(id));
        if (!row) return;
        if (action === 'view') return openView(row);
        const status = action === 'dispensed' ? 'Dispensed' : 'Cancelled';
        if (MF.Api.live && !String(id).startsWith('demo-')) {
          await MF.Api.put('prescriptions.php', { id, status });
          await load();
        } else {
          row.status = status;
          render();
        }
        MF.toast(row.rx_no + ' marked ' + status.toLowerCase(), 'success', 'Updated');
      }

      async function openView(row) {
        let items = row.items || [];
        if (MF.Api.live && !items.length && !String(row.id).startsWith('demo-')) {
          try {
            const res = await MF.Api.get('prescriptions.php?id=' + encodeURIComponent(row.id));
            items = (res.data || {}).items || [];
          } catch (e) { MF.toast(e.message, 'err', 'Could not load prescription'); }
        }
        $('#rxViewTitle').textContent = row.rx_no || 'Prescription';
        $('#rxViewBody').innerHTML = `
          <div class="d-flex justify-content-between gap-2 mb-3">
            <div>
              <div class="rx-name">${MF.esc(row.patient_name || '—')}</div>
              <div class="text-2 small">${row.rx_date ? MF.fmtDate(row.rx_date) : '—'} · ${MF.esc(row.doctor_name || 'No doctor')}</div>
              ${row.diagnosis ? `<div class="rx-muted">${MF.esc(row.diagnosis)}</div>` : ''}
            </div>
            <div>${statusBadge(row.status)}</div>
          </div>
          ${items.length ? `<table class="table table-mf"><thead><tr><th>Medicine</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th class="text-end">Qty</th><th>Instructions</th></tr></thead><tbody>
            ${items.map((l) => `<tr>
              <td class="fw-semibold">${MF.esc(l.medicine_name || l.name || '—')}</td>
              <td>${MF.esc(l.dosage || '—')}</td>
              <td>${MF.esc(l.frequency || '—')}</td>
              <td>${MF.esc(l.duration || '—')}</td>
              <td class="text-end num">${MF.num(l.qty)}</td>
              <td>${MF.esc(l.instructions || '—')}</td>
            </tr>`).join('')}
          </tbody></table>` : '<div class="empty-state"><i class="bi bi-capsule"></i>No medicines on this prescription.</div>'}`;
        bootstrap.Modal.getOrCreateInstance($('#rxViewModal')).show();
      }

      async function load() {
        if (!MF.Api.live) { render(); return; }
        try {
          const res = await MF.Api.get('prescriptions.php');
          state.rows = ((res.data || {}).ledger || []).map((r) => r);
        } catch (e) {
          MF.toast(e.message, 'err', 'Could not load prescriptions');
          state.rows = [];
        }
        render();
      }

      document.addEventListener('DOMContentLoaded', async () => {
        await MF.boot();
        resetForm();
        await loadCatalogs();
        $('#rxRecord').addEventListener('click', () => {
          resetForm();
          loadCatalogs().then(() => bootstrap.Modal.getOrCreateInstance($('#rxModal')).show()).catch(() => bootstrap.Modal.getOrCreateInstance($('#rxModal')).show());
        });
        $('#rxDoctor').addEventListener('change', () => {
          state.doctorId = $('#rxDoctor').value;
          $('#rxDoctor').classList.toggle('placeholder', !state.doctorId);
        });
        $('#rxDate').addEventListener('change', () => setDate($('#rxDate').value));
        $('#rxDateText').addEventListener('change', () => {
          const iso = displayToIso($('#rxDateText').value);
          if (iso) setDate(iso);
        });
        $('#rxAddLine').addEventListener('click', addLine);
        $('#rxSave').addEventListener('click', () => save().catch((e) => MF.toast(e.message, 'err', 'Could not save')));
        $('#rxSearch').addEventListener('input', (e) => { state.q = e.target.value; state.page = 1; render(); });
        $('#rxStatus').addEventListener('change', (e) => { state.status = e.target.value; state.page = 1; render(); });
        await load();
      });
    })();
  </script>
</body>
</html>
