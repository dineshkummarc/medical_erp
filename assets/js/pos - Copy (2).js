/* ==========================================================================
   MediFlow ERP — Retail POS Engine (Stage 1)
   Search → FEFO batch pick → cart → GST-inclusive extraction → payment.
   ========================================================================== */

(function () {
  const MF = window.MF;
  const D = window.MF_DATA;

  const state = {
    cart: [],            // { medId, batchId, qty, rate, mrp, discPct }
    heldBills: [],
    holdSeq: 1,
    payment: 'cash',
    split: { cash: 0, upi: 0 },
    tender: null,        // { cashReceived, changeReturned } for the open bill
    tenderOpen: false,
    upiSig: '',
    upiRef: '',
    pick: 'quick',       // quick | recent | subs — left-column browse tabs
    subSeed: null,       // medicine id the Substitutes tab is focused on (via "Order / substitute")
    selIdx: 0,           // arrow-key selection index over the rendered result cards
    selQuery: '',        // last search text — a new search resets the selection to card 1
    printFmt: 'a4',      // 'a4' | 'thermal' — last chosen invoice print format
    discMode: 'percent', // 'percent' (%) | 'flat' (₹) — bill-level discount mode
    rolePrivileged: true, // whoami.php narrows this to false for staff/cashier accounts
    lossApproved: null,   // set when the cashier consciously confirmed a below-cost bill
    orderPad: [],        // [{ medId, qty }] — re-order list handed off to New Purchase
    recent: [],          // medicine ids, newest first
    pendingQty: 0,       // qty-first entry: "3*amox" parks 3 until the next add
    cartIdx: -1,         // keyboard cart editing — active line (-1 = none)
    lastBill: null       // snapshot of the last completed bill for instant reprints
  };

  const $ = (s) => document.querySelector(s);
  let actionSheet = null, orderModal = null;   // Order/substitute chooser + order pad modals

  /* Button styles that need :hover / :active (can't be done with inline styles) */
  if (!document.getElementById('pos-btn-styles')) {
    const st = document.createElement('style');
    st.id = 'pos-btn-styles';
    st.textContent = `
      .pos-held {
        position:relative; border:1px solid #b7ddd4; background:#fff; color:var(--mf-primary-dark);
        border-radius:999px; font-weight:700; font-size:.8rem; padding:6px 12px;
        display:inline-flex; align-items:center; gap:6px; cursor:pointer;
        transition:background .15s ease, color .15s ease, border-color .15s ease, transform .15s ease, box-shadow .15s ease;
      }
      .pos-held:hover { background:var(--mf-primary-soft); border-color:var(--mf-primary); color:var(--mf-primary-dark); transform:translateY(-1px); box-shadow:0 4px 12px rgba(23,107,91,.16); }
      .pos-held:active { transform:translateY(0); box-shadow:none; }
      .pos-rx-verify.show { display:flex; }
      .pos-switch:hover span { background:#c4b5fd; }
      .pos-switch:hover input:checked + span { background:#6d28d9; }
      .pos-loose-add {
        background:#e7edf6; color:#16325c; border:1px solid transparent; border-radius:8px;
        padding:6px 14px; font-weight:500; display:inline-flex; align-items:center; gap:4px;
        transition:background-color .15s ease, color .15s ease, transform .15s ease, box-shadow .15s ease;
      }
      .pos-loose-add:hover {
        background:#16325c; color:#fff;
        transform:translateY(-1px); box-shadow:0 4px 10px rgba(22,50,92,.25);
      }
      .pos-loose-add:active { transform:translateY(0); box-shadow:none; background:#0f2444; color:#fff; }
      .pos-loose-add:focus-visible { outline:2px solid #16325c; outline-offset:2px; }
      /* "% off" chip on the quick card — premium indigo pill beside the retail price */
      .pos-off {
        display:inline-block; margin-left:.35rem; padding:.06em .42rem .12em; border-radius:50rem; vertical-align:middle;
        background:#EEF0FF; color:#4F46E5; font-size:.72rem; font-weight:800;
        letter-spacing:.01em; line-height:1.25; box-shadow:inset 0 0 0 1px rgba(79,70,229,.18);
      }
      /* Search clear control — occupies the right side of the search input, visible only with text */
      .pos-search-wrap { position:relative; flex:1; min-width:0; }
      .pos-search-wrap .form-control { border-top-left-radius:0; border-bottom-left-radius:0; padding-right:2rem; }
      .pos-clear {
        position:absolute; right:6px; top:50%; transform:translateY(-50%);
        width:22px; height:22px; border-radius:50%; border:0; display:grid; place-items:center; padding:0;
        background:#E9EDF1; color:#5f6b7a; font-size:.62rem; line-height:1; transition:background .15s, color .15s;
      }
      .pos-clear:hover { background:#DCE4E9; color:#172026; }
      .pos-clear:focus-visible { outline:2px solid #2E8B78; outline-offset:2px; }

      /* ================= Quick Refill ================= */
      /* Button beside the search bar */
      .pos-refill-btn {
        flex:0 0 auto; display:inline-flex; align-items:center; gap:.42rem; white-space:nowrap;
        border:1px solid #E5E7EB; background:#fff; color:#176B5B; border-radius:.5rem;
        font-size:.78rem; font-weight:650; padding:.45rem .75rem; letter-spacing:.005em;
        transition:all .16s ease; box-shadow:0 1px 2px rgba(16,24,40,.05);
      }
      .pos-refill-btn i { font-size:.95rem; }
      .pos-refill-btn:hover { background:#E6F1EE; border-color:#8FCDC0; color:#0F4D42; transform:translateY(-1px); box-shadow:0 3px 8px rgba(23,107,91,.18); }
      .pos-refill-btn:active { transform:translateY(0); }
      @media (max-width: 575.98px) { .pos-refill-btn span { display:none; } .pos-refill-btn { padding:.45rem .6rem; } }

      /* Overlay + slide-in panel */
      .pos-refill-overlay {
        position:fixed; inset:0; background:rgba(10,22,18,.42); z-index:1060;
        opacity:0; transition:opacity .22s ease; backdrop-filter:blur(2px);
      }
      .pos-refill-overlay.show { opacity:1; }
      .pos-refill-panel {
        position:fixed; top:0; right:0; bottom:0; width:min(408px, 96vw); z-index:1070;
        background:#fff; border-left:1px solid #E7ECF1; box-shadow:-24px 0 60px rgba(15,40,32,.22);
        display:flex; flex-direction:column; transform:translateX(105%);
        transition:transform .26s cubic-bezier(.32,.72,.24,1);
      }
      .pos-refill-panel.show { transform:translateX(0); }
      .prf-head {
        display:flex; align-items:center; gap:.7rem; padding:.9rem 1rem;
        border-bottom:1px solid #EEF1F4; background:linear-gradient(180deg,#FBFCFD,#fff);
      }
      .prf-avatar {
        width:42px; height:42px; flex:0 0 42px; border-radius:12px; display:grid; place-items:center;
        background:#E7F5EE; color:#146C43; font-weight:800; font-size:.8rem; letter-spacing:.02em;
      }
      .prf-who { flex:1; min-width:0; display:flex; flex-direction:column; gap:1px; }
      .prf-who strong { font-size:.92rem; letter-spacing:-.005em; color:#172026; }
      .prf-who span { font-size:.72rem; color:#6B7280; font-variant-numeric:tabular-nums; }
      .prf-close {
        width:30px; height:30px; border-radius:8px; border:0; background:#fff;
        color:#6B7280; display:grid; place-items:center; font-size:.8rem;
      }
      .prf-close:hover { background:#F1F3F5; color:#172026; }
      .prf-body { flex:1; overflow-y:auto; padding:1rem; }
      .prf-search {
        flex:0 0 auto; border:1px solid #176B5B; background:#176B5B; color:#fff; border-radius:.5rem;
        font-size:.78rem; font-weight:650; padding:.45rem .8rem; display:inline-flex; align-items:center; gap:.35rem;
      }
      .prf-search:hover { background:#0F4D42; border-color:#0F4D42; }
      .prf-msg { line-height:1.45; }
      .prf-msg.is-err { color:#B4352F; }
      .prf-msg.is-info { color:#176B5B; font-weight:600; }

      .prf-facts { display:grid; grid-template-columns:1fr 1fr; gap:.6rem; margin-bottom:.2rem; }
      .prf-chip {
        border:1px solid #EDF1F4; border-radius:.6rem; background:#F8FAFB; padding:.55rem .7rem; min-width:0;
      }
      .prf-k { display:block; font-size:.6rem; font-weight:700; letter-spacing:.07em; text-transform:uppercase; color:#8A94A0; margin-bottom:2px; }
      .prf-chip b { font-size:.78rem; color:#172026; font-weight:700; }
      .prf-sec-title { font-size:.84rem; margin:.85rem 0 .4rem; }
      .prf-history {
        border:0; background:transparent; padding:0; font-size:.72rem; font-weight:600; color:#5a6675;
        text-decoration:underline; text-underline-offset:2px;
      }
      .prf-history:hover { color:#176B5B; }
      .prf-table { border:1px solid #EDF1F4; border-radius:.65rem; overflow:hidden; }
      .prf-thead, .prf-row {
        display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:.6rem;
      }
      .prf-thead {
        padding:.45rem .75rem; background:#FAFBFC; border-bottom:1px solid #EDF1F4;
        font-size:.6rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#8A94A0;
      }
      .prf-thead span:last-child { font-size:.62rem; }
      .prf-row { padding:.55rem .75rem; background:#fff; transition:background .12s; cursor:pointer; }
      .prf-row + .prf-row { border-top:1px solid #F2F5F7; }
      .prf-row:hover { background:#FAFBFC; }
      .prf-row .prf-med { display:flex; align-items:center; gap:.6rem; min-width:0; font-size:.8rem; font-weight:600; color:#172026; }
      .prf-row .prf-med .gone { font-size:.66rem; color:#b02a37; font-weight:600; margin-left:4px; }
      .prf-row .prf-qty { font-size:.72rem; color:#5f6b7a; font-weight:500; text-align:right; white-space:nowrap; }
      .prf-check { width:1.05rem; height:1.05rem; accent-color:#176B5B; cursor:pointer; flex:0 0 auto; }
      .prf-check:disabled { accent-color:#cbd3dc; cursor:not-allowed; }
      .prf-row.is-off { opacity:.55; }
      .prf-foot { padding:.85rem 1rem; border-top:1px solid #EEF1F4; background:#fff; }
      .prf-refill {
        width:100%; border:0; border-radius:.6rem; background:#0B8A5F; color:#fff; font-size:.86rem; font-weight:700;
        padding:.7rem 1rem; display:inline-flex; align-items:center; justify-content:center; gap:.5rem;
        transition:background .15s, transform .15s, box-shadow .15s; box-shadow:0 6px 18px rgba(11,138,95,.28);
      }
      .prf-refill:hover:not(:disabled) { background:#096f4c; transform:translateY(-1px); box-shadow:0 8px 22px rgba(11,138,95,.34); }
      .prf-refill:active:not(:disabled) { transform:translateY(0); }
      .prf-refill:disabled { background:#9fb3ab; box-shadow:none; cursor:not-allowed; }
      .prf-refill i { font-size:1rem; }

      /* Full history list */
      .prf-visit { border:1px solid #EDF1F4; border-radius:.65rem; background:#fff; overflow:hidden; }
      .prf-visit + .prf-visit { margin-top:.5rem; }
      .prf-visit-head {
        width:100%; display:grid; grid-template-columns:minmax(0,1fr) auto auto; align-items:center; gap:.6rem;
        padding:.55rem .75rem; background:#fff; border:0; cursor:pointer; text-align:left;
      }
      .prf-visit-head:hover { background:#F8FAFB; }
      .prf-visit-head .v-inv { font-size:.78rem; font-weight:700; color:#172026; }
      .prf-visit-head .v-date { font-size:.64rem; color:#8A94A0; margin-top:1px; }
      .prf-visit-head .v-amt { font-size:.8rem; font-weight:700; color:#0F4D42; font-variant-numeric:tabular-nums; }
      .prf-visit-head .v-caret { color:#8A94A0; font-size:.8rem; transition:transform .2s; }
      .prf-visit.open .v-caret { transform:rotate(180deg); color:#176B5B; }
      .prf-visit-items { display:none; border-top:1px solid #F2F5F7; background:#FAFBFC; padding:.45rem .75rem .6rem; }
      .prf-visit.open .prf-visit-items { display:block; }
      .prf-visit-line { display:flex; justify-content:space-between; gap:.6rem; font-size:.74rem; padding:.18rem 0; }
      .prf-visit-line span:first-child { color:#374151; font-weight:600; }
      .prf-visit-line span:last-child { color:#8A94A0; white-space:nowrap; }

      /* Wholesale-priced line chip + loss-gate styles */
      .pos-ws {
        display:inline-block; margin-left:4px; padding:0 5px; border-radius:4px;
        font-size:.58rem; font-weight:800; letter-spacing:.04em;
        background:#E0F2FE; color:#0369A1; border:1px solid #BAE6FD;
      }

      /* Order / substitute (out of stock) */
      .pos-order-sub {
        border:1.5px solid #8b5cf6; border-radius:10px; background:#f5f3ff; color:#6d28d9;
        padding:8px 12px; display:inline-flex; flex-direction:row; align-items:center; justify-content:center;
        gap:6px; white-space:nowrap;
        transition:background-color .15s ease, color .15s ease, border-color .15s ease, transform .15s ease, box-shadow .15s ease;
      }
      .pos-order-sub i { font-size:1rem; transition:transform .35s ease; }
      .pos-order-sub:hover {
        background:#7c3aed; border-color:#7c3aed; color:#fff;
        transform:translateY(-1px); box-shadow:0 4px 12px rgba(124,58,237,.30);
      }
      .pos-order-sub:hover i { transform:rotate(180deg); }
      .pos-order-sub:active { transform:translateY(0); box-shadow:none; background:#6d28d9; border-color:#6d28d9; color:#fff; }
      .pos-order-sub:focus-visible { outline:2px solid #7c3aed; outline-offset:2px; }

      /* Stock status badge (next to MRP) */
      .pos-stock-badge {
        display:inline-flex; align-items:center; margin-left:8px; padding:1px 8px;
        font-size:.68rem; font-weight:600; line-height:1.5; border-radius:3px; vertical-align:middle;
      }
      .pos-stock-badge.in  { background:#e6f6ec; color:#157347; }
      .pos-stock-badge.low { background:#fff4dc; color:#a86400; }
      /* Out of stock badge — solid light-red fill with a tinted border */
      .pos-stock-badge.out { background:#ffd1d1; color:#A61F2B; border:1px solid #F0A6A6; border-radius:4px; }

      /* Out-of-stock card icon — solid deep-rose background, white glyph */
      .kpi-icon.pos-ico-out, .pos-ico-out { background:#D6455B !important; color:#fff !important; }

      /* Expiry pill — not the Low stock yellow. Green >6 mo, amber 3–6, orange <3, red <30 days. */
      .pos-exp {
        display:inline-flex; align-items:center; padding:1px 8px;
        font-size:.68rem; font-weight:700; line-height:1.5; border-radius:999px;
        border:1px solid transparent; letter-spacing:.01em;
      }
      .pos-exp.far { background:#e6f6ec; color:#157347; border-color:#b7e4c7; }
      .pos-exp.mid { background:#f6d36a; color:#6b4300; border-color:#e0b04a; }
      .pos-exp.near { background:#ffe1cc; color:#c2410c; border-color:#f5b183; }
      .pos-exp.urgent { background:#fde2e2; color:#b42318; border-color:#f3b4b4; }
      .pos-pick-tabs { display:flex; gap:6px; flex-wrap:wrap; margin:2px 0 10px; }
      .pos-pick-tab {
        border:1px solid #d7ebe6; background:#fff; color:#516278; border-radius:999px;
        font-size:.75rem; font-weight:700; padding:5px 12px; cursor:pointer;
      }
      .pos-pick-tab.is-on { background:var(--mf-primary-soft); color:var(--mf-primary-dark); border-color:var(--mf-primary); }
      .pos-sub-for { font-size:.68rem; font-weight:700; color:#6D28D9; margin:8px 0 2px; }

      /* Schedule chips — full "Schedule H" label, FILLED background (H #0369A1 as shared) */
      .pos-sch { display:inline-flex; align-items:center; margin-left:6px; padding:1px 7px; border-radius:6px;
                 font-size:.6rem; font-weight:800; letter-spacing:.05em; line-height:1.7; vertical-align:2px;
                 color:#fff; white-space:nowrap; }
      .pos-sch.h    { background:#0369A1; }
      .pos-sch.h1   { background:#6D28D9; }
      .pos-sch.x    { background:#B42318; }
      .pos-sch.ndps { background:#7F1D1D; }

      /* Out of stock — matte light red card with a clearly DIFFERENT deep-rose border (1.5px), never teal */
      .pos-result.is-out { background:#FCEDED; border:1.5px solid #E07B7B; }
      .pos-result.is-out:hover { background:#FCE8E8; border-color:#D6455B; box-shadow:0 0 0 3px rgba(214,69,91,.12); }
      .pos-result.is-out.is-active { border-color:#D6455B; box-shadow:0 0 0 3.5px rgba(214,69,91,.18); }

      /* Inline substitute pills on an out-of-stock card */
      .pos-oos-subs { display:flex; align-items:center; flex-wrap:wrap; gap:4px 6px; margin-top:6px; }
      .pos-oos-subs > span { font-size:.66rem; font-weight:700; color:#B4352F; letter-spacing:.01em; }
      .pos-subadd {
        border:1px solid #B5E1DC; background:#EAF7F5; color:#0F766E; border-radius:4px;
        font-size:.68rem; font-weight:700; padding:2px 7px; display:inline-flex; align-items:center; gap:4px;
        transition:background .12s ease, color .12s ease, border-color .12s ease;
      }
      .pos-subadd i { font-size:.62rem; }
      .pos-subadd b { font-weight:800; }
      .pos-subadd:hover { background:#0F766E; border-color:#0F766E; color:#fff; }
      .pos-subadd:active { transform:translateY(1px); }

      /* Out of stock — price greyed with a strike line */
      .pos-price-out { color:#9aa6b2 !important; text-decoration:line-through; font-weight:600 !important; }

      /* Substitute price-difference chip */
      .pos-subdiff { display:inline-flex; align-items:center; gap:4px; margin-top:4px; padding:2px 8px; border-radius:4px;
                     font-size:.68rem; font-weight:700; border:1px solid; }
      .pos-subdiff.cheaper { background:#DCF0E2; color:#1E7A44; border-color:#BEE2CA; }
      .pos-subdiff.dearer  { background:#FBE7D9; color:#B4451C; border-color:#F4CFB6; }
      .pos-subdiff.same    { background:#E9EDF1; color:#4B5563; border-color:#D5DCE3; }

      /* Summary — grand total in words + savings vs MRP */
      .pos-sum-words { margin-top:4px; text-align:right; font-size:.72rem; font-style:italic; color:#516278; }
      .pos-saved { display:inline-flex; align-items:center; gap:5px; margin-top:6px; padding:3px 10px;
                   border-radius:999px; background:#DCF0E2; color:#146C43; border:1px solid #BEE2CA;
                   font-size:.75rem; font-weight:800; }

      /* Arrow-key selection on search cards */
      .pos-result.is-active { border-color:var(--mf-accent); background:#F2FAF7; box-shadow:0 0 0 3px rgba(46,139,120,.16); }

      /* Cash tender on Complete sale */
      .pos-tender-dialog { max-width: 540px; }
      .pos-tender .modal-header { border-bottom: 1px solid #e4ebf3; align-items: flex-start; }
      .pos-tender .modal-body { padding: 16px 18px 8px; }
      .pos-tender .modal-footer { border-top: 0; padding: 4px 18px 16px; }
      .pos-tender-collect {
        display:flex; align-items:flex-end; justify-content:space-between; gap:12px;
        padding:12px 14px; border-radius:14px; background:#f6f9fc; border:1px solid #e4ebf3;
      }
      .pos-tender-k { font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#66758a; }
      .pos-tender-due { font-size:28px; font-weight:750; letter-spacing:-.03em; font-variant-numeric:tabular-nums; line-height:1.1; color:#1b2430; }
      .pos-tender-meta { color:#66758a; font-size:12px; text-align:right; line-height:1.45; }
      .pos-tender-label { font-size:13px; font-weight:700; margin:14px 0 6px; color:#1b2430; }
      .pos-tender-money {
        display:flex; align-items:center; gap:8px; border:1.5px solid #d5deea;
        border-radius:12px; padding:0 12px; height:52px; background:#fff;
      }
      .pos-tender-money:focus-within { border-color:#16325c; box-shadow:0 0 0 4px rgba(22,50,92,.12); }
      .pos-tender-money span { color:#66758a; font-size:20px; font-weight:700; }
      .pos-tender-money input {
        border:0; outline:0; width:100%; font-size:26px; font-weight:700; color:#1b2430;
        letter-spacing:-.03em; font-variant-numeric:tabular-nums; background:transparent;
      }
      .pos-tender-chips { display:flex; flex-wrap:wrap; gap:6px; margin-top:10px; }
      .pos-tender-chip {
        border:1px solid #d7e0ea; background:#fff; border-radius:999px; padding:6px 11px;
        font-size:13px; font-weight:700; color:#16325c; cursor:pointer;
      }
      .pos-tender-chip.on { background:#16325c; border-color:#16325c; color:#fff; }
      .pos-tender-work { display:grid; grid-template-columns:minmax(0,1fr) 156px; gap:12px; margin-top:14px; }
      .pos-tender-change {
        border-radius:14px; padding:12px 14px; min-height:132px;
        border:1px solid #b7e4c7; background:#e8f7ee;
      }
      .pos-tender-change.short { background:#fdeeee; border-color:#f5c2c2; }
      .pos-tender-change.wait { background:#f6f9fc; border-color:#e4ebf3; }
      .pos-tender-change.warn { background:#fff6e4; border-color:#f0ddb0; }
      .pos-tender-change.warn .k, .pos-tender-change.warn .amt { color:#8a5a00; }
      .pos-tender-change .k { color:#157347; }
      .pos-tender-change.short .k, .pos-tender-change.short .amt { color:#c62828; }
      .pos-tender-change.wait .k, .pos-tender-change.wait .amt { color:#66758a; }
      .pos-tender-change .amt {
        margin-top:2px; font-size:36px; line-height:1.05; font-weight:760;
        letter-spacing:-.04em; font-variant-numeric:tabular-nums; color:#157347;
      }
      .pos-tender-change.wait .amt { font-size:18px; padding-top:6px; }
      .pos-tender-hint { margin-top:4px; font-size:12px; color:#66758a; }
      .pos-tender-give { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
      .pos-tender-give span {
        display:inline-flex; align-items:center; gap:6px; background:#fff; border-radius:999px;
        padding:3px 8px 3px 4px; font-size:12px; font-weight:700; color:#16325c;
        border:1px solid rgba(21,115,71,.18);
      }
      .pos-tender-sw { width:8px; height:16px; border-radius:3px; display:inline-block; }
      .pos-tender-pad { display:grid; grid-template-columns:repeat(3,1fr); gap:6px; }
      .pos-tender-pad button {
        height:42px; border:1px solid #e4ebf3; background:#f8fafc; border-radius:10px;
        font-size:16px; font-weight:700; color:#16325c; cursor:pointer;
      }
      .pos-tender-pad button:hover { background:#e7eef8; }
      .pos-tender-pad button.ghost { color:#66758a; font-size:13px; }
      .pos-tender-note { padding:18px 8px 6px; text-align:center; color:#66758a; font-size:14px; line-height:1.5; }
      .pos-tender-note strong { display:block; color:#1b2430; font-size:16px; margin-bottom:4px; }
      .pos-tender-fine { font-size:12px; color:#66758a; margin:0 18px; }
      .pos-upi { margin: -4px 0 14px; border:1px solid #e4ebf3; border-radius:14px; background:#f6f9fc; padding:12px; }
      .pos-upi-row { display:flex; gap:14px; align-items:center; }
      .pos-upi-code {
        width:168px; height:168px; flex:0 0 168px; background:#fff; border:1px solid #e4ebf3;
        border-radius:12px; display:flex; align-items:center; justify-content:center; padding:8px;
      }
      .pos-upi-code canvas, .pos-upi-code img { display:block; width:148px !important; height:148px !important; }
      .pos-upi-k { font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#66758a; }
      .pos-upi-amt { font-size:28px; font-weight:750; letter-spacing:-.03em; font-variant-numeric:tabular-nums; line-height:1.1; color:#1b2430; }
      .pos-upi-vpa { margin-top:4px; font-weight:700; color:#16325c; word-break:break-all; }
      .pos-upi-note { margin-top:4px; font-size:12px; color:#66758a; line-height:1.45; }
      .pos-upi-form { display:flex; gap:8px; margin-top:8px; }
      .pos-upi-form input { flex:1; }
      .pos-upi-empty { font-size:12px; font-weight:650; color:#66758a; text-align:center; line-height:1.4; }
      .pos-tender-qr { display:flex; flex-direction:column; align-items:center; text-align:center; padding-top:8px; }
      .pos-tender-qr .pos-upi-code { width:220px; height:220px; flex-basis:220px; margin-top:8px; }
      .pos-tender-qr .pos-upi-code canvas, .pos-tender-qr .pos-upi-code img { width:200px !important; height:200px !important; }
      .pos-upi-split { margin-top:12px; text-align:center; }
      .pos-upi-split .pos-upi-code { margin:8px auto 0; }
      @media (max-width: 540px) {
        .pos-tender-work { grid-template-columns:1fr; }
        .pos-tender-pad { grid-template-columns:repeat(6,1fr); }
        .pos-upi-row { flex-direction:column; align-items:flex-start; }
      }
    `;
    document.head.appendChild(st);
  }
  const walkInId = () => D.customers.find((c) => c.name === 'Walk-in Customer')?.id ?? (D.customers[0]?.id ?? '');

  /* dd Mon yyyy, e.g. "08 Oct 2026" */
  function fmtExpiryDate(dateVal) {
    const d = new Date(dateVal);
    if (isNaN(d)) return '—';
    const day = String(d.getDate()).padStart(2, '0');
    const month = d.toLocaleString('en-US', { month: 'short' });
    return `${day} ${month} ${d.getFullYear()}`;
  }

  /* Quick-pick expiry chip. Not the Low stock yellow.
     green above 6 months, amber at 3–6 months, orange under 3 months, red under 30 days. */
  function expiryTier(dateVal) {
    const exp = new Date(dateVal);
    const base = new Date(MF.today ? MF.today() : Date.now());
    if (isNaN(exp)) return { key: 'far', title: 'Expiry date unknown' };
    const start = new Date(base.getFullYear(), base.getMonth(), base.getDate());
    const end = new Date(exp.getFullYear(), exp.getMonth(), exp.getDate());
    const days = Math.round((end - start) / 86400000);
    if (days < 0) return { key: 'urgent', title: 'Expired' };
    if (days < 30) return { key: 'urgent', title: 'Under 30 days to expiry' };
    const plus3 = new Date(start.getFullYear(), start.getMonth() + 3, start.getDate());
    const plus6 = new Date(start.getFullYear(), start.getMonth() + 6, start.getDate());
    if (end < plus3) return { key: 'near', title: 'Under 3 months to expiry' };
    if (end <= plus6) return { key: 'mid', title: '3–6 months to expiry' };
    return { key: 'far', title: 'More than 6 months to expiry' };
  }

  /* Pack label (Strip, Bottle, …). subUnit / packQty describe the pieces inside it. */
  function unitLabel(m) {
    return m && m.unit ? m.unit : 'units';
  }

  /* Piece label (Tab, Capsule, …). Falls back to "Tab" when a pack holds more than one piece. */
  function pieceLabel(m) {
    if (m && m.subUnit) return m.subUnit;
    if (m && Number(m.packQty) > 1) return 'Tab';
    return unitLabel(m);
  }

  function packSize(m) {
    const n = Number(m && m.packQty);
    return n > 0 ? n : 1;
  }

  function withCount(n, label) {
    const name = String(label || 'units');
    if (/^(g|mg|mcg|ml|l|iu|kg)$/i.test(name)) return name; // measurement units read the same at any quantity (30 g, 100 ml)
    if (Number(n) === 1) return name.replace(/s$/i, '') || name;
    if (/s$/i.test(name)) return name;
    return name + 's';
  }

  /* Strips + loose tablets already sitting in this cart for one medicine. */
  function cartUsage(medId) {
    let packs = 0, loose = 0;
    state.cart.forEach((l) => {
      if (l.medId != medId) return;
      if (l.unit === 'loose') loose += Number(l.qty) || 0;
      else packs += Number(l.qty) || 0;
    });
    return { packs, loose };
  }

  /* Sellable stock after the current cart, walked FEFO the same way a sale would.
     strips  = sealed packs still closed
     loose   = opened pieces not yet in the cart
     tablets = strips × pack size + loose  (what we show next to the expiry date) */
  function fefoState(medOrId) {
    const med = medOrId && typeof medOrId === 'object' ? medOrId : MF.med(medOrId);
    const packQty = packSize(med);
    const usage = med ? cartUsage(med.id) : { packs: 0, loose: 0 };
    const batches = (med ? MF.batchesOf(med.id) : [])
      .filter((b) => MF.daysTo(b.expiry) >= 0)
      .sort((a, b) => String(a.expiry).localeCompare(String(b.expiry)))
      .map((b) => ({
        id: b.id,
        batch: b,
        strips: Math.max(0, (Number(b.qty) || 0) - (Number(b.reserved) || 0)),
        loose: Math.max(0, Number(b.looseQty) || 0)
      }));

    let packsLeft = usage.packs;
    let looseLeft = usage.loose;
    for (const b of batches) {
      if (packsLeft <= 0) break;
      const take = Math.min(b.strips, packsLeft);
      b.strips -= take;
      packsLeft -= take;
    }
    for (const b of batches) {
      if (looseLeft <= 0) break;
      const take = Math.min(b.loose, looseLeft);
      b.loose -= take;
      looseLeft -= take;
    }
    for (const b of batches) {
      if (looseLeft <= 0) break;
      if (b.strips <= 0) continue;
      const take = Math.min(b.strips * packQty, looseLeft);
      const open = Math.ceil(take / packQty);
      b.strips -= open;
      b.loose += open * packQty - take;
      looseLeft -= take;
    }

    let strips = 0, loose = 0;
    batches.forEach((b) => { strips += b.strips; loose += b.loose; });
    return {
      med, packQty, strips, loose,
      tablets: strips * packQty + loose,
      batches,
      inCartPacks: usage.packs,
      inCartLoose: usage.loose,
      nextStrip: batches.find((b) => b.strips > 0) || null,
      nextLoose: batches.find((b) => b.loose > 0 || b.strips > 0) || null
    };
  }

  /* Extra qty this cart line can still take from its own batch (other lines on that batch already removed). */
  function lineRoom(l) {
    const med = MF.med(l.medId);
    const packQty = packSize(med);
    const raw = (D.batches || []).find((x) => x.id == l.batchId);
    if (!raw || MF.daysTo(raw.expiry) < 0) return { strips: 0, tablets: 0 };
    let strips = Math.max(0, (Number(raw.qty) || 0) - (Number(raw.reserved) || 0));
    let loose = Math.max(0, Number(raw.looseQty) || 0);
    state.cart.forEach((line) => {
      if (line === l || line.batchId != l.batchId) return;
      if (line.unit === 'loose') {
        let need = Number(line.qty) || 0;
        const take = Math.min(loose, need);
        loose -= take;
        need -= take;
        if (need > 0 && strips > 0) {
          const open = Math.min(strips, Math.ceil(need / packQty));
          strips -= open;
          loose += open * packQty - need;
          if (loose < 0) loose = 0;
        }
      } else {
        strips = Math.max(0, strips - (Number(line.qty) || 0));
      }
    });
    if (l.unit === 'loose') {
      return { strips: Math.max(0, strips), tablets: Math.max(0, strips * packQty + loose - (Number(l.qty) || 0)) };
    }
    return { strips: Math.max(0, strips - (Number(l.qty) || 0)), tablets: 0 };
  }

  /* In stock / Low stock / Out of stock — judged on strips still left after the cart. */
  function stockBadge(m, live) {
    const stock = live ? live.strips : 0;
    const tablets = live ? live.tablets : 0;
    const low = Number(m.reorderLevel ?? m.minStock ?? 10);
    const st = tablets <= 0 ? ['out', 'Out of stock'] : stock <= low ? ['low', 'Low stock'] : ['in', 'In stock'];
    return `<span class="pos-stock-badge ${st[0]}">${st[1]}</span>`;
  }

  /* "Medicine name · Brand" — brand shown muted after a centre dot */
  /* Schedule label (H / H1 / X / NDPS) — drives the card badge and the automatic Rx toggle. */
  function rxSchedule(m) {
    const s = String((m && m.schedule) || '').trim().toUpperCase().replace(/^SCHEDULE\s+/, '');
    const hit = /^(H1|NDPS|X)(?![A-Z0-9])/.exec(s);
    if (hit) return hit[1];
    return /^H(?![A-Z0-9])/.test(s) ? 'H' : '';
  }

  function nameLine(m) {
    const brand = m.brandRef ? ` <span class="text-2 fw-normal">· ${MF.esc(m.brandRef)}</span>` : '';
    const sch = rxSchedule(m);
    const schChip = sch ? `<span class="pos-sch ${sch.toLowerCase()}" title="Schedule ${sch} — prescription required, Rx verification turns on automatically">Schedule ${sch}</span>` : '';
    return `<div class="pr-name">${MF.esc(m.name)}${brand}${schChip}</div>`;
  }

  /* Batch + expiry only. Strip and tablet counts live on the stock line. */
  function batchExpiryPills(b, m) {
    if (!b) return '';
    const tier = expiryTier(b.expiry);
    return `
      <div class="d-flex align-items-center flex-wrap gap-1 mt-1">
        <span class="badge rounded-pill bg-light text-dark" title="Batch ${MF.esc(b.batchNo)}"><i class="bi bi-upc-scan"></i> ${MF.esc(b.batchNo)}</span>
        <span class="pos-exp ${tier.key}" title="${tier.title}">Exp : ${fmtExpiryDate(b.expiry)}</span>
      </div>`;
  }

/* Generic/substitute linking: shown only when a medicine has no sellable batch. */
  function subsLine(m) {
    const subs = subsOf(m);
    if (!subs.length) return '';
    return `<div class="pr-meta text-2 mt-1"><i class="bi bi-arrow-left-right"></i> Try instead: ${subs.slice(0, 4).map((s) => MF.esc(s.name)).join(', ')}</div>`;
  }

  /* Attach click → addToCart for BOTH search results and the "Fast moving" shortcuts */
  function bindResultClicks(box) {
    box.querySelectorAll('.pos-result:not([disabled])').forEach((btn) =>
      btn.addEventListener('click', () => addToCart(btn.dataset.med)));
    box.querySelectorAll('.pos-loose-add').forEach((btn) =>
      btn.addEventListener('click', (e) => { e.stopPropagation(); addToCart(btn.dataset.med, 'loose'); }));
    box.querySelectorAll('.pos-order-sub').forEach((btn) =>
      btn.addEventListener('click', (e) => { e.stopPropagation(); openOrderOrSub(btn.dataset.med); }));
    box.querySelectorAll('.pos-subadd').forEach((btn) =>
      btn.addEventListener('click', (e) => { e.stopPropagation(); addToCart(btn.dataset.med); }));
  }

  /* Left-column MRP line: shown as its own row right under the batch/expiry pills. */
  function mrpLine(m, live) {
    return `<div class="small-xs text-2 mt-1">MRP : ${MF.fmt(m.mrp, 2)}/${MF.esc(unitLabel(m))}${stockBadge(m, live)}</div>`;
  }

  /* Strip + tablet totals, both net of the cart. Measure-piece packs (tube of "g", bottle of "ml")
     don't double-count: when pieces == packs the line shows the pack count only. */
  function stockText(m, live) {
    const strips = `${MF.num(live.strips)} ${MF.esc(withCount(live.strips, unitLabel(m)))}`;
    if (pieceLabel(m) === unitLabel(m) || live.tablets === live.strips) return `Stock : ${strips}`;
    const tabs = `${MF.num(live.tablets)} ${MF.esc(withCount(live.tablets, pieceLabel(m)))}`;
    return `Stock : ${strips} · ${tabs}`;
  }

  /* Right-side block: live strip stock, and either the add button or a purple
     order/substitute tile (styled like the payment-method tiles) when out of stock. */
  function priceBlock(m, live) {
    const outOfStock = live.tablets <= 0;
    const heldInCart = outOfStock && (live.inCartPacks > 0 || live.inCartLoose > 0);
    const sellPrice = Number(m.retailRate ?? m.mrp);
    const mrpN = Number(m.mrp) || 0;
    const mrpNote = sellPrice !== mrpN
      ? `<div class="small-xs text-2" style="text-decoration:line-through;">MRP ${MF.fmt(mrpN, 2)}</div>` : '';
    // Discount chip next to the retail price — teal, shown only when retail actually undercuts MRP
    const offPct = mrpN > 0 && sellPrice < mrpN ? Math.round(((mrpN - sellPrice) / mrpN) * 100) : 0;
    const offChip = offPct > 0 ? `<span class="pos-off" title="${offPct}% off MRP" aria-label="${offPct} percent off">${offPct}% off</span>` : '';
    // Per-piece rate under the retail price — only for COUNTABLE pack pieces (strips of tablets/capsules:
    // ₹37.00 ÷ 10 = ₹3.70/tablet). For whole-unit packs measured in g/ml (tubes, bottles) a per-gram rate
    // is meaningless at the counter, so it stays hidden. "Add loose" stays the only toggle-gated element.
    const loosePiece = withCount(1, pieceLabel(m)).toLowerCase();
    const loosePrice = packSize(m) > 1
      ? `<div class="small-xs text-2">${MF.fmt(sellPrice / packSize(m), 2)}/${loosePiece}</div>` : '';
    const action = outOfStock
      ? (heldInCart
          ? `<div class="small-xs mt-1" style="color:#a86400;font-weight:600;">All remaining in cart</div>`
          : `<button type="button" class="btn pos-order-sub mt-1" data-med="${m.id}">
           <i class="bi bi-arrow-repeat"></i>
           <span class="fw-semibold" style="font-size:.75rem;">Order / substitute</span>
         </button>`)
      : (m.allowLoose ? `<button type="button" class="btn btn-sm mt-1 pos-loose-add" data-med="${m.id}">
           <i class="bi bi-plus-circle"></i> Add loose ${loosePiece}
         </button>` : '');
    return `
        ${mrpNote}
        <div class="fw-bold num${outOfStock ? ' pos-price-out' : ''}"${outOfStock ? ' title="Out of stock — price cannot be charged"' : ''}>${MF.fmt(sellPrice, 2)}${offChip}</div>
        ${loosePrice}
        <div class="small-xs text-2 mt-1" title="Sellable strips and tablets left after this cart">${stockText(m, live)}</div>
        ${action}`;
  }

  /* Price difference chip for a substitute vs the item it replaces. */
  function subDiff(sub, src) {
    const a = Number(sub.retailRate ?? sub.mrp) || 0;
    const b = Number(src.retailRate ?? src.mrp) || 0;
    const d = a - b;
    const per = MF.esc(unitLabel(sub));
    if (Math.abs(d) < 0.005) return `<span class="pos-subdiff same"><i class="bi bi-arrow-left-right"></i>Same price · ${MF.fmt(a, 2)}/${per}</span>`;
    return d < 0
      ? `<span class="pos-subdiff cheaper"><i class="bi bi-arrow-down"></i>₹${Math.abs(d).toFixed(2)}/${per} cheaper than ${MF.esc(src.name)}</span>`
      : `<span class="pos-subdiff dearer"><i class="bi bi-arrow-up"></i>₹${d.toFixed(2)}/${per} more than ${MF.esc(src.name)}</span>`;
  }

  /* One quick-pick / search card. Stock (strips and tablets) is the only quantity shown.
     `cmp` = the medicine this is a substitute for → shows the price difference. */
  function resultCard(m, cmp) {
    const live = fefoState(m);
    // Earliest batch that still has anything to sell (loose pieces before a later sealed strip).
    const shown = live.nextLoose || live.nextStrip;
    const b = shown ? shown.batch : MF.pickBatch(m.id);
    const noPack = live.strips <= 0;
    const out = live.tablets <= 0;
    return `
      <div class="pos-result${out ? ' is-out' : ''}" role="button" tabindex="0" data-med="${m.id}" ${noPack ? 'disabled' : ''}>
        <div class="kpi-icon ${out ? 'pos-ico-out' : 'tone-primary'}" style="width:38px;height:38px;flex-basis:38px;font-size:1rem"><i class="bi bi-capsule"></i></div>
        <div class="flex-grow-1 text-start">
          ${nameLine(m)}
          <div class="pr-meta">${MF.esc(m.composition)}</div>
          ${cmp ? subDiff(m, cmp) : ''}
          ${b ? batchExpiryPills(b, m) : `<div class="pr-meta text-danger mt-1">No sellable batch (expired stock only)</div>${subsLine(m)}`}
          ${mrpLine(m, live)}
          ${out ? outSubsStrip(m) : ''}
        </div>
        <div class="text-end">
          ${priceBlock(m, live)}
        </div>
      </div>`;
  }

  /* ---------------- Keyboard nav + barcode scan ---------------- */
  function resultCards() { return Array.from(document.querySelectorAll('#posResults .pos-result')); }

  function paintSel() {
    const cards = resultCards();
    if (!cards.length) { state.selIdx = 0; return; }
    state.selIdx = Math.min(Math.max(state.selIdx, 0), cards.length - 1);
    cards.forEach((c, i) => c.classList.toggle('is-active', i === state.selIdx));
  }

  /* An exact, non-empty barcode hit — scanners type the full code, so an exact match is a scan. */
  function exactBarcodeMatch(q) {
    q = String(q || '').trim();
    if (!q) return null;
    const hits = (D.medicines || []).filter((m) => m.barcode && String(m.barcode).trim() === q);
    return hits[0] || null;
  }

  /* Qty-first entry: "3*amox" (or 3x / 3×) parks qty 3 for the next add — counter-speed habit. */
  function paintQtyFlag() {
    const f = $('#posQtyFlag');
    if (!f) return;
    if (state.pendingQty > 0) { f.hidden = false; f.textContent = '×' + state.pendingQty; }
    else f.hidden = true;
  }
  function qtyFlagReset() { state.pendingQty = 0; paintQtyFlag(); }
  function qtyPrefixParse(input) {
    const m = String(input.value || '').match(/^\s*(\d{1,3})\s*[*xX×]\s*(.*)$/);
    if (!m) return;
    state.pendingQty = Math.max(1, Math.min(999, parseInt(m[1], 10) || 1));
    input.value = m[2] || '';
    paintQtyFlag();
  }

  function resetSearch(box) {
    box.value = '';
    qtyFlagReset();
    if (searchTimer) { clearTimeout(searchTimer); searchTimer = null; }
    searchMeds(''); // back to Quick picks instantly — no stale hits under a cleared box
    state.selIdx = 0;
    state.selQuery = '';
    const cb = document.getElementById('posSearchClear');
    if (cb) cb.hidden = true; // barcode autoscan clears without an input event — keep the × in sync
    searchMeds('');
  }

  function onSearchKeys(e) {
    // A pending debounced render must never leave Enter/arrows acting on stale cards.
    if (e.key === 'Enter' || e.key === 'ArrowDown' || e.key === 'ArrowUp') flushSearchMeds();
    const cards = resultCards();
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      if (!cards.length) return;
      e.preventDefault();
      const step = e.key === 'ArrowDown' ? 1 : -1;
      state.selIdx = (state.selIdx + step + cards.length) % cards.length;
      paintSel();
      cards[state.selIdx].scrollIntoView({ block: 'nearest' });
      return;
    }
    if (e.key === 'Enter') {
      e.preventDefault();
      const exact = exactBarcodeMatch(e.target.value);
      if (exact) { addToCart(exact.id); resetSearch(e.target); return; }
      const card = cards[state.selIdx] || cards[0];
      if (!card) return;
      if (card.hasAttribute('disabled')) { MF.toast('That medicine is out of stock — try Order / substitute.', 'warn', 'Stock'); return; }
      addToCart(card.dataset.med);
      paintSel();
    }
  }

  /* ---------------- Medicine search ---------------- */
  /* Debounced result rendering: typing fires ONE render after a 120ms pause —
     barcode exact-add and the ×3 qty flag stay synchronous in the input handler.
     flushSearchMeds() must run before any code reads the rendered cards (Enter /
     arrows) so a fast typist can never add from stale results. */
  let searchTimer = null;
  function queueSearchMeds(q) {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { searchTimer = null; searchMeds(q); }, 120);
  }
  function flushSearchMeds() {
    if (!searchTimer) return;
    clearTimeout(searchTimer);
    searchTimer = null;
    searchMeds($('#posSearch') ? $('#posSearch').value : '');
  }

  function searchMeds(q) {
    const qRaw = String(q || '');
    if (qRaw !== state.selQuery) { state.selIdx = 0; state.selQuery = qRaw; }
    q = qRaw.trim().toLowerCase();
    const box = $('#posResults');
    const tabs = $('#posPickTabs');
    if (tabs) tabs.hidden = !!q;
    if (!q) { box.innerHTML = emptySearch(); bindResultClicks(box); paintSel(); return; }
    const hits = D.medicines.filter((m) =>
      (m.name + ' ' + m.generic + ' ' + m.composition + ' ' + m.brandRef).toLowerCase().includes(q) ||
      MF.batchesOf(m.id).some((b) => b.batchNo.toLowerCase().includes(q))
    ).slice(0, 8);
    if (!hits.length) { box.innerHTML = `<div class="empty-state"><i class="bi bi-emoji-neutral"></i>No medicine matches “${MF.esc(q)}”.</div>`; return; }
    box.innerHTML = hits.map((m) => resultCard(m)).join('');
    bindResultClicks(box);
    paintSel();
  }

  function emptySearch() {
    if (state.pick === 'recent') {
      const picks = recentMeds();
      if (!picks.length) return `<div class="empty-state"><i class="bi bi-clock-history"></i>No recent medicines yet. Add one to the cart and it will show up here.</div>`;
      return `${picks.map((m) => resultCard(m)).join('')}<p class="text-2 small mt-3 mb-0"><i class="bi bi-clock-history me-1"></i>Medicines added on this counter, newest first.</p>`;
    }
    if (state.pick === 'subs') {
      const rows = substituteMeds();
      const seed = state.subSeed ? MF.med(state.subSeed) : null;
      if (!rows.length) return `<div class="empty-state"><i class="bi bi-arrow-left-right"></i>${seed ? `No in-stock substitute for ${MF.esc(seed.name)} with the same composition.` : 'No in-stock substitutes for the current cart.'}</div>`;
      return `${rows.map((row) => `<div class="pos-sub-for">Instead of ${MF.esc(row.from.name)} · ${MF.fmt(row.from.retailRate ?? row.from.mrp, 2)}</div>${resultCard(row.med, row.from)}`).join('')}<p class="text-2 small mt-3 mb-0"><i class="bi bi-arrow-left-right me-1"></i>In stock, same composition or generic group — the price difference is shown against your item.</p>`;
    }
    const picks = (D.medicines || []).slice(0, 5);
    if (!picks.length) return `<div class="empty-state"><i class="bi bi-capsule"></i>No medicines yet — add some in Medicine Master.</div>`;
    return `${picks.map((m) => resultCard(m)).join('')}<p class="text-2 small mt-3 mb-0"><i class="bi bi-lightbulb me-1"></i>Search by medicine name, generic name, composition, batch no or barcode.</p>`;
  }

  /* ---------------- Cart ---------------- */
  /* ===== Sell-price engine ====================================================
     * Rates resolve per customer TYPE, never blindly:
     *   retail    → retail_rate (fallback MRP)
     *   wholesale / hospital / clinic → wholesale_rate (fallback retail_rate)
     * Every price is then clamped to the SELECTED BATCH's MRP — batches bought
     * before an MRP hike legally cannot bill above their own printed MRP. */
  function posCust() {
    return MF.cust($('#posCustomer') ? $('#posCustomer').value : '') || null;
  }
  function posCustIsBusiness(cust) {
    const c = cust === undefined ? posCust() : cust;
    const t = String((c && c.type) || '').toLowerCase();
    return t !== '' && t !== 'retail' && String(c ? c.name : '') !== 'Walk-in Customer';
  }
  function batchMrpOf(med, b) {
    const bm = Number(b && b.mrp) || 0;
    return bm > 0 ? bm : (Number(med.mrp) || 0);
  }
  function sellPriceOf(med, b) {
    const business = posCustIsBusiness();
    const ws = Number(med.wholesaleRate) || 0;
    const base = business && ws > 0 ? ws : Number(med.retailRate ?? med.mrp);
    const cap = batchMrpOf(med, b);
    return {
      rate: cap > 0 ? Math.min(base, cap) : base,
      kind: business && ws > 0 ? 'wholesale' : 'retail',
      clamped: cap > 0 && base > cap,
    };
  }
  /* Cashier role for the discount cap — fetched once, fail-open (owner = free). */
  let _posRole = null;
  async function posRole() {
    if (_posRole) return _posRole;
    try {
      const res = await MF.Api.get('whoami.php');
      _posRole = res && res.data ? res.data : { role: 'admin', privileged: true };
    } catch (e) { _posRole = { role: 'admin', privileged: true }; }
    return _posRole;
  }
  const DISC_CAP_STAFF = 10;                    // % — cashier ceiling, admin is unlimited

  function addToCart(medId, unit = 'pack') {
    const med = MF.med(medId);
    if (!med) return;
    const pack = unit !== 'loose';
    if (!pack && !med.allowLoose) { MF.toast('Loose sale is not enabled for ' + med.name, 'warn', 'Stock'); return; }
    const live = fefoState(med);
    if (pack && live.strips <= 0) {
      MF.toast(`No ${withCount(2, unitLabel(med))} available for ${med.name}`, 'warn', 'Stock');
      return;
    }
    if (!pack && live.tablets <= 0) {
      MF.toast(`No ${withCount(2, pieceLabel(med))} available for ${med.name}`, 'warn', 'Stock');
      return;
    }
    const slot = pack ? live.nextStrip : live.nextLoose;
    if (!slot) { MF.toast('No sellable batch available for ' + med.name, 'warn', 'Stock'); return; }
    const px = sellPriceOf(med, slot.batch || slot);
    const sell = px.rate;
    const rate = pack ? sell : sell / packSize(med);
    const line = state.cart.find((l) => l.batchId == slot.id && (pack ? l.unit !== 'loose' : l.unit === 'loose'));
    if (line) line.qty++;
    else {
      const rawDisc = Number(med.defaultDiscount) || 0;
      const discPct = med.discountType === 'rupee'
        ? (rate > 0 ? Math.min(100, (rawDisc / rate) * 100) : 0)
        : Math.min(100, rawDisc);
      state.cart.push({ medId, batchId: slot.id, qty: 1, rate, mrp: med.mrp, discPct, unit: pack ? 'pack' : 'loose', kind: px.kind });
    }
    // Qty-first entry ("3*amox") — top up the line we just touched, clamped to batch room.
    if (state.pendingQty > 0) {
      const row = line || state.cart[state.cart.length - 1];
      if (row) {
        const room = lineRoom(row);
        const left = pack ? room.strips : room.tablets;
        const extra = Math.min(state.pendingQty - 1, Math.max(0, left));
        if (extra > 0) row.qty += extra;
        if (extra < state.pendingQty - 1) MF.toast(`Only ${row.qty} available in this batch — short of the ${state.pendingQty} requested`, 'warn', 'Stock');
      }
      qtyFlagReset();
    }
    if (med.rxRequired) {
      MF.toast(med.name + ' is Schedule ' + med.schedule + ' — pick the Doctor and the patient above (highlighted), or attach the recorded prescription.', 'info', 'Rx item');
      rxNudgeSync();
    }
    rememberRecent(medId);
    renderCart();
  }

  function rememberRecent(medId) {
    const id = String(medId);
    state.recent = [id, ...state.recent.filter((x) => String(x) !== id)].slice(0, 12);
    try { sessionStorage.setItem('mf-pos-recent', JSON.stringify(state.recent)); } catch (e) { /* ignore */ }
  }

  function medBySaleLine(line) {
    if (!line) return null;
    const id = line.medId || line.medicineId || line.medicine_id;
    if (id != null && MF.med(id)) return MF.med(id);
    const name = line.medicine_name || line.name || line.medicine;
    if (!name) return null;
    return (D.medicines || []).find((m) => String(m.name).toLowerCase() === String(name).toLowerCase()) || null;
  }

  function recentMeds() {
    const ids = [];
    const push = (id) => {
      if (id == null || id === '') return;
      if (!ids.some((x) => String(x) === String(id))) ids.push(id);
    };
    state.recent.forEach(push);
    const invoices = D.salesInvoices || D.sales || [];
    invoices.slice().reverse().forEach((inv) => {
      (inv.items || inv.lines || []).forEach((line) => {
        const med = medBySaleLine(line);
        if (med) push(med.id);
      });
    });
    return ids.map((id) => MF.med(id)).filter(Boolean).slice(0, 8);
  }

  /* ---- Rich substitute matching -------------------------------------------------
     Salts come from BOTH the Generic/composition group field (one chip per salt)
     and the composition text. Two items are substitutes when they share a salt
     (exact salt, or same salt root with a different strength). Only items with
     sellable stock right now (FEFO) qualify; ranked by shared salts then price. */
  function saltsOf(m) {
    const raw = String((m && m.genericGroup) || '') + ';' + String((m && (m.composition || m.generic)) || '');
    return [...new Set(raw.split(/[,;+]|\s+\/\s+/).map((s) => s.trim().toLowerCase()).filter((s) => s.length >= 3))];
  }
  function saltRoot(s) {
    const t = String(s || '').toLowerCase().replace(/[^a-z ]/g, ' ').trim().split(/\s+/)[0] || '';
    return t.length >= 3 ? t : '';
  }
  function subsOf(med) {
    const my = saltsOf(med);
    if (!my.length) return [];
    const myRoots = my.map(saltRoot).filter(Boolean);
    const myKey = my.slice().sort().join('|');
    const price = Number(med.retailRate ?? med.mrp) || 0;
    const out = [];
    (D.medicines || []).forEach((c) => {
      if (String(c.id) === String(med.id)) return;
      if (fefoState(c).tablets <= 0) return;               // no sellable stock → not a usable substitute
      const cs = saltsOf(c);
      if (!cs.length) return;
      const cRoots = cs.map(saltRoot).filter(Boolean);
      const shared = my.filter((s, i) => cs.includes(s) || (myRoots[i] && cRoots.includes(myRoots[i]))).length;
      if (!shared) return;
      const sameGroup = cs.slice().sort().join('|') === myKey;
      const score = (sameGroup ? 100 : 0) + shared * 10 - Math.abs((Number(c.retailRate ?? c.mrp) || 0) - price) / 1000;
      out.push({ med: c, score });
    });
    out.sort((a, b) => b.score - a.score);
    return out.map((x) => x.med);
  }

  /* Small in-stock substitute pills shown right on an out-of-stock card. */
  function subPill(s, src) {
    const d = (Number(s.retailRate ?? s.mrp) || 0) - (Number(src.retailRate ?? src.mrp) || 0);
    const diff = Math.abs(d) < 0.005 ? 'same price' : (d < 0 ? '−' + MF.fmt(Math.abs(d), 2) : '+' + MF.fmt(d, 2));
    return `<button type="button" class="pos-subadd" data-med="${s.id}" title="Add ${MF.esc(s.name)} instead — ${diff}"><i class="bi bi-arrow-left-right"></i>${MF.esc(s.name)} <b>${diff}</b></button>`;
  }
  function outSubsStrip(m) {
    const subs = subsOf(m).slice(0, 3);
    if (!subs.length) return '';
    return `<div class="pos-oos-subs"><span>Substitutes in stock:</span>${subs.map((s) => subPill(s, m)).join('')}</div>`;
  }

  function substituteMeds() {
    if (state.subSeed) {
      const seed = MF.med(state.subSeed);
      return seed ? subsOf(seed).map((m) => ({ med: m, from: seed })).slice(0, 8) : [];
    }
    const seeds = [];
    const pushSeed = (id) => {
      const med = MF.med(id);
      if (med && !seeds.some((m) => m.id == med.id)) seeds.push(med);
    };
    state.cart.forEach((l) => pushSeed(l.medId));
    if (!seeds.length && state.recent.length) pushSeed(state.recent[0]);
    const out = [];
    const push = (m, from) => {
      if (!m || seeds.some((s) => s.id == m.id) || out.some((x) => x.med.id == m.id)) return;
      out.push({ med: m, from });
    };
    if (seeds.length) {
      seeds.forEach((s) => subsOf(s).forEach((m) => push(m, s)));
    } else {
      (D.medicines || []).slice(0, 12).forEach((s) => subsOf(s).forEach((m) => push(m, s)));
    }
    return out.slice(0, 8);
  }

  /* ---------------- Order pad (re-order list for the next purchase) ---------------- */
  function orderPadLoad() {
    try {
      const raw = JSON.parse(sessionStorage.getItem('mf-pos-order') || '[]');
      state.orderPad = Array.isArray(raw) ? raw.filter((r) => r && r.medId != null && MF.med(r.medId)) : [];
    } catch (e) { state.orderPad = []; }
  }
  function orderPadSave() {
    try { sessionStorage.setItem('mf-pos-order', JSON.stringify(state.orderPad)); } catch (e) { /* ignore */ }
  }
  function paintOrderCount() {
    const n = $('#posOrderCount');
    if (n) n.textContent = String(state.orderPad.length);
    const chip = $('#posOrderChip');
    if (chip) {
      chip.classList.toggle('has-pending', !!state.orderPad.length);
      chip.title = state.orderPad.length
        ? `${state.orderPad.length} medicine(s) pending — open New Purchase and place the order`
        : 'Order pad';
    }
  }
  /* Aim for ~2× the low-stock threshold (or 20 when none is set). */
  function suggestedOrderQty(m) {
    const low = Number(m.reorderLevel ?? m.minStock ?? 0) || 0;
    const cur = MF.stockOf(m.id);
    return Math.max(1, (low > 0 ? low * 2 : 20) - cur);
  }
  function addToOrderPad(medId, silent) {
    const med = MF.med(medId);
    if (!med) return;
    if (!state.orderPad.some((r) => String(r.medId) === String(medId))) {
      state.orderPad.push({ medId, qty: suggestedOrderQty(med) });
      orderPadSave();
      paintOrderCount();
    }
    if (!silent) MF.toast(med.name + ' added to the order pad.', 'success', 'Order pad');
  }

  function renderOrderPad() {
    const box = $('#posOrderBody');
    if (!box) return;
    if (!state.orderPad.length) {
      box.innerHTML = `<div class="empty-state"><i class="bi bi-cart-plus"></i>Nothing to order yet.<br><span class="small">Out-of-stock cards offer “Add to order pad”.</span></div>`;
      return;
    }
    box.innerHTML = state.orderPad.map((r, i) => {
      const med = MF.med(r.medId);
      if (!med) return '';
      const cur = MF.stockOf(r.medId);
      return `<div class="d-flex align-items-center gap-2 py-2${i ? ' border-top' : ''}">
        <div class="me-auto">
          <div class="fw-semibold">${MF.esc(med.name)}</div>
          <div class="text-2 small-xs">On hand ${MF.num(cur)}· re-order below ${MF.num(Number(med.reorderLevel ?? med.minStock ?? 0) || 0)}</div>
        </div>
        <input type="number" min="1" class="form-control form-control-sm pos-opad-qty num" data-i="${i}" value="${r.qty}" style="width:76px" title="Order quantity (strips)">
        <button type="button" class="btn btn-icon btn-light-mf text-danger pos-opad-rm" data-i="${i}" title="Remove"><i class="bi bi-x-lg"></i></button>
      </div>`;
    }).join('');
    box.querySelectorAll('.pos-opad-qty').forEach((el) => el.addEventListener('change', () => {
      state.orderPad[+el.dataset.i].qty = Math.max(1, parseInt(el.value) || 1);
      orderPadSave();
    }));
    box.querySelectorAll('.pos-opad-rm').forEach((el) => el.addEventListener('click', () => {
      state.orderPad.splice(+el.dataset.i, 1);
      orderPadSave(); paintOrderCount(); renderOrderPad();
    }));
  }

  /* "Order / substitute" tile on an out-of-stock card → chooser (or straight to the pad). */
  function openOrderOrSub(medId) {
    const med = MF.med(medId);
    if (!med) return;
    const subs = subsOf(med);
    if (!subs.length) {
      addToOrderPad(medId);
      MF.toast('No in-stock substitute — added to the order pad for the next purchase.', 'info', 'Order / substitute');
      return;
    }
    $('#posSheetTitle').textContent = med.name;
    $('#posSheetStock').textContent = 'Out of stock — sell a substitute now, or order this one.';
    $('#posSheetSub').querySelector('.cnt').textContent = String(subs.length);
    $('#posSheetSub').onclick = () => { actionSheet.hide(); showSubstitutes(medId); };
    $('#posSheetOrder').onclick = () => { actionSheet.hide(); addToOrderPad(medId); };
    actionSheet.show();
  }

  /* Jump to the Substitutes tab focused on one medicine. */
  function showSubstitutes(medId) {
    const med = MF.med(medId);
    if (!med) return;
    state.subSeed = String(medId);
    state.pick = 'subs';
    const tabs = $('#posPickTabs');
    if (tabs) tabs.querySelectorAll('.pos-pick-tab').forEach((b) => b.classList.toggle('is-on', b.dataset.pick === 'subs'));
    const input = $('#posSearch');
    if (input) input.value = '';
    searchMeds('');
    if (!subsOf(med).length) MF.toast('No in-stock substitute for ' + med.name + ' with the same composition', 'info', 'Substitutes');
  }

  /* "2 Strips · 20 Tabs" (or just tablets, for a loose line) — recomputed from the stepper qty. */
  function lineMeasure(l, med) {
    if (l.unit === 'loose') return `${MF.num(l.qty)} ${MF.esc(withCount(l.qty, pieceLabel(med)))}`;
    const tabs = l.qty * packSize(med);
    return `${MF.num(l.qty)} ${MF.esc(withCount(l.qty, unitLabel(med)))} · ${MF.num(tabs)} ${MF.esc(withCount(tabs, pieceLabel(med)))}`;
  }

  function calcLine(l) {
    const gross = l.qty * l.rate;
    const disc = gross * (l.discPct / 100);
    const net = gross - disc;
    const med = MF.med(l.medId);
    const gstAmt = net - net / (1 + med.gst / 100);   // GST extracted from MRP (inclusive)
    return { gross, disc, net, gstAmt };
  }

  function calcTotals() {
    let subtotal = 0, discount = 0, gst = 0;
    state.cart.forEach((l) => {
      const c = calcLine(l);
      subtotal += c.gross; discount += c.disc; gst += c.gstAmt;
    });
    const discVal = parseFloat($('#posGlobalDisc')?.value) || 0;
    const base = subtotal - discount;
    const billDisc = state.discMode === 'flat'
      ? Math.max(0, Math.min(discVal, base))                       // ₹ off the bill, never below zero
      : base * (Math.max(0, Math.min(100, discVal)) / 100);        // % off the bill
    const billDiscPct = base > 0 ? (billDisc / base) * 100 : 0;    // effective % for the sale API
    discount += billDisc;
    const net = subtotal - discount;
    const grand = Math.round(net);
    const roundOff = grand - net;
    return { subtotal, discount, gst, net, grand, roundOff, billDisc, billDiscPct };
  }

  /* Keyboard cart editing — arrows pick a line (mouse-free), +/− qty, Del removes.
   Render-safe: syncCartKb() re-asserts the highlight after every renderCart(). */
  function setCartIdx(i) {
    state.cartIdx = state.cart.length ? Math.max(-1, Math.min(state.cart.length - 1, i)) : -1;
    $('#posCartBody')?.querySelectorAll('tbody tr[data-ki]').forEach((tr) =>
      tr.classList.toggle('is-kb', +tr.dataset.ki === state.cartIdx));
    if (state.cartIdx >= 0) $('#posCartBody')?.querySelector('tr.is-kb')?.scrollIntoView({ block: 'nearest' });
  }
  function cartKbAdjust(delta) {
    const btn = $('#posCartBody')?.querySelector(`[data-a="${delta > 0 ? 'inc' : 'dec'}"][data-i="${state.cartIdx}"]`);
    if (btn) btn.click(); // reuse the stock-clamped UI path — never invent stock math twice
  }
  function cartKbRemove() {
    if (state.cartIdx < 0) return;
    const btn = $('#posCartBody')?.querySelector(`[data-a="rm"][data-i="${state.cartIdx}"]`);
    if (btn) btn.click();
  }
  function syncCartKb() {
    const hint = $('#posKbHint'); if (hint) hint.hidden = state.cart.length === 0;
    rxNudgeSync(); // cart emptied/loaded — glow follows the cart's Rx state
    if (state.cartIdx >= state.cart.length) state.cartIdx = state.cart.length - 1;
    $('#posCartBody')?.querySelectorAll('tbody tr[data-ki]').forEach((tr) =>
      tr.classList.toggle('is-kb', +tr.dataset.ki === state.cartIdx));
  }
  function renderCart() {
    const box = $('#posCartBody');
    if (!state.cart.length) {
      box.innerHTML = `<div class="empty-state py-5"><i class="bi bi-cart3"></i>Cart is empty.<br><span class="small">Search a medicine on the left and click to add.</span></div>`;
    } else {
      box.innerHTML = `
        <div class="table-scroll" style="max-height:330px">
        <table class="table table-mf">
          <thead><tr><th>Medicine</th><th class="text-center">Qty</th><th class="text-end">Rate</th><th class="text-center">Disc%</th><th class="text-end">Amount</th><th></th></tr></thead>
          <tbody>
          ${state.cart.map((l, i) => {
            const med = MF.med(l.medId);
            const b = D.batches.find((x) => x.id === l.batchId);
            const c = calcLine(l);
            return `<tr data-ki="${i}" tabindex="-1" title="Click to select line for keyboard editing">
              <td style="min-width:170px">
                <div class="td-title">${MF.esc(med.name)}</div>
                <div class="td-sub num">B: ${b.batchNo} · Exp ${MF.fmtMonthYear(b.expiry)} · GST ${med.gst}%${l.unit === 'loose' ? ` · Loose` : ''}${l.kind === 'wholesale' ? '<span class="pos-ws">WS</span>' : ''}</div>
                <div class="td-sub num">${lineMeasure(l, med)}</div>
              </td>
              <td class="text-center">
                <div class="qty-stepper">
                  <button data-a="dec" data-i="${i}" type="button">−</button>
                  <input value="${l.qty}" data-a="qty" data-i="${i}" inputmode="numeric">
                  <button data-a="inc" data-i="${i}" type="button">+</button>
                </div>
              </td>
              <td class="text-end num">${MF.fmt(l.rate, 2)}</td>
              <td class="text-center">
                <input class="form-control form-control-sm text-center" style="width:60px;display:inline-block" value="${l.discPct}" data-a="disc" data-i="${i}" inputmode="decimal">
              </td>
              <td class="text-end num fw-semibold">${MF.fmt(c.net, 2)}</td>
              <td><button class="btn btn-icon btn-light-mf text-danger" data-a="rm" data-i="${i}" title="Remove"><i class="bi bi-trash3"></i></button></td>
            </tr>`;
          }).join('')}
          </tbody>
        </table>
        </div>`;
      box.querySelectorAll('[data-a]').forEach((el) => {
        el.addEventListener(el.tagName === 'INPUT' ? 'change' : 'click', () => {
          const i = +el.dataset.i, l = state.cart[i], a = el.dataset.a;
          const med = MF.med(l.medId);
          const room = lineRoom(l);
          if (a === 'inc') {
            const left = l.unit === 'loose' ? room.tablets : room.strips;
            if (left <= 0) {
              const word = l.unit === 'loose' ? withCount(l.qty, pieceLabel(med)) : withCount(l.qty, unitLabel(med));
              MF.toast(`Only ${l.qty} ${word} left in this batch`, 'warn', 'Stock');
            } else l.qty++;
          }
          if (a === 'dec') l.qty = Math.max(1, l.qty - 1);
          if (a === 'qty') {
            const extra = l.unit === 'loose' ? room.tablets : room.strips;
            l.qty = Math.max(1, Math.min(l.qty + extra, parseInt(el.value) || 1));
          }
          if (a === 'disc') {
            let v = Math.max(0, Math.min(100, parseFloat(el.value) || 0));
            if (!state.rolePrivileged && v > DISC_CAP_STAFF) {
              v = DISC_CAP_STAFF;
              MF.toast(`Cashier discount ceiling is ${DISC_CAP_STAFF}% per line — an admin login can go higher.`, 'warn', 'Discount cap');
            }
            l.discPct = v;
          }
          if (a === 'rm') state.cart.splice(i, 1);
          renderCart(); renderSummary();
        });
      });
      box.querySelectorAll('tbody tr[data-ki]').forEach((tr) =>
        tr.addEventListener('click', (e) => { if (!e.target.closest('button,input,select,a')) setCartIdx(+tr.dataset.ki); }));
    }
    renderSummary();
    syncCartKb();
    renderRxChip();
    refreshPicks();
    refreshDraftBtn();
  }

  /* Redraw Quick picks / search cards so strip + tablet counts follow the cart. */
  function refreshPicks() {
    const input = $('#posSearch');
    if (!input || !$('#posResults')) return;
    searchMeds(input.value || '');
  }

  /* "Rupees One Thousand Two Hundred Thirty-Four Only" — Indian numbering. */
  function inrWords(n) {
    const num = Math.round(Math.abs(Number(n) || 0));
    if (!num) return '';
    const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
      'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    const two = (x) => (x < 20 ? ONES[x] : TENS[Math.floor(x / 10)] + (x % 10 ? '-' + ONES[x % 10] : ''));
    const three = (x) => {
      const h = Math.floor(x / 100), r = x % 100;
      return (h ? ONES[h] + ' Hundred' + (r ? ' ' : '') : '') + (r ? two(r) : '');
    };
    let out = '';
    const crore = Math.floor(num / 10000000);
    const lakh = Math.floor(num / 100000) % 100;
    const thou = Math.floor(num / 1000) % 100;
    const rest = num % 1000;
    if (crore) out += three(crore) + ' Crore ';
    if (lakh) out += two(lakh) + ' Lakh ';
    if (thou) out += two(thou) + ' Thousand ';
    if (rest) out += three(rest);
    return 'Rupees ' + out.trim() + ' Only';
  }

  /* Savings vs selling everything at full MRP (pack MRP prorated for loose lines). */
  function savedVsMrp(t) {
    let atMrp = 0;
    state.cart.forEach((l) => {
      const med = MF.med(l.medId);
      const packMrp = Number(l.mrp) || Number(med && med.mrp) || 0;
      atMrp += (l.unit === 'loose' && med) ? l.qty * (packMrp / packSize(med)) : l.qty * packMrp;
    });
    return Math.max(0, atMrp - t.grand);
  }

  /* ---------- Bill context strip (invoice · clock · cashier · counter · connection) ---------- */
  function paintMetaClock() {
    const now = new Date();
    const d = $('#posMetaDate'), t = $('#posMetaTime');
    if (d) d.textContent = now.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    if (t) t.textContent = now.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: false });
  }
  function paintMetaNet() {
    const bar = $('#posMetaBar'), txt = $('#posMetaNetTxt');
    const online = navigator.onLine;
    if (bar) bar.classList.toggle('is-off', !online);
    if (txt) txt.textContent = online ? 'Online' : 'Offline';
  }
  function nextInvoiceGuess() {
    const raw = localStorage.getItem('mf-pos-nextinv') || '';
    if (!raw) return '— auto —';
    const m = raw.match(/^(.*?)(\d{4})-(\d+)$/);
    if (m && m[2] !== String(new Date().getFullYear())) return `${m[1]}${new Date().getFullYear()}-0001`; // year rollover
    return raw;
  }
  function paintMetaInvoice() {
    const el = $('#posMetaInv');
    if (el) el.textContent = nextInvoiceGuess();
  }
  function noteCompletedInvoice(inv) {
    const m = String(inv || '').match(/^(.*?)(\d{3,})$/);
    if (!m) return; // unexpected format — leave the strip as-is
    localStorage.setItem('mf-pos-nextinv', m[1] + String(parseInt(m[2], 10) + 1).padStart(m[2].length, '0'));
    paintMetaInvoice();
  }
  function initPosMeta() {
    if (!$('#posMetaBar')) return;
    const c = $('#posMetaCounter');
    if (c) c.textContent = localStorage.getItem('mf-pos-counter') || '1';
    paintMetaClock(); setInterval(paintMetaClock, 15000);
    paintMetaNet();
    addEventListener('online', paintMetaNet);
    addEventListener('offline', paintMetaNet);
    paintMetaInvoice();
  }

  function paintDiscToggle() {
    const pct = $('#posDiscPct'), rs = $('#posDiscRs');
    if (pct) pct.classList.toggle('is-on', state.discMode !== 'flat');
    if (rs) rs.classList.toggle('is-on', state.discMode === 'flat');
    const inp = $('#posGlobalDisc');
    if (inp) inp.title = state.discMode === 'flat' ? 'Flat rupees off the bill' : 'Percent off the bill';
  }
  function setDiscMode(mode) {
    state.discMode = mode === 'flat' ? 'flat' : 'percent';
    paintDiscToggle();
    renderSummary();
  }

  function renderSummary() {
    const t = calcTotals();
    const saved = savedVsMrp(t);
    $('#posSummary').innerHTML = `
      <div class="sum-row"><span class="text-2">Subtotal (MRP)</span><span class="num">${MF.fmt(t.subtotal, 2)}</span></div>
      <div class="sum-row"><span class="text-2">Discount</span><span class="num text-danger">− ${MF.fmt(t.discount, 2)}</span></div>
      <div class="sum-row"><span class="text-2">GST included in MRP</span><span class="num">${MF.fmt(t.gst, 2)}</span></div>
      <div class="sum-row"><span class="text-2">Round off</span><span class="num">${t.roundOff >= 0 ? '+' : '−'} ${MF.fmt(Math.abs(t.roundOff), 2)}</span></div>
      <div class="sum-row total"><span>Grand Total</span><span class="num text-primary">${MF.fmt(t.grand)}</span></div>
      ${t.grand > 0 ? `<div class="pos-sum-words">${inrWords(t.grand)}</div>` : ''}
      ${saved >= 0.5 ? `<div style="text-align:right"><span class="pos-saved"><i class="bi bi-piggy-bank"></i>You saved ${MF.fmt(saved, 2)}</span></div>` : ''}`;
    const amt = $('#posCompleteAmt');
    if (amt) amt.textContent = MF.fmt(t.grand);
    renderUpiPanel();
  }

  function needsRx(line) {
    const med = MF.med(line.medId);
    return !!(med && (med.rxRequired || rxSchedule(med)));
  }

  /* First Schedule H/H1/X/NDPS line in the cart → its schedule label, else ''. */
  function autoRxSchedule() {
    for (const l of state.cart) {
      const med = MF.med(l.medId);
      const sch = med && rxSchedule(med);
      if (sch) return sch;
    }
    return '';
  }

  /* Rx nudge, two tiers:
     purple (.is-rx)      = add-time guidance, clears as fields get resolved;
     red    (.is-rx-flag) = the complete-sale gate STOPPED on this field.
     Nothing here blocks by itself — the gate in completeSale decides. */
  const rxFlag = { cust: false, doc: false };
  function rxNudgeSync() {
    const custWrap = pickerState['#posCustomer'] && pickerState['#posCustomer'].wrap;
    const docWrap = pickerState['#posDoctor'] && pickerState['#posDoctor'].wrap;
    if (!custWrap || !docWrap) return;
    const hasRx = state.cart.some(needsRx);
    const isWalkin = String($('#posCustomer').value) === String(walkInId());
    const docMissing = !$('#posDoctor').value;
    if (!hasRx) { rxFlag.cust = false; rxFlag.doc = false; }
    if (!isWalkin) rxFlag.cust = false;
    if (!docMissing) rxFlag.doc = false;
    custWrap.classList.toggle('is-rx-flag', hasRx && isWalkin && rxFlag.cust);
    docWrap.classList.toggle('is-rx-flag', hasRx && docMissing && rxFlag.doc);
    custWrap.classList.toggle('is-rx', hasRx && isWalkin && !(rxFlag.cust && isWalkin));
    docWrap.classList.toggle('is-rx', hasRx && docMissing && !(rxFlag.doc && docMissing));
  }

  /* Turn the Rx switch on ourselves — scheduled drugs force it, OTC stays manual. */
  function setRxOn(sch) {
    const toggle = $('#posRxOn');
    if (!toggle) return;
    const wasOff = !toggle.checked;
    toggle.checked = true;
    const panel = $('#posRxPanel');
    if (panel) panel.hidden = false;
    if (wasOff) {
      MF.toast('Schedule ' + sch + ' item in the cart — Rx verification is on for this bill.', 'info', 'Rx auto-verification');
      loadRxOptions().then(() => { if (state._rxBill) wireRxAttachment(state._rxBill); }).catch(() => {});
    }
  }

  function renderRxChip() {
    const chip = $('#posRxChip');
    const wrap = $('#posRxToggleWrap');
    const hasRx = state.cart.some(needsRx);
    const show = hasRx || !!state._rxBill;
    if (chip) chip.style.display = show ? 'inline-flex' : 'none';
    if (wrap) {
      wrap.hidden = !show;
      wrap.classList.toggle('show', show);
    }
    const toggle = $('#posRxOn');
    if (show) {
      const sch = autoRxSchedule();
      if (sch) setRxOn(sch);
    } else {
      const panel = $('#posRxPanel');
      if (panel) panel.hidden = true;
      if (toggle) toggle.checked = false;
    }
  }

  async function fillDoctors() {
    const sel = $('#posDoctor');
    if (!sel || sel.tagName !== 'SELECT') return;
    const current = sel.value;
    let rows = Array.isArray(D.doctors) ? D.doctors.slice() : [];
    if (MF.Api && MF.Api.live) {
      try {
        const res = await MF.Api.get('doctors.php');
        const data = res.data;
        const list = Array.isArray(data) ? data : ((data && data.doctors) || []);
        if (list.length) rows = list;
      } catch (e) { /* keep bootstrap doctors */ }
    }
    rows = rows.filter((d) => d && d.name && d.status !== 'Inactive');
    rows.sort((a, b) => String(a.name).localeCompare(String(b.name)));
    sel.innerHTML = '<option value="">— Walk-in / none —</option>' + rows.map((d) => `<option value="${MF.esc(d.id)}">${MF.esc(d.name)}${d.specialty ? ' — ' + MF.esc(d.specialty) : ''}</option>`).join('');
    const wanted = current || (state._rxBill && state._rxBill.doctorId) || '';
    if (wanted && ![...sel.options].some((o) => String(o.value) === String(wanted))) {
      const name = (state._rxBill && state._rxBill.doctorName) || 'Doctor';
      sel.insertAdjacentHTML('beforeend', `<option value="${MF.esc(wanted)}">${MF.esc(name)}</option>`);
    }
    if (wanted) sel.value = String(wanted);
  }
  MF.refillPosDoctors = fillDoctors;   // hook for the quick-add modal on the POS page

  /* ---------------- Payments ---------------- */
  function bindPayments() {
    /* Customer type change re-prices the whole cart — retail ↔ wholesale rates.
       Discounts typed so far stay; only the base rate follows the account. */
    const repriceCart = () => {
      if (!state.cart.length) return;
      let toWs = 0, toRetail = 0;
      state.cart.forEach((l) => {
        const m = MF.med(l.medId);
        const b = D.batches.find((x) => String(x.id) === String(l.batchId));
        if (!m) return;
        const px = sellPriceOf(m, b);
        const newRate = l.unit === 'loose' ? px.rate / packSize(m) : px.rate;
        if (Math.abs(newRate - l.rate) > 0.004) {
          l.rate = newRate;
          px.kind === 'wholesale' ? toWs++ : toRetail++;
        }
        l.kind = px.kind;
      });
      if (toWs) MF.toast(`${toWs} line(s) re-priced at wholesale rates.`, 'info', 'Wholesale');
      else if (toRetail) MF.toast(`${toRetail} line(s) re-priced at retail rates.`, 'info', 'Retail pricing');
      renderCart();
    };
    $('#posCustomer').addEventListener('change', repriceCart);

    /* Role for the discount cap — resolved once, fail-open for admins. */
    posRole().then((r) => { state.rolePrivileged = r.privileged !== false; });

    document.querySelectorAll('input[name="posPay"]').forEach((r) =>
      r.addEventListener('change', () => {
        state.payment = r.value;
        if (r.value === 'credit') {
          const cust = $('#posCustomer').value;
          if (MF.cust(cust)?.name === 'Walk-in Customer') { MF.toast('Credit sales are not allowed for Walk-in Customer', 'err', 'Payment'); $('#posPayCash').checked = true; state.payment = 'cash'; }
          else MF.toast('Due will be posted to the customer ledger', 'info', 'Credit sale');
        }
        if (r.value === 'split') new bootstrap.Modal($('#posSplitModal')).show();
        renderUpiPanel();
      }));
    ensureSplitQr();
    $('#posSplitModal')?.addEventListener('shown.bs.modal', renderSplitQr);
    $('#posSplitApply').addEventListener('click', () => {
      const t = calcTotals();
      const cash = parseFloat($('#splitCash').value) || 0;
      const upi = parseFloat($('#splitUpi').value) || 0;
      if (Math.abs(cash + upi - t.grand) > 0.5) { MF.toast(`Split amounts must equal Grand Total ${MF.fmt(t.grand)}`, 'warn', 'Split payment'); return; }
      state.split = { cash, upi };
      bootstrap.Modal.getInstance($('#posSplitModal')).hide();
      MF.toast(`Split saved — Cash ${MF.fmt(cash)} + UPI ${MF.fmt(upi)}`, 'success', 'Split payment');
      renderUpiPanel();
    });
  }

  /* Dynamic UPI QR — amount is written into the code so the scan opens that exact figure. */
  const UPI_KEY = 'mf-store-upi';

  function storeUpi() {
    const s = (D && D.store) || {};
    const settings = (D && D.settings) || {};
    const found = s.upi || s.upiId || s.upiVpa || s.upi_id || s.upi_vpa || s.vpa
      || settings.upi || settings.upi_id || settings.upi_vpa || settings.vpa || '';
    if (String(found).trim()) return String(found).trim();
    try { return (localStorage.getItem(UPI_KEY) || '').trim(); } catch (e) { return ''; }
  }

  function saveStoreUpi(raw) {
    const clean = String(raw || '').trim().replace(/\s+/g, '');
    if (!/^[\w.\-]{2,}@[A-Za-z0-9]{2,64}$/.test(clean)) return false;
    try { localStorage.setItem(UPI_KEY, clean); } catch (e) { /* private mode */ }
    if (D) {
      D.store = D.store || {};
      D.store.upi = clean;
    }
    state.upiSig = '';
    return true;
  }

  function upiRefFor(amount) {
    const lines = state.cart.map((l) => [l.medId, l.batchId, l.qty, l.rate, l.discPct, l.unit].join(':')).join(',');
    const sig = [state.payment, Number(amount).toFixed(2), storeUpi(), lines].join('|');
    if (state.upiSig !== sig) {
      state.upiSig = sig;
      state.upiRef = 'P' + Date.now().toString(36).toUpperCase();
    }
    return state.upiRef;
  }

  function upiPayUri(amount, ref) {
    const pa = storeUpi();
    if (!pa || !(Number(amount) > 0)) return '';
    const pn = String((D && D.store && D.store.name) || 'Store').slice(0, 40);
    const am = Number(amount).toFixed(2);
    const note = ('Bill ' + (ref || '')).trim().slice(0, 40);
    const pairs = [['pa', pa], ['pn', pn], ['am', am], ['mam', am], ['cu', 'INR'], ['tn', note]];
    if (ref) pairs.push(['tr', String(ref).slice(0, 35)]);
    return 'upi://pay?' + pairs.map(([k, v]) => k + '=' + encodeURIComponent(v).replace(/%40/g, '@')).join('&');
  }

  function paintQr(host, text, size) {
    if (!host || !text) return;
    if (typeof QRCode === 'undefined') {
      host.innerHTML = '<div class="pos-upi-empty">QR code could not be drawn.</div>';
      return;
    }
    let qr = host._upiQr;
    if (!qr || host.dataset.size !== String(size)) {
      host.innerHTML = '';
      const el = document.createElement('div');
      host.appendChild(el);
      qr = new QRCode(el, {
        text: text,
        width: size,
        height: size,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
      });
      host._upiQr = qr;
      host.dataset.size = String(size);
      host.dataset.text = text;
      return;
    }
    if (host.dataset.text !== text) {
      qr.makeCode(text);
      host.dataset.text = text;
    }
  }

  function bindUpiSave(root, after) {
    const btn = root.querySelector('[data-upi-save]');
    if (!btn || btn.dataset.bound) return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', () => {
      const input = root.querySelector('[data-upi-input]');
      if (!saveStoreUpi(input && input.value)) {
        MF.toast('Enter a UPI ID like store@okaxis', 'warn', 'UPI ID');
        return;
      }
      MF.toast('UPI ID saved on this counter', 'success', 'UPI');
      if (after) after();
    });
    const change = root.querySelector('[data-upi-change]');
    if (change) change.addEventListener('click', () => {
      const setup = root.querySelector('[data-upi-setup]');
      const ready = root.querySelector('[data-upi-ready]');
      const input = root.querySelector('[data-upi-input]');
      if (setup) setup.hidden = false;
      if (ready) ready.hidden = true;
      if (input) { input.value = storeUpi(); input.focus(); }
    });
  }

  function upiSetupHtml() {
    return `<div data-upi-setup>
      <div class="pos-upi-k">Store UPI ID</div>
      <p class="pos-upi-note">Add the shop UPI ID once. The QR carries the bill amount, so the customer’s app opens on that exact figure.</p>
      <div class="pos-upi-form">
        <input class="form-control" data-upi-input placeholder="store@okaxis" autocomplete="off" spellcheck="false" value="${MF.esc(storeUpi())}">
        <button type="button" class="btn btn-mf" data-upi-save>Save</button>
      </div>
    </div>`;
  }

  function ensureUpiPanel() {
    if (document.getElementById('posUpiPanel') || !document.getElementById('posPayUpi')) return;
    const row = document.getElementById('posPayUpi').closest('.row');
    if (!row) return;
    const panel = document.createElement('div');
    panel.id = 'posUpiPanel';
    panel.className = 'pos-upi';
    panel.hidden = true;
    panel.innerHTML = upiSetupHtml() + `
      <div class="pos-upi-ready" data-upi-ready hidden>
        <div class="pos-upi-row">
          <div class="pos-upi-code" data-upi-code aria-label="UPI payment QR"></div>
          <div>
            <div class="pos-upi-k">Scan to pay</div>
            <div class="pos-upi-amt" data-upi-amt>—</div>
            <div class="pos-upi-vpa" data-upi-vpa></div>
            <div class="pos-upi-note">Amount is fixed in the QR. It updates when the bill changes.</div>
            <button type="button" class="btn btn-link btn-sm p-0 mt-1" data-upi-change>Change UPI ID</button>
          </div>
        </div>
      </div>`;
    row.insertAdjacentElement('afterend', panel);
    bindUpiSave(panel, renderUpiPanel);
  }

  function renderUpiPanel() {
    ensureUpiPanel();
    const panel = document.getElementById('posUpiPanel');
    if (!panel) return;
    const show = state.payment === 'upi';
    panel.hidden = !show;
    if (!show) return;
    const amount = state.cart.length ? Math.round(calcTotals().grand) : 0;
    const vpa = storeUpi();
    const setup = panel.querySelector('[data-upi-setup]');
    const ready = panel.querySelector('[data-upi-ready]');
    if (!vpa) {
      if (setup) setup.hidden = false;
      if (ready) ready.hidden = true;
      return;
    }
    if (setup) setup.hidden = true;
    if (ready) ready.hidden = false;
    const vpaEl = panel.querySelector('[data-upi-vpa]');
    const amtEl = panel.querySelector('[data-upi-amt]');
    if (vpaEl) vpaEl.textContent = vpa;
    if (amtEl) amtEl.textContent = amount ? MF.fmt(amount) : '—';
    const code = panel.querySelector('[data-upi-code]');
    if (!amount) {
      if (code) {
        code.innerHTML = '<div class="pos-upi-empty">Add items to generate the QR</div>';
        code._upiQr = null;
        code.dataset.text = '';
      }
      return;
    }
    paintQr(code, upiPayUri(amount, upiRefFor(amount)), 148);
  }

  function ensureSplitQr() {
    const input = document.getElementById('splitUpi');
    if (!input || document.getElementById('posSplitQr')) return;
    const box = document.createElement('div');
    box.id = 'posSplitQr';
    box.className = 'pos-upi-split';
    box.hidden = true;
    box.innerHTML = `<div class="pos-upi-k">Scan UPI portion</div><div class="pos-upi-amt" data-split-amt></div><div class="pos-upi-code" data-split-code></div><div class="pos-upi-note">This code is only the UPI part of the split.</div>`;
    input.parentElement.insertAdjacentElement('afterend', box);
    input.addEventListener('input', renderSplitQr);
  }

  function renderSplitQr() {
    ensureSplitQr();
    const box = document.getElementById('posSplitQr');
    const input = document.getElementById('splitUpi');
    if (!box || !input) return;
    const amount = parseFloat(input.value) || 0;
    const vpa = storeUpi();
    if (!(amount > 0)) { box.hidden = true; return; }
    const amt = box.querySelector('[data-split-amt]');
    const code = box.querySelector('[data-split-code]');
    if (!vpa) {
      box.hidden = false;
      if (amt) amt.textContent = 'Add the store UPI ID on the UPI payment tile first.';
      if (code) code.hidden = true;
      return;
    }
    box.hidden = false;
    if (code) code.hidden = false;
    if (amt) amt.textContent = MF.fmt(amount, 2);
    paintQr(code, upiPayUri(amount, upiRefFor(amount)), 148);
  }

  /* ---------------- Held bills ---------------- */
  const DRAFT_KEY = 'mf-pos-draft';

  function refreshDraftBtn() {
    const btn = $('#posDraft');
    if (!btn) return;
    let has = false;
    try { has = !!localStorage.getItem(DRAFT_KEY); } catch (e) { has = false; }
    const load = (!state.cart.length && has);
    btn.innerHTML = `<span><i class="bi ${load ? 'bi-folder2-open' : 'bi-save'} me-1"></i>${load ? 'Load' : 'Save'} Draft</span><span class="pos-act-key">F9</span>`;
  }

  function saveDraft() {
    if (!state.cart.length) { loadDraft(); return; }
    const draft = {
      cart: state.cart,
      customer: $('#posCustomer') ? $('#posCustomer').value : '',
      doctor: $('#posDoctor') ? $('#posDoctor').value : '',
      disc: $('#posGlobalDisc') ? $('#posGlobalDisc').value : '0',
      discMode: state.discMode,
      payment: state.payment,
    };
    try { localStorage.setItem(DRAFT_KEY, JSON.stringify(draft)); }
    catch (e) { MF.toast('Could not save the draft on this browser.', 'err', 'Save Draft'); return; }
    refreshDraftBtn();
    MF.toast('Draft saved on this counter. Clear the cart and press F9 to load it.', 'success', 'Save Draft');
  }

  function loadDraft() {
    let draft = null;
    try { draft = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (e) { draft = null; }
    if (!draft || !Array.isArray(draft.cart) || !draft.cart.length) {
      MF.toast('No draft saved on this counter.', 'warn', 'Save Draft');
      return;
    }
    state.cart = draft.cart;
    if ($('#posCustomer') && draft.customer) $('#posCustomer').value = draft.customer;
    if ($('#posDoctor') && draft.doctor) $('#posDoctor').value = draft.doctor;
    if (window.POSUI) POSUI.syncPickers();
    if ($('#posGlobalDisc')) $('#posGlobalDisc').value = draft.disc || 0;
    if (draft.discMode) { state.discMode = draft.discMode === 'flat' ? 'flat' : 'percent'; paintDiscToggle(); }
    if (draft.payment) {
      state.payment = draft.payment;
      const radio = document.querySelector(`input[name="posPay"][value="${draft.payment}"]`);
      if (radio) radio.checked = true;
    }
    renderCart();
    MF.toast('Draft loaded into the cart.', 'success', 'Save Draft');
  }

  function printInvoice() {
    if (!state.cart.length) { MF.toast('Cart is empty — nothing to print', 'warn'); return; }
    MF.printHtml(receiptHtml('DRAFT', calcTotals(), state.printFmt));
  }

  function paintPrintBtn() {
    const lbl = $('#posPrintLbl');
    if (lbl) lbl.textContent = state.printFmt === 'thermal' ? '80mm' : 'A4';
  }

  async function clearCart() {
    if (!state.cart.length) return;
    const ok = await MF.confirm({ title: 'Clear current bill?', message: 'All cart items will be removed.', confirmText: 'Clear', tone: 'danger' });
    if (ok) { state.cart = []; renderCart(); }
  }

  function selectPay(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.checked = true;
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function holdBill() {
    if (!state.cart.length) { MF.toast('Cart is empty — nothing to hold', 'warn'); return; }
    state.heldBills.push({ id: state.holdSeq++, customer: $('#posCustomer').value, items: JSON.parse(JSON.stringify(state.cart)), at: Date.now() });
    state.cart = []; renderCart();
    updateHoldBadge();
    MF.toast('Bill held. Retrieve it from the Held Bills chip.', 'info', 'Bill held');
  }

  function updateHoldBadge() {
    const b = $('#posHeldBadge');
    if (!b) return;
    b.textContent = state.heldBills.length;
    b.style.display = state.heldBills.length ? 'inline-flex' : 'none';
  }

  function showHeldBills() {
    const body = $('#posHeldBody');
    if (!state.heldBills.length) { body.innerHTML = `<div class="empty-state"><i class="bi bi-hourglass"></i>No held bills.</div>`; }
    else {
      body.innerHTML = `<div class="table-mf border rounded">${state.heldBills.map((h) => {
        const cust = MF.cust(h.customer);
        const custName = cust ? cust.name : 'Customer';
        const amount = h.items.reduce((s, l) => s + l.qty * l.rate * (1 - (Number(l.discPct) || 0) / 100), 0);
        const units = h.items.reduce((s, l) => s + l.qty, 0);
        const names = h.items.slice(0, 2).map((l) => (MF.med(l.medId) || {}).name).filter(Boolean);
        const itemsTxt = names.join(' · ') + (h.items.length > 2 ? ` +${h.items.length - 2} more` : '');
        const time = h.at ? new Date(h.at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) : '';
        return `
        <div class="d-flex align-items-center justify-content-between gap-2 p-2 border-bottom">
          <div class="min-w-0">
            <div class="fw-semibold small">Hold #${h.id} · ${MF.esc(custName)} · <span class="num">${MF.fmt(amount)}</span></div>
            <div class="text-2 small-xs">${time ? MF.esc(time) + ' · ' : ''}${h.items.length} item(s) · ${MF.num(units)} unit(s)</div>
            ${itemsTxt ? `<div class="text-2 small-xs text-truncate" title="${MF.esc(itemsTxt)}"><i class="bi bi-capsule me-1"></i>${MF.esc(itemsTxt)}</div>` : ''}
          </div>
          <div class="d-flex gap-2 flex-shrink-0">
            <button class="btn btn-sm btn-light-mf" data-load="${h.id}">Load</button>
            <button class="btn btn-sm btn-light-mf text-danger" data-del="${h.id}"><i class="bi bi-trash3"></i></button>
          </div>
        </div>`;
      }).join('')}</div>`;
      body.querySelectorAll('[data-load]').forEach((b) => b.addEventListener('click', () => {
        const h = state.heldBills.find((x) => x.id === +b.dataset.load);
        state.cart = h.items; $('#posCustomer').value = h.customer;
        if (window.POSUI) POSUI.syncPickers();
        state.heldBills = state.heldBills.filter((x) => x.id !== h.id);
        updateHoldBadge(); renderCart();
        bootstrap.Modal.getInstance($('#posHeldModal')).hide();
        MF.toast('Held bill loaded into cart', 'success');
      }));
      body.querySelectorAll('[data-del]').forEach((b) => b.addEventListener('click', () => {
        state.heldBills = state.heldBills.filter((x) => x.id !== +b.dataset.del);
        updateHoldBadge(); showHeldBills();
      }));
    }
  }

  /* ---------------- Complete sale ---------------- */
  const TENDER_NOTES = [2000, 500, 200, 100, 50, 20, 10, 5, 2, 1];

  function tenderSuggestions(total) {
    const vals = new Set([total]);
    [10, 50, 100].forEach((step) => {
      const up = Math.ceil(total / step) * step;
      if (up > total && up - total <= 100) vals.add(up);
    });
    [200, 500, 2000].forEach((n) => { if (n > total) vals.add(n); });
    return [...vals].sort((a, b) => a - b).slice(0, 6);
  }

  function changeBreakdown(change) {
    let left = Math.max(0, Math.round(change));
    const out = [];
    TENDER_NOTES.forEach((n) => {
      const count = Math.floor(left / n);
      if (count > 0) { out.push([n, count]); left -= count * n; }
    });
    return out;
  }

  function noteSwatch(n) {
    return { 2000: '#c45c93', 500: '#7d8f78', 200: '#e07a3d', 100: '#7d72b8', 50: '#3f9a4a', 20: '#c4a035', 10: '#c47a45' }[n] || '#8d97a3';
  }

  /* Cash: amount received, change, and the notes to hand back.
     Other modes just confirm the amount — there is no change to calculate. */
  function openTender(t) {
    return new Promise((resolve) => {
      let host = document.getElementById('pos-tender-root');
      if (!host) {
        host = document.createElement('div');
        host.id = 'pos-tender-root';
        document.body.appendChild(host);
      }
      const due = Math.round(t.grand);
      const cash = state.payment === 'cash';
      const cust = MF.cust($('#posCustomer').value);
      const who = MF.esc(cust ? cust.name : 'Customer');
      const payLabel = { cash: 'Cash', upi: 'UPI', card: 'Card', credit: 'Credit', split: 'Split' }[state.payment] || state.payment;
      const roundSign = t.roundOff >= 0 ? '+' : '−';
      let paid = cash ? String(due) : '';
      let result = null;

      const upiAmount = state.payment === 'upi' ? due : (state.payment === 'split' ? (Number(state.split.upi) || 0) : 0);
      const showQr = upiAmount > 0;
      const headIcon = showQr ? 'qr-code-scan' : 'cash-stack';
      const otherCopy = {
        upi: ['Collect on UPI', `Show the customer ${MF.fmt(due)} and wait for the success screen. No change on a UPI sale.`],
        card: ['Charge the card', `Swipe or tap for ${MF.fmt(due)}. Change is not calculated for card payments.`],
        credit: ['Post to customer dues', `${MF.fmt(due)} will be added to the customer ledger.`],
        split: ['Split already balanced', `Cash ${MF.fmt(state.split.cash)} + UPI ${MF.fmt(state.split.upi)}. No change to return.`]
      }[state.payment] || ['Confirm sale', `Collect ${MF.fmt(due)}.`];

      host.innerHTML = `
        <div class="modal fade pos-tender" tabindex="-1">
          <div class="modal-dialog modal-dialog-centered pos-tender-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <div>
                  <h5 class="modal-title mb-0"><i class="bi bi-${headIcon} me-2 text-success"></i>Complete sale</h5>
                  <div class="text-2 small">${payLabel} · ${who} · ${state.cart.length} item(s)</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="pos-tender-collect">
                  <div>
                    <div class="pos-tender-k">Amount to collect</div>
                    <div class="pos-tender-due">${MF.fmt(due)}</div>
                  </div>
                  <div class="pos-tender-meta">Subtotal ${MF.fmt(t.subtotal, 2)}<br>Round off ${roundSign} ${MF.fmt(Math.abs(t.roundOff), 2)}</div>
                </div>
                ${cash ? `
                <div class="pos-tender-label" id="posTenderPaidLabel">Cash received</div>
                <div class="pos-tender-money">
                  <span>₹</span>
                  <input id="posTenderPaid" inputmode="numeric" autocomplete="off" aria-labelledby="posTenderPaidLabel" value="${paid}">
                </div>
                <div class="pos-tender-chips" id="posTenderChips"></div>
                <div class="pos-tender-work">
                  <div id="posTenderChange"></div>
                  <div class="pos-tender-pad" aria-label="Keypad">
                    ${[1, 2, 3, 4, 5, 6, 7, 8, 9].map((n) => `<button type="button" data-key="${n}">${n}</button>`).join('')}
                    <button type="button" class="ghost" data-key="c">Clear</button>
                    <button type="button" data-key="0">0</button>
                    <button type="button" class="ghost" data-key="b" aria-label="Backspace">⌫</button>
                  </div>
                </div>` : `
                <div id="posTenderExtra"></div>`}
              </div>
              <p class="pos-tender-fine">Stock will be deducted from the earliest expiry batch.</p>
              <div class="modal-footer">
                <button type="button" class="btn btn-light-mf" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-mf" id="posTenderOk">Complete sale</button>
              </div>
            </div>
          </div>
        </div>`;

      const modalEl = host.querySelector('.modal');
      const modal = new bootstrap.Modal(modalEl);
      state.tenderOpen = true;

      function paintTenderExtra() {
        const extra = host.querySelector('#posTenderExtra');
        if (!extra) return;
        if (!showQr) {
          extra.innerHTML = `<div class="pos-tender-note"><strong>${MF.esc(otherCopy[0])}</strong>${MF.esc(otherCopy[1])}</div>`;
          return;
        }
        const vpa = storeUpi();
        if (!vpa) {
          extra.innerHTML = `<div class="pos-tender-qr">${upiSetupHtml()}<p class="pos-upi-note">You can still complete the sale if the customer pays on another UPI code.</p></div>`;
          bindUpiSave(extra, () => { renderUpiPanel(); paintTenderExtra(); });
          return;
        }
        const label = state.payment === 'split' ? 'Scan the UPI portion' : 'Scan to pay';
        extra.innerHTML = `<div class="pos-tender-qr">
          <div class="pos-upi-k">${label}</div>
          <div class="pos-upi-code" data-tender-code aria-label="UPI payment QR"></div>
          <div class="pos-upi-vpa">${MF.esc(vpa)}</div>
          <div class="pos-upi-note">The app opens with ${MF.fmt(upiAmount, 2)}. Wait for the success screen, then complete the sale.</div>
        </div>`;
        paintQr(extra.querySelector('[data-tender-code]'), upiPayUri(upiAmount, upiRefFor(upiAmount)), 200);
      }
      paintTenderExtra();

      function refresh() {
        if (!cash) return;
        const input = host.querySelector('#posTenderPaid');
        const box = host.querySelector('#posTenderChange');
        const ok = host.querySelector('#posTenderOk');
        const amount = paid === '' ? null : (parseInt(paid, 10) || 0);
        host.querySelectorAll('.pos-tender-chip').forEach((chip) => {
          chip.classList.toggle('on', amount !== null && amount === +chip.dataset.set);
        });
        if (amount === null) {
          box.innerHTML = `<div class="pos-tender-change wait"><div class="k">Change to return</div><div class="amt">Enter cash received</div></div>`;
          ok.disabled = true;
          ok.textContent = 'Complete sale';
        } else if (amount < due) {
          box.innerHTML = `<div class="pos-tender-change short"><div class="k">Still short</div><div class="amt">${MF.fmt(due - amount)}</div><div class="pos-tender-hint">Complete stays off until the bill is covered.</div></div>`;
          ok.disabled = true;
          ok.textContent = `Need ${MF.fmt(due - amount)}`;
        } else if (amount === due) {
          box.innerHTML = `<div class="pos-tender-change"><div class="k">Change to return</div><div class="amt">${MF.fmt(0)}</div><div class="pos-tender-hint">Exact amount. Nothing to hand back.</div></div>`;
          ok.disabled = false;
          ok.textContent = 'Complete sale';
        } else {
          const change = amount - due;
          const notes = changeBreakdown(change).map(([n, c]) =>
            `<span><i class="pos-tender-sw" style="background:${noteSwatch(n)}"></i>${MF.fmt(n)} × ${c}</span>`).join('');
          const warn = change > 5000;
          box.innerHTML = `
            <div class="pos-tender-change${warn ? ' warn' : ''}">
              <div class="k">${warn ? 'Large change — recheck' : 'Change to return'}</div>
              <div class="amt">${MF.fmt(change)}</div>
              <div class="pos-tender-hint">Give back</div>
              <div class="pos-tender-give">${notes}</div>
            </div>`;
          ok.disabled = false;
          ok.textContent = 'Complete sale';
        }
        if (input && input.value !== paid) input.value = paid;
      }

      function paintChips() {
        const wrap = host.querySelector('#posTenderChips');
        if (!wrap) return;
        wrap.innerHTML = tenderSuggestions(due).map((n) => {
          const label = n === due ? `Exact ${MF.fmt(n)}` : MF.fmt(n);
          return `<button type="button" class="pos-tender-chip" data-set="${n}">${label}</button>`;
        }).join('');
        wrap.querySelectorAll('.pos-tender-chip').forEach((chip) => {
          chip.addEventListener('click', () => {
            paid = chip.dataset.set;
            const input = host.querySelector('#posTenderPaid');
            if (input) { input.value = paid; input.focus(); input.select(); }
            refresh();
          });
        });
      }

      paintChips();
      refresh();

      host.querySelector('#posTenderPaid')?.addEventListener('input', (e) => {
        paid = e.target.value.replace(/\D/g, '').replace(/^0+(?=\d)/, '').slice(0, 6);
        if (e.target.value !== paid) e.target.value = paid;
        refresh();
      });
      host.querySelectorAll('[data-key]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const input = host.querySelector('#posTenderPaid');
          const all = input && input.selectionStart === 0 && input.selectionEnd === input.value.length && input.value.length > 0;
          const key = btn.dataset.key;
          if (key === 'c') paid = '';
          else if (key === 'b') paid = paid.slice(0, -1);
          else {
            const base = all ? '' : paid;
            paid = (base + key).replace(/^0+(?=\d)/, '').slice(0, 6);
          }
          if (input) {
            input.value = paid;
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
          }
          refresh();
        });
      });
      host.querySelector('#posTenderOk').addEventListener('click', () => {
        const ok = host.querySelector('#posTenderOk');
        if (ok.disabled) return;
        if (cash) {
          const received = parseInt(paid, 10) || 0;
          if (paid === '' || received < due) return;
          result = { cashReceived: received, changeReturned: received - due };
        } else {
          result = { cashReceived: 0, changeReturned: 0 };
        }
        modal.hide();
      });
      modalEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          host.querySelector('#posTenderOk')?.click();
        }
      });
      modalEl.addEventListener('shown.bs.modal', () => {
        paintTenderExtra();
        const input = host.querySelector('#posTenderPaid');
        if (input) { input.focus(); input.select(); }
      }, { once: true });
      modalEl.addEventListener('hidden.bs.modal', () => {
        state.tenderOpen = false;
        resolve(result);
      }, { once: true });
      modal.show();
    });
  }

  function receiptHtml(invNo, t, fmt, snap) {
    // snap = frozen bill context for reprints (cart/payment/tender/customer as-was);
    // omitted on the live path, where current state is the context.
    const liveCust = MF.cust($('#posCustomer').value);
    const cust = snap && snap.cust ? snap.cust : (liveCust || { name: 'Customer' });
    const cart = snap && snap.cart ? snap.cart : state.cart;
    const pay = snap && snap.payment ? snap.payment : state.payment;
    const split = snap && snap.split ? snap.split : state.split;
    const tender = snap && 'tender' in snap ? snap.tender : state.tender;
    const payLabel = { cash: 'Cash', upi: 'UPI', card: 'Card', credit: 'Credit', split: `Split (Cash ${MF.fmt(split.cash)} + UPI ${MF.fmt(split.upi)})` }[pay] || pay;
    const tenderRows = pay === 'cash' && tender
      ? `<div class="sum-row"><span class="text-2">Cash paid</span><span class="num">${MF.fmt(tender.cashReceived)}</span></div>
         <div class="sum-row"><span class="text-2">Change</span><span class="num">${MF.fmt(tender.changeReturned)}</span></div>`
      : '';
    const html = `
      <div class="text-center mb-3">
        <img src="assets/images/logo.svg" width="42" alt="">
        <h6 class="fw-bold mt-2 mb-0">${MF.esc(D.store.name)}</h6>
        ${D.store.address ? `<div class="text-2 small-xs">${MF.esc(D.store.address)}${D.store.gstin ? ' · GSTIN ' + D.store.gstin : ''}</div>` : ''}
      </div>
      <div class="d-flex justify-content-between small mb-2">
        <span>Invoice: <strong>${invNo}</strong></span><span>${MF.fmtDate(MF.today())}</span>
      </div>
      <div class="small mb-2">Customer: <strong>${MF.esc(cust.name || 'Customer')}</strong> · Payment: <strong>${payLabel}</strong></div>
      <table class="table table-sm table-bordered small">
        <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Rate</th><th class="text-end">Amt</th></tr></thead>
        <tbody>${cart.map((l) => {
          const m = MF.med(l.medId), c = calcLine(l);
          return `<tr><td>${MF.esc(m.name)}</td><td class="text-center">${l.qty}</td><td class="text-end num">${MF.fmt(l.rate, 2)}</td><td class="text-end num">${MF.fmt(c.net, 2)}</td></tr>`;
        }).join('')}</tbody>
      </table>
      <div class="ms-auto" style="max-width:260px">
        <div class="sum-row"><span class="text-2">Subtotal</span><span class="num">${MF.fmt(t.subtotal, 2)}</span></div>
        <div class="sum-row"><span class="text-2">Discount</span><span class="num">− ${MF.fmt(t.discount, 2)}</span></div>
        <div class="sum-row"><span class="text-2">Round off</span><span class="num">${t.roundOff >= 0 ? '+' : '−'} ${MF.fmt(Math.abs(t.roundOff), 2)}</span></div>
        <div class="sum-row total"><span>Total</span><span class="num">${MF.fmt(t.grand)}</span></div>
        ${tenderRows}
      </div>
      <p class="text-center text-2 small-xs mt-3 mb-0">Medicines once sold will not be taken back without valid reason · Get well soon!</p>`;
    // Thermal 80mm roll → narrow, compact column; A4 → the normal width.
    return fmt === 'thermal'
      ? `<div style="width:72mm; margin:0 auto; font-size:11px; line-height:1.42;">${html}</div>`
      : html;
  }

  /* ===== Last-bill reprint chip =====
   Paper jams don't wait for the Sales-invoices page. A frozen snapshot of the
   bill we just completed lets the cashier reprint A4/thermal right from the
   meta bar — even after the cart is reset, even offline. */
  function saveLastBill(invNo, t) {
    const cust = MF.cust($('#posCustomer').value);
    state.lastBill = {
      invNo,
      grand: t.grand,
      at: Date.now(),
      snap: {
        cart: JSON.parse(JSON.stringify(state.cart)),
        payment: state.payment,
        split: { cash: state.split.cash, upi: state.split.upi },
        tender: state.tender ? { cashReceived: state.tender.cashReceived, changeReturned: state.tender.changeReturned } : null,
        cust: cust ? { id: cust.id, name: cust.name, phone: cust.phone || '' } : null,
      },
      t: { subtotal: t.subtotal, discount: t.discount, roundOff: t.roundOff, grand: t.grand },
    };
    try {
      localStorage.setItem('mf-pos-lastbill', JSON.stringify({ v: 1, bill: state.lastBill }));
    } catch (e) { /* quota — memory-only is fine */ }
  }
  function paintLastBill() {
    const chip = $('#posLastBill');
    if (!chip) return;
    let b = state.lastBill;
    if (!b) {
      try { b = (JSON.parse(localStorage.getItem('mf-pos-lastbill') || 'null') || {}).bill || null; } catch (e) { b = null; }
      if (b) state.lastBill = b;
    }
    if (!b) { chip.classList.remove('show'); return; }
    $('#posLastBillTxt').textContent = b.invNo + ' · ' + MF.fmt(b.grand);
    chip.classList.add('show');
  }
  function reprintLastBill(fmt) {
    const b = state.lastBill;
    if (!b) { MF.toast('No completed bill to reprint yet on this counter.', 'warn', 'Reprint'); return; }
    MF.printHtml(receiptHtml(b.invNo, b.t, fmt, b.snap));
  }
  function initLastBillChip() {
    const chip = $('#posLastBill'), menu = $('#posReprintMenu');
    if (!chip || !menu) return;
    paintLastBill();
    chip.addEventListener('click', (e) => { e.stopPropagation(); menu.hidden = !menu.hidden; });
    document.addEventListener('click', (e) => { if (!e.target.closest('#posReprintMenu')) menu.hidden = true; });
    $('#posReprintThermal')?.addEventListener('click', () => { menu.hidden = true; reprintLastBill('thermal'); });
    $('#posReprintA4')?.addEventListener('click', () => { menu.hidden = true; reprintLastBill('a4'); });
  }

  /* ===== Business credit gate =====
     * A credit bill to a business account with a cap set warns (and asks for a
     * cashier confirmation) when the projected dues would cross the cap.
     * Retail accounts are exempt — their policy fields stay NULL server-side. */
  function ensureCreditModal() {
    if (document.getElementById('posCreditModal')) return;
    const wrap = document.createElement('div');
    wrap.innerHTML = `<div class="modal fade" id="posCreditModal" tabindex="-1" data-bs-focus="false" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-shield-exclamation me-2" style="color:#B42318"></i>Credit limit exceeded</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body" id="posCreditBody"></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light-mf" data-bs-dismiss="modal">Cancel billing</button>
            <button type="button" class="btn btn-danger" id="posCreditProceed"><i class="bi bi-exclamation-triangle me-1"></i>Sell on credit anyway</button>
          </div>
        </div>
      </div>
    </div>`;
    document.body.appendChild(wrap.firstElementChild);
  }
  function posCreditConfirm(html) {
    return new Promise((resolve) => {
      ensureCreditModal();
      const el = document.getElementById('posCreditModal');
      document.getElementById('posCreditBody').innerHTML = html;
      const modal = bootstrap.Modal.getOrCreateInstance(el);
      let done = false;
      const finish = (ok) => { if (done) return; done = true; el.removeEventListener('hidden.bs.modal', onHide); resolve(ok); };
      const onHide = () => finish(false);
      el.addEventListener('hidden.bs.modal', onHide);
      document.getElementById('posCreditProceed').onclick = () => { finish(true); modal.hide(); };
      modal.show();
    });
  }
  async function creditGate(t) {
    if (state.payment !== 'credit') return true;
    const cust = MF.cust($('#posCustomer') ? $('#posCustomer').value : '');
    if (!cust || cust.name === 'Walk-in Customer') return true;      // walk-in credit is already blocked at the payment radios
    const typeKey = String(cust.type || '').toLowerCase();
    if (typeKey === '' || typeKey === 'retail') return true;          // policy applies to business accounts only
    const limit = Number(cust.credit_limit ?? cust.creditLimit ?? 0);
    if (!(limit > 0)) return true;                                    // no cap configured on this account
    const bill = Math.max(0, Number(t && t.grand) || 0);
    let duesNow = null;
    try {
      const res = await MF.Api.get('customer-dues.php?id=' + encodeURIComponent(cust.id));
      duesNow = Number(res && res.data && res.data.customer ? res.data.customer.outstanding : 0);
      if (!Number.isFinite(duesNow)) duesNow = null;
    } catch (e) { duesNow = null; }
    if (duesNow === null) return true;                                // ledger unavailable — don't freeze the counter; dues still post server-side
    const headroom = limit - duesNow;
    if (bill <= headroom) {
      const left = headroom - bill;
      if (left < limit * 0.2) {
        MF.toast(`${cust.name} will be down to ${MF.fmt(Math.max(0, left))} of the ${MF.fmt(limit)} credit cap after this bill.`, 'warn', 'Credit watch');
      }
      return true;
    }
    const projected = duesNow + bill;
    const overBy = projected - limit;
    return posCreditConfirm(`
      <p class="mb-2" style="font-size:.86rem">This credit bill to <strong>${MF.esc(cust.name)}</strong>${cust.business_name ? ' (' + MF.esc(cust.business_name) + ')' : ''} will take the account past its cap:</p>
      <table class="table table-sm mb-2" style="font-size:.84rem">
        <tbody>
          <tr><td class="text-2">Credit limit</td><td class="text-end num fw-semibold">${MF.fmt(limit)}</td></tr>
          <tr><td class="text-2">Outstanding today</td><td class="text-end num">${MF.fmt(duesNow)}</td></tr>
          <tr><td class="text-2">Headroom left</td><td class="text-end num">${MF.fmt(Math.max(0, headroom))}</td></tr>
          <tr><td class="text-2">This bill on credit</td><td class="text-end num fw-semibold">${MF.fmt(bill)}</td></tr>
          <tr class="table-danger"><td class="fw-semibold">Dues after this bill</td>
            <td class="text-end num fw-bold">${MF.fmt(projected)} <span class="badge bg-danger ms-1">+${MF.fmt(overBy)} over</span></td></tr>
        </tbody>
      </table>
      ${cust.credit_days ? `<div class="text-2 mb-2" style="font-size:.74rem">Account terms: payment due within <strong>${cust.credit_days} day${Number(cust.credit_days) === 1 ? '' : 's'}</strong>.</div>` : ''}
      <div class="alert alert-warning py-2 mb-0" role="note" style="font-size:.76rem;border-radius:10px">
        <i class="bi bi-lightbulb me-1"></i>Cleaner: collect the existing ${MF.fmt(duesNow)} first, or bill this one on cash/UPI.
      </div>`);
  }

  /* ===== Below-cost floor =====================================================
     * Below-MRP selling is normal; below-COST selling is a choice — so it must be
     * confirmed, then recorded in the sale_audit ledger. */
  function ensureLossModal() {
    if (document.getElementById('posLossModal')) return;
    const wrap = document.createElement('div');
    wrap.innerHTML = `<div class="modal fade" id="posLossModal" tabindex="-1" data-bs-focus="false" data-bs-backdrop="static" data-bs-keyboard="false">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-cash-coin me-2" style="color:#B42318"></i>Selling below purchase cost</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body" id="posLossBody"></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light-mf" data-bs-dismiss="modal">Back to bill</button>
            <button type="button" class="btn btn-danger" id="posLossProceed"><i class="bi bi-arrow-down-circle me-1"></i>Allow this loss sale</button>
          </div>
        </div>
      </div>
    </div>`;
    document.body.appendChild(wrap.firstElementChild);
  }
  function belowCostLines(t) {
    const billPct = (t && t.billDiscPct) || 0;
    return state.cart.map((l) => {
      const m = MF.med(l.medId);
      const b = D.batches.find((x) => String(x.id) === String(l.batchId));
      const costPack = Number(b && b.purchaseRate) || 0;      // batches store rate per pack
      if (!m || costPack <= 0) return null;
      const costUnit = l.unit === 'loose' ? costPack / Math.max(1, packSize(m)) : costPack;
      const afterLine = l.rate * (1 - (l.discPct || 0) / 100);
      const eff = afterLine * (1 - billPct / 100);
      if (eff + 0.004 < costUnit) {
        return { name: m.name, eff, cost: costUnit, loss: costUnit - eff, qty: l.qty, unit: l.unit };
      }
      return null;
    }).filter(Boolean);
  }
  function lossGate(loss) {
    ensureLossModal();
    const el = document.getElementById('posLossModal');
    const totalLoss = loss.reduce((s, x) => s + x.loss * x.qty, 0);
    document.getElementById('posLossBody').innerHTML = `
      <p class="mb-2" style="font-size:.86rem">${loss.length} line(s) on this bill are priced <strong>below their purchase cost</strong> after discounts:</p>
      <table class="table table-sm mb-2" style="font-size:.82rem">
        <thead><tr><th>Medicine</th><th class="text-end">Cost</th><th class="text-end">Selling</th><th class="text-end">Loss/unit</th></tr></thead>
        <tbody>${loss.map((x) => `<tr>
            <td class="fw-semibold">${MF.esc(x.name)}${x.unit === 'loose' ? ' <span class="text-2">(loose)</span>' : ''}</td>
            <td class="text-end num">${MF.fmt(x.cost, 2)}</td>
            <td class="text-end num">${MF.fmt(x.eff, 2)}</td>
            <td class="text-end num fw-bold" style="color:#B42318">−${MF.fmt(x.loss, 2)}</td>
          </tr>`).join('')}
        </tbody>
      </table>
      <div class="alert alert-danger py-2 mb-0" role="note" style="font-size:.78rem;border-radius:10px">
        <i class="bi bi-cash-stack me-1"></i>Total margin given up on this bill: <strong>${MF.fmt(totalLoss, 2)}</strong>.
        Clearing dated stock deliberately is fine — this keeps the choice on record.
      </div>`;
    return new Promise((resolve) => {
      const modal = bootstrap.Modal.getOrCreateInstance(el);
      let done = false;
      const finish = (ok) => { if (done) return; done = true; el.removeEventListener('hidden.bs.modal', onHide); resolve(ok); };
      const onHide = () => finish(false);
      el.addEventListener('hidden.bs.modal', onHide);
      document.getElementById('posLossProceed').onclick = () => { finish(true); modal.hide(); };
      modal.show();
    });
  }

  async function completeSale() {
    if (state.tenderOpen) { document.getElementById('posTenderOk')?.click(); return; }
    if (!state.cart.length) { MF.toast('Cart is empty', 'warn', 'Cannot complete sale'); return; }
    // Schedule H/H1/X/NDPS in the cart → doctor and patient details are mandatory.
    const sch = autoRxSchedule();
    if (sch) {
      const doc = $('#posDoctor');
      if (!doc || !String(doc.value || '').trim()) {
        rxFlag.doc = true;
        rxNudgeSync();
        MF.toast('Pick the prescribing doctor — mandatory for Schedule ' + sch + ' items. It is flagged in red above.', 'warn', 'Rx required');
        // .focus() on the ghost select = invisible; land the cursor in the live picker.
        (pickerState['#posDoctor'] ? pickerState['#posDoctor'].input : doc).focus();
        return;
      }
      const cust = $('#posCustomer');
      if (cust && String(cust.value) === String(walkInId())) {
        rxFlag.cust = true;
        rxNudgeSync();
        MF.toast('Patient details are mandatory for Schedule ' + sch + ' — select or add the customer in the red field above.', 'warn', 'Rx required');
        (pickerState['#posCustomer'] ? pickerState['#posCustomer'].input : cust).focus();
        return;
      }
    }
    const t = calcTotals();
    // Cost floor first (cheaper to fix than a credit issue later at approval time).
    const loss = belowCostLines(t);
    if (loss.length) {
      if (!await lossGate(loss)) { MF.toast('Sale paused — raise the rates or remove the deep-discount lines.', 'info', 'Cost floor'); return; }
      state.lossApproved = loss.map((x) => x.name);
    } else state.lossApproved = null;
    if (!await creditGate(t)) { MF.toast('Sale paused — adjust the payment mode or collect dues first.', 'info', 'Credit gate'); return; }
    /* Capture-later: a prescription-controlled bill with nothing attached gets ONE
       soft confirm (0.5 s), then the sale flows and the gap is queued after posting. */
    let rxPendingTag = false;
    if (state.cart.some(needsRx) && !($('#posRx') && $('#posRx').value)) {
      const okRx = await MF.confirm({
        title: 'No prescription attached',
        message: 'This bill has a prescription-controlled item and no recorded prescription is attached. Bill now against the paper Rx and queue it for later capture (photo/details at idle time)?',
        confirmText: 'Bill now, capture later',
        cancelText: 'Go back',
        tone: 'warning',
      });
      if (!okRx) return;
      rxPendingTag = true;
    }
    const tender = await openTender(t);
    if (!tender) return;
    state.tender = tender;

    $('#posComplete').disabled = true;
    try {
      const res = await MF.Api.post('sales.php', {
        customerId: $('#posCustomer').value,
        paymentMode: state.payment,
        globalDiscPct: Math.min(100, Math.max(0, t.billDiscPct || 0)),
        splitCash: state.split.cash,
        splitUpi: state.split.upi,
        cashReceived: tender.cashReceived,
        changeReturned: tender.changeReturned,
        items: state.cart.map((l) => ({ medId: l.medId, batchId: l.batchId, qty: l.qty, rate: l.rate, discPct: l.discPct, unit: l.unit || 'pack' })),
      });
      MF.printHtml(receiptHtml(res.invoiceNo, t, state.printFmt));
      saveLastBill(res.invoiceNo, t);
      paintLastBill();
      MF.toast(`${res.invoiceNo} · ${MF.fmt(res.grandTotal)} · ${state.payment.toUpperCase()}`, 'success', 'Sale completed');
      if (state.lossApproved && state.lossApproved.length) {
        MF.Api.post('sale-audit.php', {
          event: 'BELOW_COST_SALE',
          invoiceNo: res.invoiceNo,
          detail: `${state.lossApproved.length} line(s) sold below landed cost on ${res.invoiceNo}: ${state.lossApproved.join('; ')}. Cashier confirmed at POS.`,
        }).catch(() => { /* best effort — the sale itself is saved */ });
      }
      state.lossApproved = null;
      noteCompletedInvoice(res.invoiceNo);
      // Auto-close the attached script: consumed by this bill, it leaves the dropdown.
      const attachedRx = $('#posRx') ? String($('#posRx').value || '') : '';
      if (attachedRx) {
        MF.Api.put('prescriptions.php', { id: attachedRx, status: 'Dispensed' }).catch(() => { /* best effort */ });
        const row = (D.prescriptions || []).find((r) => String(r.id) === attachedRx);
        if (row) row.status = 'Dispensed';
      }
      if (rxPendingTag) {
        const rxLines = state.cart.filter(needsRx);
        const codes = [...new Set(rxLines.map((l) => (MF.med(l.medId) || {}).schedule).filter(Boolean))];
        const itemsTxt = rxLines.map((l) => `${(MF.med(l.medId) || {}).name || 'Medicine'} x${l.qty}`).join('; ');
        MF.Api.post('sale-audit.php', {
          event: 'RX_CAPTURE_PENDING',
          invoiceNo: res.invoiceNo,
          detail: `${res.grandTotal ? MF.fmt(res.grandTotal) : ''} · schedules: ${codes.join(', ') || 'Rx item'} · items: ${itemsTxt} · sold by ${$('#posCustomer').selectedOptions[0]?.text || 'Walk-in'} · queued for capture`,
        }).catch(() => { /* best effort — the sale itself is saved */ });
        refreshRxPending();
      }
      if (res.balanceDue > 0) MF.toast(`${MF.fmt(res.balanceDue)} added to customer dues`, 'info', 'Credit sale');
      state.cart = [];
      state.tender = null;
      state.cartIdx = -1;
      await MF.rehydrate(); // refresh D.batches so stock levels are current
      $('#posCustomer').value = walkInId();
      if (window.POSUI) POSUI.syncPickers();
      renderCart();
      searchMeds($('#posSearch').value); // redraw Quick picks / search cards with the new stock

      /* Smart reminder — the bill is done and stock just moved: if re-orders are still waiting, push to place them */
      if (state.orderPad.length) {
        const pending = state.orderPad.length;
        setTimeout(async () => {
          const ok = await MF.confirm({
            title: 'Pending re-order',
            message: `${pending} medicine(s) you marked out of stock are still waiting in the Order Pad. Open New Purchase and send the order now, before the next customer asks?`,
            confirmText: 'Open New Purchase',
            cancelText: 'Later',
            tone: 'warning',
          });
          if (ok) location.href = 'purchase.php';
        }, 900);
      }
    } catch (err) {
      state.tender = null;
      MF.toast(err.message || 'Could not complete the sale.', 'danger', 'Sale failed');
    } finally {
      $('#posComplete').disabled = false;
    }
  }

  function rxStatus(s) {
    if (!s || s === 'Recorded' || s === 'Pending') return 'Pending';
    if (s === 'Ready') return 'Ready';
    if (s === 'Dispensed' || s === 'Completed') return 'Dispensed';
    if (s === 'Cancelled') return 'Cancelled';
    return 'Pending';
  }

  function rxLabel(row) {
    const date = row.rx_date && MF.fmtDate ? MF.fmtDate(row.rx_date) : (row.rx_date || '');
    return [row.rx_no || 'Rx', row.patient_name || row.patient, date, rxStatus(row.status)].filter(Boolean).join(' · ');
  }

  function paintRxMeta(row) {
    const box = $('#posRxMeta');
    if (!box) return;
    if (!row) {
      box.innerHTML = 'Choose a prescription to show the doctor, patient and medicines.';
      return;
    }
    const doctor = row.doctor_name || row.doctorName || 'Doctor not recorded';
    const patient = row.patient_name || row.patient || 'Patient';
    const date = row.rx_date && MF.fmtDate ? MF.fmtDate(row.rx_date) : (row.rx_date || '—');
    const meds = row.medicines || row.items || [];
    box.innerHTML = `<strong>${MF.esc(row.rx_no || 'Prescription')}</strong> · ${MF.esc(rxStatus(row.status))}<br>Patient ${MF.esc(patient)} · Doctor ${MF.esc(doctor)} · ${MF.esc(date)}${meds.length ? ' · ' + meds.length + ' medicine' + (meds.length === 1 ? '' : 's') : ''}`;
  }

  function ensureRxOption(row) {
    const sel = $('#posRx');
    const id = row && (row.id != null ? row.id : row.rxId);
    if (!sel || id == null || id === '') return;
    if (![...sel.options].some((o) => String(o.value) === String(id))) {
      const label = row.rx_no || row.patient_name ? rxLabel(row) : [row.rxNo || 'Prescription', row.patient].filter(Boolean).join(' · ');
      sel.insertAdjacentHTML('beforeend', `<option value="${MF.esc(id)}">${MF.esc(label)}</option>`);
    }
  }

  async function loadRxOptions() {
    const sel = $('#posRx');
    if (!sel) return;
    let rows = [];
    if (MF.Api && MF.Api.live) {
      try {
        const res = await MF.Api.get('prescriptions.php');
        rows = ((res.data || {}).ledger) || [];
      } catch (e) {
        rows = D.prescriptions || [];
      }
    } else {
      rows = D.prescriptions || [];
    }
    // Dispensed scripts are consumed goods — a script billed once must never be
    // attachable again (double-dispensing is a compliance violation, not a choice).
    rows = rows.filter((r) => {
      const st = rxStatus(r.status);
      return st === 'Ready' || st === 'Pending';
    });
    const customerId = $('#posCustomer') ? $('#posCustomer').value : '';
    // Scope follows the customer picker your way: a real customer selected → THEIR
    // prescriptions only (attaching a stranger's script was always wrong data).
    // Walk-in keeps the eligible full list so a script can still drive the bill
    // and fill the customer in, one tap.
    const walkin = String(customerId) === String(walkInId());
    if (customerId && !walkin) rows = rows.filter((r) => String(r.customer_id || '') === String(customerId));
    const rank = { Ready: 0, Pending: 1, Dispensed: 2 };
    rows.sort((a, b) => {
      const aMatch = customerId && walkin === false && String(a.customer_id || '') === String(customerId) ? 0 : 1;
      const bMatch = customerId && walkin === false && String(b.customer_id || '') === String(customerId) ? 0 : 1;
      if (aMatch !== bMatch) return aMatch - bMatch;
      return (rank[rxStatus(a.status)] ?? 9) - (rank[rxStatus(b.status)] ?? 9);
    });
    state.rxRows = rows;
    const current = sel.value;
    sel.innerHTML = `<option value="">— select prescription —</option>` + rows.map((r) => {
      const mine = customerId && String(r.customer_id || '') === String(customerId);
      return `<option value="${MF.esc(r.id)}">${MF.esc(rxLabel(r))}${mine ? ' · this customer' : ''}</option>`;
    }).join('');
    if (current && [...sel.options].some((o) => o.value === current)) sel.value = current;
    // Dead-end face fix: tell the cashier the blank dropdown is NOT a wall.
    const emptyBox = $('#posRxEmpty');
    if (emptyBox) emptyBox.hidden = rows.length > 0;
    if (state._rxBill) {
      ensureRxOption(state._rxBill);
      if (state._rxBill.rxId && [...sel.options].some((o) => String(o.value) === String(state._rxBill.rxId))) {
        sel.value = String(state._rxBill.rxId);
      }
      wireRxAttachment(state._rxBill);
    }
  }

  function setRxAttached(on) {
    const panel = $('#posRxPanel');
    const toggle = $('#posRxOn');
    if (toggle) toggle.checked = !!on;
    if (panel) panel.hidden = !on;
    if (on) loadRxOptions().catch(() => {});
  }

  async function addRxMedicines(row) {
    let items = (row.medicines && row.medicines.length) ? row.medicines : (row.items || []);
    if (!items.length && MF.Api && MF.Api.live && row.id) {
      try {
        const res = await MF.Api.get('prescriptions.php?id=' + encodeURIComponent(row.id));
        items = ((res.data || {}).items) || [];
        row.items = items;
      } catch (e) { /* keep the empty list */ }
    }
    let added = 0;
    items.forEach((item) => {
      const med = (D.medicines || []).find((m) => String(m.id) === String(item.medicine_id || item.medicineId) || String(m.name).toLowerCase() === String(item.medicine_name || item.name || '').toLowerCase());
      if (!med) {
        MF.toast((item.medicine_name || item.name || 'Medicine') + ' is not in the medicine master', 'warn', 'Prescription');
        return;
      }
      const qty = Math.max(1, Number(item.qty) || 1);
      addToCart(med.id);
      const line = [...state.cart].reverse().find((l) => String(l.medId) === String(med.id));
      if (line) line.qty = qty;
      added += 1;
    });
    if (added) renderCart();
    return added;
  }

  async function onRxPick() {
    const sel = $('#posRx');
    if (!sel) return;
    const row = (state.rxRows || []).find((r) => String(r.id) === String(sel.value));
    const doc = $('#posDoctor');
    if (!row) {
      if (doc) doc.value = '';
      paintRxMeta(null);
      return;
    }
    if (doc && doc.tagName === 'SELECT') {
      const id = row.doctor_id || row.doctorId || '';
      if (id && ![...doc.options].some((o) => String(o.value) === String(id))) {
        doc.insertAdjacentHTML('beforeend', `<option value="${MF.esc(id)}">${MF.esc(row.doctor_name || row.doctorName || 'Doctor')}</option>`);
      }
      doc.value = id ? String(id) : '';
    } else if (doc) {
      doc.value = row.doctor_id || row.doctorId || '';
    }
    const cust = $('#posCustomer');
    if (cust && row.customer_id && [...cust.options].some((o) => String(o.value) === String(row.customer_id))) {
      cust.value = row.customer_id;
    }
    if (window.POSUI) POSUI.syncPickers();
    paintRxMeta(row);
    if (String(state.loadedRxId || '') === String(row.id)) return;
    const added = await addRxMedicines(row);
    state.loadedRxId = row.id;
    if (added) MF.toast((row.rx_no || 'Prescription') + ' medicines added to the cart', 'success', 'Prescription');
  }

  function wireRxAttachment(bill) {
    const toggle = $('#posRxOn');
    const panel = $('#posRxPanel');
    if (!toggle || !panel || !bill) return false;
    toggle.checked = true;
    panel.hidden = false;
    const wrap = $('#posRxToggleWrap');
    if (wrap) {
      wrap.hidden = false;
      wrap.classList.add('show');
    }
    state._rxBill = bill;
    ensureRxOption({
      id: bill.rxId,
      rx_no: bill.rxNo,
      patient_name: bill.patient,
      status: 'Ready',
      doctor_id: bill.doctorId
    });
    const sel = $('#posRx');
    if (sel && bill.rxId) sel.value = String(bill.rxId);
    const doc = $('#posDoctor');
    if (doc && bill.doctorId) doc.value = bill.doctorId;
    if (window.POSUI) POSUI.syncPickers();
    paintRxMeta({
      rx_no: bill.rxNo,
      patient_name: bill.patient,
      doctor_name: bill.doctorName || '',
      status: bill.status || 'Ready',
      rx_date: bill.date || '',
      medicines: bill.items || []
    });
    return true;
  }

  function applyRxBill() {
    if (!state._rxBill) {
      let raw = '';
      try { raw = sessionStorage.getItem('mf-rx-bill') || ''; } catch (e) { return; }
      if (!raw) return;
      try { state._rxBill = JSON.parse(raw); } catch (e) { return; }
    }
    const bill = state._rxBill;
    if (!bill) return;
    const cust = document.getElementById('posCustomer');
    if (cust && bill.customerId && [...cust.options].some((o) => String(o.value) === String(bill.customerId))) {
      cust.value = bill.customerId;
    }
    const doc = document.getElementById('posDoctor');
    if (doc && doc.tagName === 'SELECT' && bill.doctorId) {
      if (![...doc.options].some((o) => String(o.value) === String(bill.doctorId))) {
        doc.insertAdjacentHTML('beforeend', `<option value="${MF.esc(bill.doctorId)}">${MF.esc(bill.doctorName || 'Doctor')}</option>`);
      }
      doc.value = String(bill.doctorId);
    } else if (doc) {
      doc.value = bill.doctorId || '';
    }
    if (window.POSUI) POSUI.syncPickers();
    if (!state._rxApplied) {
      let added = 0;
      (bill.items || []).forEach((item) => {
        const med = (D.medicines || []).find((m) => String(m.id) === String(item.medicineId) || String(m.name).toLowerCase() === String(item.name || '').toLowerCase());
        if (!med) {
          MF.toast((item.name || 'Medicine') + ' is not in the medicine master', 'warn', 'Prescription');
          return;
        }
        const qty = Math.max(1, Number(item.qty) || 1);
        addToCart(med.id);
        const line = [...state.cart].reverse().find((l) => String(l.medId) === String(med.id));
        if (line) line.qty = qty;
        added += 1;
      });
      if (added) renderCart();
      state._rxApplied = true;
      state.loadedRxId = bill.rxId || null;
      try { sessionStorage.removeItem('mf-rx-bill'); } catch (e) { /* ignore */ }
      if (added) MF.toast((bill.rxNo || 'Prescription') + ' loaded into the cart', 'success', 'Bill at POS');
    }
    wireRxAttachment(bill);
  }

  /* ---------------- Init ---------------- */
  /* ================= Searchable pickers (customer / doctor) =================
   The raw <select> stays the single source of truth — drafts, held bills, the
   credit gate and post-sale resets keep working untouched. The lookup box only
   mirrors and drives it, reading options live at open time (rehydrate-safe). */
  const pickerState = {};
  function pickerItems(selId) {
    const sel = $(selId); if (!sel) return [];
    return [...sel.options].filter((o) => o.value !== '').map((o) => {
      let sub = '';
      if (selId === '#posCustomer') {
        const c = MF.cust(o.value);
        sub = (c && c.phone) ? c.phone : (/default/i.test(o.text) ? 'Walk-in desk default' : '');
      } else {
        const d = (D.doctors || []).find((x) => String(x.id) === String(o.value));
        sub = d ? String(d.specialty || d.speciality || d.reg_no || '') : '';
      }
      return { id: String(o.value), name: String(o.text).replace(/\s*\(default\)\s*$/, ''), sub };
    });
  }
  function pickerRecentsKey(selId) { return 'mf-pos-recentpicks-' + selId.slice(1); }
  function pickerPushRecent(selId, id) {
    let ids = []; try { ids = JSON.parse(localStorage.getItem(pickerRecentsKey(selId)) || '[]'); } catch (e) { /* start fresh */ }
    ids = [String(id), ...ids.filter((x) => String(x) !== String(id))].slice(0, 8);
    try { localStorage.setItem(pickerRecentsKey(selId), JSON.stringify(ids)); } catch (e) { /* storage locked */ }
  }
  function pickerSyncLabel(selId) {
    const st = pickerState[selId]; if (!st) return;
    const opt = st.sel.selectedOptions && st.sel.selectedOptions[0];
    st.input.value = opt ? String(opt.text).replace(/\s*\(default\)\s*$/, '') : '';
  }
  window.POSUI = Object.assign(window.POSUI || {}, {
    syncPickers() { Object.keys(pickerState).forEach(pickerSyncLabel); rxNudgeSync(); }
  });
  function pickerPaintMenu(st) {
    const q = String(st.input.value || '').trim().toLowerCase();
    const all = pickerItems(st.selId);
    const match = (it) => !q || it.name.toLowerCase().includes(q) || (it.sub && it.sub.toLowerCase().includes(q));
    const recIds = []; try { JSON.parse(localStorage.getItem(pickerRecentsKey(st.selId)) || '[]').forEach((x) => recIds.push(String(x))); } catch (e) { /* none */ }
    const recents = recIds.map((id) => all.find((it) => it.id === id)).filter(Boolean).filter(match).slice(0, 4);
    // Progressive disclosure: an empty search renders recents + top slice only — the full
    // book is always one typed letter away, never 1000 buttons deep. Data stays complete.
    const CAP_IDLE = 25, CAP_TYPED = 50;
    const recentIds = new Set(recents.map((it) => it.id));
    let rest = all.filter((it) => !recentIds.has(it.id)).filter(match);
    // Walk-in Customer is the counter's default — pin it right under Recents, always.
    const pinned = [];
    if (st.selId === '#posCustomer' && typeof walkInId === 'function') {
      const wid = String(walkInId() || '');
      const pin = rest.find((it) => it.id === wid);
      if (pin) { pinned.push(pin); rest = rest.filter((it) => it.id !== wid); }
    }
    const cap = q ? CAP_TYPED : CAP_IDLE;
    const shown = [...pinned, ...rest].slice(0, cap);
    const moreHidden = rest.length + pinned.length - shown.length;
    st.list = [...recents, ...shown];
    st.hot = st.list.length ? 0 : -1;
    const optHtml = (it, i) =>
      `<button type="button" class="pos-lookup-opt${i === st.hot ? ' is-hot' : ''}" data-pk="${MF.esc(it.id)}"><span class="nm">${MF.esc(it.name)}</span>${it.sub ? `<span class="ph">${MF.esc(it.sub)}</span>` : ''}</button>`;
    let html = '';
    let pos = 0;
    if (recents.length) { html += `<div class="pos-lookup-rec">Recent</div>` + recents.map((it) => optHtml(it, pos++)).join(''); }
    if (shown.length) { html += (recents.length ? `<div class="pos-lookup-rec">${q ? 'Matches' : 'All'}</div>` : '') + shown.map((it) => optHtml(it, pos++)).join(''); }
    if (moreHidden > 0) html += `<div class="pos-lookup-empty">… ${MF.num(moreHidden)} more — keep typing to narrow</div>`;
    if (!st.list.length) html = `<div class="pos-lookup-empty">No match — add ${st.kind} below.</div>`;
    html += `<button type="button" class="pos-lookup-add" data-pk-add="1"><i class="bi bi-plus-circle-dotted"></i>Add new ${st.kind}</button>`;
    st.menu.innerHTML = html;
  }
  function pickerOpen(st) {
    if (st.open) return;
    Object.values(pickerState).forEach((o) => { if (o !== st) o.menu.hidden = true, o.open = false; });
    st.open = true;
    pickerPaintMenu(st);
    st.menu.hidden = false;
  }
  function pickerClose(st, revert) {
    if (!st.open) return;
    st.open = false;
    st.menu.hidden = true;
    if (revert !== false) pickerSyncLabel(st.selId);
  }
  function pickerChoose(st, id) {
    st.sel.value = id;
    st.sel.dispatchEvent(new Event('change', { bubbles: true })); // repricing, credit gate, drafts
    pickerPushRecent(st.selId, id);
    pickerClose(st, false);
    pickerSyncLabel(st.selId);
    rxNudgeSync(); // a field just got settled — the glow may clear
    $('#posSearch').focus();
  }
  function initPicker(selId, addBtnId, kind) {
    const sel = $(selId); if (!sel || pickerState[selId]) return;
    sel.classList.add('pos-lookup-ghost');
    const wrap = document.createElement('div');
    wrap.className = 'pos-lookup';
    sel.parentNode.insertBefore(wrap, sel);
    wrap.appendChild(sel);
    wrap.insertAdjacentHTML('beforeend',
      `<input class="pos-lookup-input" type="text" autocomplete="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" placeholder="Type to search ${kind}…" role="combobox" aria-expanded="false" aria-haspopup="listbox" aria-label="Search ${kind}"><i class="bi bi-chevron-down pos-lookup-caret"></i><div class="pos-lookup-menu" role="listbox" hidden></div>`);
    const st = pickerState[selId] = { selId, sel, wrap, kind, addBtnId, list: [], hot: -1, open: false,
      input: wrap.querySelector('input'), menu: wrap.querySelector('.pos-lookup-menu') };
    pickerSyncLabel(selId);
    st.input.addEventListener('focus', () => { st.input.select(); pickerOpen(st); });
    st.input.addEventListener('click', () => { if (!st.open) pickerOpen(st); });
    st.input.addEventListener('input', () => { if (!st.open) pickerOpen(st); else pickerPaintMenu(st); });
    st.input.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if (!st.open) return pickerOpen(st);
        const step = e.key === 'ArrowDown' ? 1 : -1;
        st.hot = st.list.length ? (st.hot + step + st.list.length) % st.list.length : -1;
        [...st.menu.querySelectorAll('.pos-lookup-opt')].forEach((el, i) => el.classList.toggle('is-hot', i === st.hot));
        st.menu.querySelector('.pos-lookup-opt.is-hot')?.scrollIntoView({ block: 'nearest' });
        return;
      }
      if (e.key === 'Enter') {
        e.preventDefault();
        if (st.open && st.hot >= 0 && st.list[st.hot]) pickerChoose(st, st.list[st.hot].id);
        else pickerClose(st);
        return;
      }
      if (e.key === 'Escape') { e.stopPropagation(); pickerClose(st); $('#posSearch').focus(); }
    });
    st.menu.addEventListener('mousedown', (e) => e.preventDefault()); // keep input focus while clicking
    st.menu.addEventListener('click', (e) => {
      const add = e.target.closest('[data-pk-add]');
      if (add) {
        pickerClose(st, false);
        const btn = $(st.addBtnId);
        if (btn) btn.click(); // reuse the existing quick-add modal — zero duplication
        return;
      }
      const opt = e.target.closest('.pos-lookup-opt');
      if (opt) pickerChoose(st, opt.dataset.pk);
    });
    st.sel.addEventListener('change', () => pickerSyncLabel(selId));
    document.addEventListener('click', (e) => { if (!wrap.contains(e.target)) pickerClose(st); });
  }

  /* ===== Rx capture-later queue (Round B) =====
   Ledger-driven: pending = RX_CAPTURE_PENDING audit rows with no later
   RX_CAPTURED for the same invoice. Details-only capture today — it writes a
   real row into the prescriptions register; photo storage needs a migration
   of its own (deferred on purpose). */
  state.rxPending = [];
  async function refreshRxPending() {
    if (!(MF.Api && MF.Api.live)) return;
    try {
      const res = await MF.Api.get('sale-audit.php?event=PENDING_RX');
      state.rxPending = (res.data && res.data.pending) || [];
    } catch (e) { state.rxPending = []; }
    const pill = $('#posRxPend');
    if (pill) {
      $('#posRxPendTxt').textContent = state.rxPending.length;
      pill.classList.toggle('show', state.rxPending.length > 0);
    }
    const modalOpen = $('#posRxPendModal')?.classList.contains('show');
    if (modalOpen) paintRxPendingList();
  }
  function rpRegisterFlag(detail) {
    const m = String(detail || '').match(/schedules:\s*([^·]+)/i);
    const codes = m ? m[1].split(',').map((s) => s.trim()).filter(Boolean) : [];
    return codes.filter((c) => /^(H1|X|NDPS)$/i.test(c));
  }
  function paintRxPendingList() {
    const box = $('#rpList'); if (!box) return;
    if (!state.rxPending.length) {
      box.innerHTML = `<div class="empty-state py-4"><i class="bi bi-shield-check"></i>Queue clear — every prescription-controlled sale is captured.</div>`;
      return;
    }
    box.innerHTML = state.rxPending.map((r, i) => {
      const flag = rpRegisterFlag(r.detail);
      const when = String(r.created_at || '').replace('T', ' ').slice(0, 16);
      return `<div class="d-flex align-items-center gap-2 p-2 ${i ? 'border-top' : ''}">
        <div class="flex-grow-1">
          <div class="fw-semibold" style="font-size:.86rem">${MF.esc(r.invoice_no)} <span class="text-2" style="font-size:.72rem">${MF.esc(when)}</span></div>
          <div class="text-2" style="font-size:.74rem">${MF.esc(String(r.detail || '').replace(/ · queued for capture$/, ''))}</div>
        </div>
        ${flag.length ? `<span class="badge" style="background:#FDE2E2;color:#B42318;font-size:.66rem" title="Register-required schedule — clear before day-end">Register · ${MF.esc(flag.join(', '))}</span>` : ''}
        <button type="button" class="btn btn-mf-soft btn-sm" data-rp="${i}"><i class="bi bi-pencil-square me-1"></i>Capture</button>
      </div>`;
    }).join('');
    box.querySelectorAll('[data-rp]').forEach((b) => b.addEventListener('click', () => rpOpenForm(+b.dataset.rp)));
  }
  let rpActive = null;
  function rpOpenForm(i) {
    rpActive = state.rxPending[i] || null;
    if (!rpActive) return;
    $('#rpFormTitle').textContent = 'Capture — ' + rpActive.invoice_no;
    const docSel = $('#rpDoctor');
    docSel.innerHTML = '<option value="">— select doctor —</option>' +
      ((D.doctors || []).slice().sort((a, b) => String(a.name).localeCompare(String(b.name)))
        .map((d) => `<option value="${MF.esc(d.id)}">${MF.esc(d.name)}</option>`).join(''));
    $('#rpDate').value = MF.today();
    $('#rpFormWrap').hidden = false;
    setTimeout(() => $('#rpPatient').focus(), 150);
  }
  async function rpSave() {
    if (!rpActive) return;
    const patient = $('#rpPatient').value.trim();
    if (!patient) { MF.toast('Patient name is needed on the register entry.', 'warn', 'Rx capture'); return; }
    const doctorId = $('#rpDoctor').value;
    if (!doctorId) { MF.toast('Pick the prescribing doctor.', 'warn', 'Rx capture'); return; }
    const btn = $('#rpSave');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
    try {
      // The register demands ≥1 medicine line — rebuild them from the sale's
      // own queued detail ("items: A x2; B x1"); the generic fallback keeps
      // capture unblockable even if an old-shaped row is met.
      let items = [];
      const m = String(rpActive.detail || '').match(/items:\s*(.+?)\s*·\s*sold by/i);
      if (m) {
        items = m[1].split(';').map((seg) => {
          const mm = seg.trim().match(/^(.*)\sx(\d{1,4})$/);
          return mm ? { name: mm[1].trim(), qty: parseInt(mm[2], 10) } : null;
        }).filter(Boolean);
      }
      if (!items.length) items = [{ name: 'Prescription-controlled medicines per paper Rx (see ' + rpActive.invoice_no + ')', qty: 1 }];
      const res = await MF.Api.post('prescriptions.php', {
        customer_id: 0, patient_name: patient,
        patient_age: $('#rpAge').value.trim(), patient_phone: $('#rpPhone').value.trim(),
        doctor_id: doctorId, rx_date: $('#rpDate').value || MF.today(),
        diagnosis: 'Counter capture for ' + rpActive.invoice_no, status: 'Ready', items,
      });
      await MF.Api.post('sale-audit.php', {
        event: 'RX_CAPTURED', invoiceNo: rpActive.invoice_no,
        detail: `Prescription registered (id ${res.id ?? '—'}) against ${rpActive.invoice_no} at capture-later queue.`,
      });
      MF.toast(rpActive.invoice_no + ' — prescription attached, queue cleared.', 'success', 'Rx capture');
      $('#rpFormWrap').hidden = true; rpActive = null;
      ['rpPatient', 'rpAge', 'rpPhone'].forEach((id) => { $('#' + id).value = ''; });
      await refreshRxPending();
    } catch (e) {
      MF.toast(e.message || 'Could not save the prescription entry.', 'err', 'Rx capture');
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Attach & mark captured';
    }
  }
  async function rpMarkManual() {
    if (!rpActive) return;
    const ok = await MF.confirm({
      title: 'Mark captured manually?',
      message: rpActive.invoice_no + ' will leave the queue with an audit note saying it was handled off-screen. Use only when the prescription record was fixed elsewhere.',
      confirmText: 'Mark captured', cancelText: 'Keep it', tone: 'warning',
    });
    if (!ok) return;
    try {
      await MF.Api.post('sale-audit.php', { event: 'RX_CAPTURED', invoiceNo: rpActive.invoice_no, detail: 'Marked captured manually from the capture-later queue (record fixed off-screen).' });
      $('#rpFormWrap').hidden = true; rpActive = null;
      await refreshRxPending();
    } catch (e) {
      MF.toast(e.message || 'Could not clear this entry.', 'err', 'Rx capture');
    }
  }

  /* ===== Scan & Send Rx — phone photo inbox =====
   Customers photograph their Rx to shop's upload page (in-store QR); the counter
   polls ?action=pending every 9s (shared-hosting reality: push needs websockets,
   polling ships "without refresh" honestly). Attach writes a REAL prescription
   (photo kept on the register entry), claims the inbox row, and auto-selects the
   script in the bill's dropdown through the same onRxPick path. */
  state.rxInbox = [];
  let sxActive = null;
  const SX_SEEN_KEY = 'mf-pos-rxinbox-seen';
  function sxSeenId() { try { return parseInt(localStorage.getItem(SX_SEEN_KEY) || '0', 10) || 0; } catch (e) { return 0; } }
  function sxMarkSeen(n) { try { localStorage.setItem(SX_SEEN_KEY, String(n)); } catch (e) { /* storage locked */ } }
  function paintScanRxPill() {
    const pill = $('#posScanRx'); if (!pill) return;
    $('#posScanRxTxt').textContent = state.rxInbox.length;
    // Show whenever the API is live, even at 0 — the modal holds the
    // "In-store QR" poster button, so it must be reachable on day one,
    // before any customer has sent a photo yet.
    pill.classList.toggle('show', !!(MF.Api && MF.Api.live));
    pill.style.opacity = state.rxInbox.length ? '1' : '.55';
    pill.title = state.rxInbox.length
      ? 'Prescription photos waiting — click to review'
      : 'Scan & Send Rx — open the inbox or print the in-store QR poster';
  }
  async function refreshScanRx(announce) {
    if (!(MF.Api && MF.Api.live)) return;
    try {
      const res = await MF.Api.get('rx-inbox.php?action=pending');
      const rows = (res.data && res.data.inbox) || [];
      const latest = (res.data && res.data.latest) || rows.reduce((m, r) => Math.max(m, +r.id), 0);
      state.rxInbox = rows;
      if (announce && latest > sxSeenId() && rows.length) {
        MF.toast('New prescription photo received — open the QR inbox in the meta bar to attach it.', 'info', 'Scan & Send Rx');
      }
      if (latest > sxSeenId()) sxMarkSeen(latest);
      paintScanRxPill();
      if ($('#posScanRxModal')?.classList.contains('show')) paintScanRxList();
    } catch (e) { /* network blip — next poll */ }
  }
  function paintScanRxList() {
    const box = $('#sxList'); if (!box) return;
    if (!state.rxInbox.length) {
      box.innerHTML = `<div class="empty-state py-4"><i class="bi bi-inbox"></i>Inbox empty — nothing sent in yet.</div>`;
      return;
    }
    box.innerHTML = state.rxInbox.map((r) => {
      const when = String(r.created_at || '').replace('T', ' ').slice(0, 16);
      return `<div class="d-flex align-items-center gap-2 p-2 border-bottom">
        <img src="${MF.esc(r.image_path)}" alt="Rx photo" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid #e3ebf4;cursor:pointer" data-sximg="${MF.esc(r.image_path)}">
        <div class="flex-grow-1" style="min-width:0">
          <div class="fw-semibold" style="font-size:.84rem">${r.sender_phone ? MF.esc('☎ ' + r.sender_phone) : 'No number given'}</div>
          <div class="text-2" style="font-size:.72rem">${MF.esc(when)}${r.note ? ' · ' + MF.esc(r.note) : ''}</div>
        </div>
        <button type="button" class="btn btn-mf-soft btn-sm" data-sx="${r.id}"><i class="bi bi-paperclip me-1"></i>Attach</button>
      </div>`;
    }).join('');
    box.querySelectorAll('[data-sx]').forEach((b) => b.addEventListener('click', () => sxOpenForm(b.dataset.sx)));
    box.querySelectorAll('[data-sximg]').forEach((img) => img.addEventListener('click', () => window.open(img.dataset.sximg, '_blank')));
  }
  function sxOpenForm(id) {
    sxActive = state.rxInbox.find((r) => String(r.id) === String(id)) || null;
    if (!sxActive) return;
    $('#sxFormTitle').textContent = 'Attach — photo #' + sxActive.id;
    $('#sxDoctor').innerHTML = '<option value="">— select doctor —</option>' +
      ((D.doctors || []).slice().sort((a, b) => String(a.name).localeCompare(String(b.name)))
        .map((d) => `<option value="${MF.esc(d.id)}">${MF.esc(d.name)}</option>`).join(''));
    $('#sxCustomer').innerHTML = '<option value="">— keep current —</option>' +
      ((D.customers || []).map((c) => `<option value="${MF.esc(c.id)}">${MF.esc(c.name)}</option>`).join(''));
    $('#sxDate').value = MF.today();
    // Phone-number magic: a sender match pre-fills patient + customer.
    if (sxActive.sender_phone) {
      const hits = (D.customers || []).filter((c) => String(c.phone || '').replace(/\D/g, '').slice(-10) === String(sxActive.sender_phone).slice(-10) && c.phone);
      if (hits.length === 1) { $('#sxPatient').value = hits[0].name; $('#sxCustomer').value = hits[0].id; }
    }
    $('#sxFormWrap').hidden = false;
    setTimeout(() => $('#sxPatient').focus(), 150);
  }
  async function sxAttach() {
    if (!sxActive) return;
    const patient = $('#sxPatient').value.trim();
    if (!patient) { MF.toast('Patient name is needed on the register entry.', 'warn', 'Scan & Send Rx'); return; }
    const doctorId = $('#sxDoctor').value;
    if (!doctorId) { MF.toast('Pick the prescribing doctor.', 'warn', 'Scan & Send Rx'); return; }
    const btn = $('#sxAttach');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Attaching…';
    try {
      const customerId = $('#sxCustomer').value || ($('#posCustomer') ? $('#posCustomer').value : '');
      const res = await MF.Api.post('prescriptions.php', {
        customer_id: customerId || 0, patient_name: patient,
        doctor_id: doctorId, rx_date: $('#sxDate').value || MF.today(),
        diagnosis: 'Scan & Send — see attached photo', status: 'Ready',
        image_path: sxActive.image_path,
        items: [{ name: 'Prescription photo received via Scan & Send (inbox #' + sxActive.id + ')', qty: 1 }],
      });
      await MF.Api.post('rx-inbox.php', { action: 'claim', id: sxActive.id });
      $('#sxFormWrap').hidden = true;
      ['sxPatient'].forEach((id) => { $('#' + id).value = ''; });
      sxActive = null;
      await refreshScanRx(false);
      MF.toast('Prescription saved and ready to pick.', 'success', 'Scan & Send Rx');
      // Auto-select it in the bill through the stock attach path.
      setRxAttached(true);
      await loadRxOptions().catch(() => {});
      const sel = $('#posRx');
      if (sel && [...sel.options].some((o) => String(o.value) === String(res.id))) {
        sel.value = String(res.id);
        await onRxPick().catch(() => {});
      }
    } catch (e) {
      MF.toast(e.message || 'Could not attach this photo.', 'err', 'Scan & Send Rx');
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-paperclip me-1"></i>Attach to current bill';
    }
  }
  async function sxDismiss() {
    if (!sxActive) return;
    const ok = await MF.confirm({ title: 'Dismiss this photo?', message: 'It leaves the inbox without becoming a prescription. Use only for junk or wrong photos.', confirmText: 'Dismiss', cancelText: 'Keep', tone: 'danger' });
    if (!ok) return;
    try {
      await MF.Api.post('rx-inbox.php', { action: 'dismiss', id: sxActive.id });
      $('#sxFormWrap').hidden = true; sxActive = null;
      await refreshScanRx(false);
    } catch (e) {
      MF.toast(e.message || 'Could not dismiss.', 'err', 'Scan & Send Rx');
    }
  }
  function printScanRxPoster() {
    if (typeof QRCode === 'undefined') { MF.toast('QR library not ready on this page.', 'warn', 'Scan & Send Rx'); return; }
    const url = new URL('rx-send.php', location.href).href;
    const host = document.createElement('div');
    host.style.display = 'none';
    document.body.appendChild(host);
    new QRCode(host, { text: url, width: 210, height: 210, correctLevel: QRCode.CorrectLevel.M });
    // Canvas pixels are NOT innerHTML — a painted <canvas> serializes empty.
    // Bake it into a PNG data-URL <img> so the print document really carries the QR.
    const cv = host.querySelector('canvas');
    const qr = cv
      ? `<img src="${cv.toDataURL('image/png')}" width="210" height="210" alt="Scan to send Rx">`
      : host.innerHTML;
    host.remove();
    MF.printHtml(`
      <div style="max-width:380px;margin:0 auto;text-align:center;border:2px solid #176B5B;border-radius:18px;padding:26px 20px">
        <h6 class="fw-bold mb-1" style="font-size:1.15rem;color:#176B5B">${MF.esc(D.store?.name || 'Pharmacy')}</h6>
        <div class="text-2 small mb-3">Scan &amp; Send Rx</div>
        <div style="display:inline-block;padding:10px;border:1px solid #d7ebe6;border-radius:12px">${qr}</div>
        <p style="margin-top:14px;font-size:14px;line-height:1.5">Have your prescription on WhatsApp/gallery?<br><strong>Scan → send the photo → collect at counter.</strong></p>
        <div class="text-2" style="font-size:.7rem">${MF.esc(url)}</div>
      </div>`);
  }

  document.addEventListener('DOMContentLoaded', async () => {
    if (!document.getElementById('posSearch')) return;
    await MF.boot();

    initPicker('#posCustomer', '#posAddCustomer', 'customer');
    initPicker('#posDoctor', '#posAddDoctor', 'doctor');
    initLastBillChip();

    // Rx capture-later queue wiring
    refreshRxPending();
    $('#posRxPend')?.addEventListener('click', () => {
      paintRxPendingList();
      bootstrap.Modal.getOrCreateInstance($('#posRxPendModal')).show();
    });
    $('#rpSave')?.addEventListener('click', rpSave);
    $('#rpManual')?.addEventListener('click', rpMarkManual);
    $('#rpCancel')?.addEventListener('click', () => { $('#rpFormWrap').hidden = true; rpActive = null; });

    // Scan & Send Rx inbox: boot silently, then poll every 9 s (paused while tab hidden).
    refreshScanRx(false);
    setInterval(() => { if (!document.hidden) refreshScanRx(true).catch(() => {}); }, 9000);
    $('#posScanRx')?.addEventListener('click', () => {
      paintScanRxList();
      bootstrap.Modal.getOrCreateInstance($('#posScanRxModal')).show();
    });
    $('#sxQrBtn')?.addEventListener('click', printScanRxPoster);
    $('#sxAttach')?.addEventListener('click', sxAttach);
    $('#sxBack')?.addEventListener('click', () => { $('#sxFormWrap').hidden = true; sxActive = null; });
    $('#sxDismiss')?.addEventListener('click', sxDismiss);

    /* Keyboard cart editing (scope: everything 1–5). Only when focus is NOT inside
       a field or a modal — typing in the search box keeps driving search arrows. */
    document.addEventListener('keydown', (e) => {
      if (!document.getElementById('posCartBody')) return;
      if (e.target.closest('input, textarea, select, [contenteditable], .modal')) return;
      if (!state.cart.length) return;
      const k = e.key;
      if (k === 'ArrowDown' || k === 'ArrowUp') {
        e.preventDefault();
        setCartIdx(state.cartIdx < 0 ? (k === 'ArrowDown' ? 0 : state.cart.length - 1) : state.cartIdx + (k === 'ArrowDown' ? 1 : -1));
      } else if (k === '+' || k === '=') {
        if (state.cartIdx >= 0) { e.preventDefault(); cartKbAdjust(1); }
      } else if (k === '-' || k === '_') {
        if (state.cartIdx >= 0) { e.preventDefault(); cartKbAdjust(-1); }
      } else if (k === 'Delete' || k === 'Backspace') {
        if (state.cartIdx >= 0) { e.preventDefault(); cartKbRemove(); }
      } else if (k === 'Escape' && state.cartIdx >= 0) {
        e.preventDefault();
        setCartIdx(-1);
        $('#posSearch').focus();
      }
    });

    // Qty-first flag badge inside the search box — JS-injected, no markup churn.
    const searchWrap = $('.pos-search-wrap');
    if (searchWrap && !$('#posQtyFlag')) {
      searchWrap.insertAdjacentHTML('beforeend', '<span class="pos-qtyflag" id="posQtyFlag" hidden></span>');
    }

    try {
      const savedRecent = JSON.parse(sessionStorage.getItem('mf-pos-recent') || '[]');
      if (Array.isArray(savedRecent)) state.recent = savedRecent.map(String);
    } catch (e) { /* ignore */ }

    const searchClear = $('#posSearchClear');
    const syncSearchClear = () => { if (searchClear) searchClear.hidden = !$('#posSearch').value.trim(); };
    if (searchClear) {
      searchClear.addEventListener('click', (e) => {
        e.preventDefault();
        resetSearch($('#posSearch'));
        searchClear.hidden = true;
        $('#posSearch').focus();
      });
      $('#posSearch').addEventListener('input', syncSearchClear);
      syncSearchClear();
    }
    $('#posSearch').addEventListener('input', (e) => {
      state.subSeed = null;
      qtyPrefixParse(e.target); // "3*term" parks qty 3; term alone continues below
      const v = e.target.value.trim();
      // Barcode scanner: a full, exact barcode typed in one burst adds the pack straight to the cart.
      if (v.length >= 6) {
        const exact = exactBarcodeMatch(v);
        if (exact) { addToCart(exact.id); resetSearch(e.target); return; }
      }
      // Render coalesced — barcode add above stays synchronous; only card painting waits.
      queueSearchMeds(e.target.value);
    });
    $('#posSearch').addEventListener('keydown', onSearchKeys);

    /* ================= Quick Refill side panel ================= */
    const refillPanel = $('#posRefillPanel');
    const refillOverlay = $('#posRefillOverlay');
    let refillData = null;          // last lookup result
    let refillChecked = new Set();  // selected row indexes
    let refillMobile = '';          // the digits the current customer was found with

    function refillMsg(text, cls) {
      const el = $('#prfMsg');
      el.textContent = text;
      el.className = 'prf-msg text-2 small mt-2' + (cls ? ' ' + cls : '');
    }
    function refillPaintButton() {
      const n = refillChecked.size;
      const btn = $('#prfRefillBtn');
      btn.disabled = !n;
      btn.querySelector('span').textContent = 'Refill selected (' + n + ' item' + (n === 1 ? '' : 's') + ')';
    }
    function refillReset() {
      refillData = null;
      refillChecked = new Set();
      $('#prfResult').hidden = true;
      $('#prfLookup').hidden = false;
      $('#prfAvatar').textContent = '?';
      $('#prfName').textContent = 'Quick refill';
      $('#prfSub').textContent = 'Find a regular by mobile number';
      refillMsg('Type a mobile number to pull up the customer\u2019s last prescription.');
      refillPaintButton();
    }
    function refillOpen() {
      refillReset();
      refillPanel.hidden = false;
      refillOverlay.hidden = false;
      requestAnimationFrame(() => { refillPanel.classList.add('show'); refillOverlay.classList.add('show'); });
      setTimeout(() => $('#prfMobile').focus(), 220);
    }
    function refillClose() {
      refillPanel.classList.remove('show');
      refillOverlay.classList.remove('show');
      setTimeout(() => { refillPanel.hidden = true; refillOverlay.hidden = true; }, 280);
    }
    function refillMatch(name) {
      const key = String(name || '').trim().toLowerCase();
      if (!key) return null;
      return D.medicines.find((m) => String(m.name || '').toLowerCase() === key)
        || D.medicines.find((m) => { const x = String(m.name || '').toLowerCase(); return key.startsWith(x) || x.startsWith(key); })
        || D.medicines.find((m) => { const x = String(m.name || '').toLowerCase(); return key.includes(x) || x.includes(key); });
    }
    function refillPaintResult(data) {
      refillData = data;
      refillChecked = new Set();
      $('#prfLookup').hidden = true;
      $('#prfResult').hidden = false;
      $('#prfAvatar').textContent = data.customer.initials || 'P';
      $('#prfName').textContent = data.customer.name;
      const bought = Number(data.customer.purchases) || 0;
      $('#prfSub').textContent = (data.customer.phone || '') + (bought ? ' · ' + bought + ' purchase' + (bought === 1 ? '' : 's') : ' · no purchases yet');
      const d = data.daysSince;
      $('#prfLast').textContent = d == null ? 'No purchases yet' : d === 0 ? 'Today' : d === 1 ? 'Yesterday' : d + ' days ago';
      $('#prfDoctor').textContent = data.doctor || '—';
      const box = $('#prfItems');
      if (!data.items.length) {
        box.innerHTML = '<div class="prf-row" style="cursor:default"><span class="prf-med"><i class="bi bi-inbox"></i><span>No items in this customer\u2019s last sale.</span></span><span class="prf-qty"></span></div>';
      } else {
        box.innerHTML = data.items.map((it, i) => {
          const ok = !!refillMatch(it.name);
          if (ok) refillChecked.add(i);
          return '<div class="prf-row' + (ok ? '' : ' is-off') + '">' +
            '<span class="prf-med"><input type="checkbox" class="prf-check" data-i="' + i + '"' + (ok ? ' checked' : ' disabled') + '>' +
            '<span>' + MF.esc(it.name) + (ok ? '' : ' <span class="gone">not in master</span>') + '</span></span>' +
            '<span class="prf-qty">' + MF.esc(it.qtyLabel) + '</span></div>';
        }).join('');
      }
      refillPaintButton();
    }
    async function refillSearch() {
      const v = ($('#prfMobile').value || '').replace(/[^0-9]/g, '');
      if (v.length < 6) { refillMsg('Enter at least 6 digits of the mobile number.', 'is-err'); $('#prfMobile').focus(); return; }
      refillMsg('Searching…', 'is-info');
      const btn = $('#prfSearchBtn');
      btn.disabled = true;
      try {
        let data = null;
        if (MF.Api && MF.Api.live) {
          const res = await MF.Api.get('patient-lookup.php?mobile=' + encodeURIComponent(v));
          data = res && res.data;
        } else {
          refillMsg('Not connected to the API — patient lookup needs the live backend.', 'is-err');
          return;
        }
        if (!data || !data.customer) { refillMsg('No customer found for ' + v + '. Save them from the customers page first.', 'is-err'); return; }
        refillMobile = v;
        refillPaintResult(data);
      } catch (e) {
        refillMsg((e && e.message) ? e.message : 'Lookup failed — check the API file is deployed.', 'is-err');
      } finally { btn.disabled = false; }
    }
    async function refillApply() {
      if (!refillData || !refillChecked.size) return;
      const picked = [...refillChecked].map((i) => refillData.items[i]).filter(Boolean);
      let added = 0, skipped = 0;
      for (const it of picked) {
        const med = refillMatch(it.name);
        if (!med) { skipped++; continue; }
        added++;
        const unit = (it.unit === 'loose' && med.allowLoose) ? 'loose' : 'pack';
        const times = Math.max(1, Math.min(50, Number(it.qty) || 1));
        for (let n = 0; n < times; n++) addToCart(med.id, unit);
      }
      if (MF.Api && MF.Api.live) {
        try {
          await MF.Api.post('patient-refill.php', {
            customer_id: refillData.customer.id,
            customer_name: refillData.customer.name,
            items: picked.map((it) => ({ medId: it.medId, name: it.name, qty: it.qty, unit: it.unit })),
          });
        } catch (e) { /* optional log — never block the sale */ }
      }
      refillClose();
      MF.toast('Items added to current invoice' + (skipped ? ' · ' + skipped + ' skipped (not in master)' : ''), skipped ? 'warn' : 'success', 'Quick refill');
    }
    $('#posRefillBtn').addEventListener('click', refillOpen);
    $('#prfClose').addEventListener('click', refillClose);
    refillOverlay.addEventListener('click', refillClose);
    $('#prfSearchBtn').addEventListener('click', refillSearch);
    $('#prfMobile').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); refillSearch(); } if (e.key === 'Escape') refillClose(); });
    /* Full history — swaps the prescription list for every past visit (items expand inline) */
    async function refillHistory() {
      if (!refillMobile) return;
      const box = $('#prfHistList');
      $('#prfResult').hidden = true;
      $('#prfHist').hidden = false;
      box.innerHTML = '<div class="prf-msg text-2 small">Loading history…</div>';
      try {
        const res = await MF.Api.get('patient-lookup.php?mobile=' + encodeURIComponent(refillMobile) + '&history=1');
        const data = res && res.data;
        const rows = (data && data.history) || [];
        $('#prfHistVisits').textContent = String((data && data.visits) || 0);
        $('#prfHistSpend').textContent = MF.fmt(Number((data && data.lifetimeSpend) || 0), 2);
        if (!rows.length) {
          box.innerHTML = '<div class="prf-visit"><div class="prf-visit-line" style="padding:.6rem .75rem">No purchases recorded for this customer yet.</div></div>';
          return;
        }
        box.innerHTML = rows.map((r, i) => {
          const lines = (r.items || []).map((it) =>
            '<div class="prf-visit-line"><span>' + MF.esc(it.name) + '</span><span>' + MF.esc(it.qtyLabel) + ' · ' + MF.fmt(Number(it.amount) || 0, 2) + '</span></div>').join('');
          return '<div class="prf-visit" data-h="' + i + '">' +
            '<button type="button" class="prf-visit-head"><span><span class="v-inv">' + MF.esc(r.invoice || ('Sale #' + r.id)) + '</span><div class="v-date">' + MF.esc(MF.fmtDate ? MF.fmtDate(r.date) : r.date) + (r.doctor ? ' · ' + MF.esc(r.doctor) : '') + '</div></span>' +
            '<span class="v-amt">' + MF.fmt(Number(r.total) || 0, 2) + '</span><i class="bi bi-chevron-down v-caret"></i></button>' +
            '<div class="prf-visit-items">' + lines + '</div></div>';
        }).join('');
        if (rows.length) $('#prfHist').querySelector('.prf-visit')?.classList.add('open');
      } catch (e) {
        box.innerHTML = '<div class="prf-msg text-2 small is-err">' + MF.esc((e && e.message) ? e.message : 'Could not load history.') + '</div>';
      }
    }
    $('#prfHistory').addEventListener('click', refillHistory);
    $('#prfHistBack').addEventListener('click', () => { $('#prfHist').hidden = true; $('#prfResult').hidden = false; });
    $('#prfHistList').addEventListener('click', (e) => {
      const head = e.target.closest('.prf-visit-head');
      if (!head) return;
      head.closest('.prf-visit').classList.toggle('open');
    });
    $('#prfItems').addEventListener('change', (e) => {
      const cb = e.target.closest('.prf-check');
      if (!cb || cb.disabled) return;
      const i = +cb.dataset.i;
      if (cb.checked) refillChecked.add(i); else refillChecked.delete(i);
      refillPaintButton();
    });
    $('#prfItems').addEventListener('click', (e) => {
      if (e.target.closest('.prf-check')) return;
      const row = e.target.closest('.prf-row');
      if (!row || row.classList.contains('is-off')) return;
      const cb = row.querySelector('.prf-check');
      if (cb && !cb.disabled) { cb.checked = !cb.checked; cb.dispatchEvent(new Event('change', { bubbles: true })); }
    });
    $('#prfRefillBtn').addEventListener('click', refillApply);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !refillPanel.hidden) { /* handled by panel inputs too */ refillClose(); } });
    const pickTabs = $('#posPickTabs');
    if (pickTabs) pickTabs.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-pick]');
      if (!btn) return;
      state.pick = btn.dataset.pick;
      state.subSeed = null;
      pickTabs.querySelectorAll('.pos-pick-tab').forEach((b) => b.classList.toggle('is-on', b === btn));
      const input = $('#posSearch');
      if (input && input.value) input.value = '';
      searchMeds('');
    });
    searchMeds('');

    $('#posClearCart').addEventListener('click', clearCart);
    $('#posHold').addEventListener('click', holdBill);
    $('#posDraft').addEventListener('click', saveDraft);
    const held = $('#posHeldChip');
    if (held) held.addEventListener('click', () => { showHeldBills(); new bootstrap.Modal($('#posHeldModal')).show(); });
    const rxToggle = $('#posRxOn');
    if (rxToggle) {
      rxToggle.addEventListener('change', () => {
        const sch = autoRxSchedule();
        if (!rxToggle.checked && sch) {
          rxToggle.checked = true;
          MF.toast('Not while a Schedule ' + sch + ' item is in the cart — Rx verification stays on.', 'warn', 'Rx required');
        }
        const panel = $('#posRxPanel');
        if (panel) panel.hidden = !rxToggle.checked;
        if (rxToggle.checked) loadRxOptions().then(() => { if (state._rxBill) wireRxAttachment(state._rxBill); }).catch(() => {});
      });
    }
    const rxSel = $('#posRx');
    if (rxSel) rxSel.addEventListener('change', () => onRxPick().catch((e) => MF.toast(e.message, 'err', 'Prescription')));
    const custSel = $('#posCustomer');
    if (custSel) custSel.addEventListener('change', () => { if ($('#posRxOn') && $('#posRxOn').checked) loadRxOptions().catch(() => {}); });
    $('#posPrint').addEventListener('click', printInvoice);
    const printTh = $('#posPrintThermal');
    if (printTh) printTh.addEventListener('click', () => { state.printFmt = 'thermal'; paintPrintBtn(); printInvoice(); });
    const printA4 = $('#posPrintA4');
    if (printA4) printA4.addEventListener('click', () => { state.printFmt = 'a4'; paintPrintBtn(); printInvoice(); });
    const printCp = $('#posPrintComplete');
    if (printCp) printCp.addEventListener('click', () => completeSale());
    paintPrintBtn();
    $('#posComplete').addEventListener('click', completeSale);

    /* Order pad */
    actionSheet = new bootstrap.Modal($('#posActionSheet'));
    orderModal = new bootstrap.Modal($('#posOrderPadModal'));
    orderPadLoad();
    paintOrderCount();
    /* Smart reminder — POS opened with an unfinished re-order: nudge once per session */
    if (state.orderPad.length && !sessionStorage.getItem('mf-pos-order-nudge')) {
      sessionStorage.setItem('mf-pos-order-nudge', '1');
      setTimeout(() => MF.toast(`${state.orderPad.length} medicine(s) are still waiting in the Order Pad — send them to New Purchase to avoid a stockout.`, 'warn', 'Pending Re-order'), 2200);
    }
    const orderChip = $('#posOrderChip');
    if (orderChip) orderChip.addEventListener('click', () => { renderOrderPad(); orderModal.show(); });
    const orderClear = $('#posOrderClear');
    if (orderClear) orderClear.addEventListener('click', async () => {
      if (!state.orderPad.length) return;
      const ok = await MF.confirm({ title: 'Clear the order pad?', message: 'All re-order lines will be removed.', confirmText: 'Clear', tone: 'danger' });
      if (ok) { state.orderPad = []; orderPadSave(); paintOrderCount(); renderOrderPad(); }
    });
    const orderOpen = $('#posOrderOpen');
    if (orderOpen) orderOpen.addEventListener('click', () => {
      if (!state.orderPad.length) { MF.toast('Order pad is empty.', 'warn'); return; }
      location.href = 'purchase.php';
    });
    $('#posGlobalDisc').addEventListener('input', renderSummary);
    $('#posGlobalDisc').addEventListener('change', () => {
      if (state.rolePrivileged) return renderSummary();
      const el = $('#posGlobalDisc');
      let v = parseFloat(el.value) || 0;
      if (v <= 0) return;
      if (state.discMode !== 'flat') {
        if (v > DISC_CAP_STAFF) {
          el.value = DISC_CAP_STAFF;
          MF.toast(`Cashier bill-discount ceiling is ${DISC_CAP_STAFF}% — an admin login can go higher.`, 'warn', 'Discount cap');
        }
      } else {
        const base = calcTotals().subtotal;
        const capRs = base * DISC_CAP_STAFF / 100;
        if (v > capRs && base > 0) {
          el.value = capRs.toFixed(0);
          MF.toast(`Cashier bill-discount ceiling is ${DISC_CAP_STAFF}% (${MF.fmt(capRs)}) — ask an admin.`, 'warn', 'Discount cap');
        }
      }
      renderSummary();
    });
    const dPct = $('#posDiscPct'), dRs = $('#posDiscRs');
    if (dPct) dPct.addEventListener('click', () => setDiscMode('percent'));
    if (dRs) dRs.addEventListener('click', () => setDiscMode('flat'));
    paintDiscToggle();
    initPosMeta();
    bindPayments();
    renderCart();
    fillDoctors().then(() => applyRxBill()).catch(() => {});
    loadRxOptions().catch(() => {});
    applyRxBill();
    setTimeout(applyRxBill, 400);

    document.addEventListener('keydown', (e) => {
      if (!document.getElementById('posSearch')) return;
      const key = e.key;
      if (key === 'F2') { e.preventDefault(); $('#posSearch').focus(); }
      else if (key === 'F3') { e.preventDefault(); selectPay('posPayCash'); }
      else if (key === 'F4') { e.preventDefault(); selectPay('posPayUpi'); }
      else if (key === 'F5') { e.preventDefault(); selectPay('posPayCard'); }
      else if (key === 'F6') { e.preventDefault(); selectPay('posPayCredit'); }
      else if (key === 'F7') { e.preventDefault(); selectPay('posPaySplit'); }
      else if (key === 'F8') { e.preventDefault(); holdBill(); }
      else if (key === 'F9') { e.preventDefault(); saveDraft(); }
      else if (key === 'F10') { e.preventDefault(); completeSale(); }
      else if ((e.ctrlKey || e.metaKey) && key.toLowerCase() === 'p' && !e.altKey) { e.preventDefault(); printInvoice(); }
      else if (e.altKey && !e.ctrlKey && !e.metaKey && key.toLowerCase() === 'c') { e.preventDefault(); clearCart(); }
    });
  });
})();

/* UPI QR renderer (qrcodejs). Bundled so Retail POS does not need a second file. */
var QRCode;!function(){function a(a){this.mode=c.MODE_8BIT_BYTE,this.data=a,this.parsedData=[];for(var b=[],d=0,e=this.data.length;e>d;d++){var f=this.data.charCodeAt(d);f>65536?(b[0]=240|(1835008&f)>>>18,b[1]=128|(258048&f)>>>12,b[2]=128|(4032&f)>>>6,b[3]=128|63&f):f>2048?(b[0]=224|(61440&f)>>>12,b[1]=128|(4032&f)>>>6,b[2]=128|63&f):f>128?(b[0]=192|(1984&f)>>>6,b[1]=128|63&f):b[0]=f,this.parsedData=this.parsedData.concat(b)}this.parsedData.length!=this.data.length&&(this.parsedData.unshift(191),this.parsedData.unshift(187),this.parsedData.unshift(239))}function b(a,b){this.typeNumber=a,this.errorCorrectLevel=b,this.modules=null,this.moduleCount=0,this.dataCache=null,this.dataList=[]}function i(a,b){if(void 0==a.length)throw new Error(a.length+"/"+b);for(var c=0;c<a.length&&0==a[c];)c++;this.num=new Array(a.length-c+b);for(var d=0;d<a.length-c;d++)this.num[d]=a[d+c]}function j(a,b){this.totalCount=a,this.dataCount=b}function k(){this.buffer=[],this.length=0}function m(){return"undefined"!=typeof CanvasRenderingContext2D}function n(){var a=!1,b=navigator.userAgent;return/android/i.test(b)&&(a=!0,aMat=b.toString().match(/android ([0-9]\.[0-9])/i),aMat&&aMat[1]&&(a=parseFloat(aMat[1]))),a}function r(a,b){for(var c=1,e=s(a),f=0,g=l.length;g>=f;f++){var h=0;switch(b){case d.L:h=l[f][0];break;case d.M:h=l[f][1];break;case d.Q:h=l[f][2];break;case d.H:h=l[f][3]}if(h>=e)break;c++}if(c>l.length)throw new Error("Too long data");return c}function s(a){var b=encodeURI(a).toString().replace(/\%[0-9a-fA-F]{2}/g,"a");return b.length+(b.length!=a?3:0)}a.prototype={getLength:function(){return this.parsedData.length},write:function(a){for(var b=0,c=this.parsedData.length;c>b;b++)a.put(this.parsedData[b],8)}},b.prototype={addData:function(b){var c=new a(b);this.dataList.push(c),this.dataCache=null},isDark:function(a,b){if(0>a||this.moduleCount<=a||0>b||this.moduleCount<=b)throw new Error(a+","+b);return this.modules[a][b]},getModuleCount:function(){return this.moduleCount},make:function(){this.makeImpl(!1,this.getBestMaskPattern())},makeImpl:function(a,c){this.moduleCount=4*this.typeNumber+17,this.modules=new Array(this.moduleCount);for(var d=0;d<this.moduleCount;d++){this.modules[d]=new Array(this.moduleCount);for(var e=0;e<this.moduleCount;e++)this.modules[d][e]=null}this.setupPositionProbePattern(0,0),this.setupPositionProbePattern(this.moduleCount-7,0),this.setupPositionProbePattern(0,this.moduleCount-7),this.setupPositionAdjustPattern(),this.setupTimingPattern(),this.setupTypeInfo(a,c),this.typeNumber>=7&&this.setupTypeNumber(a),null==this.dataCache&&(this.dataCache=b.createData(this.typeNumber,this.errorCorrectLevel,this.dataList)),this.mapData(this.dataCache,c)},setupPositionProbePattern:function(a,b){for(var c=-1;7>=c;c++)if(!(-1>=a+c||this.moduleCount<=a+c))for(var d=-1;7>=d;d++)-1>=b+d||this.moduleCount<=b+d||(this.modules[a+c][b+d]=c>=0&&6>=c&&(0==d||6==d)||d>=0&&6>=d&&(0==c||6==c)||c>=2&&4>=c&&d>=2&&4>=d?!0:!1)},getBestMaskPattern:function(){for(var a=0,b=0,c=0;8>c;c++){this.makeImpl(!0,c);var d=f.getLostPoint(this);(0==c||a>d)&&(a=d,b=c)}return b},createMovieClip:function(a,b,c){var d=a.createEmptyMovieClip(b,c),e=1;this.make();for(var f=0;f<this.modules.length;f++)for(var g=f*e,h=0;h<this.modules[f].length;h++){var i=h*e,j=this.modules[f][h];j&&(d.beginFill(0,100),d.moveTo(i,g),d.lineTo(i+e,g),d.lineTo(i+e,g+e),d.lineTo(i,g+e),d.endFill())}return d},setupTimingPattern:function(){for(var a=8;a<this.moduleCount-8;a++)null==this.modules[a][6]&&(this.modules[a][6]=0==a%2);for(var b=8;b<this.moduleCount-8;b++)null==this.modules[6][b]&&(this.modules[6][b]=0==b%2)},setupPositionAdjustPattern:function(){for(var a=f.getPatternPosition(this.typeNumber),b=0;b<a.length;b++)for(var c=0;c<a.length;c++){var d=a[b],e=a[c];if(null==this.modules[d][e])for(var g=-2;2>=g;g++)for(var h=-2;2>=h;h++)this.modules[d+g][e+h]=-2==g||2==g||-2==h||2==h||0==g&&0==h?!0:!1}},setupTypeNumber:function(a){for(var b=f.getBCHTypeNumber(this.typeNumber),c=0;18>c;c++){var d=!a&&1==(1&b>>c);this.modules[Math.floor(c/3)][c%3+this.moduleCount-8-3]=d}for(var c=0;18>c;c++){var d=!a&&1==(1&b>>c);this.modules[c%3+this.moduleCount-8-3][Math.floor(c/3)]=d}},setupTypeInfo:function(a,b){for(var c=this.errorCorrectLevel<<3|b,d=f.getBCHTypeInfo(c),e=0;15>e;e++){var g=!a&&1==(1&d>>e);6>e?this.modules[e][8]=g:8>e?this.modules[e+1][8]=g:this.modules[this.moduleCount-15+e][8]=g}for(var e=0;15>e;e++){var g=!a&&1==(1&d>>e);8>e?this.modules[8][this.moduleCount-e-1]=g:9>e?this.modules[8][15-e-1+1]=g:this.modules[8][15-e-1]=g}this.modules[this.moduleCount-8][8]=!a},mapData:function(a,b){for(var c=-1,d=this.moduleCount-1,e=7,g=0,h=this.moduleCount-1;h>0;h-=2)for(6==h&&h--;;){for(var i=0;2>i;i++)if(null==this.modules[d][h-i]){var j=!1;g<a.length&&(j=1==(1&a[g]>>>e));var k=f.getMask(b,d,h-i);k&&(j=!j),this.modules[d][h-i]=j,e--,-1==e&&(g++,e=7)}if(d+=c,0>d||this.moduleCount<=d){d-=c,c=-c;break}}}},b.PAD0=236,b.PAD1=17,b.createData=function(a,c,d){for(var e=j.getRSBlocks(a,c),g=new k,h=0;h<d.length;h++){var i=d[h];g.put(i.mode,4),g.put(i.getLength(),f.getLengthInBits(i.mode,a)),i.write(g)}for(var l=0,h=0;h<e.length;h++)l+=e[h].dataCount;if(g.getLengthInBits()>8*l)throw new Error("code length overflow. ("+g.getLengthInBits()+">"+8*l+")");for(g.getLengthInBits()+4<=8*l&&g.put(0,4);0!=g.getLengthInBits()%8;)g.putBit(!1);for(;;){if(g.getLengthInBits()>=8*l)break;if(g.put(b.PAD0,8),g.getLengthInBits()>=8*l)break;g.put(b.PAD1,8)}return b.createBytes(g,e)},b.createBytes=function(a,b){for(var c=0,d=0,e=0,g=new Array(b.length),h=new Array(b.length),j=0;j<b.length;j++){var k=b[j].dataCount,l=b[j].totalCount-k;d=Math.max(d,k),e=Math.max(e,l),g[j]=new Array(k);for(var m=0;m<g[j].length;m++)g[j][m]=255&a.buffer[m+c];c+=k;var n=f.getErrorCorrectPolynomial(l),o=new i(g[j],n.getLength()-1),p=o.mod(n);h[j]=new Array(n.getLength()-1);for(var m=0;m<h[j].length;m++){var q=m+p.getLength()-h[j].length;h[j][m]=q>=0?p.get(q):0}}for(var r=0,m=0;m<b.length;m++)r+=b[m].totalCount;for(var s=new Array(r),t=0,m=0;d>m;m++)for(var j=0;j<b.length;j++)m<g[j].length&&(s[t++]=g[j][m]);for(var m=0;e>m;m++)for(var j=0;j<b.length;j++)m<h[j].length&&(s[t++]=h[j][m]);return s};for(var c={MODE_NUMBER:1,MODE_ALPHA_NUM:2,MODE_8BIT_BYTE:4,MODE_KANJI:8},d={L:1,M:0,Q:3,H:2},e={PATTERN000:0,PATTERN001:1,PATTERN010:2,PATTERN011:3,PATTERN100:4,PATTERN101:5,PATTERN110:6,PATTERN111:7},f={PATTERN_POSITION_TABLE:[[],[6,18],[6,22],[6,26],[6,30],[6,34],[6,22,38],[6,24,42],[6,26,46],[6,28,50],[6,30,54],[6,32,58],[6,34,62],[6,26,46,66],[6,26,48,70],[6,26,50,74],[6,30,54,78],[6,30,56,82],[6,30,58,86],[6,34,62,90],[6,28,50,72,94],[6,26,50,74,98],[6,30,54,78,102],[6,28,54,80,106],[6,32,58,84,110],[6,30,58,86,114],[6,34,62,90,118],[6,26,50,74,98,122],[6,30,54,78,102,126],[6,26,52,78,104,130],[6,30,56,82,108,134],[6,34,60,86,112,138],[6,30,58,86,114,142],[6,34,62,90,118,146],[6,30,54,78,102,126,150],[6,24,50,76,102,128,154],[6,28,54,80,106,132,158],[6,32,58,84,110,136,162],[6,26,54,82,110,138,166],[6,30,58,86,114,142,170]],G15:1335,G18:7973,G15_MASK:21522,getBCHTypeInfo:function(a){for(var b=a<<10;f.getBCHDigit(b)-f.getBCHDigit(f.G15)>=0;)b^=f.G15<<f.getBCHDigit(b)-f.getBCHDigit(f.G15);return(a<<10|b)^f.G15_MASK},getBCHTypeNumber:function(a){for(var b=a<<12;f.getBCHDigit(b)-f.getBCHDigit(f.G18)>=0;)b^=f.G18<<f.getBCHDigit(b)-f.getBCHDigit(f.G18);return a<<12|b},getBCHDigit:function(a){for(var b=0;0!=a;)b++,a>>>=1;return b},getPatternPosition:function(a){return f.PATTERN_POSITION_TABLE[a-1]},getMask:function(a,b,c){switch(a){case e.PATTERN000:return 0==(b+c)%2;case e.PATTERN001:return 0==b%2;case e.PATTERN010:return 0==c%3;case e.PATTERN011:return 0==(b+c)%3;case e.PATTERN100:return 0==(Math.floor(b/2)+Math.floor(c/3))%2;case e.PATTERN101:return 0==b*c%2+b*c%3;case e.PATTERN110:return 0==(b*c%2+b*c%3)%2;case e.PATTERN111:return 0==(b*c%3+(b+c)%2)%2;default:throw new Error("bad maskPattern:"+a)}},getErrorCorrectPolynomial:function(a){for(var b=new i([1],0),c=0;a>c;c++)b=b.multiply(new i([1,g.gexp(c)],0));return b},getLengthInBits:function(a,b){if(b>=1&&10>b)switch(a){case c.MODE_NUMBER:return 10;case c.MODE_ALPHA_NUM:return 9;case c.MODE_8BIT_BYTE:return 8;case c.MODE_KANJI:return 8;default:throw new Error("mode:"+a)}else if(27>b)switch(a){case c.MODE_NUMBER:return 12;case c.MODE_ALPHA_NUM:return 11;case c.MODE_8BIT_BYTE:return 16;case c.MODE_KANJI:return 10;default:throw new Error("mode:"+a)}else{if(!(41>b))throw new Error("type:"+b);switch(a){case c.MODE_NUMBER:return 14;case c.MODE_ALPHA_NUM:return 13;case c.MODE_8BIT_BYTE:return 16;case c.MODE_KANJI:return 12;default:throw new Error("mode:"+a)}}},getLostPoint:function(a){for(var b=a.getModuleCount(),c=0,d=0;b>d;d++)for(var e=0;b>e;e++){for(var f=0,g=a.isDark(d,e),h=-1;1>=h;h++)if(!(0>d+h||d+h>=b))for(var i=-1;1>=i;i++)0>e+i||e+i>=b||(0!=h||0!=i)&&g==a.isDark(d+h,e+i)&&f++;f>5&&(c+=3+f-5)}for(var d=0;b-1>d;d++)for(var e=0;b-1>e;e++){var j=0;a.isDark(d,e)&&j++,a.isDark(d+1,e)&&j++,a.isDark(d,e+1)&&j++,a.isDark(d+1,e+1)&&j++,(0==j||4==j)&&(c+=3)}for(var d=0;b>d;d++)for(var e=0;b-6>e;e++)a.isDark(d,e)&&!a.isDark(d,e+1)&&a.isDark(d,e+2)&&a.isDark(d,e+3)&&a.isDark(d,e+4)&&!a.isDark(d,e+5)&&a.isDark(d,e+6)&&(c+=40);for(var e=0;b>e;e++)for(var d=0;b-6>d;d++)a.isDark(d,e)&&!a.isDark(d+1,e)&&a.isDark(d+2,e)&&a.isDark(d+3,e)&&a.isDark(d+4,e)&&!a.isDark(d+5,e)&&a.isDark(d+6,e)&&(c+=40);for(var k=0,e=0;b>e;e++)for(var d=0;b>d;d++)a.isDark(d,e)&&k++;var l=Math.abs(100*k/b/b-50)/5;return c+=10*l}},g={glog:function(a){if(1>a)throw new Error("glog("+a+")");return g.LOG_TABLE[a]},gexp:function(a){for(;0>a;)a+=255;for(;a>=256;)a-=255;return g.EXP_TABLE[a]},EXP_TABLE:new Array(256),LOG_TABLE:new Array(256)},h=0;8>h;h++)g.EXP_TABLE[h]=1<<h;for(var h=8;256>h;h++)g.EXP_TABLE[h]=g.EXP_TABLE[h-4]^g.EXP_TABLE[h-5]^g.EXP_TABLE[h-6]^g.EXP_TABLE[h-8];for(var h=0;255>h;h++)g.LOG_TABLE[g.EXP_TABLE[h]]=h;i.prototype={get:function(a){return this.num[a]},getLength:function(){return this.num.length},multiply:function(a){for(var b=new Array(this.getLength()+a.getLength()-1),c=0;c<this.getLength();c++)for(var d=0;d<a.getLength();d++)b[c+d]^=g.gexp(g.glog(this.get(c))+g.glog(a.get(d)));return new i(b,0)},mod:function(a){if(this.getLength()-a.getLength()<0)return this;for(var b=g.glog(this.get(0))-g.glog(a.get(0)),c=new Array(this.getLength()),d=0;d<this.getLength();d++)c[d]=this.get(d);for(var d=0;d<a.getLength();d++)c[d]^=g.gexp(g.glog(a.get(d))+b);return new i(c,0).mod(a)}},j.RS_BLOCK_TABLE=[[1,26,19],[1,26,16],[1,26,13],[1,26,9],[1,44,34],[1,44,28],[1,44,22],[1,44,16],[1,70,55],[1,70,44],[2,35,17],[2,35,13],[1,100,80],[2,50,32],[2,50,24],[4,25,9],[1,134,108],[2,67,43],[2,33,15,2,34,16],[2,33,11,2,34,12],[2,86,68],[4,43,27],[4,43,19],[4,43,15],[2,98,78],[4,49,31],[2,32,14,4,33,15],[4,39,13,1,40,14],[2,121,97],[2,60,38,2,61,39],[4,40,18,2,41,19],[4,40,14,2,41,15],[2,146,116],[3,58,36,2,59,37],[4,36,16,4,37,17],[4,36,12,4,37,13],[2,86,68,2,87,69],[4,69,43,1,70,44],[6,43,19,2,44,20],[6,43,15,2,44,16],[4,101,81],[1,80,50,4,81,51],[4,50,22,4,51,23],[3,36,12,8,37,13],[2,116,92,2,117,93],[6,58,36,2,59,37],[4,46,20,6,47,21],[7,42,14,4,43,15],[4,133,107],[8,59,37,1,60,38],[8,44,20,4,45,21],[12,33,11,4,34,12],[3,145,115,1,146,116],[4,64,40,5,65,41],[11,36,16,5,37,17],[11,36,12,5,37,13],[5,109,87,1,110,88],[5,65,41,5,66,42],[5,54,24,7,55,25],[11,36,12],[5,122,98,1,123,99],[7,73,45,3,74,46],[15,43,19,2,44,20],[3,45,15,13,46,16],[1,135,107,5,136,108],[10,74,46,1,75,47],[1,50,22,15,51,23],[2,42,14,17,43,15],[5,150,120,1,151,121],[9,69,43,4,70,44],[17,50,22,1,51,23],[2,42,14,19,43,15],[3,141,113,4,142,114],[3,70,44,11,71,45],[17,47,21,4,48,22],[9,39,13,16,40,14],[3,135,107,5,136,108],[3,67,41,13,68,42],[15,54,24,5,55,25],[15,43,15,10,44,16],[4,144,116,4,145,117],[17,68,42],[17,50,22,6,51,23],[19,46,16,6,47,17],[2,139,111,7,140,112],[17,74,46],[7,54,24,16,55,25],[34,37,13],[4,151,121,5,152,122],[4,75,47,14,76,48],[11,54,24,14,55,25],[16,45,15,14,46,16],[6,147,117,4,148,118],[6,73,45,14,74,46],[11,54,24,16,55,25],[30,46,16,2,47,17],[8,132,106,4,133,107],[8,75,47,13,76,48],[7,54,24,22,55,25],[22,45,15,13,46,16],[10,142,114,2,143,115],[19,74,46,4,75,47],[28,50,22,6,51,23],[33,46,16,4,47,17],[8,152,122,4,153,123],[22,73,45,3,74,46],[8,53,23,26,54,24],[12,45,15,28,46,16],[3,147,117,10,148,118],[3,73,45,23,74,46],[4,54,24,31,55,25],[11,45,15,31,46,16],[7,146,116,7,147,117],[21,73,45,7,74,46],[1,53,23,37,54,24],[19,45,15,26,46,16],[5,145,115,10,146,116],[19,75,47,10,76,48],[15,54,24,25,55,25],[23,45,15,25,46,16],[13,145,115,3,146,116],[2,74,46,29,75,47],[42,54,24,1,55,25],[23,45,15,28,46,16],[17,145,115],[10,74,46,23,75,47],[10,54,24,35,55,25],[19,45,15,35,46,16],[17,145,115,1,146,116],[14,74,46,21,75,47],[29,54,24,19,55,25],[11,45,15,46,46,16],[13,145,115,6,146,116],[14,74,46,23,75,47],[44,54,24,7,55,25],[59,46,16,1,47,17],[12,151,121,7,152,122],[12,75,47,26,76,48],[39,54,24,14,55,25],[22,45,15,41,46,16],[6,151,121,14,152,122],[6,75,47,34,76,48],[46,54,24,10,55,25],[2,45,15,64,46,16],[17,152,122,4,153,123],[29,74,46,14,75,47],[49,54,24,10,55,25],[24,45,15,46,46,16],[4,152,122,18,153,123],[13,74,46,32,75,47],[48,54,24,14,55,25],[42,45,15,32,46,16],[20,147,117,4,148,118],[40,75,47,7,76,48],[43,54,24,22,55,25],[10,45,15,67,46,16],[19,148,118,6,149,119],[18,75,47,31,76,48],[34,54,24,34,55,25],[20,45,15,61,46,16]],j.getRSBlocks=function(a,b){var c=j.getRsBlockTable(a,b);if(void 0==c)throw new Error("bad rs block @ typeNumber:"+a+"/errorCorrectLevel:"+b);for(var d=c.length/3,e=[],f=0;d>f;f++)for(var g=c[3*f+0],h=c[3*f+1],i=c[3*f+2],k=0;g>k;k++)e.push(new j(h,i));return e},j.getRsBlockTable=function(a,b){switch(b){case d.L:return j.RS_BLOCK_TABLE[4*(a-1)+0];case d.M:return j.RS_BLOCK_TABLE[4*(a-1)+1];case d.Q:return j.RS_BLOCK_TABLE[4*(a-1)+2];case d.H:return j.RS_BLOCK_TABLE[4*(a-1)+3];default:return void 0}},k.prototype={get:function(a){var b=Math.floor(a/8);return 1==(1&this.buffer[b]>>>7-a%8)},put:function(a,b){for(var c=0;b>c;c++)this.putBit(1==(1&a>>>b-c-1))},getLengthInBits:function(){return this.length},putBit:function(a){var b=Math.floor(this.length/8);this.buffer.length<=b&&this.buffer.push(0),a&&(this.buffer[b]|=128>>>this.length%8),this.length++}};var l=[[17,14,11,7],[32,26,20,14],[53,42,32,24],[78,62,46,34],[106,84,60,44],[134,106,74,58],[154,122,86,64],[192,152,108,84],[230,180,130,98],[271,213,151,119],[321,251,177,137],[367,287,203,155],[425,331,241,177],[458,362,258,194],[520,412,292,220],[586,450,322,250],[644,504,364,280],[718,560,394,310],[792,624,442,338],[858,666,482,382],[929,711,509,403],[1003,779,565,439],[1091,857,611,461],[1171,911,661,511],[1273,997,715,535],[1367,1059,751,593],[1465,1125,805,625],[1528,1190,868,658],[1628,1264,908,698],[1732,1370,982,742],[1840,1452,1030,790],[1952,1538,1112,842],[2068,1628,1168,898],[2188,1722,1228,958],[2303,1809,1283,983],[2431,1911,1351,1051],[2563,1989,1423,1093],[2699,2099,1499,1139],[2809,2213,1579,1219],[2953,2331,1663,1273]],o=function(){var a=function(a,b){this._el=a,this._htOption=b};return a.prototype.draw=function(a){function g(a,b){var c=document.createElementNS("http://www.w3.org/2000/svg",a);for(var d in b)b.hasOwnProperty(d)&&c.setAttribute(d,b[d]);return c}var b=this._htOption,c=this._el,d=a.getModuleCount();Math.floor(b.width/d),Math.floor(b.height/d),this.clear();var h=g("svg",{viewBox:"0 0 "+String(d)+" "+String(d),width:"100%",height:"100%",fill:b.colorLight});h.setAttributeNS("http://www.w3.org/2000/xmlns/","xmlns:xlink","http://www.w3.org/1999/xlink"),c.appendChild(h),h.appendChild(g("rect",{fill:b.colorDark,width:"1",height:"1",id:"template"}));for(var i=0;d>i;i++)for(var j=0;d>j;j++)if(a.isDark(i,j)){var k=g("use",{x:String(i),y:String(j)});k.setAttributeNS("http://www.w3.org/1999/xlink","href","#template"),h.appendChild(k)}},a.prototype.clear=function(){for(;this._el.hasChildNodes();)this._el.removeChild(this._el.lastChild)},a}(),p="svg"===document.documentElement.tagName.toLowerCase(),q=p?o:m()?function(){function a(){this._elImage.src=this._elCanvas.toDataURL("image/png"),this._elImage.style.display="block",this._elCanvas.style.display="none"}function d(a,b){var c=this;if(c._fFail=b,c._fSuccess=a,null===c._bSupportDataURI){var d=document.createElement("img"),e=function(){c._bSupportDataURI=!1,c._fFail&&_fFail.call(c)},f=function(){c._bSupportDataURI=!0,c._fSuccess&&c._fSuccess.call(c)};return d.onabort=e,d.onerror=e,d.onload=f,d.src="data:image/gif;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4//8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg==",void 0}c._bSupportDataURI===!0&&c._fSuccess?c._fSuccess.call(c):c._bSupportDataURI===!1&&c._fFail&&c._fFail.call(c)}if(this._android&&this._android<=2.1){var b=1/window.devicePixelRatio,c=CanvasRenderingContext2D.prototype.drawImage;CanvasRenderingContext2D.prototype.drawImage=function(a,d,e,f,g,h,i,j){if("nodeName"in a&&/img/i.test(a.nodeName))for(var l=arguments.length-1;l>=1;l--)arguments[l]=arguments[l]*b;else"undefined"==typeof j&&(arguments[1]*=b,arguments[2]*=b,arguments[3]*=b,arguments[4]*=b);c.apply(this,arguments)}}var e=function(a,b){this._bIsPainted=!1,this._android=n(),this._htOption=b,this._elCanvas=document.createElement("canvas"),this._elCanvas.width=b.width,this._elCanvas.height=b.height,a.appendChild(this._elCanvas),this._el=a,this._oContext=this._elCanvas.getContext("2d"),this._bIsPainted=!1,this._elImage=document.createElement("img"),this._elImage.style.display="none",this._el.appendChild(this._elImage),this._bSupportDataURI=null};return e.prototype.draw=function(a){var b=this._elImage,c=this._oContext,d=this._htOption,e=a.getModuleCount(),f=d.width/e,g=d.height/e,h=Math.round(f),i=Math.round(g);b.style.display="none",this.clear();for(var j=0;e>j;j++)for(var k=0;e>k;k++){var l=a.isDark(j,k),m=k*f,n=j*g;c.strokeStyle=l?d.colorDark:d.colorLight,c.lineWidth=1,c.fillStyle=l?d.colorDark:d.colorLight,c.fillRect(m,n,f,g),c.strokeRect(Math.floor(m)+.5,Math.floor(n)+.5,h,i),c.strokeRect(Math.ceil(m)-.5,Math.ceil(n)-.5,h,i)}this._bIsPainted=!0},e.prototype.makeImage=function(){this._bIsPainted&&d.call(this,a)},e.prototype.isPainted=function(){return this._bIsPainted},e.prototype.clear=function(){this._oContext.clearRect(0,0,this._elCanvas.width,this._elCanvas.height),this._bIsPainted=!1},e.prototype.round=function(a){return a?Math.floor(1e3*a)/1e3:a},e}():function(){var a=function(a,b){this._el=a,this._htOption=b};return a.prototype.draw=function(a){for(var b=this._htOption,c=this._el,d=a.getModuleCount(),e=Math.floor(b.width/d),f=Math.floor(b.height/d),g=['<table style="border:0;border-collapse:collapse;">'],h=0;d>h;h++){g.push("<tr>");for(var i=0;d>i;i++)g.push('<td style="border:0;border-collapse:collapse;padding:0;margin:0;width:'+e+"px;height:"+f+"px;background-color:"+(a.isDark(h,i)?b.colorDark:b.colorLight)+';"></td>');g.push("</tr>")}g.push("</table>"),c.innerHTML=g.join("");var j=c.childNodes[0],k=(b.width-j.offsetWidth)/2,l=(b.height-j.offsetHeight)/2;k>0&&l>0&&(j.style.margin=l+"px "+k+"px")},a.prototype.clear=function(){this._el.innerHTML=""},a}();QRCode=function(a,b){if(this._htOption={width:256,height:256,typeNumber:4,colorDark:"#000000",colorLight:"#ffffff",correctLevel:d.H},"string"==typeof b&&(b={text:b}),b)for(var c in b)this._htOption[c]=b[c];"string"==typeof a&&(a=document.getElementById(a)),this._android=n(),this._el=a,this._oQRCode=null,this._oDrawing=new q(this._el,this._htOption),this._htOption.text&&this.makeCode(this._htOption.text)},QRCode.prototype.makeCode=function(a){this._oQRCode=new b(r(a,this._htOption.correctLevel),this._htOption.correctLevel),this._oQRCode.addData(a),this._oQRCode.make(),this._el.title=a,this._oDrawing.draw(this._oQRCode),this.makeImage()},QRCode.prototype.makeImage=function(){"function"==typeof this._oDrawing.makeImage&&(!this._android||this._android>=3)&&this._oDrawing.makeImage()},QRCode.prototype.clear=function(){this._oDrawing.clear()},QRCode.CorrectLevel=d}();