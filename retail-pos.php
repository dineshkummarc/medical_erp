<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/middleware/auth.php'; // redirects to /login.php if not logged in

$client = Tenant::current();
$user   = Auth::user();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Retail POS · OPTMS-RX</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .pos-held {
      position:relative; border:1px solid #b7ddd4; background:#fff; color:var(--mf-primary-dark);
      border-radius:999px; font-weight:700; font-size:.8rem; padding:6px 12px;
      display:inline-flex; align-items:center; gap:6px;
      transition:background .15s ease, color .15s ease, border-color .15s ease, transform .15s ease, box-shadow .15s ease;
    }
    .pos-held:hover { background:var(--mf-primary-soft); border-color:var(--mf-primary); color:var(--mf-primary-dark); transform:translateY(-1px); box-shadow:0 4px 12px rgba(23,107,91,.16); }
    .pos-held:active { transform:translateY(0); box-shadow:none; }
    .pos-held-count {
      min-width:18px; height:18px; border-radius:999px; background:var(--mf-danger); color:#fff;
      font-size:.68rem; font-weight:750; display:inline-flex; align-items:center; justify-content:center; padding:0 5px;
    }
    .pos-pick-tabs { display:flex; gap:6px; flex-wrap:wrap; margin:2px 0 10px; }
    .pos-pick-tab {
      border:1px solid #d7ebe6; background:#fff; color:#516278; border-radius:999px;
      font-size:.75rem; font-weight:700; padding:5px 12px; cursor:pointer;
      transition:background .15s ease, color .15s ease, border-color .15s ease;
    }
    .pos-pick-tab.is-on { background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-color:var(--mf-primary); }
    .pos-pick-tab:hover { border-color:var(--mf-primary); color:var(--mf-primary-dark); }
    .pos-sub-for { font-size:.68rem; font-weight:700; color:#6D28D9; margin:8px 0 2px; }
    .pos-rx-verify {
      display:none; align-items:center; justify-content:space-between; gap:12px;
      margin-top:10px; padding:8px 12px; border-radius:999px; background:#f7f4ff; border:1px solid #e6defa;
      transition:border-color .15s ease, background .15s ease;
    }
    .pos-rx-verify.show { display:flex; }
    .pos-rx-verify:hover { border-color:#c4b5fd; background:#f3edff; }
    .pos-rx-verify .rx-chip { font-size:.78rem; padding:.35rem .7rem; }
    .pos-switch { position:relative; width:42px; height:24px; flex:0 0 42px; margin:0; }
    .pos-switch input { position:absolute; opacity:0; width:0; height:0; }
    .pos-switch span {
      position:absolute; inset:0; background:#ddd6fe; border-radius:999px; cursor:pointer;
      transition:background .15s ease;
    }
    .pos-switch span:before {
      content:""; position:absolute; width:18px; height:18px; left:3px; top:3px; border-radius:50%;
      background:#fff; box-shadow:0 1px 3px rgba(76,29,149,.2); transition:transform .15s ease;
    }
    .pos-switch input:checked + span { background:#7c3aed; }
    .pos-switch input:checked + span:before { transform:translateX(18px); }
    .pos-switch:hover span { background:#c4b5fd; }
    .pos-switch:hover input:checked + span { background:#6d28d9; }
    .pos-rx-panel { margin-top:8px; padding:10px 12px; border:1px solid #d7ebe6; border-radius:12px; background:#f7fbfa; }
    .pos-rx-meta { margin-top:8px; color:#516278; font-size:.82rem; line-height:1.45; }
    .pos-rx-meta strong { color:#1b2430; }

    /* Complete Sale — full-width, sticky at the bottom of the invoice card */
    .pos-complete-bar {
      position:sticky; bottom:0; z-index:30; margin:14px -16px -16px; padding:10px 16px 14px;
      background:rgba(255,255,255,.94); backdrop-filter:blur(6px); border-top:1px solid #E4EBF4;
    }
    .pos-complete-btn {
      width:100%; display:flex; align-items:center; justify-content:center; gap:8px;
      font-size:1.02rem; font-weight:700; padding:12px 16px; border-radius:12px;
      box-shadow:0 8px 20px -8px rgba(23,107,91,.45);
      transition:transform .12s ease, box-shadow .15s ease, filter .15s ease;
    }
    .pos-complete-btn:hover { transform:translateY(-1px); box-shadow:0 12px 26px -10px rgba(23,107,91,.5); filter:brightness(1.04); }
    .pos-complete-btn:active { transform:translateY(0); box-shadow:none; }
    .pos-complete-btn:disabled { opacity:.6; }
    .pos-complete-amt { font-size:1.12rem; font-weight:800; font-variant-numeric:tabular-nums; }
  </style>
</head>
<body data-page="retail-pos">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-cart3 me-2 text-success"></i>Retail POS</h1>
            <p class="page-sub">Counter billing · FEFO batch picking · GST-inclusive MRP pricing</p>
          </div>
          <div class="ms-auto d-flex align-items-center gap-2">
            <span class="badge badge-soft-secondary"><i class="bi bi-person me-1"></i><?= htmlspecialchars($user['name']) ?></span>
          </div>
        </div>

        <div class="pos-grid">
          <!-- LEFT: search -->
          <div class="card-mf p-3">
            <label class="form-label d-flex justify-content-between" for="posSearch">
               <span>Search medicine</span>
               <span class="badge bg-light text-dark border">F2</span>
            </label>
            <div class="input-group mb-2">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input id="posSearch" class="form-control" placeholder="Medicine name, barcode or batch…" autocomplete="off" autofocus>
            </div>
            <div class="pos-pick-tabs" id="posPickTabs">
              <button type="button" class="pos-pick-tab is-on" data-pick="quick">Quick picks</button>
              <button type="button" class="pos-pick-tab" data-pick="recent">Recent</button>
              <button type="button" class="pos-pick-tab" data-pick="subs">Substitutes</button>
            </div>
            <div id="posResults"></div>
          </div>

          <!-- RIGHT: current invoice -->
          <div class="card-mf">
            <div class="card-head">
              <h2 class="card-title"><i class="bi bi-receipt"></i>Current Invoice</h2>
              <div class="card-tools">
                <button class="pos-held" id="posHeldChip" type="button">
                  <i class="bi bi-hourglass-split"></i>Held Bills
                  <span class="pos-held-count" id="posHeldBadge" style="display:none">0</span>
                </button>
              </div>
            </div>
            <div class="p-3">
              <div class="row g-2 mb-2">
                <div class="col-md-6">
                  <label class="form-label" for="posCustomer">Customer</label>
                  <div class="d-flex gap-2">
                    <select class="form-select" id="posCustomer"></select>
                    <button class="btn btn-light-mf" type="button" id="posAddCustomer" title="Add new customer"><i class="bi bi-plus-lg"></i></button>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="posDoctor">Prescribing Doctor</label>
                  <select class="form-select" id="posDoctor">
                    <option value="">— Walk-in / none —</option>
                  </select>
                </div>
              </div>
              <div class="pos-rx-verify" id="posRxToggleWrap">
                <span class="rx-chip" id="posRxChip"><i class="bi bi-file-medical"></i>Rx verification needed</span>
                <label class="pos-switch" for="posRxOn" title="Attach the prescription">
                  <input id="posRxOn" type="checkbox">
                  <span></span>
                </label>
              </div>
              <div class="pos-rx-panel" id="posRxPanel" hidden>
                <label class="form-label" for="posRx">Prescription</label>
                <select class="form-select" id="posRx">
                  <option value="">— select prescription —</option>
                </select>
                <div class="pos-rx-meta" id="posRxMeta">Turn the switch on to attach the recorded prescription for this bill.</div>
              </div>

              <div id="posCartBody"></div>

              <div class="row g-2 align-items-end mt-2">
                <div class="col-6">
                  <label class="form-label">Bill-level discount (%)</label>
                  <input type="number" min="0" max="100" class="form-control" id="posGlobalDisc" value="0" placeholder="0">
                </div>
                <div class="col-6"><div id="posSummary"></div></div>
              </div>

              <div class="sr-group-label mt-3">Payment</div>
              <div class="row g-2 row-cols-5 mb-3">
                <div class="col pay-opt"><input type="radio" name="posPay" id="posPayCash" value="cash" checked><label for="posPayCash"><i class="bi bi-cash"></i>Cash <small class="d-block text-muted">F3</small></label></div>
                <div class="col pay-opt"><input type="radio" name="posPay" id="posPayUpi" value="upi"><label for="posPayUpi"><i class="bi bi-qr-code-scan"></i>UPI <small class="d-block text-muted">F4</small></label></div>
                <div class="col pay-opt"><input type="radio" name="posPay" id="posPayCard" value="card"><label for="posPayCard"><i class="bi bi-credit-card"></i>Card <small class="d-block text-muted">F5</small></label></div>
                <div class="col pay-opt"><input type="radio" name="posPay" id="posPayCredit" value="credit"><label for="posPayCredit"><i class="bi bi-journal-text"></i>Credit <small class="d-block text-muted">F6</small></label></div>
                <div class="col pay-opt"><input type="radio" name="posPay" id="posPaySplit" value="split"><label for="posPaySplit"><i class="bi bi-diagram-3"></i>Split <small class="d-block text-muted">F7</small></label></div>
              </div>

              <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-light-mf" id="posHold" type="button"><i class="bi bi-hourglass-split me-1"></i>Hold Bill <span class="badge bg-light text-dark border ms-1">F8</span></button>
                <button class="btn btn-light-mf" id="posDraft" type="button"><i class="bi bi-save me-1"></i>Save Draft <span class="badge bg-light text-dark border ms-1">F9</span></button>
                <div class="btn-group">
                  <button class="btn btn-light-mf" id="posPrint" type="button"><i class="bi bi-printer me-1"></i>Print · <span id="posPrintLbl">A4</span> <span class="badge bg-light text-dark border ms-1">Ctrl+P</span></button>
                  <button class="btn btn-light-mf dropdown-toggle dropdown-toggle-split" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Print options"><span class="visually-hidden">Print options</span></button>
                  <ul class="dropdown-menu">
                    <li><button class="dropdown-item" id="posPrintThermal" type="button"><i class="bi bi-receipt me-2"></i>Thermal 80mm</button></li>
                    <li><button class="dropdown-item" id="posPrintA4" type="button"><i class="bi bi-file-earmark-ruled me-2"></i>A4</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item" id="posPrintComplete" type="button"><i class="bi bi-check2-circle me-2"></i>Complete &amp; Print <span class="text-2 small-xs ms-1">F10</span></button></li>
                  </ul>
                </div>
                <button class="btn btn-light-mf text-danger ms-auto" id="posClearCart" type="button"><i class="bi bi-trash3 me-1"></i>Clear <span class="badge bg-light text-dark border ms-1">Alt+C</span></button>
              </div>

              <div class="pos-complete-bar">
                <button class="btn btn-mf pos-complete-btn" id="posComplete" type="button">
                  <i class="bi bi-check2-circle"></i>
                  <span>Complete Sale ·</span>
                  <span class="pos-complete-amt" id="posCompleteAmt">₹0</span>
                  <span class="badge bg-white text-success">F10</span>
                </button>
              </div>
            </div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <!-- Split payment modal -->
  <div class="modal fade" id="posSplitModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-mf-sm">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-diagram-3 me-2 text-success"></i>Split Payment</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Cash amount (₹)</label>
            <input type="number" class="form-control" id="splitCash" value="0" min="0">
          </div>
          <div>
            <label class="form-label">UPI amount (₹)</label>
            <input type="number" class="form-control" id="splitUpi" value="0" min="0">
          </div>
          <p class="text-2 small mt-3 mb-0">Both amounts together must equal the Grand Total.</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="posSplitApply">Apply Split</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Held bills modal -->
  <div class="modal fade" id="posHeldModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-hourglass-split me-2 text-success"></i>Held Bills</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="posHeldBody"></div>
      </div>
    </div>
  </div>

  <!-- Add Customer modal -->
  <div class="modal fade" id="addCustomerModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Customer</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Name <span class="req">*</span></label>
            <input class="form-control" id="pcName" placeholder="e.g. Ramesh Kumar">
          </div>
          <div class="mb-2">
            <label class="form-label">Phone</label>
            <input class="form-control" id="pcPhone">
          </div>
          <div class="mb-2">
            <label class="form-label">Address</label>
            <input class="form-control" id="pcAddress">
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="pcSave">Save Customer</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/data.js"></script>
  <script src="assets/js/config.js"></script>
  <script src="assets/js/app.js"></script>
  <script src="assets/js/pos.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', async () => {
      await MF.boot();
      const D = window.MF_DATA;

      function renderCustomers() {
        const custSel = document.getElementById('posCustomer');
        custSel.innerHTML = D.customers.map((c) =>
          `<option value="${c.id}">${MF.esc(c.name)}${c.name === 'Walk-in Customer' ? ' (default)' : ''}</option>`).join('');
      }
      renderCustomers();

      document.getElementById('posAddCustomer').addEventListener('click', () => {
        ['pcName', 'pcPhone', 'pcAddress'].forEach((id) => document.getElementById(id).value = '');
        new bootstrap.Modal('#addCustomerModal').show();
      });
      document.getElementById('pcSave').addEventListener('click', async () => {
        const name = document.getElementById('pcName').value.trim();
        if (!name) { MF.toast('Customer name is required.', 'warn'); return; }
        try {
          const res = await MF.Api.post('customers.php', {
            name,
            phone: document.getElementById('pcPhone').value.trim(),
            address: document.getElementById('pcAddress').value.trim(),
          });
          await MF.rehydrate();
          renderCustomers();
          document.getElementById('posCustomer').value = res.id;
          bootstrap.Modal.getInstance(document.getElementById('addCustomerModal')).hide();
          MF.toast('Customer added.', 'success');
        } catch (err) {
          MF.toast(err.message || 'Could not add customer.', 'danger');
        }
      });
    });
  </script>
</body>
</html>
