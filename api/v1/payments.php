<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/middleware/auth.php';
?>
<!doctype html>
<html lang="en">
<head>
  <!-- build 2026-10-08.23 - receipts & payments register (writes the ledger dues pages read) -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Payments · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .pm-hint { font-size:.66rem; color:#9AA3AF; margin-top:2px; }
    .pm-hint b { color:#64748B; font-weight:600; }
    .pm-typechip { font-size:.64rem; font-weight:700; letter-spacing:.02em; padding:2px 7px; border-radius:20px; }
    .pm-in  { color:#0F4D42; font-weight:600; }
    .pm-out { color:#B42318; font-weight:600; }
  </style>
</head>
<body data-page="payments">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-credit-card-2-front me-2 text-success"></i>Payments</h1>
            <p class="page-sub">Receipts &amp; payouts — every rupee in and out, with receipts</p>
          </div>
          <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">
            <input type="date" class="form-control form-control-sm" id="pmFrom" style="width:150px">
            <span class="text-2 small">to</span>
            <input type="date" class="form-control form-control-sm" id="pmTo" style="width:150px">
            <button class="btn btn-sm btn-light-mf" id="pmApply"><i class="bi bi-funnel me-1"></i>Apply</button>
            <button class="btn btn-sm btn-light-mf" id="pmPayBtn"><i class="bi bi-arrow-up-circle me-1"></i>Pay</button>
            <button class="btn btn-sm btn-mf" id="pmReceiveBtn"><i class="bi bi-arrow-down-circle me-1"></i>Receive</button>
          </div>
        </div>

        <div class="row g-3 mb-3" id="pmKpis"></div>

        <div class="card-mf">
          <div class="card-head">
            <h2 class="card-title"><i class="bi bi-list-ul"></i>Payment Register</h2>
            <div class="card-tools d-flex gap-2 flex-wrap">
              <select class="form-select form-select-sm" id="pmDirF" style="width:auto">
                <option value="">All directions</option><option value="in">Received</option><option value="out">Paid out</option>
              </select>
              <select class="form-select form-select-sm" id="pmTypeF" style="width:auto">
                <option value="">All parties</option><option value="customer">Customers</option><option value="supplier">Suppliers</option><option value="other">Others</option>
              </select>
              <select class="form-select form-select-sm" id="pmModeF" style="width:auto">
                <option value="">All modes</option><option>Cash</option><option>Bank</option><option>UPI</option><option>Cheque</option>
              </select>
              <input class="form-control form-control-sm" id="pmSearch" placeholder="Search party / note / receipt…" style="width:220px">
            </div>
          </div>
          <div class="table-scroll" style="max-height:none">
            <table class="table table-mf">
              <thead><tr>
                <th>Date</th><th>Receipt</th><th>Party</th><th>Mode</th><th>Note</th>
                <th class="text-end">Received</th><th class="text-end">Paid</th><th class="text-end">Actions</th>
              </tr></thead>
              <tbody id="pmBody"></tbody>
              <tfoot id="pmFoot"></tfoot>
            </table>
          </div>
        </div>

      </main>
    </div>
  </div>

  <!-- Record payment modal -->
  <div class="modal fade" id="pmModal" tabindex="-1" data-bs-focus="false">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="pmTitle">Record Payment</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-2 row-cols-2 mb-3">
            <div class="col pay-opt"><input type="radio" name="pmDir" id="pmDirIn" value="in" checked><label for="pmDirIn"><i class="bi bi-arrow-down-circle"></i>Receive</label></div>
            <div class="col pay-opt"><input type="radio" name="pmDir" id="pmDirOut" value="out"><label for="pmDirOut"><i class="bi bi-arrow-up-circle"></i>Pay out</label></div>
          </div>
          <div class="mb-2">
            <label class="form-label">Party type</label>
            <select class="form-select" id="pmPartyType">
              <option value="customer">Customer — collect against dues</option>
              <option value="supplier">Supplier — pay against dues</option>
              <option value="other">Other — misc receipt / payment</option>
            </select>
            <div class="pm-hint" id="pmPartyHint"></div>
          </div>
          <div class="mb-2" id="pmPartyRow">
            <label class="form-label">Party <span class="req">*</span></label>
            <select class="form-select" id="pmParty"></select>
            <div class="pm-hint" id="pmDueHint"></div>
          </div>
          <div class="mb-2" id="pmOtherRow" hidden>
            <label class="form-label">Name <span class="req">*</span></label>
            <input class="form-control" id="pmOtherName" placeholder="Who is this from / to?" maxlength="60">
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label">Amount (₹) <span class="req">*</span></label>
              <div class="d-flex gap-2">
                <input type="number" class="form-control" id="pmAmount" min="1" step="0.01">
                <button type="button" class="btn btn-light-mf" id="pmFillDue" title="Fill the full outstanding" hidden>Full</button>
              </div>
            </div>
            <div class="col-6"><label class="form-label">Payment Mode</label>
              <select class="form-select" id="pmMode"><option>Cash</option><option>Bank</option><option>UPI</option><option>Cheque</option></select></div>
          </div>
          <div class="row g-2">
            <div class="col-6"><label class="form-label">Date</label><input type="date" class="form-control" id="pmDate"></div>
            <div class="col-6"><label class="form-label">Note</label><input class="form-control" id="pmNote" placeholder="Optional" maxlength="160"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="pmSave"><i class="bi bi-check2 me-1"></i>Save &amp; Print Receipt</button>
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
      let rows = [];
      let dues = { customer: [], supplier: [], loaded: false };

      $('#pmFrom').value = MF.today();
      $('#pmTo').value = MF.today();

      const esc = (v) => MF.esc(String(v == null ? '' : v));
      const TYPE_LABEL = { customer: 'Customer', supplier: 'Supplier', other: 'Other' };

      function filtered() {
        const dir = $('#pmDirF').value, type = $('#pmTypeF').value, mode = $('#pmModeF').value;
        const q = $('#pmSearch').value.trim().toLowerCase();
        return rows.filter((r) =>
          (!dir || r.direction === dir) && (!type || r.partyType === type) && (!mode || r.mode === mode) &&
          (!q || (r.partyName + ' ' + r.note + ' ' + r.receiptNo).toLowerCase().includes(q)));
      }

      function render() {
        const list = filtered();
        const tin = list.filter((r) => r.direction === 'in').reduce((s, r) => s + r.amount, 0);
        const tout = list.filter((r) => r.direction === 'out').reduce((s, r) => s + r.amount, 0);
        $('#pmKpis').innerHTML = [
          ['Received', MF.fmt(tin), 'success', 'arrow-down-circle'],
          ['Paid out', MF.fmt(tout), 'danger', 'arrow-up-circle'],
          ['Net flow', (tin - tout >= 0 ? '+' : '−') + ' ' + MF.fmt(Math.abs(tin - tout)), 'primary', 'arrow-left-right'],
          ['Entries', list.length, 'info', 'receipt'],
        ].map(([l, v, tone, icon]) => `
          <div class="col-6 col-md-3"><div class="card-mf kpi-card h-100"><div class="kpi-icon tone-${tone}"><i class="bi bi-${icon}"></i></div>
            <div><div class="kpi-label">${l}</div><div class="kpi-value num">${v}</div></div></div></div>`).join('');

        $('#pmBody').innerHTML = list.map((r) => `
          <tr>
            <td class="num">${MF.fmtDate(r.date)}${r.time ? `<div class="pm-hint">${esc(r.time)}</div>` : ''}</td>
            <td class="num text-2">${esc(r.receiptNo)}</td>
            <td class="td-title">${esc(r.partyName || '—')}<div class="pm-hint"><span class="pm-typechip badge-soft-${r.direction === 'in' ? 'success' : 'danger'}">${r.direction === 'in' ? '↓ RECEIVED' : '↑ PAID'}</span> <span class="pm-typechip badge-soft-secondary">${TYPE_LABEL[r.partyType] || r.partyType}</span></div></td>
            <td>${esc(r.mode)}</td>
            <td class="text-2">${esc(r.note || '—')}</td>
            <td class="text-end num ${r.direction === 'in' ? 'pm-in' : ''}">${r.direction === 'in' ? MF.fmt(r.amount) : ''}</td>
            <td class="text-end num ${r.direction === 'out' ? 'pm-out' : ''}">${r.direction === 'out' ? MF.fmt(r.amount) : ''}</td>
            <td class="text-end row-actions">
              <button class="btn btn-icon btn-light-mf" data-print="${r.id}" title="Print receipt"><i class="bi bi-printer"></i></button>
              <button class="btn btn-icon btn-light-mf text-danger" data-del="${r.id}" title="Delete entry"><i class="bi bi-trash"></i></button>
            </td>
          </tr>`).join('') || `<tr><td colspan="8"><div class="empty-state"><i class="bi bi-credit-card-2-front"></i>No payments match this view.</div></td></tr>`;
        $('#pmFoot').innerHTML = list.length ? `<tr><td colspan="5">Shown totals</td><td class="text-end num pm-in">${MF.fmt(tin)}</td><td class="text-end num pm-out">${MF.fmt(tout)}</td><td></td></tr>` : '';
        $('#pmBody').querySelectorAll('[data-del]').forEach((b) => b.addEventListener('click', () => remove(+b.dataset.del)));
        $('#pmBody').querySelectorAll('[data-print]').forEach((b) => b.addEventListener('click', () => {
          const p = rows.find((x) => x.id === +b.dataset.print);
          if (p) printReceipt(p);
        }));
      }

      async function load() {
        try {
          const res = await MF.Api.get(`payments.php?from=${$('#pmFrom').value}&to=${$('#pmTo').value}`);
          rows = res.data || [];
        } catch (err) {
          rows = [];
          MF.toast(err.message || 'Could not load payments.', 'danger', 'Payments');
        }
        render();
      }

      async function remove(id) {
        const p = rows.find((x) => x.id === id);
        const ok = await MF.confirm({
          title: 'Delete this payment entry?',
          message: p ? `${esc(p.receiptNo)} · ${esc(p.partyName || 'Other')} · ₹${MF.fmt(p.amount)} — the dues ledger will adjust automatically.` : '',
          confirmText: 'Delete', tone: 'danger',
        });
        if (!ok) return;
        try {
          await MF.Api.del('payments.php?id=' + id);
          MF.toast('Payment removed.', 'success');
          dues.loaded = false;
          load();
        } catch (err) {
          MF.toast(err.message || 'Could not remove payment.', 'danger');
        }
      }

      /* ---- Record modal ---- */
      async function loadDues() {
        if (dues.loaded) return;
        $('#pmPartyHint').textContent = 'Reading outstanding dues…';
        try {
          const [cres, sres] = await Promise.allSettled([MF.Api.get('customer-dues.php'), MF.Api.get('supplier-dues.php')]);
          dues.customer = (cres.status === 'fulfilled' && cres.value && cres.value.data ? cres.value.data.customers : []) || [];
          dues.supplier = (sres.status === 'fulfilled' && sres.value && sres.value.data ? sres.value.data.suppliers : []) || [];
          dues.loaded = true;
          $('#pmPartyHint').textContent = '';
        } catch (e) {
          $('#pmPartyHint').textContent = 'Dues could not be read — names still selectable after a page refresh.';
        }
      }

      function partyList() {
        const type = $('#pmPartyType').value;
        if (type === 'customer') {
          return dues.customer.map((c) => ({
            id: c.customer_id, name: c.customer_name, due: +c.outstanding || 0,
          })).sort((a, b) => b.due - a.due || a.name.localeCompare(b.name));
        }
        if (type === 'supplier') {
          return dues.supplier.map((s) => ({
            id: s.supplier_id, name: s.supplier_name || s.name, due: +s.outstanding || 0,
          })).sort((a, b) => b.due - a.due || a.name.localeCompare(b.name));
        }
        return [];
      }

      function paintParty() {
        const type = $('#pmPartyType').value;
        const other = type === 'other';
        $('#pmPartyRow').hidden = other;
        $('#pmOtherRow').hidden = !other;
        $('#pmFillDue').hidden = true;
        $('#pmDueHint').innerHTML = '';
        if (other) {
          $('#pmPartyHint').textContent = 'Misc entries never touch any dues ledger.';
          return;
        }
        const list = partyList();
        $('#pmParty').innerHTML = '<option value="">— Select ' + type + ' —</option>' +
          list.map((p) => `<option value="${p.id}" data-due="${p.due}">${esc(p.name)}${p.due > 0.009 ? ' — dues ₹' + MF.fmt(p.due) : ' — settled'}</option>`).join('');
        $('#pmPartyHint').innerHTML = list.length
          ? `Direction is locked: <b>${type === 'customer' ? 'customers always pay you (receipt)' : 'you always pay suppliers (payout)'}</b>.`
          : `No ${type}s with dues history yet — add bills first.`;
        const forced = type === 'customer' ? 'in' : 'out';
        document.querySelector(`input[name="pmDir"][value="${forced}"]`).checked = true;
        document.querySelectorAll('input[name="pmDir"]').forEach((r) => { r.disabled = true; });
      }

      function dueOfSelected() {
        const opt = $('#pmParty').selectedOptions[0];
        const due = opt ? +opt.dataset.due || 0 : 0;
        if (due > 0.009) {
          $('#pmDueHint').innerHTML = `Outstanding: <b>₹${MF.fmt(due)}</b> — collecting above it parks the excess as advance.`;
          $('#pmFillDue').hidden = false;
        } else {
          $('#pmDueHint').innerHTML = $('#pmParty').value ? 'No outstanding — this will record as an advance.' : '';
          $('#pmFillDue').hidden = true;
        }
        return due;
      }

      async function openModal(direction) {
        const dir = direction === 'out' ? 'out' : 'in';
        document.querySelector(`input[name="pmDir"][value="${dir}"]`).checked = true;
        document.querySelectorAll('input[name="pmDir"]').forEach((r) => { r.disabled = false; });
        $('#pmPartyType').value = dir === 'out' ? 'supplier' : 'customer';
        $('#pmAmount').value = ''; $('#pmNote').value = ''; $('#pmOtherName').value = '';
        $('#pmDate').value = MF.today();
        $('#pmTitle').textContent = dir === 'out' ? 'Record Payout' : 'Record Receipt';
        new bootstrap.Modal($('#pmModal')).show();
        await loadDues();
        paintParty();
      }

      $('#pmReceiveBtn').addEventListener('click', () => openModal('in'));
      $('#pmPayBtn').addEventListener('click', () => openModal('out'));
      $('#pmPartyType').addEventListener('change', () => {
        const type = $('#pmPartyType').value;
        if (type === 'other') document.querySelectorAll('input[name="pmDir"]').forEach((r) => { r.disabled = false; });
        paintParty();
      });
      $('#pmParty').addEventListener('change', dueOfSelected);
      $('#pmFillDue').addEventListener('click', () => {
        const due = dueOfSelected();
        if (due > 0.009) $('#pmAmount').value = due.toFixed(2);
      });

      $('#pmSave').addEventListener('click', async () => {
        const amount = parseFloat($('#pmAmount').value) || 0;
        if (amount <= 0) { MF.toast('Enter a valid amount.', 'warn'); return; }
        const type = $('#pmPartyType').value;
        const payload = {
          partyType: type, amount,
          direction: document.querySelector('input[name="pmDir"]:checked').value,
          mode: $('#pmMode').value, date: $('#pmDate').value, note: $('#pmNote').value.trim(),
        };
        if (type === 'other') {
          const nm = $('#pmOtherName').value.trim();
          if (nm.length < 2) { MF.toast('Name who this payment is from / to.', 'warn'); return; }
          payload.partyName = nm;
        } else {
          if (!$('#pmParty').value) { MF.toast('Pick the ' + type + '.', 'warn'); return; }
          payload.partyId = parseInt($('#pmParty').value, 10);
        }
        $('#pmSave').disabled = true;
        try {
          const res = await MF.Api.post('payments.php', payload);
          const rec = res && res.data ? res.data : {};
          bootstrap.Modal.getInstance($('#pmModal')).hide();
          MF.toast(`${payload.direction === 'out' ? 'Paid' : 'Received'} — receipt ${rec.receiptNo || ''}`, 'success', 'Payment recorded');
          dues.loaded = false;
          await load();
          const saved = rows.find((x) => x.id === rec.id) || {
            ...rec, date: payload.date, time: '', direction: payload.direction, partyType: type,
            partyName: type === 'other' ? payload.partyName : ($('#pmParty').selectedOptions[0] || {}).textContent,
            amount, mode: payload.mode, note: payload.note, receiptNo: rec.receiptNo,
          };
          printReceipt(saved);
        } catch (err) {
          MF.toast(err.message || 'Could not record the payment.', 'danger');
        } finally {
          $('#pmSave').disabled = false;
        }
      });

      /* ---- Receipt printing (honest: only what the row can prove) ---- */
      function numWords(n) {
        n = Math.round(Math.abs(+n || 0));
        if (!n) return 'ZERO';
        const ONES = ['', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN', 'ELEVEN', 'TWELVE', 'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN', 'SEVENTEEN', 'EIGHTEEN', 'NINETEEN'];
        const TENS = ['', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY'];
        const two = (x) => (x < 20 ? ONES[x] : TENS[Math.floor(x / 10)] + (x % 10 ? ' ' + ONES[x % 10] : ''));
        const three = (x) => { const h = Math.floor(x / 100), r = x % 100; return (h ? ONES[h] + ' HUNDRED' + (r ? ' ' : '') : '') + (r ? two(r) : ''); };
        const parts = [];
        const cr = Math.floor(n / 1e7); n %= 1e7;
        const lk = Math.floor(n / 1e5); n %= 1e5;
        const th = Math.floor(n / 1e3); n %= 1e3;
        if (cr) parts.push(three(cr) + ' CRORE');
        if (lk) parts.push(two(lk) + ' LAKH');
        if (th) parts.push(two(th) + ' THOUSAND');
        if (n) parts.push(three(n));
        return parts.join(' ');
      }

      function printReceipt(p) {
        const store = D.store || {};
        const inn = p.direction !== 'out';
        const title = inn ? 'PAYMENT RECEIPT' : 'PAYMENT VOUCHER';
        const verb = inn ? 'Received with thanks from' : 'Paid to';
        MF.printHtml(`<style>
          @page { size: 80mm auto; margin: 4mm; }
          .p-rc { width:72mm; margin:0 auto; font-family:Arial, Helvetica, sans-serif; color:#111827; font-size:11px; line-height:1.45; }
          .p-h { text-align:center; border-bottom:1.5px dashed #111827; padding-bottom:6px; margin-bottom:6px; }
          .p-shop { font-size:14px; font-weight:800; }
          .p-sub { font-size:8.5px; color:#4b5563; }
          .p-tag { display:inline-block; border:1px solid #111827; font-weight:800; font-size:9px; letter-spacing:.14em; padding:2px 8px; margin:4px 0; }
          .p-kv { display:flex; justify-content:space-between; gap:8px; padding:1.5px 0; font-size:10px; }
          .p-kv span:first-child { color:#6b7280; }
          .p-amt { text-align:center; margin:8px 0 2px; }
          .p-amt b { font-size:20px; }
          .p-words { text-align:center; font-size:8.5px; color:#4b5563; text-transform:uppercase; }
          .p-sign { margin-top:22px; display:flex; justify-content:space-between; font-size:9px; }
          .p-sign div { border-top:1px solid #111827; padding-top:3px; min-width:26mm; text-align:center; }
          .p-ft { text-align:center; margin-top:8px; font-size:8.5px; color:#6b7280; }
        </style>
        <div class="p-rc">
          <div class="p-h">
            <div class="p-shop">${esc(store.name || 'Pharmacy')}</div>
            ${store.address ? `<div class="p-sub">${esc(store.address)}</div>` : ''}
            ${store.phone ? `<div class="p-sub">Ph: ${esc(store.phone)}</div>` : ''}
          </div>
          <div style="text-align:center"><span class="p-tag">${title}</span></div>
          <div class="p-kv"><span>Receipt No</span><b>${esc(p.receiptNo)}</b></div>
          <div class="p-kv"><span>Date</span><b>${MF.fmtDate(p.date)}${p.time ? ' · ' + esc(p.time) : ''}</b></div>
          <div class="p-kv"><span>${verb}</span><b>${esc(p.partyName || '—')}</b></div>
          ${p.note ? `<div class="p-kv"><span>Note</span><span>${esc(p.note)}</span></div>` : ''}
          <div class="p-amt"><b>₹ ${MF.fmt(p.amount)}</b></div>
          <div class="p-words">Rupees ${numWords(p.amount)} only · ${esc(p.mode)}</div>
          <div class="p-sign"><div>Depositor</div><div>Authorised Signatory</div></div>
          <div class="p-ft">Computer-generated ${inn ? 'receipt' : 'voucher'} · Thank you</div>
        </div>`);
      }

      ['#pmDirF', '#pmTypeF', '#pmModeF'].forEach((s) => $(s).addEventListener('change', render));
      $('#pmSearch').addEventListener('input', render);
      $('#pmApply').addEventListener('click', load);
      load();
    })();
    });
  </script>
</body>
</html>
