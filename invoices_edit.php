<?php
// =========================================================================
// PHP LOGIC (Backend Handling)
// =========================================================================

require_once 'functions.php';
require_login();

// 1. Ambil ID dari URL
$id = intval($_GET['id'] ?? 0);
if($id == 0) {
    header('Location: invoices_list.php'); exit;
}

// 2. Ambil data invoice dan item-item lama
$inv_res = mysqli_query($mysqli, "SELECT * FROM invoices WHERE id = $id");
$inv = mysqli_fetch_assoc($inv_res);

if (!$inv) {
    flash_set('error', 'Invoice not found');
    header('Location: invoices_list.php'); exit;
}

$items_res = mysqli_query($mysqli, "SELECT * FROM invoice_items WHERE invoice_id = $id ORDER BY item_no ASC");
$items = mysqli_fetch_all($items_res, MYSQLI_ASSOC);

$satuans = ['Unit', 'Pcs', 'Pack', 'Set', 'Koli', 'Box', 'Buah', 'Pallet'];

// 3. Proses form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = intval($_POST['customer_id']);
    $note = $_POST['note'] ?? '';

    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $discount = floatval($_POST['discount'] ?? 0);
    $ppn_raw_value = floatval($_POST['ppn_raw_value'] ?? 0);
    $total = floatval($_POST['total'] ?? 0);

    $ppn = $ppn_raw_value;

    $faktur_inv = $_POST['faktur_inv'] ?? '';
    $created_at = $_POST['created_at'] ?? date('Y-m-d');
    $date_jatuh_tempo = $_POST['date_jatuh_tempo'] ?? '';
    $po_number = $_POST['po_number'] ?? '';

    mysqli_begin_transaction($mysqli);
    $success = true;

    try {
        $query_update_inv = "
            UPDATE invoices SET
                customer_id = ?,
                note = ?,
                subtotal = ?,
                discount = ?,
                ppn = ?,
                total = ?,
                faktur_inv = ?,
                date_jatuh_tempo = ?,
                po_number = ?,
                created_at = ?
            WHERE id = ?
        ";

        $stmt = mysqli_prepare($mysqli, $query_update_inv);

        mysqli_stmt_bind_param(
            $stmt,
            'isddddssssi',
            $customer_id,
            $note,
            $subtotal,
            $discount,
            $ppn,
            $total,
            $faktur_inv,
            $date_jatuh_tempo,
            $po_number,
            $created_at,
            $id
        );

        if (!mysqli_stmt_execute($stmt)) {
            $success = false;
        }

        if ($success) {
            mysqli_query($mysqli, "DELETE FROM invoice_items WHERE invoice_id = $id");

            $descs = $_POST['description'] ?? [];
            $qtys = $_POST['qty'] ?? [];
            $satuans_post = $_POST['satuan'] ?? [];
            $unit_prices = $_POST['unit_price_raw'] ?? [];
            $amounts = $_POST['amount_raw'] ?? [];

            for($i = 0; $i < count($descs); $i++){
                if(trim($descs[$i]) === '') continue;

                $item_no = $i + 1;
                $desc = $descs[$i];
                $qty = intval($qtys[$i]);
                $satuan = $satuans_post[$i];
                $unit_price = floatval($unit_prices[$i] ?? 0);
                $amount = floatval($amounts[$i] ?? 0);

                $stmt2 = mysqli_prepare($mysqli, "
                    INSERT INTO invoice_items
                    (invoice_id, item_no, description, qty, satuan, unit_price, amount)
                    VALUES (?,?,?,?,?,?,?)
                ");

                mysqli_stmt_bind_param(
                    $stmt2,
                    'iisissd',
                    $id,
                    $item_no,
                    $desc,
                    $qty,
                    $satuan,
                    $unit_price,
                    $amount
                );

                if (!mysqli_stmt_execute($stmt2)) {
                    $success = false;
                    break;
                }
            }
        }

        if ($success) {
            mysqli_commit($mysqli);
            flash_set('success', 'Invoice updated successfully');
            header('Location: invoices_view.php?id=' . $id); exit;
        } else {
            mysqli_rollback($mysqli);
            flash_set('error', 'Failed to update invoice');
        }

    } catch (Exception $e) {
        mysqli_rollback($mysqli);
        flash_set('error', 'Error: ' . $e->getMessage());
    }
}

$customers_res = mysqli_query($mysqli, "SELECT id, name, customer_no FROM customers ORDER BY name ASC");
$customers = mysqli_fetch_all($customers_res, MYSQLI_ASSOC);

$error_msg = flash_get('error');
$success_msg = flash_get('success');

include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Invoice</title>
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
            background: linear-gradient(135deg, var(--warning), #f9c74f);
            color: white;
            padding: 25px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: 0 10px 20px rgba(248, 150, 30, 0.2);
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
        
        .alert-danger {
            background: rgba(247, 37, 133, 0.1);
            color: #f72585;
            border-left: 5px solid #f72585;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 15px;
            font-weight: 500;
        }
        
        .alert-success {
            background: rgba(76, 201, 240, 0.1);
            color: #4cc9f0;
            border-left: 5px solid #4cc9f0;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 15px;
            font-weight: 500;
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
            color: var(--warning);
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
            border-color: var(--warning);
            box-shadow: 0 0 0 3px rgba(248, 150, 30, 0.1);
        }
        
        .form-control[readonly] {
            background-color: var(--light);
            cursor: not-allowed;
        }
        
        .input-group {
            display: flex;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--light-gray);
            transition: var(--transition);
        }
        
        .input-group:focus-within {
            border-color: var(--warning);
            box-shadow: 0 0 0 3px rgba(248, 150, 30, 0.1);
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
            background: linear-gradient(135deg, var(--warning), #f9c74f);
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
            background-color: rgba(248, 150, 30, 0.03);
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
            border-color: var(--warning);
            box-shadow: 0 0 0 2px rgba(248, 150, 30, 0.1);
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
        
        .btn-warning {
            background: linear-gradient(135deg, var(--warning), #f9c74f);
            color: #212529;
        }
        
        .btn-warning:hover {
            background: linear-gradient(135deg, #f9c74f, var(--warning));
            box-shadow: 0 5px 15px rgba(248, 150, 30, 0.3);
            transform: translateY(-2px);
            color: #212529;
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
        
        .btn-success {
            background: linear-gradient(135deg, #4cc9f0, #4895ef);
            color: white;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #4895ef, #4cc9f0);
            box-shadow: 0 5px 15px rgba(76, 201, 240, 0.3);
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
        }
        
        .summary-item {
            padding: 20px;
            border-radius: 8px;
            background: var(--light);
            border-left: 4px solid var(--warning);
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
        
        .summary-item.total {
            border-left-color: #7209b7;
            background: linear-gradient(135deg, rgba(114, 9, 183, 0.1), rgba(248, 150, 30, 0.1));
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
        
        /* Modal Notification */
        .modal-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
            animation: slideInRight 0.5s ease-out;
        }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .is-invalid {
            border-color: #f72585 !important;
            box-shadow: 0 0 0 3px rgba(247, 37, 133, 0.1) !important;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Add fade-in animation to main sections */
        .invoice-card, .items-container, .summary-container {
            animation: fadeIn 0.6s ease-out;
        }
        
        /* Zero value styling */
        .zero-value {
            color: #6c757d !important;
            font-style: italic;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="invoice-container">
        <div class="invoice-header">
            <h1><i class="fas fa-edit"></i> Edit Invoice</h1>
            <p>Update details for Invoice #<?= htmlspecialchars($inv['invoice_no']); ?></p>
        </div>
        
        <?php if($error_msg): ?>
            <div class="alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>
        
        <?php if($success_msg): ?>
            <div class="alert-success">
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
                        <label for="customer_id"><i class="fas fa-user"></i> Customer</label>
                        <select name="customer_id" id="customer_id" class="form-control" required>
                            <option value="">-- Select Customer --</option>
                            <?php foreach($customers as $c): ?>
                                <option value="<?= $c['id']; ?>" <?= ($inv['customer_id'] == $c['id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($c['name'].' ('.$c['customer_no'].')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="faktur_inv"><i class="fas fa-hashtag"></i> Invoice Number</label>
                        <input type="text" name="faktur_inv" id="faktur_inv" class="form-control" 
                               value="<?= htmlspecialchars($inv['faktur_inv']); ?>" required>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="created_at"><i class="fas fa-calendar-day"></i> Invoice Date</label>
                        <input type="date" name="created_at" id="created_at" class="form-control" 
                               value="<?= date('Y-m-d', strtotime($inv['created_at'])); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_jatuh_tempo"><i class="fas fa-calendar-alt"></i> Due Date</label>
                        <input type="date" name="date_jatuh_tempo" id="date_jatuh_tempo" class="form-control" 
                               value="<?= date('Y-m-d', strtotime($inv['date_jatuh_tempo'])); ?>" readonly required>
                    </div>
                    
                    <div class="form-group">
                        <label for="po_number"><i class="fas fa-file-contract"></i> PO Number</label>
                        <input type="text" name="po_number" id="po_number" class="form-control" 
                               value="<?= htmlspecialchars($inv['po_number']); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Items Section -->
            <div class="items-container">
                <div class="items-header">
                    <h3><i class="fas fa-boxes"></i> Invoice Items</h3>
                    <button type="button" id="addRowInv" class="btn btn-success btn-sm">
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
                            <?php foreach($items as $i => $item): ?>
                            <tr>
                                <td class="item-no"><?= $i + 1; ?></td>
                                <td>
                                    <input type="text" name="description[]" class="form-control" 
                                           value="<?= htmlspecialchars($item['description']); ?>">
                                </td>
                                <td>
                                    <input type="number" name="qty[]" class="form-control qty" 
                                           value="<?= $item['qty']; ?>" min="0">
                                </td>
                                <td>
                                    <select name="satuan[]" class="form-control">
                                        <option value="">-- Select --</option>
                                        <?php foreach($satuans as $s): ?>
                                            <option value="<?= $s; ?>" <?= ($item['satuan']==$s?'selected':''); ?>>
                                                <?= $s; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <div class="input-group">
                                        
                                        <input type="text" name="unit_price_display[]" class="form-control unit_price" 
                                               value="<?= $item['unit_price'] == 0 ? '' : number_format($item['unit_price'], 0, '.', ''); ?>">
                                    </div>
                                    <input type="hidden" name="unit_price_raw[]" value="<?= $item['unit_price']; ?>">
                                </td>
                                <td>
                                    <div class="input-group">
                                       
                                        <input type="text" name="amount_display[]" class="form-control amount" 
                                               value="<?= $item['amount'] == 0 ? '0' : number_format($item['amount'], 0, '.', ''); ?>" readonly>
                                    </div>
                                    <input type="hidden" name="amount_raw[]" value="<?= $item['amount']; ?>">
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm removeRow">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="info-note">
                    <i class="fas fa-lightbulb"></i> Tip: Leave item description empty to remove a row
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
                        <div class="value <?= ($inv['subtotal'] == 0) ? 'zero-value' : ''; ?>" id="subtotalDisplay">
                            Rp <?= number_format($inv['subtotal'], 0, ',', '.'); ?>
                        </div>
                        <input type="hidden" name="subtotal" id="subtotal_raw" value="<?= $inv['subtotal']; ?>">
                    </div>
                    
                    <div class="summary-item">
                        <h4>Discount</h4>
                        <div class="input-group">
                            
                            <input type="text" name="discount_display" id="discountInv" class="form-control" 
                                   value="<?= $inv['discount'] == 0 ? '' : number_format($inv['discount'], 0, '.', ''); ?>">
                        </div>
                        <input type="hidden" name="discount" id="discount_raw" value="<?= $inv['discount']; ?>">
                    </div>
                    
                    <div class="summary-item ppn">
                        <h4>PPN (11%)</h4>
                        <div class="value <?= ($inv['ppn'] == 0) ? 'zero-value' : ''; ?>" id="ppnDisplay">
                            Rp <?= number_format($inv['ppn'], 0, ',', '.'); ?>
                        </div>
                        <input type="hidden" name="ppn_raw_value" id="ppn_raw_value" value="<?= $inv['ppn']; ?>">
                    </div>
                    
                    <div class="summary-item total">
                        <h4>Total Amount</h4>
                        <div class="value <?= ($inv['total'] == 0) ? 'zero-value' : ''; ?>" id="totalDisplay">
                            Rp <?= number_format($inv['total'], 0, ',', '.'); ?>
                        </div>
                        <input type="hidden" name="total" id="total_raw" value="<?= $inv['total']; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="note"><i class="fas fa-sticky-note"></i> Additional Notes</label>
                    <textarea name="note" id="note" class="form-control" rows="3"><?= htmlspecialchars($inv['note']); ?></textarea>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="actions-footer">
                <a href="invoices_list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <div class="d-flex gap-2">
                    <a href="invoices_view.php?id=<?= $id; ?>" class="btn btn-info">
                        <i class="fas fa-eye"></i> View Invoice
                    </a>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save"></i> Update Invoice
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Modal Notification -->
    <div id="notificationModal" class="modal-notification" style="display: none;">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle me-3" style="font-size: 24px;"></i>
                <div>
                    <h5 class="mb-0" id="notificationTitle">Success!</h5>
                    <p class="mb-0" id="notificationMessage">Your changes have been saved successfully.</p>
                </div>
            </div>
            <button type="button" class="btn-close" onclick="hideNotification()"></button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    /* ========== UTILITY FUNCTIONS ========== */
    function formatRupiah(num) {
        if (isNaN(num) || num === null) num = 0;
        // Format angka dengan pemisah ribuan
        const formatted = parseFloat(num).toLocaleString('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
        return num === 0 ? '0' : 'Rp ' + formatted;
    }
    
    function parseNumber(str) {
        if (typeof str === 'number') return str;
        if (str === '' || str === null || str === undefined) return 0;
        
        // Hapus semua karakter non-angka kecuali titik dan koma
        let cleaned = String(str).replace(/[^0-9.,-]/g, "");
        
        // Jika kosong setelah dibersihkan, return 0
        if (cleaned === '') return 0;
        
        // Ganti koma dengan titik untuk decimal
        cleaned = cleaned.replace(',', '.');
        
        // Hapus semua titik kecuali yang terakhir (untuk pemisah ribuan)
        const parts = cleaned.split('.');
        if (parts.length > 2) {
            cleaned = parts[0] + parts.slice(1).join('');
        }
        
        const result = parseFloat(cleaned);
        return isNaN(result) ? 0 : result;
    }
    
    function cleanCurrencyInput(input) {
        // Hilangkan format currency saat input
        let value = $(input).val();
        if (value === '' || value === '0') return '';
        
        // Hapus 'Rp ' dan titik pemisah ribuan
        value = value.replace(/Rp\s?/g, '').replace(/\./g, '');
        return value;
    }
    
    function restoreCurrencyFormat(input, value) {
        // Restore format setelah input
        if (value === 0 || value === '0') {
            $(input).val('0');
        } else {
            $(input).val(formatRupiah(value));
        }
    }
    
    /* ========== MAIN CALCULATION ========== */
    function recalcInv() {
        const PPN_RATE = 11;
        let subtotal = 0;
        
        $("#itemsTableInv tbody tr").each(function() {
            let tr = $(this);
            let qty = parseNumber(tr.find(".qty").val());
            let unit_raw = parseNumber(tr.find('input[name="unit_price_raw[]"]').val());
            let amount = qty * unit_raw;
            
            // Update amount display
            tr.find(".amount").val(formatRupiah(amount));
            tr.find('input[name="amount_raw[]"]').val(amount);
            
            // Update hidden raw value
            tr.find('input[name="unit_price_raw[]"]').val(unit_raw);
            
            subtotal += amount;
        });
        
        // Update summary displays
        const subtotalFormatted = formatRupiah(subtotal);
        $("#subtotalDisplay").html(subtotalFormatted);
        $("#subtotal_raw").val(subtotal);
        
        // Toggle zero-value class
        if (subtotal === 0) {
            $("#subtotalDisplay").addClass('zero-value');
        } else {
            $("#subtotalDisplay").removeClass('zero-value');
        }
        
        let discount = parseNumber($("#discount_raw").val());
        let base = subtotal - discount;
        
        // Hitung PPN (11% dari base)
        let ppnValue = base > 0 ? Math.round(base * PPN_RATE / 100) : 0;
        
        const ppnFormatted = formatRupiah(ppnValue);
        $("#ppnDisplay").html(ppnFormatted);
        $("#ppn_raw_value").val(ppnValue);
        
        if (ppnValue === 0) {
            $("#ppnDisplay").addClass('zero-value');
        } else {
            $("#ppnDisplay").removeClass('zero-value');
        }
        
        let total = base + ppnValue;
        
        const totalFormatted = formatRupiah(total);
        $("#totalDisplay").html(totalFormatted);
        $("#total_raw").val(total);
        
        if (total === 0) {
            $("#totalDisplay").addClass('zero-value');
        } else {
            $("#totalDisplay").removeClass('zero-value');
        }
    }
    
    /* ========== UNIT PRICE HANDLING ========== */
    $(document).on("focus", ".unit_price", function() {
        let value = cleanCurrencyInput(this);
        $(this).val(value);
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
        restoreCurrencyFormat(this, raw);
    });
    
    /* ========== DISCOUNT HANDLING ========== */
    $(document).on("focus", "#discountInv", function() {
        let value = cleanCurrencyInput(this);
        $(this).val(value);
    });
    
    $(document).on("input", "#discountInv", function() {
        let num = parseNumber($(this).val());
        $("#discount_raw").val(num);
        recalcInv();
    });
    
    $(document).on("blur", "#discountInv", function() {
        let raw = parseNumber($("#discount_raw").val());
        restoreCurrencyFormat(this, raw);
    });
    
    /* ========== QTY HANDLING ========== */
    $(document).on("input", ".qty", function() {
        let val = $(this).val();
        // Hilangkan karakter non-angka
        $(this).val(val.replace(/[^0-9]/g, ''));
        
        // Jika kosong atau 0, set ke 0
        if (val === '' || parseInt(val) < 0) {
            $(this).val('0');
        }
        
        recalcInv();
    });
    
    $(document).on("blur", ".qty", function() {
        let val = $(this).val();
        if (val === '' || parseInt(val) < 0) {
            $(this).val('0');
            recalcInv();
        }
    });
    
    /* ========== DATE CALCULATION ========== */
    function calculateDueDate(dateString) {
        if (!dateString) return "";
        let d = new Date(dateString + "T00:00:00");
        if (isNaN(d.getTime())) return "";
        
        d.setMonth(d.getMonth() + 1);
        
        return d.getFullYear() + "-" +
               String(d.getMonth() + 1).padStart(2, "0") + "-" +
               String(d.getDate()).padStart(2, "0");
    }
    
    $("#created_at").on("change", function() {
        $("#date_jatuh_tempo").val(calculateDueDate($(this).val()));
    });
    
    /* ========== ADD ROW ========== */
    $("#addRowInv").click(function() {
        let idx = $("#itemsTableInv tbody tr").length + 1;
        
        let satuanOptions = `
            <option value="">-- Select --</option>
            <?php foreach($satuans as $s): ?>
                <option value="<?= $s; ?>"><?= $s; ?></option>
            <?php endforeach; ?>
        `;
        
        let tr = `
            <tr>
                <td class="item-no">${idx}</td>
                <td>
                    <input type="text" name="description[]" class="form-control" placeholder="Enter item description">
                </td>
                <td>
                    <input type="number" name="qty[]" class="form-control qty" value="0" min="0">
                </td>
                <td>
                    <select name="satuan[]" class="form-control">${satuanOptions}</select>
                </td>
                <td>
                    <div class="input-group">
                        
                        <input type="text" name="unit_price_display[]" class="form-control unit_price" placeholder="0">
                    </div>
                    <input type="hidden" name="unit_price_raw[]" value="0">
                </td>
                <td>
                    <div class="input-group">
                        
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
        
        // Scroll to the new row
        $('html, body').animate({
            scrollTop: $("#itemsTableInv tbody tr:last").offset().top - 100
        }, 500);
    });
    
    /* ========== REMOVE ROW ========== */
    $(document).on("click", ".removeRow", function() {
        $(this).closest("tr").remove();
        
        // Update row numbers
        $("#itemsTableInv tbody tr").each(function(i) {
            $(this).find(".item-no").text(i + 1);
        });
        
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
        
        // Check invoice number
        if (!$("#faktur_inv").val().trim()) {
            messages.push("Please enter an invoice number");
            $("#faktur_inv").addClass("is-invalid");
            isValid = false;
        } else {
            $("#faktur_inv").removeClass("is-invalid");
        }
        
        // Check if there's at least one item with description
        let hasItems = false;
        $('input[name="description[]"]').each(function() {
            if ($(this).val().trim()) {
                hasItems = true;
            }
        });
        
        if (!hasItems) {
            messages.push("Please add at least one item to the invoice");
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            showNotification("Validation Error", messages.join("<br>"), "danger");
        } else {
            // Show saving notification
            showNotification("Saving", "Updating invoice...", "info");
        }
    });
    
    /* ========== NOTIFICATION FUNCTIONS ========== */
    function showNotification(title, message, type = "success") {
        const modal = $("#notificationModal");
        const titleEl = $("#notificationTitle");
        const messageEl = $("#notificationMessage");
        
        // Set content
        titleEl.text(title);
        messageEl.html(message);
        
        // Set alert type
        const alertDiv = modal.find(".alert");
        alertDiv.removeClass("alert-success alert-danger alert-info alert-warning");
        alertDiv.addClass(`alert-${type}`);
        
        // Update icon
        const icon = alertDiv.find("i");
        icon.removeClass("fa-check-circle fa-exclamation-circle fa-info-circle fa-exclamation-triangle");
        
        if (type === "success") {
            icon.addClass("fa-check-circle");
        } else if (type === "danger") {
            icon.addClass("fa-exclamation-circle");
        } else if (type === "info") {
            icon.addClass("fa-info-circle");
        } else if (type === "warning") {
            icon.addClass("fa-exclamation-triangle");
        }
        
        // Show notification
        modal.fadeIn(300);
        
        // Auto-hide after 5 seconds for success/info messages
        if (type === "success" || type === "info") {
            setTimeout(() => {
                modal.fadeOut(300);
            }, 5000);
        }
    }
    
    function hideNotification() {
        $("#notificationModal").fadeOut(300);
    }
    
    /* ========== INITIALIZATION ========== */
    $(document).ready(function() {
        // Format all existing unit prices on load
        $(".unit_price").each(function() {
            let hidden = $(this).closest("td").find('input[name="unit_price_raw[]"]');
            let raw = parseNumber(hidden.val());
            restoreCurrencyFormat(this, raw);
        });
        
        // Format discount on load
        let discountRaw = parseNumber($("#discount_raw").val());
        restoreCurrencyFormat("#discountInv", discountRaw);
        
        // Set initial calculation
        recalcInv();
        
        // Initialize tooltips
        $('[title]').tooltip();
        
        // Auto-calculate due date on page load
        if ($("#created_at").val()) {
            $("#date_jatuh_tempo").val(calculateDueDate($("#created_at").val()));
        }
        
        // Auto-update due date when invoice date changes
        $("#created_at").on("change", function() {
            $("#date_jatuh_tempo").val(calculateDueDate($(this).val()));
        });
        
        // Check for flash messages
        <?php if($success_msg): ?>
            setTimeout(() => {
                showNotification("Success", "<?= htmlspecialchars($success_msg); ?>", "success");
            }, 500);
        <?php endif; ?>
        
        <?php if($error_msg): ?>
            setTimeout(() => {
                showNotification("Error", "<?= htmlspecialchars($error_msg); ?>", "danger");
            }, 500);
        <?php endif; ?>
    });
    </script>
</body>
</html>

<?php include 'footer.php'; ?>