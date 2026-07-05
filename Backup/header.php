<?php
// header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'functions.php';

$current_page = basename($_SERVER['PHP_SELF']);
$is_login_page = ($current_page === 'login.php');

$body_class = 'hold-transition';
$body_class .= $is_login_page ? ' login-page' : ' ' . (is_logged_in() ? 'sidebar-mini' : '');

$page_icons = [
    'customers_create.php' => 'fa-user-plus',
    'customers_list.php' => 'fa-users',
    'quotations_create.php' => 'fa-file-invoice',
    'finance.php' => 'fa-chart-line'
];
$icon = $page_icons[$current_page] ?? 'fa-layer-group';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Afshin APP | Admin Panel</title>
    <link rel="icon" href="img/afshin2.png" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

    <?php if ($is_login_page): ?>
    <style>
        .login-page {
            background-image: url('img/bg_login.jpg') !important;
            background-size: cover !important; 
            background-position: center !important;
            background-repeat: no-repeat !important;
            background-attachment: fixed !important;
        }
        .login-logo a {
            color: white !important;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
        }
    </style>
    <?php endif; ?>

    <style>
        :root {
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 78px;
            --navbar-height: 57px;
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }
        
        /* Sidebar Fixed Styling */
        .sidebar-fixed {
            position: fixed !important;
            top: var(--navbar-height);
            left: 0;
            height: calc(100vh - var(--navbar-height));
            width: var(--sidebar-width);
            z-index: 1030;
            transition: all 0.3s ease;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        /* Sidebar Collapsed State */
        .sidebar-mini.sidebar-collapse .sidebar-fixed {
            width: var(--sidebar-collapsed-width);
        }
        
        .sidebar-mini.sidebar-collapse .sidebar-fixed .nav-sidebar .nav-link p,
        .sidebar-mini.sidebar-collapse .sidebar-fixed .nav-sidebar .nav-link .right {
            display: none !important;
        }
        
        .sidebar-mini.sidebar-collapse .sidebar-fixed .nav-sidebar .nav-treeview {
            display: none !important;
        }
        
        .sidebar-mini.sidebar-collapse .sidebar-fixed .brand-text,
        .sidebar-mini.sidebar-collapse .sidebar-fixed .user-panel .info,
        .sidebar-mini.sidebar-collapse .sidebar-fixed .nav-header {
            display: none !important;
        }
        
        /* Adjust content when sidebar is fixed */
        .content-wrapper {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - var(--navbar-height));
        }
        
        .sidebar-mini.sidebar-collapse .content-wrapper {
            margin-left: var(--sidebar-collapsed-width);
        }
        
        /* Responsive Breakpoints */
        @media (max-width: 991.98px) {
            .sidebar-fixed {
                transform: translateX(-100%);
                width: 250px;
            }
            
            .sidebar-open .sidebar-fixed {
                transform: translateX(0);
                box-shadow: 5px 0 15px rgba(0, 0, 0, 0.2);
            }
            
            .content-wrapper {
                margin-left: 0 !important;
            }
            
            .overlay {
                display: none;
                position: fixed;
                top: var(--navbar-height);
                left: 0;
                width: 100%;
                height: calc(100vh - var(--navbar-height));
                background: rgba(0, 0, 0, 0.5);
                z-index: 1029;
            }
            
            .sidebar-open .overlay {
                display: block;
            }
        }
        
        @media (max-width: 575.98px) {
            .navbar-modern .nav-link span {
                display: none;
            }
            
            .sidebar-fixed {
                width: 220px;
            }
        }
        
        /* Sidebar Styling Improvements */
        .nav-sidebar > .nav-item {
            margin-bottom: 0.2rem;
        }
        
        .nav-sidebar .nav-link {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin: 0.15rem 0.5rem;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        
        .nav-sidebar .nav-link:hover {
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.05));
            color: var(--primary-color);
            transform: translateX(5px);
        }
        
        .nav-sidebar .nav-link.active {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            color: white !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .nav-sidebar .nav-treeview .nav-link {
            padding-left: 2.5rem;
            font-size: 0.9rem;
        }
        
        .nav-sidebar .nav-treeview .nav-link:hover {
            background: rgba(102, 126, 234, 0.08);
        }
        
        .sidebar .brand-link {
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            background: white;
            padding: 0.8rem 1rem;
            height: var(--navbar-height);
            display: flex;
            align-items: center;
        }
        
        .sidebar .brand-text {
            font-weight: 700;
            font-size: 1.3rem;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            transition: all 0.3s ease;
        }
        
        .user-panel {
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            padding: 1rem;
            margin: 0;
        }
        
        .user-panel .info a {
            font-weight: 600;
            color: #333;
            font-size: 0.95rem;
        }
        
        /* Scrollbar Styling for Sidebar */
        .sidebar-fixed::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar-fixed::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .sidebar-fixed::-webkit-scrollbar-thumb {
            background: linear-gradient(var(--primary-color), var(--secondary-color));
            border-radius: 3px;
        }
        
        /* Card Header Styling */
        .card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            height: 4px;
            width: 100%;
            background: linear-gradient(90deg, var(--primary), var(--info));
        }
        
        .card-header {
            position: relative;
        }

        .form-control {
            border-radius: 10px;
            padding: 10px 14px;
            border: 1px solid #e0e0e0;
            transition: all 0.25s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.15rem rgba(0, 123, 255, 0.15);
        }

        /* Loading Overlay */
        #loading-overlay {
            display: flex;
            position: fixed;
            top:0;
            left:0;
            width:100%;
            height:100%;
            background: rgba(255,255,255,0.85);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #218838);
            border: none;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
        }

        #loading-overlay .spinner-border {
            width:3rem;
            height:3rem;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg);}
            100% { transform: rotate(360deg);}
        }

        /* Navbar Modern */
        .navbar-modern {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-bottom: none;
            box-shadow: 0 2px 15px rgba(0,0,0,.15);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--navbar-height);
            z-index: 1031;
        }

        .navbar-modern .nav-link {
            color: #ffffff !important;
            font-weight: 500;
            transition: all .25s ease;
        }

        .navbar-modern .nav-link:hover {
            background: rgba(255,255,255,.15);
            border-radius: 6px;
        }

        .navbar-modern .navbar-badge {
            font-size: 0.65rem;
            top: 6px;
            right: 6px;
        }

        .user-menu img.user-image {
            width: 32px;
            height: 32px;
            object-fit: cover;
        }

        .dropdown-menu {
            border-radius: 10px;
        }

        .dropdown-menu-lg {
            min-width: 280px;
        }
        
        /* Main Content Adjustments */
        body {
            padding-top: var(--navbar-height);
        }
        
        .wrapper {
            min-height: 100vh;
            position: relative;
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

    <script>
    $(document).ready(function() {
        // Hilangkan overlay setelah halaman siap
        $('#loading-overlay').fadeOut(1500);
        
        // Mobile sidebar toggle
        $('[data-widget="pushmenu"]').on('click', function(e) {
            e.preventDefault();
            $('body').toggleClass('sidebar-open');
            
            // Toggle overlay on mobile
            if ($(window).width() < 992) {
                if ($('body').hasClass('sidebar-open')) {
                    $('<div class="overlay"></div>').appendTo('body');
                } else {
                    $('.overlay').remove();
                }
            }
        });
        
        // Close sidebar when clicking overlay on mobile
        $(document).on('click', '.overlay', function() {
            $('body').removeClass('sidebar-open');
            $(this).remove();
        });
        
        // Close sidebar when clicking outside on mobile
        $(document).on('click', function(e) {
            if ($(window).width() < 992) {
                if ($('body').hasClass('sidebar-open') && 
                    !$(e.target).closest('.sidebar-fixed, [data-widget="pushmenu"]').length) {
                    $('body').removeClass('sidebar-open');
                    $('.overlay').remove();
                }
            }
        });
        
        // Auto-close sidebar on mobile when clicking a link
        $(window).resize(function() {
            if ($(window).width() >= 992) {
                $('body').removeClass('sidebar-open');
                $('.overlay').remove();
            } else {
                $('body').removeClass('sidebar-collapse');
            }
        });
    });
    </script>

</head>
<body class="hold-transition sidebar-mini layout-navbar-fixed <?php echo $body_class; ?>">

<?php if(!$is_login_page): ?>

    <div id="loading-overlay">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>

    <div class="wrapper">
        <nav class="main-header navbar navbar-expand navbar-light navbar-modern">
            <!-- Left navbar -->
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                        <i class="fas fa-bars"></i>
                    </a>
                </li>

                <li class="nav-item d-none d-sm-inline-block">
                    <a href="index.php" class="nav-link">
                        <i class="fas fa-home mr-1"></i> <span>Dashboard</span>
                    </a>
                </li>
            </ul>

            <!-- Right navbar -->
            <ul class="navbar-nav ml-auto">
                <!-- Notifications -->
                <li class="nav-item dropdown">
                    <a class="nav-link" data-toggle="dropdown" href="#">
                        <i class="far fa-bell"></i>
                        <span class="badge badge-warning navbar-badge">3</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right shadow">
                        <span class="dropdown-header">
                            <i class="fas fa-bell mr-1"></i> 3 Notifications
                        </span>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item">
                            <i class="fas fa-user-plus mr-2 text-primary"></i> New customer added
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item dropdown-footer">View all</a>
                    </div>
                </li>

                <!-- User Menu -->
                <li class="nav-item dropdown user-menu">
                    <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                        <img src="img/habib.gusti.png" class="user-image img-circle elevation-2" alt="User">
                        <span class="d-none d-md-inline">
                            <?php echo $_SESSION['username'] ?? 'User'; ?>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right shadow">
                        <li class="user-header bg-primary">
                            <img src="img/habib.gusti.png" class="img-circle elevation-2" alt="User">
                            <p>
                                <?php echo $_SESSION['username'] ?? 'User'; ?>
                                <small>Administrator</small>
                            </p>
                        </li>
                        <li class="user-footer">
                            <a href="profile.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-user-cog"></i> Profile
                            </a>
                            <a href="logout.php" class="btn btn-outline-danger btn-sm float-right">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>
        
        <?php if(is_logged_in()): ?>
        <!-- Fixed Sidebar -->
        <aside class="main-sidebar sidebar-light-primary elevation-4 sidebar-fixed">
            <a href="index.php" class="brand-link text-center">
                <span class="brand-text font-weight-bold">AFSHIN APP</span>
            </a>
            <div class="sidebar">
                <div class="user-panel mt-1 pb-3 mb-2 d-flex">
                    <div class="info">
                        <a href="#" class="d-block">
                            <i class="fas fa-user-circle mr-1"></i>
                            <?php echo htmlspecialchars(current_user()['full_name'] ?? current_user()['username']); ?>
                        </a>
                    </div>
                </div>
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview">
                        <li class="nav-item">
                            <a href="index.php" class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-tachometer-alt"></i>
                                <p>Dashboard</p>
                            </a>
                        </li>
                        
                        <li class="nav-item has-treeview">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-users"></i>
                                <p>Customer<i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="customers_create.php" class="nav-link <?php echo $current_page == 'customers_create.php' ? 'active' : ''; ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Create Customer</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="customers_list.php" class="nav-link <?php echo $current_page == 'customers_list.php' ? 'active' : ''; ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Customer List</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                        <li class="nav-item has-treeview">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-file-invoice"></i>
                                <p>Quotation<i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="quotations_create.php" class="nav-link <?php echo $current_page == 'quotations_create.php' ? 'active' : ''; ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Create Quotation</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="quotations_list.php" class="nav-link <?php echo $current_page == 'quotations_list.php' ? 'active' : ''; ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Quotation List</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                        <li class="nav-item has-treeview">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-file-invoice-dollar"></i>
                                <p>Invoice<i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="invoices_create.php" class="nav-link <?php echo $current_page == 'invoices_create.php' ? 'active' : ''; ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Create Invoice</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="invoices_list.php" class="nav-link <?php echo $current_page == 'invoices_list.php' ? 'active' : ''; ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Invoice List</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                        <li class="nav-item has-treeview">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-shipping-fast"></i> 
                                <p>Travel Document<i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="travel_document_create.php" class="nav-link <?php echo $current_page == 'travel_document_create.php' ? 'active' : ''; ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Create Travel Doc</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="travel_document_list.php" class="nav-link <?php echo $current_page == 'travel_document_list.php' ? 'active' : ''; ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Travel Doc List</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                        <li class="nav-item has-treeview">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-clipboard-list"></i> 
                                <p>Service Report<i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="service_report_create.php" class="nav-link <?php echo $current_page == 'service_report_create.php' ? 'active' : ''; ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Create Service Report</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="service_report_list.php" class="nav-link <?php echo $current_page == 'service_report_list.php' ? 'active' : ''; ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Service Report List</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                        <li class="nav-item has-treeview">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-clipboard-list"></i> 
                                <p>Berita Acara<i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="berita_acara_create.php" class="nav-link <?php echo $current_page == 'berita_acara_create.php' ? 'active' : ''; ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Create Berita Acara</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="berita_acara_list.php" class="nav-link <?php echo $current_page == 'berita_acara_list.php' ? 'active' : ''; ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Berita Acara List</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                        <li class="nav-item">
                            <a href="finance.php" class="nav-link <?php echo $current_page == 'finance.php' ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-file-alt"></i>
                                <p>Finance</p>
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a href="document_history.php" class="nav-link <?php echo $current_page == 'document_history.php' ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-search-alt"></i>
                                <p>Document History</p>
                            </a>
                        </li>

                        <?php if(current_user()['role'] === 'admin'): ?>
                        <li class="nav-item has-treeview">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-user-cog"></i>
                                <p>User<i class="right fas fa-angle-left"></i></p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="users_create.php" class="nav-link <?php echo $current_page == 'users_create.php' ? 'active' : ''; ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Create User</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="users_manage.php" class="nav-link <?php echo $current_page == 'users_manage.php' ? 'active' : ''; ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Manage User</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </aside>
        <?php endif; ?>

        <div class="content-wrapper">
            <section class="content p-3">
                <div class="container-fluid">

<?php endif; ?> 