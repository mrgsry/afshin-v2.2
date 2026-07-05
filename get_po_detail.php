<?php
require_once 'db.php';

$id = intval($_GET['id']);

$header = mysqli_fetch_assoc(mysqli_query($mysqli,"
SELECT subtotal,ppn,pph,total
FROM invoices
WHERE id=$id
"));

$items = mysqli_query($mysqli,"
SELECT description,qty,satuan,unit_price,amount
FROM invoice_items
WHERE invoice_id=$id
ORDER BY item_no ASC
");

echo "<table class='table table-sm table-bordered'>";
echo "<thead>
<tr>
<th>No</th>
<th>Description</th>
<th>Qty</th>
<th>Unit</th>
<th>Unit Price</th>
<th>Amount</th>
</tr>
</thead><tbody>";

$no=1;
while($r=mysqli_fetch_assoc($items)){
echo "<tr>
<td>".$no++."</td>
<td>".$r['description']."</td>
<td>".$r['qty']."</td>
<td>".$r['satuan']."</td>
<td class='text-right'>Rp ".number_format($r['unit_price'],0,',','.')."</td>
<td class='text-right'>Rp ".number_format($r['amount'],0,',','.')."</td>
</tr>";
}

echo "</tbody></table>";

echo "<div class='text-right mt-3'>";
echo "<p><b>Subtotal:</b> Rp ".number_format($header['subtotal'],0,',','.')."</p>";
echo "<p><b>PPN:</b> Rp ".number_format($header['ppn'],0,',','.')."</p>";
echo "<p><b>PPH:</b> Rp ".number_format($header['pph'],0,',','.')."</p>";
echo "<h5><b>Total:</b> Rp ".number_format($header['total'],0,',','.')."</h5>";
echo "</div>";