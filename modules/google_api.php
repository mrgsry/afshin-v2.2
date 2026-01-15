<?php
require_once 'functions.php';
require_once 'db.php';
require_login();
include 'header.php';
require_once 'modules/google_api.php';


// Data untuk summary cards (existing code remains the same)
$custCount = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS cnt FROM customers"))['cnt'] ?? 0;
$quotCount = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS cnt FROM quotations"))['cnt'] ?? 0;
$invCount = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) AS cnt FROM invoices"))['cnt'] ?? 0;
$totalTransaksi = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT SUM(total) AS sum_total FROM invoices"))['sum_total'] ?? 0;

// Data invoice bulan ini
$current_month = date('Y-m');
$month_query = "SELECT COUNT(*) as total, SUM(total) as total_value FROM invoices WHERE DATE_FORMAT(created_at, '%Y-%m') = '{$current_month}'";
$month_result = mysqli_query($mysqli, $month_query);
$month_row = mysqli_fetch_assoc($month_result);
$invMonthCount = $month_row['total'] ?? 0;
$monthTotalValue = $month_row['total_value'] ?? 0;

// Data invoice kemarin
$yesterday = date('Y-m-d', strtotime('-1 day'));
$yesterday_query = "SELECT COUNT(*) as total, SUM(total) as total_value FROM invoices WHERE DATE(created_at) = '{$yesterday}'";
$yesterday_result = mysqli_query($mysqli, $yesterday_query);
$yesterday_row = mysqli_fetch_assoc($yesterday_result);
$invYesterdayCount = $yesterday_row['total'] ?? 0;
$yesterdayTotalValue = $yesterday_row['total_value'] ?? 0;

// Data chart: jumlah invoice per customer (TOP 10)
$q_customer_invoice = mysqli_query($mysqli, "
    SELECT 
        c.name AS customer_name,
        COUNT(i.id) AS jumlah_invoice_customer,
        SUM(i.total) AS total_nilai
    FROM customers c
    JOIN invoices i ON c.id = i.customer_id
    GROUP BY c.id, c.name
    ORDER BY jumlah_invoice_customer DESC
    LIMIT 10
");

$customer_labels = [];
$invoicePerCustomer = [];
$customer_values = [];

while($row = mysqli_fetch_assoc($q_customer_invoice)) {
    $customer_labels[] = htmlspecialchars($row['customer_name']);
    $invoicePerCustomer[] = $row['jumlah_invoice_customer'];
    $customer_values[] = floatval($row['total_nilai']);
}

// Data chart: pendapatan per bulan (6 bulan terakhir)
$q_pendapatan = mysqli_query($mysqli, "
    SELECT 
        DATE_FORMAT(created_at, '%b %Y') AS bulan,
        DATE_FORMAT(created_at, '%Y-%m') AS bulan_tahun,
        COUNT(*) AS jumlah_invoice,
        SUM(total) AS total_bulan
    FROM invoices
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY YEAR(created_at), MONTH(created_at)
");

$labels = [];
$invoicePerBulan = [];
$pendapatanPerBulan = [];

while($row = mysqli_fetch_assoc($q_pendapatan)) {
    $labels[] = $row['bulan'];
    $invoicePerBulan[] = $row['jumlah_invoice'];
    $pendapatanPerBulan[] = $row['total_bulan'];
}

// Data untuk quick stats
$top_customers = [];
$top_customers_query = mysqli_query($mysqli, "
    SELECT 
        c.name,
        COUNT(i.id) as invoice_count,
        SUM(i.total) as total_value
    FROM customers c
    JOIN invoices i ON c.id = i.customer_id
    GROUP BY c.id, c.name
    ORDER BY total_value DESC
    LIMIT 5
");

while($row = mysqli_fetch_assoc($top_customers_query)) {
    $top_customers[] = $row;
}

// Data untuk chart total PO Customers
$po_customers_query = mysqli_query($mysqli, "
    SELECT 
        c.name AS customer_name,
        COUNT(i.id) AS total_po
    FROM customers c
    LEFT JOIN invoices i ON c.id = i.customer_id
    GROUP BY c.id, c.name
    HAVING COUNT(i.id) > 0
    ORDER BY total_po DESC
    LIMIT 8
");

$po_customer_labels = [];
$po_customer_data = [];
$po_customer_colors = ['#6366F1', '#10B981', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#14B8A6'];

while($row = mysqli_fetch_assoc($po_customers_query)) {
    $po_customer_labels[] = htmlspecialchars($row['customer_name']);
    $po_customer_data[] = $row['total_po'];
}

// Data tambahan untuk statistik PO
$total_customers_with_po = mysqli_fetch_assoc(mysqli_query($mysqli, "
    SELECT COUNT(DISTINCT customer_id) as total FROM invoices
"))['total'] ?? 0;

$avg_po_per_customer = $total_customers_with_po > 0 ? 
    round($invCount / $total_customers_with_po, 1) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CV Afshin Raya Teknik</title>
    <style>
        :root {
            --primary-color: #6366F1;
            --secondary-color: #8B5CF6;
            --success-color: #10B981;
            --warning-color: #F59E0B;
            --info-color: #3B82F6;
            --dark-color: #1F2937;
            --light-color: #F9FAFB;
            --gray-color: #6B7280;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --card-shadow-hover: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .dashboard-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            min-height: 100vh;
            border-radius: 0;
        }

        @media (min-width: 1200px) {
            .dashboard-container {
                border-radius: 20px;
                margin: 20px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            }
        }

        /* Header Styles */
        .dashboard-header {
            padding: 2rem 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 0 0 20px 20px;
            margin-bottom: 2rem;
            color: white;
        }

        .greeting-text {
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .date-badge {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 0.5rem 1rem;
            border-radius: 50px;
        }

        /* Card Styles */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--card-shadow-hover);
        }

        .stat-card-primary::before { background: linear-gradient(90deg, #6366F1, #8B5CF6); }
        .stat-card-warning::before { background: linear-gradient(90deg, #F59E0B, #F97316); }
        .stat-card-success::before { background: linear-gradient(90deg, #10B981, #059669); }
        .stat-card-info::before { background: linear-gradient(90deg, #3B82F6, #2563EB); }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            background: linear-gradient(135deg, currentColor, transparent);
            opacity: 0.9;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--dark-color), var(--gray-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0.5rem 0;
        }

        .stat-trend {
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Chart Cards */
        .chart-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            border: none;
            height: 100%;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #F3F4F6;
        }

        .chart-title {
            font-weight: 600;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-title i {
            color: var(--primary-color);
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* Top Customers Table */
        .customer-table {
            width: 100%;
        }

        .customer-table tr {
            transition: all 0.2s ease;
        }

        .customer-table tr:hover {
            background: #F9FAFB;
            transform: scale(1.01);
        }

        .customer-rank {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .customer-rank-1 { background: linear-gradient(135deg, #F59E0B, #D97706); }
        .customer-rank-2 { background: linear-gradient(135deg, #6B7280, #4B5563); }
        .customer-rank-3 { background: linear-gradient(135deg, #92400E, #78350F); }

        /* Quick Actions */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .action-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            color: var(--dark-color);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--card-shadow);
            border: 2px solid transparent;
        }

        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--card-shadow-hover);
            border-color: var(--primary-color);
            color: var(--primary-color);
            text-decoration: none;
        }

        .action-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .action-card:nth-child(2) .action-icon { background: linear-gradient(135deg, #F59E0B, #D97706); }
        .action-card:nth-child(3) .action-icon { background: linear-gradient(135deg, #10B981, #059669); }
        .action-card:nth-child(4) .action-icon { background: linear-gradient(135deg, #3B82F6, #2563EB); }

        /* Dropdown Styles */
        .chart-dropdown .dropdown-toggle {
            background: #F3F4F6;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            color: var(--gray-color);
            transition: all 0.2s ease;
        }

        .chart-dropdown .dropdown-toggle:hover {
            background: #E5E7EB;
            color: var(--dark-color);
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            padding: 0.5rem;
        }

        .dropdown-item {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-header {
                padding: 1rem 0;
                border-radius: 0 0 16px 16px;
            }
            
            .stat-number {
                font-size: 1.75rem;
            }
            
            .chart-container {
                height: 250px;
            }
            
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #F3F4F6;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 4px;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Modern Header -->
        <div class="dashboard-header">
            <div class="container-fluid">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                    <div class="mb-3 mb-md-0">
                        <h1 class="h2 mb-2 font-weight-bold">Dashboard Analytics</h1>
                        <p class="greeting-text mb-0">
                            <i class="fas fa-rocket mr-2"></i>Selamat datang, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Pengguna'); ?>! 
                            Sistem siap melayani Anda.
                        </p>
                    </div>
                    <div class="date-badge">
                        <i class="fas fa-calendar-alt mr-2"></i>
                        <?php echo date('l, d F Y'); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <?php if($msg = flash_get()): ?>
                <div class="alert alert-info alert-dismissible fade show fade-in" role="alert" style="border-radius: 12px; border: none;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-info-circle mr-3" style="font-size: 1.25rem;"></i>
                        <div><?php echo htmlspecialchars($msg); ?></div>
                    </div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Summary Stats with Modern Cards -->
            <div class="row mb-4 fade-in">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card stat-card-primary">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted mb-1">Total Customers</div>
                                <div class="stat-number"><?php echo $custCount; ?></div>
                                <div class="d-flex align-items-center mt-2">
                                    <span class="stat-trend">
                                        <i class="fas fa-users mr-1"></i>
                                        <?php echo $total_customers_with_po; ?> aktif
                                    </span>
                                </div>
                            </div>
                            <div class="stat-icon" style="color: #6366F1;">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card stat-card-warning">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted mb-1">Total Quotations</div>
                                <div class="stat-number"><?php echo $quotCount; ?></div>
                                <div class="d-flex align-items-center mt-2">
                                    <span class="stat-trend" style="background: rgba(245, 158, 11, 0.1); color: #F59E0B;">
                                        <i class="fas fa-file-contract mr-1"></i>
                                        Penawaran aktif
                                    </span>
                                </div>
                            </div>
                            <div class="stat-icon" style="color: #F59E0B;">
                                <i class="fas fa-file-contract"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card stat-card-success">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted mb-1">Total Invoices</div>
                                <div class="stat-number"><?php echo $invCount; ?></div>
                                <div class="d-flex align-items-center mt-2">
                                    <span class="stat-trend" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
                                        <i class="fas fa-chart-line mr-1"></i>
                                        <?php echo $avg_po_per_customer; ?> PO/customer
                                    </span>
                                </div>
                            </div>
                            <div class="stat-icon" style="color: #10B981;">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="stat-card stat-card-info">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="text-muted mb-1">Total Transaksi</div>
                                <div class="stat-number">Rp <?php echo number_format($totalTransaksi, 0, ',', '.'); ?></div>
                                <div class="d-flex align-items-center mt-2">
                                    <span class="stat-trend" style="background: rgba(59, 130, 246, 0.1); color: #3B82F6;">
                                        <i class="fas fa-arrow-up mr-1"></i>
                                        Bulan ini: Rp <?php echo number_format($monthTotalValue, 0, ',', '.'); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="stat-icon" style="color: #3B82F6;">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="row fade-in" style="animation-delay: 0.1s;">
                <!-- Invoice per Customer Chart -->
                <div class="col-xl-8 col-lg-7 mb-4">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h6 class="chart-title">
                                <i class="fas fa-chart-bar"></i>
                                Top 10 Customer berdasarkan Jumlah Invoice
                            </h6>
                            <div class="chart-dropdown dropdown">
                                <a class="dropdown-toggle" href="#" role="button" data-toggle="dropdown">
                                    <i class="fas fa-ellipsis-h"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <a class="dropdown-item" href="customers_list.php">
                                        <i class="fas fa-users mr-2"></i>Lihat Semua Customer
                                    </a>
                                    <a class="dropdown-item" href="invoices_create.php">
                                        <i class="fas fa-plus mr-2"></i>Buat Invoice Baru
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="invoicePerCustomerChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- PO Customer Chart -->
                <div class="col-xl-4 col-lg-5 mb-4">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h6 class="chart-title">
                                <i class="fas fa-chart-pie"></i>
                                Distribusi PO Customers
                            </h6>
                        </div>
                        <div class="chart-container">
                            <canvas id="poCustomerChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Second Charts Row -->
            <div class="row fade-in" style="animation-delay: 0.2s;">
                <!-- Revenue Chart -->
                <div class="col-xl-8 col-lg-7 mb-4">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h6 class="chart-title">
                                <i class="fas fa-chart-line"></i>
                                Performa Pendapatan (6 Bulan Terakhir)
                            </h6>
                            <div class="chart-dropdown dropdown">
                                <a class="dropdown-toggle" href="#" role="button" data-toggle="dropdown">
                                    <i class="fas fa-filter mr-1"></i> Filter
                                </a>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <h6 class="dropdown-header">Periode Waktu</h6>
                                    <a class="dropdown-item" href="#" onclick="filterChart('3month')">
                                        3 Bulan Terakhir
                                    </a>
                                    <a class="dropdown-item" href="#" onclick="filterChart('6month')">
                                        6 Bulan Terakhir
                                    </a>
                                    <a class="dropdown-item" href="#" onclick="filterChart('1year')">
                                        1 Tahun Terakhir
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="pendapatanChart"></canvas>
                        </div>
                    </div>
                </div>


                
                <!-- Top Customers -->
                <div class="col-xl-4 col-lg-5 mb-4">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h6 class="chart-title">
                                <i class="fas fa-trophy"></i>
                                Top 5 Customer Berdasarkan Nilai
                            </h6>
                        </div>
                        <div class="table-responsive">
                            <table class="customer-table">
                                <tbody>
                                    <?php if(empty($top_customers)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">
                                            <i class="fas fa-info-circle mr-2"></i>
                                            Belum ada data customer
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach($top_customers as $index => $customer): ?>
                                    <tr class="py-3">
                                        <td class="py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="customer-rank <?php echo $index < 3 ? 'customer-rank-' . ($index + 1) : ''; ?>">
                                                    <?php echo $index + 1; ?>
                                                </div>
                                                <div class="ml-3">
                                                    <div class="font-weight-bold"><?php echo htmlspecialchars($customer['name']); ?></div>
                                                    <small class="text-muted"><?php echo $customer['invoice_count']; ?> invoice</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-right py-3">
                                            <div class="font-weight-bold text-success">
                                                Rp <?php echo number_format($customer['total_value'], 0, ',', '.'); ?>
                                            </div>
                                            <small class="text-muted">Total nilai</small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="customers_list.php" class="btn btn-outline-primary btn-sm" style="border-radius: 8px;">
                                <i class="fas fa-eye mr-1"></i> Lihat Semua Customer
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row fade-in" style="animation-delay: 0.3s;">
                <div class="col-12 mb-4">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h6 class="chart-title">
                                <i class="fas fa-bolt"></i>
                                Quick Actions
                            </h6>
                        </div>
                        <div class="quick-actions-grid">
                            <a href="invoices_create.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-plus-circle"></i>
                                </div>
                                <div>
                                    <div class="font-weight-bold">Buat Invoice</div>
                                    <small class="text-muted">Buat invoice baru</small>
                                </div>
                            </a>
                            <a href="quotations_create.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-file-contract"></i>
                                </div>
                                <div>
                                    <div class="font-weight-bold">Buat Quotation</div>
                                    <small class="text-muted">Buat penawaran baru</small>
                                </div>
                            </a>
                            <a href="customers_create.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <div>
                                    <div class="font-weight-bold">Tambah Customer</div>
                                    <small class="text-muted">Tambah customer baru</small>
                                </div>
                            </a>
                            <a href="invoices_list.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-list"></i>
                                </div>
                                <div>
                                    <div class="font-weight-bold">Lihat Invoice</div>
                                    <small class="text-muted">Lihat semua invoice</small>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <script>
    // Format currency
    function formatCurrency(value) {
        return 'Rp ' + value.toLocaleString('id-ID');
    }

    // Chart 1: Invoice per Customer (Dual Axis)
    const invoicePerCustomerCtx = document.getElementById('invoicePerCustomerChart').getContext('2d');
    new Chart(invoicePerCustomerCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($customer_labels); ?>,
            datasets: [
                {
                    label: 'Jumlah Invoice',
                    data: <?php echo json_encode($invoicePerCustomer); ?>,
                    backgroundColor: 'rgba(99, 102, 241, 0.7)',
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 1,
                    borderRadius: 8,
                    yAxisID: 'y'
                },
                {
                    label: 'Total Nilai',
                    data: <?php echo json_encode($customer_values); ?>,
                    type: 'line',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(31, 41, 55, 0.95)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 1,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (context.datasetIndex === 0) {
                                return `${label}: ${context.parsed.y} invoice`;
                            } else {
                                return `${label}: ${formatCurrency(context.parsed.y)}`;
                            }
                        }
                    }
                },
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        maxRotation: 45
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Jumlah Invoice',
                        color: '#666'
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    },
                    ticks: {
                        precision: 0
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Total Nilai (Rp)',
                        color: '#666'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        callback: function(value) {
                            return formatCurrency(value);
                        }
                    }
                }
            },
            animation: {
                duration: 1000,
                easing: 'easeOutQuart'
            }
        }
    });

    // Chart 2: Total PO Customers (Pie Chart)
    const poCustomerCtx = document.getElementById('poCustomerChart').getContext('2d');
    new Chart(poCustomerCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($po_customer_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($po_customer_data); ?>,
                backgroundColor: <?php echo json_encode($po_customer_colors); ?>,
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 20
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(31, 41, 55, 0.95)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 1,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} PO (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '65%',
            animation: {
                animateScale: true,
                animateRotate: true,
                duration: 1000,
                easing: 'easeOutQuart'
            }
        }
    });

    // Chart 3: Pendapatan per Bulan (Dual Axis)
    const pendapatanCtx = document.getElementById('pendapatanChart').getContext('2d');
    new Chart(pendapatanCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [
                {
                    label: 'Pendapatan',
                    data: <?php echo json_encode($pendapatanPerBulan); ?>,
                    borderColor: 'rgba(16, 185, 129, 1)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y'
                },
                {
                    label: 'Jumlah Invoice',
                    data: <?php echo json_encode($invoicePerBulan); ?>,
                    borderColor: 'rgba(59, 130, 246, 1)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(31, 41, 55, 0.95)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 1,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (context.datasetIndex === 0) {
                                return `${label}: ${formatCurrency(context.parsed.y)}`;
                            } else {
                                return `${label}: ${context.parsed.y} invoice`;
                            }
                        }
                    }
                },
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Pendapatan (Rp)',
                        color: '#666'
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    },
                    ticks: {
                        callback: function(value) {
                            return formatCurrency(value);
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Jumlah Invoice',
                        color: '#666'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        precision: 0
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            },
            animation: {
                duration: 1000,
                easing: 'easeOutQuart'
            }
        }
    });

    // Fungsi untuk filter chart
    function filterChart(period) {
        // Simulasi loading
        const pendapatanChartInstance = Chart.getChart('pendapatanChart');
        pendapatanChartInstance.data.datasets[0].data = pendapatanChartInstance.data.datasets[0].data.map(() => 0);
        pendapatanChartInstance.update('none');
        
        // Dalam implementasi nyata, ini akan memanggil API atau reload data
        setTimeout(() => {
            alert('Fitur filter ' + period + ' akan diimplementasikan pada versi berikutnya dengan integrasi AJAX');
            pendapatanChartInstance.data.datasets[0].data = <?php echo json_encode($pendapatanPerBulan); ?>;
            pendapatanChartInstance.update();
        }, 500);
        return false;
    }

    // Add animation to cards on scroll
    document.addEventListener('DOMContentLoaded', function() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = 1;
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, {
            threshold: 0.1
        });

        document.querySelectorAll('.fade-in').forEach((card) => {
            card.style.opacity = 0;
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });
    });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>