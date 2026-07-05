<?php
require_once 'db.php';
header('Content-Type: application/json');

$invoice_id = intval($_GET['invoice_id'] ?? 0);
if ($invoice_id <= 0) {
    echo json_encode(['success'=>false, 'message'=>'Invoice ID tidak valid']);
    exit;
}

// Ambil data invoice utama
$invoice_query = mysqli_query($mysqli, "
    SELECT 
        i.*,
        c.name as customer_name,
        DATE_FORMAT(i.created_at, '%d/%m/%Y') as invoice_date
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    WHERE i.id = $invoice_id
");

if(mysqli_num_rows($invoice_query) == 0) {
    echo json_encode(['success'=>false, 'message'=>'Invoice tidak ditemukan']);
    exit;
}

$invoice_data = mysqli_fetch_assoc($invoice_query);

// Format angka untuk invoice
$invoice = [
    'customer_name' => $invoice_data['customer_name'] ?? '',
    'invoice_date' => $invoice_data['invoice_date'] ?? '',
    'po_number' => $invoice_data['po_number'] ?? '',
    'subtotal_formatted' => 'Rp ' . number_format(floatval($invoice_data['subtotal'] ?? 0), 2, ',', '.'),
    'ppn_formatted' => 'Rp ' . number_format(floatval($invoice_data['ppn'] ?? 0), 2, ',', '.'),
    'pph_formatted' => 'Rp ' . number_format(floatval($invoice_data['pph'] ?? 0), 2, ',', '.'),
    'total_formatted' => 'Rp ' . number_format(floatval($invoice_data['total'] ?? 0), 2, ',', '.')
];

// Ambil semua items invoice
$items_query = mysqli_query($mysqli, "
    SELECT 
        description, 
        qty, 
        satuan,
        unit_price,
        amount
    FROM invoice_items
    WHERE invoice_id = $invoice_id
    ORDER BY item_no ASC
");

$items = [];
while($row = mysqli_fetch_assoc($items_query)){
    $items[] = [
        'description' => htmlspecialchars($row['description'] ?? ''),
        'qty_formatted' => number_format(floatval($row['qty'] ?? 0), 2),
        'qty' => number_format(floatval($row['qty'] ?? 0), 2),
        'satuan' => htmlspecialchars($row['satuan'] ?? ''),
        'unit_price_formatted' => 'Rp ' . number_format(floatval($row['unit_price'] ?? 0), 2, ',', '.'),
        'amount_formatted' => 'Rp ' . number_format(floatval($row['amount'] ?? 0), 2, ',', '.'),
        'amount' => 'Rp ' . number_format(floatval($row['amount'] ?? 0), 2, ',', '.')
    ];
}

// Hitung total items
$total_items = count($items);

echo json_encode([
    'success' => true,
    'invoice' => $invoice,
    'items' => $items,
    'total_items' => $total_items
]);

mysqli_close($mysqli);