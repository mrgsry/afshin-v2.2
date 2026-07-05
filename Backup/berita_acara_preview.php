<?php
// =========================================================================
// BERITA ACARA - VIEW DETAIL
// =========================================================================

require_once 'functions.php';
require_login();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    flash_set('error', 'ID Berita Acara tidak valid!');
    header('Location: berita_acara_list.php');
    exit;
}

$id = (int)$_GET['id'];

// Query data berita acara
$query = "
    SELECT b.*, c.name as customer_name_original, c.customer_no 
    FROM berita_acara b
    LEFT JOIN customers c ON b.customer_id = c.id
    WHERE b.id = {$id}
";

$result = mysqli_query($mysqli, $query);
if (!$result || mysqli_num_rows($result) == 0) {
    flash_set('error', 'Berita Acara tidak ditemukan!');
    header('Location: berita_acara_list.php');
    exit;
}

$ba = mysqli_fetch_assoc($result);

// Query items
$items_query = "
    SELECT * FROM berita_acara_items 
    WHERE berita_acara_id = {$id} 
    ORDER BY item_no
";
$items_result = mysqli_query($mysqli, $items_query);

// Jika tidak ada items di tabel items, gunakan data dari header
$has_items = mysqli_num_rows($items_result) > 0;

include 'header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-0">📄 Detail Berita Acara</h3>
            <p class="text-muted mb-0">No: <strong><?php echo htmlspecialchars($ba['nomor_ba']); ?></strong></p>
        </div>
        <div class="btn-group">
            <a href="berita_acara_print.php?id=<?php echo $id; ?>" target="_blank" class="btn btn-warning">
                <i class="fas fa-print"></i> Cetak
            </a>
            <a href="berita_acara_edit.php?id=<?php echo $id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="berita_acara_list.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="row">
        <div class="col-md-8">
            <!-- Info Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi Berita Acara</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <th width="40%">Nomor BA</th>
                                    <td>: <strong><?php echo htmlspecialchars($ba['nomor_ba']); ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Tanggal BA</th>
                                    <td>: <?php echo date('d F Y', strtotime($ba['tanggal_ba'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Customer</th>
                                    <td>: <?php echo htmlspecialchars($ba['customer_nama']); ?></td>
                                </tr>
                                <tr>
                                    <th>Lokasi</th>
                                    <td>: <?php echo htmlspecialchars($ba['lokasi']); ?></td>
                                </tr>
                                <tr>
                                    <th>Pelaksana</th>
                                    <td>: <?php echo htmlspecialchars($ba['pelaksana']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <th width="40%">PO Number</th>
                                    <td>: <?php echo !empty($ba['po_number']) ? htmlspecialchars($ba['po_number']) : '-'; ?></td>
                                </tr>
                                <tr>
                                    <th>Product Code</th>
                                    <td>: <?php echo !empty($ba['prod_code']) ? htmlspecialchars($ba['prod_code']) : '-'; ?></td>
                                </tr>
                                <tr>
                                    <th>Ship By</th>
                                    <td>: <?php echo !empty($ba['ship_by']) ? htmlspecialchars($ba['ship_by']) : '-'; ?></td>
                                </tr>
                                <tr>
                                    <th>Keterangan</th>
                                    <td>: <?php echo htmlspecialchars($ba['keterangan']); ?></td>
                                </tr>
                                <tr>
                                    <th>Dibuat</th>
                                    <td>: <?php echo date('d/m/Y H:i', strtotime($ba['created_at'])); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Pekerjaan / Uraian:</h6>
                        <div class="alert alert-light">
                            <?php echo nl2br(htmlspecialchars($ba['pekerjaan'])); ?>
                        </div>
                    </div>
                    
                    <?php if(!empty($ba['customer_alamat'])): ?>
                    <div class="mt-3">
                        <h6>Alamat Customer:</h6>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($ba['customer_alamat'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Quick Actions -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-bolt"></i> Aksi Cepat</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="berita_acara_print.php?id=<?php echo $id; ?>&format=pdf" 
                           target="_blank" class="btn btn-outline-danger">
                            <i class="fas fa-file-pdf"></i> Download PDF
                        </a>
                        <button onclick="copyToClipboard('<?php echo $ba['nomor_ba']; ?>')" 
                                class="btn btn-outline-secondary">
                            <i class="fas fa-copy"></i> Salin No. BA
                        </button>
                        <a href="mailto:?subject=Berita Acara <?php echo urlencode($ba['nomor_ba']); ?>&body=Lihat detail: <?php echo urlencode($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                           class="btn btn-outline-primary">
                            <i class="fas fa-share-alt"></i> Bagikan
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Customer Info -->
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-user-tie"></i> Info Customer</h5>
                </div>
                <div class="card-body">
                    <h6><?php echo htmlspecialchars($ba['customer_nama']); ?></h6>
                    <?php if(!empty($ba['customer_no'])): ?>
                        <p class="mb-1"><small>Kode: <?php echo htmlspecialchars($ba['customer_no']); ?></small></p>
                    <?php endif; ?>
                    <?php if(!empty($ba['customer_name_original']) && $ba['customer_name_original'] != $ba['customer_nama']): ?>
                        <p class="mb-1"><small>Nama asli: <?php echo htmlspecialchars($ba['customer_name_original']); ?></small></p>
                    <?php endif; ?>
                    <hr>
                    <p class="mb-0">
                        <i class="fas fa-history"></i> 
                        Total BA: 
                        <?php 
                        $total_ba_query = "SELECT COUNT(*) as total FROM berita_acara WHERE customer_id = " . (int)$ba['customer_id'];
                        $total_ba_result = mysqli_query($mysqli, $total_ba_query);
                        $total_ba = mysqli_fetch_assoc($total_ba_result);
                        echo '<strong>' . $total_ba['total'] . '</strong>';
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="fas fa-boxes"></i> Daftar Item / Barang</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th width="5%">No</th>
                            <th>Description</th>
                            <th width="10%">Item Code</th>
                            <th width="10%">Qty</th>
                            <th width="12%">Unit</th>
                            <th width="15%">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($has_items): ?>
                            <?php while($item = mysqli_fetch_assoc($items_result)): ?>
                            <tr>
                                <td><?php echo $item['item_no']; ?></td>
                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                                <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                <td><?php echo number_format($item['qty']); ?></td>
                                <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                <td><?php echo htmlspecialchars($item['keterangan']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td>1</td>
                                <td><?php echo htmlspecialchars($ba['pekerjaan']); ?></td>
                                <td>-</td>
                                <td>1</td>
                                <td>Unit</td>
                                <td><?php echo htmlspecialchars($ba['keterangan']); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if($has_items): ?>
                    <tfoot>
                        <tr class="table-info">
                            <td colspan="3" class="text-right"><strong>Total:</strong></td>
                            <td>
                                <?php 
                                mysqli_data_seek($items_result, 0);
                                $total_qty = 0;
                                while($item = mysqli_fetch_assoc($items_result)) {
                                    $total_qty += $item['qty'];
                                }
                                echo '<strong>' . number_format($total_qty) . '</strong>';
                                ?>
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Audit Trail -->
    <?php 
    // Cek jika ada update
    if (!empty($ba['updated_at']) && $ba['updated_at'] != $ba['created_at']): 
    ?>
    <div class="alert alert-light mt-4">
        <small>
            <i class="fas fa-history"></i> Terakhir diupdate: 
            <?php echo date('d/m/Y H:i', strtotime($ba['updated_at'])); ?>
        </small>
    </div>
    <?php endif; ?>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('Nomor BA berhasil disalin: ' + text);
    }, function(err) {
        console.error('Gagal menyalin: ', err);
    });
}

// Print langsung
function directPrint() {
    window.open('berita_acara_print.php?id=<?php echo $id; ?>&direct=true', '_blank');
}
</script>

<style>
.table-borderless th {
    font-weight: 600;
    color: #495057;
}
.alert-light {
    background-color: #f8f9fa;
    border-left: 4px solid #007bff;
}
.card-header h5 {
    font-size: 1.1rem;
}
</style>

<?php 
include 'footer.php'; 
?>