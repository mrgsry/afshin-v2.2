<?php
require_once 'functions.php';
require_login();

$id = intval($_GET['id'] ?? 0);

$res = mysqli_query($mysqli, "
    SELECT q.*, c.name AS customer_name, c.customer_no, c.address, c.pic
    FROM quotations q
    LEFT JOIN customers c ON q.customer_id = c.id
    WHERE q.id = $id
");

if(!$quote = mysqli_fetch_assoc($res)){
    echo "Quotation not found";
    exit;
}

$items = mysqli_query($mysqli, "
    SELECT 
        item_no,
        description_quot AS description,
        qty,
        satuan_quot AS satuan,
        unit_price,
        amount
    FROM quotation_items
    WHERE quotation_id = $id
    ORDER BY item_no ASC
");


// Fungsi helper untuk memformat angka menjadi format Rupiah (Rp)
function formatRupiah($number) {
    return 'Rp ' . number_format($number, 0, ',', '.');
}


// Cek apakah ada diskon
$has_discount = isset($quote['discount']) && $quote['discount'] > 0;
// Fungsi helper untuk menghilangkan line break dari teks
function removeLineBreaks($text) {
    if (empty($text)) {
        return '';
    }
    // Ganti semua jenis line break dengan spasi
    $text = str_replace(["\r\n", "\r", "\n"], ' ', $text);
    // Hapus spasi berlebih yang mungkin muncul
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

$quotation_prefix = substr($quote['quotation_no'], 0, 3);
$file_name = $quotation_prefix . ' Quotation ' . $quote['customer_name'];
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo htmlspecialchars($file_name); ?></title>

<style>

body {
    font-family: Arial, sans-serif;
    font-size: 12px;
    margin: 20px 40px;
}

@page {
    size: A4;
    margin: 1mm 1mm 1mm 1mm;
}

/* === Header Container BARU (Kop Surat) === */
.header-container {
    margin-bottom: 10px;
    border-top: 1px solid black; /* Garis di atas */
    border-bottom: 1px solid black; /* Garis di bawah */
    padding-top: 5px;
    padding-bottom: 1px;
    display: table;
    width: 100%;
}

.header-logo {
    display: table-cell;
    vertical-align: middle;
    width: 15%;
    padding-right: 15px;
}

.header-logo img {
    width: 100px;
    height: auto;
}
.header-content {
    display: table-cell;
    vertical-align: top;
    width: 85%;
    padding-left: 10px;
}

.header-content h3 {
    font-weight: bold;
    font-size: 16px;
    margin: 0;
    padding-bottom: 5px;
}

.header-content p {
    margin: 0;
    font-size: 12px;
    line-height: 1.4;
}

.header-text {
    font-weight: bold;
    text-align: center;
    font-size: 14px;
}

.small-text {
    font-size: 12px;
    text-align: center;
}

.title {
    text-align: center;
    font-size: 16px;
    margin: 10px 0 20px 0;
    text-decoration: underline;
    font-weight: bold;
}

/* === Info Table === */
.info-table {
    width: 100%;
    margin-bottom: 12px;
}

.info-table td {
    vertical-align: top;
    padding: 3px 0;
    font-size: 12px;
}

/* === Items Table === */
table.items {
    border-collapse: collapse;
    width: 100%;
    margin-top: 10px;
}

table.items th, table.items td {
    border: 1px solid black;
    padding: 4px;
}

table.items th {
    background: #6bb0ffff;
    text-align: center;
}

/* === Totals Table === */
.total-table {
    width: 40%;
    float: right;
    margin-top: 5px;
    border-collapse: collapse;
}

.total-table td {
    border: 1px solid black;
    padding: 4px;
}
.signature-table {
    width: 100%;
    margin-top: 8px;
}

/* CSS untuk layout notes agar berjejer horizontal */
.notes-container {
    display: flex;
    justify-content: space-between; /* Untuk memberi jarak antara dua div */
    gap: 20px; /* Jarak antar kolom */
    margin-top: 20px;
}

.note-box-half {
    border: 1px solid black;
    padding: 10px;
    width: 48%; /* Mendapatkan hampir setengah lebar dengan sedikit gap */
    font-size: 12px;
    min-height: 80px; /* Agar tinggi kotak minimal sama */
    box-sizing: border-box; /* Pastikan padding dihitung dalam lebar */
}
/* Style untuk technical info table di dalam note-box-half */
.note-box-half table {
    width: 100%;
    border-collapse: collapse;
}
.note-box-half table td {
    padding: 2px 0;
    border: none; /* Hilangkan border pada tabel di dalam note-box-half */
    font-size: 12px;
}

/* Style untuk signature area */
.signature-cell {
    position: relative; /* Untuk menempatkan gambar di atas teks */
    text-align: center;
    padding-top: 5px; /* Ruang untuk tanda tangan */
}

.signature-stamp-img {
    position: absolute;
    bottom: 10px; /* Sesuaikan posisi vertikal cap */
    left: 50%;
    transform: translateX(-50%);
    width: 40px; /* Lebar cap */
    height: auto;
    opacity: 0.7; /* Transparansi cap */
    z-index: 1; /* Pastikan cap di belakang teks */
}

.signature-sign-img {
    position: absolute;
    bottom: 60px; /* Sesuaikan posisi vertikal tanda tangan */
    left: 50%;
    transform: translateX(-50%);
    width: 250px; /* Lebar tanda tangan */
    height: auto;
    z-index: 2; /* Pastikan tanda tangan di atas cap (jika bertumpuk) */
}

.signature-line {
    border-bottom: 1px solid black;
    width: 180%;
    margin: 5px auto;
}

/* === Delivery Info Style === */
.delivery-info {
    font-size: 12px;
    margin-top: 10px;
    margin-bottom: 10px;
}
.delivery-info td {
    padding-top: 2px;
    padding-bottom: 2px;
}
</style>

</head>
<body onload="window.print()">

<div class="header-container">
    <div class="header-logo">
        <img src="img/afshin2.png" alt="Logo">
    </div>

    <div class="header-content">
        <h3>CV. AFSHIN RAYA TEKNIK</h3>
        <p style="font-weight: bold;">
            Penyedia Sparepart Mesin Bubut dan Milling, Jasa Maintenance dan Kontruksi Gedung
        </p>
        <p>
            Kp. Ciketing, Jl. Kramat No. 75, RT. 004 RW. 011, Desa/Kelurahan Mustikajaya, Kecamatan Mustikajaya, Kota Bekasi, Jawa Barat, 17158
        </p>
        <p>
            Tlp : +62 896 1464 7011<br>
            Email : cvafshinrayateknik@gmail.com
        </p>
    </div>
</div>

<div class="title">QUOTATION</div>

<table class="info-table">
<tr>
    <td width="65%">
        <strong>Messrs,</strong><br>
        <?php echo htmlspecialchars($quote['customer_name']); ?><br>
        <?php echo nl2br(htmlspecialchars($quote['address'])); ?><br>
        Attn: <?php echo htmlspecialchars($quote['pic']); ?><br>
    </td>

    <td width="35%">
        <table>
            <tr>
                <td><strong>Date</strong></td>
                <td>: 
                    <?php
                    echo !empty($quote['date_quot'])
                        ? date("d-m-Y", strtotime($quote['date_quot']))
                        : '-';
                    ?>
                </td>
            </tr>
            <tr><td><strong>No.</strong></td><td>: <?php echo $quote['quotation_no']; ?></td></tr>
        </table>
    </td>
</tr>
</table>

    <table class="delivery-info">
    <tr>
        <td colspan="3">Dear</td>
    </tr>
    <tr>
        <td colspan="3">We have pleasure in Quotation, Parts, Repair Fee and/or Service Fee as follow :</td>
    </tr>
    <tr><td colspan="3"><div style="height: 5px;"></div></td></tr>
    <tr>
        <td style="width: 150px;">Place of Delivery</td>
        <td style="width: 10px;">:</td>
        <td><?php echo htmlspecialchars($quote['customer_name']); ?></td>
    </tr>
    <tr>
        <td>Time of Delivery</td>
        <td>:</td>
        <td>As Soon As Possible</td>
    </tr>
    <tr>
        <td>Term of Payment</td>
        <td>:</td>
        <td>30 Days After Invoice Send</td>
    </tr>
    <tr>
        <td>Validity</td>
        <td>:</td>
        <td>30 Days</td>
    </tr>
    <tr>
        <td>Remarks</td>
        <td>:</td>
        <td>-</td>
    </tr>
</table>

<table class="items">
<tr>
    <th>No</th>
    <th>Description</th>
    <th>Qty</th>
    <th>Satuan</th>
    <th>Unit Price</th>
    <th>Amount</th>
</tr>

<?php while($it = mysqli_fetch_assoc($items)): ?>
<tr>
    <td style="text-align:center;"><?php echo $it['item_no']; ?></td>
    <td><?php echo htmlspecialchars($it['description']); ?></td>
    <td style="text-align:center;"><?php echo $it['qty']; ?></td>
    <td style="text-align:center;"><?php echo htmlspecialchars($it['satuan']); ?></td>
    <td style="text-align:right;"><?php echo formatRupiah($it['unit_price']); ?></td>
    <td style="text-align:right;"><?php echo formatRupiah($it['amount']); ?></td>
</tr>
<?php endwhile; ?>
</table>

<table class="total-table">
<tr>
    <td>Subtotal</td>
    <td align="right"><?php echo formatRupiah($quote['subtotal']); ?></td>
</tr>

<?php if($has_discount): ?>
<tr class="discount-row">
    <td>Discount</td>
    <td align="right"><?php echo formatRupiah($quote['discount']); ?></td>
</tr>

<tr>
    <td>Subtotal after Discount</td>
    <td align="right"><?php 
        $subtotal_after_discount = $quote['subtotal'] - $quote['discount'];
        echo formatRupiah($subtotal_after_discount);
    ?></td>
</tr>
<?php endif; ?>

<tr>
    <td>PPN 12%</td>
    <td align="right"><?php 
        // Hitung PPN berdasarkan subtotal setelah diskon jika ada diskon
        if($has_discount) {
            $ppn_amount = ($subtotal_after_discount * 11) / 100;
        } else {
            $ppn_amount = $quote['ppn'];
        }
        echo formatRupiah($ppn_amount); 
    ?></td>
</tr>

<tr>
    <td><strong>Total</strong></td>
    <td align="right"><strong><?php 
        // Hitung total berdasarkan subtotal setelah diskon jika ada diskon
        if($has_discount) {
            $final_total = $subtotal_after_discount + $ppn_amount;
        } else {
            $final_total = $quote['total'];
        }
        echo formatRupiah($final_total); 
    ?></strong></td>
</tr>
</table>
<div style="clear: both;"></div>
    
<div class="notes-container">
    <div class="note-box-half">
        <strong>Note :</strong><br>
        <?php 
        // Contoh notes. Anda bisa mengambilnya dari database jika ada field khusus.
        // Untuk saat ini, saya menggunakan teks statis atau mengambil dari field 'note' jika tersedia
        $displayNote = "a. Price Include TAX<br>b. Warranty 3 Month<br>c. Warranty applies to the same problem";
        if (!empty($quote['note'])) {
            $displayNote = nl2br(htmlspecialchars($quote['note']));
        }
        echo $displayNote;
        ?>
    </div>

    <div class="note-box-half">
        <table>
            <tr><td style="width: 40%;">Control Model</td><td style="width: 5%;">:</td><td><?php echo htmlspecialchars($quote['control_model'] ?? '-'); ?></td></tr>
            <tr><td>Control Serial No</td><td>:</td><td>-</td></tr>
            <tr><td>MTB</td><td>:</td><td><?php echo htmlspecialchars($quote['mtb'] ?? '-'); ?></td></tr>
            <tr><td>Model</td><td>:</td><td>-</td></tr>
            <tr><td>Serial No</td><td>:</td><td>-</td></tr>
        </table>
    </div>
</div>

<table class="signature-table" style="width:100%;">
    <tr>
        <td style="width:50%; vertical-align: top; text-align: left; padding-left: 0; padding-top: 50px;">
            Awaiting your inquiry order,<br>
            Best Regards
        </td>
        <td style="width:50%; vertical-align: top; text-align: left; padding-left: 0; padding-top: 50px;">
            Please sign here and send back when agree
        </td>
    </tr>
    <tr>
        <td class="signature-cell" style="width:50%; vertical-align: top; padding-left: 0;">
            
            <img src="img/cap2.png" alt="Stamp" class="signature-stamp-img" style="bottom: 25px; left: 110px; width: 240px;">
            
            <div style="margin-top: 90px;"></div> 
            <span style="display: block; width: 150px; text-align: left; margin-left: 30px;"><u>Manisah</u></span>
            <span style="display: block; width: 150px; text-align: left; margin-left: 30px;">Direkrut</span>
        </td>
        
        <td class="signature-cell" style="width:50%; vertical-align: top; padding-left: 0;">
            <div style="height: 120px;"></div> 
            <span style="display: block; width: 250px; border-bottom: 1px solid black; margin-left: 0;"></span>
            <span style="display: block; width: 250px; text-align: left; margin-top: 2px;">Sign</span>
            <span style="display: block; width: 250px; text-align: left;">Position</span>
        </td>
    </tr>
</table>
    
<div style="margin-top: 20px;">
    <p style="font-weight: bold;">Please Transfer in Full Amount to CV Afshin Raya Teknik</p>
    <p style="font-weight: bold;">BANK MANDIRI A/C 167-00-0604327</p>
</div>
      
</body>
</html>