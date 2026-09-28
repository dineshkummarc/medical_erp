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
  <title>Customers · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .cu-modal .form-control, .cu-modal .form-select { border-radius:10px; min-height:40px; border-color:#e5e7eb; transition:border-color .15s ease, box-shadow .15s ease; }
    .cu-modal .form-control:hover, .cu-modal .form-select:hover { border-color:var(--mf-primary); }
    .cu-modal .form-control:focus, .cu-modal .form-select:focus { border-color:var(--mf-primary); box-shadow:0 0 0 3px rgba(23,107,91,.16); }
    .cu-save, .cu-edit { border-radius:10px; transition:background .15s ease, color .15s ease, border-color .15s ease, transform .15s ease; }
    .cu-save:hover, .cu-edit:hover { transform:translateY(-1px); }
    .cu-edit:hover { background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-color:var(--mf-primary); }
    .cu-addr { display:block; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#516278; }
    .cu-type { display:inline-flex; align-items:center; margin-top:4px; border:1px solid #d7ebe6; background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-radius:4px; padding:1px 6px; font-size:.72rem; font-weight:700; letter-spacing:.01em; }
  </style>
</head>
<body data-page="customers">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-people me-2 text-success"></i>Customers</h1>
            <p class="page-sub" id="cuCount"></p>
          </div>
          <div class="ms-auto d-flex gap-2">
            <button class="btn btn-mf" id="cuAddBtn"><i class="bi bi-person-plus me-1"></i>Add Customer</button>
          </div>
        </div>

        <div class="row g-3 mb-3" id="cuKpis"></div>

        <div class="card-mf p-3 mb-3">
          <div class="row g-2">
            <div class="col-md-6">
              <div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span>
              <input class="form-control" id="cuSearch" placeholder="Search name, business, phone, GSTIN…"></div>
            </div>
            <div class="col-md-4">
              <select class="form-select" id="cuDue">
                <option value="">All Dues</option>
                <option value="due">Has Due</option>
                <option value="clear">Dues Cleared</option>
              </select>
            </div>
          </div>
        </div>

        <div class="card-mf">
          <div class="table-scroll" style="max-height:none">
            <table class="table table-mf">
              <thead>
                <tr>
                  <th>Customer Name</th><th>Phone</th><th>GSTIN</th><th>Address</th>
                  <th class="text-end">Total Sales</th><th class="text-end">Paid</th><th class="text-end">Due</th>
                  <th>Last Purchase</th><th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="cuBody"></tbody>
            </table>
          </div>
        </div>

      </main>
    </div>
  </div>

  <!-- Add customer modal -->
  <div class="modal fade cu-modal" id="cuAddModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="cuFormTitle">Add Customer</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label">Customer Name <span class="req">*</span></label><input class="form-control" id="cuName" placeholder="Contact person"></div>
          <div class="mb-2"><label class="form-label" for="cuBiz">Business name</label><input class="form-control" id="cuBiz" placeholder="Hospital, clinic or shop"></div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label">Phone</label><input class="form-control" id="cuPhone" placeholder="98xxx xxxxx"></div>
            <div class="col-6"><label class="form-label" for="cuType">Customer type</label>
              <select class="form-select" id="cuType">
                <option value="retail">Retail Customer</option>
                <option value="wholesale">Wholesale Dealer</option>
                <option value="Hospital">Hospital</option>
                <option value="Clinic">Clinic</option>
                <option value="Others">Others</option>
              </select>
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label">GSTIN</label><input class="form-control" id="cuGstin" placeholder="Optional for retail"></div>
            <div class="col-6"><label class="form-label">Drug License No.</label><input class="form-control" id="cuDl" placeholder="Optional"></div>
          </div>
          <div><label class="form-label">Address</label><textarea class="form-control" id="cuAddr" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf cu-save" id="cuAddSave"><i class="bi bi-check2 me-1"></i>Save Customer</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Profile modal -->
  <div class="modal fade" id="cuProfileModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="cuProfileTitle"></h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <ul class="nav nav-pills-mf mb-3" id="cuProfileTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#cuTabOverview">Overview</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#cuTabSales">Sales History</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#cuTabPayments">Payments</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#cuTabDues">Dues</button></li>
          </ul>
          <div class="tab-content" id="cuProfileBody"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-mf-outline cu-edit" id="cuEditBtn" type="button"><i class="bi bi-pencil me-1"></i>Edit</button>
          <button class="btn btn-mf" id="cuReceiveBtn"><i class="bi bi-cash-coin me-1"></i>Receive Payment</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Receive payment modal -->
  <div class="modal fade" id="cuPayModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-mf-sm">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Receive Payment</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="alert alert-light border py-2 small" id="cuPayDueInfo"></div>
          <div class="mb-2"><label class="form-label">Amount (₹) <span class="req">*</span></label><input type="number" class="form-control" id="cuPayAmt" min="1"></div>
          <div><label class="form-label">Mode</label>
            <select class="form-select" id="cuPayMode"><option value="Cash">Cash</option><option value="UPI">UPI</option><option value="Bank">Bank Transfer</option><option value="Cheque">Cheque</option></select></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="cuPaySave">Record Payment</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/app.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', async () => {
      await MF.boot();
    (function () {
      const MF = window.MF, D = window.MF_DATA;
      const $ = (s) => document.querySelector(s);
      let current = null;
      let editId = null;
      let reopenProfile = false;
      const savedRows = {};

      function remember(id, fields) {
        if (id == null || id === '') return;
        savedRows[String(id)] = Object.assign({}, savedRows[String(id)] || {}, fields);
      }

      function applySaved() {
        (D.customers || []).forEach((c) => {
          const saved = savedRows[String(c.id)];
          if (saved) Object.assign(c, saved);
        });
      }

      function typeLabel(value) {
        return { retail: 'Retail Customer', wholesale: 'Wholesale Dealer', Hospital: 'Hospital', Clinic: 'Clinic', Others: 'Others' }[value] || value || '—';
      }

      function typeValue(value) {
        const key = String(value || '').toLowerCase();
        return { retail: 'retail', wholesale: 'wholesale', hospital: 'Hospital', clinic: 'Clinic', others: 'Others', other: 'Others' }[key] || 'retail';
      }

      function list() { return D.customers.filter((c) => c.name !== 'Walk-in Customer'); }

      function filtered() {
        const q = $('#cuSearch').value.toLowerCase();
        const due = $('#cuDue').value;
        return list().filter((c) => {
          const biz = c.business_name || c.businessName || '';
          if (q && !(c.name + biz + (c.phone || '') + (c.gstin || '') + (c.address || '') + typeLabel(c.type)).toLowerCase().includes(q)) return false;
          if (due === 'due' && c.due <= 0) return false;
          if (due === 'clear' && c.due > 0) return false;
          return true;
        });
      }

      function renderKpis() {
        const l = list();
        const totalDue = l.reduce((s, c) => s + c.due, 0);
        const withDue = l.filter((c) => c.due > 0).length;
        $('#cuKpis').innerHTML = [
          ['Total Customers', l.length, 'primary', 'people'],
          ['Lifetime Sales', MF.fmt(l.reduce((s, c) => s + c.totalSales, 0)), 'success', 'graph-up-arrow'],
          ['Outstanding Dues', MF.fmt(totalDue), 'danger', 'cash-stack'],
          ['Customers with Due', withDue, 'warning', 'exclamation-triangle']
        ].map(([lbl, v, tone, icon]) => `
          <div class="col-6 col-xl-3"><div class="card-mf kpi-card h-100">
            <div class="kpi-icon tone-${tone}"><i class="bi bi-${icon}"></i></div>
            <div><div class="kpi-label">${lbl}</div><div class="kpi-value num">${v}</div></div>
          </div></div>`).join('');
      }

      function render() {
        const l = filtered();
        $('#cuCount').textContent = `${list().length} customer(s)`;
        $('#cuBody').innerHTML = l.map((c) => {
          const biz = c.business_name || c.businessName || '';
          const addr = String(c.address || '').replace(/\s+/g, ' ').trim();
          const kind = typeLabel(c.type);
          const gstin = c.gstin || '';
          return `
          <tr>
            <td><div class="td-title">${MF.esc(c.name)}</div>${biz ? `<div class="td-sub">${MF.esc(biz)}</div>` : ''}</td>
            <td><div class="num">${MF.esc(c.phone || '—')}</div>${kind && kind !== '—' ? `<span class="cu-type">${MF.esc(kind)}</span>` : ''}</td>
            <td class="num">${gstin ? MF.esc(gstin) : '<span class="text-2">—</span>'}</td>
            <td>${addr ? `<span class="cu-addr" title="${MF.esc(addr)}">${MF.esc(addr)}</span>` : '<span class="text-2">—</span>'}</td>
            <td class="text-end num">${MF.fmt(c.totalSales)}</td>
            <td class="text-end num text-success">${MF.fmt(c.paid)}</td>
            <td class="text-end num fw-semibold ${c.due ? 'text-danger' : ''}">${MF.fmt(c.due)}</td>
            <td class="num text-2">${c.lastPurchase ? MF.fmtDate(c.lastPurchase) : '—'}</td>
            <td class="text-end row-actions">
              <button class="btn btn-sm btn-mf-soft" data-view="${c.id}">Profile</button>
            </td>
          </tr>`;
        }).join('') || `<tr><td colspan="9"><div class="empty-state"><i class="bi bi-people"></i>No customers match the filters.</div></td></tr>`;
        $('#cuBody').querySelectorAll('[data-view]').forEach((b) => b.addEventListener('click', () => openProfile(b.dataset.view)));
      }

      async function openProfile(id) {
        current = MF.cust(Number(id));
        const c = current;
        $('#cuProfileTitle').textContent = c.name;
        $('#cuProfileBody').innerHTML = `<div class="text-center text-2 p-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>`;
        new bootstrap.Modal($('#cuProfileModal')).show();

        const ledger = await MF.Api.get('party-ledger.php?type=customer&id=' + c.id);
        const { invoices, payments } = ledger.data ?? ledger; // tolerate either shape

        $('#cuProfileBody').innerHTML = `
          <div class="tab-pane fade show active" id="cuTabOverview">
            <div class="row g-3 mb-3">
              ${[['Phone', MF.esc(c.phone || '—')], ['Business', MF.esc(c.business_name || c.businessName || '—')],
                 ['Customer type', MF.esc(typeLabel(c.type))], ['Address', MF.esc(c.address || '—')],
                 ['Total Sales', MF.fmt(c.totalSales)], ['Received', MF.fmt(c.paid)],
                 ['Outstanding Due', MF.fmt(c.due)], ['Last Purchase', c.lastPurchase ? MF.fmtDate(c.lastPurchase) : '—']].map(([k, v]) =>
                `<div class="col-md-4 col-6"><div class="kpi-label">${k}</div><div class="fw-semibold num">${v}</div></div>`).join('')}
            </div>
            ${c.due > 0 ? `<div class="alert alert-light border d-flex align-items-center gap-2 small mb-0">
              <i class="bi bi-exclamation-triangle text-warning"></i>
              <span>Outstanding <strong>${MF.fmt(c.due)}</strong> — use "Receive Payment" to post a receipt.</span></div>` : ''}
          </div>
          <div class="tab-pane fade" id="cuTabSales">
            ${invoices.length ? `<table class="table table-mf border rounded"><thead><tr><th>Invoice</th><th>Date</th><th class="text-end">Amount</th><th>Payment</th><th>Status</th></tr></thead><tbody>
              ${invoices.map((i) => `<tr><td class="num td-title">${i.no}</td><td class="num">${MF.fmtDate(i.date)}</td><td class="text-end num">${MF.fmt(i.amount)}</td><td>${i.mode}</td><td>${MF.statusBadge(i.status)}</td></tr>`).join('')}
            </tbody></table>` : `<div class="empty-state"><i class="bi bi-receipt"></i>No sales recorded yet.</div>`}
          </div>
          <div class="tab-pane fade" id="cuTabPayments">
            ${payments.length ? `<table class="table table-mf border rounded"><thead><tr><th>Date</th><th>Mode</th><th>Note</th><th class="text-end">Amount</th></tr></thead><tbody>
              ${payments.map((p) => `<tr><td class="num">${MF.fmtDate(p.date)}</td><td>${p.mode}</td><td class="text-2">${MF.esc(p.note || '—')}</td><td class="text-end num">${MF.fmt(p.amount)}</td></tr>`).join('')}
            </tbody></table>` : `<div class="empty-state"><i class="bi bi-cash-coin"></i>No payments recorded yet.</div>`}
          </div>
          <div class="tab-pane fade" id="cuTabDues">
            ${invoices.filter((i) => i.due > 0).length ? `<table class="table table-mf border rounded"><thead><tr><th>Invoice</th><th>Date</th><th class="text-end">Total</th><th class="text-end">Due</th></tr></thead><tbody>
              ${invoices.filter((i) => i.due > 0).map((i) => `<tr><td class="num td-title">${i.no}</td><td class="num">${MF.fmtDate(i.date)}</td><td class="text-end num">${MF.fmt(i.amount)}</td><td class="text-end num text-danger fw-semibold">${MF.fmt(i.due)}</td></tr>`).join('')}
            </tbody></table>` : `<div class="empty-state"><i class="bi bi-check-circle"></i>No outstanding dues. Account is clear.</div>`}
          </div>`;
      }

      $('#cuReceiveBtn').addEventListener('click', () => {
        if (!current) return;
        if (current.due <= 0) { MF.toast(current.name + ' has no outstanding dues.', 'info', 'No dues'); return; }
        $('#cuPayDueInfo').innerHTML = `<i class="bi bi-info-circle me-1"></i>${MF.esc(current.name)} owes <strong>${MF.fmt(current.due)}</strong>`;
        $('#cuPayAmt').value = current.due;
        new bootstrap.Modal($('#cuPayModal')).show();
      });
      $('#cuPaySave').addEventListener('click', async () => {
        const amt = parseFloat($('#cuPayAmt').value) || 0;
        if (amt <= 0) { MF.toast('Enter a valid amount.', 'warn'); return; }
        try {
          await MF.Api.post('payments.php', { partyType: 'customer', partyId: current.id, amount: amt, mode: $('#cuPayMode').value });
          bootstrap.Modal.getInstance($('#cuPayModal')).hide();
          bootstrap.Modal.getInstance($('#cuProfileModal')).hide();
          MF.toast(`${MF.fmt(amt)} received from ${current.name}.`, 'success', 'Payment recorded');
          await MF.rehydrate();
          applySaved();
          renderKpis(); render();
        } catch (err) {
          MF.toast(err.message || 'Could not record payment.', 'danger');
        }
      });

      function openForm(row) {
        editId = row ? row.id : null;
        $('#cuFormTitle').textContent = row ? 'Edit Customer' : 'Add Customer';
        $('#cuAddSave').innerHTML = row
          ? '<i class="bi bi-check2 me-1"></i>Update Customer'
          : '<i class="bi bi-check2 me-1"></i>Save Customer';
        $('#cuName').value = row ? (row.name || '') : '';
        $('#cuBiz').value = row ? (row.business_name || row.businessName || '') : '';
        $('#cuPhone').value = row ? (row.phone || '') : '';
        $('#cuType').value = row ? typeValue(row.type) : 'retail';
        $('#cuGstin').value = row ? (row.gstin || '') : '';
        $('#cuDl').value = row ? (row.dl_no || row.dlNo || '') : '';
        $('#cuAddr').value = row ? (row.address || '') : '';
        const profileEl = $('#cuProfileModal');
        const profileOpen = profileEl.classList.contains('show');
        if (row && profileOpen) {
          reopenProfile = true;
          profileEl.addEventListener('hidden.bs.modal', function showEdit() {
            profileEl.removeEventListener('hidden.bs.modal', showEdit);
            bootstrap.Modal.getOrCreateInstance($('#cuAddModal')).show();
          });
          bootstrap.Modal.getOrCreateInstance(profileEl).hide();
          return;
        }
        reopenProfile = false;
        bootstrap.Modal.getOrCreateInstance($('#cuAddModal')).show();
      }

      $('#cuAddModal').addEventListener('hidden.bs.modal', () => {
        if (!reopenProfile || !current) return;
        reopenProfile = false;
        openProfile(current.id);
      });

      $('#cuAddBtn').addEventListener('click', () => openForm(null));
      $('#cuEditBtn').addEventListener('click', () => { if (current) openForm(current); });
      $('#cuAddSave').addEventListener('click', async () => {
        const name = $('#cuName').value.trim();
        const savingId = editId;
        if (!name) { MF.toast('Customer name is required.', 'err', 'Validation'); return; }
        const body = {
          name, type: $('#cuType').value, phone: $('#cuPhone').value.trim(),
          business_name: $('#cuBiz').value.trim(),
          gstin: $('#cuGstin').value.trim(), dlNo: $('#cuDl').value.trim(), address: $('#cuAddr').value.trim(),
        };
        try {
          const saved = savingId
            ? await MF.Api.put('customers.php', Object.assign({ id: savingId }, body))
            : await MF.Api.post('customers.php', body);
          const id = saved.id || savingId;
          const extra = {
            name,
            type: body.type,
            business_name: body.business_name,
            businessName: body.business_name,
            gstin: body.gstin,
            dl_no: body.dlNo,
            dlNo: body.dlNo,
            address: body.address,
            phone: body.phone
          };
          remember(id, extra);
          const backToProfile = reopenProfile;
          reopenProfile = false;
          const editEl = $('#cuAddModal');
          const closed = new Promise((resolve) => {
            editEl.addEventListener('hidden.bs.modal', function once() {
              editEl.removeEventListener('hidden.bs.modal', once);
              resolve();
            });
          });
          bootstrap.Modal.getInstance(editEl).hide();
          await closed;
          MF.toast(savingId ? 'Customer updated' : name + ' added to customer master.', 'success', savingId ? 'Saved' : 'Customer created');
          if (MF.rehydrate) await MF.rehydrate();
          await loadProfiles();
          applySaved();
          const hit = (D.customers || []).find((c) => String(c.id) === String(id));
          if (hit) Object.assign(hit, extra);
          else if (id) D.customers.push(Object.assign({ id, due: 0, paid: 0, totalSales: 0, lastPurchase: null }, extra));
          if (current && String(current.id) === String(id)) Object.assign(current, extra);
          renderKpis(); render();
          if (backToProfile) openProfile(id);
        } catch (err) {
          MF.toast(err.message || 'Could not save the customer.', 'danger');
        }
      });

      ['cuSearch', 'cuDue'].forEach((id) => $('#' + id).addEventListener('input', render));

      async function loadProfiles() {
        if (!MF.Api.live) return;
        try {
          const res = await MF.Api.get('customers.php');
          const rows = Array.isArray(res.data) ? res.data : [];
          rows.forEach((row) => {
            const hit = (D.customers || []).find((c) => String(c.id) === String(row.id));
            if (!hit) return;
            ['name', 'phone', 'address', 'type', 'gstin', 'business_name', 'dl_no'].forEach((key) => {
              if (Object.prototype.hasOwnProperty.call(row, key) && row[key] != null && row[key] !== '') hit[key] = row[key];
            });
            if (row.businessName && !hit.business_name) hit.business_name = row.businessName;
            if (row.dlNo && !hit.dl_no) hit.dl_no = row.dlNo;
          });
          applySaved();
        } catch (e) { /* ledger still uses the bootstrapped customers */ }
      }
      loadProfiles().then(() => { applySaved(); renderKpis(); render(); });
      renderKpis(); render();
    })();
    });
  </script>
</body>
</html>
