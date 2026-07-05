<?php
// =========================================================================
// travel_document_print.php (Versi Revisi Final - Dinamis dari DB atau Draft)
// =========================================================================

require_once 'functions.php'; 

// Data Default Perusahaan Anda
$company_name = 'CV. AFSHIN RAYA TEKNIK';
$company_slogan = 'Penyedia Sparepart Mesin Bubut dan Milling, Jasa Maintenance dan Kontruksi Gedung';
$company_address = 'Kp. Ciketing, Jl. Kramat No. 75, RT. 004 RW. 011, Desa/Kelurahan Mustikajaya, Kecamatan Mustikajaya, Kota Bekasi, Jawa Barat, 17158';
$company_phone = 'Tlp : +62 896 1464 7011';
$company_email = 'Email : cvafshinrayateknik@gmail.com';

// Inisialisasi variabel dokumen
$travel_no = '(Nomor SJ - Belum Tersimpan)'; // Default untuk Draft
$customer_id = 0;
$date_doc = date('Y-m-d');
$po_number = '-'; 
$note = '';
$prod_code = '-';
$ship_by = '-';
$dell_to = '-';
$items = [];
$customer_data = null;
$error_message = null;

// --- 1. LOGIKA PENGAMBILAN DATA (DB vs DRAFT) ---

if (isset($_GET['id'])) {
    // SCENARIO A: Cetak dari dokumen yang SUDAH TERSIMPAN (Travel Document ID)
    $document_id = intval($_GET['id']);
    
    // a. Ambil data Header
    $q_doc = mysqli_query($mysqli, "
        SELECT 
            td.*, 
            c.name AS customer_name, c.address AS customer_address, c.pic AS customer_pic
        FROM travel_documents td 
        LEFT JOIN customers c ON td.customer_id = c.id
        WHERE td.id = {$document_id}
    ");
    
    if ($q_doc && $doc_data = mysqli_fetch_assoc($q_doc)) {
        // Isi variabel dokumen dari DB
        $travel_no   = $doc_data['travel_no'];
        $customer_id = $doc_data['customer_id'];
        $date_doc    = $doc_data['date_doc'];
        $po_number   = $doc_data['po_number'] ?: '-';
        $note        = $doc_data['note'] ?: '';
        $prod_code   = $doc_data['prod_code'] ?: '-';
        $ship_by     = $doc_data['ship_by'] ?: '-';
        $dell_to     = $doc_data['dell_to'] ?: '-';

        // Isi data Customer dari hasil JOIN
        $customer_data = [
            'name'    => $doc_data['customer_name'],
            'address' => $doc_data['customer_address'],
            'pic'     => $doc_data['customer_pic']
        ];

        // b. Ambil data Item
        $q_items = mysqli_query($mysqli, "
            SELECT item_desc, qty, unit, remarks 
            FROM travel_document_items 
            WHERE document_id = {$document_id} 
            ORDER BY item_no ASC
        ");
        while ($item = mysqli_fetch_assoc($q_items)) {
            $items[] = $item;
        }

    } else {
        $error_message = "Dokumen dengan ID: {$document_id} tidak ditemukan.";
    }

} else {
    // SCENARIO B: Cetak dari data DRAFT / EDIT (menggunakan parameter GET/URL)
    
    // a. Ambil data Header dari URL
    $travel_no   = $_GET['travel_no'] ?? '(Nomor SJ - Belum Tersimpan)';
    $customer_id = $_GET['customer_id'] ?? 0;
    $date_doc    = $_GET['date_doc'] ? date('Y-m-d', strtotime($_GET['date_doc'])) : date('Y-m-d'); 
    $po_number   = $_GET['po_number'] ?? '-'; 
    $note        = $_GET['note'] ?? '';
    $prod_code   = $_GET['prod_code'] ?? '-';
    $ship_by     = $_GET['ship_by'] ?? '-';
    $dell_to     = $_GET['delv_to'] ?? '-'; // Di Create/Edit pakai 'delv_to'

    // b. Ambil data Item dari JSON di URL
    $items_json = $_GET['items_json'] ?? '[]';
    $items = json_decode($items_json, true);

    // c. Ambil data Customer dari DB berdasarkan ID
    if ($customer_id && isset($mysqli)) {
        $q_cust = mysqli_query($mysqli, "SELECT name, address, pic FROM customers WHERE id = " . intval($customer_id));
        if ($q_cust) {
            $customer_data = mysqli_fetch_assoc($q_cust);
        }
    }
}

// --- 2. FUNGSI UTILITY ---
if (!function_exists('format_date')) {
    function format_date($date_str) {
        // Contoh format: 24-Nov-2025
        return date('d-M-Y', strtotime($date_str));
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Surat Jalan - <?php echo htmlspecialchars($travel_no); ?></title>

<style>

body {
    font-family: Arial, sans-serif;
    font-size: 12px;
    margin: 5mm; 
}

@page {
    size: A4;
    margin: 1mm; 
}

/* === HEADER (Kop Surat) === */
.header-container {
    margin-bottom: 5px; 
    border-top: 1px solid black; 
    border-bottom: 1px solid black; 
    padding: 5px 0 1px 0;
    overflow: auto; 
}

.header-logo {
    float: left;
    width: 150px; 
    height: 100px; 
    margin-right: 15px;
    text-align: center;
}

.header-logo img {
    height: 100px; 
    width: auto;
    display: block;
}

.header-content {
    margin-left: 170px; 
    padding-top: 5px;
}

.header-content h3 {
    font-weight: bold;
    font-size: 16px;
    margin: 0;
    padding-bottom: 10px;
    display: inline-block; 
}

.header-content p {
    margin: 0;
    font-size: 12px;
    line-height: 1.4;
}

.title {
    text-align: center;
    font-size: 16px;
    margin: 10px 0 20px 0;
    text-decoration: underline;
    font-weight: bold;
}

/* === Info Block (Detail SJ) === */
.info-container {
    padding: 5px 0; 
    overflow: auto; 
    margin-bottom: 15px; 
}

.customer-info {
    font-size: 12px;
    width: 48%;
    float: left;
}
.customer-info table, .doc-info table {
    width: 100%;
    table-layout: fixed; 
}
.customer-info table td, .doc-info table td {
    padding: 1px 0;
    vertical-align: top;
}

.customer-info .label {
    width: 70px; 
}
.customer-info .colon {
    width: 5px; 
}


.doc-info {
    font-size: 12px;
    width: 48%;
    float: right;
}
.doc-info table {
    margin-left: 10px;
}
.doc-info .label {
    width: 120px;
}
.doc-info .colon {
    width: 5px;
}

/* === Items Table === */
.items-intro {
    margin-bottom: 5px;
}

table.items {
    border-collapse: collapse;
    width: 100%;
    margin-top: 5px; 
}

table.items th, table.items td {
    border: 1px solid black;
    padding: 6px;
}

table.items th {
    background: #f2f2f2; 
    text-align: center;
    font-size: 12px;
}
table.items td {
    vertical-align: top;
}

/* === Note and Signature === */
.note-container {
    margin-top: 15px;
    font-size: 12px;
    padding-bottom: 10px;
}

.signature-table {
    width: 100%;
    margin-top: 40px; 
    text-align: center;
}
.signature-table td {
    width: 50%;
    padding-top: 10px;
}
.signature-line {
    border-bottom: 1px solid black;
    width: 80%;
    margin: 100px auto 5px auto; 
}
</style>

</head>
<body>

<?php if ($error_message): ?>
    <div style="color: red; font-size: 14px; text-align: center; border: 1px solid red; padding: 10px; margin: 50px;">
        Error: <?php echo htmlspecialchars($error_message); ?>
    </div>
    <script>window.onload = function() { console.error("<?php echo addslashes($error_message); ?>"); };</script>
<?php return; endif; ?>

<div class="document-container">

    <div class="header-container">
        <div class="header-logo">
            <img src="img/afshin2.png" alt="Logo CV. AFSHIN RAYA TEKNIK"> 
        </div>

        <div class="header-content">
            <h3><?php echo htmlspecialchars($company_name); ?></h3>
            <p style="font-weight: bold; margin-top: 2px;">
                <?php echo htmlspecialchars($company_slogan); ?>
            </p>
            <p>
                <?php echo htmlspecialchars($company_address); ?>
            </p>
            <p>
                <?php echo htmlspecialchars($company_phone); ?>
                <br>
                <?php echo htmlspecialchars($company_email); ?>
            </p>
        </div>
    </div>

    <div class="title">SURAT JALAN</div>

    <div class="">
        <div class="customer-info">
            <table>
                <tr>
                    <td class="label">SHIP TO</td>
                    <td class="colon">:</td>
                    <td><?php echo htmlspecialchars($customer_data['name'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td class="label">ADDRESS</td>
                    <td class="colon">:</td>
                    <td><?php echo nl2br(htmlspecialchars($customer_data['address'] ?? 'N/A')); ?></td>
                </tr>
                <tr>
                    <td class="label">ATTN</td>
                    <td class="colon">:</td>
                    <td><?php echo htmlspecialchars($customer_data['pic'] ?? '-'); ?></td>
                </tr>
            </table>
        </div>
        
        <div class="doc-info">
            <table>
                <tr>
                    <td class="label"><strong>NUMBER</strong></td>
                    <td class="colon">:</td>
                    <td><?php echo htmlspecialchars($travel_no); ?></td> 
                </tr>
                <tr>
                    <td class="label"><strong>DATE</strong></td>
                    <td class="colon">:</td>
                    <td><?php echo format_date($date_doc); ?></td>
                </tr>
                <tr>
                    <td class="label"><strong>PO NUMBER</strong></td>
                    <td class="colon">:</td>
                    <td><?php echo htmlspecialchars($po_number); ?></td>
                </tr>
                <tr>
                    <td class="label"><strong>PRODUCT CODE</strong></td>
                    <td class="colon">:</td>
                    <td><?php echo htmlspecialchars($prod_code); ?></td>
                </tr>
                <tr>
                    <td class="label"><strong>SHIP BY</strong></td>
                    <td class="colon">:</td>
                    <td><?php echo htmlspecialchars($ship_by); ?></td>
                </tr>
                <tr>
                    <td class="label"><strong>DELIVER TO</strong></td>
                    <td class="colon">:</td>
                    <td><?php echo htmlspecialchars($dell_to); ?></td>
                </tr>
            </table>
        </div>
        <div style="clear: both;"></div>
    </div>

    <p class="items-intro">Bersama dengan ini kami kirimkan sejumlah barang, yaitu :</p>

    <table class="items">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="45%">Item Description</th>
                <th width="10%">Qty</th>
                <th width="10%">Satuan</th>
                <th width="30%">Keterangan (Remarks)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if (!empty($items)): 
                $num = 1;
                foreach ($items as $item):
            ?>
            <tr>
                <td align="center"><?php echo $num++; ?></td>
                <td><?php echo htmlspecialchars($item['item_desc'] ?? ''); ?></td>
                <td align="center"><?php echo number_format($item['qty'] ?? 0); ?></td>
                <td align="center"><?php echo htmlspecialchars($item['unit'] ?? ''); ?></td>
                <td align="center"><?php echo htmlspecialchars($item['remarks'] ?? ''); ?></td>
            </tr>
            <?php 
                endforeach; 
            else:
            ?>
            <tr>
                <td colspan="5" align="center">Tidak ada item</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
	<div class="text-left">
	<p>Diterima tanggal : <?php echo format_date($date_doc); ?></p>
	</div>
    <?php if (!empty($note)): ?>
        <div class="note-container">
            <strong>Catatan:</strong>
            <p style="border: 1px solid #000; padding: 5px; margin-top: 5px; min-height: 40px;">
                <?php echo nl2br(htmlspecialchars($note)); ?>
            </p>
        </div>
    <?php endif; ?>

    <table class="signature-table">
        <tr>
            <td class="text-left">
                
                <br><br>
                Penerima
                <div class="signature-line"></div>
         
            </td>
            <td>
                Best Regards,
                <br><br>
                <?php echo htmlspecialchars($company_name); ?>
                <br><br>
                <div class="signature-line"></div>
              
            </td>
        </tr>
    </table>
</div>

<script>
    window.onload = function() {
        window.print();
    };
</script>

</body>
</html>