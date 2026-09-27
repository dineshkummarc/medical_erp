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
  <title>Doctors · Optms Rx</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .dr-stat { background:#f8fafc; border:1px solid #e7edf4; border-radius:14px; padding:12px 14px; }
    .dr-stat span { display:block; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#6c757d; }
    .dr-stat strong { display:block; margin-top:3px; font-size:1.2rem; font-weight:750; letter-spacing:-.02em; color:#1b2430; }
    .dr-stat.accent { background:var(--mf-primary-soft); border-color:#d7ebe6; }
    .dr-ledger { border:1px solid #e3ebf4; border-radius:16px; background:#fff; overflow:hidden; box-shadow:0 1px 2px rgba(22,50,92,.04), 0 10px 28px rgba(22,50,92,.04); }
    .dr-toolbar { display:flex; align-items:center; gap:10px; padding:12px 16px; border-bottom:1px solid #e7eef6; background:#f7f8fa; flex-wrap:wrap; }
    .dr-search { display:flex; align-items:center; gap:8px; flex:1; min-width:220px; background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:0 12px; min-height:40px; transition:border-color .15s ease, box-shadow .15s ease; }
    .dr-search:focus-within { border-color:var(--mf-primary); box-shadow:0 0 0 3px rgba(23,107,91,.16); }
    .dr-search i { color:#8b9bb0; font-size:15px; }
    .dr-search input { border:0; outline:0; box-shadow:none !important; background:transparent; width:100%; padding:8px 0; font-size:.92rem; color:#1b2430; }
    .dr-search input::placeholder { color:#9aa8b8; }
    .dr-status, .dr-select {
      min-height:40px; border:1px solid #e5e7eb; border-radius:10px; color:#374151; font-size:.9rem; background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 16 16'%3E%3Cpath fill='%236b7280' d='M3.2 5.5 8 10.3 12.8 5.5'/%3E%3C/svg%3E") no-repeat right 12px center;
      padding:0 32px 0 12px; appearance:none; transition:border-color .15s ease, box-shadow .15s ease;
    }
    .dr-status { width:148px; font-weight:600; }
    .dr-status:hover, .dr-status:focus, .dr-select:hover, .dr-select:focus { border-color:var(--mf-primary); box-shadow:0 0 0 3px rgba(23,107,91,.12); outline:0; }
    .dr-name { font-weight:700; color:#1b2430; }
    .dr-sub { display:block; color:#8b9bb0; font-size:.75rem; font-weight:600; }
    .dr-ledger .table-mf { margin:0; }
    .dr-ledger .table-mf thead th { background:#f7f9fc; color:#7b8798; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; border-bottom:1px solid #e7eef6; padding:12px 14px; white-space:nowrap; }
    .dr-ledger .table-mf tbody td { border-bottom:1px solid #f0f4f8; padding:13px 14px; vertical-align:middle; }
    .dr-ledger .table-mf tbody tr:hover td { background:#f4faf8; }
    .dr-foot { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:12px 16px; border-top:1px solid #e3ebf4; }
    .dr-foot .pagination { gap:4px; }
    .dr-foot .page-link { border:1px solid #e3ebf4; color:#516278; border-radius:8px; min-width:32px; text-align:center; font-weight:650; padding:.3rem .55rem; transition:background .15s ease, color .15s ease, border-color .15s ease; }
    .dr-foot .page-item.active .page-link { background:var(--mf-primary); border-color:var(--mf-primary); color:#fff; }
    .dr-foot .page-link:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-color:var(--mf-primary); }
    .dr-note { color:#8b9bb0; font-size:.78rem; line-height:1.45; padding:10px 16px 12px; margin:0; border-top:1px solid #eef3f8; background:#fbfcfe; }
    .dr-kebab { width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; padding:0; transition:background .15s ease, color .15s ease, border-color .15s ease; }
    .btn.dr-kebab:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-color:var(--mf-primary); }
    .dr-menu { min-width:180px; padding:6px; border:1px solid #e7edf4; border-radius:12px; box-shadow:0 12px 32px rgba(16,32,64,.14); }
    .dr-menu .dropdown-item { display:flex; align-items:center; gap:10px; font-size:.84rem; font-weight:600; border-radius:8px; padding:.48rem .65rem; transition:background .15s ease, color .15s ease; }
    .dr-menu .dropdown-item i { width:1.05rem; color:var(--mf-primary); }
    .dr-menu .dropdown-item:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); }
    .dr-modal .modal-content { border:0; border-radius:16px; }
    .dr-modal .modal-header { padding:16px 20px; border-bottom:1px solid #eef2f6; }
    .dr-modal .modal-title { font-size:1.05rem; font-weight:700; }
    .dr-modal .modal-body { padding:18px 20px 8px; }
    .dr-modal .modal-footer { border-top:1px solid #eef2f6; padding:12px 20px; }
    .dr-label { display:block; font-size:.78rem; font-weight:600; color:#374151; margin-bottom:6px; }
    .dr-label .req { color:#dc3545; }
    .dr-modal .form-control, .dr-modal .dr-select { border:1px solid #e5e7eb; border-radius:10px; min-height:40px; font-size:.9rem; color:#1b2430; background-color:#fff; box-shadow:none; }
    .dr-modal .form-control::placeholder { color:#9aa3af; }
    .dr-modal .form-control:focus { border-color:var(--mf-primary); box-shadow:0 0 0 3px rgba(23,107,91,.16); }
    .dr-cancel { border:0; background:transparent; color:#374151; font-weight:650; padding:8px 12px; border-radius:8px; transition:background .15s ease, color .15s ease; }
    .dr-cancel:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); }
    .dr-save { border-radius:10px; padding:8px 14px; }
    .dr-save:hover { transform:translateY(-1px); }
  </style>
</head>
<body data-page="doctors">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">
        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-heart-pulse me-2 text-success"></i>Doctors</h1>
            <p class="page-sub" id="drCount">Prescribing doctors used on bills and prescriptions.</p>
          </div>
          <div class="ms-auto">
            <button class="btn btn-mf" id="drAdd" type="button"><i class="bi bi-plus-lg me-1"></i>Add Doctor</button>
          </div>
        </div>

        <div class="row g-3 mb-3" id="drStats"></div>

        <div class="card-mf dr-ledger">
          <div class="dr-toolbar">
            <label class="dr-search">
              <i class="bi bi-search"></i>
              <input id="drSearch" type="search" placeholder="Search name, reg no, specialty, clinic…" autocomplete="off">
            </label>
            <select class="dr-status" id="drStatus" aria-label="All status">
              <option value="all">All status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div class="table-scroll" style="max-height:none;overflow:visible">
            <table class="table table-mf">
              <thead>
                <tr>
                  <th>Doctor</th>
                  <th>Registration</th>
                  <th>Specialization</th>
                  <th>Mobile</th>
                  <th>Clinic / Hospital</th>
                  <th>Status</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="drBody"></tbody>
            </table>
          </div>
          <div class="dr-foot">
            <span class="text-2 small" id="drPageInfo"></span>
            <ul class="pagination pagination-sm mb-0" id="drPager"></ul>
          </div>
          <p class="dr-note">A doctor saved here can be selected on a prescription or a retail bill. Marking a doctor inactive hides them from new bills without deleting old ones.</p>
        </div>
      </main>
    </div>
  </div>

  <div class="modal fade dr-modal" id="drModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="drTitle">Add Doctor</h5>
          <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="dr-label" for="drName">Doctor Name <span class="req">*</span></label>
              <input class="form-control" id="drName" placeholder="e.g. Dr. Meena Devi" autocomplete="off">
            </div>
            <div class="col-md-8">
              <label class="dr-label" for="drReg">Registration Number <span class="req">*</span></label>
              <input class="form-control" id="drReg" placeholder="e.g. BMC/2013/03157" autocomplete="off">
            </div>
            <div class="col-md-4">
              <label class="dr-label" for="drSpec">Specialization</label>
              <input class="form-control" id="drSpec" placeholder="e.g. Pediatrician" autocomplete="off">
            </div>
            <div class="col-md-4">
              <label class="dr-label" for="drPhone">Mobile</label>
              <input class="form-control" id="drPhone" inputmode="tel" placeholder="+91 ..." autocomplete="off">
            </div>
            <div class="col-md-4">
              <label class="dr-label" for="drEmail">Email</label>
              <input class="form-control" id="drEmail" type="email" inputmode="email" placeholder="name@clinic.in" autocomplete="off">
            </div>
            <div class="col-md-4">
              <label class="dr-label" for="drClinic">Clinic / Hospital</label>
              <input class="form-control" id="drClinic" placeholder="Clinic name" autocomplete="off">
            </div>
            <div class="col-md-8">
              <label class="dr-label" for="drAddress">Address</label>
              <input class="form-control" id="drAddress" placeholder="Area, city, pin" autocomplete="off">
            </div>
            <div class="col-md-4">
              <label class="dr-label" for="drFormStatus">Status</label>
              <select class="dr-select w-100" id="drFormStatus">
                <option value="Active" selected>Active</option>
                <option value="Inactive">Inactive</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="dr-cancel" data-bs-dismiss="modal" type="button">Cancel</button>
          <button class="btn btn-mf dr-save" id="drSave" type="button"><i class="bi bi-check-lg me-1"></i>Save Doctor</button>
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
      const state = { rows: [], q: '', status: 'all', page: 1, per: 12, editId: null, seq: 1 };

      function badge(status) {
        return MF.badge(status || 'Active', status === 'Inactive' ? 'secondary' : 'success');
      }

      function filtered() {
        const q = state.q.toLowerCase();
        return state.rows.filter((r) => {
          if (state.status !== 'all' && String(r.status || 'Active').toLowerCase() !== state.status) return false;
          if (!q) return true;
          return [r.name, r.reg_no, r.regNo, r.specialty, r.clinic, r.phone, r.email].join(' ').toLowerCase().includes(q);
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
        const active = state.rows.filter((r) => (r.status || 'Active') === 'Active').length;
        $('#drCount').textContent = `${state.rows.length} doctor${state.rows.length === 1 ? '' : 's'} on file`;
        $('#drStats').innerHTML = `
          <div class="col-6 col-md-4"><div class="dr-stat accent"><span>Doctors</span><strong>${MF.num(state.rows.length)}</strong></div></div>
          <div class="col-6 col-md-4"><div class="dr-stat"><span>Active</span><strong>${MF.num(active)}</strong></div></div>
          <div class="col-12 col-md-4"><div class="dr-stat"><span>Inactive</span><strong>${MF.num(state.rows.length - active)}</strong></div></div>`;
        $('#drBody').innerHTML = slice.map((r) => `<tr>
          <td><span class="dr-name">${MF.esc(r.name || '—')}</span>${r.email ? `<span class="dr-sub">${MF.esc(r.email)}</span>` : ''}</td>
          <td class="num">${MF.esc(r.reg_no || r.regNo || '—')}</td>
          <td>${r.specialty ? MF.esc(r.specialty) : '<span class="text-2">—</span>'}</td>
          <td>${r.phone ? MF.esc(r.phone) : '<span class="text-2">—</span>'}</td>
          <td>${r.clinic ? MF.esc(r.clinic) : '<span class="text-2">—</span>'}${r.address ? `<span class="dr-sub">${MF.esc(r.address)}</span>` : ''}</td>
          <td>${badge(r.status || 'Active')}</td>
          <td class="text-end">
            <div class="dropdown">
              <button type="button" class="btn btn-icon btn-light-mf dr-kebab" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-label="Actions"><i class="bi bi-three-dots-vertical"></i></button>
              <ul class="dropdown-menu dropdown-menu-end dr-menu">
                <li><button type="button" class="dropdown-item" data-a="edit" data-id="${MF.esc(r.id)}"><i class="bi bi-pencil"></i><span>Edit</span></button></li>
                <li><button type="button" class="dropdown-item" data-a="toggle" data-id="${MF.esc(r.id)}"><i class="bi bi-pause-circle"></i><span>${(r.status || 'Active') === 'Active' ? 'Mark inactive' : 'Mark active'}</span></button></li>
              </ul>
            </div>
          </td>
        </tr>`).join('') || '<tr><td colspan="7"><div class="empty-state"><i class="bi bi-heart-pulse"></i>No doctors yet. Add one to use them on prescriptions and bills.</div></td></tr>';
        const start = slice.length ? (state.page - 1) * state.per + 1 : 0;
        $('#drPageInfo').textContent = `Showing ${start}–${(state.page - 1) * state.per + slice.length} of ${list.length}`;
        $('#drPager').innerHTML = pageButtons(pages, state.page);
        $('#drPager').querySelectorAll('[data-pg]').forEach((b) => b.addEventListener('click', () => { state.page = +b.dataset.pg; render(); }));
        $('#drBody').querySelectorAll('[data-a]').forEach((b) => b.addEventListener('click', () => onAction(b.dataset.a, b.dataset.id)));
      }

      function resetForm() {
        state.editId = null;
        $('#drTitle').textContent = 'Add Doctor';
        $('#drName').value = '';
        $('#drReg').value = '';
        $('#drSpec').value = '';
        $('#drPhone').value = '';
        $('#drEmail').value = '';
        $('#drClinic').value = '';
        $('#drAddress').value = '';
        $('#drFormStatus').value = 'Active';
      }

      function openAdd() {
        resetForm();
        bootstrap.Modal.getOrCreateInstance($('#drModal')).show();
        setTimeout(() => $('#drName').focus(), 200);
      }

      function openEdit(row) {
        state.editId = row.id;
        $('#drTitle').textContent = 'Edit Doctor';
        $('#drName').value = row.name || '';
        $('#drReg').value = row.reg_no || row.regNo || '';
        $('#drSpec').value = row.specialty || '';
        $('#drPhone').value = row.phone || '';
        $('#drEmail').value = row.email || '';
        $('#drClinic').value = row.clinic || '';
        $('#drAddress').value = row.address || '';
        $('#drFormStatus').value = row.status === 'Inactive' ? 'Inactive' : 'Active';
        bootstrap.Modal.getOrCreateInstance($('#drModal')).show();
      }

      function payload() {
        return {
          name: $('#drName').value.trim(),
          reg_no: $('#drReg').value.trim(),
          regNo: $('#drReg').value.trim(),
          specialty: $('#drSpec').value.trim(),
          phone: $('#drPhone').value.trim(),
          email: $('#drEmail').value.trim(),
          clinic: $('#drClinic').value.trim(),
          address: $('#drAddress').value.trim(),
          status: $('#drFormStatus').value
        };
      }

      async function save() {
        const body = payload();
        if (!body.name) { MF.toast('Doctor name is required.', 'warn', 'Doctor'); $('#drName').focus(); return; }
        if (!body.reg_no) { MF.toast('Registration number is required.', 'warn', 'Doctor'); $('#drReg').focus(); return; }
        if (body.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(body.email)) {
          MF.toast('Enter a valid email address.', 'warn', 'Doctor');
          $('#drEmail').focus();
          return;
        }
        if (MF.Api.live && !String(state.editId || '').startsWith('demo-')) {
          if (state.editId) await MF.Api.put('doctors.php', Object.assign({ id: state.editId }, body));
          else await MF.Api.post('doctors.php', body);
          MF.toast(state.editId ? 'Doctor updated' : 'Doctor saved', 'success', 'Saved');
          bootstrap.Modal.getInstance($('#drModal')).hide();
          if (MF.rehydrate) await MF.rehydrate();
          await load();
          return;
        }
        if (state.editId) {
          const row = state.rows.find((r) => String(r.id) === String(state.editId));
          if (row) Object.assign(row, body, { reg_no: body.reg_no, regNo: body.reg_no });
        } else {
          state.rows.unshift(Object.assign({ id: 'demo-' + state.seq }, body));
          state.seq += 1;
        }
        MF.toast('Saved in this browser only. Run the migration to store it.', 'info', 'Demo');
        bootstrap.Modal.getInstance($('#drModal')).hide();
        render();
      }

      async function onAction(action, id) {
        const row = state.rows.find((r) => String(r.id) === String(id));
        if (!row) return;
        if (action === 'edit') return openEdit(row);
        const next = (row.status || 'Active') === 'Active' ? 'Inactive' : 'Active';
        const body = {
          id: row.id,
          name: row.name,
          reg_no: row.reg_no || row.regNo,
          regNo: row.reg_no || row.regNo,
          specialty: row.specialty || '',
          phone: row.phone || '',
          email: row.email || '',
          clinic: row.clinic || '',
          address: row.address || '',
          status: next
        };
        if (MF.Api.live && !String(id).startsWith('demo-')) {
          await MF.Api.put('doctors.php', body);
          await load();
        } else {
          row.status = next;
          render();
        }
        MF.toast(row.name + ' marked ' + next.toLowerCase(), 'success', 'Updated');
      }

      function fromBootstrap() {
        return (D.doctors || []).map((d) => ({
          id: d.id,
          name: d.name || '',
          specialty: d.specialty || '',
          phone: d.phone || '',
          reg_no: d.reg_no || d.regNo || '',
          email: d.email || '',
          clinic: d.clinic || '',
          address: d.address || '',
          status: d.status || 'Active'
        }));
      }

      async function load() {
        if (!MF.Api.live) {
          if (!state.rows.length) state.rows = fromBootstrap();
          render();
          return;
        }
        try {
          const res = await MF.Api.get('doctors.php');
          const data = res.data;
          state.rows = Array.isArray(data) ? data : (data && data.doctors) || [];
        } catch (e) {
          MF.toast(e.message, 'err', 'Could not load doctors');
          state.rows = fromBootstrap();
        }
        render();
      }

      document.addEventListener('DOMContentLoaded', async () => {
        await MF.boot();
        $('#drAdd').addEventListener('click', openAdd);
        $('#drSave').addEventListener('click', () => save().catch((e) => MF.toast(e.message, 'err', 'Could not save')));
        $('#drSearch').addEventListener('input', (e) => { state.q = e.target.value; state.page = 1; render(); });
        $('#drStatus').addEventListener('change', (e) => { state.status = e.target.value; state.page = 1; render(); });
        await load();
      });
    })();
  </script>
</body>
</html>
