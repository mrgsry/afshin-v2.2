<?php
require_once 'functions.php';
require_login();

// Proses delete jika form modal dikirim
if (isset($_POST['delete_id'])) {
    $id = intval($_POST['delete_id']);
    mysqli_query($mysqli, "DELETE FROM invoices WHERE id=$id");
    flash_set('success', 'Invoice berhasil dihapus');
    header('Location: invoices_list.php');
    exit;
}

// --- Konfigurasi Pagination ---
$limit = 15; // Dinaikkan untuk tampilan modern
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// --- Filter dan Search ---
$search = trim($_GET['search'] ?? '');
$filter_customer = intval($_GET['customer'] ?? 0);
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_status = $_GET['status'] ?? 'all'; // Filter status baru

// Fungsi untuk menentukan status faktur
function getInvoiceStatus($faktur)
{
    $faktur = trim($faktur ?? '');

    if (
        empty($faktur) || $faktur === '-' || $faktur === '000' ||
        $faktur === 'NULL' || $faktur === 'null' ||
        preg_match('/^[-\s]*$/', $faktur)
    ) {
        return [
            'status' => 'PENDING',
            'badge' => 'badge-warning',
            'icon' => 'fa-clock'
        ];
    }

    return [
        'status' => 'SUCCESS',
        'badge' => 'badge-success',
        'icon' => 'fa-check-circle'
    ];
}

// Build WHERE conditions
$where_conditions = [];

// Search
if (!empty($search)) {
    $search_esc = mysqli_real_escape_string($mysqli, $search);
    $where_conditions[] = "(
        i.invoice_no LIKE '%$search_esc%' OR
        i.faktur_inv LIKE '%$search_esc%' OR
        i.po_number LIKE '%$search_esc%' OR
        c.name LIKE '%$search_esc%'
    )";
}

// Filter customer
if ($filter_customer > 0) {
    $where_conditions[] = "i.customer_id = $filter_customer";
}

// Filter tanggal
if (!empty($filter_date_from)) {
    $where_conditions[] = "DATE(i.created_at) >= '$filter_date_from'";
}

if (!empty($filter_date_to)) {
    $where_conditions[] = "DATE(i.created_at) <= '$filter_date_to'";
}

// Filter status
if ($filter_status === 'pending') {
    $where_conditions[] = "(i.faktur_inv IS NULL OR i.faktur_inv = '' OR i.faktur_inv = '-' OR i.faktur_inv = '000' OR i.faktur_inv = 'NULL')";
} elseif ($filter_status === 'success') {
    $where_conditions[] = "(i.faktur_inv IS NOT NULL AND i.faktur_inv != '' AND i.faktur_inv != '-' AND i.faktur_inv != '000' AND i.faktur_inv != 'NULL')";
}

// Gabungkan kondisi WHERE
$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_conditions);
}

// Query untuk data
$query = "
    SELECT 
    i.*,
    c.name AS customer_name,
    COUNT(ii.id) AS item_count,
    MIN(ii.description) AS first_description,
    MIN(ii.satuan) AS first_satuan
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    LEFT JOIN invoice_items ii ON ii.invoice_id = i.id
    $where_sql
    GROUP BY i.id
    ORDER BY i.created_at DESC, i.id DESC
    LIMIT $limit OFFSET $offset
";

$res = mysqli_query($mysqli, $query);

// Query untuk total data
$count_query = "
    SELECT COUNT(DISTINCT i.id) AS total
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    $where_sql
";
$count_result = mysqli_query($mysqli, $count_query);
$total_row = mysqli_fetch_assoc($count_result);
$total_data = $total_row['total'] ?? 0;
$total_pages = ceil($total_data / $limit);

// Ambil daftar customer
$customers_query = "SELECT id, name FROM customers ORDER BY name ASC";
$customers_result = mysqli_query($mysqli, $customers_query);
$customers = [];
while ($customer = mysqli_fetch_assoc($customers_result)) {
    $customers[] = $customer;
}

// --- Export to Excel ---
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $export_query = "
        SELECT 
            i.*,
            c.name AS customer_name,
            (SELECT COUNT(*) FROM invoice_items ii WHERE ii.invoice_id = i.id) as item_count
        FROM invoices i 
        LEFT JOIN customers c ON i.customer_id = c.id 
        $where_sql
        ORDER BY i.created_at DESC, i.id DESC
    ";
    $export_result = mysqli_query($mysqli, $export_query);

    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="invoice_list_' . date('Ymd_His') . '.xls"');

    echo "<table border='1'>";
    echo "<tr>";
    echo "<th>No</th>";
    echo "<th>Invoice No</th>";
    echo "<th>Faktur Pajak</th>";
    echo "<th>Tanggal Invoice</th>";
    echo "<th>Customer</th>";
    echo "<th>PO Number</th>";
    echo "<th>Description</th>";
    echo "<th>Unit</th>";
    echo "<th>Subtotal</th>";
    echo "<th>PPN</th>";
    echo "<th>PPH</th>";
    echo "<th>Total</th>";
    echo "<th>Status</th>";
    echo "</tr>";

    $export_no = 1;
    while ($row = mysqli_fetch_assoc($export_result)) {
        $subtotal = floatval($row['subtotal']);
        $ppn = $row['ppn'] ?? 0;
        $pph = $row['pph'] ?? 0;
        $total = floatval($row['total']);

        $desc_query = "SELECT description, satuan FROM invoice_items WHERE invoice_id = {$row['id']} LIMIT 1";
        $desc_result = mysqli_query($mysqli, $desc_query);
        $desc_row = mysqli_fetch_assoc($desc_result);
        $first_description = $desc_row['description'] ?? '-';
        $first_satuan = $desc_row['satuan'] ?? '-';

        $created_date = !empty($row['created_at']) ? date('d/m/Y', strtotime($row['created_at'])) : '-';
        $faktur = trim($row['faktur_inv'] ?? '');

        $status_info = getInvoiceStatus($faktur);
        $invoice_status = $status_info['status'];

        echo "<tr>";
        echo "<td>" . $export_no++ . "</td>";
        echo "<td>" . htmlspecialchars($row['invoice_no']) . "</td>";
        echo "<td>" . htmlspecialchars($row['faktur_inv']) . "</td>";
        echo "<td>" . $created_date . "</td>";
        echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['po_number'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($first_description) . "</td>";
        echo "<td>" . htmlspecialchars($first_satuan) . "</td>";
        echo "<td>" . number_format($subtotal, 2) . "</td>";
        echo "<td>" . number_format($ppn, 2) . "</td>";
        echo "<td>" . number_format($pph, 2) . "</td>";
        echo "<td>" . number_format($total, 2) . "</td>";
        echo "<td>" . $invoice_status . "</td>";
        echo "</tr>";
    }

    echo "</table>";
    exit;
}

$error_msg = flash_get('error');
$success_msg = flash_get('success');

include 'header.php';
?>

<style>
    :root {
        --primary-color: #4f46e5;
        --primary-hover: #4338ca;
        --success-color: #10b981;
        --warning-color: #f59e0b;
        --danger-color: #ef4444;
        --info-color: #3b82f6;
        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-600: #4b5563;
        --gray-700: #374151;
        --gray-900: #111827;
        --border-radius: 12px;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .container-fluid {
        max-width: 1400px;
        margin: 0 auto;
    }

    /* Header Modern */
    .page-header {
        background: white;
        border-radius: var(--border-radius);
        padding: 1.5rem 2rem;
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow-md);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .page-header h3 {
        margin: 0;
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--gray-900);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .page-header h3 i {
        color: var(--primary-color);
    }

    .page-header .stats {
        display: flex;
        gap: 2rem;
        margin-top: 0.5rem;
    }

    .page-header .stat-item {
        display: flex;
        flex-direction: column;
    }

    .page-header .stat-label {
        font-size: 0.75rem;
        color: var(--gray-600);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.25rem;
    }

    .page-header .stat-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--primary-color);
    }

    /* Modern Buttons */
    .btn-modern {
        padding: 0.625rem 1.25rem;
        border-radius: 8px;
        font-weight: 500;
        font-size: 0.875rem;
        transition: all 0.2s;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: var(--shadow-sm);
    }

    .btn-modern:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .btn-modern:active {
        transform: translateY(0);
    }

    .btn-primary-modern {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
        color: white;
    }

    .btn-primary-modern:hover {
        background: linear-gradient(135deg, var(--primary-hover) 0%, #3730a3 100%);
        color: white;
    }

    .btn-success-modern {
        background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
        color: white;
    }

    .btn-success-modern:hover {
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        color: white;
    }

    /* Filter Card Modern */
    .filter-card {
        background: white;
        border-radius: var(--border-radius);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow);
    }

    .filter-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.25rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--gray-100);
    }

    .filter-card-header h6 {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        color: var(--gray-900);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .filter-card-header i {
        color: var(--primary-color);
    }

    /* Form Controls Modern */
    .form-control-modern {
        border: 1.5px solid var(--gray-200);
        border-radius: 8px;
        padding: 0.625rem 0.875rem;
        font-size: 0.875rem;
        transition: all 0.2s;
        background: white;
    }

    .form-control-modern:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        outline: none;
    }

    .form-label-modern {
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--gray-700);
        margin-bottom: 0.5rem;
        display: block;
    }

    /* Table Modern */
    .table-card {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        overflow: hidden;
    }

    .table-card-header {
        background: linear-gradient(135deg, var(--gray-50) 0%, white 100%);
        padding: 1.25rem 1.5rem;
        border-bottom: 2px solid var(--gray-100);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .table-card-header h6 {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        color: var(--gray-900);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .table-modern {
        width: 100%;
        margin: 0;
    }

    .table-modern thead th {
        background: var(--gray-50);
        color: var(--gray-700);
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1rem 0.75rem;
        border-bottom: 2px solid var(--gray-200);
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .table-modern tbody tr {
        transition: all 0.2s;
        border-bottom: 1px solid var(--gray-100);
    }

    .table-modern tbody tr:hover {
        background: var(--gray-50);
        transform: scale(1.001);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .table-modern tbody td {
        padding: 1rem 0.75rem;
        font-size: 0.875rem;
        color: var(--gray-700);
        vertical-align: middle;
    }

    /* Badge Modern */
    .badge-modern {
        padding: 0.375rem 0.75rem;
        border-radius: 6px;
        font-weight: 500;
        font-size: 0.75rem;
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
    }

    .badge-success-modern {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-warning-modern {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-info-modern {
        background: #dbeafe;
        color: #1e40af;
    }

    .badge-secondary-modern {
        background: var(--gray-200);
        color: var(--gray-700);
    }

    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 0.375rem;
    }

    .btn-action {
        padding: 0.375rem 0.625rem;
        border-radius: 6px;
        border: none;
        font-size: 0.8125rem;
        transition: all 0.2s;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        height: 32px;
    }

    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .btn-action-view {
        background: #dbeafe;
        color: #1e40af;
    }

    .btn-action-view:hover {
        background: #bfdbfe;
        color: #1e3a8a;
    }

    .btn-action-edit {
        background: #fef3c7;
        color: #92400e;
    }

    .btn-action-edit:hover {
        background: #fde68a;
        color: #78350f;
    }

    .btn-action-print {
        background: #e0e7ff;
        color: #3730a3;
    }

    .btn-action-print:hover {
        background: #c7d2fe;
        color: #312e81;
    }

    .btn-action-delete {
        background: #fee2e2;
        color: #991b1b;
    }

    .btn-action-delete:hover {
        background: #fecaca;
        color: #7f1d1d;
    }

    /* Pagination Modern */
    .pagination-modern {
        background: var(--gray-50);
        padding: 1.25rem 1.5rem;
        border-top: 2px solid var(--gray-100);
    }

    .pagination-modern .pagination {
        margin: 0;
        gap: 0.25rem;
    }

    .pagination-modern .page-link {
        border: 1.5px solid var(--gray-200);
        color: var(--gray-700);
        padding: 0.5rem 0.875rem;
        border-radius: 6px;
        font-weight: 500;
        transition: all 0.2s;
        margin: 0 0.125rem;
    }

    .pagination-modern .page-link:hover {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
        transform: translateY(-1px);
    }

    .pagination-modern .page-item.active .page-link {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
        box-shadow: var(--shadow-sm);
    }

    .pagination-modern .page-item.disabled .page-link {
        background: var(--gray-100);
        border-color: var(--gray-200);
        color: var(--gray-400);
    }

    /* Alert Modern */
    .alert-modern {
        border-radius: var(--border-radius);
        padding: 1rem 1.25rem;
        border: none;
        box-shadow: var(--shadow);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .alert-success-modern {
        background: #d1fae5;
        color: #065f46;
    }

    .alert-danger-modern {
        background: #fee2e2;
        color: #991b1b;
    }

    .alert-info-modern {
        background: #dbeafe;
        color: #1e40af;
    }

    /* Filter Tags */
    .filter-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .filter-tag {
        background: var(--gray-100);
        color: var(--gray-700);
        padding: 0.375rem 0.75rem;
        border-radius: 6px;
        font-size: 0.8125rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .filter-tag strong {
        font-weight: 600;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--gray-600);
    }

    .empty-state i {
        font-size: 4rem;
        color: var(--gray-300);
        margin-bottom: 1.5rem;
    }

    .empty-state h6 {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--gray-700);
        margin-bottom: 0.5rem;
    }

    .empty-state p {
        color: var(--gray-500);
        margin-bottom: 1.5rem;
    }

    /* Modal Modern */
    .modal-content {
        border-radius: var(--border-radius);
        border: none;
        box-shadow: var(--shadow-lg);
    }

    .modal-header {
        border-bottom: 2px solid var(--gray-100);
        padding: 1.25rem 1.5rem;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        border-top: 2px solid var(--gray-100);
        padding: 1rem 1.5rem;
    }

    /* Scrollbar Custom */
    .table-responsive::-webkit-scrollbar {
        height: 8px;
    }

    .table-responsive::-webkit-scrollbar-track {
        background: var(--gray-100);
        border-radius: 4px;
    }

    .table-responsive::-webkit-scrollbar-thumb {
        background: var(--gray-300);
        border-radius: 4px;
    }

    .table-responsive::-webkit-scrollbar-thumb:hover {
        background: var(--gray-400);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .page-header {
            padding: 1rem;
        }

        .page-header h3 {
            font-size: 1.25rem;
        }

        .filter-card,
        .table-card {
            border-radius: 8px;
        }

        .table-modern thead th,
        .table-modern tbody td {
            padding: 0.75rem 0.5rem;
            font-size: 0.8125rem;
        }

        .btn-modern {
            padding: 0.5rem 1rem;
            font-size: 0.8125rem;
        }
    }

    /* Loading State */
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .loading-spinner {
        width: 50px;
        height: 50px;
        border: 4px solid var(--gray-200);
        border-top-color: var(--primary-color);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Tooltip Modern */
    .tooltip-modern {
        position: relative;
        cursor: help;
    }

    .tooltip-modern:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        padding: 0.5rem 0.75rem;
        background: var(--gray-900);
        color: white;
        font-size: 0.75rem;
        border-radius: 6px;
        white-space: nowrap;
        margin-bottom: 0.5rem;
        z-index: 1000;
    }

    /* Number Display */
    .number-display {
        font-family: 'SF Mono', Monaco, 'Courier New', monospace;
        font-weight: 600;
        font-size: 0.875rem;
    }

    .number-positive {
        color: var(--success-color);
    }

    .number-negative {
        color: var(--danger-color);
    }

    /* Status Indicator */
    .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 0.5rem;
    }

    .status-indicator.success {
        background: var(--success-color);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
    }

    .status-indicator.warning {
        background: var(--warning-color);
        box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
    }

    /* Quick Stats Cards */
    .quick-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .stat-card {
        background: white;
        border-radius: var(--border-radius);
        padding: 1.25rem;
        box-shadow: var(--shadow);
        transition: all 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .stat-card-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        font-size: 1.5rem;
    }

    .stat-card-icon.primary {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.1) 0%, rgba(67, 56, 202, 0.1) 100%);
        color: var(--primary-color);
    }

    .stat-card-icon.success {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.1) 100%);
        color: var(--success-color);
    }

    .stat-card-icon.warning {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(217, 119, 6, 0.1) 100%);
        color: var(--warning-color);
    }

    .stat-card-label {
        font-size: 0.8125rem;
        color: var(--gray-600);
        margin-bottom: 0.25rem;
    }

    .stat-card-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--gray-900);
    }
</style>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h3>
                <i class="fas fa-file-invoice"></i>
                Daftar Invoice
            </h3>
            <div class="stats">
                <div class="stat-item">
                    <span class="stat-label">Total Invoice</span>
                    <span class="stat-value"><?php echo number_format($total_data); ?></span>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="invoices_create.php" class="btn-modern btn-primary-modern">
                <i class="fas fa-plus-circle"></i>
                <span>Buat Invoice</span>
            </a>
            <button type="button" class="btn-modern btn-success-modern" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i>
                <span>Export Excel</span>
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($error_msg): ?>
        <div class="alert-modern alert-danger-modern mb-3">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($error_msg); ?></span>
            <button type="button" class="close ml-auto" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($success_msg): ?>
        <div class="alert-modern alert-success-modern mb-3">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($success_msg); ?></span>
            <button type="button" class="close ml-auto" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Filter Card -->
    <div class="filter-card">
        <div class="filter-card-header">
            <h6>
                <i class="fas fa-filter"></i>
                Filter & Pencarian
            </h6>
        </div>
        <form method="GET" action="" id="filterForm">
            <input type="hidden" name="page" id="pageInput" value="1">
            
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label-modern">
                        <i class="fas fa-search mr-1"></i>
                        Pencarian
                    </label>
                    <input type="text" 
                           class="form-control-modern" 
                           name="search"
                           value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Invoice, Faktur, Customer, PO...">
                </div>

                <div class="col-md-2">
                    <label class="form-label-modern">
                        <i class="fas fa-building mr-1"></i>
                        Customer
                    </label>
                    <select class="form-control-modern" name="customer">
                        <option value="0">Semua Customer</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>"
                                <?php echo ($filter_customer == $customer['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label-modern">
                        <i class="fas fa-check-circle mr-1"></i>
                        Status
                    </label>
                    <select class="form-control-modern" name="status">
                        <option value="all" <?php echo ($filter_status === 'all') ? 'selected' : ''; ?>>Semua Status</option>
                        <option value="success" <?php echo ($filter_status === 'success') ? 'selected' : ''; ?>>Success</option>
                        <option value="pending" <?php echo ($filter_status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label-modern">
                        <i class="fas fa-calendar-alt mr-1"></i>
                        Dari Tanggal
                    </label>
                    <input type="date" 
                           class="form-control-modern" 
                           name="date_from"
                           value="<?php echo htmlspecialchars($filter_date_from); ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label-modern">
                        <i class="fas fa-calendar-check mr-1"></i>
                        Sampai Tanggal
                    </label>
                    <input type="date" 
                           class="form-control-modern" 
                           name="date_to"
                           value="<?php echo htmlspecialchars($filter_date_to); ?>">
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn-modern btn-primary-modern w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>

            <?php if (!empty($search) || $filter_customer > 0 || !empty($filter_date_from) || !empty($filter_date_to) || $filter_status !== 'all'): ?>
                <div class="filter-tags">
                    <?php if (!empty($search)): ?>
                        <span class="filter-tag">
                            <strong>Search:</strong> <?php echo htmlspecialchars($search); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($filter_customer > 0):
                        $customer_name = '';
                        foreach ($customers as $cust) {
                            if ($cust['id'] == $filter_customer) {
                                $customer_name = $cust['name'];
                                break;
                            }
                        }
                    ?>
                        <span class="filter-tag">
                            <strong>Customer:</strong> <?php echo htmlspecialchars($customer_name); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($filter_status !== 'all'): ?>
                        <span class="filter-tag">
                            <strong>Status:</strong> <?php echo ucfirst($filter_status); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($filter_date_from)): ?>
                        <span class="filter-tag">
                            <strong>Dari:</strong> <?php echo htmlspecialchars($filter_date_from); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($filter_date_to)): ?>
                        <span class="filter-tag">
                            <strong>Sampai:</strong> <?php echo htmlspecialchars($filter_date_to); ?>
                        </span>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-link" onclick="resetFilter()">
                        <i class="fas fa-times"></i> Reset Filter
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table Card -->
    <div class="table-card">
        <div class="table-card-header">
            <div>
                <h6>
                    <i class="fas fa-table"></i>
                    Data Invoice
                </h6>
                <small class="text-muted">Menampilkan <?php echo min($limit, $total_data); ?> dari <?php echo number_format($total_data); ?> invoice</small>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th width="3%">No</th>
                        <th width="10%">Invoice No</th>
                        <th width="8%">Tanggal</th>
                        <th width="7%">Status</th>
                        <th width="10%">Faktur</th>
                        <th width="12%">Customer</th>
                        <th width="8%">PO Number</th>
                        <th width="15%">Description</th>
                        <th width="5%">Unit</th>
                        <th width="8%">Subtotal</th>
                        <th width="6%">PPN</th>
                        <th width="6%">PPH</th>
                        <th width="8%">Total</th>
                        <th width="4%">Items</th>
                        <th width="10%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($res) > 0): ?>
                        <?php
                        $no = $offset + 1;
                        while ($row = mysqli_fetch_assoc($res)):
                            $item_count = $row['item_count'] ?? 0;
                            $total_items = $item_count + 1;

                            $subtotal = floatval($row['subtotal']);
                            $ppn = floatval($row['ppn'] ?? 0);
                            $pph = floatval($row['pph'] ?? 0);
                            $total = floatval($row['total']);

                            $first_description = htmlspecialchars($row['first_description'] ?? '');
                            $first_satuan = htmlspecialchars($row['first_satuan'] ?? '');

                            $short_description = strlen($first_description) > 40
                                ? substr($first_description, 0, 40) . '...'
                                : $first_description;

                            $created_date = !empty($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : '-';

                            $status_info = getInvoiceStatus($row['faktur_inv'] ?? '');
                            $invoice_status = $status_info['status'];
                            $status_badge = str_replace('badge-', 'badge-', $status_info['badge']) . '-modern';
                            $status_icon = $status_info['icon'];
                        ?>
                            <tr>
                                <td class="text-center">
                                    <span class="text-muted"><?php echo $no++; ?></span>
                                </td>
                                <td>
                                    <strong style="color: var(--primary-color);">
                                        <?php echo htmlspecialchars($row['invoice_no']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="text-muted"><?php echo $created_date; ?></span>
                                </td>
                                <td>
                                    <span class="badge-modern <?php echo $status_badge; ?>">
                                        <span class="status-indicator <?php echo strtolower($invoice_status); ?>"></span>
                                        <?php echo $invoice_status; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($invoice_status === 'SUCCESS'): ?>
                                        <span class="badge-modern badge-secondary-modern">
                                            <?php echo htmlspecialchars($row['faktur_inv']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($row['customer_name']); ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['po_number'])): ?>
                                        <span class="badge-modern badge-secondary-modern">
                                            <?php echo htmlspecialchars($row['po_number']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div title="<?php echo $first_description; ?>">
                                        <?php echo $short_description ?: '-'; ?>
                                        <?php if ($item_count > 0): ?>
                                            <button type="button" 
                                                    class="btn btn-xs btn-link view-items-btn p-0 ml-1"
                                                    data-invoice-id="<?php echo $row['id']; ?>"
                                                    data-invoice-no="<?php echo htmlspecialchars($row['invoice_no']); ?>">
                                                <small>+<?php echo $item_count; ?> item</small>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($first_satuan)): ?>
                                        <span class="badge-modern badge-secondary-modern">
                                            <?php echo $first_satuan; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <span class="number-display number-positive">
                                        <?php echo number_format($subtotal, 0, ',', '.'); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <span class="number-display">
                                        <?php echo number_format($ppn, 0, ',', '.'); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <span class="number-display">
                                        <?php echo number_format($pph, 0, ',', '.'); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <strong class="number-display" style="color: var(--primary-color);">
                                        <?php echo number_format($total, 0, ',', '.'); ?>
                                    </strong>
                                </td>
                                <td class="text-center">
                                    <span class="badge-modern <?php echo $total_items > 1 ? 'badge-info-modern' : 'badge-secondary-modern'; ?>">
                                        <?php echo $total_items; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="invoices_view.php?id=<?php echo $row['id']; ?>"
                                           class="btn-action btn-action-view"
                                           title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="invoices_edit.php?id=<?php echo $row['id']; ?>"
                                           class="btn-action btn-action-edit"
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="invoice_print.php?id=<?php echo $row['id']; ?>"
                                           target="_blank"
                                           class="btn-action btn-action-print"
                                           title="Cetak">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <button type="button"
                                                class="btn-action btn-action-delete"
                                                data-toggle="modal"
                                                data-target="#deleteModal"
                                                data-id="<?php echo $row['id']; ?>"
                                                data-invoice-no="<?php echo htmlspecialchars($row['invoice_no']); ?>"
                                                title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="15">
                                <div class="empty-state">
                                    <i class="fas fa-file-invoice"></i>
                                    <?php if (!empty($search) || $filter_customer > 0 || !empty($filter_date_from) || !empty($filter_date_to) || $filter_status !== 'all'): ?>
                                        <h6>Tidak ada invoice yang sesuai</h6>
                                        <p>Coba ubah atau reset filter pencarian Anda</p>
                                        <button type="button" class="btn-modern btn-primary-modern" onclick="resetFilter()">
                                            <i class="fas fa-redo"></i>
                                            Reset Filter
                                        </button>
                                    <?php else: ?>
                                        <h6>Belum ada invoice</h6>
                                        <p>Mulai dengan membuat invoice pertama Anda</p>
                                        <a href="invoices_create.php" class="btn-modern btn-primary-modern">
                                            <i class="fas fa-plus-circle"></i>
                                            Buat Invoice Pertama
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-modern">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="text-muted">
                        <small>
                            Menampilkan <?php echo ($total_data > 0) ? ($offset + 1) : 0; ?> - 
                            <?php echo min($offset + $limit, $total_data); ?> dari <?php echo number_format($total_data); ?> data
                        </small>
                    </div>
                    
                    <nav>
                        <ul class="pagination mb-0">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="#" onclick="goToPage(1); return false;">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="#" onclick="goToPage(<?php echo $page - 1; ?>); return false;">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page == 1) {
                                $end_page = min(5, $total_pages);
                            }
                            
                            if ($end_page == $total_pages) {
                                $start_page = max(1, $total_pages - 4);
                            }
                            
                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="#" onclick="goToPage(1); return false;">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="#" onclick="goToPage(<?php echo $i; ?>); return false;">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="#" onclick="goToPage(' . $total_pages . '); return false;">' . $total_pages . '</a></li>';
                            }
                            ?>
                            
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="#" onclick="goToPage(<?php echo $page + 1; ?>); return false;">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="#" onclick="goToPage(<?php echo $total_pages; ?>); return false;">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    
                    <div class="text-muted">
                        <small>Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?></small>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Items -->
<div class="modal fade" id="itemsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--info-color) 0%, #2563eb 100%); color: white;">
                <h5 class="modal-title">
                    <i class="fas fa-list-ol"></i>
                    Detail Items
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="modalContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modern btn-primary-modern" data-dismiss="modal">
                    <i class="fas fa-times"></i>
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Delete -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post">
            <input type="hidden" name="delete_id" id="delete_id">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--danger-color) 0%, #dc2626 100%); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle"></i>
                        Konfirmasi Hapus
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus invoice berikut?</p>
                    <div class="alert-modern alert-danger-modern">
                        <strong id="invoiceNoText"></strong>
                    </div>
                    <p class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        Tindakan ini tidak dapat dibatalkan!
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i>
                        Ya, Hapus
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // View items button
    $('.view-items-btn').on('click', function() {
        var invoiceId = $(this).data('invoice-id');
        var invoiceNo = $(this).data('invoice-no');

        $('#modalContent').html('<div class="text-center py-5"><div class="loading-spinner mx-auto"></div><p class="mt-3 text-muted">Memuat data...</p></div>');
        $('#itemsModal .modal-title').html('<i class="fas fa-list-ol"></i> Detail Items - ' + invoiceNo);

        $.ajax({
            url: 'get_invoice_items.php',
            type: 'GET',
            data: { invoice_id: invoiceId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let html = `
                        <div class="mb-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <small class="text-muted">Customer</small>
                                    <p class="mb-2"><strong>${response.invoice.customer_name}</strong></p>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Invoice Date</small>
                                    <p class="mb-2"><strong>${response.invoice.invoice_date}</strong></p>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">PO Number</small>
                                    <p class="mb-2"><strong>${response.invoice.po_number || '-'}</strong></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead style="background: var(--gray-50);">
                                    <tr>
                                        <th width="5%">No</th>
                                        <th width="50%">Description</th>
                                        <th width="10%">Qty</th>
                                        <th width="10%">Unit</th>
                                        <th width="12%">Unit Price</th>
                                        <th width="13%">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>`;

                    $.each(response.items, function(index, item) {
                        html += `
                            <tr>
                                <td class="text-center">${index + 1}</td>
                                <td>${item.description}</td>
                                <td class="text-center">${item.qty_formatted}</td>
                                <td class="text-center">${item.satuan}</td>
                                <td class="text-right">${item.unit_price_formatted}</td>
                                <td class="text-right"><strong>${item.amount_formatted}</strong></td>
                            </tr>`;
                    });

                    html += `
                                </tbody>
                                <tfoot style="background: var(--gray-50);">
                                    <tr>
                                        <td colspan="5" class="text-right"><strong>Subtotal:</strong></td>
                                        <td class="text-right"><strong>${response.invoice.subtotal_formatted}</strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-right"><strong>PPN:</strong></td>
                                        <td class="text-right"><strong>${response.invoice.ppn_formatted}</strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-right"><strong>PPH:</strong></td>
                                        <td class="text-right"><strong>${response.invoice.pph_formatted}</strong></td>
                                    </tr>
                                    <tr style="background: var(--primary-color); color: white;">
                                        <td colspan="5" class="text-right"><strong>Total:</strong></td>
                                        <td class="text-right"><strong>${response.invoice.total_formatted}</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>`;

                    $('#modalContent').html(html);
                } else {
                    $('#modalContent').html('<div class="alert-modern alert-danger-modern">Gagal memuat data: ' + response.message + '</div>');
                }
            },
            error: function() {
                $('#modalContent').html('<div class="alert-modern alert-danger-modern">Gagal memuat data items</div>');
            }
        });

        $('#itemsModal').modal('show');
    });

    // Delete modal
    $('#deleteModal').on('show.bs.modal', function(event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var invoiceNo = button.data('invoice-no');

        $('#delete_id').val(id);
        $('#invoiceNoText').text('Invoice No: ' + invoiceNo);
    });

    // Tooltips
    $('[title]').tooltip();
});

function goToPage(page) {
    if (page < 1 || page > <?php echo $total_pages; ?>) return;
    $('#pageInput').val(page);
    $('#filterForm').submit();
}

function resetFilter() {
    window.location.href = 'invoices_list.php';
}

function exportToExcel() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = 'invoices_list.php?' + params.toString();
}
</script>

<?php include 'footer.php'; ?>