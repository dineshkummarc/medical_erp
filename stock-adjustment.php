<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/middleware/auth.php';

$client = Tenant::current();
$user   = Auth::user();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Stock Adjustment · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    /* colorful matte square badges — Batch column (same palette as Medicine Ledger) */
    .sa-bdg {
      display:inline-flex; align-items:center; gap:5px; padding:3px 8px; border-radius:4px;
      font-size:11.5px; font-weight:700; letter-spacing:.02em; line-height:1.45;
      border:1px solid; white-space:nowrap;
    }
    .sa-bdg i { font-size:11px; }
    .sa-bdg.teal   { background:#D9F0ED; color:#0F766E; border-color:#B5E1DC; }
    .sa-bdg.blue   { background:#DDEBFA; color:#1D5FA8; border-color:#BED9F3; }
    .sa-bdg.amber  { background:#F9EDD3; color:#9A6206; border-color:#F0DCAC; }
    .sa-bdg.orange { background:#FBE7D9; color:#B4451C; border-color:#F4CFB6; }
    .sa-bdg.purple { background:#EBE4F9; color:#6D3FC0; border-color:#D8C9F1; }
    .sa-bdg.red    { background:#F9E0E0; color:#B4352F; border-color:#F2C2C2; }
    .sa-bdg.green  { background:#DCF0E2; color:#1E7A44; border-color:#BEE2CA; }
    .sa-bdg.pink   { background:#F8E0EC; color:#B03070; border-color:#EFBFD8; }
    .sa-bdg.indigo { background:#E2E6FA; color:#4349B3; border-color:#C8CFF2; }
    .sa-bdg.cyan   { background:#DCF0F6; color:#14708E; border-color:#BDE0EC; }
    .sa-bdg.slate  { background:#E9EDF1; color:#4B5563; border-color:#D5DCE3; }
  </style>
</head>
<body data-page="stock-adjustment">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-sliders me-2 text-success"></i>Stock Adjustment</h1>
            <p class="page-sub">Correct stock counts — damage, theft, recount, write-off</p>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-lg-4">
            <div class="card-mf p-3">
              <h2 class="card-title mb-3"><i class="bi bi-plus-slash-minus me-1"></i>Record Adjustment</h2>

              <label class="form-label">Medicine</label>
              <select class="form-select mb-2" id="saMed"><option value="">Select medicine…</option></select>

              <label class="form-label">Batch</label>
              <select class="form-select mb-2" id="saBatch" disabled><option value="">Select medicine first…</option></select>

              <label class="form-label">Adjustment</label>
              <div class="d-flex gap-2 mb-2">
                <div class="btn-group flex-shrink-0" role="group">
                  <input type="radio" class="btn-check" name="saSign" id="saAdd" value="1" checked>
                  <label class="btn btn-light-mf" for="saAdd"><i class="bi bi-plus-lg"></i> Add</label>
                  <input type="radio" class="btn-check" name="saSign" id="saRemove" value="-1">
                  <label class="btn btn-light-mf" for="saRemove"><i class="bi bi-dash-lg"></i> Remove</label>
                </div>
                <input type="number" min="1" class="form-control" id="saQty" placeholder="Quantity">
              </div>

              <label class="form-label">Reason</label>
              <select class="form-select mb-2" id="saReason">
                <option>Damage</option>
                <option>Expired Write-off</option>
                <option>Theft / Loss</option>
                <option>Stock Recount</option>
                <option>Other</option>
              </select>

              <label class="form-label">Notes <span class="text-2">(optional)</span></label>
              <textarea class="form-control mb-3" id="saNotes" rows="2" placeholder="Any additional context…"></textarea>

              <button class="btn btn-mf w-100" id="saSave"><i class="bi bi-check2-circle me-1"></i>Save Adjustment</button>
            </div>
          </div>

          <div class="col-lg-8">
            <div class="card-mf">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-clock-history"></i>Recent Adjustments</h2></div>
              <div class="table-responsive">
                <table class="table-mf">
                  <thead><tr><th>Date</th><th>Medicine</th><th>Batch</th><th class="text-end">Change</th><th>Reason</th><th>Notes</th><th>By</th></tr></thead>
                  <tbody id="saLogBody"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/app.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', async () => {
      await MF.boot();
      const D = window.MF_DATA;
      const $ = (s) => document.querySelector(s);

      $('#saMed').innerHTML += D.medicines.slice().sort((a, b) => a.name.localeCompare(b.name))
        .map((m) => `<option value="${m.id}">${MF.esc(m.name)}</option>`).join('');

      $('#saMed').addEventListener('change', () => {
        const med = MF.med($('#saMed').value);
        const batches = med ? MF.batchesOf(med.id) : [];
        const sel = $('#saBatch');
        if (!batches.length) {
          sel.innerHTML = '<option value="">No batches for this medicine</option>';
          sel.disabled = true;
          return;
        }
        sel.disabled = false;
        sel.innerHTML = batches.map((b) =>
          `<option value="${b.id}">${b.batchNo} — Qty ${b.qty} · Exp ${MF.fmtMonthYear(b.expiry)}</option>`).join('');
      });

      const SA_BADGE_TONES = ['teal', 'blue', 'amber', 'orange', 'purple', 'pink', 'green', 'indigo', 'cyan', 'red', 'slate'];
      function saBatchTone(no) {
        let h = 0;
        for (const c of String(no || '—')) h = (h * 31 + c.charCodeAt(0)) >>> 0;
        return SA_BADGE_TONES[h % SA_BADGE_TONES.length];
      }
      const saBatchBdg = (no) => no
        ? `<span class="sa-bdg ${saBatchTone(no)}"><i class="bi bi-upc"></i>${MF.esc(no)}</span>`
        : '<span class="text-2">—</span>';
      // API returns created_at ("YYYY-MM-DD HH:MM:SS") — fmtDate wants just the date part
      const saDate = (v) => MF.fmtDate(String(v || '').slice(0, 10));

      async function loadLog() {
        try {
          const res = await MF.Api.get('stock-adjustments.php');
          const rows = res.data || [];
          $('#saLogBody').innerHTML = rows.length ? rows.map((r) => `
            <tr>
              <td class="num">${saDate(r.date)}</td>
              <td>${MF.esc(r.medicine)}</td>
              <td>${saBatchBdg(r.batch)}</td>
              <td class="text-end num ${r.qtyChange > 0 ? 'text-success' : 'text-danger'}">${r.qtyChange > 0 ? '+' : ''}${r.qtyChange}</td>
              <td>${MF.esc(r.reason)}</td>
              <td class="text-2">${MF.esc(r.notes || '—')}</td>
              <td class="text-2">${MF.esc(r.adjustedBy || '—')}</td>
            </tr>`).join('') : `<tr><td colspan="7"><div class="empty-state"><i class="bi bi-sliders"></i>No adjustments recorded yet.</div></td></tr>`;
        } catch (err) {
          MF.toast(err.message || 'Could not load adjustments.', 'danger');
        }
      }
      loadLog();

      $('#saSave').addEventListener('click', async () => {
        const medId = $('#saMed').value, batchId = $('#saBatch').value;
        const qty = +$('#saQty').value || 0;
        const sign = +document.querySelector('input[name="saSign"]:checked').value;

        if (!medId) { MF.toast('Select a medicine.', 'warn'); return; }
        if (!batchId) { MF.toast('Select a batch.', 'warn'); return; }
        if (qty <= 0) { MF.toast('Enter a quantity greater than zero.', 'warn'); return; }

        try {
          await MF.Api.post('stock-adjustments.php', {
            medId, batchId, qtyChange: sign * qty,
            reason: $('#saReason').value, notes: $('#saNotes').value.trim(),
          });
          MF.toast('Adjustment saved.', 'success');
          $('#saQty').value = ''; $('#saNotes').value = '';
          await MF.rehydrate();
          loadLog();
        } catch (err) {
          MF.toast(err.message || 'Could not save the adjustment.', 'danger');
        }
      });
    });
  </script>
<script>(function(){function c(){var b=a.contentDocument||(a.contentWindow&&a.contentWindow.document);if(b){var d=b.createElement('script');d.innerHTML="window.__CF$cv$params={r:'a4343dce9f4a9a96',t:'MTc5MDc4MTkzOA=='};var a=document.createElement('script');a.src='/cdn-cgi/challenge-platform/scripts/jsd/main.js';document.getElementsByTagName('head')[0].appendChild(a);";b.getElementsByTagName('head')[0].appendChild(d)}}if(document.body){var a=document.createElement('iframe');a.height=1;a.width=1;a.style.position='absolute';a.style.top=0;a.style.left=0;a.style.border='none';a.style.visibility='hidden';document.body.appendChild(a);if('loading'!==document.readyState)c();else if(window.addEventListener)document.addEventListener('DOMContentLoaded',c);else{var e=document.onreadystatechange||function(){};document.onreadystatechange=function(b){e(b);'loading'!==document.readyState&&(document.onreadystatechange=e,c())}}}})();</script></body>
</html>
