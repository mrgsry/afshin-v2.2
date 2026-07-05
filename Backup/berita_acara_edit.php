<?php
// =========================================================================
// BERITA ACARA - EDIT
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
$query = "SELECT * FROM berita_acara WHERE id = {$id}";
$result = mysqli_query($mysqli, $query);
if (!$result || mysqli_num_rows($result) == 0) {
    flash_set('error', 'Berita Acara tidak ditemukan!');
    header('Location: berita_acara_list.php');
    exit;
}

$ba = mysqli_fetch_assoc($result);

// Query items dari berita_acara_items
$items_query = "SELECT * FROM berita_acara_items WHERE berita_acara_id = {$id} ORDER BY item_no";
$items_result = mysqli_query($mysqli, $items_query);

// Ambil data PO Number dari invoices untuk dropdown
$po_numbers_query = mysqli_query($mysqli, "
    SELECT 
        i.id,
        i.po_number,
        i.customer_id,
        c.name as customer_name,
        i.created_at
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    WHERE i.po_number IS NOT NULL AND i.po_number != ''
    ORDER BY i.created_at DESC
");

$po_list = [];
$po_customer_data = [];
if ($po_numbers_query) {
    while ($row = mysqli_fetch_assoc($po_numbers_query)) {
        $po_list[$row['po_number']] = $row['id'];
        $po_customer_data[$row['po_number']] = [
            'invoice_id' => $row['id'],
            'customer_name' => $row['customer_name']
        ];
    }
}

$satuans = ['Unit', 'Pcs', 'Set', 'Lot', 'Buah'];

// --- Logika POST (Update Data) ---

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update'){
    
    // Ambil dan bersihkan data
    $nomor_ba          = isset($_POST['nomor_ba']) ? trim($_POST['nomor_ba']) : '';
    $tanggal_ba        = isset($_POST['tanggal_ba']) ? trim($_POST['tanggal_ba']) : '';
    $customer_name     = isset($_POST['customer_name']) ? trim($_POST['customer_name']) : '';
    $customer_alamat   = isset($_POST['customer_alamat']) ? trim($_POST['customer_alamat']) : '';
    $lokasi            = isset($_POST['lokasi']) ? trim($_POST['lokasi']) : '';
    $pekerjaan         = isset($_POST['pekerjaan']) ? trim($_POST['pekerjaan']) : '';
    $po_number         = isset($_POST['po_number']) ? trim($_POST['po_number']) : '';
    $invoice_id        = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
    $pelaksana         = isset($_POST['pelaksana']) ? trim($_POST['pelaksana']) : 'CV. Afshin Raya Teknik';
    
    // Data dari tabel items (ambil dari baris pertama)
    $description = '';
    $item_code = '-';
    $qty = 1;
    $um = 'Unit';
    $keterangan = 'OK';
    
    // Ambil data dari items JSON
    $items_json = isset($_POST['items_json']) ? $_POST['items_json'] : '[]';
    $items = json_decode($items_json, true);
    
    if (!empty($items) && is_array($items) && count($items) > 0) {
        // Ambil data dari baris pertama items
        $first_item = $items[0];
        $description = isset($first_item['description']) ? trim($first_item['description']) : '';
        $item_code = isset($first_item['item_code']) ? trim($first_item['item_code']) : '-';
        $qty = isset($first_item['qty']) ? (int)$first_item['qty'] : 1;
        $um = isset($first_item['unit']) ? trim($first_item['unit']) : 'Unit';
        $keterangan = isset($first_item['keterangan']) ? trim($first_item['keterangan']) : 'OK';
    }
    
    // Validasi lebih longgar - hanya field yang benar-benar wajib
    if (empty($nomor_ba)) {
        flash_set('error', 'Nomor BA harus diisi!');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $id);
        exit;
    }
    
    // Jika description kosong, gunakan pekerjaan sebagai default
    if (empty($description)) {
        $description = $pekerjaan;
    }
    
    // Default values jika kosong
    if (empty($lokasi)) {
        $lokasi = $customer_name;
    }
    
    if (empty($pelaksana)) {
        $pelaksana = 'CV. Afshin Raya Teknik';
    }
    
    if (empty($um)) {
        $um = 'Unit';
    }
    
    if (empty($keterangan)) {
        $keterangan = 'OK';
    }
    
    $mysqli->begin_transaction();
    $success = false;
    $error_message = '';
    
    try {
        // DEBUG: Log data yang akan diupdate
        error_log("Updating BA ID: $id");
        error_log("Nomor BA: $nomor_ba");
        error_log("Pekerjaan: $pekerjaan");
        error_log("Description: $description");
        
        // 1. Update data berita_acara
        $stmt_header = $mysqli->prepare("
            UPDATE berita_acara SET
                nomor_ba = ?,
                tanggal_ba = ?,
                pekerjaan = ?,
                description = ?,
                item_code = ?,
                qty = ?,
                um = ?,
                keterangan = ?,
                po_number = ?,
                invoice_id = ?,
                customer_name = ?,
                customer_alamat = ?,
                lokasi = ?,
                pelaksana = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        if (!$stmt_header) {
            throw new Exception("Prepare statement gagal: " . $mysqli->error);
        }
        
        // Debug binding
        $bind_result = $stmt_header->bind_param('sssssissssssssi', 
            $nomor_ba,      // s - nomor_ba
            $tanggal_ba,    // s - tanggal_ba
            $pekerjaan,     // s - pekerjaan
            $description,   // s - description
            $item_code,     // s - item_code
            $qty,           // i - qty
            $um,            // s - um
            $keterangan,    // s - keterangan
            $po_number,     // s - po_number
            $invoice_id,    // i - invoice_id
            $customer_name, // s - customer_name
            $customer_alamat, // s - customer_alamat
            $lokasi,        // s - lokasi
            $pelaksana,     // s - pelaksana
            $id             // i - id
        );
        
        if (!$bind_result) {
            throw new Exception("Bind parameter gagal: " . $stmt_header->error);
        }
        
        $execute_result = $stmt_header->execute();
        
        if (!$execute_result) {
            if ($mysqli->errno == 1062) {
                 throw new Exception("Nomor Berita Acara <strong>{$nomor_ba}</strong> sudah ada.");
            }
            throw new Exception("Gagal update berita acara: " . $stmt_header->error);
        }
        
        $affected_rows = $stmt_header->affected_rows;
        error_log("Affected rows: $affected_rows");
        
        // 2. Hapus items lama dan simpan yang baru (jika tabel ada)
        $check_items_table = mysqli_query($mysqli, "SHOW TABLES LIKE 'berita_acara_items'");
        if (mysqli_num_rows($check_items_table) > 0) {
            // Hapus items lama
            $delete_items = mysqli_query($mysqli, "DELETE FROM berita_acara_items WHERE berita_acara_id = {$id}");
            if (!$delete_items) {
                throw new Exception("Gagal menghapus items lama: " . mysqli_error($mysqli));
            }
            
            // Simpan items baru (mulai dari item ke-2)
            if (count($items) > 1) {
                $stmt_item = $mysqli->prepare("
                    INSERT INTO berita_acara_items 
                    (berita_acara_id, item_no, description, item_code, qty, unit, keterangan) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt_item) {
                    for ($i = 1; $i < count($items); $i++) {
                        $item = $items[$i];
                        if (!empty($item['description'])) {
                            $item_no = $i + 1; // Item no dimulai dari 2
                            $item_description = isset($item['description']) ? $item['description'] : '';
                            $item_code_val = isset($item['item_code']) ? $item['item_code'] : '-';
                            $qty_item = isset($item['qty']) ? (int)$item['qty'] : 1;
                            $unit = isset($item['unit']) ? $item['unit'] : 'Unit';
                            $keterangan_item = isset($item['keterangan']) ? $item['keterangan'] : 'OK';

                            $stmt_item->bind_param('iississ', 
                                $id, $item_no, $item_description, $item_code_val, $qty_item, $unit, $keterangan_item
                            );
                            
                            if (!$stmt_item->execute()) {
                                throw new Exception("Gagal menyimpan item ke-{$item_no}: " . $stmt_item->error);
                            }
                        }
                    }
                }
            }
        }
        
        $mysqli->commit();
        $success = true;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        $error_message = $e->getMessage();
        error_log("Error updating BA: " . $error_message);
    }

    if ($success) {
        flash_set('success', "Berita Acara <strong>{$nomor_ba}</strong> berhasil diupdate!");
        // Redirect ke halaman edit lagi untuk melihat perubahan
        header('Location: berita_acara_edit.php?id=' . $id); 
        exit;
    } else {
        $error_modal = true;
        $error_modal_message = $error_message;
        // Tidak redirect, tetap di halaman ini untuk menampilkan modal error
    }
}

// --------------------------------------------------
// --- HTML Output ---
// --------------------------------------------------
$error_msg = flash_get('error');
$success_msg = flash_get('success'); 
include 'header.php'; 

// Siapkan data items untuk JavaScript
$all_items = [];
// Tambahkan item utama dari header
$all_items[] = [
    'description' => $ba['description'] ?: $ba['pekerjaan'],
    'item_code' => $ba['item_code'] ?: '-',
    'qty' => $ba['qty'] ?: 1,
    'unit' => $ba['um'] ?: 'Unit',
    'keterangan' => $ba['keterangan'] ?: 'OK'
];

// Tambahkan items tambahan dari berita_acara_items
if ($items_result && mysqli_num_rows($items_result) > 0) {
    while ($item = mysqli_fetch_assoc($items_result)) {
        $all_items[] = [
            'description' => $item['description'],
            'item_code' => $item['item_code'],
            'qty' => $item['qty'],
            'unit' => $item['unit'],
            'keterangan' => $item['keterangan']
        ];
    }
}

$items_json_initial = json_encode($all_items);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">✏️ Edit Berita Acara</h3>
        <a href="berita_acara_list.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Kembali ke Detail
        </a>
    </div>

    <!-- Modal untuk Error -->
    <?php if(isset($error_modal) && $error_modal): ?>
    <div class="modal fade" id="errorModal" tabindex="-1" role="dialog" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="errorModalLabel">
                        <i class="fas fa-exclamation-triangle"></i> Error
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger mb-0">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_modal_message; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal untuk Success -->
    <?php if($success_msg): ?>
    <div class="modal fade" id="successModal" tabindex="-1" role="dialog" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">
                        <i class="fas fa-check-circle"></i> Sukses
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success mb-0">
                        <i class="fas fa-check"></i> <?php echo $success_msg; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal untuk Error dari Flash -->
    <?php if($error_msg): ?>
    <div class="modal fade" id="flashErrorModal" tabindex="-1" role="dialog" aria-labelledby="flashErrorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="flashErrorModalLabel">
                        <i class="fas fa-exclamation-triangle"></i> Error
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger mb-0">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal Konfirmasi Update -->
    <div class="modal fade" id="confirmUpdateModal" tabindex="-1" role="dialog" aria-labelledby="confirmUpdateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="confirmUpdateModalLabel">
                        <i class="fas fa-question-circle"></i> Konfirmasi Update
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin mengupdate Berita Acara ini?</p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Pastikan semua data sudah benar sebelum melakukan update.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success" id="confirmUpdateBtn">
                        <i class="fas fa-save"></i> Ya, Update Sekarang
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Validasi Error -->
    <div class="modal fade" id="validationErrorModal" tabindex="-1" role="dialog" aria-labelledby="validationErrorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="validationErrorModalLabel">
                        <i class="fas fa-exclamation-triangle"></i> Validasi Error
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="validationErrorContent">
                    <!-- Content akan diisi oleh JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <form id="beritaAcaraForm" method="POST" action="">
        <input type="hidden" name="items_json" id="items_json_input" value='<?php echo htmlspecialchars($items_json_initial); ?>'>
        <input type="hidden" name="invoice_id" id="invoice_id_input" value="<?php echo $ba['invoice_id']; ?>">
        <input type="hidden" name="action" value="update">

        <!-- HEADER CARD -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-file-contract"></i> Informasi Header</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Nomor Berita Acara <span class="text-danger">*</span></label>
                            <input type="text" name="nomor_ba" class="form-control font-weight-bold" 
                                   value="<?php echo htmlspecialchars($ba['nomor_ba']); ?>" required readonly>
                        </div>
                        <div class="form-group">
                            <label>Tanggal BA <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_ba" class="form-control" 
                                   value="<?php echo htmlspecialchars($ba['tanggal_ba']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>PO Number</label>
                            <div class="input-group">
                                <input type="text" name="po_number" id="po_number_input" 
                                       class="form-control" placeholder="Masukkan Nomor PO"
                                       value="<?php echo htmlspecialchars($ba['po_number']); ?>">
                                <div class="input-group-append">
                                    <select id="po_number_select" class="form-control">
                                        <option value="">-- Pilih PO yang sudah ada --</option>
                                        <?php foreach(array_keys($po_list) as $po): ?>
                                        <option value="<?php echo htmlspecialchars($po); ?>">
                                            <?php echo htmlspecialchars($po); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Pihak I / Customer <span class="text-danger">*</span></label>
                                <input type="text" name="customer_name" id="customer_name_input" 
                                       class="form-control" required placeholder="Nama Customer"
                                       value="<?php echo htmlspecialchars($ba['customer_name']); ?>">
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Lokasi <span class="text-danger">*</span></label>
                                    <input type="text" name="lokasi" id="lokasi_input" 
                                           class="form-control" required placeholder="Lokasi Pekerjaan"
                                           value="<?php echo htmlspecialchars($ba['lokasi']); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Alamat Customer</label>
                            <textarea name="customer_alamat" id="customer_alamat_input" 
                                      class="form-control" rows="2" placeholder="Alamat Customer"><?php echo htmlspecialchars($ba['customer_alamat']); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Pekerjaan / Uraian <span class="text-danger">*</span></label>
                    <input type="text" name="pekerjaan" class="form-control" 
                           placeholder="Contoh: PR#02581 Mtc [C8888] Repair Modif Gear Turret CNC Takamaz" required
                           value="<?php echo htmlspecialchars($ba['pekerjaan']); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Pelaksana (Pihak II)</label>
                        <input type="text" name="pelaksana" class="form-control" 
                               value="<?php echo !empty($ba['pelaksana']) ? htmlspecialchars($ba['pelaksana']) : 'CV. Afshin Raya Teknik'; ?>" required>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ITEMS CARD -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-list-ol"></i> Daftar Item / Barang</h5>
                <small class="text-white-50">Data baris pertama akan disimpan ke kolom utama tabel berita_acara</small>
            </div>
            <div class="card-body p-2">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="itemsTable">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th>Description <span class="text-danger">*</span></th>
                                <th style="width: 10%;">Item Code</th>
                                <th style="width: 10%;">Qty <span class="text-danger">*</span></th>
                                <th style="width: 12%;">Unit <span class="text-danger">*</span></th>
                                <th style="width: 10%;">Keterangan</th>
                                <th style="width: 5%;">Act</th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody">
                            <!-- Items akan diisi oleh JavaScript -->
                        </tbody>
                    </table>
                </div>
                <button id="addItemRow" class="btn btn-sm btn-outline-success" type="button">
                    <i class="fas fa-plus"></i> Tambah Item (Opsional)
                </button>
                <div class="text-muted small mt-2">
                    <i class="fas fa-info-circle"></i> Baris pertama wajib diisi dan akan disimpan ke kolom utama. Baris berikutnya opsional.
                </div>
            </div>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="mt-4 pt-3 border-top d-flex justify-content-end">
            <a href="berita_acara_list.php?id=<?php echo $id; ?>" class="btn btn-secondary btn-lg mx-2">
                <i class="fas fa-times"></i> Batal
            </a>
            <button type="button" onclick="showConfirmModal()" class="btn btn-success btn-lg">
                <i class="fas fa-save"></i> Update Berita Acara
            </button>
        </div>
    </form>
</div> 

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// --- Data dari PHP untuk autofill ---
const PO_CUSTOMER_DATA = <?php echo json_encode($po_customer_data); ?>;
const PO_LIST = <?php echo json_encode($po_list); ?>;
const SATUANS = <?php echo json_encode($satuans); ?>;
const INITIAL_ITEMS = <?php echo $items_json_initial; ?>;

// --- FUNGSI UTAMA ---

$(document).ready(function() {
    // 1. Isi tabel items dengan data awal
    populateItemsTable();
    
    // 2. Handler untuk PO Select
    $("#po_number_select").change(function() {
        const selectedPO = $(this).val();
        $("#po_number_input").val(selectedPO);
        
        if (selectedPO && PO_CUSTOMER_DATA[selectedPO]) {
            const data = PO_CUSTOMER_DATA[selectedPO];
            
            // Isi data customer
            $("#customer_name_input").val(data.customer_name || '');
            $("#lokasi_input").val(data.customer_name || '');
            $("#invoice_id_input").val(data.invoice_id || '');
            
            // Kosongkan alamat
            $("#customer_alamat_input").val('');
        }
        $(this).val('');
    });
    
    // 3. Auto-fill lokasi sama dengan customer
    $("#customer_name_input").on('input', function() {
        if ($("#lokasi_input").val() === '') {
            $("#lokasi_input").val($(this).val());
        }
    });
    
    // 4. Auto-fill pekerjaan dari item description baris pertama
    $('#itemsTable').on('input', '.item-desc:first', function() {
        const pekerjaanInput = $('input[name="pekerjaan"]');
        if (pekerjaanInput.val() === '') {
            pekerjaanInput.val($(this).val());
        }
    });
    
    // 5. Tampilkan modal error/success jika ada
    <?php if(isset($error_modal) && $error_modal): ?>
    $('#errorModal').modal('show');
    <?php endif; ?>
    
    <?php if($success_msg): ?>
    $('#successModal').modal('show');
    <?php endif; ?>
    
    <?php if($error_msg): ?>
    $('#flashErrorModal').modal('show');
    <?php endif; ?>
    
    // 6. Handler untuk tombol confirm update
    $('#confirmUpdateBtn').click(function() {
        $('#confirmUpdateModal').modal('hide');
        submitForm();
    });
});

function populateItemsTable() {
    const $tbody = $('#itemsTableBody');
    $tbody.empty();
    
    INITIAL_ITEMS.forEach((item, index) => {
        const tr = `
            <tr>
                <td class="item_no">${index + 1}</td>
                <td>
                    <textarea 
                        name="item_desc[]" 
                        class="form-control form-control-sm item-desc"
                        rows="2"
                        placeholder="Uraian item"
                        ${index === 0 ? 'required' : ''}
                    >${escapeHtml(item.description || '')}</textarea>
                </td>
                <td>
                    <input type="text" name="item_code[]" class="form-control form-control-sm item-code" 
                           value="${escapeHtml(item.item_code || '-')}" placeholder="Kode Item">
                </td>
                <td>
                    <input type="number" name="item_qty[]" class="form-control form-control-sm item-qty" 
                           value="${item.qty || 1}" min="1" ${index === 0 ? 'required' : ''}>
                </td>
                <td>
                    <select name="item_unit[]" class="form-control form-control-sm item-unit" ${index === 0 ? 'required' : ''}>
                        <option value="">-- pilih --</option>
                        ${generateUnitOptions(item.unit || 'Unit')}
                    </select>
                </td>
                <td>
                    <input type="text" name="item_keterangan[]" class="form-control form-control-sm item-ket" 
                           value="${escapeHtml(item.keterangan || 'OK')}" placeholder="Keterangan">
                </td>
                <td>
                    ${index === 0 ? '' : '<button class="btn btn-danger btn-sm removeRow" type="button" title="Hapus"><i class="fas fa-times"></i></button>'}
                </td>
            </tr>
        `;
        $tbody.append(tr);
    });
    
    reindexTable('itemsTable');
}

function generateUnitOptions(selectedUnit) {
    let options = '';
    SATUANS.forEach(satuan => {
        const selected = (satuan === selectedUnit) ? 'selected' : '';
        options += `<option value="${escapeHtml(satuan)}" ${selected}>${escapeHtml(satuan)}</option>`;
    });
    return options;
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// --- FUNGSI TABEL ITEM ---

function reindexTable(tableId) {
    $(`#${tableId} tbody tr`).each(function(i){
        $(this).find(".item_no").text(i + 1);
    });
}

// Tambah baris item
$("#addItemRow").click(function(){
    let tr = `
        <tr>
            <td class="item_no"></td>
            <td>
                <input type="text" name="item_desc[]" class="form-control form-control-sm item-desc" 
                       placeholder="Uraian item tambahan">
            </td>
            <td>
                <input type="text" name="item_code[]" class="form-control form-control-sm item-code" 
                       value="-" placeholder="Kode Item">
            </td>
            <td>
                <input type="number" name="item_qty[]" class="form-control form-control-sm item-qty" 
                       value="1" min="1">
            </td>
            <td>
                <select name="item_unit[]" class="form-control form-control-sm item-unit">
                    <option value="">-- pilih --</option>
                    ${SATUANS.map(s => `<option value="${escapeHtml(s)}">${escapeHtml(s)}</option>`).join('')}
                </select>
            </td>
            <td>
                <input type="text" name="item_keterangan[]" class="form-control form-control-sm item-ket" 
                       value="OK" placeholder="Keterangan">
            </td>
            <td>
                <button class="btn btn-danger btn-sm removeRow" type="button" title="Hapus">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        </tr>
    `;
    $("#itemsTableBody").append(tr);
    reindexTable('itemsTable');
});

// Hapus baris item (tidak boleh menghapus baris pertama)
$(document).on("click", ".removeRow", function(){
    const rowCount = $("#itemsTableBody tr").length;
    if (rowCount > 1) {
        $(this).closest("tr").remove();
        reindexTable('itemsTable');
    } else {
        showValidationError('Minimal harus ada 1 baris item!');
    }
});

// --- FUNGSI VALIDASI & MODAL ---

function collectItemsData() {
    let itemsData = [];
    $("#itemsTableBody tr").each(function() {
        const description = $(this).find('.item-desc').val();
        const itemCode = $(this).find('.item-code').val();
        const qty = $(this).find('.item-qty').val();
        const unit = $(this).find('.item-unit').val();
        const keterangan = $(this).find('.item-ket').val();

        // Hanya ambil baris yang memiliki description
        if ($.trim(description) !== '') {
            itemsData.push({
                description: description,
                item_code: itemCode || '-',
                qty: parseInt(qty) || 1,
                unit: unit || 'Unit',
                keterangan: keterangan || 'OK'
            });
        }
    });
    return itemsData;
}

function validateForm() {
    const errors = [];
    
    // Validasi header
    const nomorBA = $('input[name="nomor_ba"]').val();
    const tanggalBA = $('input[name="tanggal_ba"]').val();
    const customerName = $('input[name="customer_name"]').val();
    const lokasi = $('input[name="lokasi"]').val();
    const pekerjaan = $('input[name="pekerjaan"]').val();

    if (!nomorBA) errors.push("Nomor BA harus diisi");
    if (!tanggalBA) errors.push("Tanggal BA harus diisi");
    if (!customerName) errors.push("Customer harus diisi");
    if (!lokasi) errors.push("Lokasi harus diisi");
    if (!pekerjaan) errors.push("Pekerjaan harus diisi");

    // Kumpulkan data item
    const itemsData = collectItemsData();
    
    // Validasi item pertama
    if (itemsData.length === 0) {
        errors.push("Item pertama (Description, Qty, Unit) harus diisi");
    } else {
        const firstItem = itemsData[0];
        if (!firstItem.description || !firstItem.qty || !firstItem.unit) {
            errors.push("Item pertama: Description, Qty, dan Unit harus diisi");
        }
    }
    
    return {
        isValid: errors.length === 0,
        errors: errors,
        itemsData: itemsData
    };
}

function showValidationError(message) {
    let content = '';
    if (Array.isArray(message)) {
        content = '<ul class="mb-0">';
        message.forEach(error => {
            content += `<li><i class="fas fa-exclamation-circle text-danger mr-2"></i>${error}</li>`;
        });
        content += '</ul>';
    } else {
        content = `<p><i class="fas fa-exclamation-circle text-danger mr-2"></i>${message}</p>`;
    }
    
    $('#validationErrorContent').html(content);
    $('#validationErrorModal').modal('show');
}

function showConfirmModal() {
    const validation = validateForm();
    if (!validation.isValid) {
        showValidationError(validation.errors);
        return;
    }
    
    // Set data ke hidden input
    $("#items_json_input").val(JSON.stringify(validation.itemsData));
    
    // Tampilkan modal konfirmasi
    $('#confirmUpdateModal').modal('show');
}

function submitForm() {
    // Pastikan data terbaru sudah disimpan ke hidden input
    const validation = validateForm();
    $("#items_json_input").val(JSON.stringify(validation.itemsData));
    
    // Tampilkan loading
    const originalBtn = $('#confirmUpdateBtn');
    const originalText = originalBtn.html();
    
    originalBtn.prop('disabled', true);
    originalBtn.html(`
        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
        Menyimpan...
    `);
    
    // Submit form
    setTimeout(() => {
        document.getElementById('beritaAcaraForm').submit();
    }, 500);
}
</script>

<style>
.card-header {
    font-weight: 600;
}
.table th {
    white-space: nowrap;
}
.form-control-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
.btn-lg {
    padding: 0.5rem 1.5rem;
    font-size: 1.1rem;
}
.input-group-append select {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    border-left: 0;
}
.modal-header .close {
    color: white;
    opacity: 0.8;
}
.modal-header .close:hover {
    opacity: 1;
}
</style>

<?php 
include 'footer.php'; 
?>