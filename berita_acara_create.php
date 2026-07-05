<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
require_once 'functions.php';
require_login();

if (!$mysqli) {
    die("Koneksi database gagal!");
}

/* ============================================================
   1. AMBIL DATA PO + CUSTOMER (HEADER)
============================================================ */

$po_numbers_query = mysqli_query($mysqli, "
    SELECT 
        i.id,
        i.po_number,
        i.customer_id,
        c.name AS customer_name,
        c.address AS customer_alamat,
        i.created_at
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    WHERE i.po_number IS NOT NULL 
      AND i.po_number != ''
    ORDER BY i.created_at DESC
");

$po_list = [];
$po_customer_data = [];

if ($po_numbers_query) {
    while ($row = mysqli_fetch_assoc($po_numbers_query)) {
        $po_list[$row['po_number']] = $row['id'];
        $po_customer_data[$row['po_number']] = [
            'invoice_id'     => $row['id'],
            'customer_name'  => $row['customer_name'],
            'customer_alamat'=> $row['customer_alamat']
        ];
    }
}

/* ============================================================
   2. AMBIL ITEM BERDASARKAN INVOICE_ID
============================================================ */

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

/* ============================================================
   3. GENERATE NOMOR BA
============================================================ */

function bulan_romawi($month){
    $romawi = [1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V',6=>'VI',7=>'VII',8=>'VIII',9=>'IX',10=>'X',11=>'XI',12=>'XII'];
    return $romawi[$month];
}

function generate_ba_no($mysqli){
    $year = date('Y');
    $month = bulan_romawi(date('n'));

    $q = mysqli_query($mysqli,"
        SELECT nomor_ba FROM berita_acara
        WHERE nomor_ba LIKE '%/$year'
        ORDER BY id DESC LIMIT 1
    ");

    $last = 0;
    if($q && $row = mysqli_fetch_assoc($q)){
        $parts = explode('/',$row['nomor_ba']);
        $last = (int)$parts[0];
    }

    $next = str_pad($last+1,3,'0',STR_PAD_LEFT);
    return "$next/BAST-ART/$month/$year";
}

$next_ba_no = generate_ba_no($mysqli);

/* ============================================================
   4. SIMPAN DATA
============================================================ */

if($_SERVER['REQUEST_METHOD']=='POST' && $_POST['action']=='save'){

    $nomor_ba       = $_POST['nomor_ba'];
    $tanggal_ba     = $_POST['tanggal_ba'];
    $customer_name  = $_POST['customer_name'];
    $customer_alamat= $_POST['customer_alamat'];
    $lokasi         = $_POST['lokasi'];
    $pekerjaan      = $_POST['pekerjaan'];
    $po_number      = $_POST['po_number'];
    $invoice_id     = (int)$_POST['invoice_id'];
    $pelaksana      = $_POST['pelaksana'];   // ← HAPUS baris duplikat item_code
    $note           = $_POST['note'] ?? '';

    $items = json_decode($_POST['items_json'], true);

    if(empty($nomor_ba)||empty($tanggal_ba)||empty($customer_name)||empty($pekerjaan)){
        flash_set('error','Harap lengkapi data terlebih dahulu!');
        header("Location: berita_acara_create.php");
        exit;
    }

    $mysqli->begin_transaction();

    try{
        $first = $items[0];

        // Ambil nilai dari $first sebagai variabel terpisah agar bind_param bekerja
        $f_description = $first['description'] ?? '';
        $f_item_code   = $first['item_code']   ?? '-';
        $f_qty         = (int)($first['qty']   ?? 1);
        $f_unit        = $first['unit']        ?? 'Unit';
        $f_keterangan  = $first['keterangan']  ?? 'OK';

        $stmt = $mysqli->prepare("
            INSERT INTO berita_acara
            (nomor_ba, tanggal_ba, pekerjaan, description, item_code, qty, um, keterangan,
             po_number, invoice_id, customer_name, customer_alamat, lokasi, pelaksana, note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param("sssssississssss",
    $nomor_ba,        // s (1)
    $tanggal_ba,      // s (2)
    $pekerjaan,       // s (3)
    $f_description,   // s (4)
    $f_item_code,     // s (5)
    $f_qty,           // i (6)
    $f_unit,          // s (7)
    $f_keterangan,    // s (8)
    $po_number,       // s (9) ← bukan i
    $invoice_id,      // i (10)
    $customer_name,   // s (11)
    $customer_alamat, // s (12)
    $lokasi,          // s (13)
    $pelaksana,       // s (14)
    $note             // s (15)
);

        if(!$stmt->execute()){
            throw new Exception("Insert berita_acara gagal: " . $stmt->error);
        }

        $ba_id = $mysqli->insert_id;

        /* SIMPAN ITEM TAMBAHAN (index 1 dst) */
        if(count($items) > 1){

            $stmtItem = $mysqli->prepare("
                INSERT INTO berita_acara_items
                (berita_acara_id, item_no, description, item_code, qty, unit, keterangan)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            for($i = 1; $i < count($items); $i++){
                $it      = $items[$i];
                $no      = $i + 1;
                $it_desc = $it['description'] ?? '';
                $it_code = $it['item_code']   ?? '-';
                $it_qty  = (int)($it['qty']   ?? 1);
                $it_unit = $it['unit']        ?? 'Unit';
                $it_ket  = $it['keterangan']  ?? 'OK';

                $stmtItem->bind_param("iississ",
                    $ba_id,
                    $no,
                    $it_desc,
                    $it_code,
                    $it_qty,
                    $it_unit,
                    $it_ket
                );

                if(!$stmtItem->execute()){
                    throw new Exception("Insert item[$i] gagal: " . $stmtItem->error);
                }
            }
        }

        $mysqli->commit();
        header("Location: berita_acara_list.php");
        exit;

    }catch(Exception $e){
        $mysqli->rollback();
        die("Error: " . $e->getMessage());
    }
}

include 'header.php';
?>
<style>

/* ==============================
   GLOBAL LAYOUT FIX
============================== */

.ba-container {
    width: 100%;
    max-width: 1100px;   /* batas agar tidak terlalu melebar */
    margin: 0 auto;
    padding: 20px 25px 40px 25px;
    box-sizing: border-box;
}

/* Header Card */
.ba-header {
    width: 100%;
    border-radius: 16px;
    padding: 28px 30px;
}

/* Card */
.card-modern {
    width: 100%;
    border-radius: 16px;
    padding: 25px 28px;
    margin-bottom: 20px;
}

/* ==============================
   GRID RESPONSIVE
============================== */

.form-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

/* 2 kolom untuk tablet */
@media (max-width: 1200px) {
    .form-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* 1 kolom untuk mobile */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}

/* Full width field */
.form-group.full {
    grid-column: 1 / -1;
}

/* ==============================
   INPUT FIX
============================== */

.form-control-modern {
    width: 100%;
    padding: 12px 14px;
    font-size: 14px;
    border-radius: 10px;
    border: 1px solid #dcdfe6;
    transition: all 0.2s ease;
}

.form-control-modern:focus {
    border-color: #4361ee;
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
}

/* Input group PO fix */
.input-group-modern {
    display: flex;
    gap: 10px;
}

.input-group-modern select {
    max-width: 180px;
}

/* ==============================
   TABLE FIX
============================== */

.table-modern-wrapper {
    width: 100%;
    overflow-x: auto;
}

.table-modern {
    min-width: 900px; /* agar tidak pecah di desktop */
}

@media (max-width: 768px) {
    .table-modern {
        min-width: 700px;
    }
}

/* ==============================
   BUTTON FIX
============================== */

.action-footer {
    width: 100%;
    margin-top: 25px;
    display: flex;
    justify-content: flex-end;
}

@media (max-width: 768px) {
    .action-footer {
        justify-content: center;
    }

    .btn-modern {
        width: 100%;
    }
}

body{
    background:#f4f6fb;
    font-family:'Segoe UI',sans-serif;
}
.ba-container{
    max-width:1200px;
    margin:auto;
    padding:30px;
}
.ba-header{
    background:linear-gradient(135deg,#4361ee,#3a0ca3);
    color:white;
    padding:25px;
    border-radius:14px;
    margin-bottom:25px;
    box-shadow:0 10px 25px rgba(0,0,0,0.1);
}
.card-modern{
    background:white;
    padding:25px;
    border-radius:14px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}
.section-title{
    font-weight:600;
    font-size:18px;
    margin-bottom:20px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.form-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:20px;
}
.form-group.full{
    grid-column:1/-1;
}
.form-control-modern{
    width:100%;
    padding:12px;
    border:1px solid #ddd;
    border-radius:8px;
    transition:.3s;
}
.form-control-modern:focus{
    border-color:#4361ee;
    box-shadow:0 0 0 3px rgba(67,97,238,0.15);
}
.input-group-modern{
    display:flex;
    gap:10px;
}
.table-modern-wrapper{
    overflow-x:auto;
}
.table-modern{
    width:100%;
    border-collapse:collapse;
}
.table-modern th{
    background:#f1f3f8;
    padding:12px;
}
.table-modern td{
    padding:10px;
    border-top:1px solid #eee;
}
.btn-modern{
    padding:10px 18px;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-weight:600;
    transition:.3s;
}
.btn-primary-modern{
    background:#4361ee;
    color:white;
}
.btn-primary-modern:hover{
    background:#3a0ca3;
}
.btn-success-modern{
    background:#2ecc71;
    color:white;
}
.btn-success-modern:hover{
    background:#27ae60;
}
.action-footer{
    margin-top:25px;
    text-align:right;
}
.remove{
    background:#e63946;
    color:white;
    border:none;
    padding:6px 10px;
    border-radius:6px;
}
.remove:hover{
    background:#c9184a;
}
</style>

<div class="ba-container">

<div class="ba-header">
    <h2><i class="fas fa-file-signature"></i> Buat Berita Acara Serah Terima</h2>
    <p>Form pembuatan Berita Acara berdasarkan PO</p>
</div>

<form id="formBA" method="POST">
<input type="hidden" name="items_json" id="items_json_input">
<input type="hidden" name="invoice_id" id="invoice_id_input">
<input type="hidden" name="action" value="save">

<div class="card-modern">
    <div class="section-title">
        <i class="fas fa-info-circle"></i> Informasi Dokumen
    </div>

    <div class="form-grid">

        <div class="form-group">
            <label>Nomor BA</label>
            <input type="text" name="nomor_ba" class="form-control-modern"
                value="<?= $next_ba_no ?>" readonly>
        </div>

        <div class="form-group">
            <label>Tanggal</label>
            <input type="date" name="tanggal_ba" class="form-control-modern"
                value="<?= date('Y-m-d') ?>">
        </div>

        <div class="form-group">
            <label>PO Number</label>
            <div class="input-group-modern">
                <input type="text" name="po_number" id="po_number_input"
                    class="form-control-modern">
                <select id="po_select" class="form-control-modern">
                    <option value="">-- Pilih PO --</option>
                    <?php foreach($po_list as $po=>$id): ?>
                        <option value="<?= $po ?>"><?= $po ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Customer</label>
            <input type="text" name="customer_name"
                id="customer_name_input" class="form-control-modern">
        </div>

        <div class="form-group">
            <label>Lokasi</label>
            <input type="text" name="lokasi"
                id="lokasi_input" class="form-control-modern">
        </div>

        <div class="form-group full">
            <label>Alamat Customer</label>
            <textarea name="customer_alamat"
                id="customer_alamat_input"
                class="form-control-modern" rows="2"></textarea>
        </div>
<div class="form-group full">
    <label>Pihak II (Pelaksana)</label>
    <input type="text"
           class="form-control-modern"
           value="CV. Afshin Raya Teknik"
           readonly>
    <input type="hidden"
           name="pelaksana"
           value="CV. Afshin Raya Teknik">
</div>
        <div class="form-group full">
            <label>Pekerjaan</label>
            <input type="text" name="pekerjaan"
                class="form-control-modern">
        </div>

    </div>
</div>
<div class="form-group full">
    <label>Catatan Tambahan (Additional Notes)</label>
    <textarea name="note"
        id="note_input"
        class="form-control-modern" rows="3"
        placeholder="Masukkan catatan tambahan jika ada..."></textarea>
</div>

<div class="card-modern mt-4">
    <div class="section-title">
        <i class="fas fa-boxes"></i> Daftar Item
        <button type="button" id="addRow"
            class="btn-modern btn-success-modern">
            <i class="fas fa-plus"></i> Tambah
        </button>
    </div>

    <div class="table-modern-wrapper">
        <table class="table-modern" id="itemsTable">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Description</th>
                    <th>Item Code</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Keterangan</th>
                    <th></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="action-footer">
    <button type="button"
        onclick="submitBA()"
        class="btn-modern btn-primary-modern">
        <i class="fas fa-save"></i> Simpan Berita Acara
    </button>
</div>
<div class="modal fade" id="validationModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">
          <i class="fas fa-exclamation-triangle"></i> Data Belum Lengkap
        </h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          &times;
        </button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger mb-0">
          Harap lengkapi data yang wajib diisi terlebih dahulu!
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
          OK
        </button>
      </div>
    </div>
  </div>
</div>
</form>
</div>

<script>
const PO_CUSTOMER_DATA = <?= json_encode($po_customer_data); ?>;
const PO_ITEMS_DATA = <?= json_encode($po_items_data); ?>;

$("#po_select").change(function(){

    let po = $(this).val();
    $("#po_number_input").val(po);

    if(!po) return;

    let header = PO_CUSTOMER_DATA[po];
    $("#customer_name_input").val(header.customer_name);
    $("#customer_alamat_input").val(header.customer_alamat);
    $("#lokasi_input").val(header.customer_name);
    $("#invoice_id_input").val(header.invoice_id);

    fillItems(po);
});

function fillItems(po){

    let items = PO_ITEMS_DATA[po];
    $("#itemsTable tbody").empty();

    items.forEach((it,i)=>{

        $("#itemsTable tbody").append(`
        <tr>
        <td>${i+1}</td>
        <td><input name="item_desc[]" class="form-control" value="${it.description}"></td>
        <td><input name="item_code[]" class="form-control" value="${it.item_code}"></td>
        <td><input type="number" name="item_qty[]" class="form-control" value="${it.qty}"></td>
        <td><input name="item_unit[]" class="form-control" value="${it.unit}"></td>
        <td><input name="item_ket[]" class="form-control" value="${it.keterangan}"></td>
        <td><button class="btn btn-danger btn-sm remove">X</button></td>
        </tr>
        `);

    });
}

$(document).on("click",".remove",function(){
    $(this).closest("tr").remove();
});

function submitBA(){

    let nomor = $("input[name='nomor_ba']").val();
    let tanggal = $("input[name='tanggal_ba']").val();
    let customer = $("input[name='customer_name']").val();
    let pekerjaan = $("input[name='pekerjaan']").val();

    if(!nomor || !tanggal || !customer || !pekerjaan){
        $("#validationModal").modal("show");
        return;
    }

    let data=[];

    $("#itemsTable tbody tr").each(function(){

        let desc = $(this).find("[name='item_desc[]']").val();
        let qty  = $(this).find("[name='item_qty[]']").val();

        if(desc && qty){
            data.push({
                description: desc,
                item_code: $(this).find("[name='item_code[]']").val(),
                qty: qty,
                unit: $(this).find("[name='item_unit[]']").val(),
                keterangan: $(this).find("[name='item_ket[]']").val()
            });
        }

    });

    if(data.length === 0){
        $("#validationModal").modal("show");
        return;
    }

    $("#items_json_input").val(JSON.stringify(data));
    $("#formBA").submit();
}
</script>

<?php include 'footer.php'; ?>