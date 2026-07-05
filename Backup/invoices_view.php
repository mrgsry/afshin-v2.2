<?php
require_once 'functions.php';
require_login();
$id = intval($_GET['id'] ?? 0);
$res = mysqli_query($mysqli, "SELECT i.*, c.name as customer_name, c.customer_no, c.address FROM invoices i LEFT JOIN customers c ON i.customer_id=c.id WHERE i.id=$id");
if(!$inv = mysqli_fetch_assoc($res)){ flash_set('Not found'); header('Location: invoices_list.php'); exit; }
$items = mysqli_query($mysqli, "SELECT * FROM invoice_items WHERE invoice_id=$id ORDER BY item_no ASC");
include 'header.php';
?>
<h4>Invoice <?php echo htmlspecialchars($inv['invoice_no']); ?></h4>
<p>Customer: <?php echo htmlspecialchars($inv['customer_name'].' ('.$inv['customer_no'].')'); ?></p>
<p>PO Number: <?php echo htmlspecialchars($inv['po_number']); ?></p>
<table class="table table-bordered">
<tr><th>No</th><th>Description</th><th>Qty</th><th>Satuan</th><th>Unit Price</th><th>Amount</th></tr>
<?php while($it = mysqli_fetch_assoc($items)): ?>
<tr>
  <td><?php echo $it['item_no']; ?></td>
  <td><?php echo htmlspecialchars($it['description']); ?></td>
  <td><?php echo $it['qty']; ?></td>
  <td><?php echo htmlspecialchars($it['satuan']); ?></td>
  <td><?php echo number_format($it['unit_price'],2); ?></td>
  <td><?php echo number_format($it['amount'],2); ?></td>
</tr>
<?php endwhile; ?>
</table>
<!-- <style>
  .button2:hover {box-shadow:0 8px 16px 0 rgba(0,0,0,0.6)}
</style> -->
<p>Subtotal: <?php echo number_format($inv['subtotal'],2); ?></p>
<p>Discount: <?php echo number_format($inv['discount'],2); ?></p>
<p>PPH: <?php echo number_format($inv['pph'],2); ?></p>
<p>PPN: <?php echo number_format($inv['ppn'],2); ?></p>
<p>Total: <?php echo number_format($inv['total'],2); ?></p>

<a class="btn btn-lg btn-success" href="invoice_print.php?id=<?php echo $inv['id']; ?>">Print</a>
<a class="btn btn-lg btn-danger" href="invoices_list.php?">Back</a>

<?php include 'footer.php'; ?>