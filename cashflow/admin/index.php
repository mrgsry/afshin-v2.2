<?php
/**
 * Cashflow Operational - Admin Page
 * 
 * Requires authentication via functions.php
 * Displays DataTable with all transactions, summary cards, and filters
 */

require_once __DIR__ . '/../../functions.php';

// Check session timeout (10 minutes = 600 seconds)
$session_timeout = 600;
if (isset($_SESSION['user']) && isset($_SESSION['LAST_ACTIVITY'])) {
    if (time() - $_SESSION['LAST_ACTIVITY'] > $session_timeout) {
        // Session expired
        session_unset();
        session_destroy();
        header('Location: ../../login.php?expired=1');
        exit;
    }
}

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: ../../login.php');
    exit;
}

// Update last activity time
$_SESSION['LAST_ACTIVITY'] = time();

// Get filter parameters
$filterDate = $_GET['filter_date'] ?? '';
$filterCategory = $_GET['filter_category'] ?? '';
$filterTechnician = $_GET['filter_technician'] ?? '';

// Build WHERE clause
$where = [];
$params = [];
$types = '';

if (!empty($filterDate)) {
    $where[] = "transaction_date = ?";
    $params[] = $filterDate;
    $types .= 's';
}
if (!empty($filterCategory)) {
    $where[] = "category = ?";
    $params[] = $filterCategory;
    $types .= 's';
}
if (!empty($filterTechnician)) {
    $where[] = "technician_name LIKE ?";
    $params[] = "%{$filterTechnician}%";
    $types .= 's';
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get summary stats
$summarySql = "SELECT 
    COUNT(*) as total_transactions,
    COALESCE(SUM(amount), 0) as total_amount,
    COALESCE(SUM(CASE WHEN category = 'BBM' THEN amount ELSE 0 END), 0) as total_bbm,
    COALESCE(SUM(CASE WHEN category = 'Tol' THEN amount ELSE 0 END), 0) as total_tol,
    COALESCE(SUM(CASE WHEN category = 'Sparepart' THEN amount ELSE 0 END), 0) as total_sparepart,
    COALESCE(SUM(CASE WHEN category = 'Lainnya' THEN amount ELSE 0 END), 0) as total_lainnya
    FROM cashflow_transactions $whereClause";

$summaryStmt = $mysqli->prepare($summarySql);
if (!empty($whereClause)) {
    $summaryStmt->bind_param($types, ...$params);
}
$summaryStmt->execute();
$summary = $summaryStmt->get_result()->fetch_assoc();
$summaryStmt->close();

// Format currency
function formatRupiah($amount)
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Include main app header
require_once __DIR__ . '/../../header.php';
?>

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<!-- DataTables (Bootstrap 5 build) -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<!-- Custom styles for this page -->
<style>
:root {
    --cf-primary: #4f46e5;
    --cf-primary-dark: #3730a3;
    --cf-bg: #f4f5f9;
}

body {
    background-color: var(--cf-bg);
}

.page-header {
    margin-bottom: 1.75rem;
}

.page-header h2 {
    font-weight: 700;
    color: #1f2337;
    margin-bottom: 0.15rem;
}

.page-header .text-muted {
    font-size: 0.9rem;
}

.summary-card {
    border: none;
    border-radius: 16px;
    padding: 22px;
    height: 100%;
    color: #fff;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 20px -8px rgba(0, 0, 0, 0.25);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.summary-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -8px rgba(0, 0, 0, 0.3);
}

.summary-card i.bg-icon {
    position: absolute;
    right: 14px;
    top: 14px;
    font-size: 2.2rem;
    opacity: 0.25;
}

.card-total-count {
    background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
}

.card-total-amount {
    background: linear-gradient(135deg, #0ea5e9 0%, #38bdf8 100%);
}

.card-bbm {
    background: linear-gradient(135deg, #f43f5e 0%, #fb7185 100%);
}

.card-tol {
    background: linear-gradient(135deg, #06b6d4 0%, #22d3ee 100%);
}

.card-sparepart {
    background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
}

.card-lainnya {
    background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
}

.summary-label {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    opacity: 0.9;
}

.summary-value {
    font-size: 1.55rem;
    font-weight: 700;
    margin-top: 6px;
}

.filter-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 16px -6px rgba(0, 0, 0, 0.12);
    margin-bottom: 1.75rem;
}

.filter-card .card-body {
    padding: 1.25rem 1.5rem;
}

.filter-card label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #6b7280;
    margin-bottom: 0.25rem;
}

.data-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 16px -6px rgba(0, 0, 0, 0.12);
}

.data-card .card-header {
    background: #fff;
    border-bottom: 1px solid #eef0f4;
    border-radius: 16px 16px 0 0;
    padding: 1.1rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.data-card .card-header h5 {
    font-weight: 700;
    margin: 0;
    color: #1f2337;
}

.photo-thumbnail {
    width: 46px;
    height: 46px;
    object-fit: cover;
    cursor: pointer;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    transition: transform 0.15s ease;
}

.photo-thumbnail:hover {
    transform: scale(1.08);
}

.btn-action {
    padding: 5px 12px;
    font-size: 0.78rem;
    border-radius: 8px;
}

.badge-category {
    padding: 0.4em 0.75em;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.72rem;
}

.btn-primary {
    background-color: var(--cf-primary);
    border-color: var(--cf-primary);
}

.btn-primary:hover {
    background-color: var(--cf-primary-dark);
    border-color: var(--cf-primary-dark);
}

table.dataTable thead th {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #6b7280;
    border-bottom: 2px solid #eef0f4;
}

table.dataTable tbody td {
    vertical-align: middle;
    font-size: 0.9rem;
}

.dt-buttons .btn {
    border-radius: 8px;
    font-size: 0.8rem;
}
</style>

<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h2><i class="bi bi-graph-up-arrow me-2"></i>Dashboard Kas Operasional</h2>
        <div class="text-muted">Ringkasan dan riwayat transaksi kas operasional teknisi</div>
    </div>
    <a href="../public/index.php" class="btn btn-primary" target="_blank">
        <i class="bi bi-pencil-square me-1"></i> Form Input Teknisi
    </a>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="summary-card card-total-count">
            <i class="bi bi-receipt bg-icon"></i>
            <div class="summary-label">Total Transaksi</div>
            <div class="summary-value"><?= $summary['total_transactions'] ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="summary-card card-total-amount">
            <i class="bi bi-wallet2 bg-icon"></i>
            <div class="summary-label">Total Nominal</div>
            <div class="summary-value"><?= formatRupiah($summary['total_amount']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="summary-card card-bbm">
            <i class="bi bi-fuel-pump bg-icon"></i>
            <div class="summary-label">BBM</div>
            <div class="summary-value"><?= formatRupiah($summary['total_bbm']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="summary-card card-tol">
            <i class="bi bi-signpost-split bg-icon"></i>
            <div class="summary-label">Tol</div>
            <div class="summary-value"><?= formatRupiah($summary['total_tol']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="summary-card card-sparepart">
            <i class="bi bi-tools bg-icon"></i>
            <div class="summary-label">Sparepart</div>
            <div class="summary-value"><?= formatRupiah($summary['total_sparepart']) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="summary-card card-lainnya">
            <i class="bi bi-three-dots bg-icon"></i>
            <div class="summary-label">Lainnya</div>
            <div class="summary-value"><?= formatRupiah($summary['total_lainnya']) ?></div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card filter-card">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Tanggal</label>
                <input type="date" name="filter_date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Kategori</label>
                <select name="filter_category" class="form-select">
                    <option value="">Semua</option>
                    <option value="BBM" <?= $filterCategory === 'BBM' ? 'selected' : '' ?>>BBM</option>
                    <option value="Tol" <?= $filterCategory === 'Tol' ? 'selected' : '' ?>>Tol</option>
                    <option value="Sparepart" <?= $filterCategory === 'Sparepart' ? 'selected' : '' ?>>Sparepart
                    </option>
                    <option value="Lainnya" <?= $filterCategory === 'Lainnya' ? 'selected' : '' ?>>Lainnya</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Teknisi</label>
                <input type="text" name="filter_technician" class="form-control" placeholder="Nama teknisi"
                    value="<?= htmlspecialchars($filterTechnician) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">
                    <i class="bi bi-funnel me-1"></i> Filter
                </button>
                <a href="index.php" class="btn btn-outline-secondary flex-fill">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- DataTable -->
<div class="card data-card">
    <div class="card-header">
        <h5><i class="bi bi-list-check me-2"></i>Daftar Transaksi</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="cashflowTable" class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tanggal</th>
                        <th>Teknisi</th>
                        <th>Kategori</th>
                        <th>Nominal</th>
                        <th>Keterangan</th>
                        <th>Foto</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                            $badgeMap = [
                                'BBM'       => 'bg-danger-subtle text-danger-emphasis',
                                'Tol'       => 'bg-info-subtle text-info-emphasis',
                                'Sparepart' => 'bg-success-subtle text-success-emphasis',
                                'Lainnya'   => 'bg-warning-subtle text-warning-emphasis',
                            ];

                            $sql = "SELECT * FROM cashflow_transactions $whereClause ORDER BY transaction_date DESC, created_at DESC";
                            $stmt = $mysqli->prepare($sql);
                            if (!empty($whereClause)) {
                                $stmt->bind_param($types, ...$params);
                            }
                            $stmt->execute();
                            $result = $stmt->get_result();

                            while ($row = $result->fetch_assoc()) {
                                $photoHtml = '';
                                 if (!empty($row['photo_path'])) {
                                     $photoPath = $base_prefix . htmlspecialchars($row['photo_path']);
                                     $photoHtml = '<img src="' . $photoPath . '" class="photo-thumbnail" data-id="' . $row['id'] . '" alt="Foto">';
                                 } else {
                                     $photoHtml = '<span class="text-muted">-</span>';
                                 }

                    $badgeClass = $badgeMap[$row['category']] ?? 'bg-secondary-subtle text-secondary-emphasis';

                    echo '<tr>';
                        echo '<td>' . $row['id'] . '</td>';
                        echo '<td>' . date('d/m/Y', strtotime($row['transaction_date'])) . '</td>';
                        echo '<td>' . htmlspecialchars($row['technician_name']) . '</td>';
                        echo '<td><span class="badge badge-category ' . $badgeClass . '">' .
                                htmlspecialchars($row['category']) . '</span></td>';
                        echo '<td class="fw-semibold">' . formatRupiah($row['amount']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['description'] ?? '-') . '</td>';
                        echo '<td>' . $photoHtml . '</td>';
                        echo '<td>';
                            echo '<div class="d-flex gap-2">';
                                echo '<button class="btn btn-warning btn-action"
                                    onclick="editTransaction(' . $row['id'] . ', \'' . addslashes(htmlspecialchars($row['technician_name'])) . '\', \'' . $row['category'] . '\', ' . $row['amount'] . ', \'' . addslashes(htmlspecialchars($row['description'] ?? '')) . '\', \'' . $row['transaction_date'] . '\')"><i
                                        class="bi bi-pencil-square me-1"></i>Edit</button>';
                                echo '<button class="btn btn-danger btn-action"
                                    onclick="deleteTransaction(' . $row['id'] . ')"><i
                                        class="bi bi-trash3 me-1"></i>Hapus</button>';
                            echo '</div>';
                            echo '</td>';
                        echo '</tr>';
                    }
                    $stmt->close();
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Photo Modal -->
<div class="modal fade" id="photoModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-camera me-2"></i>Foto Bukti</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="photoFull" src="" class="img-fluid rounded" alt="Foto Bukti">
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Transaksi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" id="editId">
                    <div class="mb-3">
                        <label for="editTransactionDate" class="form-label">Tanggal Transaksi</label>
                        <input type="date" class="form-control" id="editTransactionDate" required>
                    </div>
                    <div class="mb-3">
                        <label for="editTechnicianName" class="form-label">Nama Teknisi</label>
                        <input type="text" class="form-control" id="editTechnicianName" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label for="editCategory" class="form-label">Kategori</label>
                        <select class="form-select" id="editCategory" required>
                            <option value="">-- Pilih Kategori --</option>
                            <option value="BBM">BBM</option>
                            <option value="Tol">Tol</option>
                            <option value="Sparepart">Sparepart</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editAmount" class="form-label">Nominal (Rp)</label>
                        <input type="number" class="form-control" id="editAmount" required min="1">
                    </div>
                    <div class="mb-3">
                        <label for="editDescription" class="form-label">Keterangan</label>
                        <textarea class="form-control" id="editDescription" rows="3" maxlength="500"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-warning" onclick="saveEdit()">
                    <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- jQuery (needed by DataTables) -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>

<!-- Bootstrap 5 Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- DataTables (Bootstrap 5 build) -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable with export buttons
    $('#cashflowTable').DataTable({
        dom: 'Bfrtip',
        buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
        order: [
            [1, 'desc']
        ],
        language: {
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_ baris',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ entri',
            paginate: {
                first: 'Pertama',
                last: 'Terakhir',
                next: '>',
                previous: '<'
            }
        }
    });

    // Photo thumbnail click - open modal (Bootstrap 5 API)
    $(document).on('click', '.photo-thumbnail', function() {
        const src = $(this).attr('src');
        $('#photoFull').attr('src', src);
        const photoModal = new bootstrap.Modal(document.getElementById('photoModal'));
        photoModal.show();
    });
});

// Edit transaction - open modal with data
function editTransaction(id, technician_name, category, amount, description, transaction_date) {
    document.getElementById('editId').value = id;
    document.getElementById('editTechnicianName').value = technician_name;
    document.getElementById('editCategory').value = category;
    document.getElementById('editAmount').value = amount;
    document.getElementById('editDescription').value = description;
    document.getElementById('editTransactionDate').value = transaction_date;

    const editModal = new bootstrap.Modal(document.getElementById('editModal'));
    editModal.show();
}

// Save edit
function saveEdit() {
    const id = document.getElementById('editId').value;
    const technician_name = document.getElementById('editTechnicianName').value.trim();
    const category = document.getElementById('editCategory').value;
    const amount = document.getElementById('editAmount').value.trim();
    const description = document.getElementById('editDescription').value.trim();
    const transaction_date = document.getElementById('editTransactionDate').value;

    // Validate
    if (!technician_name) {
        alert('Nama teknisi wajib diisi');
        return;
    }
    if (!category) {
        alert('Kategori wajib dipilih');
        return;
    }
    if (!amount || parseFloat(amount) <= 0) {
        alert('Nominal harus lebih dari 0');
        return;
    }
    if (!transaction_date) {
        alert('Tanggal transaksi wajib diisi');
        return;
    }

    fetch('../api/edit.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: id,
                technician_name: technician_name,
                category: category,
                amount: amount,
                description: description,
                transaction_date: transaction_date
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('Data berhasil diupdate');
                location.reload();
            } else {
                const errorMsg = result.errors ? result.errors.join('\n') : result.message;
                alert('Gagal mengupdate: ' + errorMsg);
            }
        })
        .catch(error => {
            alert('Terjadi kesalahan: ' + error);
        });
}

// Delete transaction
function deleteTransaction(id) {
    if (!confirm('Yakin ingin menghapus transaksi ini?')) {
        return;
    }

    fetch('../api/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: id
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('Data berhasil dihapus');
                location.reload();
            } else {
                alert('Gagal menghapus: ' + result.message);
            }
        })
        .catch(error => {
            alert('Terjadi kesalahan: ' + error);
        });
}
</script>
</body>

</html>