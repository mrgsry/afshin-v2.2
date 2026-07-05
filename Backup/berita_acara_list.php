<?php
// =========================================================================
// BERITA ACARA - LIST (Daftar Berita Acara Serah Terima)
// =========================================================================

require_once 'functions.php';
require_login();

// Cek apakah tabel berita_acara ada
$check_table = mysqli_query($mysqli, "SHOW TABLES LIKE 'berita_acara'");
if (mysqli_num_rows($check_table) == 0) {
    // Tabel belum dibuat, redirect ke create untuk inisialisasi
    flash_set('warning', 'Tabel Berita Acara belum dibuat. Silakan buat Berita Acara pertama.');
    header('Location: berita_acara_create.php');
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
$params = [];

// Search
if (!empty($search)) {
    $where_conditions[] = "(b.nomor_ba LIKE '%{$search}%' OR 
                           b.customer_name LIKE '%{$search}%' OR 
                           b.pekerjaan LIKE '%{$search}%' OR
                           b.description LIKE '%{$search}%' OR
                           b.po_number LIKE '%{$search}%')";
}

// Filter customer
if ($filter_customer > 0) {
    $where_conditions[] = "b.customer_id = {$filter_customer}";
}

// Filter tanggal
if (!empty($filter_date_from)) {
    $where_conditions[] = "b.tanggal_ba >= '{$filter_date_from}'";
}
if (!empty($filter_date_to)) {
    $where_conditions[] = "b.tanggal_ba <= '{$filter_date_to}'";
}

// Gabungkan kondisi WHERE
$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_conditions);
}

// Query untuk mengambil data berita acara
$query = "
    SELECT 
        b.*,
        (SELECT COUNT(*) FROM berita_acara_items bi WHERE bi.berita_acara_id = b.id) as item_count
    FROM berita_acara b
    {$where_sql}
    ORDER BY b.tanggal_ba DESC, b.id DESC
    LIMIT {$limit} OFFSET {$offset}
";

$result = mysqli_query($mysqli, $query);

// Query untuk total data (untuk pagination)
$count_query = "SELECT COUNT(*) as total FROM berita_acara b {$where_sql}";
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

// Ambil flash messages
$error_msg = flash_get('error');
$success_msg = flash_get('success');

// Include header
include 'header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-0">📋 Daftar Berita Acara Serah Terima (BAST)</h3>
            <p class="text-muted mb-0">Total: <strong><?php echo number_format($total_data); ?></strong> dokumen</p>
        </div>
        <a href="berita_acara_create.php" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Buat Berita Acara Baru
        </a>
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
                    <label for="search">Cari (No. BA/Customer/Pekerjaan/PO)</label>
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
                        <a href="berita_acara_list.php" class="btn btn-secondary">
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
            <h5 class="mb-0"><i class="fas fa-list"></i> Data Berita Acara</h5>
            <span class="badge badge-info">Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?></span>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th width="5%" class="text-center">No</th>
                            <th width="15%">Nomor BA</th>
                            <th width="12%">Tanggal</th>
                            <th width="20%">Customer / Pihak I</th>
                            <th width="15%">PO Number</th>
                            <th width="25%">Description / Pekerjaan</th>
                            <th width="8%" class="text-center">Items</th>
                            <th width="15%" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result) > 0): ?>
                            <?php 
                            $no = $offset + 1;
                            while($row = mysqli_fetch_assoc($result)): 
                                $item_count = $row['item_count'] ?? 0;
                                // PERBAIKAN: Gunakan customer_name bukan customer_nama
                                $customer_name = isset($row['customer_name']) ? $row['customer_name'] : '';
                                $description = isset($row['description']) ? $row['description'] : (isset($row['pekerjaan']) ? $row['pekerjaan'] : '');
                            ?>
                            <tr>
                                <td class="text-center"><?php echo $no++; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['nomor_ba']); ?></strong>
                                </td>
                                <td>
                                    <?php echo !empty($row['tanggal_ba']) ? date('d/m/Y', strtotime($row['tanggal_ba'])) : '-'; ?>
                                </td>
                                <td>
                                    <div class="font-weight-bold"><?php echo htmlspecialchars($customer_name); ?></div>
                                    <?php if(!empty($row['lokasi'])): ?>
                                        <small class="text-muted">Lokasi: <?php echo htmlspecialchars($row['lokasi']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if(!empty($row['po_number'])): ?>
                                        <span class="badge badge-light"><?php echo htmlspecialchars($row['po_number']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if(!empty($description)) {
                                        if(strlen($description) > 50) {
                                            echo '<span title="' . htmlspecialchars($description) . '">' 
                                                 . substr(htmlspecialchars($description), 0, 50) . '...</span>';
                                        } else {
                                            echo htmlspecialchars($description);
                                        }
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                    ?>
                                    <?php if(!empty($row['item_code']) && $row['item_code'] != '-'): ?>
                                        <br><small class="text-info">Code: <?php echo htmlspecialchars($row['item_code']); ?></small>
                                    <?php endif; ?>
                                    <?php if(!empty($row['qty']) && $row['qty'] > 1): ?>
                                        <br><small class="text-success">Qty: <?php echo $row['qty']; ?> <?php echo !empty($row['um']) ? $row['um'] : 'Unit'; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if($item_count > 0): ?>
                                        <span class="badge badge-success" title="Total <?php echo $item_count + 1; ?> items"><?php echo $item_count + 1; ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary" title="1 item">1</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                       
                                        <a href="berita_acara_print.php?id=<?php echo $row['id']; ?>" 
                                           target="_blank" class="btn btn-warning" title="Cetak">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <a href="berita_acara_edit.php?id=<?php echo $row['id']; ?>" 
                                           class="btn btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger" 
                                                onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['nomor_ba'])); ?>')"
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
                                        <i class="fas fa-file-alt fa-3x mb-3"></i><br>
                                        Belum ada Berita Acara yang dibuat.<br>
                                        <a href="berita_acara_create.php" class="btn btn-primary mt-2">
                                            <i class="fas fa-plus-circle"></i> Buat Berita Acara Pertama
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

   

<script>
// Konfirmasi hapus
function confirmDelete(id, nomorBa) {
    if(confirm(`Apakah Anda yakin ingin menghapus Berita Acara:\n${nomorBa}\n\nTindakan ini tidak dapat dibatalkan!`)) {
        window.location.href = `berita_acara_delete.php?id=${id}`;
    }
}

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
});
</script>

<style>
.card {
    border-radius: 10px;
}
.table th {
    font-weight: 600;
    background-color: #f8f9fa;
}
.btn-group .btn {
    border-radius: 4px;
}
.badge {
    font-size: 0.8em;
    padding: 0.4em 0.6em;
}
.pagination .page-item.active .page-link {
    background-color: #007bff;
    border-color: #007bff;
}
.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.05);
}
</style>

<?php 
include 'footer.php'; 
?>