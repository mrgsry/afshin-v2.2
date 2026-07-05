<?php
require_once 'functions.php';
require_login();

// Proses delete jika form modal dikirim
if(isset($_POST['delete_id'])){
    $id = intval($_POST['delete_id']);
    mysqli_query($mysqli, "DELETE FROM quotations WHERE id=$id");
    flash_set('success', 'Quotation berhasil dihapus');
    header('Location: quotations_list.php'); 
    exit;
}

// --- Konfigurasi Pagination ---
$limit = 20; // Jumlah data per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// --- Filter dan Search ---
$search = isset($_GET['search']) ? mysqli_real_escape_string($mysqli, $_GET['search']) : '';
$filter_customer = isset($_GET['customer']) ? (int)$_GET['customer'] : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// --- Query Data dengan Filter ---
$where_conditions = [];

// Search
if (!empty($search)) {
    $where_conditions[] = "(q.quotation_no LIKE '%{$search}%' OR 
                           q.faktur_quot LIKE '%{$search}%' OR 
                           q.po_number LIKE '%{$search}%' OR
                           c.name LIKE '%{$search}%')";
}

// Filter customer
if ($filter_customer > 0) {
    $where_conditions[] = "q.customer_id = {$filter_customer}";
}

// Filter tanggal
if (!empty($filter_date_from)) {
    $where_conditions[] = "q.date_quot >= '{$filter_date_from}'";
}
if (!empty($filter_date_to)) {
    $where_conditions[] = "q.date_quot <= '{$filter_date_to}'";
}

// Gabungkan kondisi WHERE
$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_conditions);
}

// Query untuk mengambil data quotation dengan item count dan description pertama
$query = "
    SELECT 
        q.*,
        c.name AS customer_name,
        (SELECT COUNT(*) FROM quotation_items qi WHERE qi.quotation_id = q.id) as item_count,
        (SELECT description_quot FROM quotation_items WHERE quotation_id = q.id ORDER BY item_no LIMIT 1) as first_description
    FROM quotations q 
    LEFT JOIN customers c ON q.customer_id = c.id 
    {$where_sql}
    ORDER BY q.date_quot DESC, q.id DESC
    LIMIT {$limit} OFFSET {$offset}
";

$res = mysqli_query($mysqli, $query);

// Query untuk total data (untuk pagination)
$count_query = "SELECT COUNT(*) as total FROM quotations q LEFT JOIN customers c ON q.customer_id = c.id {$where_sql}";
$count_result = mysqli_query($mysqli, $count_query);
$total_row = mysqli_fetch_assoc($count_result);
$total_data = $total_row['total'];
$total_pages = ceil($total_data / $limit);

// Ambil daftar customer untuk filter dropdown
$customers_query = "SELECT id, name FROM customers ORDER BY name ASC";
$customers_result = mysqli_query($mysqli, $customers_query);
$customers = [];
while ($customer = mysqli_fetch_assoc($customers_result)) {
    $customers[] = $customer;
}

// --- Export to Excel ---
if(isset($_GET['export']) && $_GET['export'] == 'excel') {
    // Query semua data untuk export
    $export_query = "
        SELECT 
            q.*,
            c.name AS customer_name,
            (SELECT COUNT(*) FROM quotation_items qi WHERE qi.quotation_id = q.id) as item_count
        FROM quotations q 
        LEFT JOIN customers c ON q.customer_id = c.id 
        {$where_sql}
        ORDER BY q.date_quot DESC, q.id DESC
    ";
    $export_result = mysqli_query($mysqli, $export_query);
    
    // Header untuk Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="quotation_list_' . date('Ymd_His') . '.xls"');
    
    echo "<table border='1'>";
    echo "<tr>";
    echo "<th>No</th>";
    echo "<th>Quotation No</th>";
    echo "<th>Tanggal</th>";
    echo "<th>Customer</th>";
    echo "<th>PO Number</th>";
    echo "<th>Description (Item Pertama)</th>";
    echo "<th>Subtotal</th>";
    echo "<th>PPN</th>";
    echo "<th>Total</th>";
    echo "<th>Jumlah Items</th>";
    echo "</tr>";
    
    $export_no = 1;
    while($row = mysqli_fetch_assoc($export_result)) {
        $subtotal = floatval($row['subtotal']);
        $ppn = $row['ppn'] ?? 0;
        $total = floatval($row['total']);
        
        // Ambil description pertama
        $desc_query = "SELECT description_quot FROM quotation_items WHERE quotation_id = {$row['id']} ORDER BY item_no LIMIT 1";
        $desc_result = mysqli_query($mysqli, $desc_query);
        $desc_row = mysqli_fetch_assoc($desc_result);
        $first_description = $desc_row['description_quot'] ?? '-';
        
        echo "<tr>";
        echo "<td>" . $export_no++ . "</td>";
        echo "<td>" . htmlspecialchars($row['quotation_no']) . "</td>";
        echo "<td>" . (!empty($row['date_quot']) ? date('d/m/Y', strtotime($row['date_quot'])) : '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['po_number'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($first_description) . "</td>";
        echo "<td>" . number_format($subtotal, 2) . "</td>";
        echo "<td>" . number_format($ppn, 2) . "</td>";
        echo "<td>" . number_format($total, 2) . "</td>";
        echo "<td>" . ($row['item_count'] + 1) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    exit;
}

// Ambil flash messages
$error_msg = flash_get('error');
$success_msg = flash_get('success');

include 'header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-0">📄 Daftar Quotation</h3>
            <p class="text-muted mb-0">Total: <strong><?php echo number_format($total_data); ?></strong> quotation</p>
        </div>
        <div>
            <a href="quotations_create.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Buat Quotation Baru
            </a>
            <a href="?export=excel&search=<?php echo urlencode($search); ?>&customer=<?php echo $filter_customer; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo ($filter_date_to); ?>" 
               class="btn btn-success">
                <i class="fas fa-file-excel"></i> Export to Excel
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($error_msg); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($success_msg); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Filter & Search Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-filter"></i> Filter & Pencarian</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="search">Cari (No. Quotation/Customer/PO)</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Kata kunci...">
                </div>
                
                <div class="col-md-3">
                    <label for="customer">Filter Customer</label>
                    <select class="form-control" id="customer" name="customer">
                        <option value="0">-- Semua Customer --</option>
                        <?php foreach($customers as $customer): ?>
                        <option value="<?php echo $customer['id']; ?>" 
                            <?php echo ($filter_customer == $customer['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($customer['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="date_from">Dari Tanggal</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" 
                           value="<?php echo htmlspecialchars($filter_date_from); ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="date_to">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" 
                           value="<?php echo htmlspecialchars($filter_date_to); ?>">
                </div>
                
                <div class="col-md-12">
                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Terapkan Filter
                        </button>
                        <a href="quotations_list.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset Filter
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list"></i> Data Quotation</h5>
            <span class="badge badge-info">Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?></span>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0" style="font-size: 0.9rem;">
                    <thead class="thead-light">
                        <tr>
                            <th width="3%" class="text-center">No</th>
                            <th width="9%">Quotation No</th>
                            <th width="7%">Tanggal</th>
                            <th width="15%">Customer</th>
                            <th width="8%">PO Number</th>
                            <th width="20%">Description</th>
                            <th width="8%" class="text-right">Subtotal</th>
                            <th width="8%" class="text-right">PPN</th>
                            <th width="8%" class="text-right">Total</th>
                            <th width="4%" class="text-center">Items</th>
                            <th width="12%" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($res) > 0): ?>
                            <?php 
                            $no = $offset + 1;
                            while($row = mysqli_fetch_assoc($res)): 
                                $item_count = $row['item_count'] ?? 0;
                                $total_items = $item_count + 1; // +1 untuk item utama
                                
                                $subtotal = floatval($row['subtotal']);
                                $ppn = floatval($row['ppn'] ?? 0);
                                $total = floatval($row['total']);
                                
                                // Ambil description dari database
                                $first_description = htmlspecialchars($row['first_description'] ?? '');
                                
                                // Potong description jika terlalu panjang
                                $short_description = strlen($first_description) > 60 
                                    ? substr($first_description, 0, 60) . '...' 
                                    : $first_description;
                            ?>
                            <tr>
                                <td class="text-center align-middle"><?php echo $no++; ?></td>
                                <td class="align-middle">
                                    <strong><?php echo htmlspecialchars($row['quotation_no']); ?></strong>
                                </td>
                                <td class="align-middle">
                                    <?php echo !empty($row['date_quot']) ? date('d/m/Y', strtotime($row['date_quot'])) : '-'; ?>
                                </td>
                                <td class="align-middle">
                                    <div style="font-size: 0.85rem; font-weight: 500;"><?php echo htmlspecialchars($row['customer_name']); ?></div>
                                </td>
                                <td class="align-middle">
                                    <?php if(!empty($row['po_number'])): ?>
                                        <span class="badge badge-secondary" style="font-size: 0.8rem;"><?php echo htmlspecialchars($row['po_number']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="align-middle">
                                    <div style="max-height: 60px; overflow: hidden;">
                                        <?php if(!empty($first_description)): ?>
                                            <div style="margin-bottom: 5px;" title="<?php echo $first_description; ?>">
                                                <?php echo $short_description; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-muted">-</div>
                                        <?php endif; ?>
                                        
                                        <?php if($item_count > 0): ?>
                                            <div>
                                                <button
                                                    data-quotation-id="<?php echo $row['id']; ?>"
                                                    data-quotation-no="<?php echo htmlspecialchars($row['quotation_no']); ?>"
                                                    class="view-items-btn">
                                                    <i class="fas fa-plus-circle"></i> +<?php echo $item_count; ?> item
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-right align-middle">
                                    <div style="font-family: monospace; font-size: 0.85rem;">
                                        <?php echo number_format($subtotal, 2); ?>
                                    </div>
                                </td>
                                <td class="text-right align-middle">
                                    <div style="font-family: monospace; font-size: 0.85rem; color: #dc3545;">
                                        <?php echo number_format($ppn, 2); ?>
                                    </div>
                                </td>
                                <td class="text-right align-middle">
                                    <div style="font-family: monospace; font-size: 0.9rem; font-weight: bold;">
                                        <?php echo number_format($total, 2); ?>
                                    </div>
                                </td>
                                <td class="text-center align-middle">
                                    <span class="badge <?php echo $total_items > 1 ? 'badge-success' : 'badge-secondary'; ?>" 
                                          style="font-size: 0.8rem;"
                                          title="Total <?php echo $total_items; ?> items">
                                        <?php echo $total_items; ?>
                                    </span>
                                </td>
                                <td class="text-center align-middle">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="quotations_view.php?id=<?php echo $row['id']; ?>" 
                                           class="btn btn-info btn-sm" title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="quotations_edit.php?id=<?php echo $row['id']; ?>" 
                                           class="btn btn-warning btn-sm" title="Edit Quotation">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="quotations_print.php?id=<?php echo $row['id']; ?>" 
                                           target="_blank" class="btn btn-primary btn-sm" title="Cetak">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger btn-sm" 
                                                data-toggle="modal" data-target="#deleteModal" 
                                                data-id="<?php echo $row['id']; ?>"
                                                data-quotation-no="<?php echo htmlspecialchars($row['quotation_no']); ?>"
                                                title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-file-contract fa-3x mb-3"></i><br>
                                        Belum ada Quotation yang dibuat.<br>
                                        <a href="quotations_create.php" class="btn btn-primary mt-2">
                                            <i class="fas fa-plus-circle"></i> Buat Quotation Pertama
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="card-footer">
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mb-0">
                    <!-- Previous Page -->
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" 
                           href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&customer=<?php echo $filter_customer; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo ($filter_date_to); ?>">
                            <i class="fas fa-chevron-left"></i> Prev
                        </a>
                    </li>
                    
                    <!-- Page Numbers -->
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&customer=' . $filter_customer . '&date_from=' . $filter_date_from . '&date_to=' . $filter_date_to . '">1</a></li>';
                        if($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    
                    for($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <a class="page-link" 
                               href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&customer=<?php echo $filter_customer; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo ($filter_date_to); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php
                    if($end_page < $total_pages) {
                        if($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&customer=' . $filter_customer . '&date_from=' . $filter_date_from . '&date_to=' . $filter_date_to . '">' . $total_pages . '</a></li>';
                    }
                    ?>
                    
                    <!-- Next Page -->
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" 
                           href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&customer=<?php echo $filter_customer; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo ($filter_date_to); ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="text-center mt-2 text-muted small">
                Menampilkan <?php echo ($total_data > 0) ? ($offset + 1) : 0; ?> - 
                <?php echo min($offset + $limit, $total_data); ?> dari <?php echo number_format($total_data); ?> data
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Stats -->
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h5 class="card-title"><i class="fas fa-file-contract"></i> Total Quotation</h5>
                    <h3 class="mb-0"><?php echo number_format($total_data); ?></h3>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h5 class="card-title"><i class="fas fa-calendar"></i> Bulan Ini</h5>
                    <h3 class="mb-0">
                        <?php 
                        $current_month = date('Y-m');
                        $month_query = "SELECT COUNT(*) as total FROM quotations WHERE DATE_FORMAT(date_quot, '%Y-%m') = '{$current_month}'";
                        $month_result = mysqli_query($mysqli, $month_query);
                        $month_row = mysqli_fetch_assoc($month_result);
                        echo number_format($month_row['total']);
                        ?>
                    </h3>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h5 class="card-title"><i class="fas fa-money-bill-wave"></i> Total Nilai</h5>
                    <h3 class="mb-0">
                        <?php 
                        $total_value_query = "SELECT SUM(total) as total FROM quotations";
                        $total_value_result = mysqli_query($mysqli, $total_value_query);
                        $total_value_row = mysqli_fetch_assoc($total_value_result);
                        echo 'Rp ' . number_format($total_value_row['total'] ?? 0, 0, ',', '.');
                        ?>
                    </h3>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h5 class="card-title"><i class="fas fa-users"></i> Customer</h5>
                    <h3 class="mb-0">
                        <?php 
                        $customer_query = "SELECT COUNT(DISTINCT customer_id) as total FROM quotations WHERE customer_id > 0";
                        $customer_result = mysqli_query($mysqli, $customer_query);
                        $customer_row = mysqli_fetch_assoc($customer_result);
                        echo number_format($customer_row['total']);
                        ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal untuk menampilkan detail items -->
<div class="modal fade" id="itemsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-list-ol"></i> Detail Items - <span id="modalQuotationNo"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6 class="text-muted">Customer: <span id="modalCustomer"></span></h6>
                    <h6 class="text-muted">Quotation Date: <span id="modalQuotationDate"></span></h6>
                    <h6 class="text-muted">PO Number: <span id="modalPONumber"></span></h6>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th width="5%">No</th>
                                <th width="55%">Description</th>
                                <th width="10%">Qty</th>
                                <th width="10%">Unit</th>
                                <th width="10%">Unit Price</th>
                                <th width="10%">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="modalItemsBody">
                            <!-- Items akan dimuat di sini -->
                        </tbody>
                        <tfoot>
                            <tr class="table-success">
                                <td colspan="5" class="text-right"><strong>Subtotal:</strong></td>
                                <td class="text-right"><strong id="modalSubtotal"></strong></td>
                            </tr>
                            <tr class="table-info">
                                <td colspan="5" class="text-right"><strong>PPN:</strong></td>
                                <td class="text-right"><strong id="modalPPN"></strong></td>
                            </tr>
                            <tr class="table-primary">
                                <td colspan="5" class="text-right"><strong>Total:</strong></td>
                                <td class="text-right"><strong id="modalTotal"></strong></td>
                            </tr>
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    <small>Total Items: <span id="modalTotalItems"></span> items</small>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times"></i> Tutup
                </button>
                <a href="#" id="modalViewLink" class="btn btn-info">
                    <i class="fas fa-eye"></i> Detail Lengkap
                </a>
                <a href="#" id="modalEditLink" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Edit Quotation
                </a>
                <a href="#" id="modalPrintLink" target="_blank" class="btn btn-primary">
                    <i class="fas fa-print"></i> Cetak
                </a>
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
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i> Konfirmasi Hapus
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                  <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus quotation berikut?</p>
                <div class="alert alert-danger">
                    <strong id="quotationNoText"></strong>
                </div>
                <p class="text-danger"><i class="fas fa-exclamation-circle"></i> Tindakan ini tidak dapat dibatalkan!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Ya, Hapus
                </button>
            </div>
        </div>
    </form>
  </div>
</div>

<script>
// Set delete_id dan quotation_no ketika modal muncul
$('#deleteModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget); 
    var id = button.data('id'); 
    var quotationNo = button.data('quotation-no');
    
    $('#delete_id').val(id);
    $('#quotationNoText').text('Quotation No: ' + quotationNo);
});

// Auto-submit filter jika perubahan
$(document).ready(function() {
    // Auto-apply tanggal hari ini untuk filter jika kosong
    if($('#date_from').val() === '' && $('#date_to').val() === '') {
        const today = new Date().toISOString().split('T')[0];
        $('#date_to').val(today);
        
        // Set awal bulan
        const firstDay = new Date();
        firstDay.setDate(1);
        $('#date_from').val(firstDay.toISOString().split('T')[0]);
    }
    
    // Fitur search dengan delay
    let searchTimer;
    $('#search').on('keyup', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
            $('form').submit();
        }, 500);
    });
    
    // Fitur filter otomatis untuk dropdown
    $('#customer, #date_from, #date_to').on('change', function() {
        $('form').submit();
    });
    
    // Tooltip untuk semua tombol
    $('[title]').tooltip({
        placement: 'top',
        trigger: 'hover'
    });
    
    // Tombol view items
    $('.view-items-btn').on('click', function() {
        var quotationId = $(this).data('quotation-id');
        var quotationNo = $(this).data('quotation-no');
        
        // Tampilkan loading
        $('#modalItemsBody').html('<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin"></i> Memuat data...</td></tr>');
        
        // Set judul modal
        $('#modalQuotationNo').text(quotationNo);
        
        // Load data via AJAX
        $.ajax({
            url: 'get_quotation_items.php',
            type: 'GET',
            data: { quotation_id: quotationId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Set data quotation
                    $('#modalCustomer').text(response.quotation.customer_name);
                    $('#modalQuotationDate').text(response.quotation.quotation_date);
                    $('#modalSubtotal').text(response.quotation.subtotal_formatted);
                    $('#modalPPN').text(response.quotation.ppn_formatted);
                    $('#modalTotal').text(response.quotation.total_formatted);
                    $('#modalTotalItems').text(response.total_items);
                    
                    // Update links
                    $('#modalViewLink').attr('href', 'quotations_view.php?id=' + quotationId);
                    $('#modalEditLink').attr('href', 'quotations_edit.php?id=' + quotationId);
                    $('#modalPrintLink').attr('href', 'quotations_print.php?id=' + quotationId);
                    
                    // Isi items
                    var itemsHtml = '';
                    $.each(response.items, function(index, item) {
                        itemsHtml += '<tr>' +
                            '<td class="text-center">' + (index + 1) + '</td>' +
                            '<td>' + item.description + '</td>' +
                            '<td class="text-center">' + item.qty_formatted + '</td>' +
                            '<td class="text-center">' + (item.satuan || '-') + '</td>' +
                            '<td class="text-right">' + item.unit_price_formatted + '</td>' +
                            '<td class="text-right">' + item.amount_formatted + '</td>' +
                            '</tr>';
                    });
                    $('#modalItemsBody').html(itemsHtml);
                    
                    // Tampilkan modal
                    $('#itemsModal').modal('show');
                } else {
                    alert('Gagal memuat data items: ' + response.message);
                }
            },
            error: function() {
                alert('Gagal memuat data items');
            }
        });
    });
});
</script>

<style>
.card {
    border-radius: 10px;
}
.table th {
    font-weight: 600;
    background-color: #f8f9fa;
    vertical-align: middle;
    font-size: 0.85rem;
    padding: 12px 8px;
    white-space: nowrap;
}
.table td {
    padding: 10px 8px;
    vertical-align: middle;
    white-space: nowrap;
}
.btn-group .btn {
    border-radius: 4px;
    padding: 4px 8px;
    margin-right: 2px;
}
.btn-group .btn:last-child {
    margin-right: 0;
}
.badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    font-weight: 500;
}
.pagination .page-item.active .page-link {
    background-color: #007bff;
    border-color: #007bff;
}
.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}
.align-middle {
    vertical-align: middle !important;
}
.text-right {
    text-align: right;
}
.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
}
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.badge-light {
    background-color: #f8f9fa;
    color: #495057;
    border: 1px solid #dee2e6;
}
.btn-outline-info {
    font-size: 0.75rem;
    padding: 0.2rem 0.4rem;
    border-radius: 3px;
}
/* Fix untuk kolom description */
.table td:nth-child(6) {
    white-space: normal;
    max-width: 250px;
    word-wrap: break-word;
}
/* Warna untuk tombol aksi */
.btn-info {
    background-color: #17a2b8;
    border-color: #17a2b8;
}
.btn-info:hover {
    background-color: #138496;
    border-color: #117a8b;
}
.btn-warning {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #212529;
}
.btn-warning:hover {
    background-color: #e0a800;
    border-color: #d39e00;
    color: #212529;
}
.btn-primary {
    background-color: #007bff;
    border-color: #007bff;
}
.btn-primary:hover {
    background-color: #0069d9;
    border-color: #0062cc;
}
.btn-danger {
    background-color: #dc3545;
    border-color: #dc3545;
}
.btn-danger:hover {
    background-color: #c82333;
    border-color: #bd2130;
}
/* Responsive untuk tombol aksi */
@media (max-width: 768px) {
    .btn-group {
        display: flex;
        flex-direction: column;
    }
    .btn-group .btn {
        margin-bottom: 2px;
        width: 100%;
        text-align: center;
    }
    .btn-group .btn:last-child {
        margin-bottom: 0;
    }
}
</style>

<?php include 'footer.php'; ?>