<?php
require_once 'functions.php';
require_login();

/* ================= FILTER ================= */
$month       = $_GET['month'] ?? date('Y-m');
$filter_year = $_GET['year']  ?? date('Y');

/* ================= DELETE ================= */
if (isset($_POST['delete_id'])) {
    $id = intval($_POST['delete_id']);
    mysqli_query($mysqli, "DELETE FROM admin_invoice_items WHERE admin_invoice_id=$id");
    mysqli_query($mysqli, "DELETE FROM admin_invoices WHERE id=$id");
    flash_set('success', 'Admin Invoice berhasil dihapus');
    header('Location: admin_invoice_list.php');
    exit;
}

/* ================= KPI ================= */
$q_income = mysqli_query($mysqli, "
    SELECT SUM(aii.total) total_income
    FROM admin_invoice_items aii
    JOIN admin_invoices ai ON ai.id = aii.admin_invoice_id
    WHERE DATE_FORMAT(ai.created_at,'%Y-%m') = '$month'
");
$income       = mysqli_fetch_assoc($q_income);
$total_income = $income['total_income'] ?? 0;

$q_year = mysqli_query($mysqli, "
    SELECT SUM(aii.total) total_year
    FROM admin_invoice_items aii
    JOIN admin_invoices ai ON ai.id = aii.admin_invoice_id
    WHERE YEAR(ai.created_at) = '$filter_year'
");
$year_row   = mysqli_fetch_assoc($q_year);
$total_year = $year_row['total_year'] ?? 0;

$q_count = mysqli_query($mysqli, "
    SELECT COUNT(*) c FROM admin_invoices
    WHERE DATE_FORMAT(created_at,'%Y-%m') = '$month'
");
$inv_count_month = mysqli_fetch_assoc($q_count)['c'] ?? 0;

$q_all = mysqli_query($mysqli, "SELECT COUNT(*) c FROM admin_invoices");
$inv_count_all = mysqli_fetch_assoc($q_all)['c'] ?? 0;

/* ================= CHART ================= */
$q_chart = mysqli_query($mysqli, "
    SELECT MONTH(ai.created_at) AS bulan, SUM(aii.total) AS total
    FROM admin_invoice_items aii
    JOIN admin_invoices ai ON ai.id = aii.admin_invoice_id
    WHERE YEAR(ai.created_at) = '$filter_year'
    GROUP BY MONTH(ai.created_at)
    ORDER BY MONTH(ai.created_at)
");
$chart_data = array_fill(1, 12, 0);
while ($cr = mysqli_fetch_assoc($q_chart)) {
    $chart_data[(int)$cr['bulan']] = (float)$cr['total'];
}
$chart_labels = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$chart_values = array_values($chart_data);

/* ================= MAIN QUERY ================= */
$q = mysqli_query($mysqli, "
    SELECT ai.*, SUM(aii.total) total, COUNT(aii.id) item_count
    FROM admin_invoices ai
    LEFT JOIN admin_invoice_items aii ON aii.admin_invoice_id = ai.id
    GROUP BY ai.id
    ORDER BY ai.id DESC
");

$all_rows = [];
while ($d = mysqli_fetch_assoc($q)) $all_rows[] = $d;

/* Pre-fetch items for JS */
$item_data = [];
foreach ($all_rows as $d) {
    $iq = mysqli_query($mysqli, "
        SELECT customer_name, po_number, invoice_no
        FROM admin_invoice_items
        WHERE admin_invoice_id = " . (int)$d['id']
    );
    $items = [];
    while ($it = mysqli_fetch_assoc($iq)) $items[] = $it;
    $item_data[$d['id']] = $items;
}

include 'header.php';
?>
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap4.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap">

<style>
/* =========================================================
   DESIGN SYSTEM — Finance Dashboard
   Aesthetic: Refined Corporate Dark-Accent
   ========================================================= */
:root {
    --ink:        #0a0f1e;
    --ink2:       #1e2a45;
    --ink3:       #3a4a6b;
    --muted:      #7b8fad;
    --dim:        #a8b8d0;

    --surf:       #ffffff;
    --surf2:      #f7f9fc;
    --surf3:      #eef2f8;
    --border:     #e2e9f3;
    --border2:    #c8d5e8;

    --brand:      #0057ff;
    --brand-d:    #0041cc;
    --brand-l:    #e8efff;
    --brand-glow: rgba(0,87,255,0.15);

    --emerald:    #00a67e;
    --emerald-l:  #e6f7f3;
    --topaz:      #f59e0b;
    --topaz-l:    #fef8e8;
    --ruby:       #e5343a;
    --ruby-l:     #fdf1f1;
    --violet:     #7c3aed;
    --violet-l:   #f3f0ff;

    --font:       'Plus Jakarta Sans', sans-serif;
    --mono:       'JetBrains Mono', monospace;

    --r-sm: 8px;
    --r:    12px;
    --r-lg: 18px;
    --r-xl: 24px;

    --sh-sm: 0 1px 3px rgba(10,15,30,.06), 0 1px 2px rgba(10,15,30,.04);
    --sh:    0 4px 20px rgba(10,15,30,.07), 0 1px 6px rgba(10,15,30,.04);
    --sh-lg: 0 16px 48px rgba(10,15,30,.10), 0 4px 16px rgba(10,15,30,.06);
    --sh-brand: 0 8px 24px rgba(0,87,255,.25);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: var(--font);
    background: var(--surf2);
    color: var(--ink);
    font-size: 14px;
    line-height: 1.5;
}

/* Subtle grid texture */
body::after {
    content: '';
    position: fixed;
    inset: 0;
    background-image:
        linear-gradient(rgba(0,87,255,.018) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0,87,255,.018) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
    z-index: 0;
}

.page-wrap {
    position: relative;
    z-index: 1;
    padding: 28px 26px 48px;
    max-width: 1480px;
    margin: 0 auto;
}

/* ── PAGE HEADER ── */
.page-hd {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 30px;
}

.page-hd-left { display: flex; flex-direction: column; gap: 4px; }

.page-hd-eyebrow {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: var(--brand);
    display: flex;
    align-items: center;
    gap: 6px;
}

.page-hd-eyebrow::before {
    content: '';
    display: inline-block;
    width: 16px; height: 2px;
    background: var(--brand);
    border-radius: 2px;
}

.page-title {
    font-size: 24px;
    font-weight: 800;
    color: var(--ink);
    letter-spacing: -.03em;
    line-height: 1.15;
}

.page-hd-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

/* ── FILTER STRIP ── */
.filter-strip {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--surf);
    border: 1.5px solid var(--border);
    border-radius: var(--r);
    padding: 8px 14px;
    box-shadow: var(--sh-sm);
}

.filter-strip-sep {
    width: 1px; height: 20px;
    background: var(--border2);
}

.filter-field {
    display: flex;
    align-items: center;
    gap: 7px;
}

.filter-field label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--muted);
    white-space: nowrap;
}

.filter-field i { color: var(--brand); font-size: 12px; }

.filter-field input,
.filter-field select {
    background: transparent;
    border: none;
    font-family: var(--font);
    font-size: 13px;
    font-weight: 600;
    color: var(--ink);
    outline: none;
    cursor: pointer;
}

/* ── BUTTONS ── */
.btn-solid {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 20px;
    border-radius: var(--r-sm);
    font-family: var(--font);
    font-size: 13px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: all .2s;
    letter-spacing: -.01em;
    white-space: nowrap;
}
.btn-solid:hover { transform: translateY(-2px); text-decoration: none; }
.btn-solid:active { transform: none; }

.btn-brand {
    background: var(--brand);
    color: #fff;
    box-shadow: var(--sh-brand);
}
.btn-brand:hover {
    background: var(--brand-d);
    box-shadow: 0 10px 30px rgba(0,87,255,.35);
    color: #fff;
}

.btn-outline {
    background: var(--surf);
    color: var(--ink2);
    border: 1.5px solid var(--border2);
    box-shadow: var(--sh-sm);
}
.btn-outline:hover { border-color: var(--brand); color: var(--brand); background: var(--brand-l); }

.btn-danger {
    background: var(--ruby);
    color: #fff;
    box-shadow: 0 4px 14px rgba(229,52,58,.3);
}
.btn-danger:hover { box-shadow: 0 8px 22px rgba(229,52,58,.4); }

/* ── FLASH ── */
.flash-msg {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 13px 18px;
    border-radius: var(--r-sm);
    font-size: 13.5px;
    font-weight: 600;
    margin-bottom: 22px;
    animation: slideIn .35s ease;
}
.flash-success { background: var(--emerald-l); color: var(--emerald); border-left: 3.5px solid var(--emerald); }

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── KPI GRID ── */
.kpi-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 22px;
}
@media (max-width: 1100px) { .kpi-row { grid-template-columns: repeat(2,1fr); } }
@media (max-width: 580px)  { .kpi-row { grid-template-columns: 1fr; } }

.kpi {
    background: var(--surf);
    border: 1.5px solid var(--border);
    border-radius: var(--r-lg);
    padding: 22px 24px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--sh-sm);
    transition: transform .2s, box-shadow .2s;
    cursor: default;
}
.kpi:hover { transform: translateY(-3px); box-shadow: var(--sh-lg); }

/* Top accent bar */
.kpi::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3.5px;
    border-radius: var(--r-lg) var(--r-lg) 0 0;
}
.kpi.k-blue::before   { background: linear-gradient(90deg, #0057ff, #60a5fa); }
.kpi.k-green::before  { background: linear-gradient(90deg, #00a67e, #34d399); }
.kpi.k-amber::before  { background: linear-gradient(90deg, #f59e0b, #fcd34d); }
.kpi.k-violet::before { background: linear-gradient(90deg, #7c3aed, #a78bfa); }

/* Large decorative circle */
.kpi::after {
    content: '';
    position: absolute;
    bottom: -24px; right: -24px;
    width: 90px; height: 90px;
    border-radius: 50%;
    opacity: .055;
}
.kpi.k-blue::after   { background: var(--brand); }
.kpi.k-green::after  { background: var(--emerald); }
.kpi.k-amber::after  { background: var(--topaz); }
.kpi.k-violet::after { background: var(--violet); }

.kpi-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 18px;
}

.kpi-ico {
    width: 42px; height: 42px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
}
.kpi.k-blue  .kpi-ico { background: var(--brand-l); color: var(--brand); }
.kpi.k-green .kpi-ico { background: var(--emerald-l); color: var(--emerald); }
.kpi.k-amber .kpi-ico { background: var(--topaz-l); color: var(--topaz); }
.kpi.k-violet .kpi-ico { background: var(--violet-l); color: var(--violet); }

.kpi-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .09em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 8px;
}

.kpi-val {
    font-family: var(--mono);
    font-size: 1.35rem;
    font-weight: 500;
    color: var(--ink);
    letter-spacing: -.02em;
    line-height: 1.2;
}

.kpi-foot {
    font-size: 11.5px;
    color: var(--dim);
    margin-top: 6px;
}

/* ── CHART ── */
.chart-panel {
    background: var(--surf);
    border: 1.5px solid var(--border);
    border-radius: var(--r-lg);
    padding: 24px 26px;
    margin-bottom: 22px;
    box-shadow: var(--sh-sm);
}

.panel-hd {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 22px;
    flex-wrap: wrap;
    gap: 12px;
}

.panel-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--ink);
    display: flex;
    align-items: center;
    gap: 8px;
}

.panel-title i { color: var(--brand); }

.panel-sub {
    font-size: 12px;
    color: var(--muted);
    margin-top: 3px;
}

.badge-total {
    font-family: var(--mono);
    font-size: 12px;
    font-weight: 500;
    background: var(--brand-l);
    color: var(--brand);
    border: 1.5px solid rgba(0,87,255,.18);
    border-radius: 20px;
    padding: 5px 15px;
}

.chart-wrap { height: 230px; }

/* ── TABLE PANEL ── */
.table-panel {
    background: var(--surf);
    border: 1.5px solid var(--border);
    border-radius: var(--r-lg);
    overflow: hidden;
    box-shadow: var(--sh-sm);
}

.table-panel-hd {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
    padding: 20px 24px;
    border-bottom: 1.5px solid var(--border);
    background: linear-gradient(to right, #f0f5ff 0%, var(--surf) 60%);
}

.tph-left { display: flex; flex-direction: column; gap: 2px; }
.tph-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--ink);
    display: flex;
    align-items: center;
    gap: 8px;
}
.tph-title i { color: var(--brand); }
.tph-sub { font-size: 12px; color: var(--muted); }

.tph-right { display: flex; align-items: center; gap: 10px; }

.count-chip {
    background: var(--brand-l);
    color: var(--brand);
    border: 1.5px solid rgba(0,87,255,.18);
    border-radius: 20px;
    padding: 4px 14px;
    font-size: 12px;
    font-weight: 700;
}

/* ── DATATABLE OVERRIDES ── */
.datatable-wrap { padding: 0 24px 24px; }

/* Search + length control row */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter {
    margin-top: 18px;
    margin-bottom: 14px;
}

.dataTables_wrapper .dataTables_length label,
.dataTables_wrapper .dataTables_filter label {
    font-size: 13px;
    font-weight: 600;
    color: var(--ink2);
    display: flex;
    align-items: center;
    gap: 8px;
}

.dataTables_wrapper .dataTables_length select,
.dataTables_wrapper .dataTables_filter input {
    border: 1.5px solid var(--border2) !important;
    border-radius: var(--r-sm) !important;
    padding: 7px 12px !important;
    font-family: var(--font) !important;
    font-size: 13px !important;
    color: var(--ink) !important;
    background: var(--surf) !important;
    outline: none !important;
    transition: border-color .2s, box-shadow .2s !important;
}

.dataTables_wrapper .dataTables_filter input:focus {
    border-color: var(--brand) !important;
    box-shadow: 0 0 0 3px var(--brand-glow) !important;
}

/* Table itself */
table.dataTable {
    border-collapse: collapse !important;
    width: 100% !important;
    font-size: 13.5px;
}

table.dataTable thead th {
    background: #f4f7fd !important;
    color: var(--muted) !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    letter-spacing: .09em !important;
    text-transform: uppercase !important;
    padding: 13px 16px !important;
    border-bottom: 2px solid var(--border) !important;
    border-top: none !important;
    white-space: nowrap;
}

table.dataTable thead th.sorting::after,
table.dataTable thead th.sorting_asc::after,
table.dataTable thead th.sorting_desc::after {
    color: var(--brand) !important;
    font-size: 10px !important;
}

table.dataTable tbody tr {
    border-bottom: 1px solid var(--border) !important;
    transition: background .15s !important;
}

table.dataTable tbody tr:hover {
    background: #f5f8ff !important;
}

table.dataTable tbody td {
    padding: 13px 16px !important;
    color: var(--ink) !important;
    vertical-align: middle !important;
    border-top: none !important;
}

/* Pagination */
.dataTables_wrapper .dataTables_paginate {
    margin-top: 16px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    border-radius: var(--r-sm) !important;
    font-family: var(--font) !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    padding: 6px 13px !important;
    border: 1.5px solid transparent !important;
    color: var(--ink2) !important;
    background: transparent !important;
    transition: all .18s !important;
    margin: 0 2px !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: var(--brand-l) !important;
    color: var(--brand) !important;
    border-color: rgba(0,87,255,.2) !important;
    box-shadow: none !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: var(--brand) !important;
    color: #fff !important;
    border-color: var(--brand) !important;
    box-shadow: 0 3px 10px rgba(0,87,255,.3) !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
    color: var(--dim) !important;
}

.dataTables_wrapper .dataTables_info {
    font-size: 12.5px;
    color: var(--muted);
    margin-top: 16px;
    padding-top: 0 !important;
}

/* DT Buttons */
.dt-buttons { display: flex; gap: 6px; margin-top: 18px; }

.dt-button {
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    padding: 8px 16px !important;
    border-radius: var(--r-sm) !important;
    font-family: var(--font) !important;
    font-size: 12.5px !important;
    font-weight: 600 !important;
    border: 1.5px solid var(--border2) !important;
    background: var(--surf) !important;
    color: var(--ink2) !important;
    cursor: pointer !important;
    transition: all .2s !important;
    text-shadow: none !important;
    box-shadow: var(--sh-sm) !important;
}

.dt-button:hover {
    background: var(--brand-l) !important;
    color: var(--brand) !important;
    border-color: rgba(0,87,255,.25) !important;
    box-shadow: none !important;
}

/* ── TABLE CELLS ── */
.inv-no-tag {
    font-family: var(--mono);
    font-size: 12px;
    font-weight: 500;
    color: var(--brand);
    background: var(--brand-l);
    border: 1px solid rgba(0,87,255,.18);
    padding: 4px 10px;
    border-radius: 6px;
    display: inline-block;
    letter-spacing: -.01em;
}

.date-main { font-weight: 600; color: var(--ink); font-size: 13px; }
.date-sub  { font-size: 11.5px; color: var(--muted); margin-top: 1px; }

.overdue-tag {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    background: var(--ruby-l);
    color: var(--ruby);
    border: 1px solid rgba(229,52,58,.2);
    border-radius: 4px;
    padding: 2px 7px;
    margin-top: 2px;
}

.money-green {
    font-family: var(--mono);
    font-size: 13px;
    font-weight: 500;
    color: var(--emerald);
    letter-spacing: -.01em;
}

.item-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: var(--surf3);
    border: 1px solid var(--border2);
    color: var(--ink2);
    border-radius: 6px;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all .18s;
}
.item-chip:hover {
    background: var(--brand);
    color: #fff;
    border-color: var(--brand);
    box-shadow: 0 3px 10px rgba(0,87,255,.25);
}
.item-chip i { font-size: 11px; }

.cust-name { font-weight: 600; color: var(--ink); font-size: 13px; }
.cust-meta { font-size: 11.5px; color: var(--muted); margin-top: 2px; }

/* ── ACTION BTNS ── */
.act-group { display: flex; gap: 5px; }

.act-btn {
    width: 31px; height: 31px;
    border-radius: 7px;
    border: 1.5px solid transparent;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    cursor: pointer;
    transition: all .18s;
    text-decoration: none;
    background: none;
}
.act-btn:hover { transform: translateY(-2px); }

.act-edit  { background: var(--topaz-l); color: var(--topaz); border-color: rgba(245,158,11,.2); }
.act-edit:hover  { background: var(--topaz); color: #fff; border-color: var(--topaz); box-shadow: 0 3px 10px rgba(245,158,11,.3); }

.act-print { background: var(--brand-l); color: var(--brand); border-color: rgba(0,87,255,.2); }
.act-print:hover { background: var(--brand); color: #fff; border-color: var(--brand); box-shadow: 0 3px 10px rgba(0,87,255,.3); }

.act-del   { background: var(--ruby-l); color: var(--ruby); border-color: rgba(229,52,58,.2); }
.act-del:hover   { background: var(--ruby); color: #fff; border-color: var(--ruby); box-shadow: 0 3px 10px rgba(229,52,58,.3); }

/* ── MODALS ── */
.modal-content {
    border: 1.5px solid var(--border) !important;
    border-radius: var(--r-lg) !important;
    background: var(--surf) !important;
    box-shadow: var(--sh-lg) !important;
}

.modal-header {
    padding: 20px 24px !important;
    border-bottom: 1.5px solid var(--border) !important;
    background: linear-gradient(to right, #f0f5ff, var(--surf)) !important;
    border-radius: var(--r-lg) var(--r-lg) 0 0 !important;
}

.modal-body { padding: 22px 24px !important; }

.modal-footer {
    padding: 14px 24px !important;
    border-top: 1.5px solid var(--border) !important;
    background: var(--surf2) !important;
}

.modal-title {
    font-size: 15px !important;
    font-weight: 700 !important;
    color: var(--ink) !important;
    display: flex !important;
    align-items: center !important;
    gap: 9px !important;
}

.close {
    background: none !important;
    border: none !important;
    font-size: 1.4rem !important;
    color: var(--muted) !important;
    cursor: pointer !important;
    line-height: 1 !important;
    padding: 0 !important;
    transition: color .15s !important;
}
.close:hover { color: var(--ink) !important; }

/* Detail table in modal */
.dtl-table { width: 100%; border-collapse: collapse; }
.dtl-table thead th {
    background: var(--surf3);
    color: var(--muted);
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .09em;
    text-transform: uppercase;
    padding: 10px 14px;
    border-bottom: 2px solid var(--border);
}
.dtl-table tbody td {
    padding: 12px 14px;
    font-size: 13px;
    border-bottom: 1px solid var(--border);
    color: var(--ink);
}
.dtl-table tbody tr:last-child td { border-bottom: none; }
.dtl-table tbody tr:hover td { background: #f5f8ff; }

.info-strip {
    margin-top: 14px;
    padding: 11px 15px;
    background: var(--brand-l);
    border-radius: var(--r-sm);
    font-size: 12.5px;
    color: var(--brand);
    font-weight: 600;
    border: 1px solid rgba(0,87,255,.15);
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Delete confirm */
.del-box {
    background: var(--ruby-l);
    border: 1.5px solid rgba(229,52,58,.18);
    border-radius: var(--r-sm);
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.del-box i { color: var(--ruby); font-size: 22px; flex-shrink: 0; }
.del-warn {
    font-size: 12px;
    color: var(--ruby);
    font-weight: 600;
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Empty */
.empty-tbl {
    text-align: center;
    padding: 60px 24px;
    color: var(--muted);
}
.empty-tbl i { font-size: 3rem; display: block; margin-bottom: 14px; color: var(--border2); }
.empty-tbl strong { display: block; font-size: 15px; color: var(--ink2); margin-bottom: 5px; }

/* Scroll */
.dt-scroll::-webkit-scrollbar { height: 5px; }
.dt-scroll::-webkit-scrollbar-track { background: var(--surf2); }
.dt-scroll::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }

@media (max-width: 768px) {
    .page-wrap { padding: 16px 14px 40px; }
    .page-hd   { flex-direction: column; align-items: flex-start; }
    .filter-strip { flex-wrap: wrap; }
}
</style>

<div class="page-wrap">

    <!-- ── PAGE HEADER ── -->
    <div class="page-hd">
        <div class="page-hd-left">
            <div class="page-hd-eyebrow">
                <i class="fas fa-file-invoice-dollar"></i> Keuangan
            </div>
            <div class="page-title">Admin Invoice</div>
        </div>

        <div class="page-hd-right">
            <form method="get" id="filterForm" style="display:contents;">
                <div class="filter-strip">
                    <div class="filter-field">
                        <label><i class="fas fa-calendar-alt"></i> Bulan</label>
                        <input type="month" name="month"
                               value="<?= htmlspecialchars($month) ?>"
                               onchange="document.getElementById('filterForm').submit()">
                    </div>
                    <div class="filter-strip-sep"></div>
                    <div class="filter-field">
                        <label><i class="fas fa-calendar"></i> Tahun</label>
                        <select name="year" onchange="document.getElementById('filterForm').submit()">
                            <?php for ($y = date('Y'); $y >= date('Y')-5; $y--): ?>
                            <option value="<?= $y ?>" <?= $filter_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </form>

            <a href="admin_invoice_create.php" class="btn-solid btn-brand">
                <i class="fas fa-plus"></i> Buat Invoice
            </a>
        </div>
    </div>

    <!-- ── FLASH ── -->
    <?php $flash = flash_get('success'); if ($flash): ?>
    <div class="flash-msg flash-success">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($flash) ?>
    </div>
    <?php endif; ?>

    <!-- ── KPI ── -->
    <div class="kpi-row">

        <div class="kpi k-blue">
            <div class="kpi-top">
                <div>
                    <div class="kpi-label">Pendapatan Bulan Ini</div>
                    <div class="kpi-val">Rp <?= number_format($total_income, 0, ',', '.') ?></div>
                    <div class="kpi-foot"><?= date('F Y', strtotime($month.'-01')) ?></div>
                </div>
                <div class="kpi-ico"><i class="fas fa-wallet"></i></div>
            </div>
        </div>

        <div class="kpi k-green">
            <div class="kpi-top">
                <div>
                    <div class="kpi-label">Pendapatan <?= $filter_year ?></div>
                    <div class="kpi-val">Rp <?= number_format($total_year, 0, ',', '.') ?></div>
                    <div class="kpi-foot">Akumulasi <?= $filter_year ?></div>
                </div>
                <div class="kpi-ico"><i class="fas fa-chart-bar"></i></div>
            </div>
        </div>

        <div class="kpi k-amber">
            <div class="kpi-top">
                <div>
                    <div class="kpi-label">Invoice Bulan Ini</div>
                    <div class="kpi-val"><?= number_format($inv_count_month) ?></div>
                    <div class="kpi-foot"><?= date('F Y', strtotime($month.'-01')) ?></div>
                </div>
                <div class="kpi-ico"><i class="fas fa-file-invoice"></i></div>
            </div>
        </div>

        <div class="kpi k-violet">
            <div class="kpi-top">
                <div>
                    <div class="kpi-label">Total Semua Invoice</div>
                    <div class="kpi-val"><?= number_format($inv_count_all) ?></div>
                    <div class="kpi-foot">Keseluruhan</div>
                </div>
                <div class="kpi-ico"><i class="fas fa-layer-group"></i></div>
            </div>
        </div>

    </div>

    <!-- ── CHART ── -->
    <div class="chart-panel">
        <div class="panel-hd">
            <div>
                <div class="panel-title">
                    <i class="fas fa-chart-area"></i>
                    Tren Pendapatan <?= $filter_year ?>
                </div>
                <div class="panel-sub">Pendapatan per bulan dalam tahun <?= $filter_year ?></div>
            </div>
            <div class="badge-total">
                Total: Rp <?= number_format($total_year, 0, ',', '.') ?>
            </div>
        </div>
        <div class="chart-wrap">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- ── TABLE PANEL ── -->
    <div class="table-panel">
        <div class="table-panel-hd">
            <div class="tph-left">
                <div class="tph-title">
                    <i class="fas fa-table"></i>
                    Daftar Admin Invoice
                </div>
                <div class="tph-sub">Semua invoice admin tersimpan di sini</div>
            </div>
            <div class="tph-right">
                <span class="count-chip">
                    <i class="fas fa-hashtag"></i> <?= $inv_count_all ?> Invoice
                </span>
            </div>
        </div>

        <div class="datatable-wrap">
            <table id="invoiceTable" class="table table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>No Invoice</th>
                        <th>Tanggal</th>
                        <th>Jatuh Tempo</th>
                        <th>Customer / Item</th>
                        <th>Total</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($all_rows as $d):
                        $items      = $item_data[$d['id']] ?? [];
                        $item_count = count($items);
                        $first_item = $items[0] ?? null;
                        $due        = strtotime($d['due_date']);
                        $overdue    = $due < time();
                    ?>
                    <tr>
                        <td style="color:var(--muted);font-weight:600;width:42px;text-align:center;">
                            <?= $no++ ?>
                        </td>

                        <td>
                            <span class="inv-no-tag"><?= htmlspecialchars($d['admin_invoice_no']) ?></span>
                        </td>

                        <td>
                            <div class="date-main"><?= date('d M Y', strtotime($d['created_at'])) ?></div>
                            <div class="date-sub"><?= date('H:i', strtotime($d['created_at'])) ?> WIB</div>
                        </td>

                        <td>
                            <div class="date-main" style="color:<?= $overdue ? 'var(--ruby)' : 'var(--ink)' ?>">
                                <?= date('d M Y', $due) ?>
                            </div>
                            <?php if ($overdue): ?>
                                <div><span class="overdue-tag">Overdue</span></div>
                            <?php else: ?>
                                <div class="date-sub"><?= ceil(($due - time()) / 86400) ?> hari lagi</div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($item_count === 0): ?>
                                <span style="color:var(--dim);font-size:12px;">—</span>
                            <?php elseif ($first_item): ?>
                                <div class="cust-name"><?= htmlspecialchars($first_item['customer_name']) ?></div>
                                <div class="cust-meta">
                                    PO: <?= htmlspecialchars($first_item['po_number']) ?>
                                    &nbsp;·&nbsp;
                                    INV: <?= htmlspecialchars($first_item['invoice_no']) ?>
                                    <?php if ($item_count > 1): ?>
                                        &nbsp;
                                        <span class="item-chip"
                                              onclick="showDetail(<?= $d['id'] ?>, '<?= htmlspecialchars($d['admin_invoice_no']) ?>')"
                                              title="Lihat semua item">
                                            <i class="fas fa-eye"></i> +<?= $item_count - 1 ?> item
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="money-green">Rp <?= number_format($d['total'], 0, ',', '.') ?></span>
                        </td>

                        <td>
                            <div class="act-group">
                                <a href="admin_invoice_edit.php?id=<?= $d['id'] ?>"
                                   class="act-btn act-edit" title="Edit">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <a href="admin_invoice_print.php?id=<?= $d['id'] ?>"
                                   target="_blank"
                                   class="act-btn act-print" title="Cetak">
                                    <i class="fas fa-print"></i>
                                </a>
                                <button class="act-btn act-del"
                                        data-toggle="modal"
                                        data-target="#deleteModal"
                                        data-id="<?= $d['id'] ?>"
                                        data-no="<?= htmlspecialchars($d['admin_invoice_no']) ?>"
                                        title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ── DETAIL MODAL ── -->
<div class="modal fade" id="detailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-list-ul" style="color:var(--brand)"></i>
                    Detail Item &mdash;
                    <span id="detailInvNo" style="color:var(--brand);font-family:var(--mono);font-size:14px;"></span>
                </h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="detailContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-solid btn-outline" data-dismiss="modal">
                    <i class="fas fa-times"></i> Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── DELETE MODAL ── -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form method="post">
            <input type="hidden" name="delete_id" id="delete_id">
            <div class="modal-content">
                <div class="modal-header" style="border-left:4px solid var(--ruby) !important;">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle" style="color:var(--ruby)"></i>
                        Konfirmasi Hapus
                    </h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p style="color:var(--ink2);font-size:13.5px;margin-bottom:15px;">
                        Anda akan menghapus invoice berikut secara permanen:
                    </p>
                    <div class="del-box">
                        <i class="fas fa-file-invoice"></i>
                        <div>
                            <div style="font-weight:700;color:var(--ruby);font-size:14px;" id="deleteInvoiceNo"></div>
                            <div style="font-size:12px;color:var(--ruby);opacity:.8;margin-top:3px;">Semua item terkait juga akan dihapus</div>
                        </div>
                    </div>
                    <div class="del-warn">
                        <i class="fas fa-lock"></i> Tindakan ini tidak dapat dibatalkan
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-solid btn-outline" data-dismiss="modal">
                        <i class="fas fa-arrow-left"></i> Batal
                    </button>
                    <button type="submit" class="btn-solid btn-danger">
                        <i class="fas fa-trash"></i> Ya, Hapus Sekarang
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- JS DATA -->
<script>
const allItems = <?= json_encode($item_data) ?>;
const chartLabels = <?= json_encode($chart_labels) ?>;
const chartValues = <?= json_encode($chart_values) ?>;
const filterYear  = '<?= $filter_year ?>';
const totalYear   = <?= json_encode(number_format($total_year, 0, ',', '.')) ?>;
</script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(function () {

    /* ── DataTable ── */
    $('#invoiceTable').DataTable({
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']],
        order: [[0, 'desc']],
        language: {
            search:           '',
            searchPlaceholder: '🔍  Cari invoice, customer, PO...',
            lengthMenu:       'Tampilkan _MENU_ data',
            info:             'Menampilkan _START_–_END_ dari _TOTAL_ invoice',
            infoEmpty:        'Tidak ada data',
            zeroRecords:      'Tidak ada invoice yang cocok',
            paginate: {
                first:    '<i class="fas fa-angle-double-left"></i>',
                last:     '<i class="fas fa-angle-double-right"></i>',
                next:     '<i class="fas fa-angle-right"></i>',
                previous: '<i class="fas fa-angle-left"></i>'
            }
        },
        dom: "<'row align-items-center'<'col-sm-6'B><'col-sm-6 text-right'f>>" +
             "<'row'<'col-12'tr>>" +
             "<'row align-items-center mt-2'<'col-sm-5'i><'col-sm-7 text-right'p>>",
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: '',
                title: 'Admin Invoice'
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> Print',
                className: '',
                title: 'Admin Invoice'
            },
            {
                extend: 'csvHtml5',
                text: '<i class="fas fa-file-csv"></i> CSV',
                className: '',
                title: 'Admin Invoice'
            }
        ],
        columnDefs: [
            { orderable: false, targets: [4, 6] },
            { searchable: false, targets: [0, 6] }
        ],
        scrollX: true
    });

    /* ── Chart ── */
    const ctx = document.getElementById('revenueChart').getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, 0, 230);
    grad.addColorStop(0, 'rgba(0,87,255,0.16)');
    grad.addColorStop(1, 'rgba(0,87,255,0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                data: chartValues,
                borderColor: '#0057ff',
                backgroundColor: grad,
                borderWidth: 2.5,
                fill: true,
                tension: 0.42,
                pointBackgroundColor: '#0057ff',
                pointBorderColor: '#fff',
                pointBorderWidth: 2.5,
                pointRadius: 5,
                pointHoverRadius: 7,
                pointHoverBorderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#fff',
                    borderColor: '#e2e9f3',
                    borderWidth: 1.5,
                    titleColor: '#7b8fad',
                    bodyColor: '#0a0f1e',
                    padding: 13,
                    callbacks: {
                        label: c => '  Rp ' + c.raw.toLocaleString('id-ID')
                    }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(226,233,243,.7)' },
                    ticks: { color: '#7b8fad', font: { size: 11, family: "'Plus Jakarta Sans', sans-serif" } }
                },
                y: {
                    grid: { color: 'rgba(226,233,243,.7)' },
                    ticks: {
                        color: '#7b8fad',
                        font: { size: 11, family: "'Plus Jakarta Sans', sans-serif" },
                        callback: v => 'Rp ' + (v >= 1e6 ? (v/1e6).toFixed(0)+'jt' : v >= 1e3 ? (v/1e3).toFixed(0)+'rb' : v)
                    }
                }
            }
        }
    });

    /* ── Delete modal ── */
    $('#deleteModal').on('show.bs.modal', function (e) {
        const btn = $(e.relatedTarget);
        $('#delete_id').val(btn.data('id'));
        $('#deleteInvoiceNo').text(btn.data('no'));
    });

    /* ── Clean backdrop ── */
    $(document).on('hidden.bs.modal', function () {
        if ($('.modal-backdrop').length > 1) {
            $('.modal-backdrop').not(':first').remove();
        }
        $('body').removeClass('modal-open');
    });
});

/* ── Detail modal ── */
function showDetail(invoiceId, invoiceNo) {
    document.getElementById('detailInvNo').textContent = invoiceNo;
    const items = allItems[invoiceId] || [];
    let html = '';

    if (!items.length) {
        html = '<div style="text-align:center;padding:32px;color:var(--muted);">Tidak ada item.</div>';
    } else {
        html = `<div style="overflow-x:auto;">
            <table class="dtl-table">
                <thead><tr>
                    <th>No</th>
                    <th>Customer</th>
                    <th>PO Number</th>
                    <th>Invoice No</th>
                </tr></thead>
                <tbody>`;

        items.forEach((item, i) => {
            html += `<tr>
                <td style="color:var(--muted);width:36px;">${i+1}</td>
                <td><strong>${item.customer_name}</strong></td>
                <td><span style="font-family:var(--mono);font-size:12px;color:var(--topaz);background:var(--topaz-l);padding:3px 8px;border-radius:5px;">${item.po_number}</span></td>
                <td><span style="font-family:var(--mono);font-size:12px;color:var(--brand);background:var(--brand-l);padding:3px 8px;border-radius:5px;">${item.invoice_no}</span></td>
            </tr>`;
        });

        html += `</tbody></table></div>
            <div class="info-strip">
                <i class="fas fa-info-circle"></i>
                Total <strong>${items.length} item</strong> dalam invoice ini
            </div>`;
    }

    document.getElementById('detailContent').innerHTML = html;
    $('#detailModal').modal('show');
}
</script>

<?php include 'footer.php'; ?>