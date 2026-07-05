<?php
// =========================================================================
// PHP LOGIC: SERVICE REPORT - EDIT (FINAL & CLEAN)
// =========================================================================

// --- Bagian A: Initial Setup & Dependencies ---
require_once 'functions.php'; 
require_login(); 

if (!$mysqli) {
    die("Koneksi database gagal!");
}

// Ambil ID dari URL
$report_id = (int)($_GET['id'] ?? 0);

if ($report_id === 0) {
    flash_set('error', 'ID Service Report tidak valid.');
    header('Location: service_report_list.php');
    exit;
}

// --- Bagian B: Data Fetching (Current Data) ---

// 1. Ambil Data Header
$header_query = "SELECT * FROM service_reports WHERE id = ?";
$stmt_header = $mysqli->prepare($header_query);
$stmt_header->bind_param('i', $report_id);
$stmt_header->execute();
$report_data = $stmt_header->get_result()->fetch_assoc();

if (!$report_data) {
    flash_set('error', 'Service Report tidak ditemukan.');
    header('Location: service_report_list.php');
    exit;
}

// 2. Ambil Data Detail
$activities_query = "SELECT * FROM service_report_activities WHERE report_id = ?";
$stmt_act = $mysqli->prepare($activities_query);
$stmt_act->bind_param('i', $report_id);
$stmt_act->execute();
$activities_data = $stmt_act->get_result()->fetch_all(MYSQLI_ASSOC);

$times_query = "SELECT * FROM service_report_times WHERE report_id = ?";
$stmt_time = $mysqli->prepare($times_query);
$stmt_time->bind_param('i', $report_id);
$stmt_time->execute();
$times_data = $stmt_time->get_result()->fetch_all(MYSQLI_ASSOC);

$models_query = "SELECT * FROM service_report_models WHERE report_id = ?";
$stmt_model = $mysqli->prepare($models_query);
$stmt_model->bind_param('i', $report_id);
$stmt_model->execute();
$models_data = $stmt_model->get_result()->fetch_all(MYSQLI_ASSOC);


// 3. Data Pendukung (Customer & Types)
$customers_query = "SELECT id, name, customer_no, pic FROM customers ORDER BY name ASC";
$customers_result = mysqli_query($mysqli, $customers_query);

$types_of_service = ['REPAIR', 'SERVICE', 'ENGINEERING'];

// 4. Fungsi Utility (untuk JS)
if (!function_exists('calculateServiceTime')) {
    // Fungsi ini akan dieksekusi di JS, kita hanya butuh function di PHP jika diperlukan di server
    // Untuk tujuan ini, kita akan embed nilai dari DB langsung ke JS
    function calculateServiceTimePHP($start, $out, $end, $back) {
        // Logika PHP untuk calculate service time (jika diperlukan di sisi server)
        // Di sini kita abaikan karena data hrs/mins sudah di-store di DB
        $startMin = strtotime("1970-01-01 " . $start);
        $endMin = strtotime("1970-01-01 " . $end);
        $outMin = strtotime("1970-01-01 " . $out);
        $backMin = strtotime("1970-01-01 " . $back);

        if ($startMin === false || $endMin === false) return ['hrs' => 0, 'mins' => 0];

        $totalDurationSec = $endMin - $startMin;

        if ($outMin !== false && $backMin !== false && $outMin < $backMin) {
            $totalDurationSec -= ($backMin - $outMin);
        }

        $totalMins = max(0, round($totalDurationSec / 60));
        return [
            'hrs' => floor($totalMins / 60),
            'mins' => $totalMins % 60
        ];
    }
}


// --- Bagian C: Logika POST (Update ke Database) ---

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update'){
    
    // Ambil dan bersihkan data Header
    $doc_no             = trim($_POST['doc_no'] ?? '');
    $customer_id        = (int)($_POST['customer_id'] ?? 0); 
    $date_doc           = trim($_POST['date_doc'] ?? '');
    $po_order_no        = trim($_POST['po_order_no'] ?? '');
    $prod_code          = trim($_POST['prod_code'] ?? '');
    $requested_by       = trim($_POST['requested_by'] ?? ''); 
    $phone              = trim($_POST['phone'] ?? ''); 
    $type_of_service    = trim($_POST['type_of_service'] ?? '');
    $remark_general     = trim($_POST['remark_general'] ?? '');
    $phenomena          = trim($_POST['phenomena'] ?? '');
    $cause              = trim($_POST['cause'] ?? '');
    $steps_taken        = trim($_POST['steps_taken'] ?? '');

    // Ambil data detail (JSON dari JavaScript)
    $activities = json_decode($_POST['activities_json'] ?? '[]', true);
    $times      = json_decode($_POST['times_json'] ?? '[]', true);
    $models     = json_decode($_POST['models_json'] ?? '[]', true);

    if (empty($doc_no) || empty($date_doc) || empty($customer_id) || empty($type_of_service)) {
        flash_set('error', 'Semua data header (Nomor SR, Tanggal, Customer, Tipe Servis) harus diisi.');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $report_id);
        exit;
    }
    
    $mysqli->begin_transaction();
    $success = false;
    
    try {
        // 1. Update service_reports (Header)
        $stmt_update_header = $mysqli->prepare("
            UPDATE service_reports SET
            doc_no = ?, date_doc = ?, customer_id = ?, po_order_no = ?, prod_code = ?, 
            requested_by = ?, phone = ?, type_of_service = ?, remark_general = ?, 
            phenomena = ?, cause = ?, steps_taken = ?
            WHERE id = ?
        ");
        
        // Tipe data: string, string, integer, string, string, string, string, string, string, string, string, string, integer
        $stmt_update_header->bind_param('ssisssssssssi', 
            $doc_no, $date_doc, $customer_id, $po_order_no, $prod_code, $requested_by, $phone, $type_of_service, 
            $remark_general, $phenomena, $cause, $steps_taken, $report_id
        );
        $stmt_update_header->execute();
        
        // 2. Hapus detail lama (CASCADE DELETE)
        $stmt_delete_act = $mysqli->prepare("DELETE FROM service_report_activities WHERE report_id = ?");
        $stmt_delete_time = $mysqli->prepare("DELETE FROM service_report_times WHERE report_id = ?");
        $stmt_delete_model = $mysqli->prepare("DELETE FROM service_report_models WHERE report_id = ?");

        $stmt_delete_act->bind_param('i', $report_id); $stmt_delete_act->execute();
        $stmt_delete_time->bind_param('i', $report_id); $stmt_delete_time->execute();
        $stmt_delete_model->bind_param('i', $report_id); $stmt_delete_model->execute();
        
        // 3. Simpan service_report_activities (Detail Baru)
        $stmt_act_insert = $mysqli->prepare("
            INSERT INTO service_report_activities 
            (report_id, activity_date, part_number, serial_number, alarm, remark_activity) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($activities as $act) {
            $stmt_act_insert->bind_param('isssss', 
                $report_id, $act['date'], $act['part_number'], $act['serial_number'], $act['alarm'], $act['remark']
            );
            if (!$stmt_act_insert->execute()) {
                throw new Exception("Gagal menyimpan aktivitas: " . $stmt_act_insert->error);
            }
        }
        
        // 4. Simpan service_report_times (Detail Baru)
        $stmt_time_insert = $mysqli->prepare("
            INSERT INTO service_report_times 
            (report_id, date_time, start_time, out_time, end_time, back_time, service_time_hrs, service_time_mins) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($times as $time) {
            $stmt_time_insert->bind_param('issssiii', 
                $report_id, $time['date'], $time['start'], $time['out'], $time['end'], $time['back'], $time['hrs'], $time['mins']
            );
            if (!$stmt_time_insert->execute()) {
                throw new Exception("Gagal menyimpan waktu kerja: " . $stmt_time_insert->error);
            }
        }

        // 5. Simpan service_report_models (Detail Baru)
        $stmt_model_insert = $mysqli->prepare("
            INSERT INTO service_report_models 
            (report_id, model_number, serial_number, mtb, mc_model) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($models as $model) {
            $stmt_model_insert->bind_param('issss', 
                $report_id, $model['model_number'], $model['serial_number'], $model['mtb'], $model['mc_model']
            );
            if (!$stmt_model_insert->execute()) {
                throw new Exception("Gagal menyimpan model mesin: " . $stmt_model_insert->error);
            }
        }
        
        $mysqli->commit();
        $success = true;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        flash_set('error', 'Error saat mengupdate Service Report: ' . $e->getMessage());
    }

    if ($success) {
        flash_set('success', "Service Report **{$doc_no}** berhasil diupdate!");
        header('Location: service_report_list.php'); 
    } else {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $report_id);
    }
    exit;
}

// --------------------------------------------------
// --- Bagian D: HTML Output ---
// --------------------------------------------------
$error_msg = flash_get('error');
$success_msg = flash_get('success'); 
include 'header.php'; 
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">✏️ Edit Service Report: **<?php echo htmlspecialchars($report_data['doc_no']); ?>**</h3>
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
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="report_id" value="<?php echo $report_id; ?>">

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
                                    data-pic="<?php echo htmlspecialchars($c['pic_name'] ?? ''); ?>"
                                    <?php echo ($c['id'] == $report_data['customer_id']) ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($c['name'].' ('.$c['customer_no'].')'); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="requested_by_input">Requested By / PIC</label>
                            <input type="text" name="requested_by" class="form-control" id="requested_by_input" value="<?php echo htmlspecialchars($report_data['requested_by'] ?? ''); ?>" placeholder="Akan terisi otomatis dari data PIC Customer">
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($report_data['phone'] ?? ''); ?>" placeholder="Nomor telepon PIC">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Nomor Service Report (SR)</label>
                            <input type="text" name="doc_no" class="form-control font-weight-bold" value="<?php echo htmlspecialchars($report_data['doc_no']); ?>" required readonly>
                        </div>
                        <div class="form-group">
                            <label>Tanggal Dokumen <span class="text-danger">*</span></label>
                            <input type="date" name="date_doc" class="form-control" value="<?php echo htmlspecialchars($report_data['date_doc']); ?>" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>PO/Order No.</label>
                                <input type="text" name="po_order_no" class="form-control" value="<?php echo htmlspecialchars($report_data['po_order_no'] ?? ''); ?>">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Product Code</label>
                                <input type="text" name="prod_code" class="form-control" value="<?php echo htmlspecialchars($report_data['prod_code'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group mt-3">
                    <label class="d-block">Type of Service <span class="text-danger">*</span></label>
                    <?php foreach ($types_of_service as $type): ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="type_of_service" id="type_<?php echo strtolower($type); ?>" value="<?php echo $type; ?>" required
                            <?php echo ($type == $report_data['type_of_service']) ? 'checked' : ''; ?>>
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
                            <?php if (empty($models_data)): ?>
                            <tr>
                                <td class="item_no">1</td>
                                <td><input type="text" name="model_number[]" class="form-control form-control-sm"></td>
                                <td><input type="text" name="model_serial[]" class="form-control form-control-sm"></td>
                                <td><input type="text" name="model_mtb[]" class="form-control form-control-sm"></td>
                                <td><input type="text" name="model_mc[]" class="form-control form-control-sm"></td>
                                <td><button class="btn btn-danger btn-sm removeRow" type="button"><i class="fas fa-times"></i></button></td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($models_data as $i => $model): ?>
                                <tr>
                                    <td class="item_no"><?php echo $i + 1; ?></td>
                                    <td><input type="text" name="model_number[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($model['model_number']); ?>"></td>
                                    <td><input type="text" name="model_serial[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($model['serial_number']); ?>"></td>
                                    <td><input type="text" name="model_mtb[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($model['mtb']); ?>"></td>
                                    <td><input type="text" name="model_mc[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($model['mc_model']); ?>"></td>
                                    <td><button class="btn btn-danger btn-sm removeRow" type="button"><i class="fas fa-times"></i></button></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <button id="addModelRow" class="btn btn-sm btn-outline-info" type="button"><i class="fas fa-plus"></i> Tambah Model</button>
            </div>
        </div>
        
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-clock"></i> Detail Waktu Kerja <small class="text-muted">(Total Service Time)</small></h5>
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
                            <?php if (empty($times_data)): ?>
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
                            <?php else: ?>
                                <?php foreach ($times_data as $i => $time): 
                                    $time_calc = calculateServiceTimePHP($time['start_time'], $time['out_time'], $time['end_time'], $time['back_time']);
                                ?>
                                <tr>
                                    <td class="item_no"><?php echo $i + 1; ?></td>
                                    <td><input type="date" name="time_date[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($time['date_time']); ?>" required></td>
                                    <td><input type="time" name="time_start[]" class="form-control form-control-sm time-input" value="<?php echo htmlspecialchars($time['start_time']); ?>"></td>
                                    <td><input type="time" name="time_out[]" class="form-control form-control-sm time-input" value="<?php echo htmlspecialchars($time['out_time']); ?>"></td>
                                    <td><input type="time" name="time_end[]" class="form-control form-control-sm time-input" value="<?php echo htmlspecialchars($time['end_time']); ?>"></td>
                                    <td><input type="time" name="time_back[]" class="form-control form-control-sm time-input" value="<?php echo htmlspecialchars($time['back_time']); ?>"></td>
                                    <td class="text-center font-weight-bold total-time"><?php echo $time_calc['hrs'] . ' HRS ' . $time_calc['mins'] . ' MINS'; ?></td>
                                    <td><button class="btn btn-danger btn-sm removeRow" type="button"><i class="fas fa-times"></i></button></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
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
                             <?php if (empty($activities_data)): ?>
                            <tr>
                                <td class="item_no">1</td>
                                <td><input type="date" name="act_date[]" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required></td>
                                <td><input type="text" name="act_part_number[]" class="form-control form-control-sm"></td>
                                <td><input type="text" name="act_serial_number[]" class="form-control form-control-sm"></td>
                                <td><input type="text" name="act_alarm[]" class="form-control form-control-sm"></td>
                                <td><input type="text" name="act_remark[]" class="form-control form-control-sm" required></td>
                                <td><button class="btn btn-danger btn-sm removeRow" type="button"><i class="fas fa-times"></i></button></td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($activities_data as $i => $act): ?>
                                <tr>
                                    <td class="item_no"><?php echo $i + 1; ?></td>
                                    <td><input type="date" name="act_date[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($act['activity_date']); ?>" required></td>
                                    <td><input type="text" name="act_part_number[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($act['part_number']); ?>"></td>
                                    <td><input type="text" name="act_serial_number[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($act['serial_number']); ?>"></td>
                                    <td><input type="text" name="act_alarm[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($act['alarm']); ?>"></td>
                                    <td><input type="text" name="act_remark[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($act['remark_activity']); ?>" required></td>
                                    <td><button class="btn btn-danger btn-sm removeRow" type="button"><i class="fas fa-times"></i></button></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
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
                    <textarea name="phenomena" class="form-control" rows="2" placeholder="Jelaskan masalah yang terjadi"><?php echo htmlspecialchars($report_data['phenomena'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Cause / Penyebab</label>
                    <textarea name="cause" class="form-control" rows="2" placeholder="Jelaskan akar penyebab masalah"><?php echo htmlspecialchars($report_data['cause'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Steps Taken / Langkah Perbaikan</label>
                    <textarea name="steps_taken" class="form-control" rows="2" placeholder="Jelaskan tindakan yang telah dilakukan untuk perbaikan"><?php echo htmlspecialchars($report_data['steps_taken'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Remark (General)</label>
                    <textarea name="remark_general" class="form-control" rows="2" placeholder="Catatan/kesimpulan umum (Opsional)"><?php echo htmlspecialchars($report_data['remark_general'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <div class="mt-4 pt-3 border-top d-flex justify-content-end">
            <button type="button" onclick="prepareAndPrintDraftSR()" class="btn btn-info btn-lg mx-2">
                <i class="fas fa-print"></i> Lihat Draft
            </button>
            <button type="button" onclick="prepareAndSubmitSR()" class="btn btn-primary btn-lg">
                <i class="fas fa-pen-square"></i> Update Service Report
            </button>
        </div>
    </form>
</div> 

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    /* * CATATAN PENTING JAVASCRIPT:
     * Logic JS untuk ADD ROW, REMOVE ROW, REINDEX, CALCULATE TIME, 
     * COLLECT DATA TO JSON, dan PREPARE AND SUBMIT/DRAFT harus sama 
     * dengan yang ada di service_report_create.php.
     * * Saya hanya menyediakan bagian yang unik untuk EDIT (autofill PIC dan reindex)
     * dan fungsi-fungsi penting lainnya untuk kelengkapan.
    */

    // --- Helper Functions (Duplicated from Create for completeness) ---

    function getTableId($element) {
        return $element.closest("table").attr('id');
    }
    function reindexTable(tableId) {
        $(`#${tableId} tbody tr`).each(function(i){
            $(this).find(".item_no").text(i + 1);
        });
    }

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

    // --- Data Mapping (MUST MATCH PHP INSERT/UPDATE LOGIC) ---
    const activityMap = {
        date: 'input[name="act_date[]"]',
        part_number: 'input[name="act_part_number[]"]',
        serial_number: 'input[name="act_serial_number[]"]',
        alarm: 'input[name="act_alarm[]"]',
        remark: 'input[name="act_remark[]"]' 
    };

    const timeMap = {
        date: 'input[name="time_date[]"]', 
        start: 'input[name="time_start[]"]', 
        out: 'input[name="time_out[]"]', 
        end: 'input[name="time_end[]"]', 
        back: 'input[name="time_back[]"]', 
        hrs: '.total-time', 
        mins: '.total-time' 
    };
    
    const modelMap = {
        model_number: 'input[name="model_number[]"]',
        serial_number: 'input[name="model_serial[]"]',
        mtb: 'input[name="model_mtb[]"]',
        mc_model: 'input[name="model_mc[]"]'
    };

    function collectDataToJson(tableId, dataMap) {
        let data = [];
        let rows = $(`#${tableId} tbody tr`);
        
        if (rows.length === 0) return data;

        rows.each(function() {
            let row = {};
            let $row = $(this);
            
            for (const key in dataMap) {
                const selector = dataMap[key];
                let value = $row.find(selector).val();

                if (tableId === 'timesTable' && (key === 'hrs' || key === 'mins')) {
                    const start = $row.find('input[name="time_start[]"]').val();
                    const out = $row.find('input[name="time_out[]"]').val();
                    const end = $row.find('input[name="time_end[]"]').val();
                    const back = $row.find('input[name="time_back[]"]').val();
                    const totalMins = calculateServiceTime(start, out, end, back);

                    value = (key === 'hrs') ? Math.floor(totalMins / 60) : totalMins % 60;
                }
                
                row[key] = value;
            }
            
            // Filtering for empty rows
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
    
    // --- Initial Setup & Listeners ---

    $(document).ready(function() {
        // 1. Autofill Requested By / PIC
        $("#customer_select").on("change", function() {
            const selectedOption = $(this).find('option:selected');
            const picName = selectedOption.data('pic');
            $("#requested_by_input").val(picName || '');
        }).trigger('change');
        
        // 2. Initial Reindex (Penting karena data dimuat dari PHP)
        reindexTable('activitiesTable');
        reindexTable('timesTable');
        reindexTable('modelsTable');

        // 3. Listener for time input changes (for dynamic calculation)
        $(document).on('change', '#timesTable input.time-input', function() {
            updateRowServiceTime($(this).closest('tr'));
        });
    });


    // --- Add Row Logic (Same as Create) ---

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
    });

    // --- Submit and Draft Functions (Adjusted for Update) ---

    function prepareAndSubmitSR() {
        const activitiesData = collectDataToJson('activitiesTable', activityMap);
        const timesData = collectDataToJson('timesTable', timeMap);
        const modelsData = collectDataToJson('modelsTable', modelMap);

        const customerId = $("#customer_select").val();
        const typeOfService = $('input[name="type_of_service"]:checked').val();

        if (!customerId || !typeOfService) {
            alert("Customer dan Type of Service harus diisi.");
            return false;
        }

        if (activitiesData.length === 0) {
            alert("Minimal satu baris Detail Aktivitas/Remark harus diisi.");
            return false;
        }
        
        if (timesData.length === 0) {
            alert("Minimal satu baris Detail Waktu Kerja (Start & End Time) harus diisi.");
            return false;
        }
        
        $("#activities_json_input").val(JSON.stringify(activitiesData));
        $("#times_json_input").val(JSON.stringify(timesData));
        $("#models_json_input").val(JSON.stringify(modelsData));

        if(confirm("Apakah Anda yakin ingin MENGUPDATE Service Report ini? Data detail lama akan DITIMPA.")) {
            $("#serviceReportForm").submit();
        }
    }

    function prepareAndPrintDraftSR() {
        // Collect data as done for submission
        const activitiesData = collectDataToJson('activitiesTable', activityMap);
        const timesData = collectDataToJson('timesTable', timeMap);
        const modelsData = collectDataToJson('modelsTable', modelMap);
        const customerId = $("#customer_select").val();
        
        if (!customerId) {
            alert("Customer harus diisi untuk melihat draft.");
            return false;
        }
        
        // Collect all header data for print URL
        const printParams = {
            doc_no: $('input[name="doc_no"]').val() + ' (DRAFT EDIT)', // Perubahan pada penanda draft
            date_doc: $('input[name="date_doc"]').val(),
            customer_id: customerId,
            po_order_no: $('input[name="po_order_no"]').val(),
            prod_code: $('input[name="prod_code"]').val(),
            requested_by: $('input[name="requested_by"]').val(),
            phone: $('input[name="phone"]').val(),
            type_of_service: $('input[name="type_of_service"]:checked').val(),
            remark_general: $('textarea[name="remark_general"]').val(),
            phenomena: $('textarea[name="phenomena"]').val(),
            cause: $('textarea[name="cause"]').val(),
            steps_taken: $('textarea[name="steps_taken"]').val(),
            
            activities_json: JSON.stringify(activitiesData),
            times_json: JSON.stringify(timesData),
            models_json: JSON.stringify(modelsData)
        };

        const queryString = $.param(printParams);
        const printUrl = 'service_report_print.php?' + queryString;

        window.open(printUrl, '_blank');
    }

</script>

<style>
/* ... (Style tetap sama) ... */
.form-control-sm {
    height: calc(1.5em + 0.5rem + 2px);
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
    line-height: 1.5;
}
.table-sm th, .table-sm td {
    padding: 0.3rem;
    vertical-align: middle;
}
.card-header h5 {
    font-size: 1.15rem;
}
</style>

<?php 
include 'footer.php'; 
?>