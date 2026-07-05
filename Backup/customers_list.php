<?php
require_once 'functions.php';
require_login();
// handle delete
if(isset($_GET['delete'])){
    $id = intval($_GET['delete']);
    mysqli_query($mysqli, "DELETE FROM customers WHERE id=$id");
    flash_set('Customer deleted successfully');
    header('Location: customers_list.php'); exit;
}
include 'header.php';

// Search functionality
$search = '';
$where = '';
if(isset($_GET['search']) && !empty($_GET['search'])) {
    $search = mysqli_real_escape_string($mysqli, $_GET['search']);
    $where = "WHERE name LIKE '%$search%' OR customer_no LIKE '%$search%' OR telephone LIKE '%$search%' OR pic LIKE '%$search%'";
}

// Get total count for stats
$countResult = mysqli_query($mysqli, "SELECT COUNT(*) as total FROM customers $where");
$totalCustomers = mysqli_fetch_assoc($countResult)['total'];

// Pagination
$perPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;
$totalPages = ceil($totalCustomers / $perPage);

$res = mysqli_query($mysqli, "SELECT * FROM customers $where ORDER BY id DESC LIMIT $offset, $perPage");

// Get customers for chart data (last 6 months)
$chartQuery = mysqli_query($mysqli, "
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
    FROM customers 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
");
$chartData = [];
while($row = mysqli_fetch_assoc($chartQuery)) {
    $chartData[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management System</title>
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
            --success: #4cc9f0;
            --danger: #f72585;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fb;
            color: #333;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.2);
        }
        
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header h1 i {
            font-size: 32px;
        }
        
        .header p {
            margin: 10px 0 0;
            opacity: 0.9;
            font-size: 16px;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 5px solid var(--primary);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-right: 20px;
            color: white;
        }
        
        .stat-card:nth-child(1) .stat-icon { background: linear-gradient(135deg, #4361ee, #4cc9f0); }
        .stat-card:nth-child(2) .stat-icon { background: linear-gradient(135deg, #f72585, #ff9e00); }
        .stat-card:nth-child(3) .stat-icon { background: linear-gradient(135deg, #3a0ca3, #7209b7); }
        
        .stat-content h3 {
            margin: 0;
            font-size: 32px;
            font-weight: 700;
            color: var(--dark);
        }
        
        .stat-content p {
            margin: 5px 0 0;
            color: var(--gray);
            font-size: 14px;
            font-weight: 500;
        }
        
        .actions-bar {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            justify-content: space-between;
        }
        
        .search-box {
            flex: 1;
            min-width: 300px;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 18px;
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
            transition: all 0.3s;
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
        
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        thead {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        thead th {
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 15px;
        }
        
        tbody tr {
            border-bottom: 1px solid var(--light-gray);
            transition: background-color 0.2s;
        }
        
        tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        tbody td {
            padding: 18px 20px;
            color: var(--dark);
            font-size: 15px;
        }
        
        .customer-name {
            font-weight: 600;
            color: var(--primary);
        }
        
        .customer-no {
            font-family: monospace;
            background: var(--light);
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .badge-active {
            background: rgba(76, 201, 240, 0.1);
            color: #4cc9f0;
        }
        
        .actions-cell {
            display: flex;
            gap: 10px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 30px 0;
        }
        
        .page-link {
            padding: 10px 16px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            color: var(--dark);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .page-link:hover {
            background-color: var(--light);
            border-color: var(--primary);
        }
        
        .page-link.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-color: var(--primary);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 15px;
            font-weight: 500;
        }
        
        .alert-info {
            background: rgba(76, 201, 240, 0.1);
            color: #4cc9f0;
            border-left: 5px solid #4cc9f0;
        }
        
        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .chart-container h3 {
            margin-top: 0;
            color: var(--dark);
            font-size: 20px;
        }
        
        .chart-placeholder {
            height: 150px;
            background: linear-gradient(135deg, #f5f7fb, #e9ecef);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            font-size: 16px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 60px;
            margin-bottom: 20px;
            color: var(--light-gray);
        }
        
        .empty-state h3 {
            margin: 0 0 10px;
            color: var(--dark);
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header {
                padding: 20px;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .actions-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                min-width: 100%;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Icons (using Unicode for simplicity) */
        .icon::before {
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            display: inline-block;
            font-style: normal;
            font-variant: normal;
            text-rendering: auto;
            -webkit-font-smoothing: antialiased;
        }
        
        .icon-users::before { content: "\f0c0"; }
        .icon-chart::before { content: "\f201"; }
        .icon-search::before { content: "\f002"; }
        .icon-plus::before { content: "\f067"; }
        .icon-edit::before { content: "\f044"; }
        .icon-trash::before { content: "\f2ed"; }
        .icon-user::before { content: "\f007"; }
        .icon-phone::before { content: "\f095"; }
        .icon-id::before { content: "\f2c2"; }
        .icon-calendar::before { content: "\f073"; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-users"></i> Customer Management</h1>
            <p>Manage your customer information and interactions</p>
        </div>
        
        <?php if($msg = flash_get()): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($msg); ?>
        </div>
        <?php endif; ?>
        
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $totalCustomers; ?></h3>
                    <p>Total Customers</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo count($chartData) > 0 ? end($chartData)['count'] : 0; ?></h3>
                    <p>New This Month</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo round($totalCustomers/6); ?>/mo</h3>
                    <p>Average Growth Rate</p>
                </div>
            </div>
        </div>
        
        <div class="chart-container">
            <h3><i class="fas fa-chart-bar"></i> Customer Growth (Last 6 Months)</h3>
            <div class="chart-placeholder">
                <?php if(count($chartData) > 0): ?>
                    <div style="width: 100%; text-align: center;">
                        <p>Chart would display here with data for:</p>
                        <div style="display: flex; justify-content: center; gap: 20px; margin-top: 10px; flex-wrap: wrap;">
                            <?php foreach($chartData as $data): ?>
                                <div style="background: linear-gradient(135deg, #4361ee, #4cc9f0); color: white; padding: 8px 15px; border-radius: 6px;">
                                    <?php echo $data['month']; ?>: <?php echo $data['count']; ?> customers
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <p>No data available for the last 6 months</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="actions-bar">
            <form method="GET" class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search customers by name, phone, ID, or PIC..." value="<?php echo htmlspecialchars($search); ?>">
            </form>
            <div style="display: flex; gap: 10px;">
                <a href="customers_create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Customer
                </a>
                <a href="#" class="btn btn-success">
                    <i class="fas fa-download"></i> Export
                </a>
            </div>
        </div>
        
        <div class="table-container">
            <?php if(mysqli_num_rows($res) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer ID</th>
                        <th>Name</th>
                        <th>NPWP</th>
                        <th>Telephone</th>
                        <th>PIC</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=($page-1)*$perPage+1; while($row = mysqli_fetch_assoc($res)): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td>
                            <span class="customer-no"><?php echo htmlspecialchars($row['customer_no']); ?></span>
                        </td>
                        <td>
                            <div class="customer-name"><?php echo htmlspecialchars($row['name']); ?></div>
                        </td>
                        <td>
                            <div class="customer-name"><?php echo htmlspecialchars($row['npwp']); ?></div>
                        </td>
                        <td>
                            <i class="fas fa-phone" style="margin-right: 8px; color: var(--gray);"></i>
                            <?php echo htmlspecialchars($row['telephone']); ?>
                        </td>
                        <td>
                            <i class="fas fa-user-tie" style="margin-right: 8px; color: var(--gray);"></i>
                            <?php echo htmlspecialchars($row['pic']); ?>
                        </td>
                        <td>
                            <span class="badge badge-active">Active</span>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a class="btn btn-primary btn-sm" href="customers_edit.php?id=<?php echo $row['id']; ?>">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a class="btn btn-danger btn-sm" href="customers_list.php?delete=<?php echo $row['id']; ?>&page=<?php echo $page; ?>" onclick="return confirm('Are you sure you want to delete customer <?php echo htmlspecialchars($row['name']); ?>?')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <h3>No customers found</h3>
                <p><?php echo !empty($search) ? 'Try adjusting your search criteria' : 'Get started by adding your first customer'; ?></p>
                <?php if(empty($search)): ?>
                    <a href="customers_add.php" class="btn btn-primary" style="margin-top: 20px; width: auto;">
                        <i class="fas fa-plus"></i> Add Customer
                    </a>
                <?php else: ?>
                    <a href="customers_list.php" class="btn btn-primary" style="margin-top: 20px; width: auto;">
                        <i class="fas fa-times"></i> Clear Search
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if($totalPages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a class="page-link" href="customers_list.php?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            <?php endif; ?>
            
            <?php 
            $start = max(1, $page - 2);
            $end = min($totalPages, $start + 4);
            if($end - $start < 4) $start = max(1, $end - 4);
            
            for($p = $start; $p <= $end; $p++): ?>
                <a class="page-link <?php echo $p == $page ? 'active' : ''; ?>" href="customers_list.php?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>">
                    <?php echo $p; ?>
                </a>
            <?php endfor; ?>
            
            <?php if($page < $totalPages): ?>
                <a class="page-link" href="customers_list.php?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Auto-submit search form when typing stops
        let searchTimeout;
        document.querySelector('input[name="search"]').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 800);
        });
        
        // Add some interactivity to table rows
        document.querySelectorAll('tbody tr').forEach(row => {
            row.addEventListener('click', function(e) {
                // Don't trigger if clicking on action buttons
                if(!e.target.closest('.actions-cell')) {
                    this.style.backgroundColor = 'rgba(67, 97, 238, 0.08)';
                    setTimeout(() => {
                        this.style.backgroundColor = '';
                    }, 300);
                }
            });
        });
    </script>
</body>
</html>
<?php include 'footer.php'; ?>