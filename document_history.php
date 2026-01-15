<?php
require_once 'functions.php';
require_login();

// --- Konfigurasi Pagination ---
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// --- Filter dan Search ---
$search_po = trim($_GET['search_po'] ?? '');
$filter_document_type = $_GET['document_type'] ?? 'all';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Build WHERE conditions untuk masing-masing tabel
$where_conditions_invoice = [];
$where_conditions_delivery = [];
$where_conditions_ba = [];
$where_conditions_service = [];

// Search PO Number untuk semua tabel
if (!empty($search_po)) {
    $search_po_esc = mysqli_real_escape_string($mysqli, $search_po);
    
    $where_conditions_invoice[] = "i.po_number LIKE '%$search_po_esc%'";
    $where_conditions_delivery[] = "td.po_number LIKE '%$search_po_esc%'";
    $where_conditions_ba[] = "b.po_number LIKE '%$search_po_esc%'";
    $where_conditions_service[] = "sr.po_order_no LIKE '%$search_po_esc%'";
}

// Filter tanggal untuk semua tabel
if (!empty($filter_date_from)) {
    $date_from_condition_invoice = "DATE(i.created_at) >= '$filter_date_from'";
    $date_from_condition_delivery = "DATE(td.date_doc) >= '$filter_date_from'";
    $date_from_condition_ba = "DATE(b.tanggal_ba) >= '$filter_date_from'";
    $date_from_condition_service = "DATE(sr.date_doc) >= '$filter_date_from'";
    
    $where_conditions_invoice[] = $date_from_condition_invoice;
    $where_conditions_delivery[] = $date_from_condition_delivery;
    $where_conditions_ba[] = $date_from_condition_ba;
    $where_conditions_service[] = $date_from_condition_service;
}

if (!empty($filter_date_to)) {
    $date_to_condition_invoice = "DATE(i.created_at) <= '$filter_date_to'";
    $date_to_condition_delivery = "DATE(td.date_doc) <= '$filter_date_to'";
    $date_to_condition_ba = "DATE(b.tanggal_ba) <= '$filter_date_to'";
    $date_to_condition_service = "DATE(sr.date_doc) <= '$filter_date_to'";
    
    $where_conditions_invoice[] = $date_to_condition_invoice;
    $where_conditions_delivery[] = $date_to_condition_delivery;
    $where_conditions_ba[] = $date_to_condition_ba;
    $where_conditions_service[] = $date_to_condition_service;
}

// Gabungkan kondisi WHERE untuk masing-masing tabel
$where_sql_invoice = !empty($where_conditions_invoice) ? ' WHERE ' . implode(' AND ', $where_conditions_invoice) : '';
$where_sql_delivery = !empty($where_conditions_delivery) ? ' WHERE ' . implode(' AND ', $where_conditions_delivery) : '';
$where_sql_ba = !empty($where_conditions_ba) ? ' WHERE ' . implode(' AND ', $where_conditions_ba) : '';
$where_sql_service = !empty($where_conditions_service) ? ' WHERE ' . implode(' AND ', $where_conditions_service) : '';

// Query untuk data gabungan semua dokumen
$query = "
    SELECT 
        'invoice' COLLATE utf8mb4_general_ci AS document_type,
        i.id AS document_id,
        i.invoice_no COLLATE utf8mb4_general_ci AS document_number,
        i.created_at AS document_date,
        i.po_number COLLATE utf8mb4_general_ci AS po_number,
        i.total,
        c.name COLLATE utf8mb4_general_ci AS customer_name,
        NULL AS delivery_note_no,
        NULL AS ba_no,
        NULL AS sr_no,
        i.customer_id
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    {$where_sql_invoice}

    UNION ALL

    SELECT 
        'travel_document' COLLATE utf8mb4_general_ci AS document_type,
        td.id AS document_id,
        td.travel_no COLLATE utf8mb4_general_ci AS document_number,
        td.date_doc AS document_date,
        td.po_number COLLATE utf8mb4_general_ci AS po_number,
        0 AS total,
        c.name COLLATE utf8mb4_general_ci AS customer_name,
        td.travel_no COLLATE utf8mb4_general_ci AS delivery_note_no,
        NULL AS ba_no,
        NULL AS sr_no,
        td.customer_id
    FROM travel_documents td
    LEFT JOIN customers c ON td.customer_id = c.id
    {$where_sql_delivery}

    UNION ALL

    SELECT 
        'berita_acara' COLLATE utf8mb4_general_ci AS document_type,
        b.id AS document_id,
        b.nomor_ba COLLATE utf8mb4_general_ci AS document_number,
        b.tanggal_ba AS document_date,
        b.po_number COLLATE utf8mb4_general_ci AS po_number,
        0 AS total,
        b.customer_name COLLATE utf8mb4_general_ci AS customer_name,
        NULL AS delivery_note_no,
        b.nomor_ba COLLATE utf8mb4_general_ci AS ba_no,
        NULL AS sr_no,
        0 AS customer_id
    FROM berita_acara b
    {$where_sql_ba}

    UNION ALL

    SELECT 
        'service_report' COLLATE utf8mb4_general_ci AS document_type,
        sr.id AS document_id,
        sr.doc_no COLLATE utf8mb4_general_ci AS document_number,
        sr.date_doc AS document_date,
        sr.po_order_no COLLATE utf8mb4_general_ci AS po_number,
        0 AS total,
        c.name COLLATE utf8mb4_general_ci AS customer_name,
        NULL AS delivery_note_no,
        NULL AS ba_no,
        sr.doc_no COLLATE utf8mb4_general_ci AS sr_no,
        sr.customer_id
    FROM service_reports sr
    LEFT JOIN customers c ON sr.customer_id = c.id
    {$where_sql_service}
";

// Filter berdasarkan document type
if ($filter_document_type != 'all') {
    $filter_document_type = mysqli_real_escape_string($mysqli, $filter_document_type);
    $query = "
        SELECT * FROM ($query) AS all_documents
        WHERE document_type = '$filter_document_type'
    ";
}

// Tambahkan sorting dan pagination
$query .= " 
    ORDER BY document_date DESC, document_id DESC 
    LIMIT $limit OFFSET $offset
";

$res = mysqli_query($mysqli, $query);

$res = mysqli_query($mysqli, $query);

// Query untuk total data
$count_query = preg_replace('/LIMIT\s+\d+\s+OFFSET\s+\d+/i', '', $query);

$count_result = mysqli_query(
    $mysqli,
    "SELECT COUNT(*) AS total FROM ($count_query) AS count_table"
);

$total_row = mysqli_fetch_assoc($count_result);
$total_data = (int)($total_row['total'] ?? 0);
$total_pages = ceil($total_data / $limit);

// Ambil semua PO untuk autocomplete
$po_query = "
    SELECT DISTINCT po_number FROM (
        SELECT po_number COLLATE utf8mb4_general_ci AS po_number FROM invoices
        WHERE po_number IS NOT NULL AND po_number != ''

        UNION ALL

        SELECT po_number COLLATE utf8mb4_general_ci FROM travel_documents
        WHERE po_number IS NOT NULL AND po_number != ''

        UNION ALL

        SELECT po_number COLLATE utf8mb4_general_ci FROM berita_acara
        WHERE po_number IS NOT NULL AND po_number != ''

        UNION ALL

        SELECT po_order_no COLLATE utf8mb4_general_ci FROM service_reports
        WHERE po_order_no IS NOT NULL AND po_order_no != ''
    ) AS all_po
    ORDER BY po_number
";

$po_result = mysqli_query($mysqli, $po_query);
// --- Export to Excel ---
if(isset($_GET['export']) && $_GET['export'] == 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="document_history_' . date('Ymd_His') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '</head>';
    echo '<body>';
    
    echo "<table border='1'>";
    echo "<tr>";
    echo "<th>No</th>";
    echo "<th>Jenis Dokumen</th>";
    echo "<th>Nomor Dokumen</th>";
    echo "<th>Tanggal</th>";
    echo "<th>PO Number</th>";
    echo "<th>Customer</th>";
    echo "<th>No. Invoice</th>";
    echo "<th>No. Surat Jalan</th>";
    echo "<th>No. Berita Acara</th>";
    echo "<th>No. Service Report</th>";
    echo "<th>Total</th>";
    echo "</tr>";
    
    $export_no = 1;
    $export_query = str_replace("LIMIT $limit OFFSET $offset", "", $query);
    $export_result = mysqli_query($mysqli, $export_query);
    
    while($row = mysqli_fetch_assoc($export_result)) {
        $document_date = !empty($row['document_date']) ? date('d/m/Y', strtotime($row['document_date'])) : '-';
        $document_type_name = getDocumentTypeName($row['document_type']);
        
        echo "<tr>";
        echo "<td>" . $export_no++ . "</td>";
        echo "<td>" . $document_type_name . "</td>";
        echo "<td>" . htmlspecialchars($row['document_number'] ?? '-') . "</td>";
        echo "<td>" . $document_date . "</td>";
        echo "<td>" . htmlspecialchars($row['po_number'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['customer_name'] ?? '-') . "</td>";
        echo "<td>" . ($row['document_type'] == 'invoice' ? htmlspecialchars($row['document_number']) : '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['delivery_note_no'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['ba_no'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['sr_no'] ?? '-') . "</td>";
        echo "<td>" . (!empty($row['total']) && $row['total'] > 0 ? number_format($row['total'], 2) : '-') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo '</body>';
    echo '</html>';
    exit;
}

// Helper functions
function getDocumentTypeName($type) {
    $types = [
        'invoice' => 'Invoice',
        'travel_document' => 'Surat Jalan',
        'berita_acara' => 'Berita Acara',
        'service_report' => 'Service Report'
    ];
    return $types[$type] ?? $type;
}

function getDocumentViewUrl($document_type, $document_id) {
    switch($document_type) {
        case 'invoice':
            return 'invoices_view.php?id=' . $document_id;
        case 'travel_document':
            return 'travel_document_view.php?id=' . $document_id;
        case 'berita_acara':
            return 'berita_acara_view.php?id=' . $document_id;
        case 'service_report':
            return 'service_report_view.php?id=' . $document_id;
        default:
            return '#';
    }
}

function getDocumentNumber($row) {
    switch($row['document_type']) {
        case 'invoice':
            return $row['document_number'];
        case 'travel_document':
            return $row['delivery_note_no'];
        case 'berita_acara':
            return $row['ba_no'];
        case 'service_report':
            return $row['sr_no'];
        default:
            return $row['document_number'] ?? '-';
    }
}

$error_msg = flash_get('error');
$success_msg = flash_get('success');

include 'header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-0">📄 Document History</h3>
            <p class="text-muted mb-0">Total: <strong><?php echo number_format($total_data); ?></strong> dokumen</p>
        </div>
        <div>
            <button type="button" class="btn btn-success" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Export to Excel
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo htmlspecialchars($error_msg); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo htmlspecialchars($success_msg); ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Filter & Search Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-search"></i> Pencarian Dokumen</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" id="filterForm" class="row g-3">
                <input type="hidden" name="page" id="pageInput" value="1">
                
                <div class="col-md-5">
                    <label for="search_po">
                        <i class="fas fa-search"></i> Cari Nomor PO / Dokumen
                    </label>
                    <div class="input-group">
                        <input type="text" 
                               class="form-control" 
                               id="search_po" 
                               name="search_po" 
                               value="<?php echo htmlspecialchars($search_po); ?>" 
                               placeholder="Masukkan nomor PO, Invoice, Surat Jalan, BA, atau Service Report..."
                               list="poNumbers">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i> Cari
                            </button>
                        </div>
                    </div>
                    <datalist id="poNumbers">
                        <?php foreach($po_numbers as $po): ?>
                            <option value="<?php echo htmlspecialchars($po); ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <small class="form-text text-muted">
                        Cari berdasarkan PO Number atau nomor dokumen lainnya
                    </small>
                </div>
                
                <div class="col-md-3">
                    <label for="document_type">
                        <i class="fas fa-filter"></i> Jenis Dokumen
                    </label>
                    <select class="form-control" id="document_type" name="document_type">
                        <option value="all" <?php echo ($filter_document_type == 'all') ? 'selected' : ''; ?>>-- Semua Dokumen --</option>
                        <option value="invoice" <?php echo ($filter_document_type == 'invoice') ? 'selected' : ''; ?>>📋 Invoice</option>
                        <option value="travel_document" <?php echo ($filter_document_type == 'travel_document') ? 'selected' : ''; ?>>🚚 Surat Jalan</option>
                        <option value="berita_acara" <?php echo ($filter_document_type == 'berita_acara') ? 'selected' : ''; ?>>📄 Berita Acara</option>
                        <option value="service_report" <?php echo ($filter_document_type == 'service_report') ? 'selected' : ''; ?>>🔧 Service Report</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="date_from">
                        <i class="fas fa-calendar-alt"></i> Dari Tanggal
                    </label>
                    <input type="date" class="form-control" id="date_from" name="date_from" 
                           value="<?php echo htmlspecialchars($filter_date_from); ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="date_to">
                        <i class="fas fa-calendar-alt"></i> Sampai Tanggal
                    </label>
                    <input type="date" class="form-control" id="date_to" name="date_to" 
                           value="<?php echo htmlspecialchars($filter_date_to); ?>">
                </div>
                
                <div class="col-12 mt-3">
                    <div class="d-flex justify-content-between">
                        <?php if(!empty($search_po) || $filter_document_type != 'all' || !empty($filter_date_from) || !empty($filter_date_to)): ?>
                        <div class="alert alert-info py-2 mb-0 flex-grow-1 mr-3">
                            <small>
                                <strong>Filter Aktif:</strong>
                                <?php if(!empty($search_po)): ?>
                                    <span class="badge badge-light mr-2">
                                        <i class="fas fa-search"></i> PO: <?php echo htmlspecialchars($search_po); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if($filter_document_type != 'all'): ?>
                                    <span class="badge badge-light mr-2">
                                        <i class="fas fa-filter"></i> <?php echo getDocumentTypeName($filter_document_type); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if(!empty($filter_date_from)): ?>
                                    <span class="badge badge-light mr-2">
                                        <i class="fas fa-calendar"></i> Dari: <?php echo htmlspecialchars($filter_date_from); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if(!empty($filter_date_to)): ?>
                                    <span class="badge badge-light mr-2">
                                        <i class="fas fa-calendar"></i> Sampai: <?php echo htmlspecialchars($filter_date_to); ?>
                                    </span>
                                <?php endif; ?>
                            </small>
                        </div>
                        <?php endif; ?>
                        <a href="document_history.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Summary -->
    <?php if(!empty($search_po)): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">
                <i class="fas fa-file-invoice"></i> Hasil Pencarian untuk PO: 
                <strong><?php echo htmlspecialchars($search_po); ?></strong>
            </h5>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="text-muted">Total Dokumen</h6>
                            <h3 class="text-primary"><?php echo number_format($total_data); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="text-muted">Invoice</h6>
                            <h3 class="text-success">
                                <?php 
                                $invoice_count = mysqli_query($mysqli, 
                                    "SELECT COUNT(*) as cnt FROM invoices WHERE po_number LIKE '%" . 
                                    mysqli_real_escape_string($mysqli, $search_po) . "%'");
                                echo number_format(mysqli_fetch_assoc($invoice_count)['cnt']);
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="text-muted">Surat Jalan</h6>
                            <h3 class="text-warning">
                                <?php 
                                $delivery_count = mysqli_query($mysqli, 
                                    "SELECT COUNT(*) as cnt FROM travel_documents WHERE po_number LIKE '%" . 
                                    mysqli_real_escape_string($mysqli, $search_po) . "%'");
                                echo number_format(mysqli_fetch_assoc($delivery_count)['cnt']);
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="text-muted">Service Report</h6>
                            <h3 class="text-danger">
                                <?php 
                                $sr_count = mysqli_query($mysqli, 
                                    "SELECT COUNT(*) as cnt FROM service_reports WHERE po_order_no LIKE '%" . 
                                    mysqli_real_escape_string($mysqli, $search_po) . "%'");
                                echo number_format(mysqli_fetch_assoc($sr_count)['cnt']);
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Data Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-history"></i> Riwayat Dokumen
                <?php if(!empty($search_po)): ?>
                    <small class="text-muted"> untuk PO: <?php echo htmlspecialchars($search_po); ?></small>
                <?php endif; ?>
            </h5>
            <div>
                <span class="badge badge-info">Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?></span>
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0" style="font-size: 0.9rem;">
                    <thead class="thead-light">
                        <tr>
                            <th width="3%" class="text-center">No</th>
                            <th width="8%">Jenis</th>
                            <th width="12%">Nomor Dokumen</th>
                            <th width="8%">Tanggal</th>
                            <th width="12%">PO Number</th>
                            <th width="18%">Customer</th>
                            <th width="10%" class="text-center">Invoice</th>
                            <th width="10%" class="text-center">Surat Jalan</th>
                            <th width="10%" class="text-center">Berita Acara</th>
                            <th width="10%" class="text-center">Service Report</th>
                            <th width="8%" class="text-right">Total</th>
                            <th width="9%" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($res) > 0): ?>
                            <?php 
                            $no = $offset + 1;
                            while($row = mysqli_fetch_assoc($res)): 
                                $document_date = !empty($row['document_date']) ? date('d/m/Y', strtotime($row['document_date'])) : '-';
                                $document_type_name = getDocumentTypeName($row['document_type']);
                                $document_number = getDocumentNumber($row);
                                
                                // Determine badge color based on document type
                                $badge_class = [
                                    'invoice' => 'badge-primary',
                                    'travel_document' => 'badge-success',
                                    'berita_acara' => 'badge-warning',
                                    'service_report' => 'badge-info'
                                ][$row['document_type']] ?? 'badge-secondary';
                                
                                $icon = [
                                    'invoice' => '📋',
                                    'travel_document' => '🚚',
                                    'berita_acara' => '📄',
                                    'service_report' => '🔧'
                                ][$row['document_type']] ?? '📄';
                            ?>
                            <tr>
                                <td class="text-center align-middle"><?php echo $no++; ?></td>
                                <td class="align-middle">
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo $icon; ?> <?php echo $document_type_name; ?>
                                    </span>
                                </td>
                                <td class="align-middle">
                                    <strong><?php echo htmlspecialchars($document_number); ?></strong>
                                </td>
                                <td class="align-middle"><?php echo $document_date; ?></td>
                                <td class="align-middle">
                                    <?php if(!empty($row['po_number'])): ?>
                                        <span class="badge badge-secondary" style="font-size: 0.8rem;">
                                            <?php echo htmlspecialchars($row['po_number']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="align-middle">
                                    <div style="font-size: 0.85rem; font-weight: 500;">
                                        <?php echo htmlspecialchars($row['customer_name'] ?? '-'); ?>
                                    </div>
                                </td>
                                <td class="text-center align-middle">
                                    <?php if($row['document_type'] == 'invoice'): ?>
                                        <span class="badge badge-primary">
                                            <?php echo htmlspecialchars($row['document_number']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center align-middle">
                                    <?php if($row['document_type'] == 'travel_document'): ?>
                                        <span class="badge badge-success">
                                            <?php echo htmlspecialchars($row['delivery_note_no']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center align-middle">
                                    <?php if($row['document_type'] == 'berita_acara'): ?>
                                        <span class="badge badge-warning">
                                            <?php echo htmlspecialchars($row['ba_no']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center align-middle">
                                    <?php if($row['document_type'] == 'service_report'): ?>
                                        <span class="badge badge-info">
                                            <?php echo htmlspecialchars($row['sr_no']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right align-middle">
                                    <?php if(!empty($row['total']) && $row['total'] > 0): ?>
                                        <div style="font-family: monospace; font-size: 0.85rem; font-weight: bold;">
                                            <?php echo number_format($row['total'], 2); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center align-middle">
                                    <?php
                                    $view_url = getDocumentViewUrl($row['document_type'], $row['document_id']);
                                    ?>
                                    <a href="<?php echo $view_url; ?>" 
                                       class="btn btn-info btn-sm" 
                                       title="Lihat Detail"
                                       target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if($row['document_type'] == 'invoice'): ?>
                                    <a href="invoice_print.php?id=<?php echo $row['document_id']; ?>" 
                                       class="btn btn-warning btn-sm mt-1" 
                                       title="Cetak"
                                       target="_blank">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <?php elseif($row['document_type'] == 'berita_acara'): ?>
                                    <a href="berita_acara_print.php?id=<?php echo $row['document_id']; ?>" 
                                       class="btn btn-warning btn-sm mt-1" 
                                       title="Cetak"
                                       target="_blank">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <?php elseif($row['document_type'] == 'travel_document'): ?>
                                    <a href="travel_document_print.php?id=<?php echo $row['document_id']; ?>" 
                                       class="btn btn-warning btn-sm mt-1" 
                                       title="Cetak"
                                       target="_blank">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <?php elseif($row['document_type'] == 'service_report'): ?>
                                    <a href="service_report_print.php?id=<?php echo $row['document_id']; ?>" 
                                       class="btn btn-warning btn-sm mt-1" 
                                       title="Cetak"
                                       target="_blank">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-file-alt fa-3x mb-3"></i><br>
                                        <?php if(!empty($search_po)): ?>
                                            <h5>Tidak ada dokumen ditemukan untuk PO: <strong><?php echo htmlspecialchars($search_po); ?></strong></h5>
                                            <p class="mb-3">Silakan coba dengan nomor PO yang lain.</p>
                                        <?php elseif($filter_document_type != 'all' || !empty($filter_date_from) || !empty($filter_date_to)): ?>
                                            <h5>Tidak ada dokumen yang sesuai dengan filter yang dipilih.</h5>
                                            <p class="mb-3">Silakan atur filter yang berbeda atau reset filter.</p>
                                        <?php else: ?>
                                            <h5>Belum ada data dokumen.</h5>
                                            <p class="mb-3">Sistem akan menampilkan semua dokumen setelah Anda membuatnya.</p>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-secondary mt-2" onclick="resetFilter()">
                                            <i class="fas fa-redo"></i> Reset Pencarian
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="card-footer">
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mb-0">
                    <!-- Previous Page -->
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="#" onclick="goToPage(<?php echo $page - 1; ?>)">
                            <i class="fas fa-chevron-left"></i> Prev
                        </a>
                    </li>
                    
                    <!-- Page Numbers -->
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="#" onclick="goToPage(1)">1</a></li>';
                        if($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    
                    for($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="#" onclick="goToPage(<?php echo $i; ?>)">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php
                    if($end_page < $total_pages) {
                        if($end_page < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        echo '<li class="page-item"><a class="page-link" href="#" onclick="goToPage(' . $total_pages . ')">' . $total_pages . '</a></li>';
                    }
                    ?>
                    
                    <!-- Next Page -->
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="#" onclick="goToPage(<?php echo $page + 1; ?>)">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="text-center mt-2 text-muted small">
                Menampilkan <?php echo ($total_data > 0) ? ($offset + 1) : 0; ?> - 
                <?php echo min($offset + $limit, $total_data); ?> dari <?php echo number_format($total_data); ?> data
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    // Set default tanggal jika kosong
    if($('#date_from').val() === '' && $('#date_to').val() === '') {
        const today = new Date().toISOString().split('T')[0];
        $('#date_to').val(today);
        
        // Set 90 hari sebelumnya (3 bulan)
        const firstDay = new Date();
        firstDay.setDate(firstDay.getDate() - 90);
        $('#date_from').val(firstDay.toISOString().split('T')[0]);
    }
    
    // Auto focus pada search field
    $('#search_po').focus();
    
    // Tooltip
    $('[title]').tooltip({
        placement: 'top',
        trigger: 'hover'
    });
    
    // Auto submit saat select berubah
    $('#document_type, #date_from, #date_to').on('change', function() {
        $('#filterForm').submit();
    });
    
    // Enter key submit
    $('#search_po').on('keypress', function(e) {
        if(e.which == 13) {
            e.preventDefault();
            $('#filterForm').submit();
        }
    });
});

// Fungsi untuk pergi ke halaman tertentu
function goToPage(page) {
    if(page < 1 || page > <?php echo $total_pages; ?>) return;
    $('#pageInput').val(page);
    $('#filterForm').submit();
}

// Fungsi reset filter
function resetFilter() {
    window.location.href = 'document_history.php';
}

// Fungsi export Excel
function exportToExcel() {
    // Ambil semua parameter filter
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    
    // Redirect ke URL export
    window.location.href = 'document_history.php?' + params.toString();
}
</script>

<style>
.card {
    border-radius: 10px;
    border: 1px solid #e3e6f0;
}
.table th {
    font-weight: 600;
    background-color: #f8f9fa;
    vertical-align: middle;
    font-size: 0.85rem;
    padding: 12px 8px;
    white-space: nowrap;
    border-bottom: 2px solid #e3e6f0;
}
.table td {
    padding: 10px 8px;
    vertical-align: middle;
    border-top: 1px solid #e3e6f0;
}
.btn-sm {
    padding: 4px 8px;
    font-size: 0.8rem;
}
.badge {
    font-size: 0.75rem;
    padding: 0.35rem 0.65rem;
    font-weight: 500;
}
.badge-primary { background-color: #4e73df; }
.badge-success { background-color: #1cc88a; }
.badge-warning { background-color: #f6c23e; color: #000; }
.badge-info { background-color: #36b9cc; }
.badge-secondary { background-color: #858796; }
.pagination .page-item.active .page-link {
    background-color: #4e73df;
    border-color: #4e73df;
}
.table-hover tbody tr:hover {
    background-color: rgba(78, 115, 223, 0.05);
}
.input-group-text {
    background-color: #f8f9fa;
    border-color: #d1d3e2;
}
.form-control:focus {
    border-color: #bac8f3;
    box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
}
.card-header {
    background-color: #f8f9fc;
    border-bottom: 1px solid #e3e6f0;
}
.btn-group-sm .btn {
    margin: 1px;
}
</style>

<?php include 'footer.php'; ?>