<?php
session_start();
require __DIR__ . '/middleware/tenant.php';
require __DIR__ . '/middleware/auth.php'; // redirects to /login.php if not logged in
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Medicine Master · Optms Rx</title>
  <link rel="icon" href="assets/images/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    #mmFormModal .modal-dialog { width:840px; max-width:840px; }
    @media (max-width: 880px) { #mmFormModal .modal-dialog { width:calc(100% - 1rem); } }
    .mm-pack-note {
      color:#5f7084; font-size:.84rem; line-height:1.4; background:#fff;
      border:1px dashed #d7e0ea; border-radius:10px; min-height:42px;
      display:flex; align-items:center; padding:8px 12px;
    }
    .mm-section-title{
      font-size:.75rem;
      font-weight:700;
      text-transform:uppercase;
      letter-spacing:.04em;
      color:#6c757d;
      padding-bottom:.35rem;
      margin-bottom:.9rem;
      border-bottom:1px solid #e9ecef;
    }
    .mm-section-title:not(:first-child){ margin-top:1.5rem; }

    /* Packaging & Identification — grouped, icon fields. Every original field stays. */
    .mm-pack { display:grid; gap:12px; }
    .mm-pack-group {
      background:#f8fafc;
      border:1px solid #e7edf4;
      border-radius:14px;
      padding:12px 14px 14px;
    }
    .mm-pack-head {
      display:flex; align-items:center; gap:8px;
      font-size:.78rem; font-weight:700; color:#176B5B; margin-bottom:10px;
    }
    .mm-pack-head i {
      width:26px; height:26px; border-radius:8px; display:grid; place-items:center;
      background:#E6F1EE; font-size:.9rem;
    }
    .mm-input {
      display:flex; align-items:center; gap:8px;
      background:#fff; border:1px solid #e3e9f1; border-radius:10px;
      padding:0 10px; min-height:42px;
      transition:border-color .15s ease, box-shadow .15s ease;
    }
    .mm-input:focus-within { border-color:#176B5B; box-shadow:0 0 0 3px rgba(23,107,91,.12); }
    .mm-input > i { color:#8aa0b8; font-size:1rem; flex:0 0 auto; }
    .mm-input .form-control,
    .mm-input .form-select {
      border:0; background:transparent; box-shadow:none;
      padding-left:0; height:40px; min-height:40px;
    }
    .mm-input .form-control:focus,
    .mm-input .form-select:focus { box-shadow:none; background:transparent; }
    .mm-input .form-select {
      appearance:none; -webkit-appearance:none; -moz-appearance:none;
      background-image:none !important; cursor:pointer; padding-right:.4rem;
    }
    .mm-input { position:relative; }
    .mm-select-caret {
      color:#8aa0b8; font-size:.72rem; pointer-events:none; margin-left:auto;
      transition:transform .15s ease, color .15s ease;
    }
    .mm-input.is-open { border-color:#176B5B; box-shadow:0 0 0 3px rgba(23,107,91,.12); }
    .mm-input.is-open .mm-select-caret { transform:rotate(180deg); color:#176B5B; }
    .mm-hint { color:#8b9bb0; font-size:.78rem; margin-top:6px; line-height:1.4; }
    .mm-sched {
      display:flex; flex-wrap:wrap; width:fit-content; max-width:100%;
      border:1px solid #d7e0ea; border-radius:10px; overflow:hidden; background:#fff;
    }
    .mm-sched { align-items:stretch; }
    .mm-sched-opt {
      border:0; background:#fff; color:#1b2430; font-weight:650; font-size:.84rem; line-height:1.2;
      margin:0; min-height:40px; padding:8px 14px; border-right:1px solid #e3ebf4;
      display:inline-flex; align-items:center; justify-content:center; align-self:stretch;
    }
    .mm-sched-opt:last-child { border-right:0; }
    .mm-sched-opt.is-on { color:#fff; }
    .mm-sched-opt.is-on.otc { background:#176B5B; }
    .mm-sched-opt.is-on.h { background:#0369A1; }
    .mm-sched-opt.is-on.sh1 { background:#6D28D9; }
    .mm-sched-opt.is-on.x { background:#B42318; }
    .mm-chips {
      display:flex; flex-wrap:wrap; gap:8px; align-items:center;
      border:1px solid #e3e9f1; border-radius:10px; background:#fff; padding:8px 10px; min-height:42px;
    }
    .mm-chip-list { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .mm-chips:focus-within { border-color:#176B5B; box-shadow:0 0 0 3px rgba(23,107,91,.12); }
    .mm-chip {
      display:inline-flex; align-items:center; gap:6px; background:#E6F1EE; color:#0F4D42;
      border-radius:999px; padding:4px 10px; font-size:.78rem; font-weight:700;
    }
    .mm-chip button { border:0; background:transparent; color:inherit; line-height:1; padding:0 2px; font-size:1rem; }
    .mm-chip-input { border:0; outline:0; flex:1; min-width:160px; background:transparent; font-size:.9rem; }
    .mm-price-warn {
      display:flex; align-items:center; gap:8px; color:#B45309; background:#FEF3E2;
      border:1px solid #f6d7a2; border-radius:10px; padding:8px 12px; font-size:.82rem; font-weight:650;
    }
    .mm-per {
      display:flex; align-items:center; gap:8px; width:100%;
      background:#fff; border:1px solid #e3e9f1; border-radius:10px; min-height:42px; padding:0 12px;
    }
    .mm-per:focus-within { border-color:#176B5B; box-shadow:0 0 0 3px rgba(23,107,91,.12); }
    .mm-per input {
      border:0; outline:0; background:transparent; flex:1 1 auto; width:1%; min-width:0; min-height:40px; padding:0;
      font-size:1rem; color:#1b2430;
    }
    .mm-per-unit { flex:0 0 auto; color:#8b9bb0; font-size:.95rem; white-space:nowrap; }
    .mm-align > [class*="col-"] { display:flex; flex-direction:column; }
    .mm-align .form-label { min-height:18px; margin-bottom:6px; line-height:1.2; }
    .mm-align .form-control {
      min-height:42px; border-radius:10px; border-color:#e3e9f1;
    }
    .mm-align .form-control:focus { border-color:#176B5B; box-shadow:0 0 0 3px rgba(23,107,91,.12); }
    .mm-field-hint { color:#8b9bb0; font-size:.78rem; margin-top:6px; line-height:1.4; }
    .mm-disc-switch { display:flex; border:1px solid #e3e9f1; border-radius:8px; overflow:hidden; flex:0 0 auto; }
    .mm-disc-switch button {
      border:0; background:#fff; color:#6c757d; min-width:32px; height:28px; padding:0 8px; font-weight:700;
    }
    .mm-disc-switch button.is-on { background:#176B5B; color:#fff; }
    .mm-price-cards { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:12px; }
    .mm-price-card {
      background:#f6f8fb; border:1px solid #e7edf4; border-radius:12px; padding:12px 14px; min-width:0;
    }
    .mm-price-card span { display:block; color:#6b7c90; font-size:.78rem; font-weight:600; margin-bottom:4px; }
    .mm-price-card strong { display:block; color:#1b2430; font-size:1.15rem; font-weight:750; letter-spacing:-.01em; }
    .mm-gst-note { color:#6b7c90; font-size:.82rem; margin-top:10px; }
    .mm-form-footer { display:flex; justify-content:space-between; align-items:center; gap:12px; width:100%; }
    .mm-form-actions { display:flex; flex-wrap:wrap; gap:10px; }
    .mm-save, .mm-save-another, .mm-cancel {
      border-radius:10px; font-weight:650; min-height:40px; padding:8px 16px;
      display:inline-flex; align-items:center; justify-content:center; gap:10px;
    }
    .mm-save { background:#176B5B; border:0; color:#fff; padding-right:12px; }
    .mm-save:hover, .mm-save:focus { background:#0F4D42; color:#fff; }
    .mm-save kbd {
      background:rgba(255,255,255,.18); color:#fff; border:0; border-radius:6px;
      font-family:inherit; font-size:.72rem; font-weight:650; padding:2px 6px; line-height:1.4;
    }
    .mm-save-another { background:#E6F1EE; border:0; color:#176B5B; }
    .mm-save-another:hover, .mm-save-another:focus { background:#D8EAE5; color:#0F4D42; }
    .mm-cancel { background:#fff; border:1px solid #d7dee7; color:#1b2430; }
    .mm-cancel:hover { background:#f8fafc; color:#1b2430; }
    .mm-save.is-busy, .mm-save-another.is-busy { position:relative; overflow:hidden; pointer-events:none; }
    .mm-save.is-busy::after, .mm-save-another.is-busy::after {
      content:""; position:absolute; left:0; bottom:0; height:3px; width:35%;
      background:#fff; animation:mm-load .8s ease-in-out infinite;
    }
    .mm-save-another.is-busy::after { background:#176B5B; }
    @keyframes mm-load { 0% { transform:translateX(-120%); } 100% { transform:translateX(320%); } }
    @media (max-width: 700px) { .mm-price-cards { grid-template-columns:1fr; } }
    .mm-select-hit {
      position:absolute; inset:0; border:0; background:transparent; cursor:pointer; z-index:2;
    }
    .mm-select-plain { position:relative; }
    .mm-select-plain .mm-select-hit { border-radius:inherit; }

    /* Custom option list — same language as the action menu, not the browser popup */
    .mm-select-menu {
      position:fixed; z-index:2000; display:none; padding:6px;
      background:#fff; border:1px solid #e7edf4; border-radius:12px;
      box-shadow:0 16px 40px rgba(16,32,64,.16);
      max-height:280px; overflow:auto;
    }
    .mm-select-menu.show { display:block; }
    .mm-select-search {
      display:flex; align-items:center; gap:6px; margin:2px 2px 6px;
      padding:0 8px; height:34px; border-radius:8px; background:#f6f9fc; border:1px solid #e7edf4;
    }
    .mm-select-search i { color:#8aa0b8; font-size:.85rem; }
    .mm-select-search input {
      border:0; outline:0; background:transparent; width:100%; font:inherit; font-size:.82rem; color:#1b2430;
    }
    .mm-select-opt {
      width:100%; display:flex; align-items:center; justify-content:space-between; gap:10px;
      border:0; background:transparent; border-radius:8px; padding:.48rem .7rem;
      font:inherit; font-size:.84rem; font-weight:600; color:#1b2430; cursor:pointer; text-align:left;
    }
    .mm-select-opt i { color:#176B5B; opacity:0; font-size:.95rem; }
    .mm-select-opt:hover, .mm-select-opt.is-hot { background:#f4f7fb; }
    .mm-select-opt.is-on { background:#E6F1EE; color:#176B5B; }
    .mm-select-opt.is-on i { opacity:1; }
    .mm-select-empty { padding:.6rem .7rem; color:#6c757d; font-size:.8rem; }

    /* Paired toggles — Stock & Status, and loose sale */
    .mm-switch-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .mm-switch {
      display:flex; align-items:center; gap:12px; margin:0; cursor:pointer;
      padding:10px 12px; border:1px solid #e7edf4; border-radius:12px; background:#fff;
      min-height:42px;
    }
    .mm-switch:hover { border-color:#cfd8e6; }
    .mm-switch strong { display:block; font-size:.84rem; font-weight:650; color:#1b2430; line-height:1.2; }
    .mm-switch small { display:block; color:#6c757d; font-size:.72rem; font-weight:500; margin-top:1px; }
    .mm-switch input {
      appearance:none; -webkit-appearance:none;
      width:40px; height:22px; border-radius:99px; background:#d5dee8;
      position:relative; flex:0 0 auto; margin:0; cursor:pointer;
      transition:background .15s ease;
    }
    .mm-switch input::after {
      content:""; position:absolute; top:2px; left:2px; width:18px; height:18px;
      border-radius:50%; background:#fff; box-shadow:0 1px 2px rgba(16,32,64,.18);
      transition:transform .15s ease;
    }
    .mm-switch input:checked { background:#176B5B; }
    .mm-switch input:checked::after { transform:translateX(18px); }
    .mm-switch input:focus-visible { outline:2px solid #176B5B; outline-offset:2px; }
    .mm-switch-compact { height:42px; padding:6px 10px; }
    .mm-switch-compact strong { font-size:.78rem; }
    .mm-switch-compact small { font-size:.68rem; }

    /* Ledger action menu */
    .mm-kebab {
      width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center;
      border-radius:8px; padding:0;
    }
    .mm-kebab i { font-size:1.15rem; line-height:1; }
    .mm-act-menu {
      min-width:188px; padding:6px; border:1px solid #e7edf4; border-radius:12px;
      box-shadow:0 12px 32px rgba(16,32,64,.14); z-index:1080;
    }
    .mm-act-menu .dropdown-item {
      display:flex; align-items:center; gap:10px;
      font-size:.84rem; font-weight:600; border-radius:8px; padding:.48rem .65rem;
    }
    .mm-act-menu .dropdown-item i { width:1.05rem; font-size:1rem; color:#176B5B; }
    .mm-act-menu .dropdown-item:hover { background:#f4f7fb; }
    .mm-act-menu .dropdown-item.text-danger i { color:inherit; }
    .mm-act-menu .dropdown-divider { margin:.35rem 0; }
    @media (max-width: 575.98px) {
      .mm-switch-row { grid-template-columns:1fr 1fr; }
      .mm-detail-stats, .mm-stock-kpis { grid-template-columns:1fr 1fr; }
      .mm-detail-cols { grid-template-columns:1fr; }
    }

    /* Medicine details */
    .mm-detail-hero { display:flex; align-items:flex-start; gap:14px; margin-bottom:14px; }
    .mm-detail-icon {
      width:52px; height:52px; border-radius:14px; flex:0 0 auto;
      display:grid; place-items:center; background:#E6F1EE; color:#176B5B; font-size:1.35rem;
    }
    .mm-detail-name { font-size:1.12rem; font-weight:750; letter-spacing:-.02em; line-height:1.25; }
    .mm-detail-name span { color:#6c757d; font-weight:500; }
    .mm-detail-sub { color:#6c757d; font-size:.82rem; margin-top:3px; }
    .mm-detail-badges { margin-left:auto; display:flex; flex-wrap:wrap; gap:6px; justify-content:flex-end; }
    .mm-detail-stats, .mm-stock-kpis { display:grid; grid-template-columns:repeat(4, 1fr); gap:10px; }
    .mm-stat, .mm-kpi {
      background:#f8fafc; border:1px solid #e7edf4; border-radius:14px; padding:10px 12px;
    }
    .mm-stat span, .mm-kpi span {
      display:block; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#6c757d;
    }
    .mm-stat strong, .mm-kpi strong {
      display:block; margin-top:3px; font-size:1.05rem; font-weight:750; letter-spacing:-.02em;
      font-variant-numeric:tabular-nums; color:#1b2430;
    }
    .mm-kpi strong { font-size:1.2rem; }
    .mm-stat small, .mm-kpi small { display:block; margin-top:2px; color:#6c757d; font-size:.75rem; }
    .mm-kpi.accent { background:#E6F1EE; border-color:#d5e8e3; }
    .mm-detail-cols { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:14px; }
    .mm-detail-card { border:1px solid #e7edf4; border-radius:14px; padding:12px 14px 4px; background:#fff; }
    .mm-detail-card .mm-pack-head { margin-bottom:4px; }
    .mm-dl-item {
      display:flex; justify-content:space-between; align-items:baseline; gap:16px;
      padding:8px 0; border-top:1px solid #f1f4f8; font-size:.84rem;
    }
    .mm-dl-item span { color:#6c757d; flex:0 0 auto; }
    .mm-dl-item strong { font-weight:650; text-align:right; color:#1b2430; }

    /* Stock summary */
    .mm-stock-top { padding:16px 16px 0; }
    .mm-stock-bar {
      display:flex; align-items:center; justify-content:space-between; gap:12px;
      padding:12px 16px;
    }
    .mm-stock-note {
      display:flex; align-items:center; gap:8px; margin:0 16px 12px; padding:8px 12px;
      border-radius:10px; font-size:.82rem; font-weight:600;
    }
    .mm-stock-note.low { background:#fff6e4; color:#8a5a00; }
    .mm-stock-note.out { background:#fdeeee; color:#c62828; }
    .mm-batch {
      display:inline-flex; align-items:center; gap:6px; background:#f4f7fb; color:#176B5B;
      border-radius:999px; padding:3px 8px; font-weight:700; font-size:.78rem;
    }
    .mm-exp { font-weight:650; }
    .mm-exp.ok { color:#157347; }
    .mm-exp.warn { color:#a86400; }
    .mm-exp.bad { color:#c62828; }
    .mm-stock-table thead th {
      font-size:.72rem; letter-spacing:.04em; text-transform:uppercase; color:#6c757d;
      font-weight:700; background:#f8fafc;
    }
    .mm-stock-empty { padding:28px 16px; }
  </style>
</head>
<body data-page="medicine-master">
  <div class="mf-layout">
    <aside class="mf-sidebar" id="mf-sidebar"></aside>
    <div class="mf-body">
      <header class="mf-topbar" id="mf-topbar"></header>
      <main class="mf-main">

        <div class="page-head">
          <div>
            <h1 class="page-title"><i class="bi bi-capsule me-2 text-success"></i>Products / Medicines</h1>
            <p class="page-sub" id="mmCount"></p>
          </div>
          <div class="ms-auto d-flex gap-2">
            <button class="btn btn-light-mf" id="mmExport"><i class="bi bi-download me-1"></i>Export CSV</button>
            <button class="btn btn-mf" id="mmAddBtn"><i class="bi bi-plus-lg me-1"></i>Add Medicine</button>
          </div>
        </div>

        <!-- Filters -->
        <div class="card-mf p-3 mb-3">
          <div class="row g-2">
            <div class="col-md-3">
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input class="form-control" id="mmSearch" placeholder="Search name, generic, brand, composition…">
              </div>
            </div>
            <div class="col-6 col-md-2"><select class="form-select" id="mmCategory"><option value="">All Categories</option></select></div>
            <div class="col-6 col-md-2"><select class="form-select" id="mmMfg"><option value="">All Manufacturers</option></select></div>
            <div class="col-6 col-md-2">
              <select class="form-select" id="mmStock">
                <option value="">All Stock Status</option>
                <option value="low">Low Stock</option>
                <option value="in">In Stock</option>
                <option value="out">Out of Stock</option>
              </select>
            </div>
            <div class="col-6 col-md-2">
              <select class="form-select" id="mmSchedule">
                <option value="">All Schedules</option>
                <option>OTC</option><option>H</option><option>H1</option><option>X</option>
              </select>
            </div>
            <div class="col-md-1"><button class="btn btn-light-mf w-100" id="mmClear"><i class="bi bi-x-lg"></i> Clear</button></div>
          </div>
        </div>

        <!-- Table -->
        <div class="card-mf">
          <div class="table-scroll" style="max-height:none;overflow:visible">
            <table class="table table-mf">
              <thead>
                <tr>
                  <th>Medicine Name</th><th>Generic Name</th><th>Brand</th><th>Category</th><th>Manufacturer</th>
                  <th>HSN</th><th class="text-center">GST</th><th>Unit</th>
                  <th class="text-end">MRP</th><th class="text-end">Retail</th><th class="text-end">Wholesale</th>
                  <th class="text-end">Stock</th><th>Status</th><th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="mmBody"></tbody>
            </table>
          </div>
          <div class="d-flex flex-wrap align-items-center gap-2 p-3 border-top">
            <span class="text-2 small" id="mmPageInfo"></span>
            <div class="ms-auto"><ul class="pagination pagination-sm mb-0" id="mmPager"></ul></div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <!-- Add / Edit modal -->
  <div class="modal fade" id="mmFormModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="mmFormTitle">Add Medicine</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">

          <div class="mm-section-title">Basic Details</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="fName">Medicine Name <span class="req">*</span></label>
              <input class="form-control" id="fName" placeholder="e.g. Paracetamol 500mg">
              <div id="fNameWarn" class="small mt-1" style="display:none"></div>
              <div class="mm-hint">Include the strength in the name, so staff can tell items apart.</div>
            </div>
            <div class="col-md-6"><label class="form-label">Generic Name</label><input class="form-control" id="fGeneric" placeholder="e.g. Paracetamol"></div>
            <div class="col-md-6"><label class="form-label">Brand Name</label><input class="form-control" id="fBrand" placeholder="e.g. Crocin Advance"></div>
            <div class="col-md-6"><label class="form-label" for="fComp">Composition</label><input class="form-control" id="fComp" placeholder="e.g. Paracetamol 500mg + Caffeine 32mg"></div>
            <div class="col-md-3"><label class="form-label">Category</label><select class="form-select" id="fCategory"></select></div>
            <div class="col-md-3"><label class="form-label">Manufacturer</label><select class="form-select" id="fMfg"></select></div>
            <div class="col-12">
              <label class="form-label" for="fGroupInput">Generic/composition group</label>
              <div class="mm-chips" id="fGroupBox">
                <div class="mm-chip-list" id="fGroupList"></div>
                <input class="mm-chip-input" id="fGroupInput" list="fGenericGroupList" placeholder="Type a salt and press Enter" autocomplete="off">
              </div>
              <input type="hidden" id="fGenericGroup">
              <datalist id="fGenericGroupList"></datalist>
              <div class="mm-hint">One chip per salt. Medicines with the same chips are substitutes of each other.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Drug schedule</label>
              <div class="mm-sched" id="fSchedGroup" role="radiogroup" aria-label="Drug schedule">
                <button type="button" class="mm-sched-opt otc is-on" data-sched="OTC">None (OTC)</button>
                <button type="button" class="mm-sched-opt h" data-sched="H">Schedule H</button>
                <button type="button" class="mm-sched-opt sh1" data-sched="H1">Schedule H1</button>
                <button type="button" class="mm-sched-opt x" data-sched="X">Schedule X</button>
              </div>
              <div class="mm-hint" id="fSchedHint">OTC items can be sold without a prescription.</div>
              <input type="hidden" id="fSchedule" value="OTC">
            </div>
          </div>

          <div class="mm-section-title">Packaging &amp; Identification</div>
          <div class="mm-pack">
            <div class="mm-pack-group">
              <div class="mm-pack-head"><i class="bi bi-fingerprint"></i> Identification</div>
              <div class="row g-3">
                <div class="col-md-6 col-12">
                  <label class="form-label" for="fBarcode">Barcode</label>
                  <div class="mm-input">
                    <i class="bi bi-upc-scan"></i>
                    <input class="form-control" id="fBarcode" placeholder="8901234…">
                  </div>
                  <div id="fBarcodeWarn" class="small mt-1" style="display:none"></div>
                </div>
              </div>
            </div>

            <div class="mm-pack-group">
              <div class="mm-pack-head"><i class="bi bi-capsule"></i> Pack contents</div>
              <div class="row g-3 align-items-start mm-align">
                <div class="col-lg-3 col-md-4 col-6">
                  <label class="form-label" for="fHsn">HSN Code</label>
                  <div class="mm-input">
                    <i class="bi bi-hash"></i>
                    <input class="form-control" id="fHsn" placeholder="e.g. 3004">
                  </div>
                </div>
                <div class="col-lg-3 col-md-4 col-6">
                  <label class="form-label" for="fUnit">Form</label>
                  <div class="mm-input">
                    <i class="bi bi-tag"></i>
                    <select class="form-select" id="fUnit">
                      <option value="" selected>Select form</option>
                      <option>Tablet</option>
                      <option>Capsule</option>
                      <option>Syrup</option>
                      <option>Drops</option>
                      <option>Injection</option>
                      <option>Ointment</option>
                      <option>Cream</option>
                      <option>Powder</option>
                      <option>Sachet</option>
                      <option>Other</option>
                    </select>
                  </div>
                </div>
                <div class="col-lg-3 col-md-4 col-12" id="fPackQtyWrap">
                  <label class="form-label" for="fPackQty"><span id="fPackQtyLabel">Units per strip</span> <span class="req">*</span></label>
                  <div class="mm-per">
                    <input type="number" min="1" id="fPackQty" value="" placeholder="e.g. 10">
                    <span class="mm-per-unit" id="fPackQtyUnit">units</span>
                  </div>
                </div>
                <div class="col-lg-6 col-md-8 col-12" id="fPackNoteWrap" hidden>
                  <label class="form-label" aria-hidden="true">&nbsp;</label>
                  <div class="mm-pack-note" id="fPackNote">Sold as one complete bottle — no sub-units to enter.</div>
                </div>
                <div class="col-lg-3 col-md-6 col-12" id="fLooseWrap" hidden>
                  <label class="form-label" aria-hidden="true">&nbsp;</label>
                  <label class="mm-switch mm-switch-compact">
                    <input type="checkbox" id="fAllowLoose">
                    <span><strong>Allow loose sale</strong><small id="fLooseHint">Sell single tablets</small></span>
                  </label>
                </div>
              </div>
            </div>

            <div class="mm-pack-group">
              <div class="mm-pack-head"><i class="bi bi-box-seam"></i> Outer box</div>
              <div class="row g-3 align-items-start mm-align">
                <div class="col-md-4 col-6">
                  <label class="form-label" for="fBoxQty">Box Qty</label>
                  <div class="mm-input">
                    <i class="bi bi-123"></i>
                    <input type="number" min="1" class="form-control" id="fBoxQty" placeholder="e.g. 10">
                  </div>
                  <div class="text-2 small mt-1" id="fBoxHint">Packs per box</div>
                </div>
                <div class="col-md-4 col-6">
                  <label class="form-label" for="fBoxUnit">Box Unit</label>
                  <div class="mm-input">
                    <i class="bi bi-box"></i>
                    <input class="form-control" id="fBoxUnit" placeholder="Box" value="Box">
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="mm-section-title">Pricing &amp; Tax</div>
          <div class="row g-3 align-items-start mm-align">
            <div class="col-md-3 col-6"><label class="form-label">GST %</label>
              <select class="form-select" id="fGst"><option>5</option><option selected>12</option><option>18</option></select></div>
            <div class="col-md-3 col-6"><label class="form-label" for="fMrp">MRP (₹)</label><input type="number" step="0.01" class="form-control" id="fMrp" placeholder="e.g. 120.00"></div>
            <div class="col-md-3 col-6"><label class="form-label" for="fPtr">Purchase Rate (₹)</label><input type="number" step="0.01" class="form-control" id="fPtr" placeholder="e.g. 80.00"></div>
            <div class="col-md-3 col-6"><label class="form-label" for="fRetail">Retail Rate (₹)</label><input type="number" step="0.01" class="form-control" id="fRetail" placeholder="e.g. 100.00"></div>
            <div class="col-md-3 col-6"><label class="form-label" for="fWholesale">Wholesale Rate (₹)</label><input type="number" step="0.01" class="form-control" id="fWholesale" placeholder="e.g. 90.00"></div>
            <div class="col-md-3 col-6">
              <label class="form-label" for="fDisc">Default discount</label>
              <div class="mm-per">
                <input type="number" min="0" step="0.01" id="fDisc" value="0" placeholder="0">
                <div class="mm-disc-switch" id="fDiscSwitch" role="group" aria-label="Discount type">
                  <button type="button" class="is-on" data-disc="percent" aria-pressed="true">%</button>
                  <button type="button" data-disc="rupee" aria-pressed="false">₹</button>
                </div>
              </div>
              <input type="hidden" id="fDiscType" value="percent">
            </div>
            <div class="col-12" id="fPriceWarn" hidden>
              <div class="mm-price-warn"><i class="bi bi-exclamation-triangle"></i>Purchase rate is above the selling price.</div>
            </div>
            <div class="col-12">
              <div class="mm-price-cards">
                <div class="mm-price-card"><span id="fSellLabel">Selling price / strip</span><strong id="fSellValue">—</strong></div>
                <div class="mm-price-card"><span>Price per unit</span><strong id="fUnitPrice">—</strong></div>
                <div class="mm-price-card"><span>Margin</span><strong id="fMargin">—</strong></div>
              </div>
              <div class="mm-gst-note" id="fGstNote">GST is included in MRP.</div>
            </div>
          </div>

          <div class="mm-section-title">Stock &amp; Status</div>
          <div class="row g-3 align-items-start mm-align">
            <div class="col-md-3 col-6">
              <label class="form-label" for="fBatchNo">Batch no</label>
              <input class="form-control" id="fBatchNo" placeholder="e.g. B24091">
            </div>
            <div class="col-md-3 col-6">
              <label class="form-label" for="fStockQty">Quantity</label>
              <div class="mm-per">
                <input type="number" min="0" id="fStockQty" value="" placeholder="e.g. 20">
                <span class="mm-per-unit" id="fStockQtyUnit">units</span>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <label class="form-label" for="fMin">Minimum Stock</label>
              <input type="number" class="form-control" id="fMin" value="50" placeholder="e.g. 10">
            </div>
            <div class="col-md-3 col-12">
              <label class="form-label" for="fExpiryAlert">Expiry Alert (days)</label>
              <input type="number" min="1" class="form-control" id="fExpiryAlert" placeholder="Default: 90">
            </div>
            <div class="col-md-6 col-12">
              <label class="form-label" for="fReorder">Reorder level</label>
              <div class="mm-per">
                <input type="number" min="0" id="fReorder" value="5" placeholder="e.g. 5">
                <span class="mm-per-unit" id="fReorderUnit">units</span>
              </div>
              <div class="mm-field-hint">Shows "Low stock" at or below this.</div>
            </div>
            <div class="col-md-6 col-12">
              <label class="form-label" for="fRack">Rack / shelf</label>
              <input class="form-control" id="fRack" placeholder="e.g. A-3">
            </div>
            <div class="col-md-6 col-12">
              <label class="mm-switch">
                <input type="checkbox" id="fActive" checked>
                <span><strong>Active</strong><small>Inactive items stay out of POS</small></span>
              </label>
            </div>
            <input type="checkbox" id="fRx" hidden>
          </div>

        </div>
        <div class="modal-footer mm-form-footer">
          <div class="mm-form-actions">
            <button class="btn mm-save" id="mmFormSave" type="button">Save medicine <kbd>Ctrl+S</kbd></button>
            <button class="btn mm-save-another" id="mmFormSaveAnother" type="button">Save and add another</button>
          </div>
          <button class="btn mm-cancel" data-bs-dismiss="modal" type="button">Cancel</button>
        </div>
      </div>
    </div>
  </div>

  <!-- View modal -->
  <div class="modal fade" id="mmViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Medicine Details</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="mmViewBody"></div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal" type="button">Close</button>
          <button class="btn btn-mf" id="mmViewEdit" type="button"><i class="bi bi-pencil me-1"></i>Edit</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Stock / Batches modal -->
  <div class="modal fade" id="mmStockModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="mmStockTitle">Stock Summary</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body p-0" id="mmStockBody"></div>
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
      const state = { search: '', category: '', mfg: '', stock: '', schedule: '', page: 1, per: 8 };
      let editingId = null;

      function buildLookups() {
        $('#mmCategory').innerHTML = '<option value="">All Categories</option>' + D.categories.map((c) => `<option>${c}</option>`).join('');
        $('#mmMfg').innerHTML = '<option value="">All Manufacturers</option>' + D.manufacturers.map((m) => `<option>${m}</option>`).join('');
        $('#fCategory').innerHTML = '<option value="">Select category</option>' + D.categories.map((c) => `<option>${c}</option>`).join('');
        $('#fMfg').innerHTML = '<option value="">Select manufacturer</option>' + D.manufacturers.map((m) => `<option>${m}</option>`).join('');
        const groups = [...new Set(D.medicines.flatMap((m) => String(m.genericGroup || '').split(/[,;]|\s+\+\s+/).map((s) => s.trim()).filter(Boolean)))].sort();
        $('#fGenericGroupList').innerHTML = groups.map((g) => `<option value="${MF.esc(g)}">`).join('');
      }

      function filtered() {
        const q = state.search.toLowerCase();
        return D.medicines.filter((m) => {
          const st = MF.stockOf(m.id);
          if (q && !(m.name + m.generic + (m.brandRef || '') + m.composition + m.manufacturer).toLowerCase().includes(q)) return false;
          if (state.category && m.category !== state.category) return false;
          if (state.mfg && m.manufacturer !== state.mfg) return false;
          if (state.schedule && m.schedule !== state.schedule) return false;
          const lowAt = Number(m.reorderLevel ?? m.minStock ?? 0);
          if (state.stock === 'low' && st > lowAt) return false;
          if (state.stock === 'in' && st <= lowAt) return false;
          if (state.stock === 'out' && st !== 0) return false;
          return true;
        });
      }

      function render() {
        const list = filtered();
        const pages = Math.max(1, Math.ceil(list.length / state.per));
        state.page = Math.min(state.page, pages);
        const slice = list.slice((state.page - 1) * state.per, state.page * state.per);
        $('#mmCount').textContent = `${list.length} of ${D.medicines.length} medicines · batch & expiry linked`;
        $('#mmBody').innerHTML = slice.map((m) => {
          const st = MF.stockOf(m.id);
          return `<tr>
            <td><div class="td-title">${MF.esc(m.name)}</div><div class="td-sub">${MF.esc(m.composition)}${(SCHEDULES[m.schedule] ? SCHEDULES[m.schedule].rx : m.rxRequired) ? ' · <span class="rx-chip" style="font-size:.6rem">Rx</span>' : ''}</div></td>
            <td>${MF.esc(m.generic)}</td>
            <td>${m.brandRef ? MF.esc(m.brandRef) : '<span class="text-2">—</span>'}</td>
            <td class="text-2">${m.category}</td>
            <td>${MF.esc(m.manufacturer)}</td>
            <td class="num text-2">${m.hsn}</td>
            <td class="text-center">${m.gst}%</td>
            <td>${m.unit}</td>
            <td class="text-end num">${MF.fmt(m.mrp, 2)}</td>
            <td class="text-end num">${MF.fmt(m.retailRate ?? m.mrp, 2)}</td>
            <td class="text-end num">${MF.fmt(m.wholesaleRate, 2)}</td>
            <td class="text-end num fw-semibold">${MF.num(st)}</td>
            <td>${MF.stockBadge(m)}</td>
            <td class="text-end mm-act">
              <div class="dropdown">
                <button type="button" class="btn btn-icon btn-light-mf mm-kebab" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-label="Actions"><i class="bi bi-three-dots-vertical"></i></button>
                <ul class="dropdown-menu dropdown-menu-end mm-act-menu">
                  <li><button type="button" class="dropdown-item" data-a="view" data-id="${m.id}"><i class="bi bi-eye"></i><span>View</span></button></li>
                  <li><button type="button" class="dropdown-item" data-a="edit" data-id="${m.id}"><i class="bi bi-pencil"></i><span>Edit</span></button></li>
                  <li><button type="button" class="dropdown-item" data-a="stock" data-id="${m.id}"><i class="bi bi-box-seam"></i><span>Stock</span></button></li>
                  <li><button type="button" class="dropdown-item" data-a="batches" data-id="${m.id}"><i class="bi bi-collection"></i><span>Batches</span></button></li>
                  <li><hr class="dropdown-divider"></li>
                  <li><button type="button" class="dropdown-item text-danger" data-a="del" data-id="${m.id}"><i class="bi bi-trash3"></i><span>Delete</span></button></li>
                </ul>
              </div>
            </td>
          </tr>`;
        }).join('') || `<tr><td colspan="14"><div class="empty-state"><i class="bi bi-search"></i>No medicines match the current filters.</div></td></tr>`;

        $('#mmPageInfo').textContent = `Showing ${slice.length ? (state.page - 1) * state.per + 1 : 0}–${(state.page - 1) * state.per + slice.length} of ${list.length}`;
        $('#mmPager').innerHTML = Array.from({ length: pages }, (_, i) =>
          `<li class="page-item ${i + 1 === state.page ? 'active' : ''}"><button class="page-link" data-pg="${i + 1}">${i + 1}</button></li>`).join('');
        $('#mmPager').querySelectorAll('[data-pg]').forEach((b) => b.addEventListener('click', () => { state.page = +b.dataset.pg; render(); }));

        $('#mmBody').querySelectorAll('[data-a]').forEach((b) => b.addEventListener('click', () => {
          const m = MF.med(b.dataset.id), a = b.dataset.a;
          if (a === 'view') openView(m);
          if (a === 'edit') openForm(m);
          if (a === 'stock') openStock(m, false);
          if (a === 'batches') openStock(m, true);
          if (a === 'del') removeMed(m);
        }));
      }

      let viewingMed = null;
      const mmTxt = (v) => (v == null || v === '') ? '—' : MF.esc(v);
      function mmPairs(rows) {
        return rows.map(([k, v]) => `<div class="mm-dl-item"><span>${k}</span><strong>${v == null || v === '' ? '—' : v}</strong></div>`).join('');
      }
      function openView(m) {
        viewingMed = m;
        const st = MF.stockOf(m.id);
        const spec = packSpec(m.form || formFromMed(m));
        const formName = m.form || formFromMed(m) || m.unit;
        const perLabel = spec ? (spec.pack === 'unit' ? 'Units' : 'Units per ' + spec.pack) : 'Units per strip';
        const piece = spec ? spec.piecePlural : 'units';
        const rxNeed = SCHEDULES[m.schedule] ? SCHEDULES[m.schedule].rx : !!m.rxRequired;
        const packRow = spec && spec.whole
          ? ['Pack', 'Sold as one complete ' + spec.pack]
          : [perLabel, m.packQty ? mmTxt(m.packQty) + ' ' + piece : '—'];
        $('#mmViewBody').innerHTML = `
          <div class="mm-detail-hero">
            <div class="mm-detail-icon"><i class="bi bi-capsule"></i></div>
            <div>
              <div class="mm-detail-name">${MF.esc(m.name)}${m.brandRef ? ` <span>· ${MF.esc(m.brandRef)}</span>` : ''}</div>
              <div class="mm-detail-sub">${mmTxt(m.composition)} · ${mmTxt(m.category)} · ${mmTxt(m.manufacturer)}</div>
            </div>
            <div class="mm-detail-badges">${MF.stockBadge(m)} ${rxNeed ? MF.badge('Schedule ' + m.schedule, 'purple') : MF.badge(m.schedule, 'secondary')} ${MF.badge(m.status || 'Active', m.status === 'Inactive' ? 'secondary' : 'success')}</div>
          </div>
          <div class="mm-detail-stats">
            <div class="mm-stat"><span>Stock</span><strong>${MF.num(st)}</strong><small>${mmTxt(m.unit)}</small></div>
            <div class="mm-stat"><span>MRP</span><strong>${MF.fmt(m.mrp, 2)}</strong></div>
            <div class="mm-stat"><span>Retail</span><strong>${MF.fmt(m.retailRate ?? m.mrp, 2)}</strong></div>
            <div class="mm-stat"><span>GST</span><strong>${mmTxt(m.gst)}%</strong></div>
          </div>
          <div class="mm-detail-cols">
            <section class="mm-detail-card">
              <div class="mm-pack-head"><i class="bi bi-card-text"></i> Identity</div>
              ${mmPairs([
                ['Generic', mmTxt(m.generic)],
                ['Brand', mmTxt(m.brandRef)],
                ['Manufacturer', mmTxt(m.manufacturer)],
                ['Category', mmTxt(m.category)],
                ['HSN', mmTxt(m.hsn)],
                ['Barcode', mmTxt(m.barcode)],
                ['Generic/composition group', mmTxt(m.genericGroup)],
              ])}
            </section>
            <section class="mm-detail-card">
              <div class="mm-pack-head"><i class="bi bi-box-seam"></i> Packaging</div>
              ${mmPairs([
                ['Form', mmTxt(formName)],
                ['Pack', mmTxt(spec ? spec.unit : m.unit)],
                packRow,
                ['Loose sale', spec && spec.whole ? 'No' : (m.allowLoose ? 'Allowed' : 'No')],
                ['Box', mmTxt([m.boxQty, m.boxUnit || 'Box'].filter((x) => x != null && x !== '').join(' '))],
                ['Schedule', mmTxt(m.schedule)],
                ['Rx required', rxNeed ? 'Yes' : 'No']
              ])}
            </section>
            <section class="mm-detail-card">
              <div class="mm-pack-head"><i class="bi bi-currency-rupee"></i> Pricing</div>
              ${mmPairs([
                ['MRP', MF.fmt(m.mrp, 2)],
                ['Purchase rate', MF.fmt(m.purchaseRate, 2)],
                ['Retail rate', MF.fmt(m.retailRate ?? m.mrp, 2)],
                ['Wholesale rate', MF.fmt(m.wholesaleRate, 2)],
                ['Default discount', m.defaultDiscount ? (m.discountType === 'rupee' ? MF.fmt(m.defaultDiscount, 2) : mmTxt(m.defaultDiscount) + '%') : '—'],
                ['GST', mmTxt(m.gst) + '%']
              ])}
            </section>
            <section class="mm-detail-card">
              <div class="mm-pack-head"><i class="bi bi-boxes"></i> Stock rules</div>
              ${mmPairs([
                ['Batch no', mmTxt(m.batchNo)],
                ['Quantity', m.openingQty != null && m.openingQty !== '' ? MF.num(m.openingQty) + ' ' + (spec ? spec.packPlural : 'units') : '—'],
                ['Current stock', MF.num(st) + ' ' + mmTxt(spec ? spec.packPlural : m.unit)],
                ['Minimum stock', mmTxt(m.minStock)],
                ['Reorder level', m.reorderLevel != null && m.reorderLevel !== '' ? MF.num(m.reorderLevel) + ' ' + (spec ? spec.packPlural : 'units') : '—'],
                ['Rack / shelf', mmTxt(m.rack)],
                ['Expiry alert', m.expiryAlertDays ? mmTxt(m.expiryAlertDays) + ' days' : '—'],
                ['Status', mmTxt(m.status || 'Active')]
              ])}
            </section>
          </div>`;
        new bootstrap.Modal($('#mmViewModal')).show();
      }

      function openForm(m) {
        editingId = m ? m.id : null;
        $('#mmFormTitle').textContent = m ? 'Edit Medicine — ' + m.name : 'Add Medicine';
        $('#fName').value = m?.name || ''; $('#fGeneric').value = m?.generic || ''; $('#fComp').value = m?.composition || ''; $('#fBrand').value = m?.brandRef || '';
        $('#fCategory').value = m?.category || ''; $('#fMfg').value = m?.manufacturer || '';
        setGroups(m?.genericGroup || (!m || m.substitutes == null ? '' : (Array.isArray(m.substitutes) ? m.substitutes.join(', ') : String(m.substitutes))));
        $('#fHsn').value = m?.hsn || ''; $('#fUnit').value = m ? formFromMed(m) : '';
        $('#fBarcode').value = m?.barcode || '';
        $('#fPackQty').value = m?.packQty ?? ''; $('#fAllowLoose').checked = !!m?.allowLoose;
        $('#fBatchNo').value = m?.batchNo || (m ? (MF.batchesOf(m.id)[0]?.batchNo || '') : '');
        $('#fStockQty').value = m?.openingQty ?? '';
        syncPackUnit();
        $('#fBoxQty').value = m?.boxQty ?? ''; $('#fBoxUnit').value = m?.boxUnit || 'Box';
        $('#fGst').value = m ? String(m.gst) : '12'; setSchedule(m?.schedule || 'OTC');
        $('#fMrp').value = m?.mrp ?? ''; $('#fPtr').value = m?.purchaseRate ?? ''; $('#fRetail').value = m?.retailRate ?? m?.mrp ?? '';
        $('#fWholesale').value = m?.wholesaleRate ?? ''; $('#fDisc').value = m?.defaultDiscount ?? 0; setDiscType(m?.discountType === 'rupee' ? 'rupee' : 'percent', false); $('#fMin').value = m?.minStock ?? 50; $('#fReorder').value = m?.reorderLevel ?? 5; $('#fRack').value = m?.rack || '';
        $('#fExpiryAlert').value = m?.expiryAlertDays ?? '';
        $('#fActive').checked = m ? m.status === 'Active' : true;
        $('#fNameWarn').style.display = 'none'; $('#fBarcodeWarn').style.display = 'none';
        checkPrice();
        new bootstrap.Modal($('#mmFormModal')).show();
      }

      const SCHEDULES = {
        OTC: { hint: 'OTC items can be sold without a prescription.', rx: false },
        H: { hint: 'Needs a prescription. Billing will require the doctor name.', rx: true },
        H1: { hint: 'Needs a prescription and goes into the H1 register. Billing will require doctor and patient details.', rx: true },
        X: { hint: 'Restricted drug. Sale needs a prescription and special records.', rx: true }
      };
      function setSchedule(code) {
        const key = SCHEDULES[code] ? code : 'OTC';
        $('#fSchedule').value = key;
        document.querySelectorAll('#fSchedGroup .mm-sched-opt').forEach((b) => b.classList.toggle('is-on', b.dataset.sched === key));
        $('#fSchedHint').textContent = SCHEDULES[key].hint;
        if ($('#fRx')) $('#fRx').checked = SCHEDULES[key].rx;
      }
      let groups = [];
      function renderGroups() {
        $('#fGroupList').innerHTML = groups.map((s, i) => `<span class="mm-chip">${MF.esc(s)}<button type="button" data-group="${i}" aria-label="Remove">&times;</button></span>`).join('');
        $('#fGenericGroup').value = groups.join(', ');
        $('#fGroupList').querySelectorAll('[data-group]').forEach((b) => b.addEventListener('click', () => {
          groups.splice(+b.dataset.group, 1);
          renderGroups();
        }));
      }
      function addGroup(raw) {
        String(raw || '').split(/[,;]|\s+\+\s+|\s*\/\s*/).map((s) => s.trim()).filter(Boolean).forEach((s) => {
          if (!groups.some((x) => x.toLowerCase() === s.toLowerCase())) groups.push(s);
        });
        renderGroups();
      }
      function setGroups(text) {
        groups = [];
        addGroup(text);
      }
      const FORM_PACK = {
        Tablet: { pack: 'strip', packPlural: 'strips', piecePlural: 'tablets', unit: 'Strip', sub: 'Tablet', loose: true, whole: false },
        Capsule: { pack: 'capsule', packPlural: 'capsules', piecePlural: 'capsules', unit: 'Capsule', sub: 'Capsule', loose: true, whole: false },
        Syrup: { pack: 'bottle', packPlural: 'bottles', piecePlural: 'ml', unit: 'Bottle', sub: 'ml', loose: false, whole: true },
        Drops: { pack: 'bottle', packPlural: 'bottles', piecePlural: 'drops', unit: 'Bottle', sub: 'Drop', loose: false, whole: true },
        Injection: { pack: 'vial', packPlural: 'vials', piecePlural: 'ml', unit: 'Vial', sub: 'ml', loose: false, whole: true },
        Ointment: { pack: 'tube', packPlural: 'tubes', piecePlural: 'g', unit: 'Tube', sub: 'g', loose: false, whole: true },
        Cream: { pack: 'tube', packPlural: 'tubes', piecePlural: 'g', unit: 'Tube', sub: 'g', loose: false, whole: true },
        Powder: { pack: 'sachet', packPlural: 'sachets', piecePlural: 'g', unit: 'Sachet', sub: 'g', loose: false, whole: true },
        Sachet: { pack: 'sachet', packPlural: 'sachets', piecePlural: 'sachets', unit: 'Sachet', sub: 'Sachet', loose: false, whole: true },
        Other: { pack: 'unit', packPlural: 'units', piecePlural: 'units', unit: 'Unit', sub: 'Unit', loose: false, whole: false }
      };
      function packSpec(form) { return FORM_PACK[form] || null; }
      function formFromMed(m) {
        if (!m) return '';
        if (m.form && FORM_PACK[m.form]) return m.form;
        if (FORM_PACK[m.unit]) return m.unit;
        const sub = String(m.subUnit || '').toLowerCase();
        if (sub === 'tablet' || sub === 'tab') return 'Tablet';
        if (sub === 'capsule' || sub === 'cap') return 'Capsule';
        const unit = String(m.unit || '').toLowerCase();
        if (unit === 'vial') return 'Injection';
        if (unit === 'tube') return 'Ointment';
        if (unit === 'sachet') return 'Sachet';
        if (unit === 'bottle') return 'Syrup';
        if (unit === 'strip') return 'Tablet';
        return '';
      }
      function syncPackUnit() {
        const spec = packSpec($('#fUnit') && $('#fUnit').value);
        const label = spec ? (spec.pack === 'unit' ? 'Units' : 'Units per ' + spec.pack) : 'Units per strip';
        if ($('#fPackQtyLabel')) $('#fPackQtyLabel').textContent = label;
        if ($('#fPackQtyUnit')) $('#fPackQtyUnit').textContent = spec ? spec.piecePlural : 'units';
        if ($('#fStockQtyUnit')) $('#fStockQtyUnit').textContent = spec ? spec.packPlural : 'units';
        if ($('#fReorderUnit')) $('#fReorderUnit').textContent = spec ? spec.packPlural : 'units';
        const whole = !!(spec && spec.whole);
        if ($('#fPackQtyWrap')) $('#fPackQtyWrap').hidden = whole;
        if ($('#fPackNoteWrap')) $('#fPackNoteWrap').hidden = !whole;
        if (whole) {
          if ($('#fPackNote')) $('#fPackNote').textContent = 'Sold as one complete ' + spec.pack + ' — no sub-units to enter.';
          if ($('#fPackQty')) $('#fPackQty').value = 1;
        }
        if ($('#fLooseWrap')) $('#fLooseWrap').hidden = !(spec && spec.loose);
        if ($('#fLooseHint') && spec && spec.loose) $('#fLooseHint').textContent = 'Sell single ' + spec.piecePlural;
        if ($('#fBoxHint')) {
          $('#fBoxHint').textContent = spec && spec.pack !== 'unit'
            ? spec.packPlural.charAt(0).toUpperCase() + spec.packPlural.slice(1) + ' per box'
            : 'Packs per box';
        }
        if (!(spec && spec.loose) && $('#fAllowLoose')) $('#fAllowLoose').checked = false;
        checkPrice();
      }
      function setDiscType(type, refresh) {
        const kind = type === 'rupee' ? 'rupee' : 'percent';
        if ($('#fDiscType')) $('#fDiscType').value = kind;
        document.querySelectorAll('#fDiscSwitch [data-disc]').forEach((b) => {
          const on = b.dataset.disc === kind;
          b.classList.toggle('is-on', on);
          b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        if (refresh !== false) checkPrice();
      }
      function sellingPrice() {
        const retail = parseFloat($('#fRetail').value);
        const mrp = parseFloat($('#fMrp').value);
        let sell = !isNaN(retail) && retail > 0 ? retail : mrp;
        const disc = parseFloat($('#fDisc') && $('#fDisc').value);
        const kind = $('#fDiscType') ? $('#fDiscType').value : 'percent';
        if (!isNaN(sell) && !isNaN(disc) && disc > 0) sell = kind === 'rupee' ? sell - disc : sell * (1 - disc / 100);
        if (!isNaN(sell) && sell < 0) sell = 0;
        return sell;
      }
      function checkPrice() {
        const buy = parseFloat($('#fPtr').value);
        const sell = sellingPrice();
        const warn = $('#fPriceWarn');
        if (warn) warn.hidden = !(!isNaN(buy) && buy > 0 && !isNaN(sell) && sell > 0 && buy > sell);
        const spec = packSpec($('#fUnit') && $('#fUnit').value);
        const pack = spec ? spec.pack : 'strip';
        const piece = spec ? String(spec.sub || 'unit').toLowerCase() : 'unit';
        const mrp = parseFloat($('#fMrp').value);
        const gst = parseFloat($('#fGst').value);
        const qty = parseFloat($('#fPackQty').value);
        const per = qty > 0 ? qty : 1;
        if ($('#fSellLabel')) $('#fSellLabel').textContent = 'Selling price / ' + pack;
        if ($('#fSellValue')) $('#fSellValue').textContent = isNaN(sell) ? '—' : MF.fmt(sell, 2);
        if ($('#fUnitPrice')) $('#fUnitPrice').textContent = isNaN(sell) ? '—' : MF.fmt(sell / per, 2) + ' / ' + piece;
        if ($('#fMargin')) $('#fMargin').textContent = isNaN(sell) || sell <= 0 || isNaN(buy) ? '—' : ((sell - buy) / sell * 100).toFixed(1) + '%';
        if ($('#fGstNote')) {
          if (isNaN(mrp) || mrp <= 0 || isNaN(gst)) $('#fGstNote').textContent = 'GST is included in MRP.';
          else $('#fGstNote').textContent = 'GST is included in MRP: ' + MF.fmt(mrp * gst / (100 + gst), 2) + ' per ' + pack + ' at ' + gst + '%.';
        }
      }

      /* Small edit-distance helper — used only to flag likely-duplicate medicine names as you type. */
      function levenshtein(a, b) {
        const m = a.length, n = b.length;
        const dp = Array.from({ length: m + 1 }, (_, i) => [i, ...Array(n).fill(0)]);
        for (let j = 0; j <= n; j++) dp[0][j] = j;
        for (let i = 1; i <= m; i++) {
          for (let j = 1; j <= n; j++) {
            dp[i][j] = a[i - 1] === b[j - 1] ? dp[i - 1][j - 1] : 1 + Math.min(dp[i - 1][j], dp[i][j - 1], dp[i - 1][j - 1]);
          }
        }
        return dp[m][n];
      }
      const normName = (s) => (s || '').toLowerCase().trim().replace(/\s+/g, ' ');
      let dupTimer;
      function checkNameDuplicate() {
        clearTimeout(dupTimer);
        dupTimer = setTimeout(() => {
          const val = normName($('#fName').value);
          const warn = $('#fNameWarn');
          if (!val) { warn.style.display = 'none'; return; }
          let exact = null, near = null, nearDist = Infinity;
          for (const m of D.medicines) {
            if (editingId && m.id === editingId) continue;
            const mn = normName(m.name);
            if (mn === val) { exact = m; break; }
            const dist = levenshtein(mn, val);
            const threshold = Math.max(2, Math.floor(val.length * 0.15));
            if (dist <= threshold && dist < nearDist) { near = m; nearDist = dist; }
          }
          if (exact) {
            warn.textContent = `⚠ A medicine named "${exact.name}" already exists (${exact.manufacturer || 'no manufacturer'}).`;
            warn.className = 'small mt-1 text-danger'; warn.style.display = '';
          } else if (near) {
            warn.textContent = `⚠ Similar medicine exists: "${near.name}" (${near.manufacturer || 'no manufacturer'}) — check it's not a duplicate.`;
            warn.className = 'small mt-1 text-warning'; warn.style.display = '';
          } else {
            warn.style.display = 'none';
          }
        }, 150);
      }
      function checkBarcodeConflict() {
        const val = $('#fBarcode').value.trim();
        const warn = $('#fBarcodeWarn');
        if (!val) { warn.style.display = 'none'; return; }
        const conflict = D.medicines.find((m) => m.barcode && m.barcode === val && (!editingId || m.id !== editingId));
        if (conflict) {
          warn.textContent = `⚠ This barcode is already used by "${conflict.name}".`;
          warn.className = 'small mt-1 text-danger'; warn.style.display = '';
        } else {
          warn.style.display = 'none';
        }
      }
      $('#fName').addEventListener('input', checkNameDuplicate);
      $('#fBarcode').addEventListener('input', checkBarcodeConflict);
      $('#fSchedGroup').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-sched]');
        if (!btn) return;
        setSchedule(btn.dataset.sched);
      });
      $('#fGroupInput').addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ',') {
          e.preventDefault();
          addGroup($('#fGroupInput').value);
          $('#fGroupInput').value = '';
        } else if (e.key === 'Backspace' && !$('#fGroupInput').value && groups.length) {
          groups.pop();
          renderGroups();
        }
      });
      $('#fGroupInput').addEventListener('blur', () => {
        if ($('#fGroupInput').value.trim()) {
          addGroup($('#fGroupInput').value);
          $('#fGroupInput').value = '';
        }
      });
      ['fPtr', 'fRetail', 'fMrp', 'fDisc', 'fPackQty'].forEach((id) => $('#' + id).addEventListener('input', checkPrice));
      $('#fGst').addEventListener('change', checkPrice);
      $('#fDiscSwitch').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-disc]');
        if (!btn) return;
        setDiscType(btn.dataset.disc);
      });
      $('#fUnit').addEventListener('change', syncPackUnit);

      let saving = false;
      async function saveMedicine(addAnother) {
        if (saving) return;
        if ($('#fGroupInput').value.trim()) { addGroup($('#fGroupInput').value); $('#fGroupInput').value = ''; }
        if (!$('#fName').value.trim()) { MF.toast('Medicine name is required.', 'err', 'Validation'); return; }
        if (!$('#fUnit').value) { MF.toast('Select a form.', 'err', 'Validation'); return; }
        if (!$('#fMrp').value) { MF.toast('MRP is required.', 'err', 'Validation'); return; }
        const busy = addAnother ? $('#mmFormSaveAnother') : $('#mmFormSave');
        saving = true;
        if (busy) busy.classList.add('is-busy');
        const spec = packSpec($('#fUnit').value);
        const payload = {
          name: $('#fName').value.trim(), generic: $('#fGeneric').value, brandRef: $('#fBrand').value.trim(), composition: $('#fComp').value,
          category: $('#fCategory').value, manufacturer: $('#fMfg').value, hsn: $('#fHsn').value,
          genericGroup: $('#fGenericGroup').value.trim(),
          substitutes: $('#fGenericGroup').value.trim(),
          barcode: $('#fBarcode').value.trim(),
          form: $('#fUnit').value, unit: spec.unit, packSize: '', gst: +$('#fGst').value, schedule: $('#fSchedule').value,
          packQty: spec.whole ? 1 : (+$('#fPackQty').value || 1), subUnit: spec.sub, allowLoose: spec.loose && $('#fAllowLoose').checked,
          batchNo: $('#fBatchNo').value.trim(),
          openingQty: $('#fStockQty').value === '' ? '' : +$('#fStockQty').value,
          boxQty: $('#fBoxQty').value, boxUnit: $('#fBoxUnit').value.trim() || 'Box',
          mrp: +$('#fMrp').value, retailRate: +$('#fRetail').value || +$('#fMrp').value, purchaseRate: +$('#fPtr').value || 0,
          defaultDiscount: $('#fDisc').value === '' ? 0 : +$('#fDisc').value, discountType: $('#fDiscType').value || 'percent',
          wholesaleRate: +$('#fWholesale').value || (+$('#fMrp').value * 0.9), minStock: +$('#fMin').value,
          reorderLevel: +$('#fReorder').value, rack: $('#fRack').value.trim(), rxRequired: !!(SCHEDULES[$('#fSchedule').value] && SCHEDULES[$('#fSchedule').value].rx), expiryAlertDays: $('#fExpiryAlert').value,
          status: $('#fActive').checked ? 'Active' : 'Inactive'
        };
        if (MF.Api.live) {
          try {
            if (editingId) await MF.Api.put('medicines.php', { id: editingId, ...payload });
            else await MF.Api.post('medicines.php', payload);
            await MF.rehydrate();
          } catch (e) {
            MF.toast(e.message, 'err', 'Save failed');
            return;
          } finally {
            saving = false;
            if (busy) busy.classList.remove('is-busy');
          }
        } else if (editingId) {
          Object.assign(MF.med(editingId), payload);
        } else {
          D.medicines.unshift({ id: 'M' + String(100 + D.medicines.length), brandRef: '', ...payload });
        }
        MF.toast(payload.name + (editingId ? ' updated successfully.' : ' added to the medicine master.'), 'success', editingId ? 'Medicine saved' : 'Medicine created');
        buildLookups();
        render();
        if (addAnother) openForm(null);
        else bootstrap.Modal.getInstance($('#mmFormModal')).hide();
        saving = false;
        if (busy) busy.classList.remove('is-busy');
      }
      $('#mmFormSave').addEventListener('click', () => saveMedicine(false));
      $('#mmFormSaveAnother').addEventListener('click', () => saveMedicine(true));
      document.addEventListener('keydown', (e) => {
        if (!(e.ctrlKey || e.metaKey) || e.key.toLowerCase() !== 's') return;
        if (!$('#mmFormModal').classList.contains('show')) return;
        e.preventDefault();
        saveMedicine(false);
      });

      function openStock(m, detailed) {
        const bs = MF.batchesOf(m.id);
        $('#mmStockTitle').textContent = (detailed ? 'Batches — ' : 'Stock Summary — ') + m.name;
        const total = MF.stockOf(m.id);
        const reserved = bs.reduce((s, b) => s + (Number(b.reserved) || 0), 0);
        const purchValue = bs.reduce((s, b) => s + b.qty * b.purchaseRate, 0);
        const mrpValue = bs.reduce((s, b) => s + b.qty * b.mrp, 0);
        const margin = mrpValue - purchValue;
        const note = total === 0
          ? `<div class="mm-stock-note out"><i class="bi bi-exclamation-circle"></i>Out of stock. Nothing can be billed until a batch is received.</div>`
          : total <= Number(m.reorderLevel ?? m.minStock ?? 0)
            ? `<div class="mm-stock-note low"><i class="bi bi-exclamation-triangle"></i>Low stock. At or below reorder level (${MF.num(m.reorderLevel)} ${MF.esc(m.unit || '')}).</div>`
            : '';
        const rows = bs.map((b) => {
          const days = MF.daysTo(b.expiry);
          const expTone = days < 0 ? 'bad' : days <= 90 ? 'warn' : 'ok';
          return `<tr>
            <td><span class="mm-batch"><i class="bi bi-upc"></i>${MF.esc(b.batchNo)}</span></td>
            <td class="num">${MF.fmtDate(b.purchaseDate)}</td>
            <td class="num mm-exp ${expTone}">${MF.fmtMonthYear(b.expiry)}</td>
            <td class="text-end num">${MF.num(b.qty)}</td>
            <td class="text-end num text-2">${MF.num(b.reserved)}</td>
            <td class="text-end num">${MF.fmt(b.purchaseRate, 2)}</td>
            <td class="text-end num">${MF.fmt(b.mrp, 2)}</td>
            <td>${MF.statusBadge(MF.batchStatus(b))}</td>
          </tr>`;
        }).join('');
        $('#mmStockBody').innerHTML = `
          <div class="mm-stock-top">
            <div class="mm-stock-kpis">
              <div class="mm-kpi accent"><span>Total qty</span><strong>${MF.num(total)}</strong><small>${MF.esc(m.unit || 'units')}${reserved ? ' · ' + MF.num(reserved) + ' reserved' : ''}</small></div>
              <div class="mm-kpi"><span>Purchase value</span><strong>${MF.fmt(purchValue)}</strong></div>
              <div class="mm-kpi"><span>MRP value</span><strong>${MF.fmt(mrpValue)}</strong><small>Margin ${MF.fmt(margin)}</small></div>
              <div class="mm-kpi"><span>Batches</span><strong>${MF.num(bs.length)}</strong><small>${detailed ? 'Full batch list' : 'FEFO order not changed'}</small></div>
            </div>
          </div>
          ${note}
          <div class="mm-stock-bar">
            <div class="text-2 small">${detailed ? 'Every batch on this medicine' : 'Sellable and held stock by batch'}</div>
            <button class="btn btn-mf-soft btn-sm" id="mmStockAdjust" type="button"><i class="bi bi-sliders me-1"></i>Stock Adjustment</button>
          </div>
          <table class="table table-mf mm-stock-table mb-0">
            <thead><tr><th>Batch No</th><th>Purchase Date</th><th>Expiry</th><th class="text-end">Qty</th><th class="text-end">Reserved</th><th class="text-end">Purchase Rate</th><th class="text-end">MRP</th><th>Status</th></tr></thead>
            <tbody>${rows || '<tr><td colspan="8"><div class="empty-state mm-stock-empty"><i class="bi bi-box-seam"></i>No batches recorded.</div></td></tr>'}</tbody>
          </table>`;
        new bootstrap.Modal($('#mmStockModal')).show();
        $('#mmStockBody').querySelector('#mmStockAdjust').addEventListener('click', () => {
          MF.toast('Opening adjustment slip for ' + m.name + ' (Stage 2 workflow)', 'info', 'Stock Adjustment');
        });
      }

      async function removeMed(m) {
        const ok = await MF.confirm({ title: `Delete ${m.name}?`, message: 'This removes the item from the master. Sales history is preserved (soft delete in production).', confirmText: 'Delete', tone: 'danger' });
        if (!ok) return;
        if (MF.Api.live) {
          try { await MF.Api.del('medicines.php?id=' + encodeURIComponent(m.id)); await MF.rehydrate(); }
          catch (e) { MF.toast(e.message, 'err', 'Delete failed'); return; }
        } else {
          D.medicines.splice(D.medicines.findIndex((x) => x.id === m.id), 1);
        }
        MF.toast(m.name + ' removed from master.', 'success', 'Deleted');
        render();
      }

      ['mmSearch', 'mmCategory', 'mmMfg', 'mmStock', 'mmSchedule'].forEach((id) =>
        $('#' + id).addEventListener('input', () => {
          state.search = $('#mmSearch').value; state.category = $('#mmCategory').value;
          state.mfg = $('#mmMfg').value; state.stock = $('#mmStock').value; state.schedule = $('#mmSchedule').value;
          state.page = 1; render();
        }));
      $('#mmClear').addEventListener('click', () => {
        ['mmSearch', 'mmCategory', 'mmMfg', 'mmStock', 'mmSchedule'].forEach((id) => $('#' + id).value = '');
        Object.assign(state, { search: '', category: '', mfg: '', stock: '', schedule: '', page: 1 });
        render();
      });
      $('#mmAddBtn').addEventListener('click', () => openForm(null));
      $('#mmViewEdit').addEventListener('click', () => {
        if (!viewingMed) return;
        const med = viewingMed;
        const el = $('#mmViewModal');
        el.addEventListener('hidden.bs.modal', () => openForm(med), { once: true });
        bootstrap.Modal.getInstance(el)?.hide();
      });
      $('#mmExport').addEventListener('click', () => MF.exportCSV('medicines.csv',
        ['Name', 'Generic', 'Brand', 'Category', 'Manufacturer', 'HSN', 'GST%', 'Unit', 'MRP', 'Purchase', 'Wholesale', 'Stock', 'Generic/composition group'],
        filtered().map((m) => [m.name, m.generic, m.brandRef || '', m.category, m.manufacturer, m.hsn, m.gst, m.unit, m.mrp, m.purchaseRate, m.wholesaleRate, MF.stockOf(m.id), m.genericGroup || ''])));

      /* Premium select menus. Native <option> popups ignore our CSS, so the list is ours
         and the closed field still uses the existing form-select / mm-input styles. */
      const selectMenu = document.createElement('div');
      selectMenu.className = 'mm-select-menu';
      selectMenu.setAttribute('role', 'listbox');
      document.body.appendChild(selectMenu);
      let openSelect = null;
      let hotIndex = -1;

      function selectAnchor(sel) {
        return sel.closest('.mm-input') || sel.closest('.mm-select-plain') || sel;
      }
      function closeSelectMenu() {
        selectMenu.classList.remove('show');
        selectMenu.innerHTML = '';
        document.querySelectorAll('.mm-input.is-open, .mm-select-plain.is-open').forEach((el) => el.classList.remove('is-open'));
        openSelect = null;
        hotIndex = -1;
      }
      function placeSelectMenu(sel) {
        const r = selectAnchor(sel).getBoundingClientRect();
        const width = Math.max(r.width, 180);
        selectMenu.style.width = width + 'px';
        selectMenu.style.left = Math.max(8, Math.min(r.left, window.innerWidth - width - 8)) + 'px';
        selectMenu.classList.add('show');
        const h = selectMenu.offsetHeight;
        const gap = 6;
        if (window.innerHeight - r.bottom < h + gap && r.top > h + gap) selectMenu.style.top = (r.top - h - gap) + 'px';
        else selectMenu.style.top = (r.bottom + gap) + 'px';
      }
      function paintSelectMenu(filter) {
        if (!openSelect) return;
        const q = (filter || '').trim().toLowerCase();
        const opts = [...openSelect.options].map((o, i) => ({ i, text: o.text, on: o.selected || o.value === openSelect.value }));
        const shown = opts.filter((o) => !q || o.text.toLowerCase().includes(q));
        const search = opts.length > 8
          ? `<div class="mm-select-search"><i class="bi bi-search"></i><input type="text" placeholder="Search" value="${MF.esc(filter || '')}" aria-label="Search options"></div>`
          : '';
        selectMenu.innerHTML = search + (shown.length
          ? shown.map((o, n) => `<button type="button" class="mm-select-opt${o.on ? ' is-on' : ''}${n === hotIndex ? ' is-hot' : ''}" role="option" data-i="${o.i}" aria-selected="${o.on}"><span>${MF.esc(o.text)}</span><i class="bi bi-check2"></i></button>`).join('')
          : `<div class="mm-select-empty">No match</div>`);
        const input = selectMenu.querySelector('input');
        if (input) {
          input.addEventListener('input', () => { hotIndex = 0; paintSelectMenu(input.value); });
          input.addEventListener('keydown', (e) => {
            if (!['ArrowDown', 'ArrowUp', 'Enter', 'Escape'].includes(e.key)) e.stopPropagation();
          });
          input.focus();
          input.setSelectionRange(input.value.length, input.value.length);
        }
        revealOption(selectMenu.querySelector('.is-on, .is-hot'));
      }
      function revealOption(el) {
        if (!el) return;
        const top = el.offsetTop;
        const bottom = top + el.offsetHeight;
        if (top < selectMenu.scrollTop) selectMenu.scrollTop = top;
        else if (bottom > selectMenu.scrollTop + selectMenu.clientHeight) selectMenu.scrollTop = bottom - selectMenu.clientHeight;
      }
      function openSelectMenu(sel) {
        if (openSelect === sel) { closeSelectMenu(); return; }
        closeSelectMenu();
        openSelect = sel;
        hotIndex = Math.max(0, [...sel.options].findIndex((o) => o.selected || o.value === sel.value));
        selectAnchor(sel).classList.add('is-open');
        paintSelectMenu('');
        placeSelectMenu(sel);
        selectMenu.querySelector('.is-on')?.focus();
      }
      function chooseSelect(index) {
        if (!openSelect || !openSelect.options[index]) return;
        openSelect.selectedIndex = index;
        openSelect.dispatchEvent(new Event('input', { bubbles: true }));
        openSelect.dispatchEvent(new Event('change', { bubbles: true }));
        closeSelectMenu();
      }
      function bindPremiumSelect(sel) {
        if (!sel || sel.dataset.mmSelect) return;
        sel.dataset.mmSelect = '1';
        const shell = sel.closest('.mm-input');
        if (shell) {
          shell.classList.add('mm-select');
          if (!shell.querySelector('.mm-select-caret')) {
            const caret = document.createElement('i');
            caret.className = 'bi bi-chevron-down mm-select-caret';
            shell.appendChild(caret);
          }
          const hit = document.createElement('button');
          hit.type = 'button';
          hit.className = 'mm-select-hit';
          hit.setAttribute('aria-label', sel.id || 'Choose');
          hit.setAttribute('aria-haspopup', 'listbox');
          shell.appendChild(hit);
          hit.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); openSelectMenu(sel); });
        } else {
          const wrap = document.createElement('div');
          wrap.className = 'mm-select-plain';
          sel.parentNode.insertBefore(wrap, sel);
          wrap.appendChild(sel);
          const hit = document.createElement('button');
          hit.type = 'button';
          hit.className = 'mm-select-hit';
          hit.setAttribute('aria-haspopup', 'listbox');
          wrap.appendChild(hit);
          hit.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); openSelectMenu(sel); });
        }
        sel.addEventListener('mousedown', (e) => e.preventDefault());
      }
      function bindPremiumSelects() {
        document.querySelectorAll('select.form-select').forEach(bindPremiumSelect);
      }
      selectMenu.addEventListener('click', (e) => {
        const btn = e.target.closest('.mm-select-opt');
        if (!btn) return;
        chooseSelect(+btn.dataset.i);
      });
      document.addEventListener('pointerdown', (e) => {
        if (!openSelect) return;
        if (selectMenu.contains(e.target)) return;
        if (selectAnchor(openSelect).contains(e.target)) return;
        closeSelectMenu();
      });
      document.addEventListener('keydown', (e) => {
        if (!openSelect) return;
        const buttons = [...selectMenu.querySelectorAll('.mm-select-opt')];
        if (e.key === 'Escape') { e.preventDefault(); closeSelectMenu(); return; }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
          e.preventDefault();
          if (!buttons.length) return;
          let cur = buttons.findIndex((b) => b.classList.contains('is-hot'));
          if (cur < 0) cur = buttons.findIndex((b) => b.classList.contains('is-on'));
          hotIndex = e.key === 'ArrowDown'
            ? Math.min(buttons.length - 1, (cur < 0 ? -1 : cur) + 1)
            : Math.max(0, (cur < 0 ? 0 : cur) - 1);
          buttons.forEach((b, n) => b.classList.toggle('is-hot', n === hotIndex));
          revealOption(buttons[hotIndex]);
        }
        if (e.key === 'Enter' && buttons.length) {
          e.preventDefault();
          const hot = buttons.find((b) => b.classList.contains('is-hot')) || buttons.find((b) => b.classList.contains('is-on')) || buttons[0];
          chooseSelect(+hot.dataset.i);
        }
      });
      function followSelectMenu(e) {
        if (!openSelect) return;
        if (e && (e.target === selectMenu || selectMenu.contains(e.target))) return;
        placeSelectMenu(openSelect);
      }
      window.addEventListener('resize', followSelectMenu);
      document.addEventListener('scroll', followSelectMenu, true);

      document.addEventListener('DOMContentLoaded', async () => {
        await MF.boot();
        buildLookups();
        bindPremiumSelects();
        const p = new URLSearchParams(location.search);
        if (p.get('stock') === 'low') { $('#mmStock').value = 'low'; state.stock = 'low'; }
        if (p.get('mfg')) { $('#mmMfg').value = p.get('mfg'); state.mfg = p.get('mfg'); }
        if (p.get('category')) { $('#mmCategory').value = p.get('category'); state.category = p.get('category'); }
        if (p.get('schedule')) { $('#mmSchedule').value = p.get('schedule'); state.schedule = p.get('schedule'); }
        render();
        if (p.get('action') === 'add') openForm(null);
      });
    })();
  </script>
<script>(function(){function c(){var b=a.contentDocument||(a.contentWindow&&a.contentWindow.document);if(b){var d=b.createElement('script');d.innerHTML="window.__CF$cv$params={r:'a42cac3b7995444f',t:'MTc5MDcwMjU3NQ=='};var a=document.createElement('script');a.src='/cdn-cgi/challenge-platform/scripts/jsd/main.js';document.getElementsByTagName('head')[0].appendChild(a);";b.getElementsByTagName('head')[0].appendChild(d)}}if(document.body){var a=document.createElement('iframe');a.height=1;a.width=1;a.style.position='absolute';a.style.top=0;a.style.left=0;a.style.border='none';a.style.visibility='hidden';document.body.appendChild(a);if('loading'!==document.readyState)c();else if(window.addEventListener)document.addEventListener('DOMContentLoaded',c);else{var e=document.onreadystatechange||function(){};document.onreadystatechange=function(b){e(b);'loading'!==document.readyState&&(document.onreadystatechange=e,c())}}}})();</script></body>
</html>
