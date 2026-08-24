<?php
// Pastikan file functions.php ada dan memiliki definisi fungsi require_login() dan bulan_romawi()
require_once 'functions.php';
require_once 'ai_config.php';
require_module_access('quotation', 'full');

// Ambil flash messages untuk notifikasi
$error_msg = flash_get('error');
$success_msg = flash_get('success');

// Pastikan $mysqli tersedia di sini (misalnya dari functions.php)
$customers = mysqli_query($mysqli, "SELECT * FROM customers ORDER BY name ASC"); 

// Definisikan opsi satuan di PHP
$satuans = ['Unit', 'Pcs', 'Pack', 'Set', 'Koli', 'Box', 'Buah', 'Pallet'];
$ai_config = gemini_config($mysqli);
$ai_openai_config = ai_config($mysqli, 'openai_compatible');

function generate_quotation_no($mysqli, $date_quot) {
    if (empty($date_quot)) {
        return null;
    }
    
    $year = date('Y', strtotime($date_quot));
    $month = date('n', strtotime($date_quot));
    
    // Pastikan fungsi bulan_romawi() ada di functions.php
    $romawi = function_exists('bulan_romawi') ? bulan_romawi($month) : '';
    
    // Query untuk mendapatkan nomor quotation terakhir pada TAHUN yang sama
    // (TIDAK memperhitungkan bulan - lanjut terus)
    $q = mysqli_query($mysqli, "
        SELECT quotation_no
        FROM quotations
        WHERE YEAR(date_quot) = '$year'
        ORDER BY 
            CAST(SUBSTRING_INDEX(quotation_no, '/', 1) AS UNSIGNED) DESC,
            id DESC
        LIMIT 1
    ");
    
    if (!$q) {
        // Error handling
        return "001/PH/ART/$romawi/$year";
    }
    
    $data = mysqli_fetch_assoc($q);
    
    if ($data && !empty($data['quotation_no'])) {
        // Format: 096/PH/ART/XI/2025
        $parts = explode('/', $data['quotation_no']);
        
        // Pastikan array memiliki cukup elemen
        if (count($parts) >= 1) {
            $last_num = intval($parts[0]); // ambil bagian nomor pertama (096)
            $next = $last_num + 1;
        } else {
            // Format tidak sesuai, mulai dari 1
            $next = 1;
        }
    } else {
        // Jika belum ada quotation di tahun ini, mulai dari 1
        $next = 1;
    }
    
    $num = sprintf("%03d", $next); // Format 3 digit, misal 096
    return "$num/PH/ART/$romawi/$year";
}

// Proses form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Logika Pemrosesan Data POST ---
    $customer_id    = intval($_POST['customer_id']);
    $date_quot      = $_POST['date_quot'] ?? null;
    
    // Ambil nilai Note dari dropdown atau dari custom_note jika dropdown memilih 'Custom'
    $note_option = $_POST['note_option'] ?? '';
    $custom_note = $_POST['custom_note'] ?? '';
    $note = ($note_option === 'Custom') ? $custom_note : $note_option;

    $control_model  = $_POST['control_model'] ?? '';
    $mtb            = $_POST['mtb'] ?? '';

    // Generate quotation number
    $quotation_no = generate_quotation_no($mysqli, $date_quot);
    
    if (!$quotation_no) {
        flash_set('error', 'Invalid date for quotation number generation');
        header('Location: quotations_create.php');
        exit;
    }

    // Nilai diambil dari hidden raw fields
    $subtotal = floatval($_POST['subtotal_raw'] ?? 0);
    $discount = floatval($_POST['discount_raw'] ?? 0);
    $ppn      = floatval($_POST['ppn'] ?? 0);
    $total    = floatval($_POST['total_raw'] ?? 0);

    // --- Insert Quotation Utama ---
    $stmt = mysqli_prepare($mysqli,
        "INSERT INTO quotations
        (quotation_no, customer_id, date_quot, note, control_model, mtb, subtotal, discount, ppn, total)
        VALUES (?,?,?,?,?,?,?,?,?,?)"
    );
    mysqli_stmt_bind_param(
        $stmt,
        "sissssdddd",
        $quotation_no, $customer_id, $date_quot, $note, $control_model, $mtb, $subtotal, $discount, $ppn, $total
    );
    
    if (!mysqli_stmt_execute($stmt)) {
        flash_set('error', 'Failed to create quotation: ' . mysqli_error($mysqli));
        header('Location: quotations_create.php');
        exit;
    }
    
    $qid = mysqli_insert_id($mysqli);

    // --- Insert Item Quotation ---
    $descs       = $_POST['description_quot'] ?? [];
    $qtys        = $_POST['qty'] ?? [];
    $satuans_post = $_POST['satuan_quot'] ?? [];
    $unit_prices = $_POST['unit_price_raw'] ?? [];
    $item_discounts = $_POST['item_discount_raw'] ?? [];
    $amounts     = $_POST['amount_raw'] ?? [];

    $success = true;
    for($i=0; $i<count($descs); $i++){
        if(trim($descs[$i]) === '') continue;
        $item_no = $i + 1;
        $desc   = (string)$descs[$i];
        $qty    = (int)$qtys[$i];
        $satuan = (string)$satuans_post[$i];
        $unit   = (float)$unit_prices[$i];
        $item_discount = min(max((float)($item_discounts[$i] ?? 0), 0), $qty * $unit);
        $amount = max(($qty * $unit) - $item_discount, 0);

        $stmt2 = mysqli_prepare($mysqli,
            "INSERT INTO quotation_items 
                (quotation_id,item_no,description_quot,qty,satuan_quot,unit_price,discount,amount)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        mysqli_stmt_bind_param(
            $stmt2,
            'iisisddd',
            $qid,
            $item_no,
            $desc,
            $qty,
            $satuan,
            $unit,
            $item_discount,
            $amount
        );
        
        if (!mysqli_stmt_execute($stmt2)) {
            $success = false;
            break;
        }
    }

    if ($success) {
        flash_set('success', 'Quotation created successfully with number: ' . $quotation_no);
        header('Location: quotations_list.php');
        exit;
    } else {
        flash_set('error', 'Failed to insert quotation items');
        header('Location: quotations_create.php');
        exit;
    }
}

include 'header.php';

// Definisi opsi note
$note_options = [
    'a. Price Include TAX' => 'a. Price Include TAX',
    "a. Price Include TAX\nb. Warranty 3 Month\nc. Warranty applies to the same problem" => 'a. Price Include TAX, b. Warranty 3 Month, c. Warranty applies to the same problem',
    'Custom' => 'Dropdown Custom'
];

// --- PERSIAPAN HTML DROPDOWN SATUAN UNTUK JAVASCRIPT ---
$satuan_options_html = '';
foreach($satuans as $s) {
    $satuan_options_html .= '<option value="'.htmlspecialchars($s).'">'.htmlspecialchars($s).'</option>';
}

// Ambil tanggal terakhir untuk preview nomor quotation
$default_date = date('Y-m-d');
$preview_quotation_no = generate_quotation_no($mysqli, $default_date);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quotation</title>
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
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 25px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.2);
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
        
        .quotation-preview {
            background: rgba(255, 255, 255, 0.1);
            padding: 10px 15px;
            border-radius: 8px;
            margin-top: 15px;
            font-size: 14px;
        }
        
        .quotation-preview strong {
            color: #4cc9f0;
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
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.1);
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
        
        /* Zero value styling */
        .zero-value {
            color: #6c757d !important;
            font-style: italic;
        }
        
        /* Quotation number preview */
        .quotation-no-preview {
            background: #e8f4fd;
            border-left: 4px solid #4cc9f0;
            padding: 12px 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 14px;
        }
        
        .quotation-no-preview .label {
            font-weight: 600;
            color: #4cc9f0;
            margin-right: 10px;
        }
        
        .quotation-no-preview .value {
            font-family: monospace;
            font-size: 16px;
            font-weight: 700;
            color: #4361ee;
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
            <h1><i class="fas fa-file-contract"></i> Create New Quotation</h1>
            <p>Fill in the details below to create a new quotation</p>
            
            <div class="quotation-no-preview">
                <span class="label">Quotation Number Preview:</span>
                <span class="value" id="quotationNoPreview">
                    <?php echo htmlspecialchars($preview_quotation_no ?? 'Set date to preview'); ?>
                </span>
            </div>
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
            <div class="quotation-card border-left border-info">
                <div class="section-title"><i class="fas fa-robot"></i> AI Auto Fill</div>
                <p class="text-muted small">Masukkan kebutuhan pelanggan. AI akan membuat draft item, note, control model, dan MTB. Harga diatur ke 0 untuk Anda tinjau.</p>
                <div class="form-group mb-2"><label for="aiBrief">Brief quotation</label><textarea id="aiBrief" class="form-control" rows="4" maxlength="6000" placeholder="Contoh: Repair spindle motor Fanuc, termasuk inspeksi, penggantian bearing, testing, dan laporan hasil pekerjaan."></textarea></div>
                <button type="button" class="btn btn-info" id="generateAiBtn"><i class="fas fa-wand-magic-sparkles mr-1"></i>Generate Draft dengan Gemini</button>
                <button type="button" class="btn btn-outline-secondary ml-1" data-toggle="modal" data-target="#aiSettingsModal" title="Pengaturan AI"><i class="fas fa-sliders-h mr-1"></i>Pengaturan</button>
                <span id="aiStatus" class="ml-2 small text-muted"></span>
            </div>
            <div class="modal fade" id="aiSettingsModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
                    <div class="modal-header bg-light"><h5 class="modal-title"><i class="fas fa-robot mr-2"></i>Pengaturan Provider AI</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                    <div class="modal-body">
                        <div class="p-3" style="background:#d9eee5;border:1px solid #a8d5c0;border-radius:6px">
                            <div class="form-group"><label for="aiProvider">Provider AI</label><select id="aiProvider" class="form-control"><option value="gemini">Gemini API</option><option value="openai_compatible">OpenAI Compatible (9Router)</option></select></div>
                            <div class="form-group"><label for="aiBaseUrl">Base URL</label><input type="url" id="aiBaseUrl" class="form-control" value="<?= htmlspecialchars($ai_config['base_url']) ?>"></div>
                            <div class="form-group"><label for="aiApiKey">API Key</label><input type="password" id="aiApiKey" class="form-control" autocomplete="new-password"></div>
                            <div class="input-group"><select id="aiModel" class="form-control"><option value="<?= htmlspecialchars($ai_config['model']) ?>"><?= htmlspecialchars($ai_config['model']) ?></option></select><div class="input-group-append"><button type="button" id="loadAiModels" class="btn btn-outline-success"><i class="fas fa-sync mr-1"></i>Load Models</button></div></div>
                            <script type="application/json" id="aiProviderConfigs"><?= json_encode(['gemini' => ['base_url' => $ai_config['base_url'], 'model' => $ai_config['model'], 'configured' => $ai_config['configured']], 'openai_compatible' => ['base_url' => $ai_openai_config['base_url'], 'model' => $ai_openai_config['model'], 'configured' => $ai_openai_config['configured']]], JSON_UNESCAPED_SLASHES) ?></script>
                            <small class="form-text text-muted">Model akan dimuat dari provider yang dipilih.</small>
                        </div>
                        <div id="aiSettingsStatus" class="small mt-2"></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button type="button" class="btn btn-primary" id="saveAiSettings"><i class="fas fa-save mr-1"></i>Simpan</button></div>
                </div></div>
            </div>
            <div class="modal fade" id="aiApplyModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
                    <div class="modal-header bg-info text-white"><h5 class="modal-title"><i class="fas fa-wand-magic-sparkles mr-2"></i>Terapkan Draft AI</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
                    <div class="modal-body">
                        <p class="mb-2">Draft AI siap diterapkan ke form quotation.</p>
                        <div class="alert alert-light border mb-0"><i class="fas fa-circle-info text-info mr-2"></i><span id="aiApplySummary"></span></div>
                        <small class="text-muted d-block mt-2">Data item yang sedang diisi akan diganti. Harga tetap dapat Anda tinjau dan ubah.</small>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-xmark mr-1"></i>Batal</button><button type="button" class="btn btn-info" id="applyAiDraft"><i class="fas fa-check mr-1"></i>Terapkan Draft</button></div>
                </div></div>
            </div>
            <!-- Quotation Details -->
            <div class="quotation-card">
                <div class="section-title">
                    <i class="fas fa-info-circle"></i> Quotation Information
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="customer_id"><i class="fas fa-user"></i> Customer</label>
                        <select name="customer_id" id="customer_id" class="form-control" required>
                            <option value="">-- Select Customer --</option>
                            <?php 
                            mysqli_data_seek($customers, 0); // Reset pointer
                            while($c = mysqli_fetch_assoc($customers)): 
                            ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($c['name'].' ('.$c['customer_no'].')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_quot"><i class="fas fa-calendar-day"></i> Quotation Date</label>
                        <input type="date" name="date_quot" id="date_quot" class="form-control" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                        <div class="text-muted small mt-1">
                            <i class="fas fa-info-circle"></i> Quotation number will be generated based on this date
                        </div>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="control_model"><i class="fas fa-cogs"></i> Control Model</label>
                        <input type="text" name="control_model" id="control_model" class="form-control" placeholder="Enter control model">
                    </div>
                    
                    <div class="form-group">
                        <label for="mtb"><i class="fas fa-truck"></i> MTB</label>
                        <input type="text" name="mtb" id="mtb" class="form-control" placeholder="Enter MTB">
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="note_option"><i class="fas fa-sticky-note"></i> Note</label>
                        <select name="note_option" id="note_option" class="form-control">
                            <option value="">-- Select Note --</option>
                            <?php foreach($note_options as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>">
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <textarea name="custom_note" id="custom_note" class="form-control mt-2" rows="3" 
                            style="display:none;" placeholder="Enter custom note here"></textarea>
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
                                <th class="item-price">Unit Price</th>
                                <th class="item-discount">Discount</th>
                                <th class="item-amount">Amount (Rp)</th>
                                <th class="item-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Item Pertama -->
                            <tr>
                                <td class="item-no">1</td>
                                <td>
                                    <!-- Textarea untuk description -->
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
                                        <?php foreach($satuans as $s): ?>
                                            <option value="<?php echo $s; ?>"><?php echo htmlspecialchars($s); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input name="unit_price[]" class="form-control unit_price" placeholder="0">
                                    <input type="hidden" name="unit_price_raw[]" value="0">
                                </td>
                                <td>
                                    <input name="item_discount[]" class="form-control item_discount" placeholder="0">
                                    <input type="hidden" name="item_discount_raw[]" value="0">
                                </td>
                                <td>
                                    <input name="amount[]" class="form-control amount" value="0" readonly>
                                    <input type="hidden" name="amount_raw[]" value="0">
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm removeRow">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
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
                        <div class="value zero-value" id="subtotalDisplay">Rp 0</div>
                        <input type="hidden" name="subtotal_raw" id="subtotal_raw" value="0">
                    </div>
                    
                    <div class="summary-item">
                        <h4>Discount</h4>
                        <input name="discount_display" id="discount" class="form-control" value="0">
                        <input type="hidden" name="discount_raw" id="discount_raw" value="0">
                    </div>
                    
                    <div class="summary-item ppn">
                        <h4>PPN (11%)</h4>
                        <div class="value zero-value" id="ppnDisplay">Rp 0</div>
                        <input type="hidden" name="ppn" id="ppn" value="0">
                    </div>
                    
                    <div class="summary-item total">
                        <h4>Total Amount</h4>
                        <div class="value zero-value" id="totalDisplay">Rp 0</div>
                        <input type="hidden" name="total_raw" id="total_raw" value="0">
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="actions-footer">
                <a href="quotations_list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check-circle"></i> Create Quotation
                </button>
            </div>
        </form>
    </div>

    <!-- Modal Notification -->
    <div id="notificationModal" class="modal-notification" style="display: none;">
        <div class="alert alert-primary d-flex align-items-center alert-dismissible fade show" role="alert">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-exclamation-triangle-fill flex-shrink-0 me-2" viewBox="0 0 16 16" role="img" aria-label="Warning:">
                <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
            </svg>
            <div>
                <h5 class="mb-0" id="notificationTitle">Success!</h5>
                <p class="mb-0" id="notificationMessage">Your quotation has been created successfully.</p>
            </div>
            <button type="button" class="btn-close" onclick="hideNotification()"></button>
        </div>
    </div>

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
        const formatted = parseFloat(num).toLocaleString('en-US', {
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
        
        cleaned = cleaned.replace(/,/g, '');
        
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
            $(input).val(formatRupiah(value));
        }
    }
    
    /* ========== QUOTATION NUMBER PREVIEW ========== */
    function updateQuotationPreview() {
        const dateValue = $('#date_quot').val();
        
        if (!dateValue) {
            $('#quotationNoPreview').text('Set date to preview');
            return;
        }
        
        // Show loading
        $('#quotationNoPreview').html('<i class="fas fa-spinner fa-spin"></i> Generating...');
        
        // AJAX request to get preview
        $.ajax({
            url: 'get_quotation_preview.php',
            type: 'POST',
            data: { date_quot: dateValue },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#quotationNoPreview').text(response.quotation_no);
                } else {
                    $('#quotationNoPreview').text('Error: ' + response.message);
                }
            },
            error: function() {
                $('#quotationNoPreview').text('Failed to generate preview');
            }
        });
    }
    
    /* ========== MAIN CALCULATION ========== */
    function recalc() {
        let subtotal = 0;
        
        $('#itemsTable tbody tr').each(function() {
            let $tr = $(this);
            let qty = parseNumber($tr.find('.qty').val());
            let up_raw = parseNumber($tr.find('input[name="unit_price_raw[]"]').val());
            
            let grossAmount = qty * up_raw;
            let itemDiscount = parseNumber($tr.find('input[name="item_discount_raw[]"]').val());
            itemDiscount = Math.min(Math.max(itemDiscount, 0), grossAmount);
            $tr.find('input[name="item_discount_raw[]"]').val(itemDiscount);
            let amount = grossAmount - itemDiscount;

            $tr.find('.amount').val(formatRupiah(amount));
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

        let discount = Math.min(Math.max(parseNumber($('#discount_raw').val()), 0), subtotal);
        $('#discount_raw').val(discount);
        let base = subtotal - discount;
        let ppnValue = base * PPN_RATE_PERCENT / 100;

        const ppnFormatted = formatRupiah(ppnValue);
        $('#ppnDisplay').html(ppnFormatted);
        $('#ppn').val(ppnValue);
        
        if (ppnValue === 0) {
            $('#ppnDisplay').addClass('zero-value');
        } else {
            $('#ppnDisplay').removeClass('zero-value');
        }

        let total = base + ppnValue;
        const totalFormatted = formatRupiah(total);
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

    $(document).on('focus', '.item_discount', function() {
        const raw = parseNumber($(this).closest('td').find('input[name="item_discount_raw[]"]').val());
        $(this).val(raw === 0 ? '' : raw);
    });

    $(document).on('input', '.item_discount', function() {
        const raw = parseNumber($(this).val());
        $(this).closest('td').find('input[name="item_discount_raw[]"]').val(raw);
        recalc();
    });

    $(document).on('blur', '.item_discount', function() {
        const raw = parseNumber($(this).closest('td').find('input[name="item_discount_raw[]"]').val());
        $(this).val(formatRupiah(raw));
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
    
    /* ========== DATE CHANGE HANDLER ========== */
    $('#date_quot').on('change', function() {
        updateQuotationPreview();
    });

    $('#aiProvider').on('change', function() {
        const configs = JSON.parse($('#aiProviderConfigs').text() || '{}');
        const config = configs[$(this).val()] || {};
        $('#aiBaseUrl').val(config.base_url || '');
        $('#aiModel').empty();
        if (config.model) $('#aiModel').append($('<option>', {value: config.model, text: config.model}));
        $('#aiApiKey').val('');
        $('#aiSettingsStatus').removeClass().addClass('small mt-2 text-muted').text('Masukkan API key untuk provider yang dipilih.');
    });

    $('#loadAiModels').on('click', async function() {
        const $button = $(this), apiKey = $('#aiApiKey').val().trim(), baseUrl = $('#aiBaseUrl').val().trim();
        if (!apiKey) { $('#aiSettingsStatus').removeClass().addClass('small mt-2 text-danger').text('API key wajib diisi untuk memuat model.'); return; }
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Loading...');
        try {
            const response = await fetch('ai_settings_api.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'models', provider:$('#aiProvider').val(), api_key:apiKey, base_url:baseUrl})});
            const payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.message || 'Model gagal dimuat.');
            const $select = $('#aiModel').empty();
            payload.data.models.forEach(model => $select.append($('<option>', {value:model.id, text:model.label})));
            $('#aiSettingsStatus').removeClass().addClass('small mt-2 text-success').text(payload.message);
        } catch (error) { $('#aiSettingsStatus').removeClass().addClass('small mt-2 text-danger').text(error.message); }
        finally { $button.prop('disabled', false).html('<i class="fas fa-sync mr-1"></i>Load Models'); }
    });

    $('#saveAiSettings').on('click', async function() {
        const $button = $(this), model = $('#aiModel').val();
        if (!model) { $('#aiSettingsStatus').removeClass().addClass('small mt-2 text-danger').text('Pilih model terlebih dahulu.'); return; }
        $button.prop('disabled', true);
        try {
            const response = await fetch('ai_settings_api.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'save', provider:$('#aiProvider').val(), api_key:$('#aiApiKey').val(), base_url:$('#aiBaseUrl').val(), model})});
            const payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.message || 'Pengaturan gagal disimpan.');
            $('#aiSettingsStatus').removeClass().addClass('small mt-2 text-success').text(payload.message);
            setTimeout(() => $('#aiSettingsModal').modal('hide'), 700);
        } catch (error) { $('#aiSettingsStatus').removeClass().addClass('small mt-2 text-danger').text(error.message); }
        finally { $button.prop('disabled', false); }
    });

    /* ========== GEMINI AUTO FILL ========== */
    $('#generateAiBtn').on('click', async function() {
        const brief = $('#aiBrief').val().trim();
        if (!brief) { showNotification('AI Auto Fill', 'Brief wajib diisi.', 'warning'); return; }
        const $button = $(this);
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Membuat draft...');
        $('#aiStatus').text('Menghubungi provider AI...');
        try {
            const customers = $('#customer_id option').map(function() { return this.value ? {id: Number(this.value), label: $(this).text().trim()} : null; }).get().filter(Boolean);
            const response = await fetch('gemini_quotation_autofill.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({brief, customers, provider: $('#aiProvider').val()}) });
            const payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.message || 'Gagal membuat draft.');
            const draft = payload.data;
            const items = Array.isArray(draft.items) ? draft.items : [];
            if (!items.length) throw new Error('Draft tidak memiliki item.');
            const customerLabel = draft.customer_id ? $('#customer_id option[value="' + draft.customer_id + '"]').text().trim() : 'Tidak ditentukan';
            $('#aiApplySummary').text(items.length + ' item akan dimasukkan' + (customerLabel !== 'Tidak ditentukan' ? ' untuk ' + customerLabel : '') + '.');
            $('#aiApplyModal').data('draft', draft).modal('show');
        } catch (error) { $('#aiStatus').text('Gagal membuat draft.'); showNotification('AI Auto Fill', error.message, 'danger'); }
        finally { $button.prop('disabled', false).html('<i class="fas fa-wand-magic-sparkles mr-1"></i>Generate Draft AI'); }
    });

    $('#applyAiDraft').on('click', function() {
        const draft = $('#aiApplyModal').data('draft');
        if (!draft) return;
        $('#customer_id').val(draft.customer_id ? String(draft.customer_id) : '').trigger('change');
        $('#control_model').val(draft.control_model || '');
        $('#mtb').val(draft.mtb || '');
        if ($('#note_option option[value="Custom"]').length === 0) $('#note_option').append('<option value="Custom">Custom</option>');
        $('#note_option').val('Custom').trigger('change'); $('#custom_note').val(draft.note || '').show();
        $('#itemsTable tbody').empty();
        (draft.items || []).forEach(function(item) {
            $('#addRow').trigger('click');
            const $row = $('#itemsTable tbody tr').last();
            $row.find('textarea[name="description_quot[]"]').val(item.description || '').each(function(){ autoResize(this); });
            $row.find('input[name="qty[]"]').val(Math.max(1, Number(item.qty) || 1));
            $row.find('select[name="satuan_quot[]"]').val(item.unit || 'Unit');
            const unitPrice = Math.max(0, Number(item.unit_price) || 0);
            $row.find('input[name="unit_price[]"]').val(unitPrice ? formatRupiah(unitPrice) : '');
            $row.find('input[name="unit_price_raw[]"]').val(unitPrice);
        });
        recalc();
        $('#aiApplyModal').modal('hide');
        $('#aiStatus').text('Draft diterapkan. Periksa item dan isi harga satuan.');
        showNotification('AI Auto Fill', 'Draft berhasil diterapkan ke form.', 'success');
    });
    
    /* ========== ADD/REMOVE ROW ========== */
    $('#addRow').click(function() {
        let idx = $('#itemsTable tbody tr').length + 1;
        
        let tr = `
            <tr>
                <td class="item-no">${idx}</td>
                <td>
                    <!-- Textarea untuk description -->
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
                    <input name="unit_price[]" class="form-control unit_price" placeholder="0">
                    <input type="hidden" name="unit_price_raw[]" value="0">
                </td>
                <td>
                    <input name="item_discount[]" class="form-control item_discount" placeholder="0">
                    <input type="hidden" name="item_discount_raw[]" value="0">
                </td>
                <td>
                    <input name="amount[]" class="form-control amount" value="0" readonly>
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
        
        $('#itemsTable tbody tr').each(function(i) {
            $(this).find('.item-no').text(i + 1);
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
    
    /* ========== FORM VALIDATION ========== */
    $('#quoteForm').on('submit', function(e) {
        let isValid = true;
        let messages = [];
        
        // Check customer selection
        if (!$('#customer_id').val()) {
            messages.push("Please select a customer");
            $('#customer_id').addClass("is-invalid");
            isValid = false;
        } else {
            $('#customer_id').removeClass("is-invalid");
        }
        
        // Check quotation date
        if (!$('#date_quot').val()) {
            messages.push("Please select a quotation date");
            $('#date_quot').addClass("is-invalid");
            isValid = false;
        } else {
            $('#date_quot').removeClass("is-invalid");
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
            showNotification("Creating", "Creating quotation...", "info");
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
        alertDiv.removeClass("alert-success alert-danger alert-info alert-warning").addClass("alert-primary");
        
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
        // Format discount on load
        let discountRaw = parseNumber($("#discount_raw").val());
        restoreCurrencyFormat("#discount", discountRaw);
        
        // Set initial calculation
        recalc();
        
        // Initialize textarea auto-resize
        initializeTextareaAutoResize();
        
        // Initialize tooltips
        $('[title]').tooltip();
        
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