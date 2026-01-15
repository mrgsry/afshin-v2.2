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
$limit = 10; // Dinaikkan menjadi 10 baris per halaman untuk user experience lebih baik
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// --- Filter dan Search ---
$search = trim($_GET['search'] ?? '');
$filter_customer = intval($_GET['customer'] ?? 0);
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Fungsi untuk menentukan status faktur
function getInvoiceStatus($faktur)
{
    $faktur = trim($faktur ?? '');

    // Jika faktur kosong, null, atau hanya mengandung karakter non-angka/dash
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
$query_params = [];

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
        echo "</tr>";
    }

    echo "</table>";
    exit;
}

$error_msg = flash_get('error');
$success_msg = flash_get('success');

include 'header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-0">Daftar Invoice</h3>
            <p class="text-muted mb-0">Total: <strong><?php echo number_format($total_data); ?></strong> invoice</p>
        </div>
        <div>
            <a href="invoices_create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus-circle"></i> Buat Invoice Baru
            </a>
            <button type="button" class="btn btn-success btn-sm" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Export to Excel
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($error_msg); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($success_msg); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Filter & Search Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light py-2">
            <h6 class="mb-0"><i class="fas fa-filter"></i> Filter & Pencarian</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" id="filterForm" class="row g-2">
                <!-- Simpan page parameter -->
                <input type="hidden" name="page" id="pageInput" value="1">

                <div class="col-md-3">
                    <label for="search" class="small mb-1">Cari (No. Invoice/Faktur/Customer/PO)</label>
                    <input type="text" class="form-control form-control-sm" id="search" name="search"
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Kata kunci...">
                </div>

                <div class="col-md-3">
                    <label for="customer" class="small mb-1">Filter Customer</label>
                    <select class="form-control form-control-sm" id="customer" name="customer">
                        <option value="0">-- Semua Customer --</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['id']; ?>"
                                <?php echo ($filter_customer == $customer['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="date_from" class="small mb-1">Dari Tanggal</label>
                    <input type="date" class="form-control form-control-sm" id="date_from" name="date_from"
                        value="<?php echo htmlspecialchars($filter_date_from); ?>">
                </div>

                <div class="col-md-2">
                    <label for="date_to" class="small mb-1">Sampai Tanggal</label>
                    <input type="date" class="form-control form-control-sm" id="date_to" name="date_to"
                        value="<?php echo htmlspecialchars($filter_date_to); ?>">
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-flex gap-1 w-100">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                            <i class="fas fa-search"></i> Cari
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="resetFilter()">
                            <i class="fas fa-redo"></i>
                        </button>
                    </div>
                </div>

                <?php if (!empty($search) || $filter_customer > 0 || !empty($filter_date_from) || !empty($filter_date_to)): ?>
                    <div class="col-12 mt-2">
                        <div class="alert alert-info py-2 mb-0">
                            <small>
                                <strong>Filter Aktif:</strong>
                                <?php if (!empty($search)): ?>
                                    <span class="badge badge-light mr-2">Search: <?php echo htmlspecialchars($search); ?></span>
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
                                    <span class="badge badge-light mr-2">Customer: <?php echo htmlspecialchars($customer_name); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($filter_date_from)): ?>
                                    <span class="badge badge-light mr-2">Dari: <?php echo htmlspecialchars($filter_date_from); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($filter_date_to)): ?>
                                    <span class="badge badge-light mr-2">Sampai: <?php echo htmlspecialchars($filter_date_to); ?></span>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-0"><i class="fas fa-list"></i> Data Invoice</h6>
                <small class="text-muted">Menampilkan <?php echo $limit; ?> data per halaman</small>
            </div>
            <div class="d-flex align-items-center">
                <span class="badge badge-info mr-3">
                    Total: <?php echo number_format($total_data); ?> invoice
                </span>
                <?php if ($total_data > $limit): ?>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary" onclick="quickNavigate('first')"
                            <?php echo ($page <= 1) ? 'disabled' : ''; ?>
                            title="Halaman pertama">
                            <i class="fas fa-angle-double-left"></i>
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="quickNavigate('prev')"
                            <?php echo ($page <= 1) ? 'disabled' : ''; ?>
                            title="Halaman sebelumnya">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="quickNavigate('next')"
                            <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>
                            title="Halaman berikutnya">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="quickNavigate('last')"
                            <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>
                            title="Halaman terakhir">
                            <i class="fas fa-angle-double-right"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0" style="font-size: 0.85rem;">
                    <thead class="thead-light sticky-top" style="top: -1px;">
                        <tr>
                            <th width="3%" class="text-center">No</th>
                            <th width="9%">Invoice No</th>
                            <th width="7%">Tanggal</th>
                            <th width="7%" class="text-center">Status</th>
                            <th width="8%">Faktur</th>
                            <th width="12%">Customer</th>
                            <th width="8%">PO Number</th>
                            <th width="18%">Description</th>
                            <th width="5%">Unit</th>
                            <th width="8%" class="text-right">Subtotal</th>
                            <th width="8%" class="text-right">PPN</th>
                            <th width="8%" class="text-right">PPH</th>
                            <th width="8%" class="text-right">Total</th>
                            <th width="4%" class="text-center">Items</th>
                            <th width="12%" class="text-center">Aksi</th>
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
                                $dpp = ($subtotal * 11) / 12;
                                $ppn = floatval($row['ppn'] ?? 0);
                                $pph = floatval($row['pph'] ?? 0);
                                $total = floatval($row['total']);

                                $first_description = htmlspecialchars($row['first_description'] ?? '');
                                $first_satuan = htmlspecialchars($row['first_satuan'] ?? '');

                                $short_description = strlen($first_description) > 50
                                    ? substr($first_description, 0, 50) . '...'
                                    : $first_description;

                                $created_date = !empty($row['created_at']) ? date('d/m/Y', strtotime($row['created_at'])) : '-';

                                // Get status from function
                                $status_info = getInvoiceStatus($row['faktur_inv'] ?? '');
                                $invoice_status = $status_info['status'];
                                $status_badge = $status_info['badge'];
                                $status_icon = $status_info['icon'];
                            ?>

                                <tr>
                                    <td class="text-center align-middle">
                                        <span class="text-muted small"><?php echo $no++; ?></span>
                                    </td>
                                    <td class="align-middle">
                                        <strong style="font-size: 0.85rem; color: #007bff;">
                                            <?php echo htmlspecialchars($row['invoice_no']); ?>
                                        </strong>
                                    </td>
                                    <td class="align-middle">
                                        <span class="text-muted"><?php echo $created_date; ?></span>
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="badge <?php echo $status_badge; ?> px-2 py-1" style="font-size: 0.7rem; min-width: 70px;">
                                            <i class="fas <?php echo $status_icon; ?>"></i>
                                            <?php echo $invoice_status; ?>
                                        </span>
                                    </td>
                                    <td class="align-middle">
                                        <?php if ($invoice_status === 'SUCCESS'): ?>
                                            <span class="badge badge-light border px-2 py-1" style="font-size: 0.7rem;">
                                                <?php echo htmlspecialchars($row['faktur_inv']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-warning px-2 py-1" style="font-size: 0.7rem;">
                                                <i class="fas fa-clock"></i> PENDING
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="align-middle">
                                        <div style="font-size: 0.8rem; font-weight: 500; color: #495057;">
                                            <?php echo htmlspecialchars($row['customer_name']); ?>
                                        </div>
                                    </td>
                                    <td class="align-middle">
                                        <?php if (!empty($row['po_number'])): ?>
                                            <span class="badge badge-light border px-2 py-1" style="font-size: 0.7rem;">
                                                <?php echo htmlspecialchars($row['po_number']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="align-middle">
                                        <div style="max-height: 50px; overflow: hidden;">
                                            <?php if (!empty($first_description)): ?>
                                                <div style="margin-bottom: 3px;" title="<?php echo $first_description; ?>">
                                                    <?php echo $short_description; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-muted small">-</div>
                                            <?php endif; ?>

                                            <?php if ($item_count > 0): ?>
                                                <div>
                                                    <button type="button" class="btn btn-xs btn-outline-info btn-sm view-items-btn"
                                                        data-invoice-id="<?php echo $row['id']; ?>"
                                                        data-invoice-no="<?php echo htmlspecialchars($row['invoice_no']); ?>"
                                                        title="Lihat semua items (<?php echo $total_items; ?> items)"
                                                        style="font-size: 0.75rem; padding: 0.1rem 0.3rem;">
                                                        <i class="fas fa-plus-circle"></i> +<?php echo $item_count; ?> item
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="align-middle">
                                        <?php if (!empty($first_satuan)): ?>
                                            <span class="badge badge-light border px-2 py-1" style="font-size: 0.7rem;">
                                                <?php echo $first_satuan; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right align-middle">
                                        <div style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 0.8rem; color: #28a745;">
                                            <?php echo number_format($subtotal, 2); ?>
                                        </div>
                                    </td>
                                    <td class="text-right align-middle">
                                        <div style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 0.8rem; color: #dc3545;">
                                            <?php echo number_format($ppn, 2); ?>
                                        </div>
                                    </td>
                                    <td class="text-right align-middle">
                                        <div style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 0.8rem; color: #dc3545;">
                                            <?php echo number_format($pph, 2); ?>
                                        </div>
                                    </td>
                                    <td class="text-right align-middle">
                                        <div style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 0.85rem; font-weight: bold; color: #007bff;">
                                            <?php echo number_format($total, 2); ?>
                                        </div>
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="badge <?php echo $total_items > 1 ? 'badge-info' : 'badge-secondary'; ?> px-2 py-1"
                                            style="font-size: 0.7rem;">
                                            <?php echo $total_items; ?>
                                        </span>
                                    </td>
                                    <td class="text-center align-middle">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="invoices_view.php?id=<?php echo $row['id']; ?>"
                                                class="btn btn-outline-info btn-sm" title="Lihat Detail"
                                                style="padding: 0.15rem 0.35rem;">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="invoices_edit.php?id=<?php echo $row['id']; ?>"
                                                class="btn btn-outline-warning btn-sm" title="Edit Invoice"
                                                style="padding: 0.15rem 0.35rem;">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="invoice_print.php?id=<?php echo $row['id']; ?>"
                                                target="_blank" class="btn btn-outline-primary btn-sm" title="Cetak"
                                                style="padding: 0.15rem 0.35rem;">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger btn-sm"
                                                data-toggle="modal" data-target="#deleteModal"
                                                data-id="<?php echo $row['id']; ?>"
                                                data-invoice-no="<?php echo htmlspecialchars($row['invoice_no']); ?>"
                                                title="Hapus"
                                                style="padding: 0.15rem 0.35rem;">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="15" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-file-invoice fa-3x mb-3"></i><br>
                                        <?php if (!empty($search) || $filter_customer > 0 || !empty($filter_date_from) || !empty($filter_date_to)): ?>
                                            <h6 class="mb-2">Tidak ada invoice yang sesuai dengan filter yang dipilih.</h6>
                                            <button type="button" class="btn btn-sm btn-secondary mt-1" onclick="resetFilter()">
                                                <i class="fas fa-redo"></i> Reset Filter
                                            </button>
                                        <?php else: ?>
                                            <h6 class="mb-2">Belum ada Invoice yang dibuat.</h6>
                                            <a href="invoices_create.php" class="btn btn-primary mt-1">
                                                <i class="fas fa-plus-circle"></i> Buat Invoice Pertama
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Enhanced Pagination Footer -->
        <?php if ($total_pages > 1): ?>
            <div class="card-footer py-2 bg-light">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        <i class="fas fa-info-circle"></i>
                        Menampilkan <?php echo ($total_data > 0) ? ($offset + 1) : 0; ?> -
                        <?php echo min($offset + $limit, $total_data); ?> dari <?php echo number_format($total_data); ?> data
                    </div>
                    
                    <div class="d-flex align-items-center">
                        <div class="mr-3">
                            <span class="small text-muted">Halaman:</span>
                            <div class="input-group input-group-sm ml-2" style="width: 120px;">
                                <input type="number" 
                                       class="form-control form-control-sm text-center" 
                                       id="pageJumpInput" 
                                       min="1" 
                                       max="<?php echo $total_pages; ?>" 
                                       value="<?php echo $page; ?>"
                                       style="height: 28px;">
                                <div class="input-group-append">
                                    <button class="btn btn-primary btn-sm" type="button" onclick="jumpToPage()" style="height: 28px;">
                                        <i class="fas fa-arrow-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm mb-0">
                                <!-- First Page -->
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="#" onclick="goToPage(1); return false;" title="Halaman pertama">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                </li>
                                
                                <!-- Previous Page -->
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="#" onclick="goToPage(<?php echo $page - 1; ?>); return false;" title="Halaman sebelumnya">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                                
                                <!-- Page Numbers -->
                                <?php
                                // Tampilkan maksimal 5 halaman
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                // Adjust jika di awal
                                if ($start_page == 1) {
                                    $end_page = min(5, $total_pages);
                                }
                                
                                // Adjust jika di akhir
                                if ($end_page == $total_pages) {
                                    $start_page = max(1, $total_pages - 4);
                                }
                                
                                // Tampilkan first page dengan ellipsis jika perlu
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
                                
                                // Tampilkan last page dengan ellipsis jika perlu
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="#" onclick="goToPage(' . $total_pages . '); return false;">' . $total_pages . '</a></li>';
                                }
                                ?>
                                
                                <!-- Next Page -->
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="#" onclick="goToPage(<?php echo $page + 1; ?>); return false;" title="Halaman berikutnya">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                                
                                <!-- Last Page -->
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="#" onclick="goToPage(<?php echo $total_pages; ?>); return false;" title="Halaman terakhir">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    
                    <div class="text-muted small">
                        <?php echo $total_pages; ?> halaman
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal untuk menampilkan detail items -->
<div class="modal fade" id="itemsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white py-2">
                <h5 class="modal-title mb-0" style="font-size: 1rem;">
                    <i class="fas fa-list-ol"></i> Detail Items
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="modalContent"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times"></i> Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Delete -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form method="post" id="deleteForm">
            <input type="hidden" name="delete_id" id="delete_id">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white py-2">
                    <h5 class="modal-title mb-0" style="font-size: 1rem;">
                        <i class="fas fa-exclamation-triangle"></i> Konfirmasi Hapus
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="small">Apakah Anda yakin ingin menghapus invoice berikut?</p>
                    <div class="alert alert-danger py-2">
                        <strong id="invoiceNoText" class="small"></strong>
                    </div>
                    <p class="text-danger small"><i class="fas fa-exclamation-circle"></i> Tindakan ini tidak dapat dibatalkan!</p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="fas fa-trash"></i> Ya, Hapus
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Set default tanggal jika kosong
        if ($('#date_from').val() === '' && $('#date_to').val() === '') {
            const today = new Date().toISOString().split('T')[0];
            $('#date_to').val(today);

            const firstDay = new Date();
            firstDay.setDate(1);
            $('#date_from').val(firstDay.toISOString().split('T')[0]);
        }

        // Hapus auto-submit untuk mencegah hilangnya filter
        $('#search, #customer, #date_from, #date_to').off('keyup change').on('change', function() {
            // Reset ke halaman 1 saat filter berubah
            $('#pageInput').val(1);
        });

        // Prevent form double submit
        $('#filterForm').on('submit', function(e) {
            // Reset ke halaman 1 saat submit form
            $('#pageInput').val(1);
            return true;
        });

        // Tooltip
        $('[title]').tooltip({
            placement: 'top',
            trigger: 'hover'
        });

        // Enter untuk search
        $('#search').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#filterForm').submit();
            }
        });

        // Enter pada page jump input
        $('#pageJumpInput').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                jumpToPage();
            }
        });

        // Tombol view items
        $('.view-items-btn').on('click', function() {
            var invoiceId = $(this).data('invoice-id');
            var invoiceNo = $(this).data('invoice-no');

            $('#modalContent').html('<div class="text-center py-3"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2 small">Memuat data...</p></div>');
            $('#itemsModal .modal-title').html('<i class="fas fa-list-ol"></i> Detail Items - ' + invoiceNo);

            $.ajax({
                url: 'get_invoice_items.php',
                type: 'GET',
                data: {
                    invoice_id: invoiceId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        let html = `
                        <div class="mb-2">
                            <h6 class="text-muted small mb-1">Customer: ${response.invoice.customer_name}</h6>
                            <h6 class="text-muted small mb-1">Invoice Date: ${response.invoice.invoice_date}</h6>
                            <h6 class="text-muted small mb-2">PO Number: ${response.invoice.po_number || '-'}</h6>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="thead-light">
                                    <tr>
                                        <th width="5%" class="small">No</th>
                                        <th width="55%" class="small">Description</th>
                                        <th width="10%" class="small">Qty</th>
                                        <th width="10%" class="small">Unit</th>
                                        <th width="10%" class="small">Unit Price</th>
                                        <th width="10%" class="small">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>`;

                        $.each(response.items, function(index, item) {
                            html += `
                            <tr>
                                <td class="text-center small">${index + 1}</td>
                                <td class="small">${item.description}</td>
                                <td class="text-center small">${item.qty_formatted}</td>
                                <td class="text-center small">${item.satuan}</td>
                                <td class="text-right small">${item.unit_price_formatted}</td>
                                <td class="text-right small">${item.amount_formatted}</td>
                            </tr>`;
                        });

                        html += `
                                </tbody>
                                <tfoot>
                                    <tr class="table-success">
                                        <td colspan="5" class="text-right small"><strong>Subtotal:</strong></td>
                                        <td class="text-right small"><strong>${response.invoice.subtotal_formatted}</strong></td>
                                    </tr>
                                    <tr class="table-info">
                                        <td colspan="5" class="text-right small"><strong>PPN:</strong></td>
                                        <td class="text-right small"><strong>${response.invoice.ppn_formatted}</strong></td>
                                    </tr>
                                    <tr class="table-info">
                                        <td colspan="5" class="text-right small"><strong>PPH:</strong></td>
                                        <td class="text-right small"><strong>${response.invoice.pph_formatted}</strong></td>
                                    </tr>
                                    <tr class="table-primary">
                                        <td colspan="5" class="text-right small"><strong>Total:</strong></td>
                                        <td class="text-right small"><strong>${response.invoice.total_formatted}</strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>`;

                        $('#modalContent').html(html);
                    } else {
                        $('#modalContent').html('<div class="alert alert-danger small">Gagal memuat data: ' + response.message + '</div>');
                    }
                },
                error: function() {
                    $('#modalContent').html('<div class="alert alert-danger small">Gagal memuat data items</div>');
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
    });

    // Fungsi untuk pergi ke halaman tertentu
    function goToPage(page) {
        if (page < 1 || page > <?php echo $total_pages; ?>) return;
        $('#pageInput').val(page);
        $('#filterForm').submit();
    }

    // Fungsi untuk jump ke halaman tertentu
    function jumpToPage() {
        const page = parseInt($('#pageJumpInput').val());
        if (page >= 1 && page <= <?php echo $total_pages; ?>) {
            goToPage(page);
        } else {
            alert('Halaman harus antara 1 dan <?php echo $total_pages; ?>');
            $('#pageJumpInput').val(<?php echo $page; ?>);
        }
    }

    // Fungsi reset filter
    function resetFilter() {
        window.location.href = 'invoices_list.php';
    }

    // Fungsi export Excel
    function exportToExcel() {
        // Ambil semua parameter filter
        const params = new URLSearchParams(window.location.search);
        params.set('export', 'excel');

        // Redirect ke URL export
        window.location.href = 'invoices_list.php?' + params.toString();
    }

    // Fungsi untuk navigasi cepat
    function quickNavigate(direction) {
        const currentPage = <?php echo $page; ?>;
        let newPage = currentPage;

        if (direction === 'first') newPage = 1;
        else if (direction === 'prev') newPage = Math.max(1, currentPage - 1);
        else if (direction === 'next') newPage = Math.min(<?php echo $total_pages; ?>, currentPage + 1);
        else if (direction === 'last') newPage = <?php echo $total_pages; ?>;

        if (newPage !== currentPage) {
            goToPage(newPage);
        }
    }
</script>

<style>
    .card {
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }

    .table th {
        font-weight: 600;
        background-color: #f8f9fa;
        vertical-align: middle;
        font-size: 0.8rem;
        padding: 10px 8px;
        white-space: nowrap;
        border-bottom: 2px solid #dee2e6;
        position: sticky;
        top: -1px;
        z-index: 10;
    }

    .table td {
        padding: 8px 6px;
        vertical-align: middle;
        white-space: nowrap;
        font-size: 0.85rem;
        border-top: 1px solid #f0f0f0;
    }

    .btn-group .btn-sm {
        border-radius: 4px;
        padding: 0.15rem 0.35rem;
        margin-right: 2px;
        font-size: 0.75rem;
        transition: all 0.2s;
    }

    .btn-group .btn-sm:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .btn-group .btn-sm:last-child {
        margin-right: 0;
    }

    .badge {
        font-size: 0.7rem;
        padding: 0.3rem 0.5rem;
        font-weight: 500;
        border-radius: 4px;
    }

    .pagination .page-item.active .page-link {
        background-color: #007bff;
        border-color: #007bff;
        color: white;
    }

    .pagination .page-link {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
        color: #007bff;
        border-radius: 4px;
        margin: 0 2px;
    }

    .pagination .page-link:hover {
        background-color: #e9ecef;
    }

    .table-hover tbody tr:hover {
        background-color: rgba(0, 123, 255, 0.08);
        transform: scale(1.001);
        transition: all 0.2s;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    /* Warna status lebih jelas */
    .badge-warning {
        background-color: #ffc107;
        color: #212529;
        border: none;
    }

    .badge-success {
        background-color: #28a745;
        color: white;
        border: none;
    }

    .badge-info {
        background-color: #17a2b8;
        color: white;
        border: none;
    }

    /* Highlight baris yang sedang aktif */
    .table-striped tbody tr:nth-of-type(odd) {
        background-color: rgba(0, 0, 0, 0.01);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .table-responsive {
            font-size: 0.75rem;
        }

        .btn-group .btn-sm {
            padding: 0.1rem 0.25rem;
            font-size: 0.7rem;
        }

        .badge {
            font-size: 0.65rem;
            padding: 0.15rem 0.3rem;
        }
        
        .pagination {
            flex-wrap: wrap;
            justify-content: center;
        }
    }

    /* Sticky header untuk tabel */
    .table-responsive {
        max-height: 600px;
        overflow-y: auto;
    }

    .sticky-top {
        background-color: #f8f9fa;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    /* Style untuk input page jump */
    #pageJumpInput {
        width: 60px;
        font-size: 0.8rem;
    }

    /* Animasi untuk tombol */
    .btn {
        transition: all 0.2s ease-in-out;
    }

    .btn:hover {
        transform: translateY(-1px);
    }

    /* Style untuk tombol aksi */
    .btn-outline-info:hover {
        background-color: #17a2b8;
        color: white;
    }

    .btn-outline-warning:hover {
        background-color: #ffc107;
        color: #212529;
    }

    .btn-outline-primary:hover {
        background-color: #007bff;
        color: white;
    }

    .btn-outline-danger:hover {
        background-color: #dc3545;
        color: white;
    }
</style>

<?php include 'footer.php'; ?>