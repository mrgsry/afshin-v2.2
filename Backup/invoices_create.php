<?php
// =========================================================================
// BOOTSTRAP
// =========================================================================
require_once 'db.php';
require_once 'functions.php';
require_login();

if (!isset($mysqli) || !$mysqli) {
    die("Koneksi database gagal!");
}

// =========================================================================
// AJAX HANDLER (WAJIB DI PALING ATAS)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    header('Content-Type: application/json');

    if ($_POST['action'] === 'generate_invoice_no') {
        $date = $_POST['date_quot'] ?? date('Y-m-d');
        echo json_encode([
            'invoice_no' => generate_invoice_no($mysqli, $date)
        ]);
        exit;
    }

    if ($_POST['action'] === 'get_customer_data') {
        $cid = (int)($_POST['customer_id'] ?? 0);

        if ($cid > 0) {
            $stmt = $mysqli->prepare("SELECT name,address,phone,email FROM customers WHERE id=?");
            $stmt->bind_param('i', $cid);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($row = $res->fetch_assoc()) {
                echo json_encode(['success'=>true,'data'=>$row]);
                exit;
            }
        }

        echo json_encode(['success'=>false]);
        exit;
    }
}

// =========================================================================
// UTILITIES
// =========================================================================
if (!function_exists('bulan_romawi')) {
    function bulan_romawi($m) {
        return [1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V',6=>'VI',7=>'VII',8=>'VIII',9=>'IX',10=>'X',11=>'XI',12=>'XII'][$m] ?? '';
    }
}

function generate_invoice_no($mysqli, $date) {
    $y = date('Y', strtotime($date));
    $m = date('n', strtotime($date));
    $r = bulan_romawi($m);

    $q = $mysqli->query("
        SELECT invoice_no FROM invoices
        WHERE YEAR(created_at)='$y'
        ORDER BY CAST(SUBSTRING_INDEX(invoice_no,'/',-1) AS UNSIGNED) DESC
        LIMIT 1
    ");

    $n = 1;
    if ($q && $d = $q->fetch_assoc()) {
        $p = explode('/', $d['invoice_no']);
        $n = intval(end($p)) + 1;
    }

    return "INV/ART/$y/$r/" . str_pad($n, 3, '0', STR_PAD_LEFT);
}

// =========================================================================
// DATA FETCH
// =========================================================================
$customers_result = $mysqli->query("SELECT id,name,customer_no FROM customers ORDER BY name");
$satuans = ['Unit','Pcs','Pack','Set','Koli','Box','LOT','Pallet','Meter','Kg','Liter','Hour'];

// =========================================================================
// FORM SUBMIT (ONLY REAL SUBMIT)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {

    $mysqli->begin_transaction();

    try {
        $customer_id = (int)$_POST['customer_id'];
        $created_at = $_POST['created_at'];
        $faktur_inv = trim($_POST['faktur_inv']);

        if (!$customer_id || !$created_at || !$faktur_inv) {
            throw new Exception("Data wajib belum lengkap.");
        }

        $invoice_no = generate_invoice_no($mysqli, $created_at);

        $stmt = $mysqli->prepare("
            INSERT INTO invoices
            (invoice_no,customer_id,note,subtotal,discount,ppn,pph,total,faktur_inv,date_jatuh_tempo,po_number,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        $stmt->bind_param(
            'sisddddddsss',
            $invoice_no,
            $customer_id,
            $_POST['note'],
            $_POST['subtotal'],
            $_POST['discount'],
            $_POST['ppn_raw_value'],
            $_POST['pph_raw_value'],
            $_POST['total'],
            $faktur_inv,
            $_POST['date_jatuh_tempo'],
            $_POST['po_number'],
            $created_at
        );

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        $iid = $mysqli->insert_id;

        foreach ($_POST['description'] as $i => $desc) {
            if (trim($desc) === '') continue;

            $stmt2 = $mysqli->prepare("
                INSERT INTO invoice_items
                (invoice_id,item_no,description,qty,satuan,unit_price,amount)
                VALUES (?,?,?,?,?,?,?)
            ");

            $no = $i + 1;
            $stmt2->bind_param(
                'iisissd',
                $iid,
                $no,
                $desc,
                $_POST['qty'][$i],
                $_POST['satuan'][$i],
                $_POST['unit_price_raw'][$i],
                $_POST['amount_raw'][$i]
            );

            if (!$stmt2->execute()) {
                throw new Exception($stmt2->error);
            }
        }

        $mysqli->commit();
        flash_set("Invoice $invoice_no berhasil dibuat.");
        header('Location: invoices_list.php');
        exit;

    } catch (Exception $e) {
        $mysqli->rollback();
        flash_set("ERROR: " . $e->getMessage());
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
}

// =========================================================================
// VIEW
// =========================================================================
$error_msg   = flash_get();
$success_msg = $error_msg && strpos($error_msg,'ERROR')===false ? $error_msg : '';
$error_msg   = strpos($error_msg,'ERROR')!==false ? $error_msg : '';

$default_invoice_no = generate_invoice_no($mysqli, date('Y-m-d'));

include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Invoice</title>
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
        
        .invoice-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .invoice-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 25px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.2);
        }
        
        .invoice-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .invoice-header h1 i {
            font-size: 32px;
        }
        
        .invoice-header p {
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
        
        .invoice-card {
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
        
        .item-satuan {
            width: 120px;
        }
        
        .item-price, .item-amount {
            width: 180px;
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
        
        .summary-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .summary-item {
            padding: 20px;
            border-radius: 8px;
            background: var(--light);
            border-left: 4px solid var(--primary);
        }
        
        .summary-item h4 {
            margin: 0 0 10px;
            font-size: 14px;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .summary-item .value {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
        }
        
        .summary-item.ppn {
            border-left-color: var(--success);
        }
        
        .summary-item.pph {
            border-left-color: var(--warning);
        }
        
        .summary-item.pph h4 small {
            font-size: 12px;
            opacity: 0.8;
            font-weight: normal;
        }
        
        .summary-item.total {
            border-left-color: #7209b7;
            background: linear-gradient(135deg, rgba(114, 9, 183, 0.1), rgba(67, 97, 238, 0.1));
        }
        
        .summary-item.total .value {
            color: #7209b7;
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
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .invoice-container {
                padding: 15px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .summary-grid {
                grid-template-columns: 1fr;
            }
            
            .item-desc {
                min-width: 200px;
            }
        }
        
        @media (max-width: 768px) {
            .invoice-header {
                padding: 20px;
            }
            
            .invoice-card, .summary-container {
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
            }
        }
        
        @media (max-width: 576px) {
            .invoice-container {
                padding: 10px;
            }
            
            .invoice-header h1 {
                font-size: 24px;
            }
            
            .section-title {
                font-size: 16px;
            }
            
            .summary-item .value {
                font-size: 24px;
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
    <div class="invoice-container">
        <div class="invoice-header">
            <h1><i class="fas fa-file-invoice-dollar"></i> Create New Invoice</h1>
            <p>Fill in the details below to create a new invoice</p>
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
        
        <form method="post" id="invoiceForm">
            <!-- Customer & Invoice Details -->
            <div class="invoice-card">
                <div class="section-title">
                    <i class="fas fa-info-circle"></i> Invoice Information
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="customer_id"><i class="fas fa-user"></i> Customer <span class="text-danger">*</span></label>
                        <select name="customer_id" id="customer_id" class="form-control" required>
                            <option value="">-- Select Customer --</option>
                            <?php mysqli_data_seek($customers_result, 0); ?>
                            <?php while($c = mysqli_fetch_assoc($customers_result)): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($c['name'] . ' (' . $c['customer_no'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="invoice_no_display"><i class="fas fa-hashtag"></i> Invoice Number</label>
                        <input type="text" id="invoice_no_display" class="form-control" 
                               value="<?php echo htmlspecialchars($default_invoice_no); ?>" readonly>
                        <small class="form-text text-muted">Otomatis digenerate berdasarkan tanggal invoice</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="faktur_inv"><i class="fas fa-receipt"></i> Faktur Pajak Number <span class="text-danger">*</span></label>
                        <input type="text" name="faktur_inv" id="faktur_inv" class="form-control" 
                               placeholder="Enter Faktur Pajak number" required>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="created_at_inv"><i class="fas fa-calendar-day"></i> Invoice Date <span class="text-danger">*</span></label>
                        <input type="date" name="created_at" id="created_at_inv" class="form-control" 
                               value="<?php echo date('Y-m-d'); ?>" required
                               onchange="updateInvoiceNumber()">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_jatuh_tempo_inv"><i class="fas fa-calendar-alt"></i> Due Date <span class="text-danger">*</span></label>
                        <input type="date" name="date_jatuh_tempo" id="date_jatuh_tempo_inv" class="form-control" 
                               readonly required>
                    </div>
                    
                    <div class="form-group">
                        <label for="po_number"><i class="fas fa-file-contract"></i> PO Number</label>
                        <div class="input-group">
                            <input type="text" name="po_number" id="po_number" class="form-control" 
                                   placeholder="Enter PO number">
                        </div>
                    </div>
                </div>
                
                <!-- Customer Info Display -->
                <div id="customer_info_display" style="display: none;">
                    <div class="customer-info-card">
                        <h5><i class="fas fa-address-card"></i> Customer Information</h5>
                        <p><strong>Alamat:</strong> <span id="customer_address"></span></p>
                        <p><strong>Telepon:</strong> <span id="customer_phone"></span></p>
                        <p><strong>Email:</strong> <span id="customer_email"></span></p>
                    </div>
                </div>
            </div>
            
            <!-- Items Section -->
            <div class="items-container">
                <div class="items-header">
                    <h3><i class="fas fa-boxes"></i> Invoice Items <span class="text-danger">*</span></h3>
                    <button type="button" id="addRowInv" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table class="items-table" id="itemsTableInv">
                        <thead>
                            <tr>
                                <th class="item-no">#</th>
                                <th class="item-desc">Item Description</th>
                                <th class="item-qty">Qty</th>
                                <th class="item-satuan">Unit</th>
                                <th class="item-price">Unit Price (Rp)</th>
                                <th class="item-amount">Amount (Rp)</th>
                                <th class="item-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="item-no">1</td>
                                <td>
                                    <input type="text" name="description[]" class="form-control" 
                                           placeholder="Enter item description" required>
                                </td>
                                <td>
                                    <input type="number" name="qty[]" class="form-control qty" 
                                           value="1" min="0.01" step="0.01" required>
                                </td>
                                <td>
                                    <select name="satuan[]" class="form-control" required>
                                        <option value="">-- Select --</option>
                                        <?php foreach($satuans as $s): ?>
                                            <option value="<?php echo $s; ?>"><?php echo htmlspecialchars($s); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                        <input type="text" name="unit_price_display[]" class="form-control unit_price" 
                                               placeholder="0" required>
                                    </div>
                                    <input type="hidden" name="unit_price_raw[]" value="0">
                                </td>
                                <td>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                        <input type="text" name="amount_display[]" class="form-control amount" 
                                               value="0" readonly>
                                    </div>
                                    <input type="hidden" name="amount_raw[]" value="0">
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm removeRow" disabled>
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="info-note">
                    <i class="fas fa-lightbulb"></i> <strong>Tip:</strong> Minimal satu item harus diisi. Item pertama tidak dapat dihapus.
                </div>
            </div>
            
            <!-- Summary Section -->
            <div class="summary-container">
                <div class="section-title">
                    <i class="fas fa-calculator"></i> Invoice Summary
                </div>
                
                <div class="summary-grid">
                    <div class="summary-item">
                        <h4>Subtotal</h4>
                        <div class="value" id="subtotalDisplay">Rp 0</div>
                        <input type="hidden" name="subtotal" id="subtotal_raw" value="0">
                    </div>
                    
                    <div class="summary-item">
                        <h4>Discount</h4>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                            <input type="text" name="discount_display" id="discountInv" class="form-control" value="0">
                        </div>
                        <input type="hidden" name="discount" id="discount_raw" value="0">
                    </div>
                    
                    <div class="summary-item ppn">
                        <h4>PPN (12%)</h4>
                        <div class="value" id="ppnDisplay">Rp 0</div>
                        <input type="hidden" name="ppn_raw_value" id="ppn_raw_value" value="0">
                    </div>
                    
                    <div class="summary-item pph">
                        <h4>PPH <small>(optional)</small></h4>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                            <input type="text" name="pph_display" id="pphInv" class="form-control" value="0">
                        </div>
                        <input type="hidden" name="pph_raw_value" id="pph_raw_value" value="0">
                    </div>
                    
                    <div class="summary-item total">
                        <h4>Total Amount</h4>
                        <div class="value" id="totalDisplay">Rp 0</div>
                        <input type="hidden" name="total" id="total_raw" value="0">
                    </div>
                </div>
                
                <div class="info-note" style="background: rgba(76, 201, 240, 0.1); border-left-color: #4cc9f0; color: #4cc9f0;">
                    <i class="fas fa-info-circle"></i> <strong>Note:</strong> PPN dihitung dari Subtotal, PPH adalah potongan setelah PPN. Total = Subtotal - Discount + PPN - PPH
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <label for="note"><i class="fas fa-sticky-note"></i> Additional Notes</label>
                    <textarea name="note" id="note" class="form-control" rows="3" 
                              placeholder="Enter any additional notes or terms here..."></textarea>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="actions-footer">
                <a href="invoices_list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-check-circle"></i> Create Invoice
                </button>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    /* ========== UTILITY FUNCTIONS ========== */
    function formatRupiah(num) {
        if (isNaN(num) || num === null) num = 0;
        return 'Rp ' + parseFloat(num).toLocaleString('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }
    
    function parseNumber(str) {
        if (typeof str === 'number') return str;
        let cleaned = String(str).replace(/[^0-9,-]/g, "");
        cleaned = cleaned.replace(',', '.');
        return parseFloat(cleaned) || 0;
    }
    
    /* ========== UPDATE INVOICE NUMBER ========== */
    function updateInvoiceNumber() {
        const dateInput = document.getElementById('created_at_inv').value;
        const dueDateInput = document.getElementById('date_jatuh_tempo_inv');
        
        if (!dateInput) return;
        
        // Set due date (1 month from invoice date)
        const invoiceDate = new Date(dateInput);
        const dueDate = new Date(invoiceDate);
        dueDate.setMonth(dueDate.getMonth() + 1);
        
        const formattedDueDate = dueDate.toISOString().split('T')[0];
        dueDateInput.value = formattedDueDate;
        
        // Generate new invoice number via AJAX
        fetch('<?php echo $_SERVER["PHP_SELF"]; ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=generate_invoice_no&date_quot=' + encodeURIComponent(dateInput)
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('invoice_no_display').value = data.invoice_no;
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
    
    /* ========== LOAD CUSTOMER DATA ========== */
    function loadCustomerData(customerId) {
        if (!customerId) {
            document.getElementById('customer_info_display').style.display = 'none';
            return;
        }
        
        fetch('<?php echo $_SERVER["PHP_SELF"]; ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_customer_data&customer_id=' + customerId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('customer_address').textContent = data.data.address || '-';
                document.getElementById('customer_phone').textContent = data.data.phone || '-';
                document.getElementById('customer_email').textContent = data.data.email || '-';
                document.getElementById('customer_info_display').style.display = 'block';
            } else {
                document.getElementById('customer_info_display').style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('customer_info_display').style.display = 'none';
        });
    }
    
    /* ========== PPH HANDLING ========== */
    $(document).on("focus", "#pphInv", function() {
        let raw = parseNumber($("#pph_raw_value").val());
        $(this).val(raw === 0 ? "" : raw);
    });

    $(document).on("input", "#pphInv", function() {
        let num = parseNumber($(this).val());
        $("#pph_raw_value").val(num);
        recalcInv();
    });

    $(document).on("blur", "#pphInv", function() {
        let raw = parseNumber($("#pph_raw_value").val());
        $(this).val(formatRupiah(raw));
    });
    
    /* ========== MAIN CALCULATION ========== */
    function recalcInv() {
    const PPN_RATE = 11;
    let subtotal = 0;

    $("#itemsTableInv tbody tr").each(function() {
        let tr = $(this);
        let qty = parseNumber(tr.find(".qty").val());
        let unit_raw = parseNumber(tr.find('input[name="unit_price_raw[]"]').val());
        let amount = qty * unit_raw;

        tr.find(".amount").val(formatRupiah(amount));
        tr.find('input[name="amount_raw[]"]').val(amount);
        subtotal += amount;
    });

    $("#subtotalDisplay").text(formatRupiah(subtotal));
    $("#subtotal_raw").val(subtotal);

    let discount = parseNumber($("#discount_raw").val());

    // DPP
    let dpp = subtotal - discount;
    if (dpp < 0) dpp = 0;

    // PPN dari DPP
    let ppnValue = dpp * PPN_RATE / 100;
    $("#ppnDisplay").text(formatRupiah(ppnValue));
    $("#ppn_raw_value").val(ppnValue);

    let pphValue = parseNumber($("#pph_raw_value").val());

    let total = dpp + ppnValue - pphValue;
    if (total < 0) total = 0;

    $("#totalDisplay").text(formatRupiah(total));
    $("#total_raw").val(total);
}

    
    /* ========== UNIT PRICE HANDLING ========== */
    $(document).on("focus", ".unit_price", function() {
        let hidden = $(this).closest("td").find('input[name="unit_price_raw[]"]');
        let raw = parseNumber(hidden.val());
        $(this).val(raw === 0 ? "" : raw);
    });
    
    $(document).on("input", ".unit_price", function() {
        let num = parseNumber($(this).val());
        let hidden = $(this).closest("td").find('input[name="unit_price_raw[]"]');
        hidden.val(num);
        recalcInv();
    });
    
    $(document).on("blur", ".unit_price", function() {
        let hidden = $(this).closest("td").find('input[name="unit_price_raw[]"]');
        let raw = parseNumber(hidden.val());
        $(this).val(formatRupiah(raw));
    });
    
    /* ========== DISCOUNT HANDLING ========== */
    $(document).on("focus", "#discountInv", function() {
        let raw = parseNumber($("#discount_raw").val());
        $(this).val(raw === 0 ? "" : raw);
    });
    
    $(document).on("input", "#discountInv", function() {
        let num = parseNumber($(this).val());
        $("#discount_raw").val(num);
        recalcInv();
    });
    
    $(document).on("blur", "#discountInv", function() {
        let raw = parseNumber($("#discount_raw").val());
        $(this).val(formatRupiah(raw));
    });
    
    /* ========== QTY HANDLING ========== */
    $(document).on("input", ".qty", function() {
        let val = $(this).val();
        if (val < 0.01) $(this).val(0.01);
        recalcInv();
    });
    
    /* ========== ADD ROW ========== */
    $("#addRowInv").click(function() {
        let idx = $("#itemsTableInv tbody tr").length + 1;
        
        let satuanOptions = `
            <option value="">-- Select --</option>
            <?php foreach($satuans as $s): ?>
                <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
            <?php endforeach; ?>
        `;
        
        let tr = `
            <tr>
                <td class="item-no">${idx}</td>
                <td>
                    <input type="text" name="description[]" class="form-control" placeholder="Enter item description" required>
                </td>
                <td>
                    <input type="number" name="qty[]" class="form-control qty" value="1" min="0.01" step="0.01" required>
                </td>
                <td>
                    <select name="satuan[]" class="form-control" required>${satuanOptions}</select>
                </td>
                <td>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                        <input type="text" name="unit_price_display[]" class="form-control unit_price" placeholder="0" required>
                    </div>
                    <input type="hidden" name="unit_price_raw[]" value="0">
                </td>
                <td>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                        <input type="text" name="amount_display[]" class="form-control amount" value="0" readonly>
                    </div>
                    <input type="hidden" name="amount_raw[]" value="0">
                </td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm removeRow">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </td>
            </tr>
        `;
        
        $("#itemsTableInv tbody").append(tr);
        
        // Enable delete button on first row if there are multiple rows
        if ($("#itemsTableInv tbody tr").length > 1) {
            $("#itemsTableInv tbody tr:first .removeRow").prop('disabled', false);
        }
        
        recalcInv();
        
        // Scroll to the new row
        $('html, body').animate({
            scrollTop: $("#itemsTableInv tbody tr:last").offset().top - 100
        }, 500);
    });
    
    /* ========== REMOVE ROW ========== */
    $(document).on("click", ".removeRow", function() {
        if ($("#itemsTableInv tbody tr").length <= 1) {
            alert("Minimal harus ada 1 baris item!");
            return;
        }
        
        $(this).closest("tr").remove();
        
        // Update row numbers
        $("#itemsTableInv tbody tr").each(function(i) {
            $(this).find(".item-no").text(i + 1);
        });
        
        // Disable delete button on first row if only one row remains
        if ($("#itemsTableInv tbody tr").length === 1) {
            $("#itemsTableInv tbody tr:first .removeRow").prop('disabled', true);
        }
        
        recalcInv();
    });
    
    /* ========== FORM VALIDATION ========== */
    $("#invoiceForm").on("submit", function(e) {
        let isValid = true;
        let messages = [];
        
        // Check customer selection
        if (!$("#customer_id").val()) {
            messages.push("Please select a customer");
            $("#customer_id").addClass("is-invalid");
            isValid = false;
        } else {
            $("#customer_id").removeClass("is-invalid");
        }
        
        // Check faktur pajak
        if (!$("#faktur_inv").val().trim()) {
            messages.push("Please enter Faktur Pajak number");
            $("#faktur_inv").addClass("is-invalid");
            isValid = false;
        } else {
            $("#faktur_inv").removeClass("is-invalid");
        }
        
        // Check invoice date
        if (!$("#created_at_inv").val()) {
            messages.push("Please select invoice date");
            $("#created_at_inv").addClass("is-invalid");
            isValid = false;
        } else {
            $("#created_at_inv").removeClass("is-invalid");
        }
        
        // Check if all required item fields are filled
        let allItemsValid = true;
        $('input[name="description[]"]').each(function(i) {
            if (!$(this).val().trim()) {
                allItemsValid = false;
                $(this).addClass("is-invalid");
            } else {
                $(this).removeClass("is-invalid");
            }
        });
        
        if (!allItemsValid) {
            messages.push("All item descriptions must be filled");
            isValid = false;
        }
        
        // Validasi PPH tidak lebih besar dari (subtotal - discount + ppn)
        let subtotal = parseNumber($("#subtotal_raw").val());
        let discount = parseNumber($("#discount_raw").val());
        let ppnValue = parseNumber($("#ppn_raw_value").val());
        let pphValue = parseNumber($("#pph_raw_value").val());
        
        let maxPphAllowed = subtotal - discount + ppnValue;
        if (pphValue > maxPphAllowed) {
            messages.push("PPH cannot be greater than (Subtotal - Discount + PPN)");
            $("#pphInv").addClass("is-invalid");
            isValid = false;
        } else {
            $("#pphInv").removeClass("is-invalid");
        }
        
        if (!isValid) {
            e.preventDefault();
            let errorMessage = "Please fix the following errors:\n\n" + messages.join("\n");
            alert(errorMessage);
            return false;
        }
        
        // Show confirmation dialog
        if (!confirm("Apakah Anda yakin ingin membuat invoice ini?")) {
            e.preventDefault();
            return false;
        }
        
        // Show loading indicator
        $(this).find('button[type="submit"]').html('<i class="fas fa-spinner fa-spin"></i> Processing...').prop('disabled', true);
    });
    
    /* ========== INITIALIZATION ========== */
    $(document).ready(function() {
        // Set initial due date
        $("#created_at_inv").trigger("change");
        
        // Load customer data when customer is selected
        $("#customer_id").on("change", function() {
            loadCustomerData($(this).val());
        });
        
        // Initial calculation
        recalcInv();
        
        // Set initial discount value
        $("#discountInv").trigger("blur");
        
        // Set initial PPH value
        $("#pphInv").trigger("blur");
        
        // Format initial unit prices
        $(".unit_price").each(function() {
            let hidden = $(this).closest("td").find('input[name="unit_price_raw[]"]');
            let raw = parseNumber(hidden.val());
            $(this).val(formatRupiah(raw));
        });
        
        // Add animation to form elements
        $(".form-control, .btn").on("focus", function() {
            $(this).addClass("animated");
        }).on("blur", function() {
            $(this).removeClass("animated");
        });
        
        // Auto-trim input on blur
        $("input[type='text']").on("blur", function() {
            $(this).val($(this).val().trim());
        });
    });
    </script>
    
    <!-- Add a bit of animation -->
    <style>
    .animated {
        animation: pulse 0.5s;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.02); }
        100% { transform: scale(1); }
    }
    
    .is-invalid {
        border-color: #f72585 !important;
        box-shadow: 0 0 0 3px rgba(247, 37, 133, 0.1) !important;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .invoice-card, .items-container, .summary-container {
        animation: fadeIn 0.6s ease-out;
    }
    
    /* Loading spinner */
    .fa-spinner {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    </style>
</body>
</html>

<?php include 'footer.php'; ?>