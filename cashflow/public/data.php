<?php
/**
 * Cashflow Operational - Public Data Page
 * 
 * View-only page for technicians to see their transaction data
 * Filter by technician name, date, and category
 */

// Database connection
require_once __DIR__ . '/../../db.php';

// Get filter parameters
$filterName = $_GET['filter_name'] ?? '';
$filterDate = $_GET['filter_date'] ?? '';
$filterCategory = $_GET['filter_category'] ?? '';

// Build WHERE clause
$where = [];
$params = [];
$types = '';

if (!empty($filterName)) {
    $where[] = "technician_name LIKE ?";
    $params[] = "%{$filterName}%";
    $types .= 's';
}
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

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get summary stats
$summarySql = "SELECT 
    COUNT(*) as total_transactions,
    COALESCE(SUM(amount), 0) as total_amount
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

// Base URL for photo
$base_prefix = '../../uploads/';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pengeluaran Operasional</title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <style>
    :root {
        --brand-1: #610b0bad;
        --brand-2: #740202;
        --brand-bg: #eef1f8;
        --cf-primary: #4f46e5;
        --cf-primary-dark: #3730a3;
    }

    * {
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    body {
        background: linear-gradient(160deg, var(--brand-bg) 0%, #e4e8f5 100%);
        min-height: 100vh;
        padding: clamp(16px, 4vw, 40px) 0;
    }

    .page-wrap {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 16px;
    }

    .brand-logo-wrap {
        display: flex;
        justify-content: center;
        margin-bottom: 12px;
    }

    .brand-logo-wrap img {
        height: 52px;
        width: auto;
        object-fit: contain;
        filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.15));
        background: #fff;
        padding: 6px 10px;
        border-radius: 10px;
    }

    .card {
        border: none;
        border-radius: 20px;
        box-shadow: 0 12px 32px -10px rgba(79, 70, 229, 0.25);
        overflow: hidden;
    }

    .card-header {
        background: linear-gradient(135deg, var(--brand-1) 0%, var(--brand-2) 100%);
        color: #fff;
        border: none;
        padding: 24px;
        text-align: center;
        position: relative;
    }

    .card-header h4 {
        font-weight: 700;
        margin: 0;
        font-size: 1.25rem;
    }

    .card-header p {
        margin: 4px 0 0;
        font-size: 0.85rem;
        opacity: 0.9;
    }

    .card-body {
        padding: clamp(20px, 5vw, 32px);
    }

    .summary-card {
        border: none;
        border-radius: 16px;
        padding: 20px;
        color: #fff;
        margin-bottom: 1rem;
        box-shadow: 0 8px 20px -8px rgba(0, 0, 0, 0.25);
    }

    .card-total-count {
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
    }

    .card-total-amount {
        background: linear-gradient(135deg, #0ea5e9 0%, #38bdf8 100%);
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

    .badge-category {
        padding: 0.4em 0.75em;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.72rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--brand-1) 0%, var(--brand-2) 100%);
        border: none;
        padding: 10px 20px;
        font-weight: 600;
        border-radius: 12px;
    }

    .btn-primary:hover {
        opacity: 0.95;
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

    .btn-back {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: #fff;
        padding: 8px 16px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 0.85rem;
        transition: background 0.2s;
    }

    .btn-back:hover {
        background: rgba(255, 255, 255, 0.3);
        color: #fff;
    }

    @media (max-width: 768px) {
        .page-wrap {
            padding: 0 12px;
        }

        .card-header {
            padding: 18px;
        }

        .data-card .card-header {
            flex-direction: column;
            gap: 12px;
            align-items: flex-start;
        }
    }
    </style>
</head>

<body>
    <div class="page-wrap">

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <div class="brand-logo-wrap" style="margin-bottom: 8px;">
                        <img src="../../img/afshin2.png" alt="Logo">
                    </div>
                    <h4>Data Pengeluaran Operasional</h4>
                    <p>CV Afshin Raya Teknik</p>
                </div>
                <a href="index.php" class="btn-back">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="summary-card card-total-count">
                    <div class="summary-label">Total Transaksi</div>
                    <div class="summary-value"><?= $summary['total_transactions'] ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="summary-card card-total-amount">
                    <div class="summary-label">Total Nominal</div>
                    <div class="summary-value"><?= formatRupiah($summary['total_amount']) ?></div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card filter-card">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Nama Teknisi</label>
                        <input type="text" name="filter_name" class="form-control" placeholder="Cari nama teknisi"
                            value="<?= htmlspecialchars($filterName) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="filter_date" class="form-control"
                            value="<?= htmlspecialchars($filterDate) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Kategori</label>
                        <select name="filter_category" class="form-select">
                            <option value="">Semua</option>
                            <option value="BBM" <?= $filterCategory === 'BBM' ? 'selected' : '' ?>>BBM</option>
                            <option value="Tol" <?= $filterCategory === 'Tol' ? 'selected' : '' ?>>Tol</option>
                            <option value="Sparepart" <?= $filterCategory === 'Sparepart' ? 'selected' : '' ?>>Sparepart
                            </option>
                            <option value="Lainnya" <?= $filterCategory === 'Lainnya' ? 'selected' : '' ?>>Lainnya
                            </option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="bi bi-search me-1"></i> Cari
                        </button>
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
                                <th>Nama Teknisi</th>
                                <th>Kategori</th>
                                <th>Nominal</th>
                                <th>Keterangan</th>
                                <th>Foto</th>
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
                                    $photoHtml = '<img src="' . $photoPath . '" class="photo-thumbnail" data-bs-toggle="modal" data-bs-target="#photoModal" data-src="' . $photoPath . '" alt="Foto">';
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
                                echo '</tr>';
                            }
                            $stmt->close();
                            ?>
                        </tbody>
                    </table>
                </div>
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

    <!-- jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <!-- Bootstrap 5 Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#cashflowTable').DataTable({
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

        // Photo thumbnail click - open modal
        $(document).on('click', '.photo-thumbnail', function() {
            const src = $(this).data('src');
            $('#photoFull').attr('src', src);
            const photoModal = new bootstrap.Modal(document.getElementById('photoModal'));
            photoModal.show();
        });
    });
    </script>
</body>

</html>