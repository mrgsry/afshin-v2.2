<?php
require_once 'db.php';

$query = mysqli_query($mysqli,"
SELECT 
i.id,
i.invoice_no,
i.created_at,
i.total,
c.name AS customer_name
FROM invoices i
LEFT JOIN customers c ON i.customer_id = c.id
WHERE i.faktur_inv = 0 
OR i.faktur_inv IS NULL 
OR i.faktur_inv = ''
ORDER BY i.created_at DESC
");

$data = [];

while($row = mysqli_fetch_assoc($query)){
    $data[] = [
        'invoice_no' => $row['invoice_no'],
        'date' => date('d M Y', strtotime($row['created_at'])),
        'customer' => $row['customer_name'],
        'total' => number_format($row['total'],0,',','.'),
        'reason' => 'Nomor Faktur Belum dibuat'
    ];
}

echo json_encode($data);