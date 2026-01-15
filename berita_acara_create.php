<?php
// =========================================================================
// PHP LOGIC: BERITA ACARA SERAH TERIMA - CREATE (FINAL & CLEAN)
// =========================================================================

// --- Bagian A: Initial Setup & Dependencies ---
require_once 'functions.php'; 
require_login(); 

if (!$mysqli) {
    die("Koneksi database gagal!");
}

// --- Bagian B: Data Fetching & Utility Functions ---

// 1. Ambil data Customer untuk Pihak I
$customers_query = "SELECT id, name, customer_no FROM customers ORDER BY name ASC"; 
$customers_result = mysqli_query($mysqli, $customers_query);

// 2. Ambil data PO Number dari invoices
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

// 3. Ambil item dari invoice_items berdasarkan invoice_id
$po_items_data = [];

if (!empty($po_list)) {
    $invoice_ids = implode(',', array_map('intval', array_values($po_list)));

    $items_q = mysqli_query($mysqli, "
        SELECT 
            ii.invoice_id,
            ii.description,
            ii.item_code,
            ii.qty,
            ii.satuan
        FROM invoice_items ii
        WHERE ii.invoice_id IN ($invoice_ids)
        ORDER BY ii.id ASC
    ");

    while ($row = mysqli_fetch_assoc($items_q)) {
        $po_number = array_search($row['invoice_id'], $po_list);
        if ($po_number) {
            $po_items_data[$po_number][] = [
                'description' => $row['description'],
                'item_code'   => $row['item_code'] ?? '-',
                'qty'         => (int)$row['qty'],
                'unit'        => $row['satuan'] ?? 'Unit',
                'keterangan'  => 'OK'
            ];
        }
    }
}

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

// 3. Fungsi Roman Month
if (!function_exists('bulan_romawi')) {
    function bulan_romawi($month) {
        $romawi = [1=>'I', 2=>'II', 3=>'III', 4=>'IV', 5=>'V', 6=>'VI', 7=>'VII', 8=>'VIII', 9=>'IX', 10=>'X', 11=>'XI', 12=>'XII'];
        return $romawi[$month] ?? '';
    }
}

// 4. Fungsi Generate Nomor BA
function generate_ba_no($mysqli) {
    $current_year = date('Y');
    $current_month_romawi = bulan_romawi(date('n'));
    
    // Ambil nomor BA terakhir pada tahun ini
    $q_last = mysqli_query($mysqli, "
        SELECT nomor_ba 
        FROM berita_acara 
        WHERE nomor_ba LIKE '%/{$current_year}'
        ORDER BY CAST(SUBSTRING_INDEX(nomor_ba, '/', 1) AS UNSIGNED) DESC, id DESC
        LIMIT 1
    ");
    
    $last_number = 0;
    if ($q_last && $row = mysqli_fetch_assoc($q_last)) {
        $parts = explode('/', $row['nomor_ba']);
        $last_number = (int)$parts[0];
    }
    
    $next_number = $last_number + 1;
    $formatted_number = str_pad($next_number, 3, '0', STR_PAD_LEFT);
    
    return "{$formatted_number}/BAST-ART/{$current_month_romawi}/{$current_year}";
}

$next_ba_no = generate_ba_no($mysqli);
$satuans = ['Unit', 'Pcs', 'Set', 'Lot', 'Buah'];

// --- Bagian C: Logika POST (Penyimpanan ke Database) ---

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save'){
    
    // Ambil dan bersihkan data Header sesuai struktur tabel
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
    
    if (empty($nomor_ba) || empty($tanggal_ba) || empty($customer_name) || empty($pekerjaan)) {
        flash_set('error', 'Semua data header (Nomor BA, Tanggal, Customer, Pekerjaan) harus diisi.');
        header('Location: ' . $_SERVER['PHP_SELF']);
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
    $ba_id = 0;
    $success = false;
    
    try {
        // 1. Simpan ke berita_acara (Header) - data dari items masuk ke kolom utama
        $stmt_header = $mysqli->prepare("
            INSERT INTO berita_acara 
            (nomor_ba, tanggal_ba, pekerjaan, description, item_code, qty, um, keterangan,
             po_number, invoice_id, customer_name, customer_alamat, lokasi, pelaksana) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt_header) {
            throw new Exception("Prepare statement gagal: " . $mysqli->error);
        }
        
        $stmt_header->bind_param('sssssissssssss', 
            $nomor_ba,      // s - nomor_ba
            $tanggal_ba,    // s - tanggal_ba
            $pekerjaan,     // s - pekerjaan
            $description,   // s - description (dari items)
            $item_code,     // s - item_code (dari items)
            $qty,           // i - qty (dari items)
            $um,            // s - um (dari items)
            $keterangan,    // s - keterangan (dari items)
            $po_number,     // s - po_number
            $invoice_id,    // i - invoice_id
            $customer_name, // s - customer_name
            $customer_alamat, // s - customer_alamat
            $lokasi,        // s - lokasi
            $pelaksana      // s - pelaksana
        );
        
        if (!$stmt_header->execute()) {
            if ($mysqli->errno == 1062) {
                 throw new Exception("Nomor Berita Acara **{$nomor_ba}** sudah ada. Silakan Refresh halaman.");
            }
            throw new Exception("Gagal menyimpan berita acara: " . $stmt_header->error);
        }
        
        $ba_id = $mysqli->insert_id; 
        
        // 2. Simpan item ke berita_acara_items (jika tabel ada dan ada lebih dari 1 item)
        $check_items_table = mysqli_query($mysqli, "SHOW TABLES LIKE 'berita_acara_items'");
        if (mysqli_num_rows($check_items_table) > 0 && count($items) > 1) {
            $stmt_item = $mysqli->prepare("
                INSERT INTO berita_acara_items 
                (berita_acara_id, item_no, description, item_code, qty, unit, keterangan) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt_item) {
                // Mulai dari item ke-2 (karena item pertama sudah disimpan di header)
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
                            $ba_id, $item_no, $item_description, $item_code_val, $qty_item, $unit, $keterangan_item
                        );
                        
                        if (!$stmt_item->execute()) {
                            throw new Exception("Gagal menyimpan item ke-{$item_no}: " . $stmt_item->error);
                        }
                    }
                }
            }
        }
        
        $mysqli->commit();
        $success = true;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        flash_set('error', 'Error saat menyimpan Berita Acara: ' . $e->getMessage());
    }

    if ($success) {
        flash_set('success', "Berita Acara {$nomor_ba} berhasil disimpan!");
        header('Location: berita_acara_list.php'); 
        exit;
    } else {
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// --------------------------------------------------
// --- Bagian D: HTML Output ---
// --------------------------------------------------
$error_msg = flash_get('error');
$success_msg = flash_get('success'); 
include 'header.php'; 
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">ðŸ“‹ Buat Berita Acara Serah Terima (BAST)</h3>
        <a href="berita_acara_list.php" class="btn btn-outline-secondary">
            <i class="fas fa-list"></i> Kembali ke Daftar
        </a>
    </div>

    <?php if($error_msg): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>
    <?php if($success_msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div><?php endif; ?>

    <form id="beritaAcaraForm" method="POST" action="">
        <input type="hidden" name="items_json" id="items_json_input">
        <input type="hidden" name="invoice_id" id="invoice_id_input">
        <input type="hidden" name="action" value="save">

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
                                   value="<?php echo htmlspecialchars($next_ba_no); ?>" required readonly>
                        </div>
                        <div class="form-group">
                            <label>Tanggal BA <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_ba" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>PO Number</label>
                            <div class="input-group">
                                <input type="text" name="po_number" id="po_number_input" 
                                       class="form-control" placeholder="Masukkan Nomor PO">
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
                                       class="form-control" required placeholder="Nama Customer">
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Lokasi <span class="text-danger">*</span></label>
                                    <input type="text" name="lokasi" id="lokasi_input" 
                                           class="form-control" required placeholder="Lokasi Pekerjaan">
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
                                      class="form-control" rows="2" placeholder="Alamat Customer"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Pekerjaan / Uraian <span class="text-danger">*</span></label>
                    <input type="text" name="pekerjaan" class="form-control" 
                           placeholder="Contoh: PR#02581 Mtc [C8888] Repair Modif Gear Turret CNC Takamaz" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Pelaksana (Pihak II)</label>
                        <input type="text" name="pelaksana" class="form-control" 
                               value="CV. Afshin Raya Teknik" required>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ITEMS CARD (Wajib - data akan disimpan ke kolom utama) -->
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
                        <tbody>
                            <tr>
                                <td class="item_no">1</td>
                               <td>
                                    <textarea 
                                        name="item_desc[]" 
                                        class="form-control form-control-sm item-desc"
                                        rows="2"
                                        placeholder="Uraian item"
                                        required
                                    ></textarea>
                                </td>
                                <td>
                                    <input type="text" name="item_code[]" class="form-control form-control-sm item-code" 
                                           value="-" placeholder="Kode Item">
                                </td>
                                <td>
                                    <input type="number" name="item_qty[]" class="form-control form-control-sm item-qty" 
                                           value="1" min="1" required>
                                </td>
                                <td>
                                    <select name="item_unit[]" class="form-control form-control-sm item-unit" required>
                                        <option value="">-- pilih --</option>
                                        <?php foreach($satuans as $satuan): ?>
                                        <option value="<?php echo $satuan; ?>" <?php echo $satuan == 'Unit' ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($satuan); ?>
                                        </option>
                                        <?php endforeach; ?>
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
            <button type="button" onclick="prepareAndPrintDraft()" class="btn btn-info btn-lg mx-2">
                <i class="fas fa-print"></i> Preview BA
            </button>
            <button type="button" onclick="prepareAndSubmitBA()" class="btn btn-success btn-lg">
                <i class="fas fa-save"></i> Simpan Berita Acara
            </button>
        </div>
    </form>
</div> 

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
const PO_ITEMS_DATA = <?php echo json_encode($po_items_data); ?>;
// --- Data dari PHP untuk autofill ---
const PO_CUSTOMER_DATA = <?php echo json_encode($po_customer_data); ?>;
const PO_LIST = <?php echo json_encode($po_list); ?>;

// --- FUNGSI UTAMA ---

$(document).ready(function() {
    // 1. Handler untuk PO Select
   $("#po_number_select").change(function() {
    const selectedPO = $(this).val();
    $("#po_number_input").val(selectedPO);

    if (selectedPO && PO_CUSTOMER_DATA[selectedPO]) {
        const header = PO_CUSTOMER_DATA[selectedPO];

        $("#customer_name_input").val(header.customer_name);
        $("#lokasi_input").val(header.customer_name);
        $("#invoice_id_input").val(header.invoice_id);

        fillItemsFromPO(selectedPO); // 🔥 INI KUNCINYA
    }

    $(this).val('');
});
    
    // 2. Auto-fill lokasi sama dengan customer
    $("#customer_name_input").on('input', function() {
        if ($("#lokasi_input").val() === '') {
            $("#lokasi_input").val($(this).val());
        }
    });
    
    // 3. Auto-fill pekerjaan dari item description baris pertama
    $('#itemsTable').on('input', '.item-desc:first', function() {
        const pekerjaanInput = $('input[name="pekerjaan"]');
        if (pekerjaanInput.val() === '') {
            pekerjaanInput.val($(this).val());
        }
    });
    
    // 4. Re-index tabel item
    reindexTable('itemsTable');
});

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
                    <?php foreach($satuans as $satuan): ?>
                    <option value="<?php echo $satuan; ?>">
                        <?php echo htmlspecialchars($satuan); ?>
                    </option>
                    <?php endforeach; ?>
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
    $("#itemsTable tbody").append(tr);
    reindexTable('itemsTable');
});

// Hapus baris item (tidak boleh menghapus baris pertama)
$(document).on("click", ".removeRow", function(){
    const rowCount = $("#itemsTable tbody tr").length;
    if (rowCount > 1) {
        $(this).closest("tr").remove();
        reindexTable('itemsTable');
    } else {
        alert("Minimal harus ada 1 baris item!");
    }
});

function fillItemsFromPO(poNumber) {
    const items = PO_ITEMS_DATA[poNumber];
    if (!items || items.length === 0) return;

    $("#itemsTable tbody").empty();

    items.forEach((item, index) => {
        const tr = `
            <tr>
                <td class="item_no">${index + 1}</td>
                <td>
                    <input type="text" class="form-control form-control-sm item-desc" 
                           value="${item.description}" required>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm item-code" 
                           value="${item.item_code}">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm item-qty" 
                           value="${item.qty}" min="1" required>
                </td>
                <td>
                    <select class="form-control form-control-sm item-unit" required>
                        <option value="Unit" ${item.unit === 'Unit' ? 'selected' : ''}>Unit</option>
                        <option value="Pcs" ${item.unit === 'Pcs' ? 'selected' : ''}>Pcs</option>
                        <option value="Set" ${item.unit === 'Set' ? 'selected' : ''}>Set</option>
                        <option value="Lot" ${item.unit === 'Lot' ? 'selected' : ''}>Lot</option>
                    </select>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm item-ket" 
                           value="${item.keterangan}">
                </td>
                <td>
                    <button class="btn btn-danger btn-sm removeRow" type="button">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
        $("#itemsTable tbody").append(tr);
    });

    reindexTable('itemsTable');
}

// --- FUNGSI VALIDASI & SUBMIT ---

function collectItemsData() {
    let itemsData = [];
    $("#itemsTable tbody tr").each(function() {
        const description = $(this).find('.item-desc').val();
        const itemCode = $(this).find('.item-code').val();
        const qty = $(this).find('.item-qty').val();
        const unit = $(this).find('.item-unit').val();
        const keterangan = $(this).find('.item-ket').val();

        // Baris pertama wajib diisi
        if ($(this).index() === 0) {
            if ($.trim(description) === '' || !qty || !unit) {
                return false; // Trigger error
            }
        }
        
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

function prepareAndSubmitBA() {
    // Validasi header
    const nomorBA = $('input[name="nomor_ba"]').val();
    const tanggalBA = $('input[name="tanggal_ba"]').val();
    const customerName = $('input[name="customer_name"]').val();
    const lokasi = $('input[name="lokasi"]').val();
    const pekerjaan = $('input[name="pekerjaan"]').val();

    if (!nomorBA || !tanggalBA || !customerName || !lokasi || !pekerjaan) {
        alert("Nomor BA, Tanggal, Customer, Lokasi, dan Pekerjaan harus diisi!");
        return false;
    }

    // Kumpulkan data item
    const itemsData = collectItemsData();
    
    // Validasi item pertama
    if (itemsData.length === 0) {
        alert("Item pertama (Description, Qty, Unit) harus diisi!");
        return false;
    }
    
    // Set data ke hidden input
    $("#items_json_input").val(JSON.stringify(itemsData));

    // Submit form
    if (confirm("Apakah Anda yakin ingin menyimpan Berita Acara ini?")) {
        $("#beritaAcaraForm").submit();
    }
    return false;
}

function prepareAndPrintDraft() {
    // Validasi minimal
    const nomorBA = $('input[name="nomor_ba"]').val();
    const customerName = $('input[name="customer_name"]').val();
    const pekerjaan = $('input[name="pekerjaan"]').val();

    if (!nomorBA || !customerName || !pekerjaan) {
        alert("Nomor BA, Customer, dan Pekerjaan harus diisi untuk preview!");
        return;
    }

    // Kumpulkan semua data form
    const itemsData = collectItemsData();
    if (itemsData.length === 0) {
        alert("Item pertama harus diisi untuk preview!");
        return;
    }

    const firstItem = itemsData[0];
    
    const formData = {
        nomor_ba: nomorBA,
        tanggal_ba: $('input[name="tanggal_ba"]').val(),
        customer_name: customerName,
        customer_alamat: $('#customer_alamat_input').val(),
        lokasi: $('input[name="lokasi"]').val(),
        pekerjaan: pekerjaan,
        description: firstItem.description || '',
        item_code: firstItem.item_code || '-',
        qty: firstItem.qty || 1,
        um: firstItem.unit || 'Unit',
        keterangan: firstItem.keterangan || 'OK',
        po_number: $('input[name="po_number"]').val(),
        invoice_id: $('#invoice_id_input').val(),
        pelaksana: $('input[name="pelaksana"]').val(),
        items: itemsData
    };

    // Simpan ke session atau langsung buka preview
    $.ajax({
        url: 'berita_acara_preview.php',
        type: 'POST',
        data: { draft_data: JSON.stringify(formData) },
        success: function(response) {
            window.open('berita_acara_preview.php', '_blank');
        },
        error: function() {
            alert("Error saat membuat preview!");
        }
    });
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
</style>

<?php 
include 'footer.php'; 
?>