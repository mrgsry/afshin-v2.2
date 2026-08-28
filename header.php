<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'functions.php';

$current_page = basename($_SERVER['PHP_SELF']);
// Auto-detect base path - works from any subdirectory
$base_prefix = (strpos($_SERVER['SCRIPT_NAME'], '/cashflow/') !== false) ? '../../' : '';
$active_user = current_user() ?: [];
$active_user_name = trim($active_user['full_name'] ?? '') ?: ($active_user['username'] ?? 'User');
$active_user_role = ucfirst($active_user['role'] ?? 'guest');
$active_user_position = trim($active_user['job_position'] ?? '') ?: 'Belum diatur';
$active_user_photo = trim($active_user['photo_path'] ?? '');
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

    .wrapper {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    .content-wrapper {
        overflow-x: hidden !important;
    }

    .table-responsive {
        overflow-x: auto;
    }

    .login-success-card {
        position: fixed;
        top: 76px;
        right: 24px;
        z-index: 1060;
        display: flex;
        align-items: center;
        gap: 12px;
        width: min(360px, calc(100vw - 32px));
        padding: 15px 16px;
        border-left: 4px solid #28a745;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 12px 30px rgba(31, 45, 61, .2);
        animation: loginSuccessIn .35s ease both;
    }

    .login-success-icon {
        display: grid;
        flex: 0 0 34px;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 50%;
        background: #e9f7ef;
        color: #28a745;
    }

    .login-success-card strong,
    .login-success-card span { display: block; }
    .login-success-card strong { color: #1f2d3d; font-size: .9rem; }
    .login-success-card span { margin-top: 2px; color: #6c757d; font-size: .78rem; }
    .login-success-close { margin-left: auto; padding: 0 4px; border: 0; background: transparent; color: #98a1ab; font-size: 1.3rem; line-height: 1; cursor: pointer; }
    .login-success-card.is-hidden { animation: loginSuccessOut .25s ease forwards; }
    @keyframes loginSuccessIn { from { opacity: 0; transform: translateY(-12px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes loginSuccessOut { to { opacity: 0; transform: translateY(-12px); } }

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
        position: fixed;
        top: 0;
        left: 280px;
        right: 0;
        width: auto;
        margin-left: 0 !important;
        height: 57px;
        z-index: 1035;
        background: #ffffff;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        border: none;
    }

    .main-header .navbar-nav {
        min-width: 0;
    }

    .main-header .navbar-nav.ml-auto {
        flex-shrink: 1;
    }

    .header-datetime {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-right: 8px;
        color: #6c757d;
        font-size: .82rem;
        line-height: 1.2;
        white-space: nowrap;
    }

    .header-datetime span { display: inline-flex; align-items: center; }
    .header-datetime i { color: #667eea; }
    .header-clock { color: #1f2d3d; font-weight: 700; font-variant-numeric: tabular-nums; }

    .main-sidebar {
        top: 0 !important;
        width: 280px !important;
        height: 100vh !important;
    }

    .content-wrapper {
        flex: 1 0 auto;
        min-height: 0 !important;
        margin-top: 57px;
        margin-left: 280px !important;
    }

    .main-footer {
        flex: 0 0 auto;
        margin-left: 280px !important;
        margin-top: 0;
    }

    /* Sidebar */
    .main-sidebar {
        background: #ffffff;
        border-right: 1px solid #eee;
    }

    .main-sidebar .sidebar {
        display: flex;
        flex-direction: column;
        height: calc(100vh - 57px);
    }

    .main-sidebar .sidebar > nav {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
    }

    .sidebar-logout {
        flex: 0 0 auto;
        margin: 8px 6px 12px;
        border-top: 1px solid #e9ecef;
        padding-top: 8px;
    }

    .sidebar-logout .nav-link {
        color: #dc3545;
    }

    .sidebar-logout .nav-link:hover {
        background: #dc3545;
        color: #fff !important;
    }

    body.sidebar-collapse .main-header {
        left: 4.6rem !important;
        width: calc(100% - 4.6rem);
        margin-left: 0 !important;
    }

    body.sidebar-collapse .main-sidebar {
        left: 0 !important;
        margin-left: 0 !important;
        width: 4.6rem !important;
    }

    body.sidebar-collapse .content-wrapper,
    body.sidebar-collapse .main-footer {
        margin-left: 4.6rem !important;
    }

    body.sidebar-collapse .brand-link {
        width: 4.6rem !important;
        padding-left: 0;
        padding-right: 0;
        text-align: center;
    }

    body.sidebar-collapse .brand-link .brand-image {
        float: none;
        margin: 0;
    }

    body.sidebar-collapse .brand-link .brand-text,
    body.sidebar-collapse .sidebar-user-name,
    body.sidebar-collapse .sidebar-user-meta,
    body.sidebar-collapse .sidebar-user-role,
    body.sidebar-collapse .nav-sidebar .nav-link p,
    body.sidebar-collapse .sidebar-logout p {
        display: none !important;
    }

    body.sidebar-collapse .sidebar-user-card {
        margin: 10px 6px 14px;
        padding: 8px 4px;
    }

    body.sidebar-collapse .sidebar-user-photo {
        width: 48px;
        height: 48px;
    }

    body.sidebar-collapse .nav-sidebar .nav-link,
    body.sidebar-collapse .sidebar-logout .nav-link {
        width: 3.4rem;
        margin-left: auto;
        margin-right: auto;
        padding-left: 0;
        padding-right: 0;
        text-align: center;
    }

    body.sidebar-collapse .nav-sidebar .nav-icon,
    body.sidebar-collapse .sidebar-logout .nav-icon {
        margin-left: 0;
        margin-right: 0;
    }

    body.sidebar-collapse .nav-sidebar .right {
        display: none;
    }

    .sidebar-user-card { margin: 10px 8px 14px; padding: 16px 12px; border-radius: 10px; background: #f1f5ff; border: 1px solid #dfe7ff; text-align: center; }
    .sidebar-user-photo, .user-profile-preview, .user-table-photo { object-fit: cover; border-radius: 50%; }
    .sidebar-user-photo { width: 68px; height: 68px; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(31,45,61,.15); }
    .sidebar-user-name { display: block; margin-top: 10px; color: #1f2d3d; font-size: .95rem; font-weight: 700; overflow-wrap: anywhere; }
    .sidebar-user-meta { display: block; color: #6c757d; font-size: .76rem; line-height: 1.45; overflow-wrap: anywhere; }
    .sidebar-user-role { display: inline-block; margin-top: 8px; padding: 3px 9px; border-radius: 20px; background: #667eea; color: #fff; font-size: .7rem; font-weight: 600; }
    .user-profile-preview { width: 96px; height: 96px; border: 2px solid #dee2e6; }
    .user-table-photo { width: 42px; height: 42px; }

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

    @media (max-width: 767.98px) {
        .main-header,
        body.sidebar-collapse .main-header {
            left: 0 !important;
            width: 100% !important;
        }

        .main-sidebar,
        body.sidebar-collapse .main-sidebar {
            width: 250px !important;
            margin-left: -250px !important;
        }

        body.sidebar-open .main-sidebar,
        body.sidebar-open.sidebar-collapse .main-sidebar {
            margin-left: 0 !important;
        }

        .content-wrapper,
        body.sidebar-collapse .content-wrapper {
            margin-left: 0 !important;
            padding: 12px !important;
        }

        .main-footer,
        body.sidebar-collapse .main-footer {
            margin-left: 0 !important;
        }
        .sidebar-user-card { margin-left: 6px; margin-right: 6px; }
        .header-datetime { gap: 8px; margin-right: 2px; font-size: .7rem; }
        .header-date-label { display: none !important; }
        .main-header .navbar-nav.ml-auto .nav-link { padding-left: 8px; padding-right: 8px; }
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
                <li class="nav-item header-datetime" aria-label="Tanggal dan waktu saat ini">
                    <span class="header-date-label"><i class="far fa-calendar-alt mr-1"></i><span id="header-date"></span></span>
                    <span class="header-clock"><i class="far fa-clock mr-1"></i><span id="header-clock"></span></span>
                </li>
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

        <script>
            (function () {
                var dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                var monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                function updateHeaderDateTime() {
                    var now = new Date();
                    var dateElement = document.getElementById('header-date');
                    var clockElement = document.getElementById('header-clock');
                    if (dateElement) dateElement.textContent = dayNames[now.getDay()] + ', ' + now.getDate() + ' ' + monthNames[now.getMonth()] + ' ' + now.getFullYear();
                    if (clockElement) clockElement.textContent = [now.getHours(), now.getMinutes(), now.getSeconds()].map(function (value) { return String(value).padStart(2, '0'); }).join(':');
                }
                updateHeaderDateTime();
                window.setInterval(updateHeaderDateTime, 1000);
            }());
        </script>

        <!-- Sidebar -->
        <aside class="main-sidebar elevation-4" style="background:#f8f9fb;">

            <a href="<?= $base_prefix ?>index.php" class="brand-link text-center border-0">
                <img src="<?= $base_prefix ?>img/afshin2.png" class="brand-image img-circle elevation-2"
                    style="width:35px;">
                <span class="brand-text font-weight-bold ml-2">AFSHIN APP</span>
            </a>

            <div class="sidebar">

                <div class="sidebar-user-card">
                    <?php if ($active_user_photo): ?>
                        <img src="<?= htmlspecialchars($base_prefix . $active_user_photo) ?>" alt="Foto <?= htmlspecialchars($active_user_name) ?>" class="sidebar-user-photo">
                    <?php else: ?>
                        <i class="fas fa-user-circle fa-4x text-secondary"></i>
                    <?php endif; ?>
                    <span class="sidebar-user-name">Selamat Datang, <?= htmlspecialchars($active_user_name) ?></span>
                    <span class="sidebar-user-meta"><i class="fas fa-briefcase mr-1"></i><?= htmlspecialchars($active_user_position) ?></span>
                    <span class="sidebar-user-role"><i class="fas fa-shield-alt mr-1"></i><?= htmlspecialchars($active_user_role) ?></span>
                </div>

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

                        <?php if (can_access_module('customer')): ?>
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

                        <?php endif; ?>
                        <?php if (can_access_module('employee')): ?>
                        <!-- EMPLOYEE -->
                        <li class="nav-item has-treeview <?= in_array($current_page,['employees_create.php','employees_edit.php','employees_list.php'])?'menu-open':'' ?>">
                            <a href="#" class="nav-link"><i class="nav-icon fas fa-user-tie"></i><p>Karyawan<i class="right fas fa-angle-right"></i></p></a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="<?= $base_prefix ?>employees_create.php" class="nav-link <?= $current_page=='employees_create.php'?'active':'' ?>"><i class="far fa-circle nav-icon"></i><p>Tambah Karyawan</p></a></li>
                                <li class="nav-item"><a href="<?= $base_prefix ?>employees_list.php" class="nav-link <?= $current_page=='employees_list.php'?'active':'' ?>"><i class="far fa-circle nav-icon"></i><p>Daftar Karyawan</p></a></li>
                            </ul>
                        </li>

                        <?php endif; ?>
                        <?php if (can_access_module('payslip')): ?>
                        <!-- PAYSLIP -->
                        <li class="nav-item has-treeview <?= in_array($current_page,['payslips_create.php','payslips_edit.php','payslips_list.php'])?'menu-open':'' ?>">
                            <a href="#" class="nav-link"><i class="nav-icon fas fa-money-check-alt"></i><p>Slip Gaji<i class="right fas fa-angle-right"></i></p></a>
                            <ul class="nav nav-treeview">
                                <li class="nav-item"><a href="<?= $base_prefix ?>payslips_create.php" class="nav-link <?= $current_page=='payslips_create.php'?'active':'' ?>"><i class="far fa-circle nav-icon"></i><p>Buat Slip Gaji</p></a></li>
                                <li class="nav-item"><a href="<?= $base_prefix ?>payslips_list.php" class="nav-link <?= $current_page=='payslips_list.php'?'active':'' ?>"><i class="far fa-circle nav-icon"></i><p>Daftar Slip Gaji</p></a></li>
                            </ul>
                        </li>

                        <?php endif; ?>
                        <?php if (can_access_module('quotation')): ?>
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

                        <?php endif; ?>
                        <?php if (can_access_module('chat')): ?>
                        <?php endif; ?>
                        <?php if (can_access_module('invoice')): ?>
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

                        <?php endif; ?>
                        <?php if (can_access_module('travel_document')): ?>
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

                        <?php endif; ?>
                        <?php if (can_access_module('service_report')): ?>
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

                        <?php endif; ?>
                        <?php if (can_access_module('berita_acara')): ?>
                        <!-- BERITA ACARA -->
                        <li
                            class="nav-item has-treeview <?= in_array($current_page,['berita_acara_create.php','berita_acara_list.php'])?'menu-open':'' ?>">
                            <a href="#" class="nav-link">
                                <i class="nav-icon fas fa-clipboard-list"></i>
                        <?php endif; ?>
                        <?php if (can_access_module('data_po')): ?>
                        <!-- DATA PO -->
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

                        <?php endif; ?>
                        <?php if (can_access_module('operational')): ?>
                        <!-- OPERATIONAL -->
                        <li class="nav-item">
                            <a href="<?= $base_prefix ?>cashflow/admin/index.php"
                                class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'cashflow') !== false ? 'active' : '' ?>">
                                <i class="nav-icon fas fa-cash-register"></i>
                                <p>Operational</p>
                            </a>
                        </li>

                        <?php endif; ?>
                        <?php if (can_access_module('finance')): ?>
                        <!-- FINANCE -->
                        <li class="nav-item">
                            <a href="<?= $base_prefix ?>finance.php"
                                class="nav-link <?= $current_page=='finance.php'?'active':'' ?>">
                                <i class="nav-icon fas fa-chart-line"></i>
                                <p>Finance</p>
                            </a>
                        </li>

                        <?php endif; ?>
                        <?php if (can_access_module('document_history')): ?>
                        <!-- DOCUMENT HISTORY -->
                        <li class="nav-item">
                            <a href="<?= $base_prefix ?>document_history.php"
                                class="nav-link <?= $current_page=='document_history.php'?'active':'' ?>">
                                <i class="nav-icon fas fa-history"></i>
                                <p>Document History</p>
                            </a>
                        </li>

                        <?php endif; ?>
                        <!-- USER MANAGEMENT -->
                        <?php if(can_access_module('user_management', 'full')): ?>
                        <li
                            class="nav-item has-treeview <?= in_array($current_page,['users_create.php','users_edit.php','users_manage.php'])?'menu-open':'' ?>">
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
                                <li class="nav-item">
                                    <a href="<?= $base_prefix ?>users_edit.php" class="nav-link <?= $current_page=='users_edit.php'?'active':'' ?>">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Edit User</p>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <?php endif; ?>

                    </ul>
                </nav>

                <div class="sidebar-logout">
                    <a href="<?= $base_prefix ?>logout.php" class="nav-link">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <p>Logout</p>
                    </a>
                </div>

            </div>
        </aside>

        <div class="content-wrapper p-3">
            <div class="container-fluid">

                <?php if (!empty($_SESSION['login_success'])): ?>
                <div class="login-success-card" role="status">
                    <div class="login-success-icon"><i class="fas fa-check"></i></div>
                    <div><strong>Login berhasil</strong><span>Selamat datang, <?= htmlspecialchars($_SESSION['login_success']) ?></span></div>
                    <button type="button" class="login-success-close" aria-label="Tutup notifikasi">&times;</button>
                </div>
                <?php unset($_SESSION['login_success']); endif; ?>

                <script>
                (function() {
                    var card = document.querySelector('.login-success-card');
                    if (!card) return;
                    var hide = function() { card.classList.add('is-hidden'); window.setTimeout(function() { card.remove(); }, 250); };
                    card.querySelector('.login-success-close').addEventListener('click', hide);
                    window.setTimeout(hide, 5000);
                }());
                </script>

                <script>
                window.addEventListener("load", function() {
                    document.getElementById("page-loader").classList.add("hide");
                });
                </script>
                <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
                <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
                <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
