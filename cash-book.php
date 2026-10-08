<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/middleware/auth.php';
?>
<!doctype html>
<html lang="en">
<head>
  <!-- build 2026-10-08.23 - daily cash book w/ physical-count variance -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cash Book · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .cb-badge { font-size:.64rem; font-weight:700; letter-spacing:.02em; padding:2px 8px; border-radius:20px; }
    .cb-in { color:#0F4D42; font-weight:600; }
    .cb-out { color:#B42318; font-weight:600; }
    .cb-den-row { display:flex; align-items:center; gap:8px; padding:.3rem 0; }
    .cb-den-row .cb-den { min-width:74px; font-weight:600; }
    .cb-den-row input { width:84px; }
    .cb-den-row .cb-sub { margin-left:auto; color:#64748B; font-size:.78rem; min-width:88px; text-align:right; }
    .cb-var-ok { color:#0F4D42; font-weight:700; }
    .cb-var-short { color:#B42318; font-weight:700; }
    .cb-var-excess { color:#B45309; font-weight:700; }
  </style>
</head>
<body data-page="cash-book">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-journal-text me-2 text-success"></i>Cash Book</h1>
            <p class="page-sub">The day's physical-cash story — book vs drawer</p>
          </div>
          <div class="ms-auto d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-light-mf" id="cbPrev" title="Previous day"><i class="bi bi-chevron-left"></i></button>
            <input type="date" class="form-control form-control-sm" id="cbDate" style="width:150px">
            <button class="btn btn-sm btn-light-mf" id="cbNext" title="Next day"><i class="bi bi-chevron-right"></i></button>
            <button class="btn btn-sm btn-light-mf" id="cbPrint"><i class="bi bi-printer me-1"></i>Day Sheet</button>
          </div>
        </div>

        <div class="row g-3 mb-3" id="cbKpis"></div>

        <div class="row g-3">
          <div class="col-xxl-8">
            <div class="card-mf">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-arrow-down-up"></i>Cash Movements</h2>
                <div class="card-tools"><span class="badge badge-soft-secondary">Cash mode only — bank &amp; UPI live outside the drawer</span></div>
              </div>
              <div class="table-scroll" style="max-height:none">
                <table class="table table-mf">
                  <thead><tr><th>Time</th><th>Type</th><th>Reference</th><th>Party / Note</th><th class="text-end">In</th><th class="text-end">Out</th></tr></thead>
                  <tbody id="cbBody"></tbody>
                  <tfoot id="cbFoot"></tfoot>
                </table>
              </div>
            </div>
          </div>

          <div class="col-xxl-4">
            <div class="card-mf mb-3">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-cash-stack"></i>Drawer Count</h2></div>
              <div class="p-3">
                <div class="d-flex justify-content-between small mb-1"><span class="text-2">Book closing</span><b class="num" id="cbBookClose">—</b></div>
                <label class="form-label mt-1">Physical cash in drawer (₹)</label>
                <div class="d-flex gap-2">
                  <input type="number" class="form-control" id="cbCount" min="0" step="0.01" placeholder="Count &amp; enter">
                  <button class="btn btn-light-mf" id="cbDenToggle" type="button" title="Denomination counter"><i class="bi bi-calculator"></i></button>
                </div>
                <div id="cbDenBox" class="mt-2" hidden>
                  <div id="cbDenRows"></div>
                  <div class="d-flex justify-content-between border-top mt-1 pt-1 small"><b>Denomination total</b><b class="num" id="cbDenTotal">0</b></div>
                </div>
                <label class="form-label mt-2">Count note</label>
                <input class="form-control" id="cbCountNote" placeholder="Optional — who counted, remark…" maxlength="200">
                <div class="d-grid mt-3"><button class="btn btn-mf" id="cbSaveCount"><i class="bi bi-check2-circle me-1"></i>Save Count</button></div>
                <div id="cbVariance" class="mt-3"></div>
                <div class="pm-note text-2 mt-2" style="font-size:.7rem" id="cbCountMeta"></div>
              </div>
            </div>

            <div class="card-mf">
              <div class="card-head"><h2 class="card-title"><i class="bi bi-info-circle"></i>How the book reads</h2></div>
              <div class="p-3 small text-2" style="line-height:1.65">
                <b class="text-dark">In:</b> the cash slice of every bill (split payments count only their cash part) + due collections &amp; receipts marked Cash.<br>
                <b class="text-dark">Out:</b> cash expenses + supplier payouts &amp; other payments marked Cash.<br>
                <b class="text-dark">Opening:</b> every movement before this day — always in agreement with billing, no manual carry-forward.
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
    (function () {
      const MF = window.MF, D = window.MF_DATA;
      const $ = (s) => document.querySelector(s);
      let book = null;
      const DENOMS = [2000, 500, 200, 100, 50, 20, 10, 5, 2, 1];
      const den = {};

      const esc = (v) => MF.esc(String(v == null ? '' : v));
      $('#cbDate').value = MF.today();
      $('#cbDate').max = MF.today();

      const TYPE_META = {
        sale: { label: 'Sale', cls: 'badge-soft-primary', icon: 'bi-bag-check' },
        collection: { label: 'Due collection', cls: 'badge-soft-success', icon: 'bi-arrow-down-circle' },
        receipt: { label: 'Receipt', cls: 'badge-soft-success', icon: 'bi-arrow-down-circle' },
        expense: { label: 'Expense', cls: 'badge-soft-danger', icon: 'bi-cash-coin' },
        'supplier-pay': { label: 'Supplier payout', cls: 'badge-soft-warning', icon: 'bi-truck' },
        payment: { label: 'Payment', cls: 'badge-soft-danger', icon: 'bi-arrow-up-circle' },
      };

      function shiftDate(days) {
        const d = new Date($('#cbDate').value + 'T00:00:00');
        d.setDate(d.getDate() + days);
        const iso = d.toISOString().slice(0, 10);
        if (iso <= MF.today()) { $('#cbDate').value = iso; load(); }
      }

      async function load() {
        try {
          const res = await MF.Api.get('cash-book.php?date=' + $('#cbDate').value);
          book = res.data;
        } catch (err) {
          book = null;
          MF.toast(err.message || 'Could not load the cash book.', 'danger', 'Cash Book');
          $('#cbBody').innerHTML = `<tr><td colspan="6"><div class="empty-state"><i class="bi bi-journal-x"></i>Cash book unavailable.</div></td></tr>`;
          return;
        }
        render();
      }

      function render() {
        const b = book;
        $('#cbKpis').innerHTML = [
          ['Opening cash', MF.fmt(b.opening), 'info', 'box-arrow-in-right'],
          ['Cash in', MF.fmt(b.cashIn), 'success', 'arrow-down-circle'],
          ['Cash out', MF.fmt(b.cashOut), 'danger', 'arrow-up-circle'],
          ['Book closing', MF.fmt(b.closing), 'primary', 'cash-stack'],
        ].map(([l, v, tone, icon]) => `
          <div class="col-6 col-md-3"><div class="card-mf kpi-card h-100"><div class="kpi-icon tone-${tone}"><i class="bi bi-${icon}"></i></div>
            <div><div class="kpi-label">${l}</div><div class="kpi-value num">${v}</div></div></div></div>`).join('');

        $('#cbBody').innerHTML = b.movements.length ? b.movements.map((m) => {
          const meta = TYPE_META[m.type] || { label: m.type, cls: 'badge-soft-secondary', icon: 'bi-dot' };
          return `<tr>
            <td class="num text-2">${m.time && m.time !== '00:00' ? esc(m.time) : '—'}</td>
            <td><span class="cb-badge ${meta.cls}"><i class="bi ${meta.icon} me-1"></i>${meta.label}</span></td>
            <td class="num text-2">${esc(m.ref || '—')}</td>
            <td class="td-title">${esc(m.party || '—')}${m.note ? `<div class="text-2" style="font-size:.68rem">${esc(m.note)}</div>` : ''}</td>
            <td class="text-end num ${m.direction === 'in' ? 'cb-in' : ''}">${m.direction === 'in' ? MF.fmt(m.amount) : ''}</td>
            <td class="text-end num ${m.direction === 'out' ? 'cb-out' : ''}">${m.direction === 'out' ? MF.fmt(m.amount) : ''}</td>
          </tr>`;
        }).join('') : `<tr><td colspan="6"><div class="empty-state"><i class="bi bi-cash-stack"></i>No cash moved ${b.isToday ? 'today yet' : 'on this day'}.</div></td></tr>`;
        $('#cbFoot').innerHTML = b.movements.length ? `<tr><td colspan="4">Day totals</td><td class="text-end num cb-in">${MF.fmt(b.cashIn)}</td><td class="text-end num cb-out">${MF.fmt(b.cashOut)}</td></tr>` : '';

        $('#cbBookClose').textContent = '₹ ' + MF.fmt(b.closing);
        const phys = b.physical;
        $('#cbCount').value = phys ? phys.count : '';
        $('#cbCountNote').value = phys ? (phys.note || '') : '';
        $('#cbCountMeta').textContent = phys && phys.savedAt ? 'Last saved ' + esc(phys.savedAt.slice(0, 16).replace('T', ' ')) : '';
        paintVariance();
      }

      function paintVariance() {
        const box = $('#cbVariance');
        const v = $('#cbCount').value.trim();
        if (!book || v === '') { box.innerHTML = book && book.physical ? varianceHtml(book.physical.count - book.closing) : ''; return; }
        const diff = (parseFloat(v) || 0) - book.closing;
        box.innerHTML = varianceHtml(diff);
      }
      function varianceHtml(diff) {
        if (Math.abs(diff) < 0.005) return `<div class="cb-var-ok"><i class="bi bi-check-circle me-1"></i>Drawer matches the book exactly.</div>`;
        if (diff < 0) return `<div class="cb-var-short"><i class="bi bi-exclamation-octagon me-1"></i>Short by ₹ ${MF.fmt(Math.abs(diff))} — investigate before closing.</div>`;
        return `<div class="cb-var-excess"><i class="bi bi-exclamation-triangle me-1"></i>Excess of ₹ ${MF.fmt(diff)} — usually an unrecorded cash receipt.</div>`;
      }
      $('#cbCount').addEventListener('input', paintVariance);

      /* Denomination counter */
      function denRows() {
        $('#cbDenRows').innerHTML = DENOMS.map((d) => `
          <div class="cb-den-row">
            <span class="cb-den">₹ ${d.toLocaleString('en-IN')}</span> ×
            <input type="number" class="form-control form-control-sm text-center" min="0" data-den="${d}" value="${den[d] || 0}">
            <span class="cb-sub num" id="cbDenSub${d}">${MF.fmt((den[d] || 0) * d)}</span>
          </div>`).join('');
        $('#cbDenRows').querySelectorAll('[data-den]').forEach((inp) => inp.addEventListener('input', () => {
          const d = +inp.dataset.den;
          den[d] = Math.max(0, parseInt(inp.value) || 0);
          $('#cbDenSub' + d).textContent = MF.fmt(den[d] * d);
          const total = DENOMS.reduce((s, x) => s + (den[x] || 0) * x, 0);
          $('#cbDenTotal').textContent = MF.fmt(total);
          $('#cbCount').value = total || '';
          paintVariance();
        }));
      }
      $('#cbDenToggle').addEventListener('click', () => {
        const box = $('#cbDenBox');
        box.hidden = !box.hidden;
        if (!box.hidden) denRows();
      });

      $('#cbSaveCount').addEventListener('click', async () => {
        const count = parseFloat($('#cbCount').value);
        if (!(count >= 0)) { MF.toast('Count the drawer first.', 'warn'); return; }
        $('#cbSaveCount').disabled = true;
        try {
          await MF.Api.post('cash-book.php', { date: $('#cbDate').value, count, note: $('#cbCountNote').value.trim() });
          MF.toast('Drawer count saved.', 'success');
          load();
        } catch (err) {
          MF.toast(err.message || 'Could not save the count.', 'danger');
        } finally {
          $('#cbSaveCount').disabled = false;
        }
      });

      /* Day sheet print */
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
      $('#cbPrint').addEventListener('click', () => {
        if (!book) { MF.toast('Nothing to print yet.', 'warn'); return; }
        const b = book, store = D.store || {};
        const meta = (t) => (TYPE_META[t] || { label: t });
        const physLine = b.physical
          ? `<div class="c-kv"><span>Physical count</span><b>₹ ${MF.fmt(b.physical.count)}</b></div>
             <div class="c-kv"><span>Variance</span><b>${varianceText(b.physical.count - b.closing)}</b></div>`
          : `<div class="c-kv"><span>Physical count</span><span>Not recorded</span></div>`;
        MF.printHtml(`<style>
          @page { size: A5; margin: 12mm; }
          .c-sh { font-family:Arial, Helvetica, sans-serif; color:#111827; font-size:11px; line-height:1.5; }
          .c-h { text-align:center; border-bottom:2px solid #176B5B; padding-bottom:7px; margin-bottom:8px; }
          .c-shop { font-size:16px; font-weight:800; }
          .c-sub { font-size:8.5px; color:#4b5563; }
          .c-title { text-align:center; font-weight:800; letter-spacing:.12em; font-size:10px; margin:6px 0; }
          table { width:100%; border-collapse:collapse; font-size:9.5px; }
          th, td { border-bottom:1px solid #e5e7eb; padding:3px 4px; text-align:left; }
          th { border-top:1px solid #111827; border-bottom:1px solid #111827; font-size:8px; text-transform:uppercase; letter-spacing:.06em; color:#4b5563; }
          .r { text-align:right; } .b { font-weight:700; }
          .c-kv { display:flex; justify-content:space-between; padding:2.5px 0; font-size:10.5px; }
          .c-sum { margin-top:8px; border-top:1.5px solid #111827; padding-top:5px; }
          .c-sign { margin-top:26px; display:flex; justify-content:space-between; font-size:9px; }
          .c-sign div { border-top:1px solid #111827; padding-top:3px; min-width:34mm; text-align:center; }
          .c-ft { text-align:center; margin-top:10px; font-size:8.5px; color:#6b7280; }
        </style>
        <div class="c-sh">
          <div class="c-h">
            <div class="c-shop">${esc(store.name || 'Pharmacy')}</div>
            ${store.address ? `<div class="c-sub">${esc(store.address)}</div>` : ''}
            ${store.phone ? `<div class="c-sub">Ph: ${esc(store.phone)}</div>` : ''}
          </div>
          <div class="c-title">CASH BOOK — DAY SHEET · ${MF.fmtDate(b.date)}</div>
          <div class="c-kv"><span>Opening cash</span><b>₹ ${MF.fmt(b.opening)}</b></div>
          <table>
            <thead><tr><th>Time</th><th>Type</th><th>Ref</th><th>Party</th><th class="r">In</th><th class="r">Out</th></tr></thead>
            <tbody>
              ${b.movements.map((m) => `<tr><td>${m.time && m.time !== '00:00' ? esc(m.time) : '—'}</td><td>${meta(m.type).label}</td><td>${esc(m.ref || '—')}</td><td>${esc(m.party || '—')}</td><td class="r">${m.direction === 'in' ? MF.fmt(m.amount) : ''}</td><td class="r">${m.direction === 'out' ? MF.fmt(m.amount) : ''}</td></tr>`).join('') ||
              '<tr><td colspan="6" style="text-align:center;color:#6b7280">No cash movements</td></tr>'}
            </tbody>
          </table>
          <div class="c-sum">
            <div class="c-kv"><span>Cash in</span><b>₹ ${MF.fmt(b.cashIn)}</b></div>
            <div class="c-kv"><span>Cash out</span><b>₹ ${MF.fmt(b.cashOut)}</b></div>
            <div class="c-kv"><span>Book closing</span><b style="font-size:13px">₹ ${MF.fmt(b.closing)}</b></div>
            <div style="font-size:8.5px;color:#4b5563;text-transform:uppercase;text-align:right">Rupees ${numWords(b.closing)} only</div>
            ${physLine}
            ${b.physical && b.physical.note ? `<div class="c-kv"><span>Count note</span><span>${esc(b.physical.note)}</span></div>` : ''}
          </div>
          <div class="c-sign"><div>Counted by</div><div>Authorised Signatory</div></div>
          <div class="c-ft">Computer-generated day sheet · ${esc(store.name || 'Pharmacy')}</div>
        </div>`);
        function varianceText(diff) {
          if (Math.abs(diff) < 0.005) return 'Matched';
          if (diff < 0) return 'Short ₹ ' + MF.fmt(Math.abs(diff));
          return 'Excess ₹ ' + MF.fmt(diff);
        }
      });

      $('#cbPrev').addEventListener('click', () => shiftDate(-1));
      $('#cbNext').addEventListener('click', () => shiftDate(1));
      $('#cbDate').addEventListener('change', load);
      load();
    })();
    });
  </script>
</body>
</html>
