<?php
// =========================================================================
// PHP LOGIC: SERVICE REPORT - PRINT/EXPORT (FIXED VERSION)
// =========================================================================

// Gunakan 'functions.php' untuk koneksi database
require_once 'functions.php'; 
// Asumsi 'functions.php' berisi $mysqli atau logika koneksi

// Cek koneksi database
if (!isset($mysqli) || !$mysqli) {
    // Sebagai alternatif jika 'functions.php' hanya mendefinisikan koneksi
    // Ganti dengan mekanisme error handling Anda
    die("Koneksi database gagal! Pastikan file functions.php sudah benar.");
}

// ----------------------------------------------------
// FUNGSI UTAMA: MENGAMBIL DATA BERDASARKAN ID ATAU DRAFT
// ----------------------------------------------------

$report_data = [];
$activities = [];
$times = [];
$models = [];
$is_draft = false;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    // Mode Database (Sudah tersimpan)
    $report_id = intval($_GET['id']);
    
    // 1. Ambil Header
    $q_header = mysqli_query($mysqli, "
        SELECT sr.*, c.name AS customer_name, c.pic AS customers_pic, c.address AS customer_address 
        FROM service_reports sr 
        JOIN customers c ON sr.customer_id = c.id
        WHERE sr.id = $report_id
    ");
    $report_data = mysqli_fetch_assoc($q_header);
    
    if (!$report_data) die("Service Report tidak ditemukan.");
    
    // 2. Ambil Activities
    $q_act = mysqli_query($mysqli, "SELECT * FROM service_report_activities WHERE report_id = $report_id ORDER BY id ASC");
    while ($row = mysqli_fetch_assoc($q_act)) $activities[] = $row;
    
    // 3. Ambil Times
    $q_time = mysqli_query($mysqli, "SELECT * FROM service_report_times WHERE report_id = $report_id ORDER BY id ASC");
    while ($row = mysqli_fetch_assoc($q_time)) $times[] = $row;
    
    // 4. Ambil Models
    $q_model = mysqli_query($mysqli, "SELECT * FROM service_report_models WHERE report_id = $report_id ORDER BY id ASC");
    while ($row = mysqli_fetch_assoc($q_model)) $models[] = $row;

} elseif (isset($_GET['doc_no'])) {
    // Mode Draft (Dari form create)
    $is_draft = false;
    
    // Ambil data dari GET (Perhatian: Gunakan POST/Session jika data terlalu besar/sensitif!)
    $report_data = [
        'doc_no' => $_GET['doc_no'] ?? '',
        'date_doc' => $_GET['date_doc'] ?? date('Y-m-d'),
        'po_order_no' => $_GET['po_order_no'] ?? '',
        'prod_code' => $_GET['prod_code'] ?? '',
        'requested_by' => $_GET['requested_by'] ?? '',
        'phone' => $_GET['phone'] ?? '',
        'type_of_service' => $_GET['type_of_service'] ?? '',
        'remark_general' => $_GET['remark_general'] ?? '',
        'phenomena' => $_GET['phenomena'] ?? '',
        'cause' => $_GET['cause'] ?? '',
        'steps_taken' => $_GET['steps_taken'] ?? '',
    ];
    
    // Ambil customer data (minimal nama, alamat, pic)
    $q_cust = mysqli_query($mysqli, "SELECT name, pic, address FROM customers WHERE id = " . intval($_GET['customer_id']));
    $cust_data = mysqli_fetch_assoc($q_cust);
    $report_data['customer_name'] = $cust_data['name'] ?? 'N/A';
    $report_data['customers_pic'] = $cust_data['pic'] ?? 'N/A'; // Gunakan key yang sama dengan DB
    $report_data['customer_address'] = $cust_data['address'] ?? 'N/A';
    
    // Ambil data detail dari JSON
    $activities = json_decode($_GET['activities_json'] ?? '[]', true);
    $times = json_decode($_GET['times_json'] ?? '[]', true);
    $models = json_decode($_GET['models_json'] ?? '[]', true);

    // Koreksi struktur data draft agar kompatibel dengan output (PENTING!)
    // Untuk activities
    foreach ($activities as &$act) {
        // Ambil dari key activity_date (draft) atau date (DB draft structure)
        $act['date'] = $act['activity_date'] ?? $act['date'] ?? date('Y-m-d'); 
        $act['remark'] = $act['remark_activity'] ?? $act['remark'] ?? '-';
        // Tambahkan default nilai lain jika tidak ada
        $act['part_number'] = $act['part_number'] ?? '-';
        $act['serial_number'] = $act['serial_number'] ?? '-';
        $act['alarm'] = $act['alarm'] ?? '-';
    }
    unset($act); 

    // Untuk times
    foreach ($times as &$time) {
        $time['service_time_hrs'] = $time['hrs'] ?? 0;
        $time['service_time_mins'] = $time['mins'] ?? 0;
        // Ambil dari key date_time (draft) atau date (DB draft structure)
        $time['date'] = $time['date_time'] ?? $time['date'] ?? date('Y-m-d'); 
        $time['start'] = $time['start_time'] ?? $time['start'] ?? '';
        $time['out'] = $time['out_time'] ?? $time['out'] ?? '';
        $time['end'] = $time['end_time'] ?? $time['end'] ?? '';
        $time['back'] = $time['back_time'] ?? $time['back'] ?? '';
    }
    unset($time); 

} else {
    die("Akses tidak sah.");
}

// ----------------------------------------------------
// FUNGSI HELPER UNTUK MENGURUS TANGGAL
// ----------------------------------------------------

/**
 * Memformat string tanggal (Y-m-d) menjadi d-M-Y atau mengembalikan '-' jika tidak valid.
 * @param string|null $date_value Nilai tanggal dari DB/Draft.
 * @return string Tanggal terformat atau '-'.
 */
function format_report_date($date_value) {
    if ($date_value && $date_value !== '0000-00-00') {
        // Coba konversi ke timestamp
        $timestamp = strtotime($date_value);
        
        // Pastikan konversi berhasil (tidak false dan tidak 0)
        if ($timestamp !== false && $timestamp > 0) {
            return date('d-M-Y', $timestamp);
        }
    }
    return '-';
}


// ----------------------------------------------------
// HTML/CSS UNTUK FORMAT CETAK
// ----------------------------------------------------

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Service Report - <?php echo htmlspecialchars($report_data['doc_no'] ?? 'Draft'); ?></title>
    <style>
        body { font-family: 'Arial', sans-serif; font-size: 10pt; margin: 0; padding: 0; }
        .container { 
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto; 
    padding: 0mm 5mm; /* kiri kanan sedikit padding */
    box-sizing: border-box; 
        } 
        
        
        /* Header dan Logo */
        .cv-header {
        border-bottom: 2px solid #000;
    padding-bottom: 2px;
    margin-bottom: 2px;
    display: flex;
    align-items: center;
    margin-top: -5px; /* naikkan header */
            }

        .cv-header img {
            height: 100px;
            margin-right: 10px;
        }

        .cv-header-text h2 {
            margin: 0;
            font-size: 16pt;
            font-weight: bold;
        }

        .cv-header-text p {
            margin: 0;
            font-size: 9pt;
            line-height: 1.2;
        }   
        
        h3.title { 
            text-align: center; 
            margin: 15px 0 10px 0; 
            font-size: 14pt; 
            text-decoration: underline;
        }
        
        /* Informasi Laporan (Kiri: Customer, Kanan: Doc No, Date, etc.) */
        .report-info { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 9pt; }
        .report-info td { padding: 0px 0; vertical-align: top; }
        
        .info-customer { width: 50%; padding-right: 10px; }
        .info-details { width: 50%; }

        .info-details table { width: 100%; border-collapse: collapse; }
        .info-details td { padding: 1px 0; }
        .info-details .label { width: 120px; font-weight: bold; }
        .info-details .separator { width: 5px; text-align: right; }

        .report-info .label-small { width: 150px; font-weight: bold; }
        .report-info .value-small { width: calc(50% - 150px); }

        /* Type of Service */
        .type-service-box { 
            border: 1px solid #000; 
            padding: 5px; 
            margin-bottom: 15px; 
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10pt;
        }
        .type-service-group { flex-grow: 1; }
        .type-service { display: inline-block; margin-right: 20px; }
        .type-service input { margin-right: 5px; }

        /* Detail Tables */
        table.detail-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 8pt; }
        table.detail-table th, table.detail-table td { 
            border: 1px solid #000; 
            padding: 5px; 
            text-align: center; 
        }
        table.detail-table th { background-color: #dcdcdc; font-weight: bold; }
        table.detail-table .col-no { width: 5%; }
        table.detail-table .col-date { width: 10%; }
        table.detail-table .col-part { text-align: left; }
        
        /* Phenomena/Cause/Steps */
        .summary-box { 
            margin-top: auto; 
            border: 1px solid #000; 
            padding: 10px; 
            font-size: 9pt; 
        }
        .summary-box p { margin: 0; line-height: 1.5; }
        .summary-box .summary-label { display: inline-block; width: 100px; font-weight: bold; }
        .summary-box .summary-value { display: inline-block; }

        /* Tanda Tangan */
        .footer-sig-title { 
            text-align: center; 
            font-weight: bold; 
            margin-top: 30px; 
            margin-bottom: 10px;
        }
        .footer-sig { width: 100%; margin-top: 10px; border-collapse: collapse; }
        .footer-sig td { 
            width: 50%; 
            padding: 10px; 
            vertical-align: top; 
            border: 1px solid #000; 
            text-align: center;
        }
        .footer-sig-label { 
            text-align: center; 
            font-weight: bold; 
            padding-bottom: 50px; 
            height: 20px;
        }
        .footer-sig-name {
            border-bottom: 1px solid #000;
            display: inline-block;
            width: 80%;
            margin-top: 50px;
            padding-bottom: 3px;
        }

        /* Print Media Queries */
        @media print {
            .no-print { display: none; }
            .container { padding: 0; margin: 0; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } /* Untuk mencetak background/warna */
        }
        .draft-watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            color: rgba(255, 0, 0, 0.15); /* Lebih redup */
            font-size: 80pt;
            font-weight: bold;
            pointer-events: none;
            z-index: 9999;
        }
        body {
    margin-top: 0;
    margin-bottom: 0;
    margin-left: 0.26cm;
    margin-right: 0.26cm;
}

    </style>
</head>
<body>
    <div class="container">
        <?php if ($is_draft): ?>
            <div class="draft-watermark no-print">DRAFT</div>
        <?php endif; ?>

     <div class="cv-header">
    <img src="img/afshin2.png" alt="Logo ART">
    <div class="cv-header-text">
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

        <h3 class="title">SERVICE REPORT</h3>
        
        <table class="report-info">
            <tr>
                <td class="info-customer">
                    <table style="width: 100%;">
                        <tr><td class="label">CUSTOMER</td><td class="separator">:</td><td> <?php echo htmlspecialchars($report_data['customer_name'] ?? 'N/A'); ?></td></tr>
                        <tr><td class="label">ADDRESS</td><td class="separator">:</td><td><?php echo nl2br(htmlspecialchars($report_data['customer_address'] ?? 'N/A')); ?></td></tr>
                        <tr><td class="label">PHONE</td><td class="separator">:</td><td><?php echo htmlspecialchars($report_data['phone'] ?? '-'); ?></td></tr>
                        <tr><td class="label">REQUESTED BY</td><td class="separator">:</td><td><?php echo htmlspecialchars($report_data['customers_pic'] ?? '-'); ?></td></tr>
                    </table>
                </td>
                <td class="info-details">
                    <table style="width: 100%;">
                        <tr><td class="label">NO DOC</td><td class="separator">:</td><td><?php echo htmlspecialchars($report_data['doc_no'] ?? '-'); ?></td></tr>
                        <tr><td class="label">DATE</td><td class="separator">:</td><td><?php echo date('d-M-Y', strtotime($report_data['date_doc'] ?? 'now')); ?></td></tr>
                        <tr><td class="label">PROD CODE</td><td class="separator">:</td><td><?php echo htmlspecialchars($report_data['prod_code'] ?? '-'); ?></td></tr>
                        <tr><td class="label">PO/ORDER NO</td><td class="separator">:</td><td><?php echo htmlspecialchars($report_data['po_order_no'] ?? '-'); ?></td></tr>
                    </table>
                </td>
            </tr>
        </table>
        
        <div class="type-service-box">
            <div class="type-service-group">
                <span style="font-weight: bold; margin-right: 12px;">TYPE OF SERVICE:</span>
                <?php 
                $types = ['REPAIR', 'SERVICE', 'ENGINEERING'];
                $selected_type = strtoupper($report_data['type_of_service'] ?? '');
                foreach ($types as $type):
                    $checked = ($selected_type == $type) ? 'checked' : '';
                ?>
                <span class="type-service">
                    <input type="checkbox" <?php echo $checked; ?> disabled> <?php echo $type; ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>

        <table class="detail-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th class="col-date">DATE</th>
                    <th style="width: 25%;">PART NUMBER</th>
                    <th style="width: 25%;">SERIAL NUMBER</th>
                    <th style="width: 15%;">ALARM</th>
                    <th style="width: 20%;">REMARK</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($activities)): ?>
                <tr><td colspan="6" style="padding: 10px;">- NO ACTIVITIES RECORDED -</td></tr>
                <?php else: ?>
                    <?php 
                    $max_rows = max(count($activities), 3); // Minimal 3 baris
                    for ($i = 0; $i < $max_rows; $i++): 
                        $act = $activities[$i] ?? null;
                        $formatted_date = $act ? format_report_date($act['activity_date'] ?? null) : '-';
                        
                        // --- PERBAIKAN: Gunakan fungsi helper untuk format tanggal ---
                        // -----------------------------------------------------------
                        
                        $part_number = $act ? htmlspecialchars($act['part_number'] ?? '-') : '-';
                        $serial_number = $act ? htmlspecialchars($act['serial_number'] ?? '-') : '-';
                        $alarm = $act ? htmlspecialchars($act['alarm'] ?? '-') : '-';
                        $remark = $act ? htmlspecialchars($act['remark_activity'] ?? null) : '-';
                        // $formatted_date = $act ? format_report_date($act['activity_date'] ?? null) : '-';
                        
                    ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo $formatted_date; ?></td> 
                        <td class="col-part"><?php echo $part_number; ?></td>
                        <td><?php echo $serial_number; ?></td>
                        <td><?php echo $alarm; ?></td>
                        <td><?php echo $remark; ?></td>
                    </tr>
                    <?php endfor; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <table class="detail-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th class="col-date">DATE</th>
                    <th style="width: 10%;">START</th>
                    <th style="width: 10%;">OUT</th>
                    <th style="width: 10%;">END</th>
                    <th style="width: 10%;">BACK</th>
                    <th style="width: 35%;">SERVICE TIME</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($times)): ?>
                <tr><td colspan="7" style="padding: 10px;">- NO TIME RECORDED -</td></tr>
                <?php else: ?>
                    <?php 
                    $max_rows = max(count($times), 4); // Minimal 4 baris
                    for ($i = 0; $i < $max_rows; $i++): 
                        $time = $times[$i] ?? null;
                        
                        $total_time = '-';
                        // --- PERBAIKAN: Gunakan fungsi helper untuk format tanggal ---
                        $formatted_date = $time ? format_report_date($time['date_time'] ?? null) : '-';
                        // -----------------------------------------------------------
                        
                        if ($time) {
                            $start_time = $time['start'] ?? $time['start_time'] ?? '';
                            $out_time = $time['out'] ?? $time['out_time'] ?? '';
                            $end_time = $time['end'] ?? $time['end_time'] ?? '';
                            $back_time = $time['back'] ?? $time['back_time'] ?? '';
                            
                            $total_time = ($time['service_time_hrs'] ?? 0) . " HRS " . ($time['service_time_mins'] ?? 0) . " MINS";
                        } else {
                            $start_time = $out_time = $end_time = $back_time = '';
                        }
                    ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo $formatted_date; ?></td>
                        <td><?php echo $start_time ? substr($start_time, 0, 5) : '-'; ?></td>
                        <td><?php echo $out_time ? substr($out_time, 0, 5) : '-'; ?></td>
                        <td><?php echo $end_time ? substr($end_time, 0, 5) : '-'; ?></td>
                        <td><?php echo $back_time ? substr($back_time, 0, 5) : '-'; ?></td>
                        <td style="text-align: center;"><?php echo $total_time; ?></td>
                    </tr>
                    <?php endfor; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <table class="detail-table">
            <thead>
                <tr>
                    <th class="col-no">No</th>
                    <th style="width: 30%;">MODEL NUMBER</th>
                    <th style="width: 25%;">SERIAL NUMBER</th>
                    <th style="width: 20%;">M.T.B</th>
                    <th style="width: 20%;">M.C MODEL</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($models)): ?>
                <tr><td colspan="5" style="padding: 10px;">- NO MACHINE MODEL RECORDED -</td></tr>
                <?php else: ?>
                    <?php 
                    $max_rows = max(count($models), 3); // Minimal 3 baris
                    for ($i = 0; $i < $max_rows; $i++): 
                        $model = $models[$i] ?? null;
                    ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo $model ? htmlspecialchars($model['model_number'] ?? '-') : '-'; ?></td>
                        <td><?php echo $model ? htmlspecialchars($model['serial_number'] ?? '-') : '-'; ?></td>
                        <td><?php echo $model ? htmlspecialchars($model['mtb'] ?? '-') : '-'; ?></td>
                        <td><?php echo $model ? htmlspecialchars($model['mc_model'] ?? '-') : '-'; ?></td>
                    </tr>
                    <?php endfor; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="summary-box">
            <p>
                <span class="summary-label">PHENOMENA</span> : 
                <span class="summary-value"><?php echo nl2br(htmlspecialchars($report_data['phenomena'] ?? '-')); ?></span>
            </p>
            <p>
                <span class="summary-label">CAUSE</span> : 
                <span class="summary-value"><?php echo nl2br(htmlspecialchars($report_data['cause'] ?? '-')); ?></span>
            </p>
            <p class="summary-row">
                <span class="summary-label">STEPS TAKEN</span>
                <span class="summary-separator">:</span>
                <span class="summary-value">
                    <?php echo nl2br(htmlspecialchars($report_data['steps_taken'] ?? '-')); ?>
                </span>
            </p>
        </div>

        <div class="footer-sig-title">
            SIGNATURE
        </div>
        
        <table class="footer-sig">
            <tr>
                <td>
                    <div class="footer-sig-label">CUSTOMER</div>
                    <div class="footer-sig-name"></div>
                </td>
                <td>
                    <div class="footer-sig-label">ENGINNER</div>
                    <div class="footer-sig-name"></div>
                </td>
            </tr>
        </table>
        
        <div class="no-print" style="text-align: center; margin-top: 20px;">
            <button onclick="window.print()" style="padding: 10px 20px; margin-right: 10px; cursor: pointer;">Print Report</button>
            <?php if ($is_draft): ?>
                <button onclick="window.close()" style="padding: 10px 20px; cursor: pointer;">Close</button>
            <?php endif; ?>
        </div>

    </div>
</body>
</html>