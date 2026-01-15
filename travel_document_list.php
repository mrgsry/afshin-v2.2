<?php
require_once 'functions.php';
require_login();

// Proses delete jika form modal dikirim
if(isset($_POST['delete_id'])){
    $id = intval($_POST['delete_id']);
    
    $mysqli->begin_transaction();
    try {
        $mysqli->query("DELETE FROM travel_document_items WHERE document_id=$id");
        $mysqli->query("DELETE FROM travel_documents WHERE id=$id");
        $mysqli->commit();
        flash_set('success', 'Travel Document (Surat Jalan) berhasil dihapus');
    } catch (mysqli_sql_exception $e) {
        $mysqli->rollback();
        flash_set('error', 'Error saat menghapus dokumen: ' . $e->getMessage());
    }
    
    header('Location: travel_document_list.php'); 
    exit;
}

// --- Konfigurasi Pagination ---
$limit = 20;
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
    $where_conditions[] = "(td.travel_no LIKE '%{$search}%' OR 
                           td.po_number LIKE '%{$search}%' OR
                           c.name LIKE '%{$search}%')";
}

// Filter customer
if ($filter_customer > 0) {
    $where_conditions[] = "td.customer_id = {$filter_customer}";
}

// Filter tanggal
if (!empty($filter_date_from)) {
    $where_conditions[] = "td.date_doc >= '{$filter_date_from}'";
}
if (!empty($filter_date_to)) {
    $where_conditions[] = "td.date_doc <= '{$filter_date_to}'";
}

// Gabungkan kondisi WHERE
$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_conditions);
}

// Query untuk mengambil data travel document dengan item count
$query = "
    SELECT 
        td.*,
        c.name AS customer_name,
        (SELECT COUNT(*) FROM travel_document_items tdi WHERE tdi.document_id = td.id) as item_count
    FROM travel_documents td 
    LEFT JOIN customers c ON td.customer_id = c.id 
    {$where_sql}
    ORDER BY td.date_doc DESC, td.id DESC
    LIMIT {$limit} OFFSET {$offset}
";

$res = mysqli_query($mysqli, $query);

// Query untuk total data (untuk pagination)
$count_query = "SELECT COUNT(*) as total FROM travel_documents td LEFT JOIN customers c ON td.customer_id = c.id {$where_sql}";
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
            td.*,
            c.name AS customer_name,
            (SELECT COUNT(*) FROM travel_document_items tdi WHERE tdi.document_id = td.id) as item_count
        FROM travel_documents td 
        LEFT JOIN customers c ON td.customer_id = c.id 
        {$where_sql}
        ORDER BY td.date_doc DESC, td.id DESC
    ";
    $export_result = mysqli_query($mysqli, $export_query);
    
    // Header untuk Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="travel_document_list_' . date('Ymd_His') . '.xls"');
    
    echo "<table border='1'>";
    echo "<tr>";
    echo "<th>No</th>";
    echo "<th>Travel Document No</th>";
    echo "<th>Tanggal</th>";
    echo "<th>Customer</th>";
    echo "<th>PO Number</th>";
    echo "<th>Jumlah Items</th>";
    echo "</tr>";
    
    $export_no = 1;
    while($row = mysqli_fetch_assoc($export_result)) {
        echo "<tr>";
        echo "<td>" . $export_no++ . "</td>";
        echo "<td>" . htmlspecialchars($row['travel_no']) . "</td>";
        echo "<td>" . (!empty($row['date_doc']) ? date('d/m/Y', strtotime($row['date_doc'])) : '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['po_number'] ?? '-') . "</td>";
        echo "<td>" . ($row['item_count']) . "</td>";
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
            <h3 class="mb-0">🚚 Daftar Travel Document (Surat Jalan)</h3>
            <p class="text-muted mb-0">Total: <strong><?php echo number_format($total_data); ?></strong> dokumen</p>
        </div>
        <div>
            <a href="travel_document_create.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Buat SJ Baru
            </a>
            <a href="?export=excel&search=<?php echo urlencode($search); ?>&customer=<?php echo $filter_customer; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>" 
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
                    <label for="search">Cari (No. SJ/Customer/PO)</label>
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
                        <a href="travel_document_list.php" class="btn btn-secondary">
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
            <h5 class="mb-0"><i class="fas fa-list"></i> Data Travel Document</h5>
            <span class="badge badge-info">Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?></span>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0" style="font-size: 0.9rem;">
                    <thead class="thead-light">
                        <tr>
                            <th width="3%" class="text-center">No</th>
                            <th width="12%">No. Surat Jalan</th>
                            <th width="10%">Tanggal</th>
                            <th width="15%">Customer</th>
                            <th width="10%">PO Number</th>
                            <th width="30%">Items Description</th>
                            <th width="5%" class="text-center">Items</th>
                            <th width="15%" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($res) > 0): ?>
                            <?php 
                            $no = $offset + 1;
                            while($row = mysqli_fetch_assoc($res)): 
                                $item_count = $row['item_count'] ?? 0;
                                
                                // Ambil semua items untuk dokumen ini
                                $items_query = "SELECT * FROM travel_document_items WHERE document_id = {$row['id']} ORDER BY item_no";
                                $items_result = mysqli_query($mysqli, $items_query);
                                $all_items = [];
                                while ($item = mysqli_fetch_assoc($items_result)) {
                                    $all_items[] = $item;
                                }
                                
                                // Ambil deskripsi item pertama untuk preview
                                $first_item_desc = !empty($all_items) ? htmlspecialchars($all_items[0]['item_desc'] ?? '') : '';
                                $short_description = strlen($first_item_desc) > 80 
                                    ? substr($first_item_desc, 0, 80) . '...' 
                                    : $first_item_desc;
                            ?>
                            <tr>
                                <td class="text-center align-middle"><?php echo $no++; ?></td>
                                <td class="align-middle">
                                    <strong><?php echo htmlspecialchars($row['travel_no']); ?></strong>
                                </td>
                                <td class="align-middle">
                                    <?php echo !empty($row['date_doc']) ? date('d/m/Y', strtotime($row['date_doc'])) : '-'; ?>
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
                                        <?php if(!empty($first_item_desc)): ?>
                                            <div style="margin-bottom: 5px;" title="<?php echo $first_item_desc; ?>">
                                                <?php echo $short_description; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-muted">-</div>
                                        <?php endif; ?>
                                        
                                        <?php if($item_count > 1): ?>
                                            <div>
                                                <button type="button" class="btn btn-xs btn-outline-info btn-sm view-items-btn" 
                                                        data-document-id="<?php echo $row['id']; ?>"
                                                        data-document-no="<?php echo htmlspecialchars($row['travel_no']); ?>"
                                                        title="Lihat semua items (<?php echo $item_count; ?> items)">
                                                    <i class="fas fa-plus-circle"></i> +<?php echo $item_count - 1; ?> item
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center align-middle">
                                    <span class="badge <?php echo $item_count > 0 ? 'badge-success' : 'badge-secondary'; ?>" 
                                          style="font-size: 0.8rem;"
                                          title="Total <?php echo $item_count; ?> items">
                                        <?php echo $item_count; ?>
                                    </span>
                                </td>
                                <td class="text-center align-middle">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="travel_document_edit.php?id=<?php echo $row['id']; ?>" 
                                           class="btn btn-info btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="travel_document_print.php?id=<?php echo $row['id']; ?>" 
                                           target="_blank" class="btn btn-warning btn-sm" title="Cetak">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger btn-sm" 
                                                data-toggle="modal" data-target="#deleteModal" 
                                                data-id="<?php echo $row['id']; ?>"
                                                data-document-no="<?php echo htmlspecialchars($row['travel_no']); ?>"
                                                title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-truck fa-3x mb-3"></i><br>
                                        Belum ada Travel Document yang dibuat.<br>
                                        <a href="travel_document_create.php" class="btn btn-primary mt-2">
                                            <i class="fas fa-plus-circle"></i> Buat Travel Document Pertama
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
                           href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&customer=<?php echo $filter_customer; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>">
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
                               href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&customer=<?php echo $filter_customer; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>">
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
                           href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&customer=<?php echo $filter_customer; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>">
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
                    <h5 class="card-title"><i class="fas fa-truck"></i> Total SJ</h5>
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
                        $month_query = "SELECT COUNT(*) as total FROM travel_documents WHERE DATE_FORMAT(date_doc, '%Y-%m') = '{$current_month}'";
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
                    <h5 class="card-title"><i class="fas fa-boxes"></i> Total Items</h5>
                    <h3 class="mb-0">
                        <?php 
                        $items_query = "SELECT COUNT(*) as total FROM travel_document_items";
                        $items_result = mysqli_query($mysqli, $items_query);
                        $items_row = mysqli_fetch_assoc($items_result);
                        echo number_format($items_row['total']);
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
                        $customer_query = "SELECT COUNT(DISTINCT customer_id) as total FROM travel_documents WHERE customer_id > 0";
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
                    <i class="fas fa-list-ol"></i> Detail Items - <span id="modalDocumentNo"></span>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6 class="text-muted">Customer: <span id="modalCustomer"></span></h6>
                    <h6 class="text-muted">Tanggal: <span id="modalDocumentDate"></span></h6>
                    <h6 class="text-muted">PO Number: <span id="modalPONumber"></span></h6>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th width="5%">No</th>
                                <th width="50%">Description</th>
                                <th width="15%">Qty</th>
                                <th width="15%">Unit</th>
                                <th width="15%">Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="modalItemsBody">
                            <!-- Items akan dimuat di sini -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-center text-muted">
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
                <a href="#" id="modalEditLink" class="btn btn-info">
                    <i class="fas fa-edit"></i> Edit
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
                <p>Apakah Anda yakin ingin menghapus Travel Document berikut?</p>
                <div class="alert alert-danger">
                    <strong id="documentNoText"></strong>
                </div>
                <p class="text-danger"><i class="fas fa-exclamation-circle"></i> Semua items terkait juga akan dihapus!</p>
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
// Set delete_id dan document_no ketika modal muncul
$('#deleteModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget); 
    var id = button.data('id'); 
    var documentNo = button.data('document-no');
    
    $('#delete_id').val(id);
    $('#documentNoText').text('No. Surat Jalan: ' + documentNo);
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
        var documentId = $(this).data('document-id');
        var documentNo = $(this).data('document-no');
        
        // Tampilkan loading
        $('#modalItemsBody').html('<tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin"></i> Memuat data...</td></tr>');
        
        // Set judul modal
        $('#modalDocumentNo').text(documentNo);
        
        // Load data via AJAX
        $.ajax({
            url: 'get_travel_document_items.php',
            type: 'GET',
            data: { document_id: documentId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Set data document
                    $('#modalCustomer').text(response.document.customer_name);
                    $('#modalDocumentDate').text(response.document.document_date);
                    $('#modalPONumber').text(response.document.po_number || '-');
                    $('#modalTotalItems').text(response.total_items);
                    
                    // Update links
                    $('#modalEditLink').attr('href', 'travel_document_edit.php?id=' + documentId);
                    $('#modalPrintLink').attr('href', 'travel_document_print.php?id=' + documentId);
                    
                    // Isi items
                    var itemsHtml = '';
                    $.each(response.items, function(index, item) {
                        itemsHtml += '<tr>' +
                            '<td class="text-center">' + (index + 1) + '</td>' +
                            '<td>' + item.item_desc + '</td>' +
                            '<td class="text-center">' + item.qty + '</td>' +
                            '<td class="text-center">' + (item.unit || '-') + '</td>' +
                            '<td>' + (item.remarks || '-') + '</td>' +
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
    max-width: 300px;
    word-wrap: break-word;
}
</style>

<?php include 'footer.php'; ?>