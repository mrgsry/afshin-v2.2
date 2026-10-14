<?php
// =========================================================================
// BOOTSTRAP
// =========================================================================
require_once 'db.php';
require_once 'functions.php';
require_once 'ai_config.php';
require_login();
require_module_access('invoice', 'full');

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
        $note = $_POST['note'] ?? '';
        $subtotal = (float) ($_POST['subtotal'] ?? 0);
        $discount = (float) ($_POST['discount'] ?? 0);
        $ppn = (float) ($_POST['ppn_raw_value'] ?? 0);
        $pph = (float) ($_POST['pph_raw_value'] ?? 0);
        $total = (float) ($_POST['total'] ?? 0);
        $date_jatuh_tempo = $_POST['date_jatuh_tempo'] ?? null;
        $po_number = $_POST['po_number'] ?? '';

        $stmt = $mysqli->prepare("
            INSERT INTO invoices
            (invoice_no,customer_id,note,subtotal,discount,ppn,pph,total,faktur_inv,date_jatuh_tempo,po_number,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        $stmt->bind_param(
            'sisddddddsss',
            $invoice_no,
            $customer_id,
            $note,
            $subtotal,
            $discount,
            $ppn,
            $pph,
            $total,
            $faktur_inv,
            $date_jatuh_tempo,
            $po_number,
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
                (invoice_id,item_no,description,qty,satuan,unit_price,discount,amount)
                VALUES (?,?,?,?,?,?,?,?)
            ");

            $no = $i + 1;
            // mysqli::bind_param() requires variables passed by reference.
            $qty = (int) ($_POST['qty'][$i] ?? 0);
            $satuan = $_POST['satuan'][$i] ?? '';
            $unit_price = (float) ($_POST['unit_price_raw'][$i] ?? 0);
            $item_discount = (float) ($_POST['item_discount_raw'][$i] ?? 0);
            $amount = (float) ($_POST['amount_raw'][$i] ?? 0);

            $stmt2->bind_param(
                'iisdsddd',
                $iid,
                $no,
                $desc,
                $qty,
                $satuan,
                $unit_price,
                $item_discount,
                $amount
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
$ai_config = gemini_config($mysqli);
$ai_openai_config = ai_config($mysqli, 'openai_compatible');
$ai_provider = $ai_openai_config['configured'] ? 'openai_compatible' : 'gemini';
$ai_active_config = $ai_provider === 'openai_compatible' ? $ai_openai_config : $ai_config;

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

    .items-table input,
    .items-table select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--light-gray);
        border-radius: 6px;
        font-size: 14px;
        box-sizing: border-box;
    }

    .items-table input:focus,
    .items-table select:focus {
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

    .item-price,
    .item-discount,
    .item-amount {
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

        .invoice-card,
        .summary-container {
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

        .items-table th,
        .items-table td {
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

        .items-table th,
        .items-table td {
            padding: 12px 10px;
            font-size: 13px;
        }

        .items-table input,
        .items-table select {
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
            <div class="invoice-card border-left border-info">
                <div class="section-title"><i class="fas fa-robot"></i> AI Auto Fill</div>
                <p class="text-muted small">Masukkan kebutuhan pelanggan. AI akan membuat draft item, harga, customer, dan catatan. Periksa data sebelum menyimpan invoice.</p>
                <div class="form-group mb-2"><label for="invoiceAiBrief">Brief invoice</label><textarea id="invoiceAiBrief" class="form-control" rows="4" maxlength="6000" placeholder="Contoh: Invoice jasa repair spindle motor Fanuc, inspeksi, penggantian bearing, testing, dan laporan pekerjaan."></textarea></div>
                <button type="button" class="btn btn-info" id="generateInvoiceAiBtn"><i class="fas fa-wand-magic-sparkles mr-1"></i>Generate Draft AI</button>
                <button type="button" class="btn btn-outline-secondary ml-1" data-toggle="modal" data-target="#invoiceAiSettingsModal" title="Pengaturan AI"><i class="fas fa-sliders-h mr-1"></i>Pengaturan</button>
                <span id="invoiceAiStatus" class="ml-2 small text-muted"></span>
            </div>

            <div class="modal fade" id="invoiceAiSettingsModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
                    <div class="modal-header bg-light"><h5 class="modal-title"><i class="fas fa-robot mr-2"></i>Pengaturan Provider AI</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                    <div class="modal-body">
                        <div class="p-3" style="background:#d9eee5;border:1px solid #a8d5c0;border-radius:6px">
                            <div class="form-group"><label for="invoiceAiProvider">Provider AI</label><select id="invoiceAiProvider" class="form-control"><option value="gemini" <?= $ai_provider === 'gemini' ? 'selected' : '' ?>>Gemini API</option><option value="openai_compatible" <?= $ai_provider === 'openai_compatible' ? 'selected' : '' ?>>OpenAI Compatible (9Router)</option></select></div>
                            <div class="form-group"><label for="invoiceAiBaseUrl">Base URL</label><input type="url" id="invoiceAiBaseUrl" class="form-control" value="<?= htmlspecialchars($ai_active_config['base_url']) ?>"></div>
                            <div class="form-group"><label for="invoiceAiApiKey">API Key</label><input type="password" id="invoiceAiApiKey" class="form-control" autocomplete="new-password"></div>
                            <div class="input-group"><select id="invoiceAiModel" class="form-control"><option value="<?= htmlspecialchars($ai_active_config['model']) ?>"><?= htmlspecialchars($ai_active_config['model']) ?></option></select><div class="input-group-append"><button type="button" id="loadInvoiceAiModels" class="btn btn-outline-success"><i class="fas fa-sync mr-1"></i>Load Models</button></div></div>
                            <script type="application/json" id="invoiceAiProviderConfigs"><?= json_encode(['gemini' => ['base_url' => $ai_config['base_url'], 'model' => $ai_config['model'], 'configured' => $ai_config['configured']], 'openai_compatible' => ['base_url' => $ai_openai_config['base_url'], 'model' => $ai_openai_config['model'], 'configured' => $ai_openai_config['configured']]], JSON_UNESCAPED_SLASHES) ?></script>
                            <small class="form-text text-muted">Model akan dimuat dari provider yang dipilih.</small>
                        </div>
                        <div id="invoiceAiSettingsStatus" class="small mt-2"></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button type="button" class="btn btn-primary" id="saveInvoiceAiSettings"><i class="fas fa-save mr-1"></i>Simpan</button></div>
                </div></div>
            </div>

            <div class="modal fade" id="invoiceAiApplyModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
                    <div class="modal-header bg-info text-white"><h5 class="modal-title"><i class="fas fa-wand-magic-sparkles mr-2"></i>Terapkan Draft AI</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
                    <div class="modal-body"><p class="mb-2">Draft AI siap diterapkan ke form invoice.</p><div class="alert alert-light border mb-0"><i class="fas fa-circle-info text-info mr-2"></i><span id="invoiceAiApplySummary"></span></div><small class="text-muted d-block mt-2">Data item, catatan, tanggal invoice, dan nomor PO yang sedang diisi akan diganti jika draft AI memuat nilainya. Faktur pajak, tanggal jatuh tempo, dan pajak tetap perlu diperiksa.</small></div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-xmark mr-1"></i>Batal</button><button type="button" class="btn btn-info" id="applyInvoiceAiDraft"><i class="fas fa-check mr-1"></i>Terapkan Draft</button></div>
                </div></div>
            </div>

            <!-- Customer & Invoice Details -->
            <div class="invoice-card">
                <div class="section-title">
                    <i class="fas fa-info-circle"></i> Invoice Information
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="customer_id"><i class="fas fa-user"></i> Customer <span
                                class="text-danger">*</span></label>
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
                        <label for="faktur_inv"><i class="fas fa-receipt"></i> Faktur Pajak Number <span
                                class="text-danger">*</span></label>
                        <input type="text" name="faktur_inv" id="faktur_inv" class="form-control"
                            placeholder="Enter Faktur Pajak number" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="created_at_inv"><i class="fas fa-calendar-day"></i> Invoice Date <span
                                class="text-danger">*</span></label>
                        <input type="date" name="created_at" id="created_at_inv" class="form-control"
                            value="<?php echo date('Y-m-d'); ?>" required onchange="updateInvoiceNumber()">
                    </div>

                    <div class="form-group">
                        <label for="date_jatuh_tempo_inv"><i class="fas fa-calendar-alt"></i> Due Date <span
                                class="text-danger">*</span></label>
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
                                <th class="item-discount">Discount (Rp)</th>
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
                                    <input type="number" name="qty[]" class="form-control qty" value="1" min="0.01"
                                        step="0.01" required>
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
                                    <input type="text" name="unit_price_display[]" class="form-control unit_price"
                                        placeholder="0" required>
                                    <input type="hidden" name="unit_price_raw[]" value="0">
                                </td>
                                <td>
                                    <input type="text" name="item_discount_display[]"
                                        class="form-control item_discount" placeholder="0">
                                    <input type="hidden" name="item_discount_raw[]" value="0">
                                </td>
                                <td>
                                    <input type="text" name="amount_display[]" class="form-control amount" value="0"
                                        readonly>
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
                    <i class="fas fa-lightbulb"></i> <strong>Tip:</strong> Minimal satu item harus diisi. Item pertama
                    tidak dapat dihapus.
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
                            <input type="text" name="discount_display" id="discountInv" class="form-control" value="0">
                        </div>
                        <input type="hidden" name="discount" id="discount_raw" value="0">
                    </div>

                    <div class="summary-item ppn">
                        <h4>PPN (11%)</h4>
                        <div class="value" id="ppnDisplay">Rp 0</div>
                        <input type="hidden" name="ppn_raw_value" id="ppn_raw_value" value="0">
                    </div>

                    <div class="summary-item pph">
                        <h4>PPH <small>(optional)</small></h4>
                        <div class="input-group">
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

                <div class="info-note"
                    style="background: rgba(76, 201, 240, 0.1); border-left-color: #4cc9f0; color: #4cc9f0;">
                    <i class="fas fa-info-circle"></i> <strong>Note:</strong> Subtotal sudah dikurangi diskon per item.
                    PPN dihitung dari Subtotal setelah diskon invoice, dan PPH adalah potongan setelah PPN.
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

    /* ========== AI AUTO FILL ========== */
    function updateInvoiceAiProviderForm(showStatus = true) {
        const configs = JSON.parse($('#invoiceAiProviderConfigs').text() || '{}');
        const config = configs[$('#invoiceAiProvider').val()] || {};
        $('#invoiceAiBaseUrl').val(config.base_url || '');
        $('#invoiceAiModel').empty();
        if (config.model) $('#invoiceAiModel').append($('<option>', {value: config.model, text: config.model}));
        $('#invoiceAiApiKey').val('');
        if (showStatus) $('#invoiceAiSettingsStatus').removeClass().addClass('small mt-2 text-muted').text(config.configured ? 'API key tersimpan. Kosongkan field API key untuk memakai key tersimpan.' : 'API key belum tersimpan untuk provider ini.');
    }

    $('#invoiceAiProvider').on('change', function() { updateInvoiceAiProviderForm(); });

    $('#loadInvoiceAiModels').on('click', async function() {
        const $button = $(this), apiKey = $('#invoiceAiApiKey').val().trim(), baseUrl = $('#invoiceAiBaseUrl').val().trim();
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Loading...');
        try {
            const response = await fetch('ai_settings_api.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'models', provider:$('#invoiceAiProvider').val(), api_key:apiKey, base_url:baseUrl})});
            const payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.message || 'Model gagal dimuat.');
            const $select = $('#invoiceAiModel').empty();
            payload.data.models.forEach(model => $select.append($('<option>', {value:model.id, text:model.label})));
            $('#invoiceAiSettingsStatus').removeClass().addClass('small mt-2 text-success').text(payload.message);
        } catch (error) { $('#invoiceAiSettingsStatus').removeClass().addClass('small mt-2 text-danger').text(error.message); }
        finally { $button.prop('disabled', false).html('<i class="fas fa-sync mr-1"></i>Load Models'); }
    });

    $('#saveInvoiceAiSettings').on('click', async function() {
        const $button = $(this), model = $('#invoiceAiModel').val();
        if (!model) { $('#invoiceAiSettingsStatus').removeClass().addClass('small mt-2 text-danger').text('Pilih model terlebih dahulu.'); return; }
        $button.prop('disabled', true);
        try {
            const response = await fetch('ai_settings_api.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'save', provider:$('#invoiceAiProvider').val(), api_key:$('#invoiceAiApiKey').val(), base_url:$('#invoiceAiBaseUrl').val(), model})});
            const payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.message || 'Pengaturan gagal disimpan.');
            $('#invoiceAiSettingsStatus').removeClass().addClass('small mt-2 text-success').text(payload.message);
            setTimeout(() => $('#invoiceAiSettingsModal').modal('hide'), 700);
        } catch (error) { $('#invoiceAiSettingsStatus').removeClass().addClass('small mt-2 text-danger').text(error.message); }
        finally { $button.prop('disabled', false); }
    });

    $('#generateInvoiceAiBtn').on('click', async function() {
        const brief = $('#invoiceAiBrief').val().trim();
        if (!brief) { $('#invoiceAiStatus').text('Brief wajib diisi.'); return; }
        const $button = $(this);
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Membuat draft...');
        $('#invoiceAiStatus').text('Menghubungi provider AI...');
        try {
            const customers = $('#customer_id option').map(function() { return this.value ? {id: Number(this.value), label: $(this).text().trim()} : null; }).get().filter(Boolean);
            const response = await fetch('gemini_invoice_autofill.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({brief, customers, provider:$('#invoiceAiProvider').val()})});
            const payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.message || 'Gagal membuat draft.');
            const draft = payload.data;
            if (!Array.isArray(draft.items) || !draft.items.length) throw new Error('Draft tidak memiliki item.');
            const customerLabel = draft.customer_id ? $('#customer_id option[value="' + draft.customer_id + '"]').text().trim() : 'Tidak ditentukan';
            const summaryParts = [draft.items.length + ' item'];
            if (customerLabel !== 'Tidak ditentukan') summaryParts.push('customer: ' + customerLabel);
            if (draft.invoice_date) summaryParts.push('tanggal: ' + draft.invoice_date);
            if (draft.po_number) summaryParts.push('PO: ' + draft.po_number);
            $('#invoiceAiApplySummary').text(summaryParts.join(' · ') + ' akan diterapkan.');
            $('#invoiceAiApplyModal').data('draft', draft).modal('show');
        } catch (error) { $('#invoiceAiStatus').text('Gagal membuat draft.'); alert(error.message); }
        finally { $button.prop('disabled', false).html('<i class="fas fa-wand-magic-sparkles mr-1"></i>Generate Draft AI'); }
    });

    $('#applyInvoiceAiDraft').on('click', function() {
        const draft = $('#invoiceAiApplyModal').data('draft');
        if (!draft) return;
        $('#customer_id').val(draft.customer_id ? String(draft.customer_id) : '').trigger('change');
        if (draft.invoice_date) {
            $('#created_at_inv').val(draft.invoice_date);
            updateInvoiceNumber();
        }
        if (draft.po_number) $('#po_number').val(draft.po_number);
        $('#note').val(draft.note || '');
        $('#itemsTableInv tbody').empty();
        (draft.items || []).forEach(function(item) {
            $('#addRowInv').trigger('click');
            const $row = $('#itemsTableInv tbody tr').last();
            $row.find('input[name="description[]"]').val(item.description || '');
            $row.find('input[name="qty[]"]').val(Math.max(0.01, Number(item.qty) || 1));
            $row.find('select[name="satuan[]"]').val(item.unit || 'Unit');
            const unitPrice = Math.max(0, Number(item.unit_price) || 0);
            $row.find('input[name="unit_price_display[]"]').val(unitPrice ? formatRupiah(unitPrice) : '');
            $row.find('input[name="unit_price_raw[]"]').val(unitPrice);
        });
        recalcInv();
        $('#invoiceAiApplyModal').modal('hide');
        $('#invoiceAiStatus').text('Draft diterapkan. Periksa data invoice sebelum disimpan.');
    });

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
            let grossAmount = qty * unit_raw;
            let itemDiscount = parseNumber(tr.find('input[name="item_discount_raw[]"]').val());
            if (itemDiscount > grossAmount) itemDiscount = grossAmount;
            tr.find('input[name="item_discount_raw[]"]').val(itemDiscount);
            let amount = grossAmount - itemDiscount;

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

    /* ========== ITEM DISCOUNT HANDLING ========== */
    $(document).on("focus", ".item_discount", function() {
        let raw = parseNumber($(this).closest("td").find('input[name="item_discount_raw[]"]').val());
        $(this).val(raw === 0 ? "" : raw);
    });

    $(document).on("input", ".item_discount", function() {
        let num = parseNumber($(this).val());
        $(this).closest("td").find('input[name="item_discount_raw[]"]').val(num);
        recalcInv();
    });

    $(document).on("blur", ".item_discount", function() {
        let hidden = $(this).closest("td").find('input[name="item_discount_raw[]"]');
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
                    <input type="text" name="unit_price_display[]" class="form-control unit_price" placeholder="0" required>
                    <input type="hidden" name="unit_price_raw[]" value="0">
                </td>
                <td>
                    <input type="text" name="item_discount_display[]" class="form-control item_discount" placeholder="0">
                    <input type="hidden" name="item_discount_raw[]" value="0">
                </td>
                <td>
                    <input type="text" name="amount_display[]" class="form-control amount" value="0" readonly>
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

        // Diskon per item tidak boleh lebih besar dari harga kotor item.
        $('.item_discount').each(function() {
            const tr = $(this).closest('tr');
            const gross = parseNumber(tr.find('.qty').val()) * parseNumber(tr.find(
                'input[name="unit_price_raw[]"]').val());
            const itemDiscount = parseNumber(tr.find('input[name="item_discount_raw[]"]').val());
            if (itemDiscount > gross) {
                messages.push("Item discount cannot be greater than the item's gross amount");
                $(this).addClass('is-invalid');
                isValid = false;
            } else {
                $(this).removeClass('is-invalid');
            }
        });

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
        $(this).find('button[type="submit"]').html('<i class="fas fa-spinner fa-spin"></i> Processing...').prop(
            'disabled', true);
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

        $(".item_discount").each(function() {
            let hidden = $(this).closest("td").find('input[name="item_discount_raw[]"]');
            $(this).val(formatRupiah(parseNumber(hidden.val())));
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
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.02);
        }

        100% {
            transform: scale(1);
        }
    }

    .is-invalid {
        border-color: #f72585 !important;
        box-shadow: 0 0 0 3px rgba(247, 37, 133, 0.1) !important;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .invoice-card,
    .items-container,
    .summary-container {
        animation: fadeIn 0.6s ease-out;
    }

    /* Loading spinner */
    .fa-spinner {
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }
    </style>
</body>

</html>

<?php include 'footer.php'; ?>