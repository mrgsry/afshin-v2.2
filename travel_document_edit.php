<?php
// =========================================================================
// PHP LOGIC: TRAVEL DOCUMENT EDIT (UPDATE) - DENGAN TOMBOL PRINT
// =========================================================================

require_once 'functions.php'; 
require_login(); 

if (!$mysqli) {
    die("Koneksi database gagal!");
}

$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($document_id === 0) {
    flash_set('error', 'ID Dokumen tidak valid.');
    header('Location: travel_document_list.php');
    exit;
}

// -----------------------------------------------------------------
// AMBIL DATA DOKUMEN SAAT INI (HEADER)
// -----------------------------------------------------------------
$stmt_header = mysqli_prepare($mysqli, "SELECT * FROM travel_documents WHERE id = ?");
mysqli_stmt_bind_param($stmt_header, 'i', $document_id);
mysqli_stmt_execute($stmt_header);
$result_header = mysqli_stmt_get_result($stmt_header);
$document_data = mysqli_fetch_assoc($result_header);

if (!$document_data) {
    flash_set('error', 'Dokumen tidak ditemukan.');
    header('Location: travel_document_list.php');
    exit;
}

// -----------------------------------------------------------------
// AMBIL DATA ITEM SAAT INI (DETAIL)
// -----------------------------------------------------------------
$items_query = mysqli_query($mysqli, "
    SELECT item_desc, qty, unit, remarks 
    FROM travel_document_items 
    WHERE document_id = {$document_id} 
    ORDER BY item_no ASC
");
$document_items = [];
while ($item = mysqli_fetch_assoc($items_query)) {
    $document_items[] = [
        'item_desc' => $item['item_desc'],
        'qty'       => (int)$item['qty'],
        'unit'      => $item['unit'],
        'remarks'   => $item['remarks'] 
    ];
}
$document_items_json = json_encode($document_items);

// -----------------------------------------------------------------
// DATA PENDUKUNG
// -----------------------------------------------------------------
$customers = mysqli_query($mysqli, "SELECT * FROM customers ORDER BY name ASC");
$satuans = ['Unit', 'Pcs', 'Pack', 'Set', 'Koli', 'Box', 'Buah', 'Pallet', 'Meter', 'Kg', 'Liter', 'Roll'];

// Ambil data customer saat ini untuk tampilan info
$current_customer_query = mysqli_query($mysqli, "SELECT * FROM customers WHERE id = " . $document_data['customer_id']);
$current_customer = mysqli_fetch_assoc($current_customer_query);

// ====================================================================
// LOGIKA POST: UPDATE KE DATABASE
// ====================================================================

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update'){
    $travel_no   = $_POST['travel_no'] ?? null; 
    $customer_id = (int)($_POST['customer_id'] ?? 0); 
    $date_doc    = $_POST['date_doc'] ?? null;
    $po_number   = $_POST['po_number'] ?? null;
    $prod_code   = $_POST['prod_code'] ?? null;
    $ship_by     = $_POST['ship_by'] ?? null; 
    $dell_to     = $_POST['dell_to'] ?? null; 
    $note        = $_POST['note'] ?? null;
    
    $items_json  = $_POST['items_json'] ?? '[]';
    $items = json_decode($items_json, true);

    if (empty($travel_no) || empty($date_doc) || empty($customer_id) || empty($items)) {
        flash_set('error', 'Semua data header (termasuk Customer, Nomor SJ) dan minimal satu item harus diisi.');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $document_id);
        exit;
    }
    
    $mysqli->begin_transaction();
    $success = false;
    
    try {
        // Update header
        $stmt_header = mysqli_prepare($mysqli, "
            UPDATE travel_documents SET
            customer_id = ?, date_doc = ?, po_number = ?, prod_code = ?, ship_by = ?, dell_to = ?, note = ?
            WHERE id = ?
        ");
        
        mysqli_stmt_bind_param($stmt_header, 'issssssi', 
            $customer_id, $date_doc, $po_number, $prod_code, $ship_by, $dell_to, $note, $document_id
        );
        
        if (!mysqli_stmt_execute($stmt_header)) {
            throw new Exception("Gagal mengupdate dokumen: " . mysqli_stmt_error($stmt_header));
        }
        
        // Hapus item lama
        if (!$mysqli->query("DELETE FROM travel_document_items WHERE document_id = {$document_id}")) {
            throw new Exception("Gagal menghapus item lama: " . mysqli_error($mysqli));
        }
        
        // Simpan item baru
        $stmt_item = mysqli_prepare($mysqli, "
            INSERT INTO travel_document_items 
            (document_id, item_no, item_desc, qty, unit, remarks) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($items as $index => $item) {
            $item_no = $index + 1;
            $item_desc = $item['item_desc'] ?? '';
            $qty = $item['qty'] ?? 0;
            $unit = $item['unit'] ?? '';
            $remarks = $item['remarks'] ?? '';

            mysqli_stmt_bind_param($stmt_item, 'iisiss', 
                $document_id, $item_no, $item_desc, $qty, $unit, $remarks
            );
            
            if (!mysqli_stmt_execute($stmt_item)) {
                throw new Exception("Gagal menyimpan item: " . mysqli_stmt_error($stmt_item));
            }
        }
        
        $mysqli->commit();
        $success = true;
        
    } catch (Exception $e) {
        $mysqli->rollback();
        flash_set('error', 'Error: ' . $e->getMessage());
    }

    if ($success) {
        flash_set('success', "Surat Jalan <strong>{$travel_no}</strong> berhasil diupdate!");
        header('Location: travel_document_list.php'); 
    } else {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $document_id);
    }
    exit;
}

// ====================================================================
// HTML OUTPUT
// ====================================================================

$error_msg = flash_get('error');
$success_msg = flash_get('success'); 
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Surat Jalan</title>
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --border-radius: 12px;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s ease;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fb;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        .document-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .document-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 25px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.2);
        }
        
        .document-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .document-header h1 i {
            font-size: 32px;
        }
        
        .document-header p {
            margin: 10px 0 0;
            opacity: 0.9;
            font-size: 16px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 15px;
            font-weight: 500;
            animation: fadeIn 0.5s ease;
        }
        
        .alert-danger {
            background: rgba(247, 37, 133, 0.1);
            color: #f72585;
            border-left: 5px solid #f72585;
        }
        
        .alert-success {
            background: rgba(76, 201, 240, 0.1);
            color: #4cc9f0;
            border-left: 5px solid #4cc9f0;
        }
        
        .document-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-gray);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: var(--primary);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 15px;
            transition: var(--transition);
            box-sizing: border-box;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .form-control[readonly] {
            background-color: var(--light);
            cursor: not-allowed;
            font-weight: bold;
            color: var(--dark);
        }
        
        .input-group {
            display: flex;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--light-gray);
            transition: var(--transition);
        }
        
        .input-group:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .input-group-prepend {
            background-color: var(--light);
            color: var(--gray);
            padding: 12px 15px;
            border-right: 1px solid var(--light-gray);
            font-weight: 600;
            min-width: 60px;
            text-align: center;
        }
        
        .input-group .form-control {
            border: none;
            border-radius: 0;
            padding-left: 15px;
        }
        
        .items-container {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .items-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 18px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .items-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .items-table th {
            background-color: #f8f9fa;
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
            border-bottom: 2px solid var(--light-gray);
        }
        
        .items-table td {
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
            vertical-align: top;
        }
        
        .items-table tr:hover {
            background-color: rgba(67, 97, 238, 0.03);
        }
        
        .items-table input, .items-table select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .items-table input:focus, .items-table select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.1);
        }
        
        .item-no {
            width: 50px;
            text-align: center;
            font-weight: 600;
            color: var(--gray);
        }
        
        .item-desc {
            min-width: 300px;
        }
        
        .item-qty {
            width: 100px;
        }
        
        .item-qty input {
            text-align: center;
        }
        
        .item-unit {
            width: 120px;
        }
        
        .item-remarks {
            width: 200px;
        }
        
        .item-actions {
            width: 100px;
            text-align: center;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
            transform: translateY(-2px);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #218838, #1e9e8a);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
            transform: translateY(-2px);
        }
        
        .btn-info {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }
        
        .btn-info:hover {
            background: linear-gradient(135deg, #138496, #117a8b);
            box-shadow: 0 5px 15px rgba(23, 162, 184, 0.3);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #495057, #6c757d);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #f72585, #b5179e);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #b5179e, #f72585);
            box-shadow: 0 5px 15px rgba(247, 37, 133, 0.3);
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 14px;
        }
        
        .btn-block {
            width: 100%;
        }
        
        .actions-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--light-gray);
        }
        
        .info-note {
            background: rgba(76, 201, 240, 0.1);
            border-left: 4px solid #4cc9f0;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 14px;
            color: #4cc9f0;
        }
        
        .info-note i {
            margin-right: 8px;
        }
        
        .customer-info-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid var(--success);
        }
        
        .customer-info-card h5 {
            margin-top: 0;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .customer-info-card p {
            margin: 8px 0;
            font-size: 14px;
        }
        
        .system-info-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid var(--warning);
        }
        
        .system-info-card h6 {
            margin-top: 0;
            color: var(--warning);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .document-container {
                padding: 15px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .item-desc {
                min-width: 200px;
            }
        }
        
        @media (max-width: 768px) {
            .document-header {
                padding: 20px;
            }
            
            .document-card {
                padding: 20px;
            }
            
            .items-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .items-table {
                display: block;
                overflow-x: auto;
            }
            
            .items-table th, .items-table td {
                min-width: 120px;
            }
            
            .item-desc {
                min-width: 250px;
            }
            
            .actions-footer {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
                margin-bottom: 10px;
            }
        }
        
        @media (max-width: 576px) {
            .document-container {
                padding: 10px;
            }
            
            .document-header h1 {
                font-size: 24px;
            }
            
            .section-title {
                font-size: 16px;
            }
            
            .items-table th, .items-table td {
                padding: 12px 10px;
                font-size: 13px;
            }
            
            .items-table input, .items-table select {
                padding: 8px 10px;
                font-size: 13px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="document-container">
        <div class="document-header">
            <h1><i class="fas fa-edit"></i> Edit Surat Jalan</h1>
            <p>Perbarui data surat jalan <strong><?php echo htmlspecialchars($document_data['travel_no']); ?></strong></p>
        </div>
        
        <?php if($error_msg): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>
        
        <?php if($success_msg): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>
        
        <!-- System Information -->
        <div class="system-info-card">
            <h6><i class="fas fa-info-circle"></i> Informasi Sistem</h6>
            <div class="row">
                <div class="col-md-4">
                    <small><strong>Nomor Surat Jalan:</strong> <?php echo htmlspecialchars($document_data['travel_no']); ?></small>
                </div>
                <div class="col-md-4">
                    <small><strong>Dibuat:</strong> <?php echo date('d/m/Y', strtotime($document_data['date_doc'])); ?></small>
                </div>
                <div class="col-md-4">
                    <small><strong>Terakhir Diupdate:</strong> <?php echo isset($document_data['updated_at']) ? date('d/m/Y H:i', strtotime($document_data['updated_at'])) : '-'; ?></small>
                </div>
            </div>
        </div>
        
        <form id="documentForm" method="POST" action="">
            <input type="hidden" name="items_json" id="items_json_input"> 
            <input type="hidden" name="action" id="action_input" value="update">

            <!-- Header Information -->
            <div class="document-card">
                <div class="section-title">
                    <i class="fas fa-info-circle"></i> Informasi Surat Jalan
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="travel_no_input"><i class="fas fa-hashtag"></i> Nomor Surat Jalan *</label>
                        <input type="text" name="travel_no" id="travel_no_input" class="form-control" 
                            value="<?php echo htmlspecialchars($document_data['travel_no']); ?>" 
                            required 
                            readonly>
                        <small class="form-text text-muted">Nomor sudah ditetapkan dan tidak dapat diubah</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_doc_input"><i class="fas fa-calendar-day"></i> Tanggal Dokumen *</label>
                        <input type="date" name="date_doc" id="date_doc_input" class="form-control" 
                               value="<?php echo htmlspecialchars($document_data['date_doc']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="customer_select"><i class="fas fa-user"></i> Customer *</label>
                        <select name="customer_id" class="form-control" id="customer_select" required>
                            <option value="">-- Pilih Customer --</option>
                            <?php mysqli_data_seek($customers, 0); ?>
                            <?php while($c = mysqli_fetch_assoc($customers)): ?>
                            <option value="<?php echo $c['id']; ?>" 
                                <?php echo ($c['id'] == $document_data['customer_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name'] . ' (' . $c['customer_no'] . ')'); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="po_number_input"><i class="fas fa-file-contract"></i> Nomor PO</label>
                        <input type="text" name="po_number" id="po_number_input" class="form-control" 
                               value="<?php echo htmlspecialchars($document_data['po_number'] ?? ''); ?>"
                               placeholder="Masukkan Nomor PO">
                    </div>
                    
                    <div class="form-group">
                        <label for="prod_code_input"><i class="fas fa-barcode"></i> Product Code (Opsional)</label>
                        <input type="text" name="prod_code" id="prod_code_input" class="form-control" 
                               value="<?php echo htmlspecialchars($document_data['prod_code'] ?? ''); ?>"
                               placeholder="Contoh: C001/01/2025">
                    </div>
                    
                    <div class="form-group">
                        <label for="ship_by_input"><i class="fas fa-shipping-fast"></i> Ship By (Pengiriman)</label>
                        <input type="text" name="ship_by" id="ship_by_input" class="form-control" 
                               value="<?php echo htmlspecialchars($document_data['ship_by'] ?? ''); ?>"
                               placeholder="Contoh: JNE/Internal/Ambil Sendiri">
                    </div>
                    
                    <div class="form-group">
                        <label for="dell_to_input"><i class="fas fa-map-marker-alt"></i> Deliver To (Alamat Kirim)</label>
                        <input type="text" name="dell_to" id="dell_to_input" class="form-control" 
                               value="<?php echo htmlspecialchars($document_data['dell_to'] ?? ''); ?>"
                               placeholder="Isi alamat kirim jika berbeda dari alamat customer">
                    </div>
                </div>
            </div>
            
            <!-- Customer Info Display -->
            <?php if($current_customer): ?>
            <div class="customer-info-card">
                <h5><i class="fas fa-address-card"></i> Informasi Customer Saat Ini</h5>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Nama:</strong> <?php echo htmlspecialchars($current_customer['name']); ?></p>
                        <p><strong>No. Customer:</strong> <?php echo htmlspecialchars($current_customer['customer_no']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Alamat:</strong> <?php echo htmlspecialchars($current_customer['address'] ?? '-'); ?></p>
                        <p><strong>Telepon:</strong> <?php echo htmlspecialchars($current_customer['telephone'] ?? '-'); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Items Section -->
            <div class="items-container">
                <div class="items-header">
                    <h3><i class="fas fa-boxes"></i> Daftar Barang / Items</h3>
                    <button type="button" id="addRowDoc" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Tambah Item
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table class="items-table" id="itemsTableDoc">
                        <thead>
                            <tr>
                                <th class="item-no">#</th>
                                <th class="item-desc">Description *</th>
                                <th class="item-qty">Qty *</th>
                                <th class="item-unit">Unit *</th>
                                <th class="item-remarks">Remarks</th>
                                <th class="item-actions">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Items akan di-load oleh JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                <div class="info-note">
                    <i class="fas fa-lightbulb"></i> <strong>Tip:</strong> Minimal satu item harus diisi. Item pertama tidak dapat dihapus jika hanya ada satu item.
                </div>
            </div>
            
            <!-- Notes Section -->
            <div class="document-card">
                <div class="section-title">
                    <i class="fas fa-sticky-note"></i> Catatan Tambahan
                </div>
                
                <div class="form-group">
                    <label for="note_input"><i class="fas fa-edit"></i> Notes / Keterangan (Opsional)</label>
                    <textarea name="note" id="note_input" class="form-control" rows="3" 
                              placeholder="Masukkan catatan tambahan atau instruksi khusus..."><?php echo htmlspecialchars($document_data['note'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="actions-footer">
                <a href="travel_document_list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                </a>
                
                <div>
                    <button type="button" onclick="prepareAndPrintDraft()" class="btn btn-info">
                        <i class="fas fa-print"></i> Print Preview
                    </button>
                    
                    <button type="button" onclick="prepareAndSubmit('update')" class="btn btn-success">
                        <i class="fas fa-save"></i> Update Surat Jalan
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    // Variabel JS untuk dropdown satuan
    const SATUAN_DROPDOWN_HTML = `<?php 
        $html = '<select name="unit[]" class="form-control" required><option value="">-- pilih --</option>';
        foreach($satuans as $s) {
            $html .= '<option value="'.htmlspecialchars($s).'">'.htmlspecialchars($s).'</option>';
        }
        $html .= '</select>';
        echo addslashes($html);
    ?>`;

    // Data item saat ini dari PHP
    const CURRENT_ITEMS = <?php echo $document_items_json; ?>;
    const SATUANS_LIST = <?php echo json_encode($satuans); ?>;

    /* ========== LOAD EXISTING ITEMS ========== */
    function loadExistingItems(items) {
        const $tableBody = $("#itemsTableDoc tbody");
        $tableBody.empty();

        if (items.length === 0) {
            addNewRow();
            return;
        }

        items.forEach((item, index) => {
            const idx = index + 1;
            const selectedUnit = item.unit || '';
            let satuanOptions = '<option value="">-- pilih --</option>';
            
            SATUANS_LIST.forEach(satuan => {
                const selected = (satuan === selectedUnit) ? 'selected' : '';
                satuanOptions += `<option value="${satuan}" ${selected}>${satuan}</option>`;
            });

            let tr = `
                <tr>
                    <td class="item-no">${idx}</td>
                    <td class="item-desc">
                        <input name="item_desc[]" class="form-control" required 
                               value="${item.item_desc ? item.item_desc.replace(/"/g, '&quot;') : ''}">
                    </td>
                    <td class="item-qty">
                        <input type="number" name="qty[]" class="form-control qty" 
                               value="${item.qty || 1}" min="1" required>
                    </td>
                    <td class="item-unit">
                        <select name="unit[]" class="form-control" required>${satuanOptions}</select>
                    </td>
                    <td class="item-remarks">
                        <input name="remarks[]" class="form-control" 
                               value="${item.remarks ? item.remarks.replace(/"/g, '&quot;') : ''}">
                    </td>
                    <td class="item-actions">
                        <button class="btn btn-danger btn-sm removeRow" type="button" title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            $tableBody.append(tr);
        });

        // Disable delete button on first row if only one row
        if ($("#itemsTableDoc tbody tr").length === 1) {
            $("#itemsTableDoc tbody tr:first .removeRow").prop('disabled', true);
        }
    }

    /* ========== VALIDASI DAN SUBMIT ========== */
    function prepareAndSubmit(action) {
        const customerId = $("#customer_select").val();
        const dateDoc = $("#date_doc_input").val();
        const travelNo = $("#travel_no_input").val();
        
        if (!customerId || !dateDoc || !travelNo) {
            alert("Customer, Tanggal Dokumen, dan Nomor Surat Jalan harus diisi.");
            return false;
        }

        let itemsData = [];
        let isValid = true;
        
        $("#itemsTableDoc tbody tr").each(function() {
            const itemDesc = $(this).find('input[name="item_desc[]"]').val().trim();
            const qty = $(this).find('input[name="qty[]"]').val();
            const unit = $(this).find('select[name="unit[]"]').val();
            const remarks = $(this).find('input[name="remarks[]"]').val();

            if (itemDesc === '' && qty === '1' && unit === '') {
                return true;
            }

            if (itemDesc !== '' && (!qty || !unit || parseInt(qty) < 1)) {
                isValid = false;
                $(this).addClass('table-danger');
                alert('Description, Qty (> 0), dan Unit harus diisi lengkap untuk semua baris yang tidak kosong.');
                return false;
            } else if (itemDesc === '' && (qty !== '1' || unit !== '')) {
                isValid = false;
                $(this).addClass('table-danger');
                alert('Description tidak boleh kosong jika Qty/Unit diisi.');
                return false;
            }

            itemsData.push({
                item_desc: itemDesc,
                qty: parseInt(qty),
                unit: unit,
                remarks: remarks
            });
        });

        itemsData = itemsData.filter(item => item.item_desc !== '');

        if (!isValid || itemsData.length === 0) {
            if(itemsData.length === 0) alert("Minimal harus ada satu item yang diisi.");
            return false;
        }

        // Simpan ke hidden input
        $("#items_json_input").val(JSON.stringify(itemsData));
        
        // Konfirmasi sebelum submit
        if (confirm("Apakah Anda yakin ingin mengupdate Surat Jalan ini?")) {
            $("#action_input").val(action);
            $("#documentForm").submit();
        }
        return true;
    }

    /* ========== PRINT PREVIEW ========== */
    function prepareAndPrintDraft() {
        const customerId = $("#customer_select").val();
        const dateDoc = $("#date_doc_input").val();
        const travelNo = $("#travel_no_input").val();
        const poNumber = $("#po_number_input").val();
        const note = $("#note_input").val();
        const prodCode = $("#prod_code_input").val();
        const shipBy = $("#ship_by_input").val();
        const dellTo = $("#dell_to_input").val();

        if (!customerId || !dateDoc || !travelNo) {
            alert("Customer, Tanggal Dokumen, dan Nomor Surat Jalan harus diisi untuk preview.");
            return false;
        }

        let itemsData = [];
        $("#itemsTableDoc tbody tr").each(function() {
            const itemDesc = $(this).find('input[name="item_desc[]"]').val().trim();
            const qty = $(this).find('input[name="qty[]"]').val();
            const unit = $(this).find('select[name="unit[]"]').val();
            const remarks = $(this).find('input[name="remarks[]"]').val();

            if (itemDesc !== '') {
                itemsData.push({
                    item_desc: itemDesc,
                    qty: parseInt(qty) || 0,
                    unit: unit,
                    remarks: remarks
                });
            }
        });

        if(itemsData.length === 0) {
            alert("Minimal harus ada satu item yang diisi untuk preview.");
            return false;
        }

        const printParams = {
            customer_id: customerId,
            date_doc: dateDoc,
            po_number: poNumber,
            note: note,
            items_json: JSON.stringify(itemsData),
            prod_code: prodCode,
            ship_by: shipBy,
            delv_to: dellTo,
            travel_no: travelNo,
            draft: true,
            edit_mode: true,
            document_id: <?php echo $document_id; ?>
        };

        const queryString = $.param(printParams);
        const printUrl = 'travel_document_print.php?' + queryString;
        window.open(printUrl, '_blank');
        return false;
    }

    /* ========== ADD NEW ROW ========== */
    function addNewRow() {
        let idx = $("#itemsTableDoc tbody tr").length + 1;
        let tr = `
            <tr>
                <td class="item-no">${idx}</td>
                <td class="item-desc">
                    <input name="item_desc[]" class="form-control" required 
                           placeholder="Masukkan deskripsi barang">
                </td>
                <td class="item-qty">
                    <input type="number" name="qty[]" class="form-control qty" 
                           value="1" min="1" required>
                </td>
                <td class="item-unit">${SATUAN_DROPDOWN_HTML.replace(/\\'/g, "'")}</td>
                <td class="item-remarks">
                    <input name="remarks[]" class="form-control" 
                           placeholder="Keterangan tambahan">
                </td>
                <td class="item-actions">
                    <button class="btn btn-danger btn-sm removeRow" type="button" title="Hapus">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        $("#itemsTableDoc tbody").append(tr);
        
        // Enable delete button on first row if there are multiple rows
        if ($("#itemsTableDoc tbody tr").length > 1) {
            $("#itemsTableDoc tbody tr:first .removeRow").prop('disabled', false);
        }
        
        // Scroll to new row
        $('html, body').animate({
            scrollTop: $("#itemsTableDoc tbody tr:last").offset().top - 100
        }, 500);
        
        showToast('Baris item baru ditambahkan', 'info');
    }

    /* ========== EVENT HANDLERS ========== */
    $(document).ready(function() {
        // Load existing items
        loadExistingItems(CURRENT_ITEMS);
        
        // Add row button
        $("#addRowDoc").click(addNewRow);

        // Remove row
        $(document).on("click", ".removeRow", function(){
            if ($("#itemsTableDoc tbody tr").length <= 1) {
                alert("Minimal harus ada 1 baris item!");
                return;
            }
            
            $(this).closest("tr").remove();
            
            // Update row numbers
            $("#itemsTableDoc tbody tr").each(function(i){
                $(this).find(".item-no").text(i + 1);
            });
            
            // Disable delete button on first row if only one row remains
            if ($("#itemsTableDoc tbody tr").length === 1) {
                $("#itemsTableDoc tbody tr:first .removeRow").prop('disabled', true);
            }
            
            showToast('Baris item dihapus', 'warning');
        });

        // Auto-focus on first description field
        $('#itemsTableDoc input[name="item_desc[]"]:first').focus();
    });

    /* ========== UTILITY FUNCTIONS ========== */
    function showToast(message, type = 'info') {
        // Remove existing toasts
        $('.toast-alert').remove();
        
        const icon = type === 'success' ? 'check-circle' : 
                     type === 'warning' ? 'exclamation-triangle' : 
                     type === 'error' ? 'times-circle' : 'info-circle';
        
        const bgColor = type === 'success' ? 'bg-success' : 
                        type === 'warning' ? 'bg-warning' : 
                        type === 'error' ? 'bg-danger' : 'bg-info';
        
        const toast = $(`
            <div class="toast-alert">
                <div class="toast ${bgColor} text-white show" role="alert" 
                     style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                    <div class="toast-body">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-${icon} mr-2"></i>
                            <span>${message}</span>
                            <button type="button" class="btn-close btn-close-white ml-auto" 
                                    onclick="$(this).closest('.toast').remove()"></button>
                        </div>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(toast);
        setTimeout(() => toast.remove(), 4000);
    }
    </script>

    <!-- Additional CSS -->
    <style>
    .table-danger {
        background-color: rgba(247, 37, 133, 0.1) !important;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .document-card, .items-container {
        animation: fadeIn 0.6s ease-out;
    }
    
    .toast {
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        animation: slideInRight 0.3s ease;
    }
    
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    .btn:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.3);
    }
    
    .system-info-card small {
        display: block;
        margin-bottom: 5px;
    }
    </style>
</body>
</html>

<?php include 'footer.php'; ?>