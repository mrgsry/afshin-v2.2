<?php
require_once 'functions.php';
require_once 'employee_signature.php';
require_module_access('payslip');

$id = (int) ($_GET['id'] ?? 0);
$payslip = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT p.*, e.signature_path AS employee_signature_path FROM payslips p LEFT JOIN employees e ON e.id = p.employee_id WHERE p.id = $id"));
if (!$payslip) {
    exit('Slip gaji tidak ditemukan.');
}
$invoiceDetails = mysqli_query($mysqli, "SELECT invoice_no, customer_name, po_number FROM payslip_invoices WHERE payslip_id = $id ORDER BY id ASC");
$invoiceCount = (int) ($payslip['invoice_count'] ?? mysqli_num_rows($invoiceDetails));
$ratePerInvoice = (float) ($payslip['rate_per_invoice'] ?? 350000);
$grossSalary = (float) ($payslip['gross_salary'] ?? $payslip['net_salary']);
$pph21Amount = (float) ($payslip['pph21_amount'] ?? 0);
$salaryMethod = ($payslip['salary_method'] ?? 'invoice') === 'custom' ? 'custom' : 'invoice';
$employeeSignature = (!empty($payslip['employee_signature_path']) && is_file(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $payslip['employee_signature_path'])))
    ? $payslip['employee_signature_path']
    : 'img/ttd-yussi.png';

function formatPayslipRupiah($amount) {
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Slip Gaji - <?= htmlspecialchars($payslip['payslip_no']) ?></title>
    <style>
    body {
        font-family: Arial, sans-serif;
        font-size: 12px;
        margin: 20px 40px;
        color: #000;
    }

    @page {
        size: A4;
        margin: 1mm;
    }

    .header-container {
        margin-bottom: 10px;
        border-top: 1px solid black;
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
        margin: 20px 0 4px;
        text-decoration: underline;
        font-weight: bold;
    }

    .document-no {
        text-align: center;
        margin-bottom: 18px;
    }

    .info-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
    }

    .info-table td {
        padding: 4px 0;
        vertical-align: top;
    }

    .label {
        width: 150px;
    }

    .salary-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }

    .salary-table th,
    .salary-table td {
        border: 1px solid black;
        padding: 8px;
    }

    .salary-table th {
        background: #6bb0ff;
        text-align: center;
    }

    .salary-table .amount {
        text-align: right;
        font-weight: bold;
    }

    .work-title {
        margin: 18px 0 8px;
        font-weight: bold;
    }

    .invoice-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14px;
    }

    .invoice-table th,
    .invoice-table td {
        border: 1px solid black;
        padding: 6px;
    }

    .invoice-table th {
        background: #6bb0ff;
        text-align: center;
    }

    .summary-table {
        width: 360px;
        margin-left: auto;
        border-collapse: collapse;
    }

    .summary-table td {
        padding: 4px 0;
    }

    .summary-table .label {
        width: 220px;
    }

    .summary-table .value {
        text-align: right;
        font-weight: bold;
    }

    .summary-table .top-border td {
        border-top: 1px solid black;
        padding-top: 8px;
    }

    .notes {
        margin-top: 14px;
        border-top: 1px solid black;
        padding-top: 10px;
        line-height: 1.5;
    }

    .signature-table {
        width: 100%;
        margin-top: 48px;
        border-collapse: collapse;
    }

    .signature-cell {
        width: 50%;
        vertical-align: top;
        padding-top: 10px;
        text-align: left;
    }

    /* Wrapper that holds both the intro line (Diterima oleh, / Hormat
       kami, ...) and the name/jabatan lines below it. Using the same
       structure + same spacing on both sides is what makes the two
       signatures line up (sejajar) with each other. */
    .signature-block {
        position: relative;
        display: inline-block;
        padding-top: 4px;
    }

    .signature-block .intro-line {
        position: relative;
        z-index: 1;
        /* Fixed height (~2 lines) so the 1-line "Diterima oleh," column
           and the 2-line "Hormat kami, CV. ..." column both take up the
           same vertical space before the name lines start below. */
        min-height: 32px;
    }

    .signature-block .name-lines {
        position: relative;
        z-index: 1;
        margin-top: 78px;
    }

    .signature-block .name-lines span {
        display: block;
        width: 170px;
        text-align: left;
    }

    /* Stamp sits on top (higher z-index) and semi-transparent so the
       "CV. AFSHIN RAYA TEKNIK" line and the director's name both show
       through it, exactly like a real ink stamp overlapping printed text.
       Moved down and enlarged so it also reaches/overlaps "Manisah". */
    .signature-stamp-img {
        position: absolute;
        top: 22px;
        left: 50%;
        transform: translateX(-50%);
        width: 250px;
        height: auto;
        opacity: 0.75;
        z-index: 2;
        pointer-events: none;
    }

    /* Employee signature (ttd) image — same positioning approach as the
       company stamp above: absolute, overlapping the intro line, sitting
       just above the name/jabatan lines. */
    .signature-ttd-img {
        position: absolute;
        top: 22px;
        left: 50%;
        transform: translateX(-50%);
        width: 150px;
        height: auto;
        z-index: 2;
        pointer-events: none;
    }
    </style>
</head>

<body onload="window.print()">
    <div class="header-container">
        <div class="header-logo"><img src="img/afshin2.png" alt="Logo"></div>
        <div class="header-content">
            <h3>CV. AFSHIN RAYA TEKNIK</h3>
            <p style="font-weight: bold;">Penyedia Sparepart Mesin Bubut dan Milling, Jasa Maintenance dan Kontruksi
                Gedung</p>
            <p>Kp. Ciketing, Jl. Kramat No. 75, RT. 004 RW. 011, Desa/Kelurahan Mustikajaya, Kecamatan Mustikajaya, Kota
                Bekasi, Jawa Barat, 17158</p>
            <p>Tlp : +62 896 1464 7011<br>Email : cvafshinrayateknik@gmail.com</p>
        </div>
    </div>

    <div class="title">SLIP GAJI</div>
    <div class="document-no"><?= htmlspecialchars($payslip['payslip_no']) ?></div>

    <table class="info-table">
        <tr>
            <td class="label">Periode Gaji</td>
            <td>: <?= date('F Y', strtotime($payslip['salary_period'])) ?></td>
            <td class="label">Tanggal Terbit</td>
            <td>: <?= date('d F Y', strtotime($payslip['issued_date'])) ?></td>
        </tr>
        <tr>
            <td>Nomor Karyawan</td>
            <td>: <?= htmlspecialchars($payslip['employee_no']) ?></td>
            <td>Nama Karyawan</td>
            <td>: <?= htmlspecialchars($payslip['employee_name']) ?></td>
        </tr>
        <tr>
            <td>Level / Jabatan</td>
            <td colspan="3">: <?= htmlspecialchars($payslip['employee_level']) ?></td>
        </tr>
    </table>

    <div class="work-title">RINCIAN PEKERJAAN &amp; UPAH:</div>
    <?php if ($salaryMethod === 'custom'): ?>
    <div style="border-top: 1px solid black; padding-top: 12px;">1. <?= htmlspecialchars($payslip['description'] ?: 'Gaji custom') ?></div>
    <?php else: ?>
    <div style="border-top: 1px solid black; padding-top: 12px;">1. Pemrosesan Tagihan/Invoice Masa
        <?= date('F Y', strtotime($payslip['salary_period'])) ?></div>
    <table class="invoice-table">
        <tr>
            <th style="width: 8%;">No.</th>
            <th>Nomor Invoice</th>
            <th>Nama PT</th>
            <th>No. PO</th>
            <th style="width: 18%;">Tarif / Upah Invoice</th>
        </tr>
        <?php $no = 1; while ($invoice = mysqli_fetch_assoc($invoiceDetails)): ?>
        <tr>
            <td style="text-align: center;"><?= $no++ ?></td>
            <td><?= htmlspecialchars($invoice['invoice_no']) ?></td>
            <td><?= htmlspecialchars($invoice['customer_name']) ?></td>
            <td><?= htmlspecialchars($invoice['po_number'] ?: '-') ?></td>
            <td style="text-align: right;"><?= formatPayslipRupiah($ratePerInvoice) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <div style="margin: 4px 0 18px 18px; line-height: 1.6;">- Jumlah Invoice Selesai : <?= $invoiceCount ?> Invoice<br>-
        Tarif per Invoice&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; : <?= formatPayslipRupiah($ratePerInvoice) ?></div>
    <?php endif; ?>
    <table class="summary-table">
        <tr class="top-border">
            <td class="label"><strong>TOTAL UPAH BRUTO</strong></td>
            <td class="value"><?= formatPayslipRupiah($grossSalary) ?></td>
        </tr>
        <tr>
            <td class="label">Potongan PPh 21 (TER 0%)</td>
            <td class="value"><?= formatPayslipRupiah($pph21Amount) ?></td>
        </tr>
        <tr class="top-border">
            <td class="label"><strong>TOTAL DITERIMA (NETO)</strong></td>
            <td class="value"><?= formatPayslipRupiah($payslip['net_salary']) ?></td>
        </tr>
    </table>
    <div class="notes"><strong>Terbilang:</strong>
        <?= htmlspecialchars(trim(terbilang((int) round($payslip['net_salary']))) . ' Rupiah') ?><?php if (!empty($payslip['description'])): ?><br><br><strong>Deskripsi:</strong><br><?= nl2br(htmlspecialchars($payslip['description'])) ?><?php endif; ?>
    </div>

    <table class="signature-table">
        <tr>
            <td class="signature-cell">
                <div class="signature-block">
                    <div class="intro-line">Diterima oleh,</div>
                    <img src="<?= htmlspecialchars($employeeSignature) ?>" alt="Tanda Tangan <?= htmlspecialchars($payslip['employee_name']) ?>" class="signature-ttd-img">
                    <div class="name-lines">
                        <span><u><?= htmlspecialchars($payslip['employee_name']) ?></u></span>
                        <span>Karyawan</span>
                    </div>
                </div>
            </td>
            <td class="signature-cell">
                <div class="signature-block">
                    <div class="intro-line">Hormat kami,<br>CV. AFSHIN RAYA TEKNIK</div>
                    <img src="img/cap2.png" alt="Cap" class="signature-stamp-img">
                    <div class="name-lines">
                        <span><u>Manisah</u></span>
                        <span>Direktur</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>
</body>

</html>