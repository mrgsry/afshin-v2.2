<?php
// =========================================================================
// PHP LOGIC (Backend Handling)
// =========================================================================

// Asumsi 'functions.php' dan 'require_login()' sudah didefinisikan
require_once 'functions.php';
// require_login(); 

// ASUMSI: $mysqli sudah tersedia dan koneksi database sudah terjalin
// Di sini, kita asumsikan $mysqli sudah terinisialisasi di 'functions.php' atau file yang menyertainya.

$id = intval($_GET['id'] ?? 0);

$res = mysqli_query($mysqli, "
    SELECT i.*, c.name AS customer_name, c.customer_no, c.address 
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    WHERE i.id = $id
");

if(!$inv = mysqli_fetch_assoc($res)){
    echo "Invoice not found";
    exit;
}

$items_res = mysqli_query($mysqli, "
    SELECT item_no, description, qty, satuan, unit_price, amount
    FROM invoice_items 
    WHERE invoice_id = $id 
    ORDER BY item_no ASC
");

// ASUMSI: Fungsi terbilang() ada. Jika tidak ada, gunakan dummy (seperti di bawah)
if (!function_exists('terbilang')) {
    function terbilang($number) {
        // Implementasi dummy atau ganti dengan library terbilang Anda yang sebenarnya
        $formatter = new NumberFormatter('id_ID', NumberFormatter::SPELLOUT);
        $terbilang = $formatter->format($number);
        return $terbilang ?: 'Nol';
    }
}

// Fungsi helper untuk memformat angka menjadi format Rupiah (Rp) tanpa desimal
function formatRupiah($number) {
    // Menggunakan 0 desimal
    return 'Rp ' . number_format($number, 0, ',', '.');
}

// Data Invoice untuk dicetak
$subtotal = floatval($inv['subtotal']);
$discount = floatval($inv['discount'] ?? 0);
$pph_value_from_db = floatval($inv['pph']); 
$ppn_value_from_db = floatval($inv['ppn']); 
$total = floatval($inv['total']);
$terbilang = ucwords(trim(terbilang($total))) . " Rupiah";
?>

<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Print Invoice - <?php echo $inv['invoice_no']; ?></title>


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

/* === Kop Surat (Kop Surat) === */
.header-container {
    margin-bottom: 10px;
    border-bottom: 1px solid black; 
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


.title {
    text-align: center;
    font-size: 16px;
    margin: 10px 0 20px 0;
    text-decoration: underline;
    font-weight: bold;
}

/* === Tabel Header Info Tambahan (Tanggal, J.Tempo, No.Faktur, PO) === */
table.invoice-info-header {
    border-collapse: collapse;
    width: 100%;
    margin-bottom: 15px;
}
table.invoice-info-header th, table.invoice-info-header td {
    border: 1px solid black;
    padding: 8px;
    text-align: left;
    vertical-align: top;
    font-size: 12px; 
}
table.invoice-info-header th {
    background: #7ba6ceff;
    font-weight: bold;
    text-align: center;
}
.tagihan-kepada {
    background: #e0e0e0; 
    font-weight: bold;
    text-align: left !important;
}

/* === Tabel Item === */
table.items {
    border-collapse: collapse;
    width: 100%;
    margin-top: 10px;
}

table.items th, table.items td {
    border: 1px solid black;
    padding: 6px;
    font-size: 12px; 
}

table.items th {
    background: #7ba6ceff;
    text-align: center;
}

/* 🚀 CSS UNTUK RINGKASAN TOTAL DAN NOTE 🚀 */
.total-table-container {
    width: 100%;
    margin-top: 10px;
}

.total-table {
    width: 300px; /* Lebar tabel total di kanan */
    float: right;
    border-collapse: collapse;
}

.total-table td {
    padding: 2px 5px;
    font-size: 12px; 
    border: none;
    line-height: 1.5;
}

.total-table td.label {
    text-align: right;
    width: 40%;
}
.total-table td.currency {
    text-align: center;
    width: 10%;
}
.total-table td.value {
    text-align: right;
    width: 50%;
}

.total-table .row-total td {
    border-top: 1px solid black;
    border-bottom: 3px ; 
    padding-top: 5px;
    padding-bottom: 5px;
}

.total-table .row-ppn td, .total-table .row-subtotal td {
    padding-bottom: 0;
}

/* Terbilang dan Note Pembayaran */
.terbilang-note-container {
    width: 100%;
    clear: both; 
    border-top: 1px solid black;
    padding-top: 8px;
    padding-bottom: 8px;
}

.terbilang-text {
    font-size: 12px;
    font-weight: bold;
    line-height: 1.5;
    margin-bottom: 10px;
}

.payment-info {
    font-size: 12px;
}

.payment-info table {
    border-collapse: collapse;
    width: auto; 
    margin-top: 5px;
}
.payment-info table td {
    padding: 2px 5px 2px 0;
    border: none;
    font-size: 12px;
}

/* === Style untuk Area Tanda Tangan === */
.signature-table {
    width: 100%;
    margin-top: 40px; 
}
.signature-table td {
    padding: 10px 0;
    width: 50%;
    vertical-align: top;
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
<div class="title">INVOICE</div>

<div style="text-align: center; margin-top: -15px; margin-bottom: 15px; font-size: 14px;">
    <?php echo $inv['invoice_no']; ?>
</div>

<table class="invoice-info-header">
<thead>
    <tr>
        <th width="35%" class="tagihan-kepada">Tagihan Kepada</th>
        <th width="15%" style="text-align: center;">Tanggal</th>
        <th width="20%" style="text-align: center;">Jatuh Tempo</th>
        <th width="20%" style="text-align: center;">Nomor Faktur</th>
        <th width="10%" style="text-align: center;">Nomor PO</th>
    </tr>
</thead>
<tbody>
    <tr>
        <td>
            <strong><?php echo htmlspecialchars($inv['customer_name']); ?></strong><br>
            <?php echo nl2br(htmlspecialchars($inv['address'])); ?><br>
        </td>
        <td align="center"><?php echo date("d M Y", strtotime($inv['created_at'])); ?></td>
        <td align="center"><?php echo date("d F Y", strtotime($inv['date_jatuh_tempo'] ?? $inv['created_at'] . ' +1 month')); ?></td>
        <td align="center"><?php echo htmlspecialchars($inv['faktur_inv'] ?? '-'); ?></td>
        <td align="center"><?php echo htmlspecialchars($inv['po_number'] ?? '-'); ?></td>
    </tr>
</tbody>
</table>

<table class="items">
<tr>
    <th>No</th>
    <th>Item Description</th>
    <th>Qty</th>
    <th>Unit</th>
    <th>Unit Price</th>
    <th>Amount</th>
</tr>

<?php mysqli_data_seek($items_res, 0); ?>
<?php while($it = mysqli_fetch_assoc($items_res)): ?>
<tr>
    <td align="center"><?php echo $it['item_no']; ?></td>
    <td><?php echo htmlspecialchars($it['description']); ?></td>
    <td align="center"><?php echo $it['qty']; ?></td>
    <td align="center"><?php echo htmlspecialchars($it['satuan']); ?></td>
    <td align="right"><?php echo formatRupiah($it['unit_price']); ?></td>
    <td align="right"><?php echo formatRupiah($it['amount']); ?></td>
</tr>
<?php endwhile; ?>

</table>

<div class="total-table-container">
    <table class="total-table">
        <tr>
            <td class="label">Sub Total</td>
            <td class="currency">Rp</td>
            <td class="value"><?php echo number_format($subtotal, 0, ',', '.'); ?></td>
        </tr>
        <?php if ($discount > 0): ?>
<tr>
    <td class="label">Diskon</td>
    <td class="currency">Rp</td>
    <td class="value">-<?php echo number_format($discount, 0, ',', '.'); ?></td>
</tr>
<?php endif; ?>
        <tr>
            <td class="label">PPN</td>
            <td class="currency">Rp</td>
            <td class="value"><?php echo number_format($ppn_value_from_db, 0, ',', '.'); ?></td>
        </tr>

        <?php if ($pph_value_from_db > 0): ?>
        <tr>
            <td class="label">PPh</td>
            <td class="currency">Rp</td>
            <td class="value"><?php echo number_format($pph_value_from_db, 0, ',', '.'); ?></td>
        </tr>
        <?php endif; ?>

        <tr class="row-total">
            <td class="label"><strong>Total</strong></td>
            <td class="currency"><strong>Rp</strong></td>
            <td class="value">
                <strong><?php echo number_format($total, 0, ',', '.'); ?></strong>
            </td>
        </tr>
    </table>
</div>

<div class="clear-float"></div>

<div class="terbilang-note-container">
    
    <div class="terbilang-text">
        Terbilang : <?php echo $terbilang; ?>
    </div>
    
    <div class="payment-info">
        Pembayaran dapat dilakukan dengan metode transfer ke :
        <table style="margin-top: 5px;">
            <tr>
                <td>Nama Bank</td>
                <td>:</td>
                <td>Mandiri</td>
            </tr>
            <tr>
                <td>No. Rekening</td>
                <td>:</td>
                <td>167-00-0604327-6</td>
            </tr>
            <tr>
                <td>Atas Nama</td>
                <td>:</td>
                <td>CV AFSHIN RAYA TEKNIK</td>
            </tr>
        </table>
    </div>
</div>

<table class="signature-table">
    <tr>
        <td style="text-align: right; padding-left: 0; padding-top: 50px;">
            Hormat kami,<br>
            CV. AFSHIN RAYA TEKNIK<br>
            <div style="height: 100px;"></div>
            ___________________________<br>
            Direktur, <br>
            Manisah
        </td>
    </tr>
</table>


</body>
</html>