<?php
require_once 'db.php';

// Fungsi helper untuk memformat angka menjadi format Rupiah (Rp)
function formatRupiah($number) {
    return 'Rp ' . number_format($number, 0, ',', '.');
}

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
        description_quot,
        qty,
        satuan_quot,
        unit_price,
        amount
    FROM quotation_items
    WHERE quotation_id = $id
    ORDER BY item_no ASC
");

// Convert Logo & Stamp to Base64 (Biar pasti muncul di browser)
$logo_path = 'img/afshin2.png';
$cap_path = 'img/cap2.png';
$logo_base64 = '';
if (file_exists($logo_path)) {
    $logo_base64 = 'data:image/' . pathinfo($logo_path, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($logo_path));
}
$cap_base64 = '';
if (file_exists($cap_path)) {
    $cap_base64 = 'data:image/' . pathinfo($cap_path, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($cap_path));
}

$has_discount = isset($quote['discount']) && $quote['discount'] > 0;
$quotation_prefix = substr($quote['quotation_no'], 0, 3);
$file_name = $quotation_prefix . ' Quotation ' . $quote['customer_name'];
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo htmlspecialchars($file_name); ?></title>
<style>

@page {
    size: A4;
    margin: 1cm;
}
body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px 40px; background: #f4f4f4; }
.container { background: #fff; max-width: 800px; margin: 0 auto; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); position: relative; }
@media print { body { margin: 0; background: #fff; } .container { box-shadow: none; max-width: 100%; } .no-print { display: none; } }
.header-container { margin-bottom: 10px; border-top: 1px solid black; border-bottom: 1px solid black; padding-top: 5px; padding-bottom: 1px; display: table; width: 100%; }
.header-logo { display: table-cell; vertical-align: middle; width: 15%; padding-right: 15px; }
.header-logo img { width: 100px; height: auto; }
.header-content { display: table-cell; vertical-align: top; width: 85%; padding-left: 10px; }
.header-content h3 { font-weight: bold; font-size: 16px; margin: 0; padding-bottom: 5px; }
.header-content p { margin: 0; font-size: 12px; line-height: 1.4; }
.title { text-align: center; font-size: 16px; margin: 10px 0 20px 0; text-decoration: underline; font-weight: bold; }
.info-table { width: 100%; margin-bottom: 12px; }
.info-table td { vertical-align: top; padding: 3px 0; font-size: 12px; }
table.items { border-collapse: collapse; width: 100%; margin-top: 10px; }
table.items th, table.items td { border: 1px solid black; padding: 4px; }
table.items th { background: #6bb0ffff; text-align: center; }
.total-table { width: 40%; float: right; margin-top: 5px; border-collapse: collapse; }
.total-table td { border: 1px solid black; padding: 4px; }
.notes-container { width: 100%; margin-top: 15px; overflow: hidden; }
.note-box { border: 1px solid black; padding: 8px; width: 48%; float: left; min-height: 70px; box-sizing: border-box; }
.note-box:first-child { margin-right: 4%; }
.signature-table { width: 100%; margin-top: 8px; }
.signature-cell { width: 48%; vertical-align: top; padding: 10px; }
.signature-cell-left { text-align: left; }
.signature-cell-right { text-align: left; }
/* STAMP STYLE FIXED FOR WEB */
.stamp-img { position: absolute; top: -20px; left: 0px; width: 220px; opacity: 0.8; z-index: 1; pointer-events: none; }
.delivery-info { font-size: 12px; margin-top: 10px; margin-bottom: 10px; }
.delivery-info td { padding-top: 2px; padding-bottom: 2px; }
.btn-print { background: #007bff; color: #fff; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin-bottom: 20px; }
</style>
</head>
<body>
<div class="container">
    <div class="no-print">
        

    <div class="header-container">
        <div class="header-logo">
            <img src="<?php echo $logo_base64; ?>" alt="Logo">
        </div>
        <div class="header-content">
            <h3>CV. AFSHIN RAYA TEKNIK</h3>
            <p style="font-weight: bold;">Penyedia Sparepart Mesin Bubut dan Milling, Jasa Maintenance dan Kontruksi Gedung</p>
            <p>Kp. Ciketing, Jl. Kramat No. 75, RT. 004 RW. 011, Mustikajaya, Kota Bekasi, 17158</p>
            <p>Tlp : +62 896 1464 7011 | Email : cvafshinrayateknik@gmail.com</p>
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
            <table width="100%">
                <tr><td width="30%"><strong>Date</strong></td><td>: <?php echo !empty($quote['date_quot']) ? date("d-m-Y", strtotime($quote['date_quot'])) : '-'; ?></td></tr>
                <tr><td><strong>No.</strong></td><td>: <?php echo $quote['quotation_no']; ?></td></tr>
            </table>
        </td>
    </tr>
    </table>

    <table class="delivery-info">
        <tr><td colspan="3">Dear, We have pleasure in Quotation, Parts, Repair Fee and/or Service Fee as follow :</td></tr>
        <tr><td style="width: 150px;">Place of Delivery</td><td style="width: 10px;">:</td><td><?php echo htmlspecialchars($quote['customer_name']); ?></td></tr>
        <tr><td>Time of Delivery</td><td>:</td><td>As Soon As Possible</td></tr>
        <tr><td>Term of Payment</td><td>:</td><td>30 Days After Invoice Send</td></tr>
    </table>

    <table class="items">
    <tr>
        <th>No</th><th>Description</th><th>Qty</th><th>Satuan</th><th>Unit Price</th><th>Amount</th>
    </tr>
    <?php while($it = mysqli_fetch_assoc($items)): ?>
    <tr>
        <td style="text-align:center;"><?php echo $it['item_no']; ?></td>
        <td><?php echo htmlspecialchars($it['description_quot']); ?></td>
        <td style="text-align:center;"><?php echo $it['qty']; ?></td>
        <td style="text-align:center;"><?php echo htmlspecialchars($it['satuan_quot']); ?></td>
        <td style="text-align:right;"><?php echo formatRupiah($it['unit_price']); ?></td>
        <td style="text-align:right;"><?php echo formatRupiah($it['amount']); ?></td>
    </tr>
    <?php endwhile; ?>
    </table>

    <table class="total-table">
    <tr><td>Subtotal</td><td align="right"><?php echo formatRupiah($quote['subtotal']); ?></td></tr>
    <?php if($has_discount): ?>
    <tr><td>Discount</td><td align="right"><?php echo formatRupiah($quote['discount']); ?></td></tr>
    <?php endif; ?>
    <tr><td>PPN 12%</td><td align="right"><?php echo formatRupiah($quote['ppn']); ?></td></tr>
    <tr><td><strong>Total</strong></td><td align="right"><strong><?php echo formatRupiah($quote['total']); ?></strong></td></tr>
    </table>
    <div style="clear: both;"></div>

    <div class="notes-container">
        <div class="note-box">
            <strong>Note :</strong><br>
            <?php echo nl2br(htmlspecialchars($quote['note'] ?: "a. Price Include TAX\nb. Warranty 3 Month")); ?>
        </div>
        <div class="note-box">
            <table width="100%" style="font-size:10px;">
                <tr><td width="40%">Control Model</td><td>: <?php echo htmlspecialchars($quote['control_model'] ?? '-'); ?></td></tr>
                <tr><td>MTB</td><td>: <?php echo htmlspecialchars($quote['mtb'] ?? '-'); ?></td></tr>
            </table>
        </div>
    </div>

    <div style="clear:both;"></div>

    <table class="signature-table">
        <tr>
            <td class="signature-cell signature-cell-left">
                <p style="margin:0;">Awaiting your inquiry order,</p>
                <p style="margin:0;">Best Regards</p>
                <div style="position:relative; height:80px; margin:0;">
                    <img src="<?php echo $cap_base64; ?>" class="stamp-img">
                </div>
                <p style="margin:0 0 2px 0; position:relative; z-index:2;"><u>Manisah</u></p>
                <p style="margin:0; position:relative; z-index:2;">Direktur</p>
            </td>
            <td class="signature-cell signature-cell-right">
                <p style="margin:0;">Please sign here and send back when agree</p>
                <div style="height:100px; margin-top:10px;"></div>
                <div style="width:100%; border-top:1px solid black; padding-top:5px;">
                    <p style="margin:2px 0;">Sign</p>
                    <p style="margin:2px 0;">Position</p>
                </div>
            </td>
        </tr>
    </table>

    <div style="margin-top: 20px;">
        <p style="font-weight: bold;">Please Transfer in Full Amount to CV Afshin Raya Teknik</p>
        <p style="font-weight: bold;">BANK MANDIRI A/C 167-00-0604327</p>
    </div>
</div>
</body>
</html>