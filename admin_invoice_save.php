<?php
require_once 'functions.php';
require_login();

$po_numbers    = $_POST['po_number'];
$customer_names = $_POST['customer_name'];
$invoice_nos   = $_POST['invoice_no'];
$qtys          = $_POST['qty'];
$prices        = $_POST['price'];

$admin_invoice_no = $_POST['admin_invoice_no'];
$created_at       = $_POST['created_at'];
$due_date         = $_POST['due_date'];

$edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;

/* ================= MODE EDIT (UPDATE) ================= */
if ($edit_id > 0) {

    // 1. Update header invoice
    mysqli_query($mysqli, "
        UPDATE admin_invoices SET
            admin_invoice_no = '$admin_invoice_no',
            created_at       = '$created_at',
            due_date         = '$due_date'
        WHERE id = $edit_id
    ");

    // 2. Hapus semua items lama
    mysqli_query($mysqli, "
        DELETE FROM admin_invoice_items
        WHERE admin_invoice_id = $edit_id
    ");

    // 3. Insert ulang items baru
    foreach ($po_numbers as $i => $po) {
        $customer = mysqli_real_escape_string($mysqli, $customer_names[$i]);
        $inv_no   = mysqli_real_escape_string($mysqli, $invoice_nos[$i]);
        $qty      = intval($qtys[$i]);
        $price    = intval($prices[$i]);
        $total    = $qty * $price;
        $po       = mysqli_real_escape_string($mysqli, $po);

        mysqli_query($mysqli, "
            INSERT INTO admin_invoice_items
                (admin_invoice_id, po_number, customer_name, invoice_no, qty, price, total)
            VALUES
                ($edit_id, '$po', '$customer', '$inv_no', $qty, $price, $total)
        ");
    }

    flash_set('success', 'Admin Invoice berhasil diupdate');
    header('Location: admin_invoice_list.php');
    exit;
}

/* ================= MODE CREATE (INSERT BARU) ================= */

// 1. Insert header
mysqli_query($mysqli, "
    INSERT INTO admin_invoices (admin_invoice_no, created_at, due_date)
    VALUES ('$admin_invoice_no', '$created_at', '$due_date')
");

$new_id = mysqli_insert_id($mysqli);

// 2. Insert items
foreach ($po_numbers as $i => $po) {
    $customer = mysqli_real_escape_string($mysqli, $customer_names[$i]);
    $inv_no   = mysqli_real_escape_string($mysqli, $invoice_nos[$i]);
    $qty      = intval($qtys[$i]);
    $price    = intval($prices[$i]);
    $total    = $qty * $price;
    $po       = mysqli_real_escape_string($mysqli, $po);

    mysqli_query($mysqli, "
        INSERT INTO admin_invoice_items
            (admin_invoice_id, po_number, customer_name, invoice_no, qty, price, total)
        VALUES
            ($new_id, '$po', '$customer', '$inv_no', $qty, $price, $total)
    ");
}

flash_set('success', 'Admin Invoice berhasil disimpan');
header('Location: admin_invoice_list.php');
exit;