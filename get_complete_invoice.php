<?php
require_once 'functions.php';
require_once 'db.php';
require_login();

$query = "
SELECT i.invoice_no,
       i.created_at,
       i.total,
       c.name as customer_name
FROM invoices i
LEFT JOIN customers c ON i.customer_id = c.id
WHERE i.faktur_inv IS NOT NULL
AND i.faktur_inv != ''
AND i.faktur_inv != 0
ORDER BY i.created_at DESC
";

$result = mysqli_query($mysqli,$query);

$data = [];

while($row=mysqli_fetch_assoc($result)){
    $data[] = [
        'invoice_no'=>$row['invoice_no'],
        'date'=>date('d/m/Y',strtotime($row['created_at'])),
        'customer'=>$row['customer_name'],
        'total'=>number_format($row['total'],0,',','.'),
        'status'=>"Faktur Sudah Dibuat"
    ];
}

echo json_encode($data);