<?php
// =========================================================================
// PHP LOGIC (Backend Handling)
// =========================================================================

require_once 'functions.php';
require_login();

$id = intval($_GET['id'] ?? 0);
if($id == 0) {
    flash_set('error', 'Invalid quotation ID');
    header('Location: quotations_list.php');
    exit;
}

// GET QUOTATION
$q = mysqli_query($mysqli, "
    SELECT * FROM quotations WHERE id=$id
");
if(!$quote = mysqli_fetch_assoc($q)){
    flash_set('error', 'Quotation not found');
    header('Location: quotations_list.php');
    exit;
}

// GET ITEMS
$items_result = mysqli_query($mysqli, "
    SELECT * FROM quotation_items
    WHERE quotation_id=$id
    ORDER BY item_no ASC
");

// Definisikan opsi satuan di PHP
$satuans = ['Unit', 'Pcs', 'Pack', 'Set', 'Koli', 'Box', 'Buah', 'Pallet'];

// === HELPER FUNCTION PHP UNTUK FORMAT RUPIAH DENGAN 2 DESIMAL ===
function format_rupiah_edit($angka, $withSymbol = false) {
    if ($angka === null || $angka === '') return $withSymbol ? 'Rp 0' : '0'; 
    $formatted = number_format($angka, 0, ',', '.'); 
    return $withSymbol ? 'Rp ' . $formatted : $formatted;
}

// === LOGIKA PHP UPDATE ===
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    
    // Nilai diambil dari hidden raw fields yang seharusnya sudah bersih dari format Rp dan koma/titik ribuan
    $subtotal = floatval($_POST['subtotal_raw'] ?? 0);
    $discount = floatval($_POST['discount_raw'] ?? 0);
    $ppn      = floatval($_POST['ppn'] ?? 0); 
    $total    = floatval($_POST['total_raw'] ?? 0);

    // Ambil nilai Note (sesuai logika create: option atau custom)
    $note_option = $_POST['note_option'] ?? '';
    $custom_note = $_POST['custom_note'] ?? '';
    $note = ($note_option === 'Custom') ? $custom_note : $note_option;

    // Ambil nilai non-finansial
    $quotation_no = mysqli_real_escape_string($mysqli, $_POST['quotation_no']);
    $note = mysqli_real_escape_string($mysqli, $note);
    $control_model = mysqli_real_escape_string($mysqli, $_POST['control_model']);
    $mtb = mysqli_real_escape_string($mysqli, $_POST['mtb']);
    $date = $_POST['date_quot']; 
    $customer_id = intval($_POST['customer_id']);

    mysqli_begin_transaction($mysqli);
    $success = true;

    try {
        // Update quotation header
        $update_query = "
        UPDATE quotations SET
            quotation_no='$quotation_no',
            customer_id=$customer_id,
            date_quot='$date',
            control_model='$control_model',
            mtb='$mtb',
            subtotal=$subtotal,
            discount=$discount,
            ppn=$ppn,
            total=$total,
            note='$note'
        WHERE id=$id
        ";
        
        if (!mysqli_query($mysqli, $update_query)) {
            $success = false;
            throw new Exception('Error updating quotation: ' . mysqli_error($mysqli));
        }

        // delete old items
        mysqli_query($mysqli, "
            DELETE FROM quotation_items WHERE quotation_id=$id
        ");

        // insert new items
        if(isset($_POST['description_quot'])){
            for($i=0; $i < count($_POST['description_quot']); $i++){
                $desc  = mysqli_real_escape_string($mysqli, $_POST['description_quot'][$i]);
                $qty   = floatval($_POST['qty'][$i]);
                $sat   = mysqli_real_escape_string($mysqli, $_POST['satuan_quot'][$i]);
                $price = floatval($_POST['unit_price_raw'][$i]);
                $amt   = floatval($_POST['amount_raw'][$i]);

                if($desc == '') continue; 
                
                $item_no = $i + 1;

                $stmt2 = mysqli_prepare($mysqli,
                    "INSERT INTO quotation_items 
                        (quotation_id,item_no,description_quot,qty,satuan_quot,unit_price,amount)
                    VALUES (?,?,?,?,?,?,?)"
                );
                mysqli_stmt_bind_param(
                    $stmt2,
                    'iisisdd',
                    $id,
                    $item_no,
                    $desc,
                    $qty,
                    $sat,
                    $price,
                    $amt
                );
                if (!mysqli_stmt_execute($stmt2)) {
                    $success = false;
                    throw new Exception('Error inserting item: ' . mysqli_stmt_error($stmt2));
                }
                mysqli_stmt_close($stmt2);
            }
        }

        if ($success) {
            mysqli_commit($mysqli);
            flash_set('success', 'Quotation updated successfully!');
            header("Location: quotations_view.php?id=$id");
            exit;
        }

    } catch (Exception $e) {
        mysqli_rollback($mysqli);
        flash_set('error', $e->getMessage());
        header('Location: quotations_edit.php?id=' . $id);
        exit;
    }
}

include 'header.php';

$quotation_date = date('Y-m-d', strtotime($quote['date_quot'] ?? $quote['created_at']));

$note_options = [
    'a. Price Include TAX' => 'a. Price Include TAX',
    "a. Price Include TAX\nb. Warranty 3 Month\nc. Warranty applies to the same problem" => 'a. Price Include TAX, b. Warranty 3 Month, c. Warranty applies to the same problem',
    'Custom' => 'Dropdown Custom'
];
$current_note = $quote['note'];
$note_option_selected = '';
$custom_note_value = '';

$standard_notes = array_keys($note_options);
if (in_array($current_note, $standard_notes)) {
    $note_option_selected = $current_note;
} else {
    $note_option_selected = 'Custom';
    $custom_note_value = $current_note;
}

// Get customers for dropdown
$customers_res = mysqli_query($mysqli, "SELECT id, name FROM customers ORDER BY name");
$customers = [];
while($c = mysqli_fetch_assoc($customers_res)) {
    $customers[] = $c;
}

$error_msg = flash_get('error');
$success_msg = flash_get('success');

// --- PERSIAPAN HTML DROPDOWN SATUAN UNTUK JAVASCRIPT ---
$satuan_options_html = '';
foreach($satuans as $s) {
    $satuan_options_html .= '<option value="'.htmlspecialchars($s).'">'.htmlspecialchars($s).'</option>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Quotation</title>
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #17a2b8;
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
        
        .quotation-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .quotation-header {
            background: linear-gradient(135deg, var(--warning), #f9c74f);
            color: white;
            padding: 25px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: 0 10px 20px rgba(248, 150, 30, 0.2);
        }
        
        .quotation-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .quotation-header h1 i {
            font-size: 32px;
        }
        
        .quotation-header p {
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
        
        .quotation-card {
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
        
        .items-table input, .items-table select, .items-table textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
            font-family: inherit;
        }
        
        .items-table input:focus, .items-table select:focus, .items-table textarea:focus {
            outline: none;
            border-color: var(--warning);
            box-shadow: 0 0 0 2px rgba(248, 150, 30, 0.1);
        }
        
        .items-table textarea {
            resize: vertical;
            min-height: 60px;
            max-height: 200px;
            line-height: 1.4;
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
        
        .btn-info {
            background: linear-gradient(135deg, var(--info), #17a2b8);
            color: white;
        }
        
        .btn-info:hover {
            background: linear-gradient(135deg, #17a2b8, var(--info));
            box-shadow: 0 5px 15px rgba(23, 162, 184, 0.3);
            transform: translateY(-2px);
            color: white;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 14px;
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
        
        /* Zero value styling */
        .zero-value {
            color: #6c757d !important;
            font-style: italic;
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
        .quotation-card, .items-container, .summary-container {
            animation: fadeIn 0.6s ease-out;
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .quotation-container {
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
            .quotation-header {
                padding: 20px;
            }
            
            .quotation-card, .summary-container {
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
            .quotation-container {
                padding: 10px;
            }
            
            .quotation-header h1 {
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
            
            .items-table input, .items-table select, .items-table textarea {
                padding: 8px 10px;
                font-size: 13px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="quotation-container">
        <div class="quotation-header">
            <h1><i class="fas fa-edit"></i> Edit Quotation</h1>
            <p>Update details for Quotation #<?php echo htmlspecialchars($quote['quotation_no']); ?></p>
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
        
        <form method="post" id="quoteForm">
            <!-- Quotation Details -->
            <div class="quotation-card">
                <div class="section-title">
                    <i class="fas fa-info-circle"></i> Quotation Information
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="quotation_no"><i class="fas fa-hashtag"></i> Quotation Number</label>
                        <input type="text" name="quotation_no" id="quotation_no" class="form-control"
                            value="<?php echo htmlspecialchars($quote['quotation_no']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="customer_id"><i class="fas fa-user"></i> Customer</label>
                        <select name="customer_id" id="customer_id" class="form-control" required>
                            <option value="">-- Select Customer --</option>
                            <?php foreach($customers as $c): ?>
                                <option value="<?php echo $c['id']; ?>"
                                    <?php if($quote['customer_id'] == $c['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($c['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_quot"><i class="fas fa-calendar-day"></i> Quotation Date</label>
                        <input type="date" name="date_quot" id="date_quot" class="form-control"
                            value="<?php echo htmlspecialchars($quotation_date); ?>" required>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="control_model"><i class="fas fa-cogs"></i> Control Model</label>
                        <input type="text" name="control_model" id="control_model" class="form-control" 
                            value="<?php echo htmlspecialchars($quote['control_model']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="mtb"><i class="fas fa-truck"></i> MTB</label>
                        <input type="text" name="mtb" id="mtb" class="form-control" 
                            value="<?php echo htmlspecialchars($quote['mtb']); ?>">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="note_option"><i class="fas fa-sticky-note"></i> Note</label>
                        <select name="note_option" id="note_option" class="form-control">
                            <option value="">-- Select Note --</option>
                            <?php foreach($note_options as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>"
                                    <?php if($note_option_selected === $value) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <textarea name="custom_note" id="custom_note" class="form-control mt-2" rows="3" 
                            style="<?php echo ($note_option_selected === 'Custom' ? '' : 'display:none;'); ?>" 
                            placeholder="Enter custom note here"><?php echo htmlspecialchars($custom_note_value); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Items Section -->
            <div class="items-container">
                <div class="items-header">
                    <h3><i class="fas fa-boxes"></i> Quotation Items</h3>
                    <button type="button" id="addRow" class="btn btn-success btn-sm">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table class="items-table" id="itemsTable">
                        <thead>
                            <tr>
                                <th class="item-no">#</th>
                                <th class="item-desc">Description</th>
                                <th class="item-qty">Qty</th>
                                <th class="item-satuan">Unit</th>
                                <th class="item-price">Unit Price (Rp)</th>
                                <th class="item-amount">Amount (Rp)</th>
                                <th class="item-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($items_result, 0);
                            while($it = mysqli_fetch_assoc($items_result)): 
                            ?>
                            <tr>
                                <td class="item-no">
                                    <?php echo $it['item_no']; ?>
                                    <input type="hidden" name="item_no[]" value="<?php echo $it['item_no']; ?>">
                                </td>
                                <td>
                                    <!-- UBAH: Gunakan textarea untuk description -->
                                    <textarea 
                                        name="description_quot[]" 
                                        class="form-control description-textarea" 
                                        rows="2" 
                                        placeholder="Enter item description..."
                                        oninput="autoResize(this)"
                                    ><?php echo htmlspecialchars($it['description_quot']); ?></textarea>
                                </td>
                                <td>
                                    <input type="number" name="qty[]" class="form-control qty" 
                                           value="<?php echo $it['qty']; ?>" min="1">
                                </td>
                                <td>
                                    <select name="satuan_quot[]" class="form-control">
                                        <option value="">-- Select --</option>
                                        <?php foreach($satuans as $s): ?>
                                            <option value="<?php echo $s; ?>"
                                                <?php if($it['satuan_quot'] == $s) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($s); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                        <input name="unit_price[]" class="form-control unit_price" 
                                               value="<?php echo $it['unit_price'] == 0 ? '' : number_format($it['unit_price'], 0, '.', ''); ?>">
                                    </div>
                                    <input type="hidden" name="unit_price_raw[]" value="<?php echo $it['unit_price']; ?>">
                                </td>
                                <td>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                        <input name="amount[]" class="form-control amount" 
                                               value="<?php echo $it['amount'] == 0 ? '0' : number_format($it['amount'], 0, '.', ''); ?>" readonly>
                                    </div>
                                    <input type="hidden" name="amount_raw[]" value="<?php echo $it['amount']; ?>">
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm removeRow">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
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
                    <i class="fas fa-calculator"></i> Quotation Summary
                </div>
                
                <div class="summary-grid">
                    <div class="summary-item">
                        <h4>Subtotal</h4>
                        <div class="value <?= ($quote['subtotal'] == 0) ? 'zero-value' : ''; ?>" id="subtotalDisplay">
                            Rp <?= number_format($quote['subtotal'], 0, ',', '.'); ?>
                        </div>
                        <input type="hidden" name="subtotal_raw" id="subtotal_raw" value="<?php echo $quote['subtotal']; ?>">
                    </div>
                    
                    <div class="summary-item">
                        <h4>Discount</h4>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                            <input name="discount_display" id="discount" class="form-control text-right" 
                                   value="<?php echo $quote['discount'] == 0 ? '' : number_format($quote['discount'], 0, '.', ''); ?>">
                        </div>
                        <input type="hidden" name="discount_raw" id="discount_raw" value="<?php echo $quote['discount']; ?>">
                    </div>
                    
                    <div class="summary-item ppn">
                        <h4>PPN (11%)</h4>
                        <div class="value <?= ($quote['ppn'] == 0) ? 'zero-value' : ''; ?>" id="ppnDisplay">
                            Rp <?= number_format($quote['ppn'], 0, ',', '.'); ?>
                        </div>
                        <input type="hidden" name="ppn" id="ppn" value="<?php echo $quote['ppn']; ?>">
                    </div>
                    
                    <div class="summary-item total">
                        <h4>Total Amount</h4>
                        <div class="value <?= ($quote['total'] == 0) ? 'zero-value' : ''; ?>" id="totalDisplay">
                            Rp <?= number_format($quote['total'], 0, ',', '.'); ?>
                        </div>
                        <input type="hidden" name="total_raw" id="total_raw" value="<?php echo $quote['total']; ?>">
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="actions-footer">
                <a href="quotations_list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <div class="d-flex gap-2">
                    <a href="quotations_view.php?id=<?php echo $id; ?>" class="btn btn-info">
                        <i class="fas fa-eye"></i> View Quotation
                    </a>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save"></i> Update Quotation
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
    const PPN_RATE_PERCENT = 11;
    
    // Fungsi untuk auto-resize textarea
    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight) + 'px';
    }
    
    // Inisialisasi auto-resize untuk semua textarea saat halaman dimuat
    function initializeTextareaAutoResize() {
        $('.description-textarea').each(function() {
            autoResize(this);
        });
    }
    
    function formatRupiah(num, withCurrencySymbol = false) {
        if (isNaN(num) || num === null) num = 0;
        const formatted = parseFloat(num).toLocaleString('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
        return num === 0 ? '0' : (withCurrencySymbol ? 'Rp ' : '') + formatted;
    }
    
    function parseNumber(str) {
        if (typeof str === 'number') return str;
        if (str === '' || str === null || str === undefined) return 0;
        
        let cleaned = String(str).replace(/[^0-9.,-]/g, "");
        
        if (cleaned === '') return 0;
        
        cleaned = cleaned.replace(',', '.');
        
        const parts = cleaned.split('.');
        if (parts.length > 2) {
            cleaned = parts[0] + parts.slice(1).join('');
        }
        
        const result = parseFloat(cleaned);
        return isNaN(result) ? 0 : result;
    }
    
    function cleanCurrencyInput(input) {
        let value = $(input).val();
        if (value === '' || value === '0') return '';
        
        value = value.replace(/Rp\s?/g, '').replace(/\./g, '');
        return value;
    }
    
    function restoreCurrencyFormat(input, value) {
        if (value === 0 || value === '0') {
            $(input).val('0');
        } else {
            $(input).val(formatRupiah(value, true));
        }
    }
    
    /* ========== MAIN CALCULATION ========== */
    function recalc() {
        let subtotal = 0;
        
        $('#itemsTable tbody tr').each(function() {
            let $tr = $(this);
            let qty = parseNumber($tr.find('.qty').val());
            let up_raw = parseNumber($tr.find('input[name="unit_price_raw[]"]').val());
            
            let amount = qty * up_raw;

            $tr.find('.amount').val(formatRupiah(amount, true));
            $tr.find('input[name="amount_raw[]"]').val(amount);
            
            subtotal += amount;
        });

        const subtotalFormatted = formatRupiah(subtotal, true);
        $('#subtotalDisplay').html(subtotalFormatted);
        $('#subtotal_raw').val(subtotal);
        
        if (subtotal === 0) {
            $('#subtotalDisplay').addClass('zero-value');
        } else {
            $('#subtotalDisplay').removeClass('zero-value');
        }

        let discount = parseNumber($('#discount_raw').val());
        let base = subtotal - discount;
        let ppnValue = base * PPN_RATE_PERCENT / 100;

        const ppnFormatted = formatRupiah(ppnValue, true);
        $('#ppnDisplay').html(ppnFormatted);
        $('#ppn').val(ppnValue);
        
        if (ppnValue === 0) {
            $('#ppnDisplay').addClass('zero-value');
        } else {
            $('#ppnDisplay').removeClass('zero-value');
        }

        let total = base + ppnValue;
        const totalFormatted = formatRupiah(total, true);
        $('#totalDisplay').html(totalFormatted);
        $('#total_raw').val(total);
        
        if (total === 0) {
            $('#totalDisplay').addClass('zero-value');
        } else {
            $('#totalDisplay').removeClass('zero-value');
        }
    }
    
    /* ========== UNIT PRICE HANDLING ========== */
    $(document).on('focus', '.unit_price', function() {
        let value = cleanCurrencyInput(this);
        $(this).val(value);
    });
    
    $(document).on('input', '.unit_price', function() {
        let num = parseNumber($(this).val());
        let hidden = $(this).closest("td").find('input[name="unit_price_raw[]"]');
        hidden.val(num);
        recalc();
    });
    
    $(document).on('blur', '.unit_price', function() {
        let hidden = $(this).closest("td").find('input[name="unit_price_raw[]"]');
        let raw = parseNumber(hidden.val());
        restoreCurrencyFormat(this, raw);
    });
    
    /* ========== DISCOUNT HANDLING ========== */
    $(document).on('focus', '#discount', function() {
        let value = cleanCurrencyInput(this);
        $(this).val(value);
    });
    
    $(document).on('input', '#discount', function() {
        let num = parseNumber($(this).val());
        $('#discount_raw').val(num);
        recalc();
    });
    
    $(document).on('blur', '#discount', function() {
        let raw = parseNumber($('#discount_raw').val());
        restoreCurrencyFormat(this, raw);
    });
    
    /* ========== QTY HANDLING ========== */
    $(document).on('input', '.qty', function() {
        let val = $(this).val();
        $(this).val(val.replace(/[^0-9.]/g, ''));
        
        if (val === '' || parseFloat(val) < 1) {
            $(this).val('1');
        }
        
        recalc();
    });
    
    $(document).on('blur', '.qty', function() {
        let val = $(this).val();
        if (val === '' || parseFloat(val) < 1) {
            $(this).val('1');
            recalc();
        }
    });
    
    /* ========== ADD/REMOVE ROW ========== */
    $('#addRow').click(function() {
        let idx = $('#itemsTable tbody tr').length + 1;
        
        let tr = `
            <tr>
                <td class="item-no">
                    ${idx}
                    <input type="hidden" name="item_no[]" value="${idx}">
                </td>
                <td>
                    <!-- UBAH: Gunakan textarea untuk description -->
                    <textarea 
                        name="description_quot[]" 
                        class="form-control description-textarea" 
                        rows="2" 
                        placeholder="Enter item description..."
                        oninput="autoResize(this)"
                    ></textarea>
                </td>
                <td>
                    <input type="number" name="qty[]" class="form-control qty" value="1" min="1">
                </td>
                <td>
                    <select name="satuan_quot[]" class="form-control">
                        <option value="">-- Select --</option>
                        <?php echo $satuan_options_html; ?>
                    </select>
                </td>
                <td>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                        <input name="unit_price[]" class="form-control unit_price" placeholder="0">
                    </div>
                    <input type="hidden" name="unit_price_raw[]" value="0">
                </td>
                <td>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                        <input name="amount[]" class="form-control amount" value="0" readonly>
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
        
        $('#itemsTable tbody').append(tr);
        
        // Inisialisasi auto-resize untuk textarea baru
        autoResize($('#itemsTable tbody tr:last-child .description-textarea')[0]);
        
        // Scroll to the new row
        $('html, body').animate({
            scrollTop: $('#itemsTable tbody tr:last').offset().top - 100
        }, 500);
    });
    
    $(document).on('click', '.removeRow', function() {
        $(this).closest('tr').remove();
        
        // Update row numbers
        $('#itemsTable tbody tr').each(function(i) {
            $(this).find('.item-no').text(i + 1);
            $(this).find('input[name="item_no[]"]').val(i + 1);
        });
        
        recalc();
    });
    
    /* ========== AUTO-RESIZE TEXTAREA ========== */
    $(document).on('input', '.description-textarea', function() {
        autoResize(this);
    });
    
    /* ========== DROPDOWN NOTE LOGIC ========== */
    $('#note_option').on('change', function() {
        let selectedValue = $(this).val();
        let $customNote = $('#custom_note');
        
        if (selectedValue === 'Custom') {
            $customNote.show().focus();
        } else {
            $customNote.hide();
            $customNote.val('');
        }
    });
    
    // Jalankan saat load untuk memastikan custom note muncul jika sebelumnya dipilih
    if ($('#note_option').val() === 'Custom') {
        $('#custom_note').show();
    }
    
    /* ========== FORM VALIDATION ========== */
    $('#quoteForm').on('submit', function(e) {
        let isValid = true;
        let messages = [];
        
        // Check quotation number
        if (!$('#quotation_no').val().trim()) {
            messages.push("Please enter a quotation number");
            $('#quotation_no').addClass("is-invalid");
            isValid = false;
        } else {
            $('#quotation_no').removeClass("is-invalid");
        }
        
        // Check customer selection
        if (!$('#customer_id').val()) {
            messages.push("Please select a customer");
            $('#customer_id').addClass("is-invalid");
            isValid = false;
        } else {
            $('#customer_id').removeClass("is-invalid");
        }
        
        // Check if there's at least one item with description
        let hasItems = false;
        $('textarea[name="description_quot[]"]').each(function() {
            if ($(this).val().trim()) {
                hasItems = true;
            }
        });
        
        if (!hasItems) {
            messages.push("Please add at least one item to the quotation");
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            showNotification("Validation Error", messages.join("<br>"), "danger");
        } else {
            // Show saving notification
            showNotification("Saving", "Updating quotation...", "info");
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
        restoreCurrencyFormat("#discount", discountRaw);
        
        // Set initial calculation
        recalc();
        
        // Initialize textarea auto-resize
        initializeTextareaAutoResize();
        
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