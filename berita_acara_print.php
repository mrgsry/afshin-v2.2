<?php
// =========================================================================
// berita_acara_print.php - Print Berita Acara Serah Terima
// =========================================================================

require_once 'functions.php';

// Data Default Perusahaan
$company_name = 'CV. AFSHIN RAYA TEKNIK';
$company_slogan = 'Penyedia Sparepart Mesin Bubut dan Milling, Jasa Maintenance dan Kontruksi Gedung';
$company_address = 'Kp. Ciketing, Jl. Kramat No. 75, RT. 004 RW. 011, Desa/Kelurahan Mustikajaya, Kecamatan Mustikajaya, Kota Bekasi, Jawa Barat, 17158';
$company_phone = 'Tlp : +62 896 1464 7011';
$company_email = 'Email : cvafshinrayateknik@gmail.com';

// Inisialisasi variabel dengan default values
$nomor_ba = '025/BAST-ART/XII/2025';
$tanggal_ba = date('Y-m-d');
$customer_name = 'PT. JTEKT COLUMN SYSTEMS INDONESIA';
$customer_alamat = '';
$lokasi = 'PT. JTEKT COLUMN SYSTEMS INDONESIA';
$pekerjaan = 'PR#02581 Mte [C8888] Repair Modif Gear Turret CNC Takamaz';
$po_number = '1788/JCSID/11/25';
$pelaksana = 'CV. Afshin Raya Teknik';
$prod_code = '-';
$ship_by = '';
$items = [];

// --- LOGIKA PENGAMBILAN DATA ---

// Mode 1: Data dari database (berdasarkan ID)
if (isset($_GET['id'])) {
    $document_id = intval($_GET['id']);
    
    $query = "SELECT * FROM berita_acara WHERE id = ?";
    $stmt = mysqli_prepare($mysqli, $query);
    mysqli_stmt_bind_param($stmt, "i", $document_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        // Data dari header
        $nomor_ba = $row['nomor_ba'];
        $tanggal_ba = $row['tanggal_ba'];
        $customer_name = $row['customer_name'];
        $customer_alamat = $row['customer_alamat'];
        $lokasi = $row['lokasi'];
        $pekerjaan = $row['pekerjaan'];
        $po_number = $row['po_number'];
        $pelaksana = $row['pelaksana'];
        $prod_code = $row['item_code'];
        $ship_by = $row['ship_by'] ?? '';
        
        // Data dari kolom utama (item pertama)
        $items[] = [
            'description' => $row['description'],
            'item_code' => $row['item_code'],
            'qty' => $row['qty'],
            'unit' => $row['um'],
            'keterangan' => $row['keterangan']
        ];
        
        // Ambil item tambahan dari tabel berita_acara_items jika ada
        $items_query = "SELECT * FROM berita_acara_items 
                       WHERE berita_acara_id = ? 
                       ORDER BY item_no ASC";
        $stmt_items = mysqli_prepare($mysqli, $items_query);
        mysqli_stmt_bind_param($stmt_items, "i", $document_id);
        mysqli_stmt_execute($stmt_items);
        $items_result = mysqli_stmt_get_result($stmt_items);
        
        while ($item_row = mysqli_fetch_assoc($items_result)) {
            $items[] = [
                'description' => $item_row['description'],
                'item_code' => $item_row['item_code'],
                'qty' => $item_row['qty'],
                'unit' => $item_row['unit'],
                'keterangan' => $item_row['keterangan']
            ];
        }
        
        if (isset($stmt_items)) {
            mysqli_stmt_close($stmt_items);
        }
    }
    
    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }
    
} 
// Mode 2: Data dari session draft (dari form create)
elseif (isset($_SESSION['bast_draft'])) {
    $draft = $_SESSION['bast_draft'];
    
    $nomor_ba = $draft['nomor_ba'] ?? '025/BAST-ART/XII/2025';
    $tanggal_ba = $draft['tanggal_ba'] ?? date('Y-m-d');
    $customer_name = $draft['customer_name'] ?? 'PT. JTEKT COLUMN SYSTEMS INDONESIA';
    $customer_alamat = $draft['customer_alamat'] ?? '';
    $lokasi = $draft['lokasi'] ?? 'PT. JTEKT COLUMN SYSTEMS INDONESIA';
    $pekerjaan = $draft['pekerjaan'] ?? 'PR#02581 Mte [C8888] Repair Modif Gear Turret CNC Takamaz';
    $po_number = $draft['po_number'] ?? '1788/JCSID/11/25';
    $pelaksana = $draft['pelaksana'] ?? 'CV. Afshin Raya Teknik';
    $prod_code = $draft['prod_code'] ?? '-';
    $ship_by = $draft['ship_by'] ?? '';
    $items = $draft['items'] ?? [];
    
} 
// Mode 3: Data langsung dari POST (dari form preview)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form POST
    $nomor_ba = $_POST['nomor_ba'] ?? $nomor_ba;
    $tanggal_ba = $_POST['tanggal_ba'] ?? $tanggal_ba;
    $customer_name = $_POST['customer_name'] ?? $customer_name;
    $customer_alamat = $_POST['customer_alamat'] ?? $customer_alamat;
    $lokasi = $_POST['lokasi'] ?? $lokasi;
    $pekerjaan = $_POST['pekerjaan'] ?? $pekerjaan;
    $po_number = $_POST['po_number'] ?? $po_number;
    $pelaksana = $_POST['pelaksana'] ?? $pelaksana;
    $prod_code = $_POST['prod_code'] ?? '-';
    $ship_by = $_POST['ship_by'] ?? '';
    
    // Parse items dari JSON
    if (isset($_POST['items_json'])) {
        $items = json_decode($_POST['items_json'], true);
    }
    
    // Simpan ke session untuk preview
    $_SESSION['bast_draft'] = [
        'nomor_ba' => $nomor_ba,
        'tanggal_ba' => $tanggal_ba,
        'customer_name' => $customer_name,
        'customer_alamat' => $customer_alamat,
        'lokasi' => $lokasi,
        'pekerjaan' => $pekerjaan,
        'po_number' => $po_number,
        'pelaksana' => $pelaksana,
        'prod_code' => $prod_code,
        'ship_by' => $ship_by,
        'items' => $items
    ];
    
} 
// Mode 4: Data dari URL parameters (untuk preview langsung)
elseif (isset($_GET['preview'])) {
    $nomor_ba = $_GET['nomor_ba'] ?? $nomor_ba;
    $tanggal_ba = $_GET['tanggal_ba'] ?? $tanggal_ba;
    $customer_name = $_GET['customer_name'] ?? $customer_name;
    $customer_alamat = $_GET['customer_alamat'] ?? $customer_alamat;
    $lokasi = $_GET['lokasi'] ?? $lokasi;
    $pekerjaan = $_GET['pekerjaan'] ?? $pekerjaan;
    $po_number = $_GET['po_number'] ?? $po_number;
    $pelaksana = $_GET['pelaksana'] ?? $pelaksana;
    $prod_code = $_GET['prod_code'] ?? '-';
    $ship_by = $_GET['ship_by'] ?? '';
    
    // Parse items dari JSON di URL
    if (isset($_GET['items_json'])) {
        $items = json_decode(urldecode($_GET['items_json']), true);
    }
}

// --- FUNGSI UTILITY ---
function format_date($date_str) {
    if (empty($date_str)) return '';
    
    // Array bulan dalam bahasa Inggris
    $months = [
        'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
    ];
    
    $timestamp = strtotime($date_str);
    $day = date('j', $timestamp);
    $month = $months[date('n', $timestamp) - 1];
    $year = date('Y', $timestamp);
    
    return $day . '-' . $month . '-' . $year;
}

// Generate nama untuk print
$print_filename = "BAST_" . str_replace('/', '_', $nomor_ba) . ".pdf";
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Berita Acara - <?php echo htmlspecialchars($nomor_ba); ?></title>

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

/* === Header Container (Kop Surat) === */
.header-container {
    margin-bottom: 10px;
    border-top: 1px solid black;
    border-bottom: 1px solid black;
    padding-top: 5px;
    padding-bottom: 1px;
    display: table;
    width: 100%;
    table-layout: fixed;
}

.header-logo {
    display: table-cell;
    vertical-align: top;
    width: 120px;
    padding-right: 10px;
    padding-top: 5px;
}

.header-logo img {
    width: 100px;
    height: auto;
    max-height: 80px;
    object-fit: contain;
}
.header-content {
    display: table-cell;
    vertical-align: top;
    width: calc(100% - 120px);
    padding-left: 0;
}

.header-content h3 {
    font-weight: bold;
    font-size: 16px;
    margin: 0;
    padding-bottom: 5px;
    line-height: 1.2;
}

.header-content p {
    margin: 0;
    font-size: 12px;
    line-height: 1.3;
}

/* === JUDUL DOKUMEN === */
.title {
    text-align: center;
    font-size: 16px;
    margin: 10px 0 20px 0;
    text-decoration: underline;
    font-weight: bold;
}

/* === INFO SECTION - Table Layout === */
.info-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
    font-size: 11px;
}

.info-table td {
    padding: 2px 0;
    vertical-align: top;
}

.info-label {
    width: 20%;
    font-weight: normal;
    white-space: nowrap;
}

.info-colon {
    width: 2%;
    text-align: center;
}

.info-value {
    width: 28%;
    font-weight: bold;
}

.info-right-label {
    width: 15%;
    text-align: right;
    padding-right: 5px;
    font-weight: normal;
    white-space: nowrap;
}

.info-right-colon {
    width: 2%;
    text-align: center;
}

.info-right-value {
    width: 33%;
    font-weight: bold;
}

/* === ITEMS TABLE === */
.items-table-container {
    margin: 20px 0 15px 0;
}

.items-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    table-layout: fixed;
}

.items-table th {
    border: 1px solid #000;
    background-color: #f0f0f0;
    font-weight: bold;
    padding: 6px 3px;
    text-align: center;
}

.items-table td {
    border: 1px solid #000;
    padding: 6px 3px;
    vertical-align: top;
}

.items-table .no-col {
    width: 5%;
    text-align: center;
}

.items-table .desc-col {
    width: 55%;
    text-align: left;
}

.items-table .code-col {
    width: 10%;
    text-align: center;
}

.items-table .qty-col {
    width: 10%;
    text-align: center;
}

.items-table .unit-col {
    width: 10%;
    text-align: center;
}

.items-table .ket-col {
    width: 10%;
    text-align: center;
}

/* === DATE RECEIVED === */
.date-received {
    margin-top: 20px;
    font-size: 11px;
}

/* === SIGNATURE SECTION - DIPERBAIKI === */
.signature-container {
    margin-top: 60px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    width: 100%;
}

.signature-box {
    text-align: center;
    width: 48%;
    position: relative;
}

.signature-content {
    margin-top: 80px; /* Jarak dari teks ke garis */
}

.signature-line {
    border-top: 1px solid #000;
    width: 250px;
    margin: 100px auto 5px auto; /* Jarak atas 40px, bawah 5px */
    padding-top: 5px;
    font-size: 11px;
}

.signature-text {
    margin-top: 5px;
    font-size: 11px;
}

/* Kontainer teks di atas garis */
.signature-text-above {
    margin-bottom: 10px; /* Jarak antara teks dan garis */
    text-align: center;
}

/* === CONTROLS (Non-print) === */
.controls {
    width: 210mm;
    max-width: 100%;
    margin: 20px auto 0;
    text-align: center;
    padding: 15px;
    background-color: #f9f9f9;
    border-radius: 5px;
}

.btn {
    padding: 8px 16px;
    margin: 0 5px;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
}

.btn-print {
    background-color: #28a745;
}

.btn-pdf {
    background-color: #dc3545;
}

.btn-back {
    background-color: #6c757d;
}

/* === PRINT SPECIFIC === */
@media print {
    @page {
        size: A4;
        margin: 1mm 1mm 1mm 1mm;
    }
    
    body {
        margin: 20px 40px;
        background-color: white;
        font-size: 12px;
    }
    
    .no-print {
        display: none !important;
    }
    
    .controls {
        display: none;
    }
}

/* === RESPONSIVE FOR SCREEN === */
@media screen and (max-width: 1200px) {
    body {
        margin: 10px;
    }
    
    .controls {
        width: 100%;
    }
}

/* === FIX FOR SMALL SCREENS === */
@media screen and (max-width: 768px) {
    .header-container {
        display: block;
    }
    
    .header-logo {
        display: block;
        width: 100%;
        text-align: center;
        margin-bottom: 10px;
        padding-right: 0;
    }
    
    .header-content {
        display: block;
        width: 100%;
        text-align: center;
    }
    
    .signature-container {
        flex-direction: column;
        gap: 40px;
    }
    
    .signature-box {
        width: 100%;
    }
}
</style>

<!-- Include html2pdf library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body onload="window.print()">

<!-- KOP SURAT -->
<div class="header-container">
    <div class="header-logo">
        <img src="img/afshin2.png" alt="Logo CV. AFSHIN RAYA TEKNIK" 
             onerror="this.style.display='none'; this.parentElement.style.display='none';">
    </div>

    <div class="header-content">
        <h3>CV. AFSHIN RAYA TEKNIK</h3>
        <p style="font-weight: bold; margin-bottom: 3px;">
            Penyedia Sparepart Mesin Bubut dan Milling, Jasa Maintenance dan Kontruksi Gedung
        </p>
        <p style="margin-bottom: 3px; line-height: 1.2;">
            Kp. Ciketing, Jl. Kramat No. 75, RT. 004 RW. 011, Desa/Kelurahan Mustikajaya, Kecamatan Mustikajaya, Kota Bekasi, Jawa Barat, 17158
        </p>
        <p style="margin-bottom: 0;">
            Tlp : +62 896 1464 7011<br>
            Email : cvafshinrayateknik@gmail.com
        </p>
    </div>
</div>

<div class="title">BERITA ACARA SERAH TERIMA PEKERJAAN</div>

<!-- INFORMASI UTAMA -->
<table class="info-table">
    <tr>
        <td class="info-label">Nama</td>
        <td class="info-colon">:</td>
        <td class="info-value"><?php echo htmlspecialchars($customer_name); ?></td>
        <td class="info-right-label">NUMBER</td>
        <td class="info-right-colon">:</td>
        <td class="info-right-value"><?php echo htmlspecialchars($nomor_ba); ?></td>
    </tr>
    <tr>
        <td class="info-label">Pekerjaan</td>
        <td class="info-colon">:</td>
        <td class="info-value" colspan="4"><?php echo htmlspecialchars($pekerjaan); ?></td>
    </tr>
    <?php 
    if (strpos($pekerjaan, '[') !== false && strpos($pekerjaan, ']') !== false) {
        echo '<tr>';
        echo '<td class="info-label"></td>';
        echo '<td class="info-colon"></td>';
        echo '<td class="info-value" colspan="4">[C8888] Repair Modif Gear Turret CNC Takamaz</td>';
        echo '</tr>';
    }
    ?>
    <tr>
        <td class="info-label">Lokasi</td>
        <td class="info-colon">:</td>
        <td class="info-value"><?php echo htmlspecialchars($lokasi); ?></td>
        <td class="info-right-label">PROD CODE</td>
        <td class="info-right-colon">:</td>
        <td class="info-right-value"><?php echo htmlspecialchars($prod_code); ?></td>
    </tr>
    <tr>
        <td class="info-label">Pelaksana</td>
        <td class="info-colon">:</td>
        <td class="info-value"><?php echo htmlspecialchars($pelaksana); ?></td>
        <td class="info-right-label">SHIP BY</td>
        <td class="info-right-colon">:</td>
        <td class="info-right-value"><?php echo htmlspecialchars($ship_by); ?></td>
    </tr>
    <tr>
        <td class="info-label">Hari/Tanggal</td>
        <td class="info-colon">:</td>
        <td class="info-value"><?php echo format_date($tanggal_ba); ?></td>
        <td class="info-right-label">PO NUMBER</td>
        <td class="info-right-colon">:</td>
        <td class="info-right-value"><?php echo htmlspecialchars($po_number); ?></td>
    </tr>
    <tr>
        <td class="info-label">Pihak I</td>
        <td class="info-colon">:</td>
        <td class="info-value"><?php echo htmlspecialchars($customer_name); ?></td>
        <td class="info-right-label">DATE</td>
        <td class="info-right-colon">:</td>
        <td class="info-right-value"><?php echo format_date($tanggal_ba); ?></td>
    </tr>
    <tr>
        <td class="info-label">Pihak II</td>
        <td class="info-colon">:</td>
        <td class="info-value"><?php echo htmlspecialchars($pelaksana); ?></td>
        <td class="info-right-label"></td>
        <td class="info-right-colon"></td>
        <td class="info-right-value"></td>
    </tr>
    <tr>
        <td class="info-label">Nomor PO</td>
        <td class="info-colon">:</td>
        <td class="info-value"><?php echo htmlspecialchars($po_number); ?></td>
        <td class="info-right-label"></td>
        <td class="info-right-colon"></td>
        <td class="info-right-value"></td>
    </tr>
</table>

<!-- TABEL ITEM -->
<div class="items-table-container">
    <table class="items-table">
        <thead>
            <tr>
                <th class="no-col">No</th>
                <th class="desc-col">Item Description</th>
                <th class="code-col">Item Code</th>
                <th class="qty-col">Qty</th>
                <th class="unit-col">U/M</th>
                <th class="ket-col">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($items)): ?>
                <?php $counter = 1; ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="no-col"><?php echo $counter++; ?></td>
                        <td class="desc-col " style="text-align:left; vertical-align: top; white-space: pre-line;">
                            <?php
                                $description = $item['description'] ?? '';
                                echo nl2br(htmlspecialchars($description));
                            ?>
                        </td>
                        <td cclass="code-col" style="vertical-align: top;"><?php echo htmlspecialchars($item['item_code'] ?? '-'); ?></td>
                        <td class="qty-col"><?php echo number_format($item['qty'] ?? 0, 0); ?></td>
                        <td class="unit-col"><?php echo htmlspecialchars($item['unit'] ?? 'Unit'); ?></td>
                        <td class="ket-col"><?php echo htmlspecialchars($item['keterangan'] ?? 'OK'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td class="no-col">1</td>
                    <td class="desc-col">PR#02581 Mte<br>[C8888] Repair Modif Gear Turret CNC Takamaz</td>
                    <td class="code-col">-</td>
                    <td class="qty-col">1</td>
                    <td class="unit-col">Unit</td>
                    <td class="ket-col">OK</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- TANGGAL DITERIMA -->
<div class="date-received">
    Diterima tanggal : <?php echo format_date($tanggal_ba); ?>
</div>

<!-- BAGIAN TANDA TANGAN - DIPERBAIKI -->
<div class="signature-container">
    <!-- Kiri: Penerima -->
    <div class="signature-box">
        <div class="signature-text-above">
            Penerima
         
        </div>
        <div class="signature-line"></div>
        <div class="signature-text">
            ( <?php echo htmlspecialchars($customer_name); ?> )
        </div>
    </div>
    
    <!-- Kanan: Penyedia Jasa -->
    <div class="signature-box">
        <div class="signature-text-above">
           Best Regards,
        </div>
        <div class="signature-line"></div>
        <div class="signature-text">
            ( <?php echo htmlspecialchars($pelaksana); ?> )
        </div>
    </div>
</div>

<!-- KONTROL TOMBOL (Tidak tampil saat print) -->
<div class="controls no-print">
    <button class="btn btn-print" onclick="window.print()">
        <i class="fas fa-print"></i> Cetak Sekarang
    </button>
    <button class="btn btn-pdf" onclick="downloadAsPDF()">
        <i class="fas fa-file-pdf"></i> Download PDF
    </button>
    <button class="btn btn-back" onclick="goBack()">
        <i class="fas fa-arrow-left"></i> Kembali
    </button>
</div>

<script>
    // Fungsi untuk download sebagai PDF
    function downloadAsPDF() {
        const element = document.body;
        const filename = "<?php echo $print_filename; ?>";
        
        const opt = {
            margin:       10,
            filename:     filename,
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { 
                scale: 2,
                useCORS: true,
                logging: false,
                width: 800,
                letterRendering: true
            },
            jsPDF:        { 
                unit: 'mm', 
                format: 'a4', 
                orientation: 'portrait',
                compress: true,
                hotfixes: ["px_scaling"]
            }
        };
        
        // Tampilkan loading
        const originalHTML = document.body.innerHTML;
        document.body.innerHTML = '<div style="text-align:center;padding:50px;font-size:16px;">Membuat PDF... Harap tunggu.</div>';
        
        html2pdf().set(opt).from(element).save().then(() => {
            document.body.innerHTML = originalHTML;
            window.location.reload();
        }).catch(err => {
            console.error('PDF generation error:', err);
            document.body.innerHTML = originalHTML;
            alert('Error saat membuat PDF. Silakan coba lagi.');
        });
    }
    
    // Fungsi untuk kembali
    function goBack() {
        if (document.referrer) {
            window.history.back();
        } else {
            window.location.href = 'berita_acara_create.php';
        }
    }
    
    // Auto download PDF jika parameter download=true
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('download') === 'true') {
        setTimeout(() => {
            downloadAsPDF();
        }, 1000);
    }
    
    // Handle image error
    document.addEventListener('DOMContentLoaded', function() {
        const logoImg = document.querySelector('.header-logo img');
        if (logoImg) {
            logoImg.onerror = function() {
                this.style.display = 'none';
                const headerContent = document.querySelector('.header-content');
                if (headerContent) {
                    headerContent.style.width = '100%';
                    headerContent.style.textAlign = 'center';
                }
            };
        }
    });
</script>

<!-- Include FontAwesome untuk icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</body>
</html>