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
      background:transparent;
      border:0;
      border-radius:0;
      padding:0;
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
      background:#fff; border:1px solid #E5E7EB; border-radius:8px;
      padding:0 10px; min-height:38px;
      transition:border-color .15s ease, box-shadow .15s ease;
    }
    .mm-input:focus-within { border-color:#2E8B78; box-shadow:0 0 0 3px rgba(46,139,120,.12); }
    .mm-input > i { color:#8aa0b8; font-size:1rem; flex:0 0 auto; }
    .mm-input .form-control,
    .mm-input .form-select {
      border:0 !important; background:transparent !important; box-shadow:none !important;
      border-radius:0 !important; outline:0; padding-left:0; height:36px; min-height:36px; font-size:.8125rem;
    }
    .mm-input .form-control:focus,
    .mm-input .form-select:focus { box-shadow:none !important; background:transparent !important; border-color:transparent !important; }
    .mm-input > i { border:0; background:transparent; box-shadow:none; }
    .mm-barcode-row { display:flex; align-items:stretch; gap:8px; }
    .mm-barcode-row .mm-input { flex:1; min-width:0; }
    .mm-gen {
      border:1px solid #176B5B; background:#fff; color:#176B5B; border-radius:10px;
      font-weight:650; padding:0 14px; white-space:nowrap;
    }
    .mm-gen:hover { background:#E6F1EE; color:#0F4D42; }
    .mm-text-btn {
      border:0; background:transparent; color:#176B5B; font:inherit; font-weight:650;
      padding:0; text-decoration:underline;
    }
    .mm-input .form-select {
      appearance:none; -webkit-appearance:none; -moz-appearance:none;
      background-image:none !important; cursor:pointer; padding-right:.4rem;
    }
    .mm-input { position:relative; }
    .mm-select-caret {
      color:#8aa0b8; font-size:.72rem; pointer-events:none; margin-left:auto;
      transition:transform .15s ease, color .15s ease;
    }
    .mm-input.is-open { border-color:#2E8B78; box-shadow:0 0 0 3px rgba(46,139,120,.12); }
    .mm-input.is-open .mm-select-caret { transform:rotate(180deg); color:#2E8B78; }
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
      border:1px solid #E5E7EB; border-radius:8px; background:#fff; padding:6px 12px; min-height:38px;
    }
    .mm-chip-list { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
    .mm-chips:focus-within { border-color:#2E8B78; box-shadow:0 0 0 3px rgba(46,139,120,.12); }
    .mm-chip {
      display:inline-flex; align-items:center; gap:6px; background:#E6F1EE; color:#0F4D42;
      border-radius:999px; padding:4px 10px; font-size:.78rem; font-weight:700;
    }
    .mm-chip button { border:0; background:transparent; color:inherit; line-height:1; padding:0 2px; font-size:1rem; }
    .mm-chip-input { border:0; outline:0; flex:1; min-width:160px; background:transparent; font-size:.9rem; padding:0; }
    .mm-price-warn {
      display:flex; align-items:center; gap:8px; color:#B45309; background:#FEF3E2;
      border:1px solid #f6d7a2; border-radius:10px; padding:8px 12px; font-size:.82rem; font-weight:650;
    }
    .mm-per {
      display:flex; align-items:center; gap:8px; width:100%;
      background:#fff; border:1px solid #E5E7EB; border-radius:8px; min-height:38px; padding:0 12px;
    }
    .mm-per:focus-within { border-color:#2E8B78; box-shadow:0 0 0 3px rgba(46,139,120,.12); }
    .mm-per input {
      border:0; outline:0; background:transparent; flex:1 1 auto; width:1%; min-width:0; min-height:0; padding:0;
      font-size:.8125rem; color:#172026;
    }
    .mm-per-unit { flex:0 0 auto; color:#8b9bb0; font-size:.95rem; white-space:nowrap; }
    .mm-align > [class*="col-"] { display:flex; flex-direction:column; }
    .mm-align .form-label { min-height:18px; margin-bottom:6px; line-height:1.2; }
    .mm-align .form-control {
      min-height:38px; border-radius:8px; border-color:#E5E7EB;
    }
    .mm-align .form-control:focus { border-color:#2E8B78; box-shadow:0 0 0 3px rgba(46,139,120,.12); }
    /* Design-system reskin — every standard field in these dialogs: 13px, 8px radius, system border + accent focus */
    #mmFormModal .form-control, #mmFormModal .form-select,
    #mmUnitModal .form-control, #mmUnitModal .form-select {
      font-size:.8125rem; border-color:#E5E7EB; border-radius:8px; color:#172026;
    }
    #mmFormModal .form-control:focus, #mmFormModal .form-select:focus,
    #mmUnitModal .form-control:focus, #mmUnitModal .form-select:focus {
      border-color:#2E8B78; box-shadow:0 0 0 3px rgba(46,139,120,.12);
    }
    .mm-field-hint { color:#8b9bb0; font-size:.78rem; margin-top:6px; line-height:1.4; }
    .mm-disc-switch { display:flex; border:1px solid #E5E7EB; border-radius:8px; overflow:hidden; flex:0 0 auto; }
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

    /* Pricing & Tax — five equal columns, identical control height, icons in labels */
    .mm-price-row { display:grid; grid-template-columns:repeat(5, minmax(150px, 1fr)); gap:12px; }
    .mm-price-row .form-label { display:flex; align-items:center; gap:5px; min-height:20px; white-space:nowrap; margin-bottom:.4rem; }
    .mm-price-row .form-label i { color:#0d9488; font-size:.85rem; }
    .mm-price-row .form-label .req { margin-left:2px; }
    .mm-price-row .form-control, .mm-price-row .form-select { height:38px; line-height:normal; padding-top:0; padding-bottom:0; }
    @media (max-width: 991.98px) { .mm-price-row { grid-template-columns:repeat(3, minmax(150px, 1fr)); } }
    @media (max-width: 575.98px) { .mm-price-row { grid-template-columns:repeat(2, minmax(130px, 1fr)); } }

    /* Stock & Status — five equal columns, uniform 38px controls (same as the basic-details inputs) */
    .mm-stock-grid { display:grid; grid-template-columns:repeat(5, minmax(140px, 1fr)); gap:14px 12px; }
    .mm-stock-grid .form-label { display:flex; align-items:center; gap:5px; min-height:20px; white-space:nowrap; margin-bottom:.4rem; }
    .mm-stock-grid .form-label i { color:#0d9488; font-size:.85rem; }
    .mm-stock-grid .form-control { height:38px; line-height:normal; padding-top:0; padding-bottom:0; }
    .mm-stock-grid .mm-per { height:38px; min-height:38px; }
    .mm-stock-grid .mm-per input { height:100%; min-height:0; }
    .mm-stock-grid .mm-field-hint { font-size:.74rem; color:#8496a8; margin-top:4px; }
    /* Status toggle — its own FULL-WIDTH row (row 3), switch pill left, text right on one line */
    .mm-active-cell { grid-column: 1 / -1; }
    .mm-active-cell .mm-switch { width:100%; min-height:38px; display:flex; align-items:center; }
    .mm-active-cell .mm-switch > span { display:flex; align-items:baseline; gap:8px; }
    .mm-active-cell .mm-switch small { display:inline; }
    .mm-autogen { background:none; border:0; padding:2px 0 0; font-size:.74rem; font-weight:600; color:#0d9488;
                  display:inline-flex; align-items:center; gap:4px; }
    .mm-autogen:hover { color:#0F766E; text-decoration:underline; }

    /* Retail > MRP — inline red hint + pink-flagged input */
    .mm-mrp-warn { color:#B42318 !important; font-weight:600; }
    .mm-price-row .form-control.is-mrp-over { background:#FDECEC !important; border-color:#F2A5A5 !important; color:#B42318; }

    /* Stock info moves to the form footer while editing — small text with a muted square badge */
    .mm-stock-foot { width:100%; display:flex; align-items:center; gap:8px; font-size:.76rem; color:#64748B; margin-bottom:6px; }
    .mm-stock-foot-badge { width:22px; height:22px; border-radius:7px; background:#EEF4F3; color:#0d9488;
                           display:inline-flex; align-items:center; justify-content:center; font-size:.75rem; flex:0 0 auto; }
    .mm-stock-foot[hidden] { display:none; }
    .mm-form-footer { flex-wrap:wrap; }

    /* Bulk Add modal */
    .mm-bulk-hint { font-size:.8rem; color:#476178; background:#f0fbf8; border:1px solid #d3eee6; border-radius:9px; padding:10px 12px; margin-bottom:10px; line-height:1.55; }
    .mm-bulk-hint code { background:#eaf3f1; border-radius:4px; padding:0 4px; }
    .mm-bulk-csv { width:100%; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:.76rem; line-height:1.5;
                   border:1px solid #dfe6ef; border-radius:9px; padding:8px 10px; resize:vertical; }
    .mm-bulk-csv:focus { border-color:#0d9488; outline:none; box-shadow:0 0 0 3px rgba(13,148,136,.12); }
    .mm-bulk-preview { margin-top:10px; max-height:320px; overflow:auto; border:1px solid #e3e9f1; border-radius:9px; }
    .mm-bulk-preview table { margin:0; font-size:.74rem; white-space:nowrap; }
    .mm-bulk-preview thead th { position:sticky; top:0; background:#f6f8fb; z-index:1; }
    .mm-blk-summary { font-size:.78rem; font-weight:700; margin-top:10px; }
    .mm-blk-ok { color:#157347; font-weight:700; }
    .mm-blk-err { color:#B42318; font-weight:600; }
    .mm-bulk-progress { display:flex; align-items:center; gap:8px; flex:1 1 180px; min-width:180px; }
    .mm-bulk-bar { flex:1; height:8px; background:#e7edf4; border-radius:99px; overflow:hidden; }
    .mm-bulk-bar span { display:block; height:100%; width:0; background:#176B5B; border-radius:99px; transition:width .2s ease; }
    .mm-active-cell .mm-switch { min-height:40px; display:flex; align-items:center; margin-bottom:0; }
    @media (max-width: 991.98px) { .mm-stock-grid { grid-template-columns:repeat(2, minmax(140px, 1fr)); } }
    @media (max-width: 575.98px) { .mm-stock-grid { grid-template-columns:repeat(2, minmax(130px, 1fr)); } }
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
    .mm-unit-add {
      flex:0 0 38px; width:38px; border-radius:8px; border:1px dashed #9CC6BB; background:#E6F1EE;
      color:#176B5B; font-size:1.05rem; display:inline-flex; align-items:center; justify-content:center;
    }
    .mm-unit-add:hover, .mm-unit-add:focus { background:#D8EAE5; border-color:#176B5B; color:#0F4D42; }

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
    .mm-select-opt:hover { background:#f4f7fb; }
    /* Type-ahead highlight — amber, so a letter-jump reads distinctly from hover (grey) and current choice (teal) */
    .mm-select-opt.is-hot { background:#FFF4D6; box-shadow:inset 3px 0 0 #F59E0B; color:#92400E; }
    .mm-select-opt.is-hot i { color:#D97706; }
    .mm-select-opt.is-hot.is-on { background:#E6F1EE; box-shadow:inset 3px 0 0 #F59E0B; }
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
    .mm-switch-compact { height:38px; padding:6px 10px; }
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
    .mm-cat {
      display:inline-block; max-width:9.5rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; vertical-align:bottom;
    }
    .mm-sch {
      display:inline-flex; align-items:center; margin-left:4px; padding:1px 6px; border-radius:999px;
      color:#fff; font-size:.62rem; font-weight:700; letter-spacing:.02em; line-height:1.4;
    }
    .mm-sch.otc { background:#176B5B; }
    .mm-sch.h { background:#0369A1; }
    .mm-sch.h1 { background:#6D28D9; }
    .mm-sch.x { background:#B42318; }
    .mm-exp-chip {
      display:inline-flex; align-items:center; margin-left:6px; padding:1px 7px; border-radius:999px;
      font-size:.68rem; font-weight:700; line-height:1.4; white-space:nowrap;
    }
    .mm-exp-chip.red { background:#fdeeee; color:#c62828; }
    .mm-exp-chip.amber { background:#fff6e4; color:#b45309; }
    .mm-new {
      display:inline-flex; align-items:center; margin-left:6px; padding:1px 7px; border-radius:999px;
      background:#f3f4f6; color:#6b7280; font-size:.68rem; font-weight:650; line-height:1.4;
    }
    /* colorful matte square badges — Batch column (same palette as Medicine Ledger) */
    .mm-bdg {
      display:inline-flex; align-items:center; gap:5px; padding:3px 8px; border-radius:4px;
      font-size:11.5px; font-weight:700; letter-spacing:.02em; line-height:1.45;
      border:1px solid; white-space:nowrap;
    }
    .mm-bdg i { font-size:11px; }
    .mm-bdg.teal   { background:#D9F0ED; color:#0F766E; border-color:#B5E1DC; }
    .mm-bdg.blue   { background:#DDEBFA; color:#1D5FA8; border-color:#BED9F3; }
    .mm-bdg.amber  { background:#F9EDD3; color:#9A6206; border-color:#F0DCAC; }
    .mm-bdg.orange { background:#FBE7D9; color:#B4451C; border-color:#F4CFB6; }
    .mm-bdg.purple { background:#EBE4F9; color:#6D3FC0; border-color:#D8C9F1; }
    .mm-bdg.red    { background:#F9E0E0; color:#B4352F; border-color:#F2C2C2; }
    .mm-bdg.green  { background:#DCF0E2; color:#1E7A44; border-color:#BEE2CA; }
    .mm-bdg.pink   { background:#F8E0EC; color:#B03070; border-color:#EFBFD8; }
    .mm-bdg.indigo { background:#E2E6FA; color:#4349B3; border-color:#C8CFF2; }
    .mm-bdg.cyan   { background:#DCF0F6; color:#14708E; border-color:#BDE0EC; }
    .mm-bdg.slate  { background:#E9EDF1; color:#4B5563; border-color:#D5DCE3; }
    #mmStockModal .modal-dialog { max-width:880px; }
    .mm-stock-kpis { grid-template-columns:repeat(4, minmax(0, 1fr)); }
    .mm-kpi { position:relative; overflow:hidden; }
    .mm-kpi i.mm-kpi-ico { position:absolute; right:10px; top:10px; font-size:1rem; opacity:.55; }
    .mm-kpi.on { background:#E6F1EE; border-color:#cfe4de; }
    .mm-kpi.on strong { color:#176B5B; }
    .mm-kpi.free { background:#eef8f1; border-color:#d4eadb; }
    .mm-kpi.free strong { color:#157347; }
    .mm-kpi.hold { background:#fff6e4; border-color:#f3e0b8; }
    .mm-kpi.hold strong { color:#8a5a00; }
    .mm-kpi.value { background:#f3f6fb; border-color:#dce5f1; }
    .mm-kpi.value strong { color:#1d4f91; }
    .mm-stock-meta { padding:0 16px 12px; color:#6c757d; font-size:.8rem; font-weight:600; }
    .mm-rate { white-space:nowrap; }
    .mm-cdot { color:#9aa8b8; padding:0 4px; }
    .mm-activity { margin:0 16px 16px; border:1px solid #e7edf4; border-radius:12px; background:#fff; }
    .mm-activity summary {
      list-style:none; cursor:pointer; padding:10px 12px; font-size:.84rem; font-weight:700; color:#1b2430;
    }
    .mm-activity summary::-webkit-details-marker { display:none; }
    .mm-activity summary .caret { display:inline-block; width:1rem; color:#6c757d; }
    .mm-activity summary span { color:#6c757d; font-weight:600; }
    .mm-activity:not([open]) summary .caret { transform:rotate(-90deg); }
    .mm-act-row {
      display:flex; align-items:center; gap:10px; padding:8px 12px; border-top:1px solid #f1f4f8; font-size:.82rem;
    }
    .mm-act-kind {
      flex:0 0 auto; min-width:5.6rem; border-radius:999px; padding:2px 8px; font-size:.68rem; font-weight:700; text-align:center;
    }
    .mm-act-kind.sale { background:#fdeeee; color:#c62828; }
    .mm-act-kind.purchase { background:#E6F1EE; color:#176B5B; }
    .mm-act-kind.adjustment { background:#fff6e4; color:#8a5a00; }
    .mm-act-row .grow { flex:1; min-width:0; }
    .mm-act-row .qty { font-weight:750; font-variant-numeric:tabular-nums; }
    .mm-act-row .qty.down { color:#c62828; }
    .mm-act-row .qty.up { color:#157347; }
    #mmBatchModal .modal-dialog { max-width:980px; }
    #mmAdjustModal .modal-dialog { max-width:560px; }
    .mm-modal-hero { display:flex; align-items:flex-start; gap:12px; padding:16px 16px 0; }
    .mm-modal-hero .ico {
      width:42px; height:42px; border-radius:12px; display:grid; place-items:center; flex:0 0 auto; font-size:1.15rem;
    }
    .mm-modal-hero.stock .ico { background:#E6F1EE; color:#176B5B; }
    .mm-modal-hero.batch .ico { background:#eef3fb; color:#1d4f91; }
    .mm-modal-hero strong { display:block; font-size:1.02rem; letter-spacing:-.02em; }
    .mm-modal-hero span { display:block; color:#6c757d; font-size:.8rem; margin-top:2px; }
    .mm-sit { margin:0 16px 16px; border:1px solid #e7edf4; border-radius:12px; overflow:hidden; }
    .mm-sit-row {
      display:flex; align-items:center; justify-content:space-between; gap:12px;
      padding:10px 12px; border-top:1px solid #f1f4f8; font-size:.86rem;
    }
    .mm-sit-row:first-child { border-top:0; }
    .mm-facts { display:grid; grid-template-columns:1fr 1fr; gap:10px; padding:0 16px 14px; }
    .mm-fact { background:#f8fafc; border:1px solid #e7edf4; border-radius:12px; padding:10px 12px; }
    .mm-fact span { display:block; color:#6c757d; font-size:.72rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
    .mm-fact strong { display:block; margin-top:3px; font-size:.95rem; }
    .mm-batch-chips { display:flex; flex-wrap:wrap; gap:8px; padding:0 16px 14px; }
    .mm-batch-chip {
      display:inline-flex; align-items:center; gap:6px; border-radius:999px; padding:4px 10px;
      background:#eef3fb; color:#1d4f91; font-size:.78rem; font-weight:700;
    }
    .mm-batch-chip.bad { background:#fdeeee; color:#c62828; }
    .mm-batch-chip.warn { background:#fff6e4; color:#a86400; }
    .mm-adj-switch { display:flex; border:1px solid #e3e9f1; border-radius:10px; overflow:hidden; }
    .mm-adj-switch button { flex:1; border:0; background:#fff; color:#6c757d; min-height:40px; font-weight:700; }
    .mm-adj-switch button.is-on.add { background:#176B5B; color:#fff; }
    .mm-adj-switch button.is-on.remove { background:#c62828; color:#fff; }
    .mm-adj-preview { margin-top:8px; font-size:.82rem; font-weight:650; color:#176B5B; }
    .mm-adj-preview.remove { color:#c62828; }
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
            <button class="btn btn-light-mf" id="mmBulkAdd"><i class="bi bi-file-earmark-arrow-up me-1"></i>Bulk Add</button>
            <button class="btn btn-light-mf" id="mmPriceBulkBtn"><i class="bi bi-tag me-1"></i>Price update</button>
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
                <option value="expiring">Expiring soon</option>
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
                  <th>Medicine Name</th><th>Brand</th><th>Category</th><th>MFR</th><th>Batch</th><th>Unit</th>
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

  <!-- Bulk price update -->
  <div class="modal fade" id="mmPriceBulkModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:560px">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-tag me-2"></i>Bulk price update</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mm-hint mb-2">Change MRP / retail / wholesale rates by one percentage for a whole category or manufacturer — server-side, in one write.</div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label" for="pbCat">Category</label><select class="form-select" id="pbCat"><option value="">All categories</option></select></div>
            <div class="col-6"><label class="form-label" for="pbMfr">Manufacturer</label><select class="form-select" id="pbMfr"><option value="">All manufacturers</option></select></div>
          </div>
          <div class="row g-2 mb-2 align-items-end">
            <div class="col-4">
              <label class="form-label" for="pbAction">Action</label>
              <select class="form-select" id="pbAction">
                <option value="adjust">Adjust by %</option>
                <option value="derive">Wholesale = MRP × %</option>
              </select>
            </div>
            <div class="col-5">
              <label class="form-label" for="pbPct" id="pbPctLabel">Change (%)</label>
              <div class="mm-input"><i class="bi bi-percent"></i><input type="number" step="0.5" class="form-control" id="pbPct" placeholder="+5 or -5"></div>
              <div class="d-flex gap-1 mt-1" id="pbQuick">
                <button type="button" class="mm-text-btn pb-quick" data-p="-5">−5%</button>
                <button type="button" class="mm-text-btn pb-quick" data-p="-2">−2%</button>
                <button type="button" class="mm-text-btn pb-quick" data-p="5">+5%</button>
                <button type="button" class="mm-text-btn pb-quick" data-p="10">+10%</button>
              </div>
            </div>
            <div class="col-4">
              <label class="form-label" for="pbRound">Round to</label>
              <select class="form-select" id="pbRound"><option value="none">2 decimals</option><option value="0.5">Nearest 0.50</option><option value="1">Nearest whole ₹</option></select>
            </div>
            <div class="col-3"><button type="button" class="btn btn-light-mf w-100" id="pbPreviewBtn">Preview</button></div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-12 d-flex gap-3">
              <label class="form-check" style="font-size:.8rem"><input class="form-check-input" type="checkbox" id="pbMrp" checked><span class="form-check-label">MRP</span></label>
              <label class="form-check" style="font-size:.8rem"><input class="form-check-input" type="checkbox" id="pbRetail" checked><span class="form-check-label">Retail rate</span></label>
              <label class="form-check" style="font-size:.8rem"><input class="form-check-input" type="checkbox" id="pbWhole"><span class="form-check-label">Wholesale rate</span></label>
            </div>
          </div>
          <div class="mm-pack-note" id="pbPreview" hidden></div>
          <div class="alert alert-warning py-2 mt-2 mb-0" role="note" style="font-size:.74rem;border-radius:10px">
            <i class="bi bi-exclamation-triangle me-1"></i>This rewrites prices on the server for every matched medicine. "—"? prices are left untouched only if the field is skipped above.
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-mf" id="pbApplyBtn" disabled><i class="bi bi-check2 me-1"></i>Apply update</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bulk add medicines -->
  <div class="modal fade" id="mmBulkModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-file-earmark-arrow-up me-2"></i>Bulk Add Medicines</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mm-bulk-hint">
            One row per medicine. Each row carries its <strong>opening batch no + quantity + expiry</strong>, so many medicines with many batches land in one go.
            Fill the template in Excel / Google Sheets / Notepad, save as CSV, then upload or paste below.
            Required columns: <code>name</code>, <code>form</code>, <code>mrp</code> — everything else is optional.
            Form accepts: Tablet, Capsule, Syrup, Drops, Injection, Ointment, Cream, Powder, Sachet, Other.
            Schedule accepts: OTC, H, H1, X. Expiry format: MM-YYYY (e.g. 04-2028).
          </div>
          <div class="d-flex flex-wrap gap-2 mb-2">
            <button class="btn btn-sm btn-light-mf" id="mmBulkTpl" type="button"><i class="bi bi-download me-1"></i>Download CSV template</button>
            <label class="btn btn-sm btn-light-mf mb-0" for="mmBulkFile"><i class="bi bi-upload me-1"></i>Upload CSV file</label>
            <input type="file" id="mmBulkFile" accept=".csv,text/csv" hidden>
            <button class="btn btn-sm btn-light-mf" id="mmBulkClear" type="button"><i class="bi bi-eraser me-1"></i>Clear</button>
          </div>
          <textarea id="mmBulkCsv" class="mm-bulk-csv" rows="6" placeholder="Paste CSV here…" spellcheck="false"></textarea>
          <div id="mmBulkSummary"></div>
          <div id="mmBulkPreviewBox"></div>
        </div>
        <div class="modal-footer d-flex align-items-center gap-2 flex-wrap">
          <div class="mm-bulk-progress" id="mmBulkProgress" hidden>
            <div class="mm-bulk-bar"><span id="mmBulkBarFill"></span></div>
            <span class="num" id="mmBulkProgressTxt">0 / 0</span>
          </div>
          <button class="btn btn-light-mf ms-auto" data-bs-dismiss="modal" type="button">Close</button>
          <button class="btn btn-light-mf" id="mmBulkValidate" type="button"><i class="bi bi-check2-square me-1"></i>Check rows</button>
          <button class="btn btn-mf" id="mmBulkImport" type="button" disabled><i class="bi bi-cloud-upload me-1"></i>Import rows</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Add / Edit modal -->
  <div class="modal fade" id="mmFormModal" tabindex="-1" data-bs-focus="false">
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
                <input class="mm-chip-input" id="fGroupInput" list="fGenericGroupList" placeholder="Type the salt + strength, e.g. Azithromycin 500mg — press Enter" autocomplete="off">
              </div>
              <input type="hidden" id="fGenericGroup">
              <datalist id="fGenericGroupList"></datalist>
              <div class="mm-hint">This drives substitutes: enter the <strong>salt + strength</strong> (e.g. <em>Azithromycin 500mg</em>) — not the category. Every medicine sharing this chip becomes a substitute automatically.</div>
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
                <div class="col-md-8 col-12">
                  <label class="form-label" for="fBarcode">Barcode</label>
                  <div class="mm-barcode-row">
                    <div class="mm-input">
                      <i class="bi bi-upc-scan"></i>
                      <input class="form-control" id="fBarcode" placeholder="8901234…">
                    </div>
                    <button type="button" class="mm-gen" id="fBarcodeGen">Generate</button>
                  </div>
                  <div class="mm-hint">No barcode on the pack? Generate an internal one and <button type="button" class="mm-text-btn" id="fBarcodePrint">print a label</button>.</div>
                  <div id="fBarcodeWarn" class="small mt-1" style="display:none"></div>
                </div>
                <div class="col-md-4 col-12">
                  <label class="form-label" for="fHsn">HSN Code</label>
                  <div class="mm-input">
                    <i class="bi bi-hash"></i>
                    <input class="form-control" id="fHsn" placeholder="e.g. 3004">
                  </div>
                </div>
              </div>
            </div>

            <div class="mm-pack-group">
              <div class="mm-pack-head"><i class="bi bi-capsule"></i> Pack contents</div>
              <div class="row g-3 align-items-start mm-align">
                <div class="col-lg-4 col-md-6 col-12">
                  <label class="form-label" for="fUnit">Form <span class="req">*</span></label>
                  <div class="d-flex gap-2 align-items-stretch">
                    <div class="mm-input flex-grow-1">
                    <i class="bi bi-tag"></i>
                    <select class="form-select" id="fUnit" data-no-search>
                      <option value="" selected>Select form</option>
                      <option>Tablet</option>
                      <option>Capsule</option>
                      <option>Syrup</option>
                      <option>Drops</option>
                      <option>Injection</option>
                      <option>Ointment</option>
                      <option>Gel</option>
                      <option>Cream</option>
                      <option>Powder</option>
                      <option>Sachet</option>
                      <option>Other</option>
                    </select>
                    </div>
                    <button type="button" class="mm-unit-add" id="fUnitAdd" title="Add a custom form name" aria-label="Add a custom form name"><i class="bi bi-plus-lg"></i></button>
                  </div>
                </div>
                <div class="col-lg-3 col-md-6 col-12" id="fPackQtyWrap">
                  <label class="form-label" for="fPackQty"><span id="fPackQtyLabel">Units per strip</span> <span class="req">*</span></label>
                  <div class="mm-per">
                    <input type="number" min="1" id="fPackQty" value="" placeholder="e.g. 10">
                    <span class="mm-per-unit" id="fPackQtyUnit">units</span>
                  </div>
                </div>
                <div class="col-lg-5 col-md-12 col-12" id="fPackNoteWrap" hidden>
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
                    <input class="form-control" id="fBoxUnit" placeholder="e.g. Box">
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="mm-section-title">Pricing &amp; Tax</div>
          <div class="row g-3 align-items-start mm-align">
            <div class="col-12">
              <div class="mm-price-row">
                <div><label class="form-label" for="fGst"><i class="bi bi-percent"></i>GST %</label>
                  <select class="form-select" id="fGst"><option value="">Select GST</option><option>5</option><option>12</option><option>18</option></select></div>
                <div><label class="form-label" for="fMrp"><i class="bi bi-tag"></i>MRP (₹) <span class="req">*</span></label><input type="number" step="0.01" class="form-control" id="fMrp" placeholder="e.g. 120.00"></div>
                <div><label class="form-label" for="fPtr"><i class="bi bi-cart-plus"></i>Purchase Rate (₹)</label><input type="number" step="0.01" class="form-control" id="fPtr" placeholder="e.g. 80.00"></div>
                <div><label class="form-label" for="fRetail"><i class="bi bi-shop"></i>Retail Rate (₹)</label><input type="number" step="0.01" class="form-control" id="fRetail" placeholder="e.g. 100.00"><div class="mm-field-hint mm-mrp-warn" id="fRetailWarn" hidden></div></div>
                <div><label class="form-label" for="fWholesale"><i class="bi bi-boxes"></i>Wholesale Rate (₹)</label><input type="number" step="0.01" class="form-control" id="fWholesale" placeholder="e.g. 90.00"></div>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <label class="form-label" for="fDisc">Default discount</label>
              <div class="mm-per">
                <input type="number" min="0" step="0.01" id="fDisc" placeholder="0">
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
          <div class="mm-stock-grid">
            <div>
              <label class="form-label" for="fBatchNo"><i class="bi bi-upc-scan"></i>Batch no</label>
              <input class="form-control" id="fBatchNo" placeholder="e.g. B24091" autocomplete="off">
              <button type="button" class="mm-autogen" id="fBatchAuto"><i class="bi bi-arrow-repeat"></i>Auto-generate</button>
            </div>
            <div>
              <label class="form-label" for="fExpiry"><i class="bi bi-calendar2-week"></i>Expiry (month)</label>
              <input class="form-control" id="fExpiry" inputmode="numeric" maxlength="7" placeholder="04-2028" autocomplete="off">
              <div class="mm-field-hint" id="fExpiryHint"></div>
            </div>
            <div>
              <label class="form-label" for="fStockQty"><i class="bi bi-stack"></i>Quantity</label>
              <div class="mm-per">
                <input type="number" min="0" id="fStockQty" value="" placeholder="e.g. 20">
                <span class="mm-per-unit" id="fStockQtyUnit">units</span>
              </div>
              <div class="mm-field-hint" id="fStockQtyHint"></div>
            </div>
            <div>
              <label class="form-label" for="fMin"><i class="bi bi-box-seam"></i>Minimum Stock</label>
              <input type="number" class="form-control" id="fMin" placeholder="e.g. 10">
            </div>
            <div>
              <label class="form-label" for="fReorder"><i class="bi bi-arrow-repeat"></i>Reorder level</label>
              <div class="mm-per">
                <input type="number" min="0" id="fReorder" placeholder="e.g. 5">
                <span class="mm-per-unit" id="fReorderUnit">units</span>
              </div>
              <div class="mm-field-hint">Shows "Low stock" at or below this.</div>
            </div>
            <div>
              <label class="form-label" for="fRack"><i class="bi bi-columns-gap"></i>Rack / shelf</label>
              <input class="form-control" id="fRack" placeholder="e.g. A-3">
            </div>
            <div>
              <label class="form-label" for="fExpiryAlert"><i class="bi bi-bell"></i>Expiry Alert (days)</label>
              <input type="number" min="1" class="form-control" id="fExpiryAlert" placeholder="Default: 90">
            </div>
            <div class="mm-active-cell">
              <label class="form-label invisible" aria-hidden="true">Status</label>
              <label class="mm-switch">
                <input type="checkbox" id="fActive" checked>
                <span><strong>Active</strong><small>Inactive items stay out of POS</small></span>
              </label>
            </div>
          </div>
          <input type="checkbox" id="fRx" hidden>

        </div>
        <div class="modal-footer mm-form-footer">
          <div class="mm-stock-foot" id="fStockFoot" hidden><span class="mm-stock-foot-badge"><i class="bi bi-box-seam"></i></span><span id="fStockFootTxt"></span></div>
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
  <!-- Add a custom dosage "Form" — opened by the + button next to the Form dropdown -->
  <div class="modal fade" id="mmUnitModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:380px">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Add new form</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mm-modal-hero" style="padding:0 0 14px">
            <div class="ico"><i class="bi bi-tag"></i></div>
            <div>
              <strong>New custom form</strong>
              <span>Appears in the Form dropdown here, every time.</span>
            </div>
          </div>
          <label class="form-label" for="mmUnitNew">Form name <span class="req">*</span></label>
          <input class="form-control" id="mmUnitNew" placeholder="e.g. Jelly, Sachet, Tea spoon" autocomplete="off" maxlength="40">
          <div class="mm-hint">Saved on this device — reusable for any medicine in the Form dropdown.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="mm-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="mm-save" id="mmUnitSave">Add &amp; pick</button>
        </div>
      </div>
    </div>
  </div>

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

  <!-- Stock position -->
  <div class="modal fade" id="mmStockModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="mmStockTitle">Stock</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body p-0" id="mmStockBody"></div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal" type="button">Close</button>
          <button class="btn btn-mf" id="mmStockAdjust" type="button"><i class="bi bi-sliders me-1"></i>Stock Adjustment</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Batch ledger -->
  <div class="modal fade" id="mmBatchModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="mmBatchTitle">Batches</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body p-0" id="mmBatchBody"></div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal" type="button">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Stock adjustment -->
  <div class="modal fade" id="mmAdjustModal" tabindex="-1" data-bs-focus="false">
    <div class="modal-dialog modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Stock Adjustment</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mm-modal-hero stock" style="padding:0 0 14px">
            <div class="ico"><i class="bi bi-sliders"></i></div>
            <div>
              <strong id="mmAdjName">Medicine</strong>
              <span id="mmAdjSub">Change on-hand quantity for one batch.</span>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label" for="mmAdjBatch">Batch</label>
              <select class="form-select" id="mmAdjBatch"></select>
            </div>
            <div class="col-12">
              <label class="form-label">Direction</label>
              <div class="mm-adj-switch" id="mmAdjDir">
                <button type="button" data-dir="add" class="is-on add">Add stock</button>
                <button type="button" data-dir="remove">Remove stock</button>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="mmAdjQty">Quantity</label>
              <input type="number" min="1" class="form-control" id="mmAdjQty" placeholder="e.g. 2">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="mmAdjReason">Reason</label>
              <select class="form-select" id="mmAdjReason">
                <option value="">Select reason</option>
                <option>Damage</option>
                <option>Expired Write-off</option>
                <option>Theft / Loss</option>
                <option>Stock Recount</option>
                <option>Other</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" for="mmAdjNotes">Notes</label>
              <input class="form-control" id="mmAdjNotes" placeholder="Optional note for the audit trail" maxlength="255">
              <div class="mm-adj-preview" id="mmAdjPreview">Select a batch to see the new on-hand quantity.</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light-mf" data-bs-dismiss="modal" type="button">Cancel</button>
          <button class="btn mm-save" id="mmAdjSave" type="button"><i class="bi bi-check2"></i>Save adjustment</button>
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
          if (state.stock === 'expiring' && !expiringSoon(m)) return false;
          return true;
        });
      }

      const freshIds = new Set();
      function soonDays(m) {
        const n = Number(m && m.expiryAlertDays);
        return n > 0 ? n : 90;
      }
      function leadBatch(m) {
        const batches = MF.batchesOf(m.id) || [];
        const dated = batches.filter((b) => b && b.expiry);
        if (dated.length) {
          dated.sort((a, b) => MF.daysTo(a.expiry) - MF.daysTo(b.expiry));
          return dated[0];
        }
        if (m.batchNo || m.expiry) return { batchNo: m.batchNo || '', expiry: m.expiry || '' };
        return batches[0] || null;
      }
      /* matte square batch badge — colour is stable per batch no */
      const MM_BADGE_TONES = ['teal', 'blue', 'amber', 'orange', 'purple', 'pink', 'green', 'indigo', 'cyan', 'red', 'slate'];
      function mmBatchTone(no) {
        let h = 0;
        for (const c of String(no || '—')) h = (h * 31 + c.charCodeAt(0)) >>> 0;
        return MM_BADGE_TONES[h % MM_BADGE_TONES.length];
      }
      function mmBatchBdg(no) {
        if (!no) return '<span class="text-2">—</span>';
        return `<span class="mm-bdg ${mmBatchTone(no)}"><i class="bi bi-upc"></i>${MF.esc(no)}</span>`;
      }
      function expiryTone(m) {
        const batch = leadBatch(m);
        if (!batch || !batch.expiry) return null;
        const days = MF.daysTo(batch.expiry);
        if (days < 0) return { tone: 'red', label: 'Exp: expired' };
        if (days <= 30) return { tone: 'red', label: 'Exp: ' + days + (days === 1 ? ' day' : ' days') };
        if (days <= soonDays(m)) return { tone: 'amber', label: 'Exp: ' + days + (days === 1 ? ' day' : ' days') };
        return null;
      }
      function expiringSoon(m) { return !!expiryTone(m); }
      function scheduleChip(code) {
        const key = String(code || '').toUpperCase();
        if (!key) return '';
        const cls = key === 'H1' ? 'h1' : key === 'H' ? 'h' : key === 'X' ? 'x' : key === 'OTC' ? 'otc' : '';
        if (!cls) return '';
        return `<span class="mm-sch ${cls}">${MF.esc(key)}</span>`;
      }
      function render() {
        const list = filtered();
        const pages = Math.max(1, Math.ceil(list.length / state.per));
        state.page = Math.min(state.page, pages);
        const slice = list.slice((state.page - 1) * state.per, state.page * state.per);
        $('#mmCount').textContent = `${list.length} of ${D.medicines.length} medicines · batch & expiry linked`;
        $('#mmBody').innerHTML = slice.map((m) => {
          const st = MF.stockOf(m.id);
          const batch = leadBatch(m);
          const exp = expiryTone(m);
          const rx = SCHEDULES[m.schedule] ? SCHEDULES[m.schedule].rx : m.rxRequired;
          const batchNo = batch && (batch.batchNo || batch.batch_no) ? (batch.batchNo || batch.batch_no) : '';
          return `<tr>
            <td><div class="td-title">${MF.esc(m.name)}</div><div class="td-sub">${MF.esc(m.composition)}${scheduleChip(m.schedule)}${rx ? ' <span class="rx-chip" style="font-size:.6rem">Rx</span>' : ''}</div></td>
            <td>${m.brandRef ? MF.esc(m.brandRef) : '<span class="text-2">—</span>'}</td>
            <td class="text-2"><span class="mm-cat" title="${MF.esc(m.category || '')}">${m.category ? MF.esc(m.category) : '—'}</span></td>
            <td>${MF.esc(m.manufacturer)}</td>
            <td>${mmBatchBdg(batchNo)}${freshIds.has(String(m.id)) ? '<span class="mm-new">new</span>' : ''}</td>
            <td>${m.unit || '—'}</td>
            <td class="text-end num">${MF.fmt(m.mrp, 2)}</td>
            <td class="text-end num">${MF.fmt(m.retailRate ?? m.mrp, 2)}</td>
            <td class="text-end num">${MF.fmt(m.wholesaleRate, 2)}</td>
            <td class="text-end num fw-semibold">${MF.num(st)}</td>
            <td>${MF.stockBadge(m)}${exp ? `<span class="mm-exp-chip ${exp.tone}">${MF.esc(exp.label)}</span>` : ''}</td>
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
        }).join('') || `<tr><td colspan="12"><div class="empty-state"><i class="bi bi-search"></i>No medicines match the current filters.</div></td></tr>`;

        $('#mmPageInfo').textContent = `Showing ${slice.length ? (state.page - 1) * state.per + 1 : 0}–${(state.page - 1) * state.per + slice.length} of ${list.length}`;
        $('#mmPager').innerHTML = Array.from({ length: pages }, (_, i) =>
          `<li class="page-item ${i + 1 === state.page ? 'active' : ''}"><button class="page-link" data-pg="${i + 1}">${i + 1}</button></li>`).join('');
        $('#mmPager').querySelectorAll('[data-pg]').forEach((b) => b.addEventListener('click', () => { state.page = +b.dataset.pg; render(); }));

        $('#mmBody').querySelectorAll('[data-a]').forEach((b) => b.addEventListener('click', () => {
          const m = MF.med(b.dataset.id), a = b.dataset.a;
          if (a === 'view') openView(m);
          if (a === 'edit') openForm(m);
          if (a === 'stock') openStock(m);
          if (a === 'batches') openBatches(m);
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
          ? ['Pack', 'Sold as one complete ' + spec.pack + (Number(m.packQty) > 1 ? ' · ' + mmTxt(m.packQty) + ' ' + piece : '')]
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
                ['Box', mmTxt([m.boxQty, m.boxUnit].filter((x) => x != null && x !== '').join(' '))],
                ['Expiry', expiryHint(expiryToField(m.expiry)) || mmTxt(expiryToField(m.expiry))],
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

      /* On edit, Quantity shows the live TOTAL strips on hand — the same number as the
         Stock column. Stock grows one batch per purchase line and is corrected through
         Stock Adjustment, so on edit this field is display-only and saving sends the
         quantity blank (the medicines API then leaves every batch untouched). */
      function stockQtyForForm(m) {
        const batches = MF.batchesOf(m.id) || [];
        if (batches.length) return String(MF.stockOf(m.id));
        return m.openingQty ?? '';
      }

      /* Batch auto-generate — pattern B + YYMMDD (e.g. B261001); same-day clicks step a suffix -A, -B… */
      function autoBatchBase() {
        const d = new Date();
        return `B${String(d.getFullYear()).slice(2)}${String(d.getMonth() + 1).padStart(2, '0')}${String(d.getDate()).padStart(2, '0')}`;
      }
      function nextAutoBatch() {
        const v = (($('#fBatchNo') || {}).value || '').trim();
        const base = autoBatchBase();
        if (!v) return base;
        if (v === base) return `${base}-A`;
        const m = v.match(new RegExp(`^${base}-([A-Z])$`));
        if (m) return `${base}-${String.fromCharCode(m[1].charCodeAt(0) + 1)}`;
        return v; // a manual number is kept unless the user confirms otherwise
      }
      async function onBatchAuto() {
        const inp = $('#fBatchNo');
        const v = (inp.value || '').trim();
        const base = autoBatchBase();
        if (v && !v.startsWith(base)) {
          if (editingId) { MF.toast('Batch number stays unchanged for an existing medicine — stock and sales history reference it.', 'warn', 'Batch no'); return; }
          const ok = await MF.confirm({ title: 'Replace batch number?', message: `Replace "${v}" with the generated ${base}?`, confirmText: 'Replace' });
          if (!ok) return;
        }
        inp.value = nextAutoBatch();
        MF.toast(`Batch ${inp.value} generated`, 'success');
      }

      function openForm(m) {
        editingId = m ? m.id : null;
        $('#mmFormTitle').textContent = m ? 'Edit Medicine — ' + m.name : 'Add Medicine';
        $('#fName').value = m?.name || ''; $('#fGeneric').value = m?.generic || ''; $('#fComp').value = m?.composition || ''; $('#fBrand').value = m?.brandRef || '';
        $('#fCategory').value = m?.category || ''; $('#fMfg').value = m?.manufacturer || '';
        setGroups(m?.genericGroup || (!m || m.substitutes == null ? '' : (Array.isArray(m.substitutes) ? m.substitutes.join(', ') : String(m.substitutes))));
        if (m && formFromMed(m)) addUnitOption(formFromMed(m), false);
        $('#fHsn').value = m?.hsn || ''; $('#fUnit').value = m ? formFromMed(m) : '';
        $('#fBarcode').value = m?.barcode || '';
        $('#fPackQty').value = m?.packQty ?? ''; $('#fAllowLoose').checked = !!m?.allowLoose;
        $('#fBatchNo').value = m?.batchNo || (m ? (MF.batchesOf(m.id)[0]?.batchNo || '') : '');
        if (!m && !$('#fBatchNo').value) $('#fBatchNo').value = nextAutoBatch(); // prefill on add-new; user can overwrite
        $('#fStockQty').value = m ? stockQtyForForm(m) : '';
        $('#fStockQty').readOnly = !!m;
        $('#fStockQty').title = m ? 'On-hand stock — add stock from New Purchase, correct it from Stock Adjustment.' : '';
        syncPackUnit();
        const qtyHint = $('#fStockQtyHint');
        if (qtyHint) qtyHint.textContent = m ? '' : 'Opening stock for the first batch.';
        const stockFoot = $('#fStockFoot'), stockFootTxt = $('#fStockFootTxt');
        if (stockFoot && stockFootTxt) {
          if (m) {
            stockFootTxt.innerHTML = `<strong>Quantity</strong> — On-hand stock: <strong>${MF.esc(stockQtyForForm(m))} ${MF.esc($('#fStockQtyUnit').textContent || 'units')}</strong> · add more from New Purchase, correct it from Stock Adjustment.`;
            stockFoot.hidden = false;
          } else { stockFootTxt.textContent = ''; stockFoot.hidden = true; }
        }
        $('#fBoxQty').value = m?.boxQty ?? ''; $('#fBoxUnit').value = m?.boxUnit || '';
        $('#fGst').value = m && m.gst != null && m.gst !== '' ? String(m.gst) : ''; setSchedule(m?.schedule || 'OTC');
        $('#fMrp').value = m?.mrp ?? ''; $('#fPtr').value = m?.purchaseRate ?? ''; $('#fRetail').value = m?.retailRate ?? m?.mrp ?? '';
        $('#fWholesale').value = m?.wholesaleRate ?? ''; $('#fDisc').value = m?.defaultDiscount ?? ''; setDiscType(m?.discountType === 'rupee' ? 'rupee' : 'percent', false); $('#fMin').value = m?.minStock ?? ''; $('#fReorder').value = m?.reorderLevel ?? ''; $('#fRack').value = m?.rack || '';
        $('#fExpiry').value = expiryToField(m?.expiry || (m ? (MF.batchesOf(m.id)[0]?.expiry || '') : ''));
        syncExpiryHint();
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
      const EXPIRY_MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      function expiryToField(value) {
        if (!value) return '';
        const text = String(value);
        const iso = /^(\d{4})-(\d{2})/.exec(text);
        if (iso) return iso[2] + '-' + iso[1];
        return /^(\d{2})-(\d{4})$/.test(text) ? text : '';
      }
      function fieldToExpiry(text) {
        const match = /^(\d{2})-(\d{4})$/.exec(String(text || '').trim());
        if (!match) return '';
        const month = +match[1];
        if (month < 1 || month > 12) return '';
        return match[2] + '-' + match[1] + '-01';
      }
      function expiryHint(text) {
        const match = /^(\d{2})-(\d{4})$/.exec(String(text || '').trim());
        if (!match) return '';
        const month = +match[1];
        if (month < 1 || month > 12) return '';
        return 'Expires ' + EXPIRY_MONTHS[month - 1] + ' ' + match[2] + '.';
      }
      function syncExpiryHint() {
        const el = $('#fExpiryHint');
        if (!el) return;
        const raw = $('#fExpiry') ? $('#fExpiry').value.trim() : '';
        el.textContent = raw ? (expiryHint(raw) || 'Use month and year, like 04-2028.') : '';
      }
      function randomBarcode() {
        let body = '20';
        for (let i = 0; i < 10; i++) body += Math.floor(Math.random() * 10);
        let sum = 0;
        for (let i = 0; i < 12; i++) sum += (+body[i]) * (i % 2 ? 3 : 1);
        return body + ((10 - (sum % 10)) % 10);
      }
      function barcodeSvg(code) {
        const digits = String(code || '').replace(/\D/g, '');
        const L = ['0001101','0011001','0010011','0111101','0100011','0110001','0101111','0111011','0110111','0001011'];
        const G = ['0100111','0110011','0011011','0100001','0011101','0111001','0000101','0010001','0001001','0010111'];
        const R = ['1110010','1100110','1101100','1000010','1011100','1001110','1010000','1000100','1001000','1110100'];
        const P = ['LLLLLL','LLGLGG','LLGGLG','LLGGGL','LGLLGG','LGGLLG','LGGGLL','LGLGLG','LGLGGL','LGGLGL'];
        if (digits.length !== 13) return '';
        const parity = P[+digits[0]];
        let bits = '101';
        for (let i = 1; i <= 6; i++) bits += (parity[i - 1] === 'L' ? L : G)[+digits[i]];
        bits += '01010';
        for (let i = 7; i <= 12; i++) bits += R[+digits[i]];
        bits += '101';
        const width = 2;
        let x = 0;
        let rects = '';
        for (const bit of bits) {
          if (bit === '1') rects += '<rect x="' + x + '" y="0" width="' + width + '" height="72" fill="#111"/>';
          x += width;
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' + x + '" height="72" viewBox="0 0 ' + x + ' 72">' + rects + '</svg>';
      }
      function printBarcodeLabel() {
        const code = $('#fBarcode').value.trim();
        if (!code) { MF.toast('Generate or enter a barcode first.', 'warn', 'Barcode'); return; }
        const name = $('#fName').value.trim() || 'Medicine';
        const svg = barcodeSvg(code);
        const win = window.open('', '_blank', 'width=420,height=320');
        if (!win) { MF.toast('Allow pop-ups to print the label.', 'warn', 'Barcode'); return; }
        win.document.write('<!doctype html><title>Label</title><style>body{font-family:Inter,sans-serif;margin:24px;text-align:center}h1{font-size:18px;margin:0 0 12px}p{letter-spacing:.12em;font-size:16px}</style><h1>' + MF.esc(name) + '</h1>' + (svg || '') + '<p>' + MF.esc(code) + '</p><' + 'script>window.onload=function(){window.print()}<' + '/script>');
        win.document.close();
      }
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
        Gel: { pack: 'tube', packPlural: 'tubes', piecePlural: 'g', unit: 'Tube', sub: 'g', loose: false, whole: true },
        Cream: { pack: 'tube', packPlural: 'tubes', piecePlural: 'g', unit: 'Tube', sub: 'g', loose: false, whole: true },
        Powder: { pack: 'sachet', packPlural: 'sachets', piecePlural: 'g', unit: 'Sachet', sub: 'g', loose: false, whole: true },
        Sachet: { pack: 'sachet', packPlural: 'sachets', piecePlural: 'sachets', unit: 'Sachet', sub: 'Sachet', loose: false, whole: true },
        Other: { pack: 'unit', packPlural: 'units', piecePlural: 'units', unit: 'Unit', sub: 'Unit', loose: false, whole: false }
      };
      function packSpec(form) {
        if (FORM_PACK[form]) return FORM_PACK[form];
        // Custom form (created with the + button) — fall back to the "Other" spec so
        // save / stock text / view / bulk never see a null spec again
        return String(form || '').trim() ? FORM_PACK.Other : null;
      }
      function formFromMed(m) {
        if (!m) return '';
        if (m.form) return m.form; // saved value is already a valid dropdown label — built-in or custom
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
        const label = !spec ? 'Units per strip' : (spec.whole ? 'Pack size' : (spec.pack === 'unit' ? 'Units' : 'Units per ' + spec.pack));
        if ($('#fPackQtyLabel')) $('#fPackQtyLabel').textContent = label;
        if ($('#fPackQtyUnit')) $('#fPackQtyUnit').textContent = spec ? spec.piecePlural : 'units';
        if ($('#fStockQtyUnit')) $('#fStockQtyUnit').textContent = spec ? spec.packPlural : 'units';
        if ($('#fReorderUnit')) $('#fReorderUnit').textContent = spec ? spec.packPlural : 'units';
        const whole = !!(spec && spec.whole);
        if ($('#fPackQtyWrap')) $('#fPackQtyWrap').hidden = false;
        if ($('#fPackNoteWrap')) $('#fPackNoteWrap').hidden = !whole;
        if (whole) {
          // Gram/ml packs (tube, bottle, vial…): the pack size field IS the printed pack size (30 g, 100 ml)
          // — kept for display (cart, stock, view). Billing stays per whole pack; loose stays unavailable.
          if ($('#fPackNote')) $('#fPackNote').textContent = 'Pack size printed on the ' + spec.pack + ' (e.g. 30 g tube, 100 ml bottle). Billing is per ' + spec.pack + ' — no loose sale.';
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

        // Retail can never exceed MRP — flag the field + inline warning
        const retail = parseFloat($('#fRetail').value);
        const retailOver = !isNaN(retail) && !isNaN(mrp) && mrp > 0 && retail > mrp;
        const retailWarn = $('#fRetailWarn');
        if (retailWarn) { retailWarn.textContent = retailOver ? `Above MRP ${MF.fmt(mrp, 2)} — can't sell over MRP` : ''; retailWarn.hidden = !retailOver; }
        const retailInp = $('#fRetail');
        if (retailInp) retailInp.classList.toggle('is-mrp-over', retailOver);

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
      $('#fBarcodeGen').addEventListener('click', () => {
        $('#fBarcode').value = randomBarcode();
        checkBarcodeConflict();
      });
      $('#fBarcodePrint').addEventListener('click', printBarcodeLabel);
      $('#fExpiry').addEventListener('input', () => {
        const digits = $('#fExpiry').value.replace(/\D/g, '').slice(0, 6);
        $('#fExpiry').value = digits.length <= 2 ? digits : digits.slice(0, 2) + '-' + digits.slice(2);
        syncExpiryHint();
      });
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

      // Custom "Form" names — created via the + button, kept on this device, reused every visit
      const CUSTOM_UNITS_KEY = 'medicine.customUnits.v1';
      const customUnits = () => { try { return JSON.parse(localStorage.getItem(CUSTOM_UNITS_KEY)) || []; } catch (e) { return []; } };
      function addUnitOption(name, persist) {
        const sel = $('#fUnit'); const val = String(name || '').trim();
        if (!sel || !val) return;
        if (![...sel.options].some((o) => o.text.toLowerCase() === val.toLowerCase())) {
          const opt = document.createElement('option');
          opt.text = val; // no value attr → option value = text
          sel.appendChild(opt);
        }
        if (persist && !customUnits().some((u) => String(u).toLowerCase() === val.toLowerCase()))
          try { localStorage.setItem(CUSTOM_UNITS_KEY, JSON.stringify([...customUnits(), val])); } catch (e) { /* storage full/blocked */ }
      }
      customUnits().forEach((u) => addUnitOption(u, false));

      // The Form "+" button opens a small in-app dialog (not a browser prompt)
      const unitModalEl = $('#mmUnitModal');
      const unitModal = () => bootstrap.Modal.getOrCreateInstance(unitModalEl);
      $('#fUnitAdd').addEventListener('click', () => {
        $('#mmUnitNew').value = '';
        $('#mmUnitNew').classList.remove('is-invalid');
        unitModal().show();
        unitModalEl.addEventListener('shown.bs.modal', () => $('#mmUnitNew').focus(), { once: true });
      });
      const submitUnit = () => {
        const raw = ($('#mmUnitNew').value || '').trim();
        if (!raw) { $('#mmUnitNew').classList.add('is-invalid'); $('#mmUnitNew').focus(); return; }
        const name = raw.charAt(0).toUpperCase() + raw.slice(1);
        addUnitOption(name, true);
        const sel = $('#fUnit');
        sel.value = name;
        sel.dispatchEvent(new Event('input', { bubbles: true }));
        sel.dispatchEvent(new Event('change', { bubbles: true }));
        unitModal().hide();
        MF.toast(`Form "${name}" added — picked for this medicine.`, 'success', 'Custom form');
      };
      $('#mmUnitSave').addEventListener('click', submitUnit);
      $('#mmUnitNew').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); submitUnit(); } });
      $('#mmUnitNew').addEventListener('input', () => $('#mmUnitNew').classList.remove('is-invalid'));

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
          form: $('#fUnit').value, unit: spec.unit, packSize: '', gst: $('#fGst').value === '' ? '' : +$('#fGst').value, schedule: $('#fSchedule').value,
          packQty: (+$('#fPackQty').value || 1), subUnit: spec.sub, allowLoose: spec.loose && $('#fAllowLoose').checked,
          batchNo: $('#fBatchNo').value.trim(),
          // On edit the read-only field shows TOTAL on-hand stock, so never send that
          // to the batch writer — echo the stored opening qty instead. The API only
          // rewrites a batch when the qty differs from what it stored, so this is a no-op.
          openingQty: editingId ? (((MF.med(editingId) || {}).openingQty) ?? '') : ($('#fStockQty').value === '' ? '' : +$('#fStockQty').value),
          boxQty: $('#fBoxQty').value, boxUnit: $('#fBoxUnit').value.trim(),
          expiry: fieldToExpiry($('#fExpiry').value),
          mrp: +$('#fMrp').value, retailRate: +$('#fRetail').value || +$('#fMrp').value, purchaseRate: $('#fPtr').value === '' ? '' : +$('#fPtr').value,
          defaultDiscount: $('#fDisc').value === '' ? '' : +$('#fDisc').value, discountType: $('#fDiscType').value || 'percent',
          wholesaleRate: $('#fWholesale').value === '' ? '' : +$('#fWholesale').value, minStock: $('#fMin').value === '' ? '' : +$('#fMin').value,
          reorderLevel: $('#fReorder').value === '' ? '' : +$('#fReorder').value, rack: $('#fRack').value.trim(), rxRequired: !!(SCHEDULES[$('#fSchedule').value] && SCHEDULES[$('#fSchedule').value].rx), expiryAlertDays: $('#fExpiryAlert').value,
          status: $('#fActive').checked ? 'Active' : 'Inactive'
        };
        const wasNew = !editingId;
        const knownIds = new Set((D.medicines || []).map((m) => String(m.id)));
        let saved = null;
        if (MF.Api.live) {
          try {
            saved = editingId
              ? await MF.Api.put('medicines.php', { id: editingId, ...payload })
              : await MF.Api.post('medicines.php', payload);
            await MF.rehydrate();
            const med = saved && (saved.medicine || (saved.data && !Array.isArray(saved.data) ? saved.data : null));
            if (med && med.id != null) {
              const local = MF.med(med.id);
              if (local) Object.assign(local, payload, med);
              else D.medicines.unshift(Object.assign({}, payload, med));
              if (wasNew) freshIds.add(String(med.id));
            }
            if (saved && saved.batch && Array.isArray(D.batches)) {
              const batch = saved.batch;
              const idx = D.batches.findIndex((b) => String(b.medId) === String(batch.medId) && String(b.batchNo) === String(batch.batchNo));
              if (idx >= 0) Object.assign(D.batches[idx], batch);
              else D.batches.push(batch);
            }
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
          const id = 'M' + String(100 + D.medicines.length);
          D.medicines.unshift({ id, brandRef: '', ...payload });
          freshIds.add(id);
        }
        if (wasNew) (D.medicines || []).forEach((m) => { if (!knownIds.has(String(m.id))) freshIds.add(String(m.id)); });
        MF.toast(payload.name + (editingId ? ' updated successfully.' : ' added to the medicine master.'), 'success', editingId ? 'Medicine saved' : 'Medicine created');
        buildLookups();
        render();
        if (addAnother) openForm(null);
        else bootstrap.Modal.getInstance($('#mmFormModal')).hide();
        saving = false;
        if (busy) busy.classList.remove('is-busy');
      }
      $('#mmFormSave').addEventListener('click', () => saveMedicine(false));
      $('#fBatchAuto').addEventListener('click', onBatchAuto);
      $('#mmFormSaveAnother').addEventListener('click', () => saveMedicine(true));
      document.addEventListener('keydown', (e) => {
        if (!(e.ctrlKey || e.metaKey) || e.key.toLowerCase() !== 's') return;
        if (!$('#mmFormModal').classList.contains('show')) return;
        e.preventDefault();
        saveMedicine(false);
      });

      let stockMed = null;
      let adjustReturn = null;
      let adjustDir = 'add';
      function batchDays(b) {
        if (!b || !b.expiry) return null;
        const days = MF.daysTo(b.expiry);
        return Number.isFinite(days) ? days : null;
      }
      function batchAvailable(b) {
        return Math.max(0, (Number(b.qty) || 0) - (Number(b.reserved) || 0));
      }
      function showModal(el) { bootstrap.Modal.getOrCreateInstance(el).show(); }
      function packWord(m) {
        const raw = String((m && m.unit) || 'strip').trim().toLowerCase();
        if (raw.endsWith('s') && raw.length > 3) return raw.slice(0, -1);
        return raw || 'strip';
      }
      function rateCell(b, m) {
        const rate = Number(b.purchaseRate || b.purchase_rate || 0);
        const mrp = Number(b.mrp || m.mrp || 0);
        return `<span class="num">${MF.fmt(rate, 2)}</span> <span class="text-2">/ ${MF.esc(packWord(m))}</span><span class="mm-cdot">·</span><span class="text-2">MRP ${MF.fmt(mrp, 2)}</span>`;
      }
      function activityRows(rows, m) {
        if (!rows.length) return '<div class="mm-act-row text-2">No sales, purchases, or adjustments yet.</div>';
        const unit = packWord(m);
        return rows.slice(0, 5).map((r) => {
          const kind = r.kind || 'adjustment';
          const qty = Number(r.qty || 0);
          const sign = qty > 0 ? '+' : '';
          const when = r.date ? MF.fmtDate(String(r.date).slice(0, 10)) : '—';
          const ref = [r.ref, r.batch, r.party].filter(Boolean).join(' · ');
          return `<div class="mm-act-row">
            <span class="mm-act-kind ${MF.esc(kind)}">${MF.esc(kind === 'sale' ? 'Sale' : kind === 'purchase' ? 'Purchase' : 'Adjustment')}</span>
            <span class="text-2">${MF.esc(when)}</span>
            <span class="grow text-2">${MF.esc(ref || '—')}</span>
            <span class="qty ${qty < 0 ? 'down' : 'up'}">${sign}${MF.num(qty)} ${MF.esc(unit)}</span>
          </div>`;
        }).join('');
      }
      function localActivity(medId) {
        const events = [];
        const medName = String((MF.med(medId) || {}).name || '');
        const same = (row) => {
          const id = row.medId || row.medicineId || row.medicine_id;
          if (id != null && id !== '') return String(id) === String(medId);
          const name = row.medicine_name || row.name || row.medicine || '';
          return name !== '' && name === medName;
        };
        (D.sales || D.salesInvoices || []).forEach((inv) => {
          (inv.items || inv.lines || []).forEach((it) => {
            if (!same(it)) return;
            events.push({ kind: 'sale', date: inv.sale_date || inv.date || inv.created_at || '', ref: inv.invoice_no || inv.no || '', batch: it.batch_no || it.batchNo || '', party: inv.customer_name || inv.customer || '', qty: -Math.abs(Number(it.qty) || 0), at: inv.created_at || inv.sale_date || inv.date || '' });
          });
        });
        (D.purchases || D.purchaseInvoices || []).forEach((inv) => {
          (inv.items || inv.lines || []).forEach((it) => {
            if (!same(it)) return;
            events.push({ kind: 'purchase', date: inv.invoice_date || inv.date || inv.created_at || '', ref: inv.invoice_no || inv.no || '', batch: it.batch_no || it.batchNo || '', party: inv.supplier_name || inv.supplier || '', qty: Math.abs(Number(it.qty) || 0), at: inv.created_at || inv.invoice_date || inv.date || '' });
          });
        });
        (D.stockAdjustments || D.adjustments || []).forEach((a) => {
          if (!same(a)) return;
          events.push({ kind: 'adjustment', date: a.created_at || a.date || '', ref: a.reason || '', batch: a.batchNo || a.batch_no || '', party: a.adjustedBy || a.adjusted_by || '', qty: Number(a.qtyChange ?? a.qty_change) || 0, at: a.created_at || a.date || '' });
        });
        return events.sort((a, b) => String(b.at || b.date).localeCompare(String(a.at || a.date))).slice(0, 5);
      }
      async function loadActivity(m) {
        const box = document.getElementById('mmActivityList');
        if (!box) return;
        let rows = [];
        if (MF.Api.live) {
          try {
            const res = await MF.Api.get('stock-activity.php?medicineId=' + encodeURIComponent(m.id));
            rows = Array.isArray(res.data) ? res.data : [];
          } catch (_) {
            rows = localActivity(m.id);
          }
        } else rows = localActivity(m.id);
        if (document.getElementById('mmActivityList')) document.getElementById('mmActivityList').innerHTML = activityRows(rows, MF.med(m.id) || m);
      }
      function openStock(m) {
        stockMed = m;
        const bs = MF.batchesOf(m.id);
        const total = MF.stockOf(m.id);
        const reserved = bs.reduce((s, b) => s + (Number(b.reserved) || 0), 0);
        const available = Math.max(0, total - reserved);
        const unit = m.unit || 'units';
        const pack = packWord(m);
        const purchValue = bs.reduce((s, b) => s + (Number(b.qty) || 0) * (Number(b.purchaseRate || b.purchase_rate) || 0), 0);
        const nearest = bs.filter((b) => b.expiry).sort((a, b) => String(a.expiry).localeCompare(String(b.expiry)))[0];
        const lowAt = Number(m.reorderLevel ?? m.minStock ?? 0);
        const note = total === 0
          ? `<div class="mm-stock-note out"><i class="bi bi-exclamation-circle"></i>Out of stock. Nothing can be billed until a batch is received.</div>`
          : total <= lowAt
            ? `<div class="mm-stock-note low"><i class="bi bi-exclamation-triangle"></i>Low stock. At or below reorder level (${MF.num(m.reorderLevel ?? m.minStock ?? 0)} ${MF.esc(unit)}).</div>`
            : '';
        const rows = bs.map((b) => {
          const days = batchDays(b);
          const expTone = days == null ? '' : days < 0 ? 'bad' : days <= 90 ? 'warn' : 'ok';
          return `<tr>
            <td><span class="mm-batch"><i class="bi bi-upc"></i>${MF.esc(b.batchNo || '—')}</span></td>
            <td class="num mm-exp ${expTone}">${b.expiry ? MF.fmtMonthYear(b.expiry) : '—'}</td>
            <td class="text-end num">${MF.num(b.qty)} <span class="text-2">${MF.esc(unit)}</span></td>
            <td class="mm-rate">${rateCell(b, m)}</td>
          </tr>`;
        }).join('');
        $('#mmStockTitle').textContent = 'Stock';
        $('#mmStockBody').innerHTML = `
          <div class="mm-modal-hero stock">
            <div class="ico"><i class="bi bi-box-seam"></i></div>
            <div><strong>${MF.esc(m.name)}</strong><span>On-hand position for this medicine.</span></div>
            <div class="ms-auto">${MF.stockBadge(m)}</div>
          </div>
          <div class="mm-stock-top"><div class="mm-stock-kpis">
            <div class="mm-kpi on"><i class="bi bi-box-seam mm-kpi-ico"></i><span>On hand</span><strong>${MF.num(total)}</strong><small>${MF.esc(unit)}</small></div>
            <div class="mm-kpi free"><i class="bi bi-check2-circle mm-kpi-ico"></i><span>Available</span><strong>${MF.num(available)}</strong><small>Ready to sell</small></div>
            <div class="mm-kpi hold"><i class="bi bi-lock mm-kpi-ico"></i><span>Reserved</span><strong>${MF.num(reserved)}</strong><small>Held for bills</small></div>
            <div class="mm-kpi value"><i class="bi bi-cash-coin mm-kpi-ico"></i><span>Purchase value</span><strong>${MF.fmt(purchValue)}</strong><small>At batch cost</small></div>
          </div></div>
          <div class="mm-stock-meta">Reorder ${m.reorderLevel === '' || m.reorderLevel == null ? '—' : MF.num(m.reorderLevel) + ' ' + MF.esc(unit)} · Rack ${m.rack ? MF.esc(m.rack) : '—'} · Nearest expiry ${nearest ? MF.fmtMonthYear(nearest.expiry) : '—'}</div>
          ${note}
          <table class="table table-mf mm-stock-table mb-3">
            <thead><tr><th>Batch</th><th>Expiry</th><th class="text-end">Qty</th><th>Purchase · MRP</th></tr></thead>
            <tbody>${rows || '<tr><td colspan="4"><div class="empty-state" style="padding:22px 16px"><i class="bi bi-box-seam"></i>No batches recorded.</div></td></tr>'}</tbody>
          </table>
          <details class="mm-activity" open>
            <summary><i class="bi bi-chevron-down caret"></i> Recent activity <span>(last sale, purchase, adjustment)</span></summary>
            <div id="mmActivityList"><div class="mm-act-row text-2">Loading recent activity…</div></div>
          </details>`;
        const btn = $('#mmStockAdjust');
        if (btn) btn.disabled = bs.length === 0;
        showModal($('#mmStockModal'));
        loadActivity(m);
      }
      function openBatches(m) {
        const bs = MF.batchesOf(m.id).slice().sort((a, b) => String(a.expiry || '9999').localeCompare(String(b.expiry || '9999')));
        const expired = bs.filter((b) => { const d = batchDays(b); return d != null && d < 0; }).length;
        const soon = bs.filter((b) => { const d = batchDays(b); return d != null && d >= 0 && d <= 90; }).length;
        const rows = bs.map((b) => {
          const days = batchDays(b);
          const expTone = days == null ? '' : days < 0 ? 'bad' : days <= 90 ? 'warn' : 'ok';
          return `<tr>
            <td><span class="mm-batch"><i class="bi bi-upc"></i>${MF.esc(b.batchNo || '—')}</span></td>
            <td class="num">${b.purchaseDate ? MF.fmtDate(b.purchaseDate) : '—'}</td>
            <td class="num mm-exp ${expTone}">${b.expiry ? MF.fmtMonthYear(b.expiry) : '—'}</td>
            <td class="text-end num">${MF.num(b.qty)}</td>
            <td class="text-end num text-2">${MF.num(b.reserved || 0)}</td>
            <td class="text-end num">${MF.fmt(b.purchaseRate || 0, 2)}</td>
            <td class="text-end num">${MF.fmt(b.mrp || 0, 2)}</td>
            <td>${MF.statusBadge(MF.batchStatus(b))}</td>
          </tr>`;
        }).join('');
        $('#mmBatchTitle').textContent = 'Batches';
        $('#mmBatchBody').innerHTML = `
          <div class="mm-modal-hero batch">
            <div class="ico"><i class="bi bi-collection"></i></div>
            <div><strong>${MF.esc(m.name)}</strong><span>Purchase and expiry ledger. Quantity changes are made from Stock.</span></div>
          </div>
          <div class="mm-batch-chips">
            <span class="mm-batch-chip">${MF.num(bs.length)} batches</span>
            <span class="mm-batch-chip warn">${MF.num(soon)} expiring within 90 days</span>
            <span class="mm-batch-chip bad">${MF.num(expired)} expired</span>
          </div>
          <table class="table table-mf mm-stock-table mb-0">
            <thead><tr><th>Batch No</th><th>Purchase Date</th><th>Expiry</th><th class="text-end">Qty</th><th class="text-end">Reserved</th><th class="text-end">Purchase Rate</th><th class="text-end">MRP</th><th>Status</th></tr></thead>
            <tbody>${rows || '<tr><td colspan="8"><div class="empty-state" style="padding:28px 16px"><i class="bi bi-collection"></i>No batches recorded.</div></td></tr>'}</tbody>
          </table>`;
        showModal($('#mmBatchModal'));
      }
      function selectedAdjustBatch() {
        if (!stockMed) return null;
        const key = $('#mmAdjBatch').value;
        return MF.batchesOf(stockMed.id).find((b) => String(b.id || b.batchNo) === key) || null;
      }
      function setAdjustDir(dir) {
        adjustDir = dir === 'remove' ? 'remove' : 'add';
        document.querySelectorAll('#mmAdjDir button').forEach((b) => {
          const on = b.dataset.dir === adjustDir;
          b.classList.toggle('is-on', on);
          b.classList.toggle('add', on && adjustDir === 'add');
          b.classList.toggle('remove', on && adjustDir === 'remove');
        });
        previewAdjust();
      }
      function previewAdjust() {
        const el = $('#mmAdjPreview');
        const batch = selectedAdjustBatch();
        const qty = Math.floor(Number($('#mmAdjQty').value));
        if (!batch) { el.className = 'mm-adj-preview'; el.textContent = 'Select a batch to see the new on-hand quantity.'; return; }
        const have = Number(batch.qty) || 0;
        const free = batchAvailable(batch);
        if (!qty || qty < 1) {
          el.className = 'mm-adj-preview';
          el.textContent = `On hand ${MF.num(have)}. Available to remove ${MF.num(free)}.`;
          return;
        }
        if (adjustDir === 'remove' && qty > free) {
          el.className = 'mm-adj-preview remove';
          el.textContent = `Only ${MF.num(free)} can be removed. ${MF.num(batch.reserved || 0)} is reserved.`;
          return;
        }
        const next = adjustDir === 'remove' ? have - qty : have + qty;
        el.className = 'mm-adj-preview' + (adjustDir === 'remove' ? ' remove' : '');
        el.textContent = `On hand becomes ${MF.num(next)} ${stockMed && stockMed.unit ? stockMed.unit : ''}`.trim() + '.';
      }
      function openAdjust(m) {
        const bs = MF.batchesOf(m.id);
        if (!bs.length) { MF.toast('Add a batch before adjusting stock.', 'warn', 'Stock Adjustment'); return; }
        stockMed = m;
        $('#mmAdjName').textContent = m.name;
        $('#mmAdjSub').textContent = 'Change on-hand quantity for one batch. This is written to the adjustment register.';
        $('#mmAdjBatch').innerHTML = bs.map((b) => `<option value="${MF.esc(b.id || b.batchNo)}">${MF.esc(b.batchNo || 'Batch')} · ${MF.num(b.qty)} on hand</option>`).join('');
        $('#mmAdjQty').value = '';
        $('#mmAdjReason').value = '';
        $('#mmAdjNotes').value = '';
        setAdjustDir('add');
        adjustReturn = m;
        const stockEl = $('#mmStockModal');
        if (stockEl.classList.contains('show')) {
          stockEl.addEventListener('hidden.bs.modal', () => setTimeout(() => showModal($('#mmAdjustModal')), 0), { once: true });
          bootstrap.Modal.getOrCreateInstance(stockEl).hide();
        } else showModal($('#mmAdjustModal'));
      }
      async function saveAdjust() {
        const m = stockMed;
        const batch = selectedAdjustBatch();
        const qty = Math.floor(Number($('#mmAdjQty').value));
        const reason = $('#mmAdjReason').value;
        const notes = $('#mmAdjNotes').value.trim();
        if (!m || !batch) { MF.toast('Select a batch.', 'err', 'Stock Adjustment'); return; }
        if (!qty || qty < 1) { MF.toast('Enter a quantity of at least 1.', 'err', 'Stock Adjustment'); return; }
        if (!reason) { MF.toast('Select a reason.', 'err', 'Stock Adjustment'); return; }
        if (adjustDir === 'remove' && qty > batchAvailable(batch)) {
          MF.toast('That quantity is more than the available stock.', 'err', 'Stock Adjustment');
          return;
        }
        const delta = adjustDir === 'remove' ? -qty : qty;
        const busy = $('#mmAdjSave');
        if (busy.classList.contains('is-busy')) return;
        busy.classList.add('is-busy');
        try {
          if (MF.Api.live) {
            if (!batch.id) { MF.toast('This batch has no id, so it cannot be adjusted on the server.', 'err', 'Stock Adjustment'); return; }
            await MF.Api.post('stock-adjustments.php', {
              medId: m.id, medicineId: m.id, batchId: batch.id, qtyChange: delta, reason, notes
            });
            await MF.rehydrate();
          } else {
            batch.qty = (Number(batch.qty) || 0) + delta;
            D.stockAdjustments = D.stockAdjustments || [];
            D.stockAdjustments.unshift({ medicineId: m.id, medId: m.id, batchNo: batch.batchNo, qtyChange: delta, reason, notes, created_at: new Date().toISOString().slice(0, 10) });
          }
          const fresh = MF.med(m.id) || m;
          stockMed = fresh;
          adjustReturn = fresh;
          render();
          MF.toast(m.name + ' stock updated.', 'success', 'Stock Adjustment');
          bootstrap.Modal.getOrCreateInstance($('#mmAdjustModal')).hide();
        } catch (e) {
          MF.toast(e.message, 'err', 'Stock Adjustment');
        } finally {
          busy.classList.remove('is-busy');
        }
      }
      $('#mmStockAdjust').addEventListener('click', () => { if (stockMed) openAdjust(stockMed); });
      $('#mmAdjDir').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-dir]');
        if (btn) setAdjustDir(btn.dataset.dir);
      });
      ['mmAdjBatch', 'mmAdjQty'].forEach((id) => $('#' + id).addEventListener('input', previewAdjust));
      $('#mmAdjBatch').addEventListener('change', previewAdjust);
      $('#mmAdjSave').addEventListener('click', saveAdjust);
      $('#mmAdjustModal').addEventListener('hidden.bs.modal', () => {
        const m = adjustReturn;
        adjustReturn = null;
        if (m) setTimeout(() => openStock(MF.med(m.id) || m), 0);
      });

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

      /* ================= Bulk Add medicines (CSV) =================
         One row → one medicine + its opening batch (no / qty / expiry).
         Posts each row through the existing medicines API, so server-side
         validation, batch writing and tenancy stay untouched. */
      let bulkRows = [];
      const BULK_ALIASES = {
        name: ['name', 'medicine', 'medicine name', 'product'], generic: ['generic', 'generic name', 'salt'],
        composition: ['composition', 'contents'],
        brandRef: ['brand', 'brand name'], category: ['category'], manufacturer: ['manufacturer', 'mfg', 'company'],
        hsn: ['hsn', 'hsn code'], gst: ['gst', 'gst%', 'gst %'], form: ['form', 'unit', 'dosage form'],
        packQty: ['pack qty', 'packqty', 'pack size', 'qty per pack'], barcode: ['barcode', 'code'],
        batchNo: ['batch no', 'batch', 'batch number'], openingQty: ['opening qty', 'opening stock', 'stock', 'qty', 'quantity'],
        expiry: ['expiry', 'expiry date', 'expiry month'], mrp: ['mrp', 'price'],
        purchaseRate: ['purchase rate', 'purchase', 'ptr'], retailRate: ['retail rate', 'retail'],
        wholesaleRate: ['wholesale rate', 'wholesale'], schedule: ['schedule'], minStock: ['min stock', 'minimum stock'],
        reorderLevel: ['reorder level', 'reorder'], rack: ['rack', 'rack / shelf', 'shelf'],
        genericGroup: ['salt group', 'generic/composition group', 'generic group', 'group'], status: ['status'],
      };
      const BULK_TPL_HEAD = ['name', 'generic', 'composition', 'brand', 'category', 'manufacturer', 'hsn', 'gst', 'form', 'pack qty', 'barcode', 'batch no', 'opening qty', 'expiry', 'mrp', 'purchase rate', 'retail rate', 'wholesale rate', 'schedule', 'min stock', 'reorder level', 'rack', 'salt group', 'status'];
      const BULK_TPL_ROWS = [
        ['Dolo 650mg', 'Paracetamol 650mg', 'Paracetamol 650mg', 'Micro Labs', 'Pain & Fever', 'Micro Labs Ltd', '30049099', '12', 'Tablet', '15', '', '', '40', '12-2027', '42.40', '32', '38', '', 'OTC', '20', '10', '', 'Paracetamol 650mg', 'Active'],
        ['Augmentin 625mg', 'Amoxicillin 500mg + Clavulanic Acid 125mg', 'Amoxicillin 500mg + Clavulanic Acid 125mg', 'GSK', 'Antibiotic', 'GSK', '30041090', '12', 'Tablet', '10', '', '', '20', '09-2027', '223.17', '181', '204', '', 'H', '10', '5', 'A-3', 'Amoxicillin 500mg + Clavulanate 125mg', 'Active'],
      ];
      const bulkCsvCell = (v) => { const s = String(v ?? ''); return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; };
      const bulkText = (rows) => rows.map((r) => r.map(bulkCsvCell).join(',')).join('\r\n');

      function bulkCsvParse(text) { // quotes + embedded commas/newlines aware
        const rows = []; let row = [], cell = '', inQ = false; const t = String(text || '').replace(/^﻿/, '');
        for (let i = 0; i <= t.length; i++) {
          const c = t[i];
          if (inQ) {
            if (c === '"') { if (t[i + 1] === '"') { cell += '"'; i++; } else inQ = false; }
            else if (c !== undefined) cell += c;
            continue;
          }
          if (c === '"') { inQ = true; continue; }
          if (c === ',') { row.push(cell); cell = ''; continue; }
          if (c === '\n' || c === '\r' || c === undefined) {
            if (c === '\r' && t[i + 1] === '\n') i++;
            row.push(cell); cell = '';
            if (row.some((x) => x.trim() !== '')) rows.push(row);
            row = []; if (c === undefined) break; continue;
          }
          cell += c;
        }
        return rows;
      }
      function bulkHeaderMap(header) {
        const norm = (h) => String(h || '').trim().toLowerCase().replace(/[₹()"/]/g, ' ').replace(/\s+/g, ' ');
        const h2 = header.map(norm); const map = {};
        Object.entries(BULK_ALIASES).forEach(([key, aliases]) => {
          for (const al of aliases) { const i = h2.indexOf(norm(al)); if (i >= 0) { map[key] = i; break; } }
        });
        return map;
      }
      const bulkNum = (s) => { const v = String(s ?? '').replace(/[₹,%\s]/g, ''); if (v === '') return ''; const n = parseFloat(v); return isNaN(n) ? '' : n; };
      const bulkForm = (s) => {
        const v = String(s || '').trim().toLowerCase();
        if (!v) return '';
        const keys = Object.keys(FORM_PACK);
        return keys.find((k) => k.toLowerCase() === v) || keys.find((k) => k.toLowerCase() === v.replace(/s$/, '')) || '';
      };
      function bulkSchedule(s) {
        const v = String(s || '').trim().toUpperCase().replace(/^SCHEDULE\s+/, '');
        if (!v) return 'OTC';
        if (v === 'NDPS') return 'X';
        return SCHEDULES[v] ? v : '';
      }
      function bulkExpiry(text) { // 12-2027 · 12-27 · 2027-09-05 · Dec 2027 · Dec-2028 · Dec-27
        const t = String(text || '').trim(); if (!t) return '';
        const yy = (y) => (String(y).length === 2 ? (Number(y) >= 70 ? '19' : '20') + y : String(y)); // YY→YYYY pivot
        let m = /^(\d{1,2})[-/](\d{2,4})$/.exec(t);                       // MM-YYYY · MM/YYYY · MM-YY
        if (m) return `${yy(m[2])}-${m[1].padStart(2, '0')}-01`;
        m = /^(\d{4})-(\d{2})/.exec(t);                                    // YYYY-MM(-DD)
        if (m) return `${m[1]}-${m[2]}-01`;
        const nm = /^([A-Za-z]+)\.?[-\s,/.]+(\d{2,4})$/.exec(t);           // "Dec 2027" · "Dec,2027" · "Dec-2027" · "Dec-27"
        if (nm) {
          const mi = EXPIRY_MONTHS.findIndex((x) => x.toLowerCase() === nm[1].slice(0, 3).toLowerCase());
          if (mi >= 0) return `${yy(nm[2])}-${String(mi + 1).padStart(2, '0')}-01`;
        }
        return '';
      }
      function bulkValidate() {
        const btn = $('#mmBulkValidate');
        const origHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Checking…';
        btn.disabled = true;
        try {
          let text = $('#mmBulkCsv').value;
          const firstLn = (text.split(/\r?\n/, 1)[0] || '');
          if (!firstLn.includes(',') && firstLn.includes('\t')) text = text.replace(/\t/g, ','); // Excel paste = TSV
          $('#mmBulkCsv').value = text;
          bulkRows = [];
          const prev = $('#mmBulkSummary'); const box = $('#mmBulkPreviewBox');
          $('#mmBulkImport').disabled = true;
          const rows = bulkCsvParse(text);
        if (!rows.length) { prev.innerHTML = ''; box.innerHTML = ''; MF.toast('Nothing to check — paste a CSV or upload a file first.', 'warn', 'Bulk Add'); return; }
        const idx = bulkHeaderMap(rows.shift() || []);
        if (idx.name == null || idx.form == null || idx.mrp == null) {
          prev.innerHTML = '';
          box.innerHTML = `<div class="mm-blk-err p-2">Could not find the required headers <code>name</code>, <code>form</code> and <code>mrp</code> in the first row. Use the template column names.</div>`;
          return;
        }
        const get = (r, k) => (idx[k] == null ? '' : String(r[idx[k]] ?? '').trim());
        const existing = new Set((D.medicines || []).map((m) => String(m.name || '').trim().toLowerCase()).filter(Boolean));
        const firstLine = new Map();
        rows.forEach((r, i) => {
          const name = get(r, 'name'); const form = bulkForm(get(r, 'form')); const mrp = bulkNum(get(r, 'mrp'));
          const schIn = get(r, 'schedule'); const sched = bulkSchedule(schIn);
          const expIn = get(r, 'expiry'); const expiry = bulkExpiry(expIn);
          const errs = [];
          if (!name) errs.push('name missing');
          if (!form) errs.push(`unknown form "${get(r, 'form')}"`);
          if (mrp === '' || mrp <= 0) errs.push('MRP missing/invalid');
          if (schIn && !sched) errs.push(`unknown schedule "${schIn}"`);
          if (expIn && !expiry) errs.push(`bad expiry "${expIn}"`);
          const key = name.toLowerCase();
          if (name && existing.has(key)) errs.push('already in the master — skipped');
          else if (name && firstLine.has(key)) errs.push(`duplicate of row ${firstLine.get(key)} — first row wins; add its second batch from New Purchase`);
          else if (name) firstLine.set(key, i + 2);
          const spec = packSpec(form) || { unit: '', sub: '', whole: false }; // form may be invalid — row keeps its error and stays importable-safe
          // Salt group drives substitutes. When empty, fall back to the generic name so
          // same-generic medicines still match each other without extra work.
          const saltGroup = get(r, 'genericGroup') || get(r, 'generic');
          bulkRows.push({
            no: i + 2, errs, result: '',
            payload: {
              name, generic: get(r, 'generic'), brandRef: get(r, 'brandRef'), composition: get(r, 'composition'),
              category: get(r, 'category'), manufacturer: get(r, 'manufacturer'), hsn: get(r, 'hsn'),
              genericGroup: saltGroup, substitutes: saltGroup, barcode: get(r, 'barcode'),
              form, unit: spec.unit, packSize: '', gst: bulkNum(get(r, 'gst')), schedule: sched || 'OTC',
              packQty: (bulkNum(get(r, 'packQty')) || 1), subUnit: spec.sub, allowLoose: false,
              batchNo: get(r, 'batchNo'), openingQty: (() => { const q = bulkNum(get(r, 'openingQty')); return q === '' ? '' : Math.max(0, q); })(),
              boxQty: '', boxUnit: '', expiry,
              mrp, retailRate: (() => { const n = bulkNum(get(r, 'retailRate')); return n === '' ? mrp : n; })(),
              purchaseRate: bulkNum(get(r, 'purchaseRate')), defaultDiscount: '', discountType: 'percent',
              wholesaleRate: bulkNum(get(r, 'wholesaleRate')), minStock: bulkNum(get(r, 'minStock')),
              reorderLevel: bulkNum(get(r, 'reorderLevel')), rack: get(r, 'rack'),
              rxRequired: !!(SCHEDULES[sched || 'OTC'] && SCHEDULES[sched || 'OTC'].rx),
              expiryAlertDays: '', status: /^inactive$/i.test(get(r, 'status')) ? 'Inactive' : 'Active',
            },
          });
        });
          bulkPaint();
        } catch (err) {
          console.error('Bulk check failed:', err);
          $('#mmBulkSummary').innerHTML = '';
          $('#mmBulkPreviewBox').innerHTML = `<div class="mm-blk-err p-2">Could not read the CSV: ${MF.esc(err.message || String(err))}</div>`;
        } finally {
          btn.innerHTML = origHtml; btn.disabled = false;
        }
      }
      function bulkPaint() {
        const prev = $('#mmBulkSummary'); const box = $('#mmBulkPreviewBox');
        if (!bulkRows.length) { prev.innerHTML = ''; box.innerHTML = ''; return; }
        const ready = bulkRows.filter((x) => !x.errs.length && x.result !== 'Imported');
        const fail = bulkRows.filter((x) => x.errs.length);
        $('#mmBulkImport').disabled = !ready.length;
        prev.innerHTML = `<div class="mm-blk-summary">${ready.length} row(s) ready to import · ${fail.length} row(s) need attention${bulkRows.length > 1000 ? ` · showing first 1000 of ${bulkRows.length} (all ${ready.length} ready rows will import)` : ''}</div>`;
        const shown = bulkRows.slice(0, 1000);
        box.innerHTML = `<div class="mm-bulk-preview"><table class="table table-sm table-hover mb-0">
          <thead><tr><th>#</th><th>Name</th><th>Form</th><th>Batch</th><th>Expiry</th><th class="text-end">Qty</th><th class="text-end">MRP</th><th>GST</th><th>Sched</th><th>Result</th></tr></thead>
          <tbody>${shown.map((x) => `<tr>
            <td class="text-2">${x.no}</td>
            <td title="${MF.esc(x.payload.name)}">${MF.esc((x.payload.name || '—').slice(0, 26))}</td>
            <td>${MF.esc(x.payload.form || '')}</td>
            <td>${MF.esc(x.payload.batchNo || '—')}</td>
            <td>${MF.esc(expiryToField(x.payload.expiry) || '—')}</td>
            <td class="text-end num">${x.payload.openingQty !== '' ? MF.num(x.payload.openingQty) : '—'}</td>
            <td class="text-end num">${x.payload.mrp !== '' ? MF.fmt(x.payload.mrp) : '—'}</td>
            <td>${x.payload.gst !== '' ? x.payload.gst + '%' : '—'}</td>
            <td>${MF.esc(x.payload.schedule)}</td>
            <td class="${x.errs.length ? 'mm-blk-err' : (x.result === 'Imported' ? 'mm-blk-ok' : '')}">${x.errs.length ? MF.esc((x.result || x.errs.join('; ')).slice(0, 60)) : (x.result || 'Ready')}</td>
          </tr>`).join('')}</tbody></table></div>`;
      }
      async function bulkImport() {
        const todo = bulkRows.filter((x) => !x.errs.length && x.result !== 'Imported');
        if (!todo.length) return;
        if (!MF.Api.live) { MF.toast('Bulk import needs a server connection.', 'err', 'Bulk Add'); return; }
        const bImport = $('#mmBulkImport'), bCheck = $('#mmBulkValidate'), prog = $('#mmBulkProgress');
        bImport.disabled = true; bCheck.disabled = true; prog.hidden = false;
        const bar = $('#mmBulkBarFill'), txt = $('#mmBulkProgressTxt');
        let done = 0, ok = 0;
        for (const row of todo) {
          try { await MF.Api.post('medicines.php', row.payload); row.result = 'Imported'; ok++; }
          catch (e) { row.result = e.message || 'failed'; row.errs = [row.result]; }
          done++; bar.style.width = ((done / todo.length) * 100).toFixed(1) + '%'; txt.textContent = `${done} / ${todo.length}`;
          bulkPaint();
        }
        prog.hidden = true; bCheck.disabled = false;
        await MF.rehydrate(); render();
        bulkPaint();
        MF.toast(`${ok} medicine(s) added to the master${ok < todo.length ? ` · ${todo.length - ok} failed (see table)` : ''}`, ok < todo.length ? 'warn' : 'success', 'Bulk Add');
      }
      function bulkReset() {
        $('#mmBulkCsv').value = bulkText([BULK_TPL_HEAD, ...BULK_TPL_ROWS]);
        bulkRows = []; $('#mmBulkImport').disabled = true; bulkPaint(); $('#mmBulkPreviewBox').innerHTML = '';
      }
      /* ============ Bulk price update ============ */
      const pbModal = () => bootstrap.Modal.getOrCreateInstance($('#mmPriceBulkModal'));
      const pbCalc = (v, pct, mode) => {
        const x = Number(v || 0) * (1 + pct / 100);
        if (mode === '1') return Math.round(x);
        if (mode === '0.5') return Math.round(x * 2) / 2;
        return Math.round(x * 100) / 100;
      };
      const pbDerive = (mrp, pct, mode) => {
        const x = Number(mrp || 0) * (pct / 100);
        if (mode === '1') return Math.round(x);
        if (mode === '0.5') return Math.round(x * 2) / 2;
        return Math.round(x * 100) / 100;
      };
      const pbIsDerive = () => ($('#pbAction') && $('#pbAction').value === 'derive');
      function pbSyncMode() {
        const d = pbIsDerive();
        $('#pbPctLabel').textContent = d ? '% of MRP' : 'Change (%)';
        $('#pbQuick').style.display = d ? 'none' : '';
        $('#pbMrp').disabled = d; $('#pbRetail').disabled = d; $('#pbWhole').disabled = true;
        if (d) { $('#pbMrp').checked = false; $('#pbRetail').checked = false; $('#pbWhole').checked = true; }
      }
      function pbMatches() {
        const cat = $('#pbCat').value, mfr = $('#pbMfr').value;
        return D.medicines.filter((m) => m.status !== 'inactive' && (!cat || m.category === cat) && (!mfr || m.manufacturer === mfr));
      }
      function pbTargets() {
        if (pbIsDerive()) return { mrp: false, retail: false, wholesale: true };
        const t = { mrp: $('#pbMrp').checked, retail: $('#pbRetail').checked, wholesale: $('#pbWhole').checked };
        return t.mrp || t.retail || t.wholesale ? t : null;
      }
      function pbPct() {
        const v = parseFloat($('#pbPct').value);
        return Number.isFinite(v) ? v : null;
      }
      function pbPreview() {
        const pct = pbPct(), targets = pbTargets();
        const box = $('#pbPreview');
        const derive = pbIsDerive();
        if (pct === null || !pct) { box.hidden = false; box.innerHTML = derive
          ? '<i class="bi bi-exclamation-circle me-1"></i>Enter % of MRP (e.g. 75 for wholesale at 75% of MRP).'
          : '<i class="bi bi-exclamation-circle me-1"></i>Enter a non-zero percentage (e.g. +5 or -5).'; return; }
        if (derive && (pct < 1 || pct > 100)) { box.hidden = false; box.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>% of MRP must sit between 1 and 100.'; return; }
        if (!derive && pct <= -100) { box.hidden = false; box.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>A decrease of 100% or more would zero every price — refused.'; return; }
        if (!targets) { box.hidden = false; box.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>Tick at least one price column (MRP / Retail / Wholesale).'; return; }
        const list = pbMatches();
        if (!list.length) { box.hidden = false; box.innerHTML = '<i class="bi bi-info-circle me-1"></i>No active medicines match this selection.'; return; }
        const mode = $('#pbRound').value;
        const col = derive ? 'wholesaleRate' : (targets.mrp ? 'mrp' : 'retailRate');
        const lines = list.slice(0, 5).map((m) => {
          const cur = Number(m[col] ?? m.mrp ?? 0);
          const next = derive ? pbDerive(m.mrp, pct, mode) : pbCalc(cur, pct, mode);
          return `<div style="display:flex;justify-content:space-between;gap:10px"><span>${MF.esc(m.name)}</span><span class="num">${MF.fmt(cur, 2)} → <strong>${MF.fmt(next, 2)}</strong></span></div>`;
        }).join('');
        box.hidden = false;
        const head = derive
          ? `<div style="font-weight:700;margin-bottom:4px"><i class="bi bi-check2-circle me-1" style="color:#0F766E"></i>${list.length} medicine(s) — wholesale rate = ${pct}% of MRP</div>`
          : `<div style="font-weight:700;margin-bottom:4px"><i class="bi bi-check2-circle me-1" style="color:#0F766E"></i>${list.length} medicine(s) will be updated${pct > 0 ? ' (increase ' + pct + '%)' : ' (decrease ' + Math.abs(pct) + '%)'}</div>`;
        box.innerHTML = head + lines +
          (list.length > 5 ? `<div class="text-2" style="font-size:.72rem;margin-top:3px">+ ${list.length - 5} more…</div>` : '');
        pbList = list;
        $('#pbApplyBtn').disabled = false;
      }
      let pbList = [];
      async function pbApply() {
        const pct = pbPct(), targets = pbTargets();
        if (!pbList.length || pct === null || !targets) return;
        if (targets.retail && !targets.mrp) MF.toast('Retail-only change — prices may cross MRP; the form will flag any that go above.', 'warn', 'Bulk price');
        const btn = $('#pbApplyBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1 spin"></i>Updating…';
        try {
          const res = await MF.Api.post('price-bulk-update.php', {
            ids: pbList.map((m) => m.id), pct, round: $('#pbRound').value, targets,
            action: pbIsDerive() ? 'derive' : 'adjust',
          });
          const n = (res.data && res.data.matched) || pbList.length;
          pbModal().hide();
          await MF.rehydrate(); render();
          MF.toast(pbIsDerive()
            ? `Wholesale rate set at ${pct}% of MRP on ${n} medicine(s)`
            : `${n} medicine(s) updated (${pct > 0 ? '+' : ''}${pct}%)`, 'success', 'Bulk price');
        } catch (e) {
          MF.toast(e.message || 'Bulk price update failed.', 'err', 'Bulk price');
        } finally {
          btn.disabled = true;
          btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Apply update';
        }
      }
      $('#mmPriceBulkBtn').addEventListener('click', () => {
        $('#pbCat').innerHTML = '<option value="">All categories</option>' + D.categories.map((c) => `<option>${MF.esc(c)}</option>`).join('');
        $('#pbMfr').innerHTML = '<option value="">All manufacturers</option>' + D.manufacturers.map((m) => `<option>${MF.esc(m)}</option>`).join('');
        $('#pbPreview').hidden = true; pbList = [];
        $('#pbApplyBtn').disabled = true;
        pbSyncMode();
        pbModal().show();
      });
      $('#pbPreviewBtn').addEventListener('click', pbPreview);
      document.querySelectorAll('.pb-quick').forEach((b) => b.addEventListener('click', () => { $('#pbPct').value = b.dataset.p; pbPreview(); }));
      ['pbCat', 'pbMfr', 'pbPct', 'pbRound', 'pbMrp', 'pbRetail', 'pbWhole'].forEach((id) =>
        $('#' + id).addEventListener('input', () => { $('#pbApplyBtn').disabled = true; $('#pbPreview').hidden = true; pbList = []; }));
      ['pbCat', 'pbMfr', 'pbRound', 'pbMrp', 'pbRetail', 'pbWhole'].forEach((id) =>
        $('#' + id).addEventListener('change', () => { $('#pbApplyBtn').disabled = true; $('#pbPreview').hidden = true; pbList = []; }));
      $('#pbAction').addEventListener('change', () => {
        pbSyncMode();
        $('#pbApplyBtn').disabled = true; $('#pbPreview').hidden = true; pbList = [];
      });
      $('#pbApplyBtn').addEventListener('click', pbApply);

      $('#mmBulkAdd').addEventListener('click', () => {
        if (!$('#mmBulkCsv').value.trim()) bulkReset();
        bootstrap.Modal.getOrCreateInstance($('#mmBulkModal')).show();
      });
      $('#mmBulkTpl').addEventListener('click', () => MF.exportCSV('medicines-bulk-template.csv', BULK_TPL_HEAD, BULK_TPL_ROWS));
      $('#mmBulkClear').addEventListener('click', bulkReset);
      $('#mmBulkValidate').addEventListener('click', bulkValidate);
      $('#mmBulkImport').addEventListener('click', bulkImport);
      $('#mmBulkFile').addEventListener('change', (e) => {
        const f = e.target.files && e.target.files[0];
        if (!f) return;
        const rd = new FileReader();
        rd.onload = () => { $('#mmBulkCsv').value = String(rd.result || ''); bulkValidate(); };
        rd.readAsText(f);
        e.target.value = '';
      });

      /* Premium select menus. Native <option> popups ignore our CSS, so the list is ours
         and the closed field still uses the existing form-select / mm-input styles. */
      const selectMenu = document.createElement('div');
      selectMenu.className = 'mm-select-menu';
      selectMenu.setAttribute('role', 'listbox');
      document.body.appendChild(selectMenu);
      let openSelect = null;
      let hotIndex = -1;
      let typeKey = ''; // last letter used by type-ahead — repeats cycle to the next match

      function selectAnchor(sel) {
        return sel.closest('.mm-input') || sel.closest('.mm-select-plain') || sel;
      }
      function closeSelectMenu() {
        selectMenu.classList.remove('show');
        selectMenu.innerHTML = '';
        document.querySelectorAll('.mm-input.is-open, .mm-select-plain.is-open').forEach((el) => el.classList.remove('is-open'));
        openSelect = null;
        hotIndex = -1;
        typeKey = '';
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
        // Search for real picklists (Category, Manufacturer, schedule, batch…); skip tiny fixed lists (GST)
        // and lists marked data-no-search (e.g. Form — type-ahead letters handle it)
        const search = opts.length > 4 && !openSelect.hasAttribute('data-no-search')
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
        typeKey = '';
        selectAnchor(sel).classList.add('is-open');
        paintSelectMenu('');
        placeSelectMenu(sel);
        // With a search box the input must keep focus (only option-focused menus focus the current option)
        if (!selectMenu.querySelector('.mm-select-search input')) selectMenu.querySelector('.is-on')?.focus();
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
        // Type-ahead: a printable letter jumps the highlight to options starting with it —
        // a DIFFERENT letter picks the first match; the SAME letter again cycles to the next match.
        // (Inside the search box, typing keeps filtering as before.)
        if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey && /\S/.test(e.key)) {
          const ae = document.activeElement;
          if (ae && ae.tagName === 'INPUT' && selectMenu.contains(ae)) return;
          const c = e.key.toLowerCase();
          const matches = buttons
            .map((b, n) => ((b.textContent || '').trim().toLowerCase().startsWith(c) ? n : -1))
            .filter((n) => n >= 0);
          if (matches.length) {
            e.preventDefault();
            let pos = 0;
            if (c === typeKey) {
              const cur = matches.indexOf(hotIndex);
              pos = cur >= 0 ? (cur + 1) % matches.length : 0;
            }
            typeKey = c;
            hotIndex = matches[pos];
            buttons.forEach((b, n) => b.classList.toggle('is-hot', n === hotIndex));
            revealOption(buttons[hotIndex]);
          }
          return;
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
<script>(function(){function c(){var b=a.contentDocument||(a.contentWindow&&a.contentWindow.document);if(b){var d=b.createElement('script');d.innerHTML="window.__CF$cv$params={r:'a4621614de127937',t:'MTc5MTI2MjY1Ng=='};var a=document.createElement('script');a.src='/cdn-cgi/challenge-platform/scripts/jsd/main.js';document.getElementsByTagName('head')[0].appendChild(a);";b.getElementsByTagName('head')[0].appendChild(d)}}if(document.body){var a=document.createElement('iframe');a.height=1;a.width=1;a.style.position='absolute';a.style.top=0;a.style.left=0;a.style.border='none';a.style.visibility='hidden';document.body.appendChild(a);if('loading'!==document.readyState)c();else if(window.addEventListener)document.addEventListener('DOMContentLoaded',c);else{var e=document.onreadystatechange||function(){};document.onreadystatechange=function(b){e(b);'loading'!==document.readyState&&(document.onreadystatechange=e,c())}}}})();</script></body>
</html>
') === 'add') openForm(null);
      });
    })();
  </script>
</body>
</html>
