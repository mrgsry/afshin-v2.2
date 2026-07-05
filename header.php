<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'functions.php';

$current_page = basename($_SERVER['PHP_SELF']);
// Auto-detect base path - works from any subdirectory
$base_prefix = (strpos($_SERVER['SCRIPT_NAME'], '/cashflow/') !== false) ? '../../' : '';
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Afshin APP | Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">


    <style>
    html,
    body {
        overflow-x: hidden !important;
    }

    .content-wrapper {
        overflow-x: hidden !important;
    }

    .table-responsive {
        overflow-x: auto;
    }

    .dataTables_wrapper {
        width: 100%;
    }

    table.dataTable {
        width: 100% !important;
    }
    </style>
    <style>
    /* Background */
    body {
        background: linear-gradient(135deg, #f6f9fc, #eef2f7);
    }

    /* Loader */
    #page-loader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        transition: opacity .4s ease, visibility .4s ease;
    }

    #page-loader.hide {
        opacity: 0;
        visibility: hidden;
    }

    /* Navbar */
    .main-header {
        background: #ffffff;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        border: none;
    }

    /* Sidebar */
    .main-sidebar {
        background: #ffffff;
        border-right: 1px solid #eee;
    }

    /* Sidebar hover */
    .nav-sidebar .nav-link {
        border-radius: 8px;
        margin: 3px 6px;
        transition: .2s;
    }

    .nav-sidebar .nav-link:hover {
        background: #667eea;
        color: #fff !important;
    }

    .nav-sidebar .nav-link.active {
        background: #667eea;
        color: #fff !important;
    }

    /* Card */
    .card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        transition: .3s;
    }

    .card:hover {
        transform: translateY(-3px);
    }

    /* Modal fix */
    .modal {
        z-index: 1080 !important;
    }

    .modal-backdrop {
        z-index: 1070 !important;
    }
    </style>

    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">
</head>

<body class="hold-transition sidebar-mini layout-fixed">

    <!-- Loader -->
    <div id="page-loader">
        <div class="spinner-border text-primary" style="width:3rem;height:3rem;"></div>
    </div>

    <div class="wrapper">

        <!-- Navbar -->
        <nav class="main-header navbar navbar-expand navbar-light">

            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#">
                        <i class="fas fa-bars"></i>
                    </a>
                </li>
                <li class="nav-item d-none d-sm-inline-block">
                    <a href="index.php" class="nav-link">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav ml-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link" data-toggle="dropdown" href="#">
                        <i class="far fa-user-circle fa-lg"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a href="<?= $base_prefix ?>profile.php" class="dropdown-item">
                            <i class="fas fa-user-cog mr-2"></i> User Setting
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="<?= $base_prefix ?>logout.php" class="dropdown-item text-danger">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </a>
                    </div>
                </li>
            </ul>

        </nav>

        <!-- Sidebar -->
        <aside class="main-sidebar elevation-4" style="background:#f8f9fb;">

            <a href="<?= $base_prefix ?>index.php" class="brand-link text-center border-0">
                <img src="<?= $base_prefix ?>img/afshin2.png" class="brand-image img-circle elevation-2"
                    style="width:35px;">
                <span class="brand-text font-weight-bold ml-2">AFSHIN APP</span>
            </a>

            <div class="sidebar">

                <nav class="mt-3">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu"
                        data-accordion="false">

                        <!-- DASHBOARD -->
                        <li class="nav-item">
                            <a href="<?= $base_prefix ?>index.php"
                                class="nav-link <?= $current_page=='index.php'?'active bg-gradient-primary text-white':'' ?>">
                                <i class="nav-icon fas fa-tachometer-alt"></i>
                                <p>Dashboard</p>
                            </a>
                        </li>

                        <!-- CUSTOMER -->
                        <li
                            class="nav-item has-treeview <?= in_array($current_page,['customers_create.php','customers_list.php'])?'menu-open':'' ?>">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-users"></i>
                                <p>
                                    Customer
                                    <i class="right fas fa-angle-right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="<?= $base_prefix ?>customers_create.php"
                                        class="nav-link <?= $current_page=='customers_create.php'?'active':'' ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Create Customer</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?= $base_prefix ?>customers_list.php"
                                        class="nav-link <?= $current_page=='customers_list.php'?'active':'' ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Customer List</p>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <!-- QUOTATION -->
                        <li
                            class="nav-item has-treeview <?= in_array($current_page,['quotations_create.php','quotations_list.php'])?'menu-open':'' ?>">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-file-contract"></i>
                                <p>
                                    Quotation
                                    <i class="right fas fa-angle-right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="<?= $base_prefix ?>quotations_create.php"
                                        class="nav-link <?= $current_page=='quotations_create.php'?'active':'' ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Create Quotation</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?= $base_prefix ?>quotations_list.php"
                                        class="nav-link <?= $current_page=='quotations_list.php'?'active':'' ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Quotation List</p>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <!-- INVOICE -->
                        <li
                            class="nav-item has-treeview <?= in_array($current_page,['invoices_create.php','invoices_list.php'])?'menu-open':'' ?>">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-file-invoice-dollar"></i>
                                <p>
                                    Invoice
                                    <i class="right fas fa-angle-right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="<?= $base_prefix ?>invoices_create.php"
                                        class="nav-link <?= $current_page=='invoices_create.php'?'active':'' ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Create Invoice</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?= $base_prefix ?>admin_invoice_list.php"
                                        class="nav-link <?= $current_page=='admin_invoice_list.php'?'active':'' ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Create Admin Invoice</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?= $base_prefix ?>invoices_list.php"
                                        class="nav-link <?= $current_page=='invoices_list.php'?'active':'' ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Invoice List</p>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <!-- TRAVEL DOCUMENT -->
                        <li
                            class="nav-item has-treeview <?= in_array($current_page,['travel_document_create.php','travel_document_list.php'])?'menu-open':'' ?>">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-truck"></i>
                                <p>
                                    Travel Document
                                    <i class="right fas fa-angle-right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="<?= $base_prefix ?>travel_document_create.php"
                                        class="nav-link <?= $current_page=='travel_document_create.php'?'active':'' ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Create Travel Doc</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?= $base_prefix ?>travel_document_list.php"
                                        class="nav-link <?= $current_page=='travel_document_list.php'?'active':'' ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Travel Doc List</p>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <!-- SERVICE REPORT -->
                        <li
                            class="nav-item has-treeview <?= in_array($current_page,['service_report_create.php','service_report_list.php'])?'menu-open':'' ?>">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-tools"></i>
                                <p>
                                    Service Report
                                    <i class="right fas fa-angle-right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="<?= $base_prefix ?>service_report_create.php"
                                        class="nav-link <?= $current_page=='service_report_create.php'?'active':'' ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Create Service Report</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?= $base_prefix ?>service_report_list.php"
                                        class="nav-link <?= $current_page=='service_report_list.php'?'active':'' ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Service Report List</p>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <!-- BERITA ACARA -->
                        <li
                            class="nav-item has-treeview <?= in_array($current_page,['berita_acara_create.php','berita_acara_list.php'])?'menu-open':'' ?>">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-clipboard-list"></i>
                                <p>
                                    Berita Acara
                                    <i class="right fas fa-angle-right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="<?= $base_prefix ?>berita_acara_create.php"
                                        class="nav-link <?= $current_page=='berita_acara_create.php'?'active':'' ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Create Berita Acara</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?= $base_prefix ?>berita_acara_list.php"
                                        class="nav-link <?= $current_page=='berita_acara_list.php'?'active':'' ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Berita Acara List</p>
                                    </a>
                                </li>
                            </ul>
                        </li>

                        <li class="nav-item">
                            <a href="<?= $base_prefix ?>data_po.php"
                                class="nav-link <?= $current_page=='data_po.php'?'active':'' ?>">
                                <i class="nav-icon fas fa-file-alt"></i>
                                <p>Data PO</p>
                            </a>
                        </li>

                        <!-- OPERATIONAL -->
                        <li class="nav-item">
                            <a href="<?= $base_prefix ?>cashflow/admin/index.php"
                                class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'cashflow') !== false ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-cash-register"></i>
                                <p>Operational</p>
                            </a>
                        </li>

                        <!-- FINANCE -->
                        <li class="nav-item">
                            <a href="<?= $base_prefix ?>finance.php"
                                class="nav-link <?= $current_page=='finance.php'?'active':'' ?>">
                                <i class="nav-icon fas fa-chart-line"></i>
                                <p>Finance</p>
                            </a>
                        </li>

                        <!-- DOCUMENT HISTORY -->
                        <li class="nav-item">
                            <a href="<?= $base_prefix ?>document_history.php"
                                class="nav-link <?= $current_page=='document_history.php'?'active':'' ?>">
                                <i class="nav-icon fas fa-history"></i>
                                <p>Document History</p>
                            </a>
                        </li>

                        <!-- USER (ADMIN ONLY) -->
                        <?php if(current_user()['role']==='admin'): ?>
                        <li
                            class="nav-item has-treeview <?= in_array($current_page,['users_create.php','users_manage.php'])?'menu-open':'' ?>">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-user-cog"></i>
                                <p>
                                    User
                                    <i class="right fas fa-angle-right"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item">
                                    <a href="<?= $base_prefix ?>users_create.php"
                                        class="nav-link <?= $current_page=='users_create.php'?'active':'' ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Create User</p>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a href="<?= $base_prefix ?>users_manage.php"
                                        class="nav-link <?= $current_page=='users_manage.php'?'active':'' ?>">
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

        <div class="content-wrapper p-3">
            <div class="container-fluid">

                <script>
                window.addEventListener("load", function() {
                    document.getElementById("page-loader").classList.add("hide");
                });
                </script>
                <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
                <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
                <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>