<?php
require_once 'functions.php';
require_login();

// Proses delete jika form modal dikirim
if(isset($_POST['delete_id'])){
    $id = intval($_POST['delete_id']);
    
    $mysqli->begin_transaction();
    try {
        $mysqli->query("SET FOREIGN_KEY_CHECKS = 0");
        
        // Hapus service report utama
        $stmt = $mysqli->prepare("DELETE FROM service_reports WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $mysqli->query("SET FOREIGN_KEY_CHECKS = 1");
                $mysqli->commit();
                flash_set('success', 'Service Report berhasil dihapus');
            } else {
                $mysqli->query("SET FOREIGN_KEY_CHECKS = 1");
                $mysqli->rollback();
                flash_set('error', 'Service Report tidak ditemukan');
            }
        } else {
            throw new Exception("Gagal menghapus Service Report: " . $stmt->error);
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $mysqli->query("SET FOREIGN_KEY_CHECKS = 1");
        $mysqli->rollback();
        flash_set('error', 'Error saat menghapus dokumen: ' . $e->getMessage());
    }
    
    header('Location: service_report_list.php'); 
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
$filter_service_type = isset($_GET['service_type']) ? $_GET['service_type'] : '';

// --- Query Data dengan Filter ---
$where_conditions = [];

// Search
if (!empty($search)) {
    $where_conditions[] = "(sr.doc_no LIKE '%{$search}%' OR 
                           sr.po_order_no LIKE '%{$search}%' OR
                           sr.type_of_service LIKE '%{$search}%' OR
                           sr.remark_general LIKE '%{$search}%' OR
                           c.name LIKE '%{$search}%')";
}

// Filter customer
if ($filter_customer > 0) {
    $where_conditions[] = "sr.customer_id = {$filter_customer}";
}

// Filter tanggal
if (!empty($filter_date_from)) {
    $where_conditions[] = "sr.date_doc >= '{$filter_date_from}'";
}
if (!empty($filter_date_to)) {
    $where_conditions[] = "sr.date_doc <= '{$filter_date_to}'";
}

// Filter service type
if (!empty($filter_service_type)) {
    $where_conditions[] = "sr.type_of_service = '{$filter_service_type}'";
}

// Gabungkan kondisi WHERE
$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_conditions);
}

// Query untuk mengambil data service report
$query = "
    SELECT 
        sr.*,
        c.name AS customer_name
    FROM service_reports sr 
    LEFT JOIN customers c ON sr.customer_id = c.id 
    {$where_sql}
    ORDER BY sr.date_doc DESC, sr.id DESC
    LIMIT {$limit} OFFSET {$offset}
";

$res = mysqli_query($mysqli, $query);

// Query untuk total data (untuk pagination)
$count_query = "SELECT COUNT(*) as total FROM service_reports sr LEFT JOIN customers c ON sr.customer_id = c.id {$where_sql}";
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

// Ambil daftar service type unik
$service_types_query = "SELECT DISTINCT type_of_service FROM service_reports WHERE type_of_service IS NOT NULL AND type_of_service != '' ORDER BY type_of_service";
$service_types_result = mysqli_query($mysqli, $service_types_query);
$service_types = [];
while ($type = mysqli_fetch_assoc($service_types_result)) {
    $service_types[] = $type['type_of_service'];
}

// --- Export to Excel ---
if(isset($_GET['export']) && $_GET['export'] == 'excel') {
    // Query semua data untuk export
    $export_query = "
        SELECT 
            sr.*,
            c.name AS customer_name
        FROM service_reports sr 
        LEFT JOIN customers c ON sr.customer_id = c.id 
        {$where_sql}
        ORDER BY sr.date_doc DESC, sr.id DESC
    ";
    $export_result = mysqli_query($mysqli, $export_query);
    
    // Header untuk Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="service_report_list_' . date('Ymd_His') . '.xls"');
    
    echo "<table border='1'>";
    echo "<tr>";
    echo "<th>No</th>";
    echo "<th>Service Report No</th>";
    echo "<th>Tanggal</th>";
    echo "<th>Customer</th>";
    echo "<th>PO/Order No</th>";
    echo "<th>Type of Service</th>";
    echo "<th>Remark</th>";
    echo "</tr>";
    
    $export_no = 1;
    while($row = mysqli_fetch_assoc($export_result)) {
        echo "<tr>";
        echo "<td>" . $export_no++ . "</td>";
        echo "<td>" . htmlspecialchars($row['doc_no']) . "</td>";
        echo "<td>" . (!empty($row['date_doc']) ? date('d/m/Y', strtotime($row['date_doc'])) : '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['po_order_no'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['type_of_service'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars(substr($row['remark_general'] ?? '', 0, 100)) . "</td>";
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
            <h3 class="mb-0">🔧 Daftar Service Report</h3>
            <p class="text-muted mb-0">Total: <strong><?php echo number_format($total_data); ?></strong> laporan</p>
        </div>
        <div>
            <a href="service_report_create.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Buat Laporan Baru
            </a>
            <a href="?export=excel&search=<?php echo urlencode($search); ?>&customer=<?php echo $filter_customer; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>&service_type=<?php echo urlencode($filter_service_type); ?>" 
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
                    <label for="search">Cari (No. SR/Customer/PO/Remark)</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Kata kunci...">
                </div>
                
                <div class="col-md-2">
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
                
                <div class="col-md-2">
                    <label for="service_type">Jenis Service</label>
                    <select class="form-control" id="service_type" name="service_type">
                        <option value="">-- Semua Jenis --</option>
                        <?php foreach($service_types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>" 
                            <?php echo ($filter_service_type == $type) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="date_from">Dari Tanggal</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" 
                           value="<?php echo htmlspecialchars($filter_date_from); ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="date_to">Sampai Tanggal</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" 
                           value="<?php echo htmlspecialchars($filter_date_to); ?>">
                </div>
                
                <div class="col-md-1 d-flex align-items-end">
                    <div class="d-flex w-100 justify-content-between">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                        <a href="service_report_list.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list"></i> Data Service Report</h5>
            <span class="badge badge-info">Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?></span>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0" style="font-size: 0.9rem;">
                    <thead class="thead-light">
                        <tr>
                            <th width="3%" class="text-center">No</th>
                            <th width="12%">No. SR</th>
                            <th width="10%">Tanggal</th>
                            <th width="15%">Customer</th>
                            <th width="12%">PO/Order No</th>
                            <th width="12%">Jenis Service</th>
                            <th width="25%">Remark</th>
                            <th width="11%" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($res) > 0): ?>
                            <?php 
                            $no = $offset + 1;
                            while($row = mysqli_fetch_assoc($res)): 
                                // Potong remark jika terlalu panjang
                                $remark = htmlspecialchars($row['remark_general'] ?? '');
                                $short_remark = strlen($remark) > 100 
                                    ? substr($remark, 0, 100) . '...' 
                                    : $remark;
                            ?>
                            <tr>
                                <td class="text-center align-middle"><?php echo $no++; ?></td>
                                <td class="align-middle">
                                    <strong><?php echo htmlspecialchars($row['doc_no']); ?></strong>
                                </td>
                                <td class="align-middle">
                                    <?php echo !empty($row['date_doc']) ? date('d/m/Y', strtotime($row['date_doc'])) : '-'; ?>
                                </td>
                                <td class="align-middle">
                                    <div style="font-size: 0.85rem; font-weight: 500;"><?php echo htmlspecialchars($row['customer_name']); ?></div>
                                </td>
                                <td class="align-middle">
                                    <?php if(!empty($row['po_order_no'])): ?>
                                        <span class="badge badge-secondary" style="font-size: 0.8rem;"><?php echo htmlspecialchars($row['po_order_no']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="align-middle">
                                    <?php if(!empty($row['type_of_service'])): ?>
                                        <span class="badge badge-info" style="font-size: 0.8rem;"><?php echo htmlspecialchars($row['type_of_service']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="align-middle">
                                    <div style="max-height: 60px; overflow: hidden;" title="<?php echo $remark; ?>">
                                        <?php echo $short_remark; ?>
                                    </div>
                                </td>
                                <td class="text-center align-middle">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="service_report_print.php?id=<?php echo $row['id']; ?>" 
                                           target="_blank" class="btn btn-info btn-sm" title="View/Print">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="service_report_edit.php?id=<?php echo $row['id']; ?>" 
                                           class="btn btn-warning btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger btn-sm" 
                                                data-toggle="modal" data-target="#deleteModal" 
                                                data-id="<?php echo $row['id']; ?>"
                                                data-doc-no="<?php echo htmlspecialchars($row['doc_no']); ?>"
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
                                        <i class="fas fa-clipboard-list fa-3x mb-3"></i><br>
                                        Belum ada Service Report yang dibuat.<br>
                                        <a href="service_report_create.php" class="btn btn-primary mt-2">
                                            <i class="fas fa-plus-circle"></i> Buat Service Report Pertama
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
                           href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&customer=<?php echo $filter_customer; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>&service_type=<?php echo urlencode($filter_service_type); ?>">
                            <i class="fas fa-chevron-left"></i> Prev
                        </a>
                    </li>
                    
                    <!-- Page Numbers -->
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&customer=' . $filter_customer . '&date_from=' . $filter_date_from . '&date_to=' . $filter_date_to . '&service_type=' . urlencode($filter_service_type) . '">1</a></li>';
                        if($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    
                    for($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <a class="page-link" 
                               href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&customer=<?php echo $filter_customer; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>&service_type=<?php echo urlencode($filter_service_type); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php
                    if($end_page < $total_pages) {
                        if($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&customer=' . $filter_customer . '&date_from=' . $filter_date_from . '&date_to=' . $filter_date_to . '&service_type=' . urlencode($filter_service_type) . '">' . $total_pages . '</a></li>';
                    }
                    ?>
                    
                    <!-- Next Page -->
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" 
                           href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&customer=<?php echo $filter_customer; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>&service_type=<?php echo urlencode($filter_service_type); ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
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
                    <h5 class="card-title"><i class="fas fa-clipboard-list"></i> Total SR</h5>
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
                        $month_query = "SELECT COUNT(*) as total FROM service_reports WHERE DATE_FORMAT(date_doc, '%Y-%m') = '{$current_month}'";
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
                    <h5 class="card-title"><i class="fas fa-tools"></i> Jenis Service</h5>
                    <h3 class="mb-0">
                        <?php 
                        $types_query = "SELECT COUNT(DISTINCT type_of_service) as total FROM service_reports WHERE type_of_service IS NOT NULL AND type_of_service != ''";
                        $types_result = mysqli_query($mysqli, $types_query);
                        $types_row = mysqli_fetch_assoc($types_result);
                        echo number_format($types_row['total']);
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
                        $customer_query = "SELECT COUNT(DISTINCT customer_id) as total FROM service_reports WHERE customer_id > 0";
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
                <p>Apakah Anda yakin ingin menghapus Service Report berikut?</p>
                <div class="alert alert-danger">
                    <strong id="docNoText"></strong>
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
// Set delete_id dan doc_no ketika modal muncul
$('#deleteModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget); 
    var id = button.data('id'); 
    var docNo = button.data('doc-no');
    
    $('#delete_id').val(id);
    $('#docNoText').text('Service Report No: ' + docNo);
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
    $('#customer, #service_type, #date_from, #date_to').on('change', function() {
        $('form').submit();
    });
    
    // Tooltip untuk semua tombol
    $('[title]').tooltip({
        placement: 'top',
        trigger: 'hover'
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
.badge-info {
    background-color: #17a2b8;
}
/* Fix untuk kolom remark */
.table td:nth-child(7) {
    white-space: normal;
    max-width: 300px;
    word-wrap: break-word;
}
</style>

<?php include 'footer.php'; ?>