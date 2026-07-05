<?php
/**
 * Generate Quotation PDF Service
 * Menghasilkan file PDF dari data quotation dengan format persis quotations_print.php
 */

require_once 'db.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function generateQuotationPDF($quotation_id) {
    global $mysqli;
    
    $id = intval($quotation_id);
    
    // Fetch Quotation Data
    $res = mysqli_query($mysqli, "
        SELECT q.*, c.name AS customer_name, c.customer_no, c.address, c.pic
        FROM quotations q
        LEFT JOIN customers c ON q.customer_id = c.id
        WHERE q.id = $id
    ");
    
    if(!$quote = mysqli_fetch_assoc($res)) {
        return false;
    }
    
    // Fetch Items
    $items_res = mysqli_query($mysqli, "
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
    
    $items = [];
    while($row = mysqli_fetch_assoc($items_res)) {
        $items[] = $row;
    }

    // Helper functions
    function formatRupiah($number) {
        return 'Rp ' . number_format($number, 0, ',', '.');
    }
    
    $has_discount = isset($quote['discount']) && $quote['discount'] > 0;
    
    // Convert Logo & Stamp to Base64 for DomPDF consistency
   $logo_path = __DIR__ . '/img/afshin2.png';
$cap_path = __DIR__ . '/img/cap2.png';
    
    $logo_base64 = '';
if (file_exists($logo_path)) {

    $logo_data = file_get_contents($logo_path);
    $logo_base64 = 'data:image/' . pathinfo($logo_path, PATHINFO_EXTENSION) . ';base64,' . base64_encode($logo_data);
}

$cap_base64 = '';
if (extension_loaded('gd') && file_exists($cap_path)) {
    $cap_data = file_get_contents($cap_path);
    $cap_base64 = 'data:image/' . pathinfo($cap_path, PATHINFO_EXTENSION) . ';base64,' . base64_encode($cap_data);
}

    $quotation_prefix = substr($quote['quotation_no'], 0, 3);
    $file_name = $quote['quotation_no'] . ' Quotation ' . $quote['customer_name'];

    
    $html = '<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
body {
    font-family: Arial, sans-serif;
    font-size: 11px;
    margin: 10px;
}
@page {
    size: A4;
    margin: 1cm;
}
.header-container {
    margin-bottom: 10px;
    border-top: 1px solid black;
    border-bottom: 1px solid black;
    padding-top: 5px;
    padding-bottom: 1px;
    width: 100%;
}
.header-table {
    width: 100%;
    border-collapse: collapse;
}
.header-logo {
    width: 15%;
    vertical-align: middle;
}
.header-logo img {
    width: 90px;
}
.header-content {
    width: 85%;
    vertical-align: top;
    padding-left: 10px;
}
.header-content h3 {
    font-size: 14px;
    margin: 0;
    font-weight: bold;
}
.header-content p {
    margin: 2px 0;
    font-size: 10px;
    line-height: 1.2;
}
.title {
    text-align: center;
    font-size: 14px;
    margin: 10px 0;
    text-decoration: underline;
    font-weight: bold;
}
.info-table {
    width: 100%;
    margin-bottom: 10px;
}
.info-table td {
    vertical-align: top;
    font-size: 11px;
}
.delivery-info {
    width: 100%;
    font-size: 11px;
    margin-bottom: 10px;
}
table.items {
    border-collapse: collapse;
    width: 100%;
    margin-top: 5px;
}
table.items th, table.items td {
    border: 1px solid black;
    padding: 4px;
}
table.items th {
    background: #6bb0ffff;
    text-align: center;
}
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
.notes-container {
    width: 100%;
    margin-top: 15px;
    display: table;
    table-layout: fixed;
    border-collapse: collapse;
}
.note-box {
    border: 1px solid black;
    padding: 8px;
    display: table-cell;
    min-height: 70px;
    box-sizing: border-box;
    vertical-align: top;
}
.note-box-left {
    width: 50%;
    border-right: none;
}
.note-box-right {
    width: 50%;
}
.signature-table {
    width: 100%;
    margin-top: 20px;
}
.signature-cell {
    width: 48%;
    vertical-align: top;
    padding: 10px;
}
.signature-cell-left {
    text-align: left;
}
.signature-cell-right {
    text-align: left;
}
.stamp-img {
    position: absolute;
    top: -10px;
    left: 20px;
    width: 180px;
    z-index: -1;
    opacity: 0.7;
}
</style>
</head>
<body>

<div class="header-container">
    <table class="header-table">
        <tr>
            <td class="header-logo"><img src="' . $logo_base64 . '"></td>
            <td class="header-content">
                <h3>CV. AFSHIN RAYA TEKNIK</h3>
                <p><strong>Penyedia Sparepart Mesin Bubut dan Milling, Jasa Maintenance dan Kontruksi Gedung</strong></p>
                <p>Kp. Ciketing, Jl. Kramat No. 75, RT. 004 RW. 011, Mustikajaya, Kota Bekasi, Jawa Barat, 17158</p>
                <p>Tlp : +62 896 1464 7011 | Email : cvafshinrayateknik@gmail.com</p>
            </td>
        </tr>
    </table>
</div>

<div class="title">QUOTATION</div>

<table class="info-table">
    <tr>
        <td width="65%">
            <strong>Messrs,</strong><br>
            ' . htmlspecialchars($quote['customer_name']) . '<br>
            ' . nl2br(htmlspecialchars($quote['address'])) . '<br>
            Attn: ' . htmlspecialchars($quote['pic']) . '
        </td>
        <td width="35%">
            <table width="100%">
                <tr>
                    <td width="30%"><strong>Date</strong></td>
                    <td>: ' . ( !empty($quote['date_quot']) ? date("d-m-Y", strtotime($quote['date_quot'])) : '-' ) . '</td>
                </tr>
                <tr>
                    <td><strong>No.</strong></td>
                    <td>: ' . htmlspecialchars($quote['quotation_no']) . '</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="delivery-info">
    <tr><td colspan="3">Dear,</td></tr>
    <tr><td colspan="3">We have pleasure in Quotation, Parts, Repair Fee and/or Service Fee as follow :</td></tr>
    <tr><td width="120">Place of Delivery</td><td width="10">:</td><td>' . htmlspecialchars($quote['customer_name']) . '</td></tr>
    <tr><td>Time of Delivery</td><td>:</td><td>As Soon As Possible</td></tr>
    <tr><td>Term of Payment</td><td>:</td><td>30 Days After Invoice Send</td></tr>
    <tr><td>Validity</td><td>:</td><td>30 Days</td></tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th width="5%">No</th>
            <th width="45%">Description</th>
            <th width="10%">Qty</th>
            <th width="10%">Satuan</th>
            <th width="15%">Unit Price</th>
            <th width="15%">Amount</th>
        </tr>
    </thead>
    <tbody>';

    foreach($items as $it) {
        $html .= '<tr>
            <td align="center">' . $it['item_no'] . '</td>
            <td>' . htmlspecialchars($it['description_quot']) . '</td>
            <td align="center">' . $it['qty'] . '</td>
            <td align="center">' . htmlspecialchars($it['satuan_quot']) . '</td>
            <td align="right">' . formatRupiah($it['unit_price']) . '</td>
            <td align="right">' . formatRupiah($it['amount']) . '</td>
        </tr>';
    }

    $html .= '</tbody>
</table>

<table class="total-table">
    <tr>
        <td>Subtotal</td>
        <td align="right">' . formatRupiah($quote['subtotal']) . '</td>
    </tr>';

    if($has_discount) {
        $subtotal_after_discount = $quote['subtotal'] - $quote['discount'];
        $html .= '<tr><td>Discount</td><td align="right">' . formatRupiah($quote['discount']) . '</td></tr>
                  <tr><td>Subtotal after Discount</td><td align="right">' . formatRupiah($subtotal_after_discount) . '</td></tr>';
        $ppn_amount = ($subtotal_after_discount * 11) / 100;
        $final_total = $subtotal_after_discount + $ppn_amount;
    } else {
        $ppn_amount = $quote['ppn'];
        $final_total = $quote['total'];
    }

    $html .= '<tr>
        <td>PPN</td>
        <td align="right">' . formatRupiah($ppn_amount) . '</td>
    </tr>
    <tr style="background:#eee;">
        <td><strong>Total</strong></td>
        <td align="right"><strong>' . formatRupiah($final_total) . '</strong></td>
    </tr>
</table>

<div style="clear:both;"></div>

<div class="notes-container">
    <div class="note-box note-box-left">
        <strong>Note :</strong><br>
        ' . ( !empty($quote['note']) ? nl2br(htmlspecialchars($quote['note'])) : "a. Price Include TAX<br>b. Warranty 3 Month<br>c. Warranty applies to the same problem" ) . '
    </div>
    <div class="note-box note-box-right">
        <table width="100%" style="font-size:10px;">
            <tr><td width="40%">Control Model</td><td width="5%">:</td><td>' . htmlspecialchars($quote['control_model'] ?? '-') . '</td></tr>
            <tr><td>Control Serial No</td><td>:</td><td>-</td></tr>
            <tr><td>MTB</td><td>:</td><td>' . htmlspecialchars($quote['mtb'] ?? '-') . '</td></tr>
            <tr><td>Model</td><td>:</td><td>-</td></tr>
            <tr><td>Serial No</td><td>:</td><td>-</td></tr>
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
                <img src="' . $cap_base64 . '" style="position:absolute; top:-25px; left:10px; width:220px; opacity:0.8; z-index:-1;">
            </div>
            <p style="margin:0 0 2px 0; position:relative; z-index:1;"><u>Manisah</u></p>
            <p style="margin:0; position:relative; z-index:1;">Direktur</p>
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

<div style="margin-top:15px; font-weight:bold; font-size:10px;">
    Please Transfer in Full Amount to CV Afshin Raya Teknik<br>
    BANK MANDIRI A/C 167-00-0604327
</div>

</body>
</html>';

    // Generate PDF using DomPDF
  try {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false); // <-- ganti true ke false
    $options->set('defaultFont', 'Arial');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $temp_dir = __DIR__ . '/tmp';
    if (!is_dir($temp_dir)) mkdir($temp_dir, 0755, true);
    
    $safe_name = preg_replace('/[\/\\\\:*?"<>|]/', '', $file_name);
$pdf_file = $temp_dir . '/' . $safe_name . '.pdf';
    
    file_put_contents($pdf_file, $dompdf->output());

    return $pdf_file;
} catch (Exception $e) {
    file_put_contents(__DIR__ . '/pdf_error.log', 
        date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n",
        FILE_APPEND
    );
    return false;
}
}
?>
