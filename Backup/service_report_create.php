<?php
// =========================================================
// SERVICE REPORT CREATE - CPANEL SAFE (PHP 8 READY)
// =========================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'functions.php';
require_login();

if (!$mysqli) {
    die('DB Connection failed');
}

/* ================== DATA MASTER ================== */

// Customer
$customers_result = mysqli_query(
    $mysqli,
    "SELECT id, name, customer_no, pic, telephone FROM customers ORDER BY name ASC"
);



// Generate SR Number
function generate_service_report_no($mysqli) {
    $year = date('Y');
    $romawi = bulan_romawi(date('n'));
    $prefix = "SR-CV";

    $q = mysqli_query($mysqli, "
        SELECT doc_no FROM service_reports
        WHERE doc_no LIKE '%/{$year}'
        ORDER BY id DESC LIMIT 1
    ");

    $last = 0;
    if ($q && $r = mysqli_fetch_assoc($q)) {
        $p = explode('/', $r['doc_no']);
        $last = (int)$p[0];
    }

    return str_pad($last + 1, 3, '0', STR_PAD_LEFT) . "/{$prefix}/{$romawi}/{$year}";
}

$next_doc_no = generate_service_report_no($mysqli);
$types_of_service = ['REPAIR','SERVICE','ENGINEERING'];

/* ================== SAVE PROCESS ================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {

    $doc_no          = trim($_POST['doc_no'] ?? '');
    $date_doc        = trim($_POST['date_doc'] ?? '');
    $customer_id     = (int)($_POST['customer_id'] ?? 0);
    $po_order_no     = trim($_POST['po_order_no'] ?? '');
    $prod_code       = trim($_POST['prod_code'] ?? '');
    $requested_by    = trim($_POST['requested_by'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $type_of_service = trim($_POST['type_of_service'] ?? '');
    $remark_general  = trim($_POST['remark_general'] ?? '');
    $phenomena       = trim($_POST['phenomena'] ?? '');
    $cause           = trim($_POST['cause'] ?? '');
    $steps_taken     = trim($_POST['steps_taken'] ?? '');

    if (!$doc_no || !$date_doc || !$customer_id || !$type_of_service) {
        flash_set('error', 'Header Service Report wajib diisi');
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $activities = json_decode($_POST['activities_json'] ?? '[]', true) ?: [];
    $times      = json_decode($_POST['times_json'] ?? '[]', true) ?: [];
    $models     = json_decode($_POST['models_json'] ?? '[]', true) ?: [];

    $mysqli->begin_transaction();

    try {

        /* ===== HEADER ===== */
        $stmt = $mysqli->prepare("
            INSERT INTO service_reports
            (doc_no, date_doc, customer_id, po_order_no, prod_code,
             requested_by, phone, type_of_service,
             remark_general, phenomena, cause, steps_taken)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        if (!$stmt) throw new Exception($mysqli->error);

        $stmt->bind_param(
            'ssisssssssss',
            $doc_no,
            $date_doc,
            $customer_id,
            $po_order_no,
            $prod_code,
            $requested_by,
            $phone,
            $type_of_service,
            $remark_general,
            $phenomena,
            $cause,
            $steps_taken
        );

        if (!$stmt->execute()) throw new Exception($stmt->error);

        $report_id = $mysqli->insert_id;

        /* ===== ACTIVITIES ===== */
        if ($activities) {
            $stmtA = $mysqli->prepare("
                INSERT INTO service_report_activities
                (report_id, activity_date, part_number, serial_number, alarm, remark_activity)
                VALUES (?,?,?,?,?,?)
            ");

            foreach ($activities as $a) {
                $stmtA->bind_param(
                    'isssss',
                    $report_id,
                    $a['date'],
                    $a['part_number'],
                    $a['serial_number'],
                    $a['alarm'],
                    $a['remark']
                );
                if (!$stmtA->execute()) throw new Exception($stmtA->error);
            }
        }

        /* ===== TIMES ===== */
        if ($times) {
            $stmtT = $mysqli->prepare("
                INSERT INTO service_report_times
                (report_id, date_time, start_time, out_time, end_time, back_time, service_time_hrs, service_time_mins)
                VALUES (?,?,?,?,?,?,?,?)
            ");

            foreach ($times as $t) {
                $stmtT->bind_param(
                    'issssiii',
                    $report_id,
                    $t['date'],
                    $t['start'],
                    $t['out'],
                    $t['end'],
                    $t['back'],
                    $t['hrs'],
                    $t['mins']
                );
                if (!$stmtT->execute()) throw new Exception($stmtT->error);
            }
        }

        /* ===== MODELS ===== */
        if ($models) {
            $stmtM = $mysqli->prepare("
                INSERT INTO service_report_models
                (report_id, model_number, serial_number, mtb, mc_model)
                VALUES (?,?,?,?,?)
            ");

            foreach ($models as $m) {
                $stmtM->bind_param(
                    'issss',
                    $report_id,
                    $m['model_number'],
                    $m['serial_number'],
                    $m['mtb'],
                    $m['mc_model']
                );
                if (!$stmtM->execute()) throw new Exception($stmtM->error);
            }
        }

        $mysqli->commit();

        flash_set('success', "Service Report {$doc_no} berhasil disimpan");
        header('Location: service_report_list.php');
        exit;

    } catch (Exception $e) {
        $mysqli->rollback();
        flash_set('error', $e->getMessage());
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// --------------------------------------------------
// --- Bagian D: HTML Output ---
// --------------------------------------------------
$error_msg = flash_get('error');
$success_msg = flash_get('success'); 
// Memulai output HTML dengan header
include 'header.php'; 
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">📝 Buat Service Report Baru</h3>
        <a href="service_report_list.php" class="btn btn-outline-secondary">
            <i class="fas fa-list"></i> Kembali ke Daftar
        </a>
    </div>

    <?php if($error_msg): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div><?php endif; ?>
    <?php if($success_msg): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div><?php endif; ?>

    <form id="serviceReportForm" method="POST" action="">
        <input type="hidden" name="activities_json" id="activities_json_input"> 
        <input type="hidden" name="times_json" id="times_json_input">
        <input type="hidden" name="models_json" id="models_json_input">
        <input type="hidden" name="action" value="save">

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-file-alt"></i> Informasi Header & Customer</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="customer_select">Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" class="form-control" id="customer_select" required>
                                <option value="">-- Pilih Customer --</option>
                                <?php mysqli_data_seek($customers_result, 0); ?>
                                <?php while($c = mysqli_fetch_assoc($customers_result)): ?>
                                <option 
                                    value="<?php echo $c['id']; ?>"
                                    data-pic="<?php echo htmlspecialchars($c['pic'] ?? ''); ?>"
                                    data-phone="<?php echo htmlspecialchars($c['telephone'] ?? ''); ?>" >
                                    <?php echo htmlspecialchars($c['name'].' ('.$c['customer_no'].')'); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="requested_by_input">Requested By / PIC</label>
                            <input type="text" name="requested_by" class="form-control" id="requested_by_input" placeholder="Akan terisi otomatis dari data PIC Customer">
                        </div>
                        <div class="form-group">
                            <label for="phone_input">Phone</label> <input type="text" name="phone" class="form-control" id="phone_input" placeholder="Nomor telepon PIC">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Nomor Service Report (SR)</label>
                            <input type="text" name="doc_no" class="form-control font-weight-bold" value="<?php echo htmlspecialchars($next_doc_no); ?>" required readonly>
                        </div>
                        <div class="form-group">
                            <label>Tanggal Dokumen <span class="text-danger">*</span></label>
                            <input type="date" name="date_doc" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>PO/Order No.</label>
                                <input type="text" name="po_order_no" class="form-control">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Product Code</label>
                                <input type="text" name="prod_code" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group mt-3">
                    <label class="d-block">Type of Service <span class="text-danger"></span></label>
                    <?php foreach ($types_of_service as $type): ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="type_of_service" id="type_<?php echo strtolower($type); ?>" value="<?php echo $type; ?>">
                        <label class="form-check-label" for="type_<?php echo strtolower($type); ?>"><?php echo $type; ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-cogs"></i> Detail Mesin / Model</h5>
            </div>
            <div class="card-body p-2">
                <div class="table-responsive">
                    <table class="table table-sm table-striped" id="modelsTable">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th>Model Number</th>
                                <th>Serial Number</th>
                                <th>M.T.B</th>
                                <th>M.C Model</th>
                                <th style="width: 5%;">Act</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="item_no">1</td>
                                <td><input type="text" name="model_number[]" class="form-control form-control-sm"></td>
                                <td><input type="text" name="model_serial[]" class="form-control form-control-sm"></td>
                                <td><input type="text" name="model_mtb[]" class="form-control form-control-sm"></td>
                                <td><input type="text" name="model_mc[]" class="form-control form-control-sm"></td>
                                <td><button class="btn btn-danger btn-sm removeRow" type="button"><i class="fas fa-times"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <button id="addModelRow" class="btn btn-sm btn-outline-info" type="button"><i class="fas fa-plus"></i> Tambah Model</button>
            </div>
        </div>
        
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-clock"></i> Detail Waktu Kerja <small class="text-muted">(Total Service Time akan terhitung otomatis)</small></h5>
            </div>
            <div class="card-body p-2">
                <div class="table-responsive">
                    <table class="table table-sm table-striped" id="timesTable">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th style="width: 12%;">Date</th>
                                <th style="width: 12%;">Start (WIB)</th>
                                <th style="width: 12%;">Out (Break)</th>
                                <th style="width: 12%;">End (WIB)</th>
                                <th style="width: 12%;">Back (Resume)</th>
                                <th style="width: 15%;">Service Time (Hrs:Mins)</th>
                                <th style="width: 5%;">Act</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="item_no">1</td>
                                <td><input type="date" name="time_date[]" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required></td>
                                <td><input type="time" name="time_start[]" class="form-control form-control-sm time-input"></td>
                                <td><input type="time" name="time_out[]" class="form-control form-control-sm time-input"></td>
                                <td><input type="time" name="time_end[]" class="form-control form-control-sm time-input"></td>
                                <td><input type="time" name="time_back[]" class="form-control form-control-sm time-input"></td>
                                <td class="text-center font-weight-bold total-time">0 HRS 0 MINS</td>
                                <td><button class="btn btn-danger btn-sm removeRow" type="button"><i class="fas fa-times"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <button id="addTimeRow" class="btn btn-sm btn-outline-warning" type="button"><i class="fas fa-plus"></i> Tambah Slot Waktu</button>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-wrench"></i> Detail Aktivitas & Part</h5>
            </div>
            <div class="card-body p-2">
                <div class="table-responsive">
                    <table class="table table-sm table-striped" id="activitiesTable">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th style="width: 12%;">Date</th>
                                <th>Part Number</th>
                                <th>Serial Number</th>
                                <th>Alarm</th>
                                <th>Remark / Tindakan <span class="text-danger">*</span></th>
                                <th style="width: 5%;">Act</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="item_no">1</td>
                                <td><input type="date" name="act_date[]" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required></td>
                                <td><input type="text" name="act_part_number[]" class="form-control form-control-sm"></td>
                                <td><input type="text" name="act_serial_number[]" class="form-control form-control-sm"></td>
                                <td><input type="text" name="act_alarm[]" class="form-control form-control-sm"></td>
                                <td><input type="text" name="act_remark[]" class="form-control form-control-sm" required></td>
                                <td><button class="btn btn-danger btn-sm removeRow" type="button"><i class="fas fa-times"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <button id="addActivityRow" class="btn btn-sm btn-outline-success" type="button"><i class="fas fa-plus"></i> Tambah Aktivitas</button>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-tasks"></i> Kesimpulan dan Tindakan</h5>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Phenomena / Masalah</label>
                    <textarea name="phenomena" class="form-control" rows="2" placeholder="Jelaskan masalah yang terjadi"></textarea>
                </div>
                <div class="form-group">
                    <label>Cause / Penyebab</label>
                    <textarea name="cause" class="form-control" rows="2" placeholder="Jelaskan akar penyebab masalah"></textarea>
                </div>
                <div class="form-group">
                    <label>Steps Taken / Langkah Perbaikan</label>
                    <textarea name="steps_taken" class="form-control" rows="2" placeholder="Jelaskan tindakan yang telah dilakukan untuk perbaikan"></textarea>
                </div>
                <div class="form-group">
                    <label>Remark (General)</label>
                    <textarea name="remark_general" class="form-control" rows="2" placeholder="Catatan/kesimpulan umum (Opsional)"></textarea>
                </div>
            </div>
        </div>

        <div class="mt-4 pt-3 border-top d-flex justify-content-end">
            <button type="button" onclick="prepareAndPrintDraftSR()" class="btn btn-info btn-lg mx-2">
                <i class="fas fa-print"></i> Lihat Draft
            </button>
            <button type="button" onclick="prepareAndSubmitSR()" class="btn btn-success btn-lg">
                <i class="fas fa-save"></i> Simpan Service Report
            </button>
        </div>
    </form>
</div> 

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    /* ========== INITIAL SETUP & AUTOFILL PIC ========== */

    $(document).ready(function() {
        // 1. Autofill Requested By / PIC dan Phone
        $("#customer_select").on("change", function() {
            const selectedOption = $(this).find('option:selected');
            // Mengambil data PIC dari atribut data-pic pada elemen <option>
            const picName = selectedOption.data('pic');
            // Mengambil data Phone PIC dari atribut data-phone yang baru ditambahkan
            const picPhone = selectedOption.data('phone'); 
            
            $("#requested_by_input").val(picName || '');
            $("#phone_input").val(picPhone || ''); // PERBAIKAN: Menggunakan ID phone_input
        }).trigger('change');
        
        // 2. Initial Reindex & Time calculation on load
        reindexTable('activitiesTable');
        reindexTable('timesTable');
        reindexTable('modelsTable');

        $('#timesTable tbody tr').each(function() {
            updateRowServiceTime($(this));
        });
    });


    /* ========== LOGIC TABLE (ADD/REMOVE) - TIDAK BERUBAH ========== */

    function getTableId($element) {
        return $element.closest("table").attr('id');
    }

    // Re-index row numbers
    function reindexTable(tableId) {
        $(`#${tableId} tbody tr`).each(function(i){
            $(this).find(".item_no").text(i + 1);
        });
    }

    // ADD ACTIVITY ROW
    $("#addActivityRow").click(function(){
        let tr = `
            <tr>
                <td class="item_no"></td>
                <td><input type="date" name="act_date[]" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required></td>
                <td><input type="text" name="act_part_number[]" class="form-control form-control-sm"></td>
                <td><input type="text" name="act_serial_number[]" class="form-control form-control-sm"></td>
                <td><input type="text" name="act_alarm[]" class="form-control form-control-sm"></td>
                <td><input type="text" name="act_remark[]" class="form-control form-control-sm" required></td>
                <td><button class="btn btn-danger btn-sm removeRow" type="button"><i class="fas fa-times"></i></button></td>
            </tr>
        `;
        $("#activitiesTable tbody").append(tr);
        reindexTable('activitiesTable');
    });

    // ADD TIME ROW
    $("#addTimeRow").click(function(){
        let tr = `
            <tr>
                <td class="item_no"></td>
                <td><input type="date" name="time_date[]" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required></td>
                <td><input type="time" name="time_start[]" class="form-control form-control-sm time-input"></td>
                <td><input type="time" name="time_out[]" class="form-control form-control-sm time-input"></td>
                <td><input type="time" name="time_end[]" class="form-control form-control-sm time-input"></td>
                <td><input type="time" name="time_back[]" class="form-control form-control-sm time-input"></td>
                <td class="text-center font-weight-bold total-time">0 HRS 0 MINS</td>
                <td><button class="btn btn-danger btn-sm removeRow" type="button"><i class="fas fa-times"></i></button></td>
            </tr>
        `;
        $("#timesTable tbody").append(tr);
        reindexTable('timesTable');
    });

    // ADD MODEL ROW
    $("#addModelRow").click(function(){
        let tr = `
            <tr>
                <td class="item_no"></td>
                <td><input type="text" name="model_number[]" class="form-control form-control-sm"></td>
                <td><input type="text" name="model_serial[]" class="form-control form-control-sm"></td>
                <td><input type="text" name="model_mtb[]" class="form-control form-control-sm"></td>
                <td><input type="text" name="model_mc[]" class="form-control form-control-sm"></td>
                <td><button class="btn btn-danger btn-sm removeRow" type="button"><i class="fas fa-times"></i></button></td>
            </tr>
        `;
        $("#modelsTable tbody").append(tr);
        reindexTable('modelsTable');
    });

    // Remove Row (Generic handler)
    $(document).on("click", ".removeRow", function(){
        const tableId = getTableId($(this));
        
        $(this).closest("tr").remove();
        reindexTable(tableId);
        if (tableId === 'timesTable') {
            // TIDAK PERLU menghitung ulang baris yang dihapus, tapi hanya re-index
            // updateRowServiceTime($(this).closest('tr')); 
        }
    });
    
    /* ========== LOGIC PERHITUNGAN WAKTU - TIDAK BERUBAH ========== */

    // Convert HH:MM to minutes
    function timeToMinutes(timeStr) {
        if (!timeStr) return 0;
        const [h, m] = timeStr.split(':').map(Number);
        return h * 60 + m;
    }

    // Calculate total service time (in minutes)
    function calculateServiceTime(start, out, end, back) {
        const startMin = timeToMinutes(start);
        const endMin = timeToMinutes(end);
        const outMin = timeToMinutes(out);
        const backMin = timeToMinutes(back);

        let totalDuration = 0;
        
        if (startMin && endMin) {
            totalDuration += (endMin - startMin);
        }
        
        if (outMin && backMin && outMin < backMin) {
            // Subtract break time
            totalDuration -= (backMin - outMin); 
        }

        return Math.max(0, totalDuration);
    }
    
    // Update service time display for a row
    function updateRowServiceTime($row) {
        const start = $row.find('input[name="time_start[]"]').val();
        const out = $row.find('input[name="time_out[]"]').val();
        const end = $row.find('input[name="time_end[]"]').val();
        const back = $row.find('input[name="time_back[]"]').val();
        
        let totalMins = calculateServiceTime(start, out, end, back);
        
        const hrs = Math.floor(totalMins / 60);
        const mins = totalMins % 60;
        
        $row.find('.total-time').text(`${hrs} HRS ${mins} MINS`);
    }
    
    // Listener for time input changes
    $(document).on('change', '#timesTable input.time-input', function() {
        updateRowServiceTime($(this).closest('tr'));
    });


    /* ========== SUBMIT LOGIC - TIDAK BERUBAH (HANYA VALIDASI) ========== */

    // Mapping fields to selectors (Must match PHP prepared statements keys)
    const activityMap = {
        date: 'input[name="act_date[]"]',
        part_number: 'input[name="act_part_number[]"]',
        serial_number: 'input[name="act_serial_number[]"]',
        alarm: 'input[name="act_alarm[]"]',
        remark: 'input[name="act_remark[]"]' // This maps to remark_activity in DB
    };

    const timeMap = {
        date: 'input[name="time_date[]"]', // This maps to date_time in DB
        start: 'input[name="time_start[]"]', // This maps to start_time in DB
        out: 'input[name="time_out[]"]', // This maps to out_time in DB
        end: 'input[name="time_end[]"]', // This maps to end_time in DB
        back: 'input[name="time_back[]"]', // This maps to back_time in DB
        hrs: '.total-time', 
        mins: '.total-time' 
    };
    
    const modelMap = {
        model_number: 'input[name="model_number[]"]',
        serial_number: 'input[name="model_serial[]"]',
        mtb: 'input[name="model_mtb[]"]',
        mc_model: 'input[name="model_mc[]"]'
    };

    // Function to collect data into JSON
    function collectDataToJson(tableId, dataMap) {
        let data = [];
        let rows = $(`#${tableId} tbody tr`);
        
        if (rows.length === 0) return data;

        rows.each(function() {
            let row = {};
            let $row = $(this);
            let hasValue = false;
            
            for (const key in dataMap) {
                const selector = dataMap[key];
                let value = $row.find(selector).val();

                // Custom calculation for time
                if (tableId === 'timesTable' && (key === 'hrs' || key === 'mins')) {
                    const start = $row.find('input[name="time_start[]"]').val();
                    const out = $row.find('input[name="time_out[]"]').val();
                    const end = $row.find('input[name="time_end[]"]').val();
                    const back = $row.find('input[name="time_back[]"]').val();
                    const totalMins = calculateServiceTime(start, out, end, back);

                    value = (key === 'hrs') ? Math.floor(totalMins / 60) : totalMins % 60;
                }
                
                row[key] = value;

                // Check for non-empty required fields
                if (value && key !== 'hrs' && key !== 'mins') {
                    if (tableId === 'activitiesTable' && key === 'remark') hasValue = true;
                    if (tableId === 'timesTable' && (key === 'start' || key === 'end')) hasValue = true;
                    if (tableId === 'modelsTable') hasValue = true;
                }
            }
            
            // Basic filtering for empty rows
            if (tableId === 'activitiesTable' && row.remark) {
                 data.push(row);
            } else if (tableId === 'timesTable' && row.date && row.start && row.end) {
                 data.push(row);
            } else if (tableId === 'modelsTable' && (row.model_number || row.serial_number)) {
                 data.push(row);
            }
        });
        return data;
    }

    // Function Submit (triggered by user click)
    function prepareAndSubmitSR() {
        const activitiesData = collectDataToJson('activitiesTable', activityMap);
        const timesData = collectDataToJson('timesTable', timeMap);
        const modelsData = collectDataToJson('modelsTable', modelMap);

        // Validasi Minimal Header
        const customerId = $("#customer_select").val();
        const typeOfService = $('input[name="type_of_service"]:checked').val();

        if (!customerId || !typeOfService) {
            alert("Customer dan Type of Service harus diisi.");
            return true;
        }

        // Validasi Minimal Detail
        if (activitiesData.length === 0) {
            alert("Minimal satu baris Detail Aktivitas/Remark harus diisi.");
            return false;
        }
        
        if (timesData.length === 0) {
            alert("Minimal satu baris Detail Waktu Kerja (Start & End Time) harus diisi.");
            return false;
        }
        
        // Serialize and set hidden inputs
        $("#activities_json_input").val(JSON.stringify(activitiesData));
        $("#times_json_input").val(JSON.stringify(timesData));
        $("#models_json_input").val(JSON.stringify(modelsData));

        // Submit form
        if(confirm("Apakah Anda yakin ingin menyimpan Service Report ini?")) {
            $("#serviceReportForm").submit();
        }
    }
    
    // Function untuk print draft (tidak diubah)
    function prepareAndPrintDraftSR() {
        alert("Fungsi Print Draft belum diimplementasikan.");
        // Logika untuk mempersiapkan data JSON dan mengirimkan ke halaman print draft
    }
</script>