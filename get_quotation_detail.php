<?php
require_once 'functions.php';
require_once 'db.php';
require_login();

$id = intval($_GET['id']);

$query = "
SELECT q.*, c.name as customer_name, c.email, c.cc_email
FROM quotations q
LEFT JOIN customers c ON q.customer_id=c.id
WHERE q.id=$id
";

$res = mysqli_query($mysqli,$query);
$quotation = mysqli_fetch_assoc($res);

$items_query = "
SELECT * FROM quotation_items
WHERE quotation_id=$id
ORDER BY item_no
";

$items_res = mysqli_query($mysqli,$items_query);

$items = [];
while($row=mysqli_fetch_assoc($items_res)){
    $items[] = [
        'description'=>$row['description_quot'],
        'qty'=>number_format($row['qty'],2),
        'unit'=>$row['satuan'] ?? '-',
        'unit_price'=>number_format($row['unit_price'],0,',','.'),
        'amount'=>number_format($row['amount'],0,',','.')
    ];
}

echo json_encode([
    'quotation_no'=>$quotation['quotation_no'],
    'customer'=>$quotation['customer_name'],
    'email'=>$quotation['email'] ?? '',
    'cc_email'=>$quotation['cc_email'] ?? '',
    'date'=>date('d/m/Y',strtotime($quotation['date_quot'])),
    'subtotal'=>number_format($quotation['subtotal'],0,',','.'),
    'ppn'=>number_format($quotation['ppn'],0,',','.'),
    'total'=>number_format($quotation['total'],0,',','.'),
    'items'=>$items
]);