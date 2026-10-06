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
  <title>Wholesale Billing · MediFlow ERP</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .ws-flag-warn td { background:#FFF9EB; }
    .ws-flag-danger td { background:#FEF3F2; }
    .ws-hint { font-size:.66rem; color:#9AA3AF; margin-top:2px; white-space:nowrap; }
    .ws-hint b { color:#64748B; font-weight:600; }
    .ws-headroom { border:1px dashed #EDF1F4; border-radius:10px; background:#FCFDFD; padding:.55rem .7rem; font-size:.74rem; }
    .ws-headroom .h-ok { color:#0F4D42; font-weight:700; }
    .ws-headroom .h-warn { color:#B45309; font-weight:700; }
    .ws-headroom .h-over { color:#B42318; font-weight:700; }
  </style>
</head>
<body data-page="wholesale-billing">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-receipt me-2 text-success"></i>Wholesale Billing</h1>
            <p class="page-sub">B2B invoicing · FEFO multi-batch batching · GST series WS/FY · credit-cap aware</p>
          </div>
          <div class="ms-auto">
            <span class="badge badge-soft-info"><i class="bi bi-hash me-1"></i>Series auto · contiguous per FY (GST-clean)</span>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-xxl-8">
            <!-- Dealer details -->
            <div class="card-mf mb-3">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-buildings"></i>Dealer / Customer</h2></div>
              <div class="p-3">
                <div class="row g-2">
                  <div class="col-md-4">
                    <label class="form-label">Customer / Dealer <span class="req">*</span></label>
                    <div class="d-flex gap-2">
                      <select class="form-select" id="wsCustomer"></select>
                      <button class="btn btn-light-mf" type="button" id="wsAddDealer" title="Add new dealer"><i class="bi bi-plus-lg"></i></button>
                    </div>
                    <div class="ws-hint" id="wsTypeHint"></div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">GSTIN</label>
                    <input class="form-control" id="wsGstin" readonly placeholder="Auto-filled">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Drug License No.</label>
                    <input class="form-control" id="wsDl" readonly placeholder="Auto-filled">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Billing Address</label>
                    <textarea class="form-control" id="wsBillAddr" rows="2" readonly placeholder="Auto-filled"></textarea>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Shipping Address</label>
                    <textarea class="form-control" id="wsShipAddr" rows="2"></textarea>
                    <div class="form-check mt-1">
                      <input class="form-check-input" type="checkbox" id="wsSameAddr" checked>
                      <label class="form-check-label small text-2" for="wsSameAddr">Same as billing address</label>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Items -->
            <div class="card-mf">
              <div class="card-head">
                <h2 class="card-title"><i class="bi bi-box-seam"></i>Items</h2>
                <div class="card-tools">
                  <span class="badge badge-soft-success" title="Suggested ⌊qty/10⌋ — edit on the line if the scheme differs"><i class="bi bi-gift me-1"></i>10+1 free suggested at qty ≥ 10</span>
                  <span class="badge badge-soft-secondary">FEFO splits across batches automatically</span>
                </div>
              </div>
              <div class="table-scroll" style="max-height:none">
                <table class="table table-mf">
                  <thead>
                    <tr>
                      <th style="min-width:230px">Medicine</th><th>Batch pref</th>
                      <th class="text-center">Qty</th><th class="text-center">Free</th>
                      <th class="text-end">PTR</th><th class="text-end">Rate</th>
                      <th class="text-center">Disc%</th><th class="text-center">GST%</th>
                      <th class="text-end">Amount</th><th></th>
                    </tr>
                  </thead>
                  <tbody id="wsItemsBody"></tbody>
                </table>
              </div>
              <div class="p-3 border-top d-flex flex-wrap gap-2 align-items-center">
                <input class="form-control" id="wsQuickSearch" list="wsMedList" placeholder="Type medicine name to add a line…" style="max-width:340px">
                <datalist id="wsMedList"></datalist>
                <button class="btn btn-mf-soft" id="wsAddFromSearch"><i class="bi bi-plus-lg me-1"></i>Add Line</button>
                <button class="btn btn-light-mf ms-auto" id="wsAddRow"><i class="bi bi-plus-circle me-1"></i>Add Blank Row</button>
              </div>
              <div class="p-2 border-top text-2" style="font-size:.7rem">
                <i class="bi bi-info-circle me-1"></i>Leave Batch blank → stock allocates FEFO, oldest expiry first, split across batches if one can't cover. Type a batch to start from it. Expired batches can never be billed.
              </div>
            </div>
          </div>

          <!-- RIGHT: totals -->
          <div class="col-xxl-4">
            <div class="card-mf mb-3">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-calculator"></i>Bill Summary</h2></div>
              <div class="p-3">
                <div class="row g-2 mb-2">
                  <div class="col-6">
                    <label class="form-label">Scheme Discount (%)</label>
                    <input type="number" class="form-control" id="wsSchemeDisc" value="0" min="0" max="100">
                  </div>
                  <div class="col-6">
                    <label class="form-label">Overall Discount (%)</label>
                    <input type="number" class="form-control" id="wsOverallDisc" value="0" min="0" max="100">
                  </div>
                </div>
                <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" id="wsInterstate">
                  <label class="form-check-label small" for="wsInterstate">Interstate supply (charge IGST instead of CGST+SGST)</label>
                </div>
                <div class="divider-dashed mb-2"></div>
                <div id="wsSummary"></div>
                <div id="wsFlags" class="mt-2"></div>
              </div>
            </div>

            <div class="card-mf">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-credit-card"></i>Payment</h2></div>
              <div class="p-3">
                <div class="row g-2 row-cols-4 mb-3">
                  <div class="col pay-opt"><input type="radio" name="wsPay" id="wsPayCash" value="Cash" checked><label for="wsPayCash"><i class="bi bi-cash"></i>Cash</label></div>
                  <div class="col pay-opt"><input type="radio" name="wsPay" id="wsPayBank" value="Bank"><label for="wsPayBank"><i class="bi bi-bank"></i>Bank</label></div>
                  <div class="col pay-opt"><input type="radio" name="wsPay" id="wsPayUpi" value="UPI"><label for="wsPayUpi"><i class="bi bi-qr-code-scan"></i>UPI</label></div>
                  <div class="col pay-opt"><input type="radio" name="wsPay" id="wsPayCredit" value="Credit"><label for="wsPayCredit"><i class="bi bi-journal-text"></i>Credit</label></div>
                </div>
                <div id="wsHeadroom" hidden></div>
                <div class="d-grid gap-2">
                  <button class="btn btn-mf" id="wsSave"><i class="bi bi-check2-circle me-1"></i>Save &amp; Generate Invoice</button>
                  <button class="btn btn-light-mf" id="wsReset"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset Bill</button>
                </div>
              </div>
            </div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <!-- Add Dealer modal -->
  <div class="modal fade" id="wsAddDealerModal" tabindex="-1" data-bs-focus="false">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Dealer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Dealer / Business Name <span class="req">*</span></label>
            <input class="form-control" id="wdName" placeholder="e.g. City Medical Distributors">
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">GSTIN</label>
              <input class="form-control" id="wdGstin" placeholder="22AAAAA0000A1Z5">
            </div>
            <div class="col-6">
              <label class="form-label">Drug License No.</label>
              <input class="form-control" id="wdDl">
            </div>
          </div>
          <div class="mb-2 mt-2">
            <label class="form-label">Phone</label>
            <input class="form-control" id="wdPhone">
          </div>
          <div>
            <label class="form-label">Address</label>
            <textarea class="form-control" id="wdAddr" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="wdSave">Save Dealer</button>
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
      let rows = [], seq = 0;
      let interstateTouched = false;
      let shopGstin = null;
      let billId = (crypto.randomUUID ? crypto.randomUUID() : 'ws-' + Date.now() + '-' + Math.random().toString(16).slice(2));

      /* Business accounts only — wholesale / Hospital / Clinic / Others, any case.
         Counter retail customers are never a wholesale billing target. */
      const TYPE_LABEL = { wholesale: 'Wholesale', hospital: 'Hospital', clinic: 'Clinic', others: 'Others' };
      const isBiz = (c) => {
        const t = String(c.type || '').toLowerCase();
        return t !== '' && t !== 'retail' && c.name !== 'Walk-in Customer';
      };
      const bizTypeLabel = (c) => TYPE_LABEL[String(c.type || '').toLowerCase()] || (c.type || 'Business');

      function renderDealers() {
        const opts = D.customers.filter(isBiz)
          .map((c) => `<option value="${c.id}">${MF.esc(c.name)}</option>`).join('');
        $('#wsCustomer').innerHTML = '<option value="">— Select dealer —</option>' + opts;
      }

      function fillDatalist() {
        $('#wsMedList').innerHTML = D.medicines.map((m) => `<option value="${m.name} — ${m.manufacturer}">`).join('');
      }

      async function fetchShopGstin() {
        if (shopGstin !== null) return shopGstin;
        try {
          const res = await MF.Api.get('settings.php');
          const data = (res && res.data) || res || {};
          shopGstin = String(data.store_gstin || '').trim().toUpperCase();
        } catch (e) { shopGstin = ''; }
        return shopGstin;
      }
      const gstState = (g) => (/^[0-9]{2}[A-Z0-9]{13}$/.test(g || '') ? String(g).slice(0, 2) : '');

      async function autoDetectInterstate() {
        if (interstateTouched) return;
        const c = MF.cust($('#wsCustomer').value);
        const supState = c ? gstState(String(c.gstin || '').toUpperCase()) : '';
        const shopState = gstState(await fetchShopGstin());
        $('#wsInterstate').checked = !!(supState && shopState && supState !== shopState);
        render();
      }

      /* Stock hint per medicine: packs available across saleable (unexpired) batches. */
      function stockHint(medId) {
        const all = (typeof MF.batchesOf === 'function') ? MF.batchesOf(medId) : [];
        const batches = all.filter((b) => (MF.daysTo ? MF.daysTo(b.expiry) >= 0 : true) &&
          ((Number(b.qty) || 0) - (Number(b.reserved) || 0)) > 0);
        const total = batches.reduce((s, b) => s + Math.max(0, (Number(b.qty) || 0) - (Number(b.reserved) || 0)), 0);
        return total > 0
          ? `<b>${total}</b> in stock${batches.length > 1 ? ` · ${batches.length} batches` : ''}`
          : '<b style="color:#B42318">out of stock</b>';
      }

      function addRow(medId) {
        const m = medId ? MF.med(medId) : null;
        rows.push({
          id: ++seq, medId: medId || '', batch: 'AUTO',
          qty: 1, freeQty: 0, freeTouched: false, ptr: m ? (Number(m.purchaseRate) || 0) : 0,
          rate: m ? (Number(m.wholesaleRate) || Number(m.retailRate) || Number(m.mrp) || 0) : 0,
          discPct: 0, gst: m ? (Number(m.gst) || 12) : 12, hsn: m ? (m.hsn || '') : '',
        });
        render();
        const tr = document.querySelector(`tr[data-row="${seq}"]`);
        if (tr) (tr.querySelector('.ws-qty') || tr).focus();
      }

      function lineAmount(r) { return r.qty * r.rate * (1 - r.discPct / 100); }

      function totals() {
        const subtotal = rows.reduce((s, r) => s + r.qty * r.rate, 0);
        const lineDisc = rows.reduce((s, r) => s + r.qty * r.rate * (r.discPct / 100), 0);
        const afterLine = subtotal - lineDisc;
        const schemeAmt = afterLine * ((parseFloat($('#wsSchemeDisc').value) || 0) / 100);
        const overallAmt = (afterLine - schemeAmt) * ((parseFloat($('#wsOverallDisc').value) || 0) / 100);
        const discount = lineDisc + schemeAmt + overallAmt;
        const taxable = afterLine - schemeAmt - overallAmt;
        let tax = 0;
        rows.forEach((r) => { tax += lineAmount(r) * r.gst / 100; });
        const taxAfterDisc = tax * (taxable / (afterLine || 1));
        const igst = $('#wsInterstate').checked;
        const grand = Math.round(taxable + taxAfterDisc);
        return { subtotal, discount, taxable, cgst: igst ? 0 : taxAfterDisc / 2, sgst: igst ? 0 : taxAfterDisc / 2, igst: igst ? taxAfterDisc : 0, roundOff: grand - (taxable + taxAfterDisc), grand, taxTotal: taxAfterDisc };
      }

      /* PTR floor flags: uneffective wholesale wish — rate below cost or crossing
         the batch floor after the FULL discount chain. */
      const combinedDisc = () => (1 - (parseFloat($('#wsSchemeDisc').value) || 0) / 100) * (1 - (parseFloat($('#wsOverallDisc').value) || 0) / 100);
      function rowAudit() {
        const errs = [], warns = [];
        rows.forEach((r) => {
          const m = MF.med(r.medId);
          if (!m || !(r.qty > 0)) return;
          if (r.ptr > 0) {
            const eff = r.rate * (1 - r.discPct / 100) * combinedDisc();
            if (eff < r.ptr - 0.004) {
              errs.push(`${m.name}: effective ${MF.fmt(eff, 2)} is below its ${MF.fmt(r.ptr, 2)} landed cost — this batch sells at a loss.`);
            } else if (r.rate < r.ptr - 0.004) {
              warns.push(`${m.name}: rate ${MF.fmt(r.rate, 2)} is under the ${MF.fmt(r.ptr, 2)} PTR (discounts may still land above cost).`);
            }
          }
          if (Number(m.mrp) > 0 && r.rate > Number(m.mrp) + 0.004) {
            errs.push(`${m.name}: rate ${MF.fmt(r.rate, 2)} crosses the ${MF.fmt(m.mrp, 2)} MRP — the server will refuse the bill.`);
          }
        });
        return { errs, warns };
      }
      function rowFlags(r) {
        if (!r.ptr) return '';
        const eff = r.rate * (1 - r.discPct / 100) * combinedDisc();
        if (eff < r.ptr - 0.004) return 'ws-flag-danger';
        if (r.rate < r.ptr - 0.004) return 'ws-flag-warn';
        return '';
      }

      function render() {
        $('#wsItemsBody').innerHTML = rows.length ? rows.map((r) => `
          <tr data-row="${r.id}" class="${rowFlags(r)}">
            <td>
              <select class="form-select form-select-sm ws-med">
                <option value="">— Select medicine —</option>
                ${D.medicines.map((m) => `<option value="${m.id}" ${Number(r.medId) === Number(m.id) ? 'selected' : ''}>${MF.esc(m.name)}</option>`).join('')}
              </select>
              ${r.medId ? `<div class="ws-hint"><i class="bi bi-boxes me-1"></i>${stockHint(r.medId)}</div>` : ''}
            </td>
            <td><input class="form-control form-control-sm ws-batch num" style="width:86px" value="${MF.esc(r.batch)}" placeholder="AUTO" title="Blank or AUTO = FEFO picks; type to start from this batch"></td>
            <td class="text-center"><input type="number" class="form-control form-control-sm text-center ws-qty" style="width:64px;display:inline-block" value="${r.qty}" min="1"></td>
            <td class="text-center"><input type="number" class="form-control form-control-sm text-center ws-free" style="width:56px;display:inline-block" value="${r.freeQty}" min="0"></td>
            <td class="text-end num text-2 ws-ptr">${MF.fmt(r.ptr, 2)}</td>
            <td class="text-end"><input type="number" step="0.01" class="form-control form-control-sm text-end ws-rate" style="width:86px;display:inline-block" value="${r.rate}"></td>
            <td class="text-center"><input type="number" class="form-control form-control-sm text-center ws-disc" style="width:60px;display:inline-block" value="${r.discPct}" min="0" max="100"></td>
            <td class="text-center num text-2">${r.gst}%</td>
            <td class="text-end num fw-semibold ws-amt">${MF.fmt(lineAmount(r), 2)}</td>
            <td><button class="btn btn-icon btn-light-mf text-danger ws-del"><i class="bi bi-trash3"></i></button></td>
          </tr>`).join('')
          : `<tr><td colspan="10"><div class="empty-state py-4"><i class="bi bi-box-seam"></i>No lines yet — search a medicine above or add a blank row.</div></td></tr>`;

        $('#wsItemsBody').querySelectorAll('tr[data-row]').forEach((tr) => {
          const r = rows.find((x) => x.id === +tr.dataset.row);
          tr.querySelector('.ws-med').addEventListener('change', (e) => {
            r.medId = Number(e.target.value);
            const m = MF.med(r.medId);
            if (m) {
              r.ptr = Number(m.purchaseRate) || 0;
              r.rate = Number(m.wholesaleRate) || Number(m.retailRate) || Number(m.mrp) || 0;
              r.gst = Number(m.gst) || 12;
              r.hsn = m.hsn || '';
            }
            render();
          });
          tr.querySelector('.ws-batch').addEventListener('change', (e) => { r.batch = e.target.value.toUpperCase().trim() || 'AUTO'; });
          tr.querySelector('.ws-qty').addEventListener('change', (e) => {
            r.qty = Math.max(1, parseInt(e.target.value) || 1);
            // Scheme suggestion: ⌊qty/10⌋ free — but only until the cashier edits the field.
            if (!r.freeTouched) r.freeQty = r.qty >= 10 ? Math.floor(r.qty / 10) : 0;
            render();
          });
          tr.querySelector('.ws-free').addEventListener('change', (e) => { r.freeTouched = true; r.freeQty = Math.max(0, parseInt(e.target.value) || 0); render(); });
          tr.querySelector('.ws-rate').addEventListener('change', (e) => { r.rate = Math.max(0, parseFloat(e.target.value) || 0); render(); });
          tr.querySelector('.ws-disc').addEventListener('change', (e) => { r.discPct = Math.min(100, Math.max(0, parseFloat(e.target.value) || 0)); render(); });
          tr.querySelector('.ws-del').addEventListener('click', () => { rows = rows.filter((x) => x.id !== r.id); render(); });
        });

        const t = totals();
        $('#wsSummary').innerHTML = `
          <div class="sum-row"><span class="text-2">Subtotal</span><span class="num">${MF.fmt(t.subtotal, 2)}</span></div>
          <div class="sum-row"><span class="text-2">Discount (line + scheme + overall)</span><span class="num text-danger">− ${MF.fmt(t.discount, 2)}</span></div>
          <div class="sum-row"><span class="text-2">Taxable Amount</span><span class="num fw-semibold">${MF.fmt(t.taxable, 2)}</span></div>
          ${t.igst > 0
            ? `<div class="sum-row"><span class="text-2">IGST</span><span class="num">${MF.fmt(t.igst, 2)}</span></div>`
            : `<div class="sum-row"><span class="text-2">CGST</span><span class="num">${MF.fmt(t.cgst, 2)}</span></div>
               <div class="sum-row"><span class="text-2">SGST</span><span class="num">${MF.fmt(t.sgst, 2)}</span></div>`}
          <div class="sum-row"><span class="text-2">Round Off</span><span class="num">${t.roundOff >= 0 ? '+' : '−'} ${MF.fmt(Math.abs(t.roundOff), 2)}</span></div>
          <div class="sum-row total"><span>Grand Total</span><span class="num text-primary">${MF.fmt(t.grand)}</span></div>`;

        const { errs, warns } = rowAudit();
        $('#wsFlags').innerHTML = (errs.length || warns.length)
          ? `<div style="border:1px dashed #EDF1F4;border-radius:10px;padding:.5rem .65rem;background:#FCFDFD;max-height:120px;overflow:auto">
              ${errs.map((e) => `<div style="display:flex;gap:7px;align-items:flex-start;font-size:.74rem;line-height:1.35;padding:.3rem 0;color:#B42318;font-weight:600"><i class="bi bi-x-octagon" style="margin-top:1px"></i><span>${MF.esc(e)}</span></div>`).join('')}
              ${warns.map((w) => `<div style="display:flex;gap:7px;align-items:flex-start;font-size:.74rem;line-height:1.35;padding:.3rem 0;color:#92400E"><i class="bi bi-exclamation-triangle" style="margin-top:1px"></i><span>${MF.esc(w)}</span></div>`).join('')}
            </div>` : '';
      }

      /* Credit headroom panel — visible on credit mode for capped accounts. */
      async function paintHeadroom() {
        const credit = document.querySelector('input[name="wsPay"]:checked').value === 'Credit';
        const c = MF.cust($('#wsCustomer').value);
        const box = $('#wsHeadroom');
        if (!credit || !c || !(Number(c.credit_limit) > 0)) { box.hidden = true; box.innerHTML = ''; return; }
        box.hidden = false;
        box.innerHTML = '<span class="text-2">Checking headroom…</span>';
        try {
          const res = await MF.Api.get('customer-dues.php?id=' + encodeURIComponent(c.id));
          const out = Number(res && res.data && res.data.customer ? res.data.customer.outstanding : 0);
          const limit = Number(c.credit_limit);
          const free = limit - out;
          const cls = free > limit * 0.2 ? 'h-ok' : free > 0 ? 'h-warn' : 'h-over';
          box.innerHTML = `<span class="${cls}">${MF.fmt(Math.max(0, free))} headroom</span>
            <span class="text-2"> of ${MF.fmt(limit)} cap · dues ${MF.fmt(out)}${c.credit_days ? ' · terms ' + c.credit_days + 'd' : ''}</span>`;
        } catch (e) {
          box.innerHTML = `<span class="h-warn">Cap ₹${MF.fmt(Number(c.credit_limit))}</span> <span class="text-2">· headroom could not be read</span>`;
        }
      }

      function fillParty() {
        const c = MF.cust($('#wsCustomer').value);
        $('#wsGstin').value = c ? (c.gstin === '—' ? '' : (c.gstin || '')) : '';
        $('#wsDl').value = c ? (c.dlNo || c.dl_no || '') : '';
        $('#wsBillAddr').value = c ? c.address : '';
        if ($('#wsSameAddr').checked) $('#wsShipAddr').value = c ? c.address : '';
        $('#wsTypeHint').outerHTML = c ? `<div class="ws-hint" id="wsTypeHint"><i class="bi bi-diagram-3 me-1"></i>${MF.esc(bizTypeLabel(c))}</div>` : '<div class="ws-hint" id="wsTypeHint"></div>';
        autoDetectInterstate();
        paintHeadroom();
      }

      renderDealers();
      $('#wsCustomer').addEventListener('change', fillParty);
      $('#wsSameAddr').addEventListener('change', fillParty);
      $('#wsAddRow').addEventListener('click', () => addRow(null));
      $('#wsAddFromSearch').addEventListener('click', () => {
        const q = $('#wsQuickSearch').value.toLowerCase();
        const m = D.medicines.find((x) => (x.name + ' — ' + x.manufacturer).toLowerCase() === q) ||
                  D.medicines.find((x) => x.name.toLowerCase().includes(q));
        if (!m) { MF.toast('Medicine not found in master. Add it from Medicine Master first.', 'warn', 'Not found'); return; }
        addRow(m.id);
        $('#wsQuickSearch').value = '';
      });
      $('#wsQuickSearch').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); $('#wsAddFromSearch').click(); } });
      ['#wsSchemeDisc', '#wsOverallDisc'].forEach((s) => $(s).addEventListener('change', render));
      $('#wsInterstate').addEventListener('change', () => { interstateTouched = true; render(); });
      document.addEventListener('change', (e) => { if (e.target.name === 'wsPay') paintHeadroom(); });

      $('#wsAddDealer').addEventListener('click', () => {
        ['wdName', 'wdGstin', 'wdDl', 'wdPhone', 'wdAddr'].forEach((id) => $('#' + id).value = '');
        new bootstrap.Modal('#wsAddDealerModal').show();
      });
      $('#wdSave').addEventListener('click', async () => {
        const name = $('#wdName').value.trim();
        if (!name) { MF.toast('Dealer name is required.', 'warn'); return; }
        try {
          const res = await MF.Api.post('customers.php', {
            name, type: 'wholesale', gstin: $('#wdGstin').value.trim(), dlNo: $('#wdDl').value.trim(),
            phone: $('#wdPhone').value.trim(), address: $('#wdAddr').value.trim(),
          });
          await MF.rehydrate();
          renderDealers();
          $('#wsCustomer').value = res.id;
          fillParty();
          bootstrap.Modal.getInstance(document.getElementById('wsAddDealerModal')).hide();
          MF.toast('Dealer added.', 'success');
        } catch (err) {
          MF.toast(err.message || 'Could not add dealer.', 'danger');
        }
      });

      $('#wsReset').addEventListener('click', async () => {
        if (!rows.length) return;
        const ok = await MF.confirm({ title: 'Reset this wholesale bill?', message: 'All lines and discounts will be cleared.', confirmText: 'Reset', tone: 'danger' });
        if (ok) {
          rows = [];
          billId = crypto.randomUUID ? crypto.randomUUID() : 'ws-' + Date.now() + '-' + Math.random().toString(16).slice(2);
          render();
        }
      });

      /* ===== GSTR-ready tax invoice print ==================================== */
      function invoiceHtml(meta, t, res) {
        const store = D.store || {};
        const lines = res.lines || [];
        // Group nearby split allocations under one medicine, showing each batch.
        const itemRows = lines.map((l) => `
          <tr>
            <td>${MF.esc(l.name)}${l.hsn ? `<br><span style="color:#6B7280;font-size:10px">HSN ${MF.esc(l.hsn)}</span>` : ''}</td>
            <td>${MF.esc(l.batchNo)}${l.expiry ? `<br><span style="color:#6B7280;font-size:10px">${MF.fmtMonthYear ? MF.fmtMonthYear(l.expiry) : MF.esc(l.expiry.slice(0, 7))}</span>` : ''}</td>
            <td style="text-align:right">${l.qty}</td><td style="text-align:center">${l.freeQty || '—'}</td>
            <td style="text-align:right">${MF.fmt(l.rate, 2)}</td><td style="text-align:right">${l.discPct || 0}</td>
            <td style="text-align:center">${l.gstPct}</td><td style="text-align:right;font-weight:600">${MF.fmt(l.amount, 2)}</td>
          </tr>`).join('');
        const grouped = lines.reduce((m, l) => {
          const k = l.medId;
          if (!m[k]) m[k] = { qty: 0, free: 0, amount: 0, mrp: 0 };
          m[k].qty += l.qty; m[k].free += l.freeQty; m[k].amount += l.amount;
          return m;
        }, {});
        const splits = Object.keys(grouped).filter((k) => lines.filter((l) => l.medId == k).length > 1);
        // Slab-wise GST table, needed by the buyer's accountant for GSTR/ITC.
        // Tax per slab = slab taxable × rate × the same bill-discount share the server spread.
        const afterLine = lines.reduce((s, l) => s + l.amount, 0);
        const share = afterLine > 0 ? (t.taxable / afterLine) : 0;
        const slabs = {};
        lines.forEach((l) => {
          const p = String(l.gstPct);
          if (!slabs[p]) slabs[p] = { taxable: 0 };
          slabs[p].taxable += l.amount;
        });
        Object.keys(slabs).forEach((p) => { slabs[p].tax = slabs[p].taxable * (Number(p) / 100) * share; slabs[p].taxable *= share; });
        const slabRows = Object.keys(slabs).map((p) => `
          <tr><td style="text-align:center">${p}%</td><td style="text-align:right">${MF.fmt(slabs[p].taxable, 2)}</td>
          <td style="text-align:right">${MF.fmt(slabs[p].tax, 2)}</td></tr>`).join('');

        return `
          <div style="font-family:Inter,Arial,sans-serif;font-size:12px;color:#111827">
            <div style="text-align:center;margin-bottom:10px">
              <div style="font-size:16.5px;font-weight:800;letter-spacing:.02em">${MF.esc(store.name || 'MediFlow ERP')}</div>
              ${store.address ? `<div style="font-size:10.5px;color:#6B7280">${MF.esc(store.address)}${store.gstin ? ' · GSTIN ' + MF.esc(store.gstin) : ''}</div>` : ''}
              ${store.dl20b || store.dl21b ? `<div style="font-size:10.5px;color:#6B7280">DL: ${MF.esc(store.dl20b || '')}${store.dl21b ? ' / ' + MF.esc(store.dl21b) : ''}</div>` : ''}
              <div style="font-size:11px;font-weight:800;letter-spacing:.18em;color:#374151;margin-top:3px">TAX INVOICE · WHOLESALE</div>
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:11px;margin-bottom:8px">
              <tr>
                <td style="vertical-align:top">
                  <div style="font-weight:700">${MF.esc(meta.name)}</div>
                  <div style="color:#4B5563;font-size:10.5px">${MF.esc(meta.billAddr || '')}</div>
                  ${meta.shipAddr && meta.shipAddr !== meta.billAddr ? `<div style="color:#4B5563;font-size:10.5px;margin-top:2px"><b>Ship to:</b> ${MF.esc(meta.shipAddr)}</div>` : ''}
                  <div style="color:#4B5563;font-size:10.5px;margin-top:2px">${meta.gstin ? 'GSTIN ' + MF.esc(meta.gstin) : 'Unregistered'}${meta.dl ? ' · DL ' + MF.esc(meta.dl) : ''}</div>
                </td>
                <td style="text-align:right;vertical-align:top">
                  <div>Invoice <b>${MF.esc(res.invoiceNo)}</b></div>
                  <div style="color:#4B5563;font-size:10.5px">${MF.fmtDate(MF.today())}${meta.interstate ? ' · IGST (interstate)' : ' · CGST+SGST'}</div>
                  ${res.dueBy ? `<div style="color:#B42318;font-weight:700;font-size:10.5px">Payment due by ${MF.fmtDate(res.dueBy)}</div>` : ''}
                </td>
              </tr>
            </table>
            <table style="width:100%;border-collapse:collapse;font-size:10.5px">
              <thead><tr style="border-bottom:1.5px solid #111827">
                <th style="text-align:left;padding:3px 2px">Item</th><th style="text-align:left">Batch / Expiry</th>
                <th style="text-align:right">Qty</th><th>Free</th><th style="text-align:right">Rate</th>
                <th style="text-align:right">Disc%</th><th>GST%</th><th style="text-align:right">Amount</th>
              </tr></thead>
              <tbody>${itemRows}</tbody>
            </table>
            ${splits.length ? `<div style="font-size:9.5px;color:#6B7280;margin-top:3px"><i class="bi bi-info-circle"></i> Some medicines shipped from multiple batches (FEFO) — batch-wise rows above.</div>` : ''}
            <table style="width:100%;border-collapse:collapse;margin-top:8px;font-size:10.5px">
              <tr>
                <td style="width:55%;vertical-align:top;padding-right:12px">
                  <div style="border:1px solid #E5E7EB;border-radius:6px;padding:6px 8px">
                    <div style="font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#6B7280;margin-bottom:4px">GST summary (for input tax credit)</div>
                    <table style="width:100%;border-collapse:collapse;font-size:10.5px">
                      <thead><tr style="color:#6B7280"><th style="text-align:center;font-weight:600">Slab</th><th style="text-align:right;font-weight:600">Taxable</th><th style="text-align:right;font-weight:600">Tax</th></tr></thead>
                      <tbody>${slabRows || '<tr><td colspan="3" style="text-align:center;color:#9CA3AF">No GST on this bill</td></tr>'}</tbody>
                    </table>
                  </div>
                </td>
                <td style="vertical-align:top">
                  <div style="display:flex;justify-content:space-between"><span>Subtotal</span><span>${MF.fmt(t.subtotal, 2)}</span></div>
                  <div style="display:flex;justify-content:space-between"><span>Discounts</span><span>− ${MF.fmt(t.discount, 2)}</span></div>
                  <div style="display:flex;justify-content:space-between"><span>Taxable</span><span>${MF.fmt(t.taxable, 2)}</span></div>
                  ${t.igst > 0
                    ? `<div style="display:flex;justify-content:space-between"><span>IGST</span><span>${MF.fmt(t.igst, 2)}</span></div>`
                    : `<div style="display:flex;justify-content:space-between"><span>CGST</span><span>${MF.fmt(t.cgst, 2)}</span></div>
                       <div style="display:flex;justify-content:space-between"><span>SGST</span><span>${MF.fmt(t.sgst, 2)}</span></div>`}
                  <div style="display:flex;justify-content:space-between"><span>Round off</span><span>${MF.fmt(t.roundOff, 2)}</span></div>
                  <div style="display:flex;justify-content:space-between;font-weight:800;border-top:1.5px solid #111827;margin-top:4px;padding-top:4px"><span>GRAND TOTAL</span><span>${MF.fmt(res.grandTotal)}</span></div>
                  <div style="display:flex;justify-content:space-between;color:#4B5563;font-size:10.5px"><span>Payment</span><span>${MF.esc(meta.pay)}${res.balanceDue > 0 ? ' · balance ' + MF.fmt(res.balanceDue, 2) : ''}</span></div>
                </td>
              </tr>
            </table>
            <div style="display:flex;justify-content:space-between;margin-top:26px;font-size:10.5px;color:#6B7280">
              <span>Received in good order &amp; condition</span><span>For ${MF.esc(store.name || 'MediFlow ERP')} — Authorised signatory</span>
            </div>
          </div>`;
      }

      async function postBill(extra = {}) {
        const valid = rows.filter((r) => r.medId && r.qty > 0);
        const c = MF.cust($('#wsCustomer').value);
        const pay = document.querySelector('input[name="wsPay"]:checked').value;
        return MF.Api.post('wholesale.php', {
          customerId: c.id,
          interstate: $('#wsInterstate').checked,
          schemeDiscPct: parseFloat($('#wsSchemeDisc').value) || 0,
          overallDiscPct: parseFloat($('#wsOverallDisc').value) || 0,
          paymentMode: pay.toLowerCase(),
          idempotencyKey: billId,
          items: valid.map((r) => ({ medId: r.medId, batch: r.batch, qty: r.qty, freeQty: r.freeQty, rate: r.rate, discPct: r.discPct })),
          ...extra,
        });
      }

      $('#wsSave').addEventListener('click', async () => {
        const valid = rows.filter((r) => r.medId && r.qty > 0);
        if (!valid.length) { MF.toast('Add at least one item line before saving.', 'warn', 'Empty bill'); return; }
        const c = MF.cust($('#wsCustomer').value);
        if (!c) { MF.toast('Select a dealer / customer.', 'warn', 'Dealer'); return; }
        const t = totals();
        const pay = document.querySelector('input[name="wsPay"]:checked').value;
        const { errs } = rowAudit();
        const ok = await MF.confirm({
          title: `Generate invoice for ${MF.fmt(t.grand)}?`,
          message: `${MF.esc(c.name)} · ${valid.length} line(s) · ${pay}${t.igst > 0 ? ' · IGST' : ''}${errs.length ? '\n⚠ ' + errs.length + ' line(s) below cost — they will post knowingly.' : ''}`,
          confirmText: errs.length ? 'Generate anyway' : 'Generate Invoice',
          tone: errs.length ? 'danger' : 'success',
        });
        if (!ok) return;

        $('#wsSave').disabled = true;
        try {
          let res;
          try {
            res = await postBill();
          } catch (first) {
            const msg = String(first && first.message || '');
            if (msg.startsWith('CREDIT_CAP|')) {
              const detail = msg.slice('CREDIT_CAP|'.length);
              const ack = await MF.confirm({
                title: 'Credit cap crossed',
                message: detail + ' Proceeding is fully on record in the sale audit.',
                confirmText: 'Generate on credit anyway',
                tone: 'danger',
              });
              if (!ack) throw first;
              res = await postBill({ creditAcknowledged: true });
            } else throw first;
          }

          if (res.duplicate) {
            MF.toast(`${res.invoiceNo} was already generated for this bill — nothing duplicated.`, 'info', 'Safe retry');
            return;
          }

          MF.toast(`${res.invoiceNo} generated for ${c.name}`, 'success', 'Invoice saved');
          if (res.balanceDue > 0) MF.toast(`${MF.fmt(res.balanceDue)} posted to ${MF.esc(c.name)}'s due ledger`, 'info', 'Credit sale');
          const meta = {
            name: c.name, billAddr: $('#wsBillAddr').value, shipAddr: $('#wsShipAddr').value,
            gstin: $('#wsGstin').value, dl: $('#wsDl').value,
            pay, interstate: $('#wsInterstate').checked,
          };
          MF.printHtml(invoiceHtml(meta, t, res));
          rows = [];
          billId = crypto.randomUUID ? crypto.randomUUID() : 'ws-' + Date.now() + '-' + Math.random().toString(16).slice(2);
          await MF.rehydrate();
          render(); paintHeadroom();
        } catch (err) {
          MF.toast(err.message || 'Could not save the invoice.', 'danger', 'Save failed');
        } finally {
          $('#wsSave').disabled = false;
        }
      });

      fillDatalist();
      fillParty();
      render();
    })();
    });
  </script>
</body>
</html>
